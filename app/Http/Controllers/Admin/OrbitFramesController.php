<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\OrbitFrames;
use App\Support\ProjectTracks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\Access;
use Sunrice\Cms\Support\Locales;

/** Gambar pertama orbit untuk picker titik */
class OrbitFramesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'view', 'projects'), 403);

        $project = $request->integer('project');
        $data = self::draft($project);
        $frames = [];
        foreach ($project > 0 ? ProjectTracks::bases($project) : ProjectTracks::sections() as $section => $base) {
            // Gambar diam dari CMS, kalah oleh frame ZIP
            $uploaded = $base !== ProjectTracks::sections()[$section];
            $media = ! $uploaded && is_int($data[$section]['image'] ?? null) ? Media::query()->find($data[$section]['image']) : null;
            if ($media instanceof Media && $media->isImage()) {
                $frames[$section] = ['src' => $media->url('large'), 'width' => (int) $media->width, 'height' => (int) $media->height];

                continue;
            }
            $dir = trim($base, '/');
            $manifest = json_decode((string) @file_get_contents(OrbitFrames::directory($base).'/manifest.json'), true);
            if (! is_array($manifest)) {
                continue;
            }
            // Acuan X/Y: frame 0
            $file = str_replace(['{size}', '{n}'], [array_key_first($manifest['sizes'] ?? ['lg' => 0]), '000'], (string) $manifest['pattern']);
            $frames[$section] = ['src' => asset("{$dir}/{$file}"), 'width' => (int) $manifest['width'], 'height' => (int) $manifest['height']];
        }

        return response()->json($frames);
    }

    /**
     * Data draf proyek
     *
     * @return array<string, mixed>
     */
    private static function draft(int $project): array
    {
        if ($project < 1) {
            return [];
        }
        $translation = EntryTranslation::query()
            ->whereRelation('entry.collection', 'handle', 'projects')
            ->where('entry_id', $project)
            ->where('locale', Locales::default())
            ->first();

        return ($translation?->draft_data ?: $translation?->data) ?? [];
    }
}
