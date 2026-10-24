<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AdminNavigationIcons;
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
            ->middleware([AdminNavigationIcons::class])
            ->plugin(CmsPlugin::make());
    }
}
