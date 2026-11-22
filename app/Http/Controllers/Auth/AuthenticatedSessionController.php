<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\MenuAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * Handle an incoming authentication request (email — role-based redirect).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'login.email' => __('Please enter a valid email address.'),
        ]);

        $user = $this->resolveUserForLogin($validated['login']);
        if (! $user || ! $this->passwordMatches($user, $validated['password'])) {
            return back()
                ->withErrors(['login' => __('Invalid credentials.')])
                ->onlyInput('login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectAfterLogin($user);
    }

    public function resolveUserForLogin(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        $user = User::query()
            ->where('email', $login)
            ->first();

        if ($user) {
            return $user;
        }

        $client = Client::query()
            ->where('client_email', $login)
            ->first();

        return $client?->user_id ? User::find($client->user_id) : null;
    }

    public function passwordMatches(User $user, string $password): bool
    {
        if (Hash::check($password, $user->password)) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Client')) {
            return trim($password) === trim((string) $user->phone);
        }

        return false;
    }

    protected function redirectAfterLogin($user): RedirectResponse
    {
        if ($user->hasRole('Client')) {
            return redirect()->route(
                $user->must_change_password ? 'client.profile' : 'client.dashboard'
            );
        }

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
