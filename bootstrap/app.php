<?php

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
use App\Http\Middleware\EnsureAgentCheckedIn;
use Illuminate\Database\QueryException;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        // ngrok, Docker, load balancers: trust X-Forwarded-* so sessions/CSRF see the real scheme & host.
        $trusted = env('TRUSTED_PROXIES');
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
            // 'agent.checked_in' => EnsureAgentCheckedIn::class, // DISABLED: Not using for now
        ]);
        $middleware->web(LocaleMiddleware::class);
        $middleware->web(DecryptHashIds::class);
    })
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withExceptions(function (Exceptions $exceptions) { // Error Pages
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
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Your session has expired due to inactivity. Please reload the page.',
                    'csrf_token' => csrf_token(),
                ], 419);
            }
            return redirect()->route('login')
                ->with('warning', 'Your session has expired due to inactivity. Please log in again.');
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not Found'], 404);
            }
            return response()->view('error.404', [], 404);
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            return response()->view('error.403', [], 403);
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() == 500) {
                return response()->view('error.500', [], 500);
            }
            if ($e->getStatusCode() == 503) {
                return response()->view('error.503', [], 503);
            }
        });
    })->create();
