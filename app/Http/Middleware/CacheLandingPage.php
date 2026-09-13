<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side HTML cache for the public landing pages.
 *
 * The rendered HTML carries per-visitor values: the CSRF token the tracker
 * sends to /analytics/track, the pbm_vid visitor id and the request URI.
 * They are swapped for placeholders before caching and filled back in for
 * every request, otherwise all visitors would share the first visitor's
 * token (every tracking request fails with 419) and visitor id.
 */
class CacheLandingPage
{
    // Caches the page for 7 days
    private const TTL_SECONDS = 604800;

    private const CSRF_PLACEHOLDER = '__PBM_CSRF_TOKEN__';

    private const VISITOR_PLACEHOLDER = '__PBM_VISITOR_ID__';

    private const URL_PLACEHOLDER = '"__PBM_REQUEST_URI__"';

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

            return response(self::personalize($html, $request), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            Cache::put($cacheKey, self::anonymize((string) $response->getContent(), $request), self::TTL_SECONDS);
        }

        return $response;
    }

    private static function anonymize(string $html, Request $request): string
    {
        $replacements = [
            csrf_token() => self::CSRF_PLACEHOLDER,
            json_encode($request->getRequestUri()) => self::URL_PLACEHOLDER,
        ];

        $visitorId = $request->attributes->get('pbm_visitor_id');

        if (is_string($visitorId) && $visitorId !== '') {
            $replacements[$visitorId] = self::VISITOR_PLACEHOLDER;
        }

        return strtr($html, $replacements);
    }

    private static function personalize(string $html, Request $request): string
    {
        return strtr($html, [
            self::CSRF_PLACEHOLDER => csrf_token(),
            self::VISITOR_PLACEHOLDER => (string) $request->attributes->get('pbm_visitor_id'),
            self::URL_PLACEHOLDER => json_encode($request->getRequestUri(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ]);
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
