<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages opened through another address than APP_URL (an ngrok link on a phone, a LAN IP) load
 * the built assets in public/build, even while the Vite dev server runs: its public/hot points
 * at wrj-hris.test:5173, which other devices cannot reach. On APP_URL itself nothing changes.
 */
class UseBuiltAssetsForOtherHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            Vite::useHotFile(storage_path('framework/vite-hot-disabled'));
        }

        return $next($request);
    }
}
