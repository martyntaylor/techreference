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
        $cacheTags = $this->getCacheTags($request);

        // Try to get from cache (with tags if supported)
        $cacheStore = method_exists(Cache::getStore(), 'tags') && !empty($cacheTags)
            ? Cache::tags($cacheTags)
            : Cache::store();

        if ($cacheStore->has($cacheKey)) {
            $cachedResponse = $cacheStore->get($cacheKey);

            // Return cached response with cache headers
            $response = response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers']);

            $response->headers->set('X-Cache', 'HIT');

            // Preserve Cache-Control from cached metadata if present, otherwise set default
            if (isset($cachedResponse['headers']['Cache-Control'])) {
                $response->headers->set('Cache-Control', $cachedResponse['headers']['Cache-Control']);
            } elseif (!$response->headers->has('Cache-Control')) {
                $response->headers->set('Cache-Control', 'public, max-age=3600');
            }

            return $response;
        }

        // Process request
        $response = $next($request);

        // Only cache cacheable responses (successful status codes, no Set-Cookie)
        $cacheableStatuses = [200, 203, 300, 301, 410];
        $isCacheable = in_array($response->getStatusCode(), $cacheableStatuses)
            && !$response->headers->has('Set-Cookie');

        if ($isCacheable) {
            // Store response in cache for 1 hour (3600 seconds) with tags if supported
            $cacheStore->put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->getCacheableHeaders($response),
            ], 3600);

            // Add cache miss header
            $response->headers->set('X-Cache', 'MISS');

            // Set Cache-Control only if not already present
            if (!$response->headers->has('Cache-Control')) {
                $response->headers->set('Cache-Control', 'public, max-age=3600');
            }
        }

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
        $cacheableHeaders = ['Content-Type', 'Content-Language', 'Cache-Control'];

        foreach ($cacheableHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }

    /**
     * Get cache tags for the request.
     *
     * Tags allow flushing related cached responses when data changes.
     */
    protected function getCacheTags(Request $request): array
    {
        $tags = ['http']; // All HTTP responses get this base tag

        // Port pages: /port/{number}
        if ($request->route() && $request->route()->getName() === 'port.show') {
            $portNumber = $request->route('portNumber');
            if ($portNumber) {
                // Handle Collection from route binding
                if ($portNumber instanceof \Illuminate\Support\Collection) {
                    $portNumber = $portNumber->first()?->port_number;
                }
                if ($portNumber) {
                    $tags[] = "port:{$portNumber}";
                }
            }
        }

        // Category pages: /ports/{category}
        if ($request->route() && $request->route()->getName() === 'ports.category') {
            $category = $request->route('category');
            if ($category) {
                $tags[] = "category:{$category}";
            }
        }

        // Port range pages: /ports/range/{start}-{end}
        if ($request->route() && $request->route()->getName() === 'ports.range') {
            $tags[] = 'ports:range';
        }

        // Ports home page
        if ($request->route() && $request->route()->getName() === 'ports.home') {
            $tags[] = 'ports:home';
        }

        return $tags;
    }
}
