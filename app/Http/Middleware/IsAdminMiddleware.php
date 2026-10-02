<?php

namespace App\Http\Middleware;

use App\Services\MenuAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdminMiddleware
{
    /**
     * Admin always passes. Staff/Agent may pass when the current page
     * was dynamically assigned to them via Menu Access / personal override.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect('/');
        }

        $user = Auth::user();

        if ($user->hasRole('Admin')) {
            return $next($request);
        }

        if (app(MenuAccessService::class)->userCanAccessRequest($user, $request)) {
            return $next($request);
        }

        abort(403, 'You do not have access to this section. Ask Admin to assign the menu for your login.');
    }
}
