<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use App\Models\Account\ChartOfAccount;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! $this->app->bound('path.public')) {
            $this->app->singleton('path.public', function () {
                // Case for subfolder installations (e.g. app is in /public_html/loan_account)
                if (basename(dirname(base_path())) === 'public_html') {
                    return dirname(base_path());
                }
                
                if (is_dir(base_path('public_html'))) {
                    return base_path('public_html');
                }
                return base_path('public');
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Avoid mixed http/https URL generation (can break session cookies + CSRF).
        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            if (str_starts_with($appUrl, 'http://')) {
                URL::forceScheme('http');
            } elseif (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Register model observers
        \App\Models\LoanAccount::observe(\App\Observers\LoanAccountObserver::class);
        \App\Models\EmiAgentAssignment::observe(\App\Observers\EmiAgentAssignmentObserver::class);
        \App\Models\EmiFollowup::observe(\App\Observers\EmiFollowupObserver::class);

        // Register login and logout event loggers
        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;
            \App\Models\LoginLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'ip_address' => request()->ip(),
                'login_at' => now(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            $user = $event->user;
            if ($user) {
                $log = \App\Models\LoginLog::where('user_id', $user->id)
                    ->whereNull('logout_at')
                    ->orderBy('login_at', 'desc')
                    ->first();
                if ($log) {
                    $log->update([
                        'logout_at' => now(),
                    ]);
                } else {
                    \App\Models\LoginLog::create([
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'ip_address' => request()->ip(),
                        'logout_at' => now(),
                    ]);
                }
            }
        });

        // Register collection created logger
        \App\Models\EmiCollection::created(function ($collection) {
            try {
                $collection->loadMissing(['emi.loanAccount.client', 'agent', 'verifiedBy']);
                
                $clientName = $collection->emi?->loanAccount?->client?->client_name ?? 'Unknown';
                $loanNumber = $collection->emi?->loanAccount?->account_number ?? 'N/A';
                $emiNumber = $collection->emi?->instalment_number ?? 'N/A';
                $amount = $collection->amount ?? 0;
                $paymentMode = $collection->payment_method ?? 'direct';
                
                $collectedBy = 'System';
                if ($collection->agent) {
                    $collectedBy = $collection->agent->agent_name;
                } elseif ($collection->verifiedBy) {
                    $collectedBy = $collection->verifiedBy->name;
                } elseif (auth()->check()) {
                    $collectedBy = auth()->user()->name;
                }

                \App\Models\CollectionLog::create([
                    'client_name' => $clientName,
                    'loan_number' => $loanNumber,
                    'emi_number' => $emiNumber,
                    'collected_amount' => $amount,
                    'payment_mode' => $paymentMode,
                    'collected_by_name' => $collectedBy,
                    'ip_address' => request()->ip() ?: '127.0.0.1',
                    'collected_at' => $collection->collected_at ?: now(),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to log collection log: ' . $e->getMessage());
            }
        });

        Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
            if ($src !== null) {
                return [
                    'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' : (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : '')
                ];
            }
            return [];
        });
        
        // Share GL accounts for Sidebar "Add" Modals and Accounting Views
        View::composer(['layouts.sections.menu.verticalMenu', 'layouts.sections.menu.submenu', 'admin.account.*'], function ($view) {
            if (auth()->check()) {
                $created_by = creatorId();
                
                // Get list of GL accounts already linked to bank accounts
                $usedBankGlIds = \App\Models\Account\BankAccount::where('created_by', $created_by)
                    ->whereNotNull('gl_account_id')
                    ->pluck('gl_account_id')
                    ->toArray();
                
                $view->with([
                    'bankGlAccounts' => ChartOfAccount::where('created_by', $created_by)
                        ->where('is_active', true)
                        ->whereHas('accountType.category', function($q) {
                            $q->where('type', 'assets');
                        })
                        ->whereNotIn('id', $usedBankGlIds)
                        ->select('id', 'account_code', 'account_name')
                        ->orderBy('account_code')
                        ->get(),
                    'revenueGlAccounts' => ChartOfAccount::where('created_by', $created_by)
                        ->where('is_active', true)
                        ->whereHas('accountType.category', function($q) {
                            $q->whereIn('type', ['revenue', 'income']);
                        })
                        ->select('id', 'account_code', 'account_name')
                        ->orderBy('account_code')
                        ->get(),
                    'expenseGlAccounts' => ChartOfAccount::where('created_by', $created_by)
                        ->where('is_active', true)
                        ->whereHas('accountType', function($q) {
                            $q->where(function ($sub) {
                                $sub->where('name', 'like', '%expense%')
                                    ->orWhereHas('category', function($q2) {
                                        $q2->where('type', 'expenses');
                                    });
                            })->where('name', 'not like', '%liability%')
                              ->where('name', 'not like', '%asset%')
                              ->where('name', 'not like', '%income%')
                              ->where('name', 'not like', '%revenue%')
                              ->where('name', 'not like', '%equity%');
                        })
                        ->select('id', 'account_code', 'account_name')
                        ->orderBy('account_code')
                        ->get(),
                    'accountCategories' => \App\Models\Account\AccountCategory::where('created_by', $created_by)
                        ->select('id', 'name', 'code')
                        ->get(),
                    'allRevenueCategories' => \App\Models\Account\RevenueCategories::where('created_by', $created_by)
                        ->where('is_active', true)
                        ->select('id', 'category_name')
                        ->get(),
                    'allExpenseCategories' => \App\Models\Account\ExpenseCategories::where('created_by', $created_by)
                        ->where('is_active', true)
                        ->select('id', 'category_name')
                        ->get(),
                    'allBankAccounts' => \App\Models\Account\BankAccount::where('created_by', $created_by)
                        ->where('is_active', true)
                        ->select('id', 'account_name')
                        ->get(),
                    'allAccountTypes' => \App\Models\Account\AccountType::where('created_by', $created_by)
                        ->select('id', 'name')
                        ->get(),
                    'allChartOfAccounts' => ChartOfAccount::where('created_by', $created_by)
                        ->select('id', 'account_code', 'account_name')
                        ->orderBy('account_code')
                        ->get(),
                ]);
            }
        });
    }
}
