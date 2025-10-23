<?php

use function Pest\Laravel\get;

test('security headers are present on all responses', function () {
    $response = get('/');

    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Content-Security-Policy');
    $response->assertHeader('Permissions-Policy');
});

test('CSP header includes nonce', function () {
    $response = get('/');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("script-src 'self'")
        ->toContain("'nonce-");
});

test('CSP header allows unsafe-inline and unsafe-eval in non-production', function () {
    // Default test environment is not production
    $response = get('/');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain('unsafe-inline')
        ->toContain('unsafe-eval')
        ->toContain("'nonce-");
});

test('HSTS header behavior varies by environment', function () {
    // Non-production - no HSTS (default test environment)
    $response = get('/');
    $response->assertHeaderMissing('Strict-Transport-Security');
});

test('csp_nonce helper returns the nonce from request', function () {
    $response = get('/');

    // The nonce should be set in the request attributes
    expect(csp_nonce())->toBeString()->not->toBeEmpty();
});
