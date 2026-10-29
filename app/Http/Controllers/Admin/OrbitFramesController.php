<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ProjectTracks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Cms\Support\Access;

/** Gambar pertama orbit untuk picker titik */
class OrbitFramesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'view', 'projects'), 403);

        $frames = [];
        foreach (ProjectTracks::sections() as $section => $base) {
            $dir = trim($base, '/');
            $manifest = json_decode((string) @file_get_contents(public_path("{$dir}/manifest.json")), true);
            if (! is_array($manifest)) {
                continue;
            }
            // Acuan X/Y: frame 0
            $file = str_replace(['{size}', '{n}'], [array_key_first($manifest['sizes'] ?? ['lg' => 0]), '000'], (string) $manifest['pattern']);
            $frames[$section] = ['src' => asset("{$dir}/{$file}"), 'width' => (int) $manifest['width'], 'height' => (int) $manifest['height']];
        }

        return response()->json($frames);
    }
}
