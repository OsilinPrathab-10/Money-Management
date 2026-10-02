<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\MobileAppPolicy;
use App\Models\MobileAppSetting;
use App\Models\CompanyDetail;
use Illuminate\Http\JsonResponse;

class AppSettingControllerApi extends Controller
{
    /**
     * Agent mobile app settings (logo, splash, colors, banners, theme, maintenance).
     * GET /api/agent/app-settings
     */
    public function index(): JsonResponse
    {
        $setting = MobileAppSetting::getOrCreate('agent');

        return response()->json([
            'success' => true,
            'message' => 'Agent app settings fetched successfully',
            'data' => $this->formatSetting($setting),
        ]);
    }

    /**
     * Active privacy policy / terms for the agent app.
     * GET /api/agent/app-settings/policies/{type?}
     */
    public function policies(?string $type = null): JsonResponse
    {
        $query = MobileAppPolicy::query()
            ->where('app_type', 'agent')
            ->where('status', 'active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id');

        if ($type) {
            $normalized = str_replace('-', '_', strtolower($type));
            if (! in_array($normalized, ['privacy_policy', 'terms_conditions'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid policy type. Use privacy_policy or terms_conditions.',
                ], 422);
            }
            $query->where('type', $normalized);
        }

        $policies = $query->get()->map(fn (MobileAppPolicy $p) => $this->formatPolicy($p));

        return response()->json([
            'success' => true,
            'message' => 'Agent app policies fetched successfully',
            'data' => $policies,
        ]);
    }

    /**
     * GET /api/agent/privacy_policy
     */
    public function privacyPolicy(): JsonResponse
    {
        return $this->latestPolicyResponse('privacy_policy', 'Privacy policy fetched successfully');
    }

    /**
     * GET /api/agent/terms_conditions
     */
    public function termsConditions(): JsonResponse
    {
        return $this->latestPolicyResponse('terms_conditions', 'Terms & conditions fetched successfully');
    }

    /**
     * GET /api/agent/help-support
     */
    public function helpSupport(): JsonResponse
    {
        $companyDetail = CompanyDetail::first();

        if (! $companyDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Company details not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Help & support details fetched successfully',
            'data' => $companyDetail,
        ]);
    }

    protected function latestPolicyResponse(string $type, string $message): JsonResponse
    {
        $policy = MobileAppPolicy::query()
            ->where('app_type', 'agent')
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
        $toUrl = function (?string $path): ?string {
            if (! $path) {
                return null;
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return url($path);
        };

        $banners = collect($setting->banner_images ?? [])
            ->filter()
            ->map(fn ($path) => $toUrl($path))
            ->values()
            ->all();

        return [
            'app_type' => 'agent',
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
            'theme_mode' => $setting->theme_mode,
            'welcome_message' => $setting->welcome_message,
            'maintenance_mode' => (bool) $setting->maintenance_mode,
        ];
    }
}
