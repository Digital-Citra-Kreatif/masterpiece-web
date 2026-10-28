<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\Access;
use Sunrice\Cms\Support\Locales;

/** Titik unit di siteplan proyek */
class UnitPointsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(Access::entries($request->user(), 'view', 'units'), 403);

        $projects = self::translations('projects')->get();
        $project = $projects->firstWhere('entry_id', $request->integer('project')) ?? $projects->first();

        return Inertia::render('App/UnitPoints', [
            'projects' => $projects->map(fn (EntryTranslation $item): array => ['id' => $item->entry_id, 'title' => self::title($item)])->values(),
            'project' => $project === null ? null : [
                'id' => $project->entry_id,
                'title' => self::title($project),
                'siteplan' => self::siteplan($project),
                'editUrl' => route('sunrice.admin.cms.collections.entries.edit', ['collection' => 'projects', 'entry' => $project->entry_id]),
            ],
            'units' => $project === null ? [] : self::units($project->entry_id)
                ->map(fn (EntryTranslation $unit): array => [
                    'id' => $unit->entry_id,
                    'code' => self::title($unit),
                    'status' => $unit->draft_data['sale_status'] ?? 'available',
                    'x' => is_numeric($unit->draft_data['x'] ?? null) ? (float) $unit->draft_data['x'] : null,
                    'y' => is_numeric($unit->draft_data['y'] ?? null) ? (float) $unit->draft_data['y'] : null,
                    'editUrl' => route('sunrice.admin.cms.collections.entries.edit', ['collection' => 'units', 'entry' => $unit->entry_id]),
                ])
                ->sortBy('code', SORT_NATURAL)
                ->values(),
            'createUrl' => route('sunrice.admin.cms.collections.entries.create', ['collection' => 'units']),
            'canUpdate' => Access::entries($request->user(), 'update', 'units'),
        ]);
    }

    public function update(Request $request, int $project, SaveDraft $saveDraft, PublishEntry $publish): RedirectResponse
    {
        abort_unless(Access::entries($request->user(), 'update', 'units'), 403);

        $validated = $request->validate([
            'points' => ['required', 'array'],
            'points.*.id' => ['required', 'integer'],
            'points.*.x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'points.*.y' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $units = self::units($project)->keyBy('entry_id');
        $canPublish = Access::entries($request->user(), 'publish', 'units');
        $saved = 0;

        foreach ($validated['points'] as $point) {
            $unit = $units->get($point['id']) ?? abort(422);
            $x = isset($point['x']) ? round((float) $point['x'], 2) : null;
            $y = isset($point['y']) ? round((float) $point['y'], 2) : null;
            if ($x === null || $y === null) {
                [$x, $y] = [null, null];
            }
            if (($unit->draft_data['x'] ?? null) == $x && ($unit->draft_data['y'] ?? null) == $y) {
                continue;
            }

            $draft = $saveDraft->handle($unit, ['data' => ['x' => $x, 'y' => $y], 'version' => $unit->version]);
            // Unit tayang langsung diperbarui
            if ($canPublish && $unit->isPublished()) {
                $publish->handle($draft);
            }
            $saved++;
        }

        return back()->with('success', $saved === 0 ? 'Tidak ada titik yang berubah.' : "{$saved} titik unit disimpan.");
    }

    /**
     * Terjemahan utama koleksi
     *
     * @return Builder<EntryTranslation>
     */
    private static function translations(string $collection): Builder
    {
        return EntryTranslation::query()
            ->whereRelation('entry.collection', 'handle', $collection)
            ->where('locale', Locales::default())
            ->orderBy('entry_id');
    }

    /**
     * Unit milik proyek
     *
     * @return Collection<int, EntryTranslation>
     */
    private static function units(int $project): Collection
    {
        return self::translations('units')->where('draft_data->project', $project)->get();
    }

    private static function title(EntryTranslation $translation): string
    {
        return (string) ($translation->draft_title ?? $translation->title);
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
