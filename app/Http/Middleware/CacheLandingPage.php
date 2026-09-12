<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side HTML cache for the public landing pages.
 */
class CacheLandingPage
{
    // Caches the page for 7 days
    private const TTL_SECONDS = 604800;

    public function handle(Request $request, Closure $next): Response
    {
        $isLandingPage = $request->routeIs('home', 'demo.*');

        if (! $request->isMethod('GET') || $request->user() || ! $isLandingPage) {
            return $next($request);
        }

        $pathKey = str_replace('/', '_', $request->path());

        // The pricing variant is chosen by ?mode=tutor, so it has to be part of
        // the key. Only this parameter is included: keying on the whole query
        // string would fragment the cache across every utm_* and fbclid value.
        $variant = $request->query('mode') === 'tutor' ? 'tutor' : 'self';
        $cacheKey = 'landing_page_html_'.config('analytics.mode')."_{$pathKey}_{$variant}:".self::manifestVersion();

        if (Cache::has($cacheKey)) {
            /** @var string $html */
            $html = Cache::get($cacheKey);

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            Cache::put($cacheKey, $response->getContent(), self::TTL_SECONDS);
        }

        return $response;
    }

    private static function manifestVersion(): string
    {
        $manifest = public_path('build/manifest.json');

        if (file_exists($manifest)) {
            return (string) filemtime($manifest);
        }

        return 'dev';
    }
}
