<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddETagHeader
{
    /**
     * Handle an incoming request and add ETag/Last-Modified headers.
     *
     * Implements HTTP caching with ETag and Last-Modified headers.
     * Supports 304 Not Modified responses for unchanged content.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process GET and HEAD requests
        if (! in_array($request->method(), ['GET', 'HEAD'])) {
            return $next($request);
        }

        // Get the response
        $response = $next($request);

        // Skip ETag handling for streamed and file responses
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        // Only add ETags to successful responses
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        // Don't add ETag for responses that already have one or shouldn't be cached
        if (
            $response->headers->has('ETag')
            || $response->headers->has('Set-Cookie')
            || stripos((string) $response->headers->get('Cache-Control', ''), 'no-store') !== false
            || $request->headers->has('Authorization')
        ) {
            return $response;
        }

        // Try to get response content (may throw LogicException for streamed responses)
        try {
            $content = $response->getContent();
        } catch (\LogicException $e) {
            // StreamedResponse::getContent() throws LogicException
            return $response;
        }

        // Skip if content is false (can happen with some response types)
        if ($content === false) {
            return $response;
        }

        // Generate ETag from content hash
        $etag = '"'.md5($content).'"';
        $response->headers->set('ETag', $etag);

        // Add Last-Modified header (use current time if not already set)
        if (! $response->headers->has('Last-Modified')) {
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s').' GMT');
        }

        // Check if client has a cached version
        $ifNoneMatch = $request->header('If-None-Match');
        $ifModifiedSince = $request->header('If-Modified-Since');
        $lastModified = $response->headers->get('Last-Modified');

        // If ETag matches, return 304 Not Modified
        if ($ifNoneMatch && $ifNoneMatch === $etag) {
            $headers = [
                'ETag' => $etag,
                'Cache-Control' => $response->headers->get('Cache-Control', 'public, max-age=3600'),
            ];
            if ($lastModified) {
                $headers['Last-Modified'] = $lastModified;
            }

            return response('', 304)->withHeaders($headers);
        }

        // If Last-Modified matches and no newer version exists, return 304
        if ($ifModifiedSince && ! $ifNoneMatch) {
            if ($lastModified && strtotime($ifModifiedSince) >= strtotime($lastModified)) {
                return response('', 304)
                    ->withHeaders([
                        'ETag' => $etag,
                        'Last-Modified' => $lastModified,
                        'Cache-Control' => $response->headers->get('Cache-Control', 'public, max-age=3600'),
                    ]);
            }
        }

        return $response;
    }
}
