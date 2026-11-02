<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Halaman selalu dicek ulang */
class RevalidatePages
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || ! $this->isHtml($response)) {
            return $response;
        }

        // Data titik inline
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate, private');
        // Lewati cache LiteSpeed
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');

        if ($response->isSuccessful()) {
            $response->setEtag(hash('xxh128', (string) $response->getContent()));
            $response->isNotModified($request);
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
