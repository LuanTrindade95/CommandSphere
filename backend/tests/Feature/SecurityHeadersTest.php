<?php

test('json api responses send a restrictive content security policy', function () {
    $response = $this->getJson('/api/v1/communities');

    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'none'")
        ->toContain("base-uri 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'none'")
        ->not->toContain('unsafe-inline')
        ->not->toContain('unsafe-eval');
});

test('html responses also send the same security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('Content-Security-Policy');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});
