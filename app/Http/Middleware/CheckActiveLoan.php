<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\LoanAccount;
use Illuminate\Support\Facades\Auth;

class CheckActiveLoan
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

        // Commented out to allow clients with active loans to apply for new loans
        /*
        $activeLoan = LoanAccount::where('client_id', $client->id)
            ->where('status', 'active')
            ->first();

        if ($activeLoan) {
            return response()->json([
                'status' => false,
                'message' => 'Client already has an active loan. Close it before applying for a new one.',
            ], 403);
        }
        */

        return $next($request);
    }
}
