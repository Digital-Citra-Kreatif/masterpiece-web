<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Actions\Media\SaveMediaFolder;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;

/**
 * Keeps each project's custom fonts together in the media library: whenever a
 * Projects entry is saved (draft or live), the files in its Global Style font
 * fields move to the folder Fonts / {project slug}, created when missing. The
 * slug is the live one, or the draft's while the project isn't published yet.
 */
final class ProjectFonts
{
    public const ROOT_FOLDER = 'Fonts';

    /** The Global Style fields that hold font files. */
    public const FIELDS = ['font_heading', 'font_description', 'font_button'];

    public function __construct(private MoveMedia $moveMedia, private SaveMediaFolder $saveFolder) {}

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
        $slug = $translation->slug ?: $translation->draft_slug;
        if ($translation->entry?->collection?->handle !== 'projects' || blank($slug)) {
            return;
        }

        $ids = $this->fontIds($translation->draft_data ?? [])->merge($this->fontIds($translation->data ?? []))->unique();
        if ($ids->isEmpty()) {
            return;
        }

        $folder = $this->folder($slug, $this->folder(self::ROOT_FOLDER));
        $media = Media::query()->whereKey($ids->all())->where(fn ($query) => $query->whereNull('folder_id')->orWhere('folder_id', '!=', $folder->id))->get();
        $this->moveMedia->handle($media, $folder);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, int>
     */
    private function fontIds(array $data): Collection
    {
        return collect(self::FIELDS)
            ->map(fn (string $field): mixed => $data['global_style'][$field] ?? null)
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->values();
    }

    private function folder(string $name, ?MediaFolder $parent = null): MediaFolder
    {
        return MediaFolder::query()->where('parent_id', $parent?->id)->where('name', $name)->first()
            ?? $this->saveFolder->handle($name, $parent);
    }
}
