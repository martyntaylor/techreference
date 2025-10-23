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
}
