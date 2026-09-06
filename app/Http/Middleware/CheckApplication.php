<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\LoanApplication;

class CheckApplication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $client = $user->client;

        $existingApplication = LoanApplication::where('client_id', $client->id)
            ->whereIn('status', ['pending', 'process', 'approved'])
            ->first();
        if ($existingApplication) {
            return response()->json([
                'status' => false,
                'message' => 'Client already has a pending or under review loan application.',
            ], 403);
        }

        return $next($request);
    }
}
