<?php

namespace App\Http\Middleware;

use App\Support\CustomerAppAccess;
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
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Allow logout so the app can clear a session after admin inactivation.
        if (str_ends_with(rtrim($request->path(), '/'), 'logout')) {
            return $next($request);
        }

        // Already-logged-in clients who are later inactivated must be blocked
        // on every customer API call (same message as login).
        if (CustomerAppAccess::isBlocked($user->client)) {
            return CustomerAppAccess::deniedResponse($user->client);
        }

        switch ($user->status) {
            case 'inactive':
                if ($user->client) {
                    return CustomerAppAccess::deniedResponse($user->client);
                }

                return response()->json([
                    'status' => false,
                    'message' => 'Your account is inactive.',
                ], 403);

            case 'blocked':
                return response()->json([
                    'status' => false,
                    'message' => 'Your account has been blocked.',
                ], 403);

            case 'active':
                break;

            default:
                if ($user->client) {
                    break;
                }

                return response()->json([
                    'status' => false,
                    'message' => 'Invalid account status.',
                ], 403);
        }

        return $next($request);
    }
}
