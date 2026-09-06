<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Allows Admin (full panel) or CreditVerifier (CIBIL / credit score area only).
 */
class CreditAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect('/');
        }

        $user = Auth::user();
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'CreditVerifier'])) {
            return $next($request);
        }

        abort(403, __('You do not have access to credit / CIBIL features.'));
    }
}
