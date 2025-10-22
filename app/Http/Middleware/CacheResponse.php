<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * Cache GET requests for 1 hour, varying by query parameters.
     * Skip cache for authenticated users.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET requests
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Skip cache for authenticated users
        if ($request->user()) {
            return $next($request);
        }

        // Skip cache for preview/debug requests
        if ($request->has('preview') || $request->has('debug')) {
            return $next($request);
        }

        // Build cache key from URL and query parameters
        $cacheKey = $this->getCacheKey($request);

        // Try to get from cache
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);

            // Return cached response with cache headers
            $response = response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers']);

            $response->headers->set('X-Cache', 'HIT');
            $response->headers->set('Cache-Control', 'public, max-age=3600');

            return $response;
        }

        // Process request
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful() && $response->getStatusCode() === 200) {
            // Store response in cache for 1 hour (3600 seconds)
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->getCacheableHeaders($response),
            ], 3600);

            // Add cache miss header
            $response->headers->set('X-Cache', 'MISS');
        }

        // Add cache control headers
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }

    /**
     * Generate cache key from request.
     */
    protected function getCacheKey(Request $request): string
    {
        $url = $request->fullUrl();
        $queryParams = $request->query();
        ksort($queryParams); // Sort for consistency

        return 'response:' . md5($url . serialize($queryParams));
    }

    /**
     * Get headers that should be cached.
     */
    protected function getCacheableHeaders(Response $response): array
    {
        $headers = [];

        // Only cache specific headers
        $cacheableHeaders = ['Content-Type', 'Content-Language'];

        foreach ($cacheableHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}
