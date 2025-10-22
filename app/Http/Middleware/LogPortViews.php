<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogPortViews
{
    /**
     * Handle an incoming request.
     *
     * Track page views for analytics - processed asynchronously.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful GET requests
        if ($request->isMethod('GET') && $response->isSuccessful()) {
            // Queue analytics logging after response is sent
            dispatch(function () use ($request) {
                $this->logPageView($request);
            })->afterResponse();
        }

        return $response;
    }

    /**
     * Log page view data for analytics.
     */
    protected function logPageView(Request $request): void
    {
        $data = [
            'timestamp' => now()->toIso8601String(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'route_name' => $request->route()?->getName(),
            'route_params' => $request->route()?->parameters() ?? [],
        ];

        // Log to analytics channel (configure in config/logging.php)
        Log::channel('analytics')->info('page_view', $data);

        // TODO: In production, send to analytics service (Google Analytics, Plausible, etc.)
        // or store in database for custom analytics dashboard
        // Example: Analytics::track('page_view', $data);
    }
}
