<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
      if (!Auth::check()) {
          return redirect('/'); // redirect to login page
      }

      if (!Auth::user()->hasRole('Admin')) {
          abort(403, 'Only Admin can access this section.');
      }

      return $next($request);
    }
}
