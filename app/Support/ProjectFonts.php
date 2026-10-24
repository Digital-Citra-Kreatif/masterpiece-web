<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Actions\Media\SaveMediaFolder;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Admin\Media\MediaUsage as MediaUsageRecord;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\MediaUsage;
use ZipArchive;

/**
 * Keeps each project's custom fonts together in the media library: whenever a
 * Projects entry is saved (draft or live), the files in its Global Style font
 * fields move to the folder Fonts / {project slug}, created when missing, and
 * renamed with the slug (the latest one, draft or live): one folder per project.
 *
 * A ZIP in a font field is unpacked: its font files (every weight) are uploaded
 * to that folder, the regular one takes the ZIP's place in the field (the editor
 * can choose another from the folder) and the ZIP is deleted.
 * Other files in it (licences, previews) are skipped, and so are folders'
 * names: only each file's own name is kept.
 */
final class ProjectFonts
{
    public const ROOT_FOLDER = 'Fonts';

    /** The Global Style fields that hold font files. */
    public const FIELDS = ['heading', 'description', 'button'];

    public const EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    /** Limits on what a ZIP may unpack to. */
    public const ZIP_MAX_FILES = 50;

    public const ZIP_MAX_FILE_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private MoveMedia $moveMedia,
        private SaveMediaFolder $saveFolder,
        private UploadMedia $uploadMedia,
        private DeleteMedia $deleteMedia,
    ) {}

    /**
     * After an admin request changed an entry. Saving a draft writes with a query
     * update, which fires no model events, so this catches what `saved` misses.
     */
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
        $folder = $this->projectFolder($translation, $slug, create: $ids->isNotEmpty());
        if ($folder === null) {
            return;
        }

        $entryId = (string) $translation->entry_id;
        $zips = Media::query()->whereKey($ids->all())->get()->filter(fn (Media $media): bool => self::isZip($media))->keyBy('id');
        if ($zips->isNotEmpty()) {
            $this->unpack($translation, $zips, $folder);
            $ids = $this->fontIds($translation->draft_data ?? [])->merge($this->fontIds($translation->data ?? []))->unique();
        }

        // A font picked from another project's folder stays there.
        $media = Media::query()->whereKey($ids->all())->where(fn ($query) => $query->whereNull('folder_id')->orWhere('folder_id', '!=', $folder->id))->get()
            ->reject(fn (Media $font): bool => self::usedElsewhere($font->id, $entryId));
        $this->moveMedia->handle($media, $folder);
        $this->deleteUnusedZips($folder);
    }

    /** Whether something other than this entry uses the file. */
    private static function usedElsewhere(int|Collection $mediaIds, string $entryId): bool
    {
        return MediaUsageRecord::query()->whereIn('media_id', collect($mediaIds)->all())
            ->where(fn ($query) => $query->where('type', '!=', 'cms.entry')->orWhere('item_id', '!=', $entryId))
            ->exists();
    }

    /**
     * The project's one folder under Fonts, named after its slug. When the
     * slug changes, the folder named after an earlier slug of the project (its
     * revisions keep them) is renamed; folders of several earlier slugs are
     * merged into it and the emptied ones go. An earlier slug another project
     * now has is that project's folder, left alone. Without a folder and with
     * no fonts to put in one ($create false), none is made.
     */
    private function projectFolder(EntryTranslation $translation, string $slug, bool $create): ?MediaFolder
    {
        $root = MediaFolder::query()->whereNull('parent_id')->where('name', self::ROOT_FOLDER)->first();
        if ($root === null && ! $create) {
            return null;
        }
        $root ??= $this->saveFolder->handle(self::ROOT_FOLDER);

        $earlierSlugs = DB::table('sunrice_revisions')->where('entry_translation_id', $translation->id)->pluck('slug')
            ->push($translation->slug, $translation->draft_slug, $translation->getOriginal('slug'), $translation->getOriginal('draft_slug'))
            ->filter()
            ->unique()
            ->reject(fn (string $earlier): bool => $earlier === $slug || self::slugTakenByAnother($earlier, $translation));
        $earlier = MediaFolder::query()->where('parent_id', $root->id)->whereIn('name', $earlierSlugs->all())->orderBy('id')->get();
        $folder = MediaFolder::query()->where('parent_id', $root->id)->where('name', $slug)->first();

        if ($folder === null && $earlier->isNotEmpty()) {
            $folder = $this->saveFolder->handle($slug, null, $earlier->shift());
        }
        foreach ($earlier as $old) {
            $this->moveMedia->handle($old->media()->get(), $folder);
            if (! $old->children()->exists()) {
                $old->delete();
            }
        }

        return $folder ?? ($create ? $this->saveFolder->handle($slug, $root) : null);
    }

    /** Whether another project now has this slug (its folder's name). */
    private static function slugTakenByAnother(string $slug, EntryTranslation $translation): bool
    {
        return EntryTranslation::query()->where('collection_id', $translation->collection_id)->where('entry_id', '!=', $translation->entry_id)
            ->where(fn ($query) => $query->where('slug', $slug)->orWhere('draft_slug', $slug))
            ->exists();
    }

    /** ZIPs left in a project's folder that nothing uses any more: their fonts were unpacked. */
    private function deleteUnusedZips(MediaFolder $folder): void
    {
        foreach ($folder->media()->get()->filter(fn (Media $media): bool => self::isZip($media)) as $zip) {
            try {
                $this->deleteMedia->handle($zip);
            } catch (ValidationException) {
                // Still used somewhere: it stays.
            }
        }
    }

    /**
     * Replaces each ZIP in the font fields (draft and live) with one of its
     * fonts, the regular one when it has it: the others wait in the project's
     * folder for the editor to choose instead.
     *
     * @param  Collection<int, Media>  $zips
     */
    private function unpack(EntryTranslation $translation, Collection $zips, MediaFolder $folder): void
    {
        $chosen = $zips->map(fn (Media $zip): ?int => self::regular($this->extract($zip, $folder)));
        $replace = function (?array $data) use ($chosen): ?array {
            foreach (self::FIELDS as $field) {
                $value = $data['global_style']['fonts'][$field] ?? null;
                // One file per field (a list is from when fields held several).
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
        // A query update fires no model events: keep the media library's "Used by" right, so the ZIPs are free to delete.
        MediaUsage::entry($translation);

        foreach ($zips as $zip) {
            try {
                $this->deleteMedia->handle($zip);
            } catch (ValidationException) {
                // Still used somewhere else: it stays.
            }
        }
    }

    /**
     * Uploads the font files in a ZIP to the folder.
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
                    // A file the media library refuses: left out.
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
     * The regular weight among a family's files: named "Regular", else one with
     * no weight or style in its name (Candara.ttf), else the first.
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
     * The media ids in the font fields: one file or several each.
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
