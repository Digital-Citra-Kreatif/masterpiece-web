<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\MediaUsage;
use ZipArchive;

/** Font per proyek */
final class ProjectFonts
{
    public const ROOT_FOLDER = 'Fonts';

    /** Field font */
    public const FIELDS = ['heading', 'description', 'button'];

    public const EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    /** Batas isi ZIP */
    public const ZIP_MAX_FILES = 50;

    public const ZIP_MAX_FILE_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private MoveMedia $moveMedia,
        private ProjectFolder $projectFolder,
        private UploadMedia $uploadMedia,
        private DeleteMedia $deleteMedia,
    ) {}

    /** Setelah simpan draft */
    public function handleRequest(Request $request): void
    {
        $route = $request->route();
        $entry = $route instanceof Route ? $route->parameter('entry') : null;
        if ($request->isMethodSafe() || ! $entry instanceof Entry || ! str_starts_with((string) $route->getName(), 'sunrice.admin.cms.entries.')) {
            return;
        }

        foreach ($entry->translations()->get() as $translation) {
            $this->handle($translation);
        }
    }

    public function handle(EntryTranslation $translation): void
    {
        $slug = $translation->draft_slug ?: $translation->slug;
        if ($translation->entry?->collection?->handle !== 'projects' || blank($slug)) {
            return;
        }

        $ids = $this->fontIds($translation->draft_data ?? [])->merge($this->fontIds($translation->data ?? []))->unique();
        $folder = $this->projectFolder->resolve($translation, $slug, self::ROOT_FOLDER, create: $ids->isNotEmpty());
        if ($folder === null) {
            return;
        }

        $entryId = (string) $translation->entry_id;
        $zips = Media::query()->whereKey($ids->all())->get()->filter(fn (Media $media): bool => self::isZip($media))->keyBy('id');
        if ($zips->isNotEmpty()) {
            $this->unpack($translation, $zips, $folder);
            $ids = $this->fontIds($translation->draft_data ?? [])->merge($this->fontIds($translation->data ?? []))->unique();
        }

        // Font proyek lain
        $media = Media::query()->whereKey($ids->all())->where(fn ($query) => $query->whereNull('folder_id')->orWhere('folder_id', '!=', $folder->id))->get()
            ->reject(fn (Media $font): bool => ProjectFolder::usedElsewhere($font->id, $entryId));
        $this->moveMedia->handle($media, $folder);
        $this->deleteUnusedZips($folder);
    }

    /** ZIP tak terpakai */
    private function deleteUnusedZips(MediaFolder $folder): void
    {
        foreach ($folder->media()->get()->filter(fn (Media $media): bool => self::isZip($media)) as $zip) {
            try {
                $this->deleteMedia->handle($zip);
            } catch (ValidationException) {
                // Masih dipakai
            }
        }
    }

    /**
     * Ganti ZIP field
     *
     * @param  Collection<int, Media>  $zips
     */
    private function unpack(EntryTranslation $translation, Collection $zips, MediaFolder $folder): void
    {
        $chosen = $zips->map(fn (Media $zip): ?int => self::regular($this->extract($zip, $folder)));
        $replace = function (?array $data) use ($chosen): ?array {
            foreach (self::FIELDS as $field) {
                $value = $data['global_style']['fonts'][$field] ?? null;
                // File per field
                $id = is_array($value) ? ($value[0] ?? null) : $value;
                if ($id === null) {
                    continue;
                }
                $data['global_style']['fonts'][$field] = $chosen->has((int) $id) ? $chosen->get((int) $id) : (int) $id;
            }

            return $data;
        };

        EntryTranslation::query()->whereKey($translation->id)->update([
            'data' => $translation->data === null ? null : json_encode($replace($translation->data), JSON_THROW_ON_ERROR),
            'draft_data' => json_encode($replace($translation->draft_data ?? []), JSON_THROW_ON_ERROR),
        ]);
        $translation->refresh();
        // Sinkron "Used by"
        MediaUsage::entry($translation);

        foreach ($zips as $zip) {
            try {
                $this->deleteMedia->handle($zip);
            } catch (ValidationException) {
                // Masih dipakai
            }
        }
    }

    /**
     * Unggah isi ZIP
     *
     * @return list<int> the new media ids
     */
    private function extract(Media $zip, MediaFolder $folder): array
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'fonts');
        file_put_contents($archivePath, Storage::disk($zip->disk)->get($zip->path));
        $archive = new ZipArchive;
        $ids = [];
        try {
            if ($archive->open($archivePath, ZipArchive::RDONLY) !== true) {
                return [];
            }
            for ($index = 0; $index < $archive->numFiles && count($ids) < self::ZIP_MAX_FILES; $index++) {
                $stat = $archive->statIndex($index);
                $name = basename(str_replace('\\', '/', (string) ($stat['name'] ?? '')));
                $isFont = in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
                if (! $isFont || str_starts_with($name, '.') || str_contains((string) $stat['name'], '__MACOSX') || ($stat['size'] ?? 0) > self::ZIP_MAX_FILE_BYTES) {
                    continue;
                }
                $contents = $archive->getFromIndex($index, self::ZIP_MAX_FILE_BYTES + 1);
                if ($contents === false || strlen($contents) > self::ZIP_MAX_FILE_BYTES) {
                    continue;
                }
                $fontPath = tempnam(sys_get_temp_dir(), 'font');
                file_put_contents($fontPath, $contents);
                try {
                    $ids[] = $this->uploadMedia->handle(new UploadedFile($fontPath, $name, null, null, true), ['folder_id' => $folder->id])->id;
                } catch (ValidationException) {
                    // File ditolak
                } finally {
                    @unlink($fontPath);
                }
            }
        } finally {
            $archive->close();
            @unlink($archivePath);
        }

        return $ids;
    }

    /**
     * Pilih font regular
     *
     * @param  list<int>  $ids
     */
    private static function regular(array $ids): ?int
    {
        $fonts = Media::query()->whereKey($ids)->orderBy('id')->get();
        $name = fn (Media $font): string => strtolower(pathinfo($font->filename, PATHINFO_FILENAME));
        $styled = '/(thin|hairline|extra|ultra|light|book|medium|semi|demi|bold|heavy|black|italic|oblique|condensed|narrow)/';

        return ($fonts->first(fn (Media $font): bool => str_contains($name($font), 'regular'))
            ?? $fonts->first(fn (Media $font): bool => preg_match($styled, $name($font)) !== 1)
            ?? $fonts->first())?->id;
    }

    private static function isZip(Media $media): bool
    {
        return strtolower(pathinfo($media->filename, PATHINFO_EXTENSION)) === 'zip';
    }

    /**
     * ID media font
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, int>
     */
    private function fontIds(array $data): Collection
    {
        return collect(self::FIELDS)
            ->flatMap(function (string $field) use ($data): array {
                $value = $data['global_style']['fonts'][$field] ?? null;

                return is_array($value) ? $value : [$value];
            })
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->values();
    }
}
