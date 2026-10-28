<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Ikon sidebar CMS */
class AdminNavigationIcons
{
    /** @var array<string, string> navigation item key => icon */
    public const ICONS = [
        'cms-collection-types' => 'home',
        'cms-collection-units' => 'clipboard-list',
    ];

    /**
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
