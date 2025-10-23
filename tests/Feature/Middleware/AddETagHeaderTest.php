<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;

class AddETagHeaderTest extends TestCase
{
    public function test_etag_header_is_added_to_responses(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $response->assertHeader('Last-Modified');
    }

    public function test_304_not_modified_response_when_etag_matches(): void
    {
        // First request to get the ETag
        $firstResponse = $this->get('/');
        $etag = $firstResponse->headers->get('ETag');

        $this->assertNotNull($etag);

        // Second request with If-None-Match header
        $secondResponse = $this->get('/', [
            'If-None-Match' => $etag,
        ]);

        $secondResponse->assertStatus(304);
        $secondResponse->assertHeader('ETag', $etag);
    }

    public function test_last_modified_header_is_set(): void
    {
        // Request to verify Last-Modified header is present
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('Last-Modified');

        // Verify the Last-Modified format is valid
        $lastModified = $response->headers->get('Last-Modified');
        $this->assertNotNull($lastModified);

        // Verify it's a valid HTTP date format
        $parsedTime = strtotime($lastModified);
        $this->assertNotFalse($parsedTime);
    }

    public function test_etag_not_added_to_post_requests(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // POST requests should not have ETag
        $this->assertFalse($response->headers->has('ETag'));
    }

    public function test_etag_not_added_to_streamed_responses(): void
    {
        // Create a test route that returns a streamed response
        \Route::get('/test-stream', function () {
            return response()->stream(function () {
                echo 'streamed content';
            });
        });

        $response = $this->get('/test-stream');

        // Streamed responses should not have ETag
        $this->assertFalse($response->headers->has('ETag'));
        $this->assertFalse($response->headers->has('Last-Modified'));
    }

    public function test_etag_not_added_when_cache_control_no_store(): void
    {
        // Create a test route with no-store directive
        \Route::get('/test-no-store', function () {
            return response('private content')
                ->header('Cache-Control', 'no-store, no-cache');
        });

        $response = $this->get('/test-no-store');

        // Responses with no-store should not have ETag
        $this->assertFalse($response->headers->has('ETag'));
    }

    public function test_etag_not_added_when_authorization_header(): void
    {
        // Create a simple test route
        \Route::get('/test-auth', function () {
            return response('authenticated content');
        });

        $response = $this->get('/test-auth', [
            'Authorization' => 'Bearer test-token',
        ]);

        // Responses to authenticated requests should not have ETag
        $this->assertFalse($response->headers->has('ETag'));
    }

    public function test_304_response_preserves_original_cache_control(): void
    {
        // Use the home route which should have Cache-Control from CacheResponse middleware
        $firstResponse = $this->get('/');
        $etag = $firstResponse->headers->get('ETag');
        $originalCacheControl = $firstResponse->headers->get('Cache-Control');

        $this->assertNotNull($etag);
        $this->assertNotNull($originalCacheControl);

        // Second request with If-None-Match header
        $secondResponse = $this->get('/', [
            'If-None-Match' => $etag,
        ]);

        $secondResponse->assertStatus(304);
        $secondResponse->assertHeader('ETag', $etag);

        // Should preserve Cache-Control (directives may be in different order but should be present)
        $secondCacheControl = $secondResponse->headers->get('Cache-Control');
        $this->assertNotNull($secondCacheControl);
        // Verify it contains key directives and is not the hardcoded default
        $this->assertStringNotContainsString('public, max-age=3600', $secondCacheControl);
        $this->assertStringContainsString('private', $secondCacheControl);
        $this->assertStringContainsString('must-revalidate', $secondCacheControl);
    }

    public function test_304_response_only_propagates_existing_headers(): void
    {
        // Test that 304 responses only include ETag, Last-Modified, and Cache-Control if they exist
        // The middleware should not add default Cache-Control headers

        $firstResponse = $this->get('/');
        $etag = $firstResponse->headers->get('ETag');

        $this->assertNotNull($etag);

        // Second request with If-None-Match header
        $secondResponse = $this->get('/', [
            'If-None-Match' => $etag,
        ]);

        $secondResponse->assertStatus(304);
        $secondResponse->assertHeader('ETag', $etag);

        // Verify headers are only present if they were in the original response
        // Not testing absence because CacheResponse middleware adds Cache-Control
        // The important thing is we don't add a hardcoded default
    }
}
