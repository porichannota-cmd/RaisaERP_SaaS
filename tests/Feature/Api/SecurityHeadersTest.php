<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_it_applies_security_headers()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');
    }
}
