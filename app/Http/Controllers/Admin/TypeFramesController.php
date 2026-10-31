<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ProjectPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\Access;
use Sunrice\Cms\Support\Locales;

/** Gambar awal tiap tab 360° Tipe untuk picker titik */
class TypeFramesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'view', 'types'), 403);

        $translation = EntryTranslation::query()
            ->whereRelation('entry.collection', 'handle', 'types')
            ->where('entry_id', $request->integer('type'))
            ->where('locale', Locales::default())
            ->first();
        // Draf bila ada, media sudah jadi objek
        $data = $translation ? $translation->values(new HydrationContext($translation->locale, preview: filled($translation->draft_data))) : [];
        // Nama kosong tetap dihitung agar urutan sama dengan repeater
        $tabs = ProjectPage::turntableTabs(['tabs' => collect($data['turntable']['tabs'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => blank($row['name'] ?? null) ? [...$row, 'name' => '-'] : $row)
            ->values()
            ->all()]);

        return response()->json(array_map(fn (array $tab): array => [
            'src' => $tab['frames'][$tab['frame']] ?? $tab['frames'][0],
            'width' => $tab['width'],
            'height' => $tab['height'],
        ], $tabs));
    }
}
