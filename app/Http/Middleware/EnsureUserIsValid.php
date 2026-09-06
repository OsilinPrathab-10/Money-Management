<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        switch ($user->status) {
            case 'inactive':
                return response()->json([
                    'message' => 'Your account is inactive.'
                ], 403);

            case 'blocked':
                return response()->json([
                    'message' => 'Your account has been blocked.'
                ], 403);

            case 'active':
                break;

            default:
                return response()->json([
                    'message' => 'Invalid account status.'
                ], 403);
        }

        return $next($request);
    }
}
