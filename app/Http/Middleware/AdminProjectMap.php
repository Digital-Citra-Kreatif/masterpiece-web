<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Symfony\Component\HttpFoundation\Response;

/** Peta titik unit di form proyek */
class AdminProjectMap
{
    public const SCRIPT = 'cms/project-map.js';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya muatan halaman penuh
        if (! $response instanceof HtmlResponse || $request->header('X-Inertia') || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');
        if ($position !== false) {
            $version = @filemtime(public_path(self::SCRIPT)) ?: 0;
            $tag = '<script type="module" src="'.e(asset(self::SCRIPT)).'?v='.$version.'"></script>';
            // Respons asli dipertahankan
            $original = $response->original;
            $response->setContent(substr_replace($content, $tag, $position, 0));
            $response->original = $original;
        }

        return $response;
    }
}
