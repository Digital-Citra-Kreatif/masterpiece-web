<?php

namespace App\Http\Middleware;

use App\Support\Content;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Halaman maintenance */
class MaintenanceMode
{
    /** Pengecualian maintenance */
    private const OPEN = ['admin', 'admin/*', 'up'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Admin tetap masuk
        if ($request->is(...self::OPEN) || $request->user() !== null) {
            return $next($request);
        }

        $maintenance = Content::maintenance();
        if ($maintenance === null) {
            return $next($request);
        }

        return response()->view('errors.maintenance', ['mt' => $maintenance], 503);
    }
}
