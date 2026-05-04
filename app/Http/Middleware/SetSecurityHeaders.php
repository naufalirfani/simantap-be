<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Handle an incoming request and add security headers to the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent browsers from MIME-type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking attacks
        $response->header('X-Frame-Options', 'DENY');

        // Enable XSS filtering in older browsers
        $response->header('X-XSS-Protection', '1; mode=block');

        // Force HTTPS connection for this domain and subdomains
        $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Restrict which features and APIs can be used
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        // Control referrer information sent in requests
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - restrict resource loading
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline'; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data: https:; "
             . "font-src 'self' data:; "
             . "connect-src 'self' https:; "
             . "frame-ancestors 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self'";

        $response->header('Content-Security-Policy', $csp);

        // Remove server information disclosure
        $response->header('Server', 'Application Server');
        
        // Remove X-Powered-By header that exposes PHP
        $response->headers->remove('X-Powered-By');

        // Disable client-side caching for sensitive data
        if ($request->is('api/*')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }

        return $response;
    }
}
