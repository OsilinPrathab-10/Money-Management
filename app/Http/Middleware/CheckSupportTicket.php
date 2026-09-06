<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;

class CheckSupportTicket
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

          if (! $client) {
              return response()->json([
                  'status' => false,
                  'message' => 'Client profile not found for this user.',
              ], 404);
          }

          $supportTickets = SupportTicket::where('client_id', $client->id)
              ->where('status', '!=', 'closed')
              ->first();

          if ($supportTickets) {
              return response()->json([
                  'status' => false,
                  'message' => 'Client already has an active support ticket.',
              ], 403);
          }
          return $next($request);
      }
}
