<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Http\Controllers\MediaController;
use Sunrice\Admin\Media\Media;

/** Media picker tanpa batas 100 id */
class MediaPickerController extends Controller
{
    /** Batas id per permintaan */
    public const MAX_IDS = 500;

    public function __invoke(Request $request): JsonResponse
    {
        // Selain daftar id: bawaan Sunrice
        if (! $request->has('ids')) {
            return app(MediaController::class)->picker($request);
        }

        $user = $request->user();
        abort_unless($user instanceof Authorizable && $user->can('sunrice.media.view'), 403);

        /** @var array{ids: list<int>} $input */
        $input = $request->validate([
            'ids' => ['array', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer'],
        ]);
        $found = Media::query()->whereKey($input['ids'])->get()->keyBy('id');

        return response()->json(['items' => collect($input['ids'])
            ->map(fn (int|string $id): ?Media => $found->get((int) $id))
            ->filter()
            ->map(fn (Media $media): array => MediaController::describe($media))
            ->values()
            ->all()]);
    }
}
