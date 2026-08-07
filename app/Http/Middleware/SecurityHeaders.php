<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers and, when the request is already
 * served over HTTPS, an HSTS header. HSTS is only emitted on secure requests,
 * so enabling it can never make an http-only environment unreachable.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = config('security.headers', []);

        if (! empty($headers['x_content_type_options'])) {
            $response->headers->set('X-Content-Type-Options', $headers['x_content_type_options']);
        }
        if (! empty($headers['x_frame_options'])) {
            $response->headers->set('X-Frame-Options', $headers['x_frame_options']);
        }
        if (! empty($headers['referrer_policy'])) {
            $response->headers->set('Referrer-Policy', $headers['referrer_policy']);
        }

        if (config('security.hsts.enabled') && $request->isSecure()) {
            $value = 'max-age=' . (int) config('security.hsts.max_age', 31536000);
            if (config('security.hsts.include_subdomains')) {
                $value .= '; includeSubDomains';
            }
            if (config('security.hsts.preload')) {
                $value .= '; preload';
            }
            $response->headers->set('Strict-Transport-Security', $value);
        }

        return $response;
    }
}
