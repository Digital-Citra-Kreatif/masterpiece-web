<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Actions\Media\MoveMedia;
use Sunrice\Admin\Actions\Media\SaveMediaFolder;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Admin\Media\MediaUsage as MediaUsageRecord;
use Sunrice\Cms\Models\EntryTranslation;

/** Folder media proyek */
final class ProjectFolder
{
    public function __construct(
        private MoveMedia $moveMedia,
        private SaveMediaFolder $saveFolder,
    ) {}

    /** Folder {root}/{slug} */
    public function resolve(EntryTranslation $translation, string $slug, string $root, bool $create): ?MediaFolder
    {
        $rootFolder = MediaFolder::query()->whereNull('parent_id')->where('name', $root)->first();
        if ($rootFolder === null && ! $create) {
            return null;
        }
        $rootFolder ??= $this->saveFolder->handle($root);

        $earlierSlugs = DB::table('sunrice_revisions')->where('entry_translation_id', $translation->id)->pluck('slug')
            ->push($translation->slug, $translation->draft_slug, $translation->getOriginal('slug'), $translation->getOriginal('draft_slug'))
            ->filter()
            ->unique()
            ->reject(fn (string $earlier): bool => $earlier === $slug || self::slugTakenByAnother($earlier, $translation));
        $earlier = MediaFolder::query()->where('parent_id', $rootFolder->id)->whereIn('name', $earlierSlugs->all())->orderBy('id')->get();
        $folder = MediaFolder::query()->where('parent_id', $rootFolder->id)->where('name', $slug)->first();

        if ($folder === null && $earlier->isNotEmpty()) {
            $folder = $this->saveFolder->handle($slug, null, $earlier->shift());
        }
        foreach ($earlier as $old) {
            $this->moveMedia->handle($old->media()->get(), $folder);
            if (! $old->children()->exists()) {
                $old->delete();
            }
        }

        return $folder ?? ($create ? $this->saveFolder->handle($slug, $rootFolder) : null);
    }

    /**
     * Dipakai entri lain
     *
     * @param  int|Collection<int, int>  $mediaIds
     */
    public static function usedElsewhere(int|Collection $mediaIds, string $entryId): bool
    {
        return MediaUsageRecord::query()->whereIn('media_id', collect($mediaIds)->all())
            ->where(fn ($query) => $query->where('type', '!=', 'cms.entry')->orWhere('item_id', '!=', $entryId))
            ->exists();
    }

    /** Slug proyek lain */
    private static function slugTakenByAnother(string $slug, EntryTranslation $translation): bool
    {
        return EntryTranslation::query()->where('collection_id', $translation->collection_id)->where('entry_id', '!=', $translation->entry_id)
            ->where(fn ($query) => $query->where('slug', $slug)->orWhere('draft_slug', $slug))
            ->exists();
    }
}
