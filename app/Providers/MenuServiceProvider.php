<?php

namespace App\Providers;

use App\Services\MenuAccessService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuAccessService::class);
    }

    public function boot(): void
    {
        // Only attach menus to layout shells that render the sidebar — not every partial.
        View::composer([
            'layouts.sections.menu.verticalMenu',
            'layouts.sections.menu.horizontalMenu',
            'layouts.sections.menu.submenu',
            'layouts.contentNavbarLayout',
            'layouts.horizontalLayout',
            'layouts.contentNavSidebarLayout',
            'layouts.commonMaster',
            'layouts.layoutMaster',
            'layouts/contentNavbarLayout',
            'layouts/horizontalLayout',
            'layouts/commonMaster',
            'layouts/layoutMaster',
        ], function ($view) {
            static $menuData = null;

            if ($menuData === null) {
                $menuData = app(MenuAccessService::class)->menuDataForUser(auth()->user());
            }

            $view->with('menuData', $menuData);
        });
    }
}
