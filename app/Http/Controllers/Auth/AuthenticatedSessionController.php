<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('authentications.auth-login', ['pageConfigs' => $pageConfigs]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // ── Step 1: Rate limit guard ───────────────────────────────────────
        $throttleKey = \Illuminate\Support\Str::lower($request->input('email')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
            ])->onlyInput('email');
        }

        // ── Step 2: Check user exists in the database ──────────────────────
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            RateLimiter::hit($throttleKey);
            return back()->withErrors([
                'email' => __('No account found with this email address.'),
            ])->onlyInput('email');
        }

        // ── Step 3: Check account status before attempting auth ────────────
        if ($user->status !== 'active') {
            RateLimiter::hit($throttleKey);
            return back()->withErrors([
                'email' => __('Your account is inactive or has been blocked. Please contact the administrator.'),
            ])->onlyInput('email');
        }

        // ── Step 4: Verify password ────────────────────────────────────────
        if (!Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey);
            return back()->withErrors([
                'email' => __('The password you entered is incorrect.'),
            ])->onlyInput('email');
        }

        // ── Step 5: Attempt login ──────────────────────────────────────────
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);
            return back()->withErrors([
                'email' => __('Invalid credentials. Please try again.'),
            ])->onlyInput('email');
        }

        // ── Step 6: Clear rate limiter and regenerate session ──────────────
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // ── Step 7: Clear Spatie permission cache (fixes stale role cache) ─
        // This is critical when many users/roles exist (5+ agents/admins)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->redirectAfterLogin(Auth::user());
    }

    protected function redirectAfterLogin($user): RedirectResponse
    {
        // Re-load roles fresh from DB to avoid cached role issues
        $user->load('roles');

        if ($user->hasRole('Admin')) {
            return redirect()->route('dashboard');
        } elseif ($user->hasRole('CreditVerifier')) {
            return redirect()->route('verification-credit-score-history');
        } elseif ($user->hasRole('Staff')) {
            return redirect()->route('support-tickets');
        } elseif ($user->hasRole('Agent')) {
            return redirect()->route('agent-dashboard');
        } else {
            // Log for debugging — user has no recognized role
            \Illuminate\Support\Facades\Log::warning('User logged in but has no recognized role', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'roles'   => $user->getRoleNames()->toArray(),
            ]);
            abort(403, 'Access denied. Your account does not have a valid role assigned. Please contact the administrator.');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
