<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\MediaPickerController;
use App\Http\Controllers\Admin\OrbitFramesController;
use App\Http\Controllers\Admin\OrbitUploadController;
use App\Http\Controllers\Admin\UnitPointsController;
use App\Http\Middleware\AdminNavigationIcons;
use App\Http\Middleware\AdminProjectMap;
use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')
            ->brand('PT Masterpiece Visual Production')
            ->middleware([AdminNavigationIcons::class, AdminProjectMap::class])
            ->plugin(CmsPlugin::make())
            ->routes(function (): void {
                // Ganti picker bawaan
                Route::get('media/picker', MediaPickerController::class)->name('media.picker');
                Route::get('unit-points/{project}', [UnitPointsController::class, 'show'])->whereNumber('project')->name('unit-points.show');
                Route::put('unit-points/{project}', [UnitPointsController::class, 'update'])->whereNumber('project')->name('unit-points.update');
                Route::get('orbit-frames', OrbitFramesController::class)->name('orbit-frames');
                Route::get('orbit-upload/{project}/{section}', [OrbitUploadController::class, 'show'])->whereNumber('project')->whereIn('section', ['masterplan', 'cluster'])->name('orbit-upload.show');
                Route::post('orbit-upload/{project}/{section}', [OrbitUploadController::class, 'store'])->whereNumber('project')->whereIn('section', ['masterplan', 'cluster'])->name('orbit-upload.store');
                Route::delete('orbit-upload/{project}/{section}', [OrbitUploadController::class, 'destroy'])->whereNumber('project')->whereIn('section', ['masterplan', 'cluster'])->name('orbit-upload.destroy');
            });
    }
}
