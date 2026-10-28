<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Admin\UnitPointsController;
use App\Http\Middleware\AdminNavigationIcons;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Support\Access;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')
            ->brand('PT Masterpiece Visual Production')
            ->middleware([AdminNavigationIcons::class])
            ->plugin(CmsPlugin::make())
            ->navigation(NavigationItem::make('unit-points')->label('Titik unit')->url('unit-points')->icon('map-pin')
                ->group('Content')->sort(11)->visible(fn (Authorizable $user): bool => Access::entries($user, 'view', 'projects')))
            ->routes(function (): void {
                Route::get('unit-points', [UnitPointsController::class, 'index'])->name('unit-points.index');
                Route::put('unit-points/{project}', [UnitPointsController::class, 'update'])->whereNumber('project')->name('unit-points.update');
            })
            ->bundle(PluginBundle::make('app')->module(self::asset('cms/unit-points.js'))->pages('App/'));
    }

    /** URL aset berversi */
    private static function asset(string $path): string
    {
        return asset($path).'?v='.(@filemtime(public_path($path)) ?: 0);
    }
}
