<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * The Vite HMR development origin.
     * Permitted only when APP_ENV=local. Never included in production.
     */
    private const VITE_DEV_ORIGIN = 'http://127.0.0.1:5173';

    /**
     * The Vite HMR WebSocket origin (for hot-reload transport).
     * Permitted only when APP_ENV=local. Never included in production.
     */
    private const VITE_DEV_WS_ORIGIN = 'ws://127.0.0.1:5173';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        return $response;
    }

    /**
     * Bunny Fonts CDN origin — used in app.blade.php for instrument-sans.
     * Loaded on every page, so it must be present in both local and production.
     */
    private const BUNNY_FONT_ORIGIN = 'https://fonts.bunny.net';

    /**
     * Build the Content-Security-Policy header value.
     *
     * In local development the Vite HMR dev server (http://127.0.0.1:5173)
     * is added to script-src, script-src-elem, and connect-src so that
     * React and Vite ES modules can load in Firefox.
     *
     * In every other environment (staging, production) the CSP is strict:
     * only 'self' origins are permitted — no Vite dev origins are present.
     */
    private function buildCsp(): string
    {
        // Production-safe base directives. These never change.
        $scriptSrc     = "'self' 'unsafe-inline' 'unsafe-eval'";
        $scriptSrcElem = "'self' 'unsafe-inline'";
        $styleSrc      = "'self' 'unsafe-inline' " . self::BUNNY_FONT_ORIGIN;
        $connectSrc    = "'self'";
        $fontSrc       = "'self' data: " . self::BUNNY_FONT_ORIGIN;

        // Local-only exception: allow the deterministic Vite dev server origin.
        // Firefox enforces script-src-elem separately for <script type="module">.
        // app()->environment('local') returns true only when APP_ENV=local.
        if (app()->environment('local')) {
            $scriptSrc     .= ' ' . self::VITE_DEV_ORIGIN;
            $scriptSrcElem .= ' ' . self::VITE_DEV_ORIGIN;
            $connectSrc    .= ' ' . self::VITE_DEV_ORIGIN . ' ' . self::VITE_DEV_WS_ORIGIN;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "script-src-elem {$scriptSrcElem}",
            "style-src {$styleSrc}",
            "img-src 'self' data:",
            "font-src {$fontSrc}",
            "connect-src {$connectSrc}",
        ]);
    }
}
