<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders Middleware
 *
 * Adds comprehensive security headers to all responses to protect against common web vulnerabilities.
 *
 * CSP Strategy:
 * - Production: Uses nonce-based CSP without unsafe-inline/unsafe-eval for strong XSS protection
 * - Development: Allows relaxed inline/eval for easier debugging
 * - Use csp_nonce() helper in Blade templates for inline scripts: <script nonce="{{ csp_nonce() }}">
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Generate CSP nonce for inline scripts
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        // Content Security Policy - Restrict resource loading
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com", // CSS nonces have poor browser support
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // Allow relaxed inline/eval in non-production environments for easier development
        if (config('app.env') !== 'production') {
            $directives[1] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' 'nonce-{$nonce}' https://cdn.jsdelivr.net";
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $directives));

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Control browser features and APIs
        $response->headers->set('Permissions-Policy', implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
        ]));

        // Strict Transport Security (HTTPS only) - Only in production
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
