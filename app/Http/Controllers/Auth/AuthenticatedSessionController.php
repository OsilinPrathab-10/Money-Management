<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\MenuAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Optional: redirect based on role
            return $this->redirectAfterLogin(Auth::user());
        }

        return back()
            ->withErrors([
              'email' => __('Invalid credentials.')])
            ->onlyInput('email');
    }

    protected function redirectAfterLogin($user): RedirectResponse
    {
        if (auth()->user()->hasRole('Admin')) {
            return redirect()->route('dashboard');
        } elseif (auth()->user()->hasRole('CreditVerifier')) {
            return redirect()->route('verification-credit-score-history');
        } elseif (auth()->user()->hasRole('Staff')) {
            $firstUrl = app(MenuAccessService::class)->firstUrlForUser($user);
            if ($firstUrl) {
                // Relative app URL (respects APP_URL / subdirectory) — avoids CSRF/session breaks on XAMPP.
                return redirect()->to(url('/' . ltrim($firstUrl, '/')));
            }

            return redirect()->route('support-tickets');
        } elseif (auth()->user()->hasRole('Agent')) {
            $firstUrl = app(MenuAccessService::class)->firstUrlForUser($user);
            if ($firstUrl) {
                return redirect()->to(url('/' . ltrim($firstUrl, '/')));
            }

            return redirect()->route('agent-dashboard');
        } else {
           abort(403, 'Access denied. You are not authorized to access this system.');
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

        return redirect()->route('login');
    }
}
