<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    // -----------------------------------------------------------------------
    // 1. Non-CSP security headers — always present regardless of environment
    // -----------------------------------------------------------------------

    public function test_it_applies_non_csp_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src-elem 'self'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net", $csp);
    }

    // -----------------------------------------------------------------------
    // 2. Local environment — Vite dev origin must appear in CSP
    // -----------------------------------------------------------------------

    public function test_local_csp_permits_vite_dev_origin(): void
    {
        // The test suite itself runs with APP_ENV=testing, so we swap to local
        // only for this test using Laravel's app/env helper.
        /** @var \Illuminate\Foundation\Application $app */
        $app = app();
        $previousEnv = $app->environment();
        $app['env'] = 'local';

        try {
            $response = $this->get('/');
            $csp      = $response->headers->get('Content-Security-Policy');

            $this->assertNotNull($csp, 'CSP header must be present');
            $this->assertStringContainsString(
                'http://127.0.0.1:5173',
                $csp,
                'Local CSP must permit the Vite dev server origin in script-src and connect-src',
            );
            $this->assertStringContainsString(
                'ws://127.0.0.1:5173',
                $csp,
                'Local CSP must permit the Vite HMR WebSocket origin in connect-src',
            );
            $this->assertStringContainsString("script-src-elem 'self' 'unsafe-inline' http://127.0.0.1:5173", $csp);
            $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
            $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net", $csp);
        } finally {
            // Always restore the original environment.
            $app['env'] = $previousEnv;
        }
    }

    // -----------------------------------------------------------------------
    // 3. Non-local (production-like) environment — Vite dev origin must be
    //    absent from the CSP to preserve production security posture.
    // -----------------------------------------------------------------------

    public function test_production_csp_excludes_vite_dev_origin(): void
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = app();
        $previousEnv = $app->environment();
        $app['env'] = 'production';

        try {
            $response = $this->get('/');
            $csp      = $response->headers->get('Content-Security-Policy');

            $this->assertNotNull($csp, 'CSP header must be present');
            $this->assertStringNotContainsString(
                '127.0.0.1:5173',
                $csp,
                'Production CSP must NOT contain the Vite dev server IPv4 origin',
            );
            $this->assertStringNotContainsString(
                'localhost:5173',
                $csp,
                'Production CSP must NOT contain the Vite dev server localhost origin',
            );
            $this->assertStringNotContainsString(
                '[::1]:5173',
                $csp,
                'Production CSP must NOT contain the Vite dev server IPv6 origin',
            );
            $this->assertStringNotContainsString(
                'ws://127.0.0.1:5173',
                $csp,
                'Production CSP must NOT contain the Vite HMR WebSocket origin',
            );
            // Strict 'self' directives must still be present.
            $this->assertStringContainsString("default-src 'self'", $csp);
            $this->assertStringContainsString("connect-src 'self'", $csp);
            $this->assertStringContainsString("script-src-elem 'self'", $csp);
            $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
            $this->assertStringContainsString("font-src 'self' data: https://fonts.bunny.net", $csp);
        } finally {
            $app['env'] = $previousEnv;
        }
    }
}
