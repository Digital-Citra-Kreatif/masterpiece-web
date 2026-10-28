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
        abort_unless(Access::entries($request->user(), 'view', 'projects'), 403);

        $projects = self::translations('projects')->get();
        $project = $projects->firstWhere('entry_id', $request->integer('project') ?: self::fromEditor($request)) ?? $projects->first();
        $points = $project === null ? collect() : self::points($project);

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
                    'x' => $points[$unit->entry_id]['x'] ?? null,
                    'y' => $points[$unit->entry_id]['y'] ?? null,
                    'editUrl' => route('sunrice.admin.cms.collections.entries.edit', ['collection' => 'units', 'entry' => $unit->entry_id]),
                ])
                ->sortBy('code', SORT_NATURAL)
                ->values(),
            'createUrl' => route('sunrice.admin.cms.collections.entries.create', ['collection' => 'units']),
            'canUpdate' => Access::entries($request->user(), 'update', 'projects'),
        ]);
    }

    public function update(Request $request, int $project, SaveDraft $saveDraft, PublishEntry $publish): RedirectResponse
    {
        abort_unless(Access::entries($request->user(), 'update', 'projects'), 403);

        $validated = $request->validate([
            'points' => ['required', 'array'],
            'points.*.id' => ['required', 'integer'],
            'points.*.x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'points.*.y' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $translation = self::translations('projects')->where('entry_id', $project)->firstOrFail();
        $units = self::units($project)->keyBy('entry_id');
        $points = self::points($translation);
        foreach ($validated['points'] as $point) {
            abort_unless($units->has($point['id']), 422);
            if (isset($point['x'], $point['y'])) {
                $points[$point['id']] = ['x' => round((float) $point['x'], 2), 'y' => round((float) $point['y'], 2)];
            } else {
                unset($points[$point['id']]);
            }
        }

        // Baris lama dipertahankan
        $ids = collect($translation->draft_data['availability']['points'] ?? [])->filter(fn (mixed $row): bool => is_array($row))->pluck('_id', 'unit');
        $rows = $units->keys()
            ->filter(fn (int $unit): bool => isset($points[$unit]))
            ->map(fn (int $unit): array => array_filter(['_id' => $ids[$unit] ?? null, 'unit' => $unit, ...$points[$unit]], fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();

        $pending = $translation->hasUnpublishedChanges();
        $availability = $translation->draft_data['availability'] ?? [];
        $draft = $saveDraft->handle($translation, ['data' => ['availability' => [...$availability, 'points' => $rows]], 'version' => $translation->version]);

        // Draf lain tidak ikut terbit
        if ($translation->isPublished() && ! $pending && Access::entries($request->user(), 'publish', 'projects')) {
            $publish->handle($draft);

            return back()->with('success', 'Titik unit disimpan dan tayang.');
        }

        return back()->with('success', 'Titik unit disimpan sebagai draf proyek. Terbitkan dari halaman proyek.');
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

    /**
     * Titik tersimpan di proyek
     *
     * @return \Illuminate\Support\Collection<int, array{x: float, y: float}>
     */
    private static function points(EntryTranslation $project): \Illuminate\Support\Collection
    {
        return collect($project->draft_data['availability']['points'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && is_int($row['unit'] ?? null) && is_numeric($row['x'] ?? null) && is_numeric($row['y'] ?? null))
            ->mapWithKeys(fn (array $row): array => [$row['unit'] => ['x' => (float) $row['x'], 'y' => (float) $row['y']]]);
    }

    /** Proyek dari editor asal */
    private static function fromEditor(Request $request): ?int
    {
        $pattern = '#/cms/collections/projects/entries/(\d+)/edit#';

        return preg_match($pattern, (string) $request->headers->get('referer'), $match) === 1 ? (int) $match[1] : null;
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
