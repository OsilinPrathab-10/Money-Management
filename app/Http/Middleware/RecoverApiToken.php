<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recover Bearer tokens that Apache/CGI/ngrok drop from the Authorization header.
 */
class RecoverApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $token = $this->extractToken($request);
            if ($token) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $candidates = [
            $request->header('Authorization'),
            $request->header('X-Authorization'),
            $request->header('X-Access-Token'),
            $request->header('X-Auth-Token'),
            $request->server('HTTP_AUTHORIZATION'),
            $request->server('REDIRECT_HTTP_AUTHORIZATION'),
            $request->server('REDIRECT_REDIRECT_HTTP_AUTHORIZATION'),
        ];

        foreach ($candidates as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            if (stripos($raw, 'Bearer ') === 0) {
                $raw = trim(substr($raw, 7));
            }

            if ($raw !== '') {
                return $raw;
            }
        }

        return null;
    }
}
