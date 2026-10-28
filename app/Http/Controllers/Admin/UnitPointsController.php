<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnitPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\Access;
use Sunrice\Cms\Support\Locales;

/** Peta titik unit di form proyek */
class UnitPointsController extends Controller
{
    public function show(Request $request, int $project): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'view', 'projects'), 403);

        $translation = self::translation('projects')->where('entry_id', $project)->firstOrFail();
        $points = UnitPoint::query()->where('project_id', $project)->get()->keyBy('unit_id');

        return response()->json([
            'siteplan' => self::siteplan($translation),
            'units' => self::units($project)
                ->map(fn (EntryTranslation $unit): array => [
                    'id' => $unit->entry_id,
                    'code' => (string) ($unit->draft_title ?? $unit->title),
                    'status' => $unit->draft_data['sale_status'] ?? 'available',
                    'x' => $points->get($unit->entry_id)?->x,
                    'y' => $points->get($unit->entry_id)?->y,
                    'editUrl' => route('sunrice.admin.cms.collections.entries.edit', ['collection' => 'units', 'entry' => $unit->entry_id]),
                ])
                ->sortBy('code', SORT_NATURAL)
                ->values(),
            'createUrl' => route('sunrice.admin.cms.collections.entries.create', ['collection' => 'units']),
            'canUpdate' => Access::entries($request->user(), 'update', 'projects'),
        ]);
    }

    public function update(Request $request, int $project): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'update', 'projects'), 403);

        $validated = $request->validate([
            'points' => ['required', 'array'],
            'points.*.id' => ['required', 'integer'],
            'points.*.x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'points.*.y' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $units = self::units($project)->keyBy('entry_id');
        foreach ($validated['points'] as $point) {
            abort_unless($units->has($point['id']), 422);
        }

        foreach ($validated['points'] as $point) {
            if (isset($point['x'], $point['y'])) {
                UnitPoint::query()->updateOrCreate(['unit_id' => $point['id']], [
                    'project_id' => $project,
                    'x' => round((float) $point['x'], 2),
                    'y' => round((float) $point['y'], 2),
                ]);
            } else {
                UnitPoint::query()->where('unit_id', $point['id'])->delete();
            }
        }

        return response()->json(['message' => count($validated['points']).' titik unit disimpan.']);
    }

    /** @return Builder<EntryTranslation> */
    private static function translation(string $collection): Builder
    {
        return EntryTranslation::query()
            ->whereRelation('entry.collection', 'handle', $collection)
            ->where('locale', Locales::default());
    }

    /**
     * Unit milik proyek
     *
     * @return Collection<int, EntryTranslation>
     */
    private static function units(int $project): Collection
    {
        return self::translation('units')->where('draft_data->project', $project)->get();
    }

    /** @return array{src: string, width: int, height: int}|null */
    private static function siteplan(EntryTranslation $project): ?array
    {
        $values = $project->values(new HydrationContext($project->locale, preview: true));
        $siteplan = $values['availability']['siteplan'] ?? null;

        if (! $siteplan instanceof Media || ! $siteplan->isImage()) {
            return null;
        }

        return ['src' => $siteplan->url('large'), 'width' => (int) $siteplan->width, 'height' => (int) $siteplan->height];
    }
}
