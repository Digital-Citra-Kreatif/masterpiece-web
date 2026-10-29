<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrbitFrames;
use App\Support\OrbitFrames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Support\Access;

/** Unggah ZIP frame orbit per potongan */
class OrbitUploadController extends Controller
{
    /** Ukuran potongan dari browser */
    public const CHUNK_BYTES = 1024 * 1024;

    /** Batas ZIP */
    public const MAX_CHUNKS = 2048;

    public function show(Request $request, int $project, string $section): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'view', 'projects'), 403);
        self::ensureProject($project);

        return response()->json(self::state($project, $section));
    }

    public function store(Request $request, int $project, string $section): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'update', 'projects'), 403);
        self::ensureProject($project);
        $input = $request->validate([
            'upload' => ['required', 'uuid'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CHUNKS],
            'chunk' => ['required', 'file', 'max:'.(int) (self::CHUNK_BYTES / 1024 * 2)],
        ]);

        $disk = Storage::disk('local');
        $part = "orbit-uploads/{$input['upload']}.part";
        $meta = "orbit-uploads/{$input['upload']}.json";
        $index = (int) $input['index'];
        $total = (int) $input['total'];
        if ($index === 0) {
            $disk->delete($part);
            $disk->put($meta, json_encode(['project' => $project, 'section' => $section, 'total' => $total, 'next' => 0]));
        }
        $state = json_decode((string) $disk->get($meta), true);
        // Potongan harus berurutan
        if (! is_array($state) || $state['project'] !== $project || $state['section'] !== $section || $state['total'] !== $total || $state['next'] !== $index || $index >= $total) {
            return response()->json(['message' => 'Urutan potongan tidak cocok. Ulangi unggah ZIP.'], 409);
        }

        file_put_contents($disk->path($part), (string) file_get_contents($input['chunk']->getRealPath()), FILE_APPEND);
        $state['next'] = $index + 1;
        if ($state['next'] < $total) {
            $disk->put($meta, json_encode($state));

            return response()->json(['received' => $state['next']]);
        }

        // Potongan terakhir: proses
        $disk->delete($meta);
        $zip = OrbitFrames::upload($project, $section).'.zip';
        $disk->delete($zip);
        $disk->move($part, $zip);
        OrbitFrames::setStatus($project, $section, 'queued');
        ProcessOrbitFrames::dispatch($project, $section);

        return response()->json(self::state($project, $section));
    }

    public function destroy(Request $request, int $project, string $section): JsonResponse
    {
        abort_unless(Access::entries($request->user(), 'update', 'projects'), 403);
        self::ensureProject($project);
        OrbitFrames::clear($project, $section);
        ProcessOrbitFrames::retrack($project);

        return response()->json(self::state($project, $section));
    }

    /**
     * Status dan frame aktif
     *
     * @return array{state: string, message: ?string, count: ?int, active: bool}
     */
    private static function state(int $project, string $section): array
    {
        return [...OrbitFrames::status($project, $section), 'active' => OrbitFrames::base($project, $section) !== null];
    }

    private static function ensureProject(int $project): void
    {
        abort_unless(EntryTranslation::query()->whereRelation('entry.collection', 'handle', 'projects')->where('entry_id', $project)->exists(), 404);
    }
}
