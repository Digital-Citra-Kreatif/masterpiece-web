<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\OrbitFramesController;
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
                Route::get('unit-points/{project}', [UnitPointsController::class, 'show'])->whereNumber('project')->name('unit-points.show');
                Route::put('unit-points/{project}', [UnitPointsController::class, 'update'])->whereNumber('project')->name('unit-points.update');
                Route::get('orbit-frames', OrbitFramesController::class)->name('orbit-frames');
            });
    }
}
