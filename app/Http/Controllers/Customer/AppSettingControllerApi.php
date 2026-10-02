<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MobileAppPolicy;
use App\Models\MobileAppSetting;
use Illuminate\Http\JsonResponse;

class AppSettingControllerApi extends Controller
{
    /**
     * Customer mobile app settings (logo, splash, colors, banners, theme, maintenance).
     * GET /api/customer/app-settings
     */
    public function index(): JsonResponse
    {
        $setting = MobileAppSetting::getOrCreate('customer');

        return response()->json([
            'success' => true,
            'message' => 'Customer app settings fetched successfully',
            'data' => $this->formatSetting($setting),
        ]);
    }

    /**
     * Customer mobile app onboarding screens.
     * GET /api/customer/onboarding
     */
    public function onboarding(): JsonResponse
    {
        $setting = MobileAppSetting::getOrCreate('customer');
        $toUrl = fn (?string $path): ?string => \App\Models\LoanType::formatImageUrl($path);

        $onboardingScreens = collect($setting->onboarding_screens ?? [])
            ->map(function ($screen) use ($toUrl) {
                return [
                    'title' => $screen['title'] ?? '',
                    'description' => $screen['description'] ?? '',
                    'image' => $toUrl($screen['image'] ?? null),
                ];
            })
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Customer app onboarding screens fetched successfully',
            'data' => $onboardingScreens,
        ]);
    }

    /**
     * Active privacy policy / terms for the customer app.
     * GET /api/customer/app-settings/policies/{type?}
     */
    public function policies(?string $type = null): JsonResponse
    {
        $query = MobileAppPolicy::query()
            ->where('app_type', 'customer')
            ->where('status', 'active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id');

        if ($type) {
            $normalized = str_replace('-', '_', strtolower($type));
            if (! in_array($normalized, ['privacy_policy', 'terms_conditions', 'about_us'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid policy type. Use privacy_policy, terms_conditions, or about_us.',
                ], 422);
            }
            $query->where('type', $normalized);
        }

        $policies = $query->get()->map(fn (MobileAppPolicy $p) => $this->formatPolicy($p));

        return response()->json([
            'success' => true,
            'message' => 'Customer app policies fetched successfully',
            'data' => $policies,
        ]);
    }

    /**
     * GET /api/customer/privacy_policy
     */
    public function privacyPolicy(): JsonResponse
    {
        return $this->latestPolicyResponse('privacy_policy', 'Privacy policy fetched successfully');
    }

    /**
     * GET /api/customer/terms_conditions
     */
    public function termsConditions(): JsonResponse
    {
        return $this->latestPolicyResponse('terms_conditions', 'Terms & conditions fetched successfully');
    }

    /**
     * GET /api/customer/about-us
     */
    public function aboutUs(): JsonResponse
    {
        $policy = MobileAppPolicy::query()
            ->where('app_type', 'customer')
            ->where('type', 'about_us')
            ->where('status', 'active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        // Use SettingsHelper to fetch Company details
        $companyDetails = [
            'company_name' => \App\Helpers\SettingsHelper::get('admin_title', 'Our Company'),
            'company_subtitle' => \App\Helpers\SettingsHelper::get('admin_subtitle'),
            'company_logo' => \App\Helpers\SettingsHelper::get('admin_logo') ? url(\App\Helpers\SettingsHelper::get('admin_logo')) : null,
            'footer_text' => \App\Helpers\SettingsHelper::get('footer_text'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'About Us fetched successfully',
            'data' => [
                'company_details' => $companyDetails,
                'about_us' => $policy ? $this->formatPolicy($policy) : null,
            ]
        ]);
    }

    protected function latestPolicyResponse(string $type, string $message): JsonResponse
    {
        $policy = MobileAppPolicy::query()
            ->where('app_type', 'customer')
            ->where('type', $type)
            ->where('status', 'active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => 'No active ' . str_replace('_', ' ', $type) . ' found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->formatPolicy($policy),
        ]);
    }

    protected function formatPolicy(MobileAppPolicy $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'title' => $p->title,
            'version' => $p->version,
            'effective_date' => optional($p->effective_date)->format('Y-m-d'),
            'content' => $p->content,
            'status' => $p->status,
        ];
    }

    protected function formatSetting(MobileAppSetting $setting): array
    {
        $toUrl = fn (?string $path): ?string => \App\Models\LoanType::formatImageUrl($path);

        $banners = collect($setting->banner_images ?? [])
            ->filter()
            ->map(fn ($path) => $toUrl($path))
            ->values()
            ->all();
        $loanBanners = collect($setting->loan_banner_images ?? [])
            ->filter()
            ->map(fn ($path) => $toUrl($path))
            ->values()
            ->all();
        $chitBanners = collect($setting->chit_banner_images ?? [])
            ->filter()
            ->map(fn ($path) => $toUrl($path))
            ->values()
            ->all();
        $fdBanners = collect($setting->fd_banner_images ?? [])
            ->filter()
            ->map(fn ($path) => $toUrl($path))
            ->values()
            ->all();

        $onboardingScreens = collect($setting->onboarding_screens ?? [])
            ->map(function ($screen) use ($toUrl) {
                return [
                    'title' => $screen['title'] ?? '',
                    'description' => $screen['description'] ?? '',
                    'image' => $toUrl($screen['image'] ?? null),
                ];
            })
            ->all();

        return [
            'app_type' => 'customer',
            'app_logo' => $toUrl($setting->app_logo),
            'splash_image' => $toUrl($setting->splash_image),
            'primary_color' => $setting->primary_color,
            'secondary_color' => $setting->secondary_color,
            'background_color' => $setting->background_color,
            'overview_tab_color' => $setting->overview_tab_color ?? '#696CFF',
            'loan_tab_color' => $setting->loan_tab_color ?? '#00CFDD',
            'chit_tab_color' => $setting->chit_tab_color ?? '#7367F0',
            'fixed_deposit_tab_color' => $setting->fixed_deposit_tab_color ?? '#FF4C51',
            'tab_colors' => [
                'overview' => $setting->overview_tab_color ?? '#696CFF',
                'loan' => $setting->loan_tab_color ?? '#00CFDD',
                'chit' => $setting->chit_tab_color ?? '#7367F0',
                'fixed_deposit' => $setting->fixed_deposit_tab_color ?? '#FF4C51',
            ],
            'banner_images' => $banners,
            'loan_banner_images' => $loanBanners,
            'chit_banner_images' => $chitBanners,
            'fd_banner_images' => $fdBanners,
            'banners' => [
                'overview' => $banners,
                'loan' => $loanBanners,
                'chit' => $chitBanners,
                'fd' => $fdBanners,
            ],
            'onboarding_screens' => $onboardingScreens,
            'theme_mode' => $setting->theme_mode,
            'welcome_message' => $setting->welcome_message,
            'maintenance_mode' => (bool) $setting->maintenance_mode,
        ];
    }
}
