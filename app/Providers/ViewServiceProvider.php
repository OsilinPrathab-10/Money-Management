<?php

namespace App\Providers;

use App\Helpers\SettingsHelper;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share admin settings with all views (skip DB-free error pages used before migrations)
        View::composer('*', function ($view) {
            if (($view->name() ?? '') === 'errors.database-setup') {
                return;
            }
            $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName', 'Finova'));
            $adminSubtitle = SettingsHelper::get('admin_subtitle', config('variables.templateSuffix', 'Loan Management'));
            $adminLogo = SettingsHelper::get('admin_logo');
            $adminLogoDark = SettingsHelper::get('admin_logo_dark');
            $adminFavicon = SettingsHelper::get('admin_favicon');
            
            $view->with([
                'adminTitle' => $adminTitle,
                'adminSubtitle' => $adminSubtitle,
                'adminLogo' => $adminLogo,
                'adminLogoDark' => $adminLogoDark,
                'adminFavicon' => $adminFavicon,
            ]);
        });
    }
}
