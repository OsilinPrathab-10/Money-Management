<?php

if (!class_exists('Pdo\Mysql')) {
    class MysqlMock {
        const ATTR_SSL_CA = 1009;
    }
    class_alias(MysqlMock::class, 'Pdo\Mysql');
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\DecryptHashIds;
use App\Http\Middleware\IsAdminMiddleware;
use App\Http\Middleware\CreditAccessMiddleware;
use App\Http\Middleware\CheckUserKyc;
use App\Http\Middleware\CheckActiveLoan;
use App\Http\Middleware\CheckSupportTicket;
use App\Http\Middleware\AdminOrStaff;
use App\Http\Middleware\AdminStaffOrAgent;
use App\Http\Middleware\EnsureUserIsValid;
use App\Http\Middleware\EnsureAgentAuthenticated;
use App\Http\Middleware\EnsureAgentCheckedIn;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RecoverApiToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

// Maintenance Mode & Server Down Controller (/devosilinprathab)
if (file_exists($serverGuard = __DIR__.'/server_guard.php')) {
    require_once $serverGuard;
}

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        // ngrok, Docker, load balancers: trust X-Forwarded-* so sessions/CSRF see the real scheme & host.
        $trusted = env('TRUSTED_PROXIES', '*');
        if ($trusted !== null && $trusted !== '') {
            $at = $trusted === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trusted))));
            if ($at !== []) {
                $middleware->trustProxies(at: $at);
            }
        }

        $middleware->alias([
            'admin' => IsAdminMiddleware::class,
            'credit_access' => CreditAccessMiddleware::class,
            'adminOrStaff' => AdminOrStaff::class,
            'adminStaffOrAgent' => AdminStaffOrAgent::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.kyc' => CheckUserKyc::class,
            'check.active.loan' => \App\Http\Middleware\CheckActiveLoan::class,
            'check.active.supportTicket' => \App\Http\Middleware\CheckSupportTicket::class,
            'check.active.application' => \App\Http\Middleware\CheckApplication::class,
            'user.valid' => EnsureUserIsValid::class,
            'agent' => EnsureAgentAuthenticated::class,
            // 'agent.checked_in' => EnsureAgentCheckedIn::class, // DISABLED: Not using for now
        ]);
        $middleware->web(LocaleMiddleware::class);
        $middleware->web(DecryptHashIds::class);

        // Exclude secret dev maintenance switch from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'devosilinprathab',
            'devosilinprathab/*',
        ]);

        // Mobile / Postman clients often omit Accept: application/json.
        // Without this, auth:sanctum redirects to the HTML login page (200).
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            RecoverApiToken::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
                return null;
            }

            return route('login');
        });
    })
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/customerapi.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/agentapi.php'));
        },
    )
    ->withExceptions(function (Exceptions $exceptions) { // Error Pages
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                $hasToken = (bool) $request->bearerToken();

                return response()->json([
                    'status' => false,
                    'message' => $hasToken
                        ? 'Invalid or expired token. Copy the full token from login/verify-mpin (example: 12|xxxxxxxx). Include the number and |.'
                        : 'Unauthenticated. Send header Authorization: Bearer {token}. For ngrok also add ngrok-skip-browser-warning: 1',
                ], 401);
            }

            // Admin DataTables / jQuery ajax: never return login HTML (that triggers tn/7).
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Your session has expired. Please sign in again.',
                ], 401);
            }

            return null;
        });

        // Missing tables (e.g. DB created but migrations not run) — avoid noisy logs & broken auth loops
        $exceptions->render(function (QueryException $e, $request) {
            $msg = $e->getMessage();
            $isMissingTable = str_contains($msg, "doesn't exist")
                || str_contains($msg, 'Base table or view not found');
            if (! $isMissingTable) {
                return null;
            }

            // Do not call auth()->logout() — it loads the user row and can recurse while `users` is missing.
            try {
                if ($request->hasSession()) {
                    $request->session()->flush();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            } catch (\Throwable) {
                // Session driver may use DB (e.g. sessions table missing)
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Database tables are missing. Run: php artisan migrate',
                ], 503);
            }

            return response()->view('errors.database-setup', [], 503);
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            // Fresh session + token so the next login attempt is not stuck on a dead CSRF cookie.
            try {
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            } catch (\Throwable $ignored) {
                //
            }

            if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
                return response()->json([
                    'message' => 'Your session has expired due to inactivity. Please sign in again.',
                    'csrf_token' => csrf_token(),
                ], 419);
            }

            return redirect()
                ->guest(route('login'))
                ->with('warning', 'Your session expired. Please sign in again.');
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not Found'], 404);
            }
            return response()->view('error.404', [], 404);
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage() ?: 'Forbidden',
                ], 403);
            }

            return response()->view('error.403', [], 403);
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage() ?: 'Request failed',
                ], $e->getStatusCode() ?: 500);
            }

            if ($e->getStatusCode() == 500) {
                return response()->view('error.500', [], 500);
            }
            if ($e->getStatusCode() == 503) {
                return response()->view('error.503', [], 503);
            }
        });
    })->create();
