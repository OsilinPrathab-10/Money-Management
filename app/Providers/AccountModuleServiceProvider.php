<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccountModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/account'));

        /** @var array<int, string> */
        $accountAbilities = [
            'manage-account-dashboard',
            'manage-account-day-book',
            'manage-account-ledger',
            'manage-account-profit-loss',
            'manage-bank-accounts', 'manage-any-bank-accounts', 'manage-own-bank-accounts',
            'create-bank-accounts', 'edit-bank-accounts', 'delete-bank-accounts',
            'manage-account-types', 'manage-any-account-types', 'manage-own-account-types',
            'create-account-types', 'edit-account-types', 'delete-account-types',
            'manage-chart-of-accounts', 'manage-any-chart-of-accounts', 'manage-own-chart-of-accounts',
            'create-chart-of-accounts', 'edit-chart-of-accounts', 'view-chart-of-accounts', 'delete-chart-of-accounts',
            'manage-vendors', 'manage-any-vendors', 'manage-own-vendors',
            'create-vendors', 'edit-vendors', 'delete-vendors',
            'manage-customers', 'manage-any-customers', 'manage-own-customers',
            'create-customers', 'edit-customers', 'delete-customers',
            'manage-vendor-payments', 'manage-any-vendor-payments', 'manage-own-vendor-payments',
            'create-vendor-payments', 'cleared-vendor-payments', 'delete-vendor-payments',
            'manage-customer-payments', 'manage-any-customer-payments', 'manage-own-customer-payments',
            'create-customer-payments', 'cleared-customer-payments', 'delete-customer-payments',
            'manage-bank-transactions', 'reconcile-bank-transactions',
            'manage-bank-transfers', 'manage-any-bank-transfers', 'manage-own-bank-transfers',
            'create-bank-transfers', 'edit-bank-transfers', 'delete-bank-transfers', 'process-bank-transfers',
            'manage-debit-notes', 'manage-any-debit-notes', 'manage-own-debit-notes',
            'view-debit-notes', 'approve-debit-notes', 'delete-debit-notes',
            'manage-credit-notes', 'manage-any-credit-notes', 'manage-own-credit-notes',
            'view-credit-notes', 'approve-credit-notes', 'delete-credit-notes',
            'manage-revenue-categories', 'create-revenue-categories', 'edit-revenue-categories', 'delete-revenue-categories',
            'manage-expense-categories', 'create-expense-categories', 'edit-expense-categories', 'delete-expense-categories',
            'manage-revenues', 'manage-any-revenues', 'manage-own-revenues',
            'create-revenues', 'edit-revenues', 'delete-revenues', 'approve-revenues', 'post-revenues',
            'manage-expenses', 'manage-any-expenses', 'manage-own-expenses',
            'create-expenses', 'edit-expenses', 'delete-expenses', 'approve-expenses', 'post-expenses',
            'manage-account-reports',
            'print-invoice-aging', 'print-bill-aging', 'print-tax-summary',
            'print-customer-balance', 'print-vendor-balance',
            'view-customer-detail-report', 'view-vendor-detail-report',
            'print-customer-detail-report', 'print-vendor-detail-report',
            // Loan app data surfaced inside Accounting (loan_accounts / emis tables)
            'view-account-loan-accounts',
            'view-account-emis',
        ];

        // Allow Admin to pass all ERP-style `can()` checks without seeding Spatie permissions.
        Gate::before(function ($user, $ability) use ($accountAbilities) {
            if ($user->hasRole('Admin') && in_array($ability, $accountAbilities, true)) {
                return true;
            }

            return null;
        });
    }
}
