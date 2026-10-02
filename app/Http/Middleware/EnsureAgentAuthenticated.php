<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request is authenticated as an Agent (Sanctum agent guard).
 */
class EnsureAgentAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('agent')->user() ?? $request->user('agent') ?? $request->user();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Token may resolve to Agent model or User with Agent role
        $isAgentModel = $user instanceof \App\Models\Agent;
        $isAgentUser = method_exists($user, 'hasRole') && $user->hasRole('Agent');

        if (! $isAgentModel && ! $isAgentUser) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Agent access only.',
            ], 403);
        }

        return $next($request);
    }
}
