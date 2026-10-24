<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the site's CMS collections their own sidebar icons.
 *
 * The CMS gives every collection the same icon and the panel has no setting for
 * it, so this wraps the navigation the panel shares (it runs after the panel's own
 * middleware) and swaps the icon of the items named here. Icons must be names the
 * panel's sidebar knows (vendor/sunrice/admin/resources/js/components/app/nav-icon.tsx);
 * unknown names show as a dot.
 */
class AdminNavigationIcons
{
    /** @var array<string, string> navigation item key => icon */
    public const ICONS = [
        'cms-collection-types' => 'home',
        'cms-collection-facilities' => 'map-pin',
        'cms-collection-units' => 'clipboard-list',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $navigation = Inertia::getShared('navigation');

        if ($navigation instanceof Closure) {
            Inertia::share('navigation', fn (): array => array_map(
                fn (array $group): array => [...$group, 'items' => array_map(self::withIcon(...), $group['items'])],
                $navigation(),
            ));
        }

        return $next($request);
    }

    /**
     * @param  array{key: string, icon: string, children?: list<array{key: string, icon: string}>}  $item
     * @return array{key: string, icon: string, children?: list<array{key: string, icon: string}>}
     */
    private static function withIcon(array $item): array
    {
        $item['icon'] = self::ICONS[$item['key']] ?? $item['icon'];

        if (isset($item['children'])) {
            $item['children'] = array_map(self::withIcon(...), $item['children']);
        }

        return $item;
    }
}
