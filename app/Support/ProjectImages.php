<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;

/** Gambar section proyek */
final class ProjectImages
{
    /** Section ke folder */
    public const SECTIONS = ['masterplan' => 'Masterplan', 'cluster' => 'Cluster'];

    public function __construct(
        private MoveMedia $moveMedia,
        private ProjectFolder $projectFolder,
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

        $entryId = (string) $translation->entry_id;
        foreach (self::SECTIONS as $section => $root) {
            $ids = self::imageIds($translation->draft_data ?? [], $section)->merge(self::imageIds($translation->data ?? [], $section))->unique()->values();
            // Hanya milik proyek
            $media = Media::query()->whereKey($ids->all())->get()
                ->reject(fn (Media $image): bool => ProjectFolder::usedElsewhere($image->id, $entryId));
            $folder = $this->projectFolder->resolve($translation, $slug, $root, create: $media->isNotEmpty());
            if ($folder === null) {
                continue;
            }

            $this->moveMedia->handle($media->reject(fn (Media $image): bool => $image->folder_id === $folder->id)->values(), $folder);
        }
    }

    /**
     * ID gambar section
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, int>
     */
    private static function imageIds(array $data, string $section): Collection
    {
        $values = [$data[$section]['image'] ?? null];
        foreach ((array) ($data[$section]['points'] ?? []) as $point) {
            $values[] = is_array($point) ? ($point['image'] ?? null) : null;
        }

        return collect($values)
            ->flatMap(fn (mixed $value): array => is_array($value) ? $value : [$value])
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->values();
    }
}
