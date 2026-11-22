<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'hasRole') && ! $user->hasRole('Client')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('warning', __('Please use the staff login for admin access.'));
        }

        if (! $user->client) {
            return redirect()->route('login')->with('error', __('No customer profile linked to this account.'));
        }

        return $next($request);
    }
}