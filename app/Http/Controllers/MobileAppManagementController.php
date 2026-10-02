<?php

namespace App\Http\Controllers;

use App\Models\MobileAppPolicy;
use App\Models\MobileAppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class MobileAppManagementController extends Controller
{
    /**
     * Parse raw route appType parameter into database enum string ('customer' or 'agent').
     */
    protected function resolveAppType(string $appType): string
    {
        $normalized = strtolower(trim($appType));
        if (in_array($normalized, ['customer', 'customer-app', 'customer_app'])) {
            return 'customer';
        }
        if (in_array($normalized, ['agent', 'agent-app', 'agent_app'])) {
            return 'agent';
        }
        abort(404, 'Invalid app type');
    }

    /**
     * Display Customer App or Agent App settings and dynamic policy tables.
     */
    public function index(string $appType): View
    {
        $dbAppType = $this->resolveAppType($appType);
        $setting = MobileAppSetting::getOrCreate($dbAppType);

        $privacyPolicies = MobileAppPolicy::where('app_type', $dbAppType)
            ->where('type', 'privacy_policy')
            ->orderBy('id', 'desc')
            ->get();

        $termsConditions = MobileAppPolicy::where('app_type', $dbAppType)
            ->where('type', 'terms_conditions')
            ->orderBy('id', 'desc')
            ->get();

        $aboutUsPolicies = MobileAppPolicy::where('app_type', $dbAppType)
            ->where('type', 'about_us')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.mobile-apps.index', compact('appType', 'dbAppType', 'setting', 'privacyPolicies', 'termsConditions', 'aboutUsPolicies'));
    }

    /**
     * Update App Details (Logo, Splash, Banner images, Colors, Theme, Welcome Message, Maintenance Mode).
     */
    public function updateSettings(Request $request, string $appType): RedirectResponse
    {
        $dbAppType = $this->resolveAppType($appType);
        $setting = MobileAppSetting::getOrCreate($dbAppType);

        $rules = [
            'app_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'splash_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'banner_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'loan_banner_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'chit_banner_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'fd_banner_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ];

        if ($request->has('primary_color')) {
            $rules = array_merge($rules, [
                'primary_color' => 'required|string|max:20',
                'secondary_color' => 'required|string|max:20',
                'background_color' => 'required|string|max:20',
                'overview_tab_color' => 'nullable|string|max:20',
                'loan_tab_color' => 'nullable|string|max:20',
                'chit_tab_color' => 'nullable|string|max:20',
                'fixed_deposit_tab_color' => 'nullable|string|max:20',
                'theme_mode' => 'required|in:light,dark,system',
                'welcome_message' => 'nullable|string|max:1000',
            ]);
        }

        $request->validate($rules);

        $uploadPath = public_path('uploads/mobile-apps/' . $dbAppType);
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true, true);
        }

        // App Logo upload
        if ($request->hasFile('app_logo')) {
            if ($setting->app_logo && File::exists(public_path($setting->app_logo))) {
                File::delete(public_path($setting->app_logo));
            }
            $logoName = 'logo_' . time() . '.' . $request->file('app_logo')->getClientOriginalExtension();
            $request->file('app_logo')->move($uploadPath, $logoName);
            $setting->app_logo = 'uploads/mobile-apps/' . $dbAppType . '/' . $logoName;
        }

        // Splash Image upload
        if ($request->hasFile('splash_image')) {
            if ($setting->splash_image && File::exists(public_path($setting->splash_image))) {
                File::delete(public_path($setting->splash_image));
            }
            $splashName = 'splash_' . time() . '.' . $request->file('splash_image')->getClientOriginalExtension();
            $request->file('splash_image')->move($uploadPath, $splashName);
            $setting->splash_image = 'uploads/mobile-apps/' . $dbAppType . '/' . $splashName;
        }

        $setting->banner_images = $this->appendBannerUploads(
            $request, 'banner_images', is_array($setting->banner_images) ? $setting->banner_images : [], $uploadPath, $dbAppType, 'banner'
        );
        $setting->loan_banner_images = $this->appendBannerUploads(
            $request, 'loan_banner_images', is_array($setting->loan_banner_images) ? $setting->loan_banner_images : [], $uploadPath, $dbAppType, 'loan_banner'
        );
        $setting->chit_banner_images = $this->appendBannerUploads(
            $request, 'chit_banner_images', is_array($setting->chit_banner_images) ? $setting->chit_banner_images : [], $uploadPath, $dbAppType, 'chit_banner'
        );
        $setting->fd_banner_images = $this->appendBannerUploads(
            $request, 'fd_banner_images', is_array($setting->fd_banner_images) ? $setting->fd_banner_images : [], $uploadPath, $dbAppType, 'fd_banner'
        );

        if ($request->has('primary_color')) {
            $setting->primary_color = $request->input('primary_color');
            $setting->secondary_color = $request->input('secondary_color');
            $setting->background_color = $request->input('background_color');
            $setting->overview_tab_color = $request->input('overview_tab_color', '#696CFF');
            $setting->loan_tab_color = $request->input('loan_tab_color', '#00CFDD');
            $setting->chit_tab_color = $request->input('chit_tab_color', '#7367F0');
            $setting->fixed_deposit_tab_color = $request->input('fixed_deposit_tab_color', '#FF4C51');
            $setting->theme_mode = $request->input('theme_mode');
            $setting->welcome_message = $request->input('welcome_message');
            $setting->maintenance_mode = $request->boolean('maintenance_mode');
        }

        $setting->save();

        return redirect()->back()->with('success', ucfirst($dbAppType) . ' App settings updated successfully.');
    }

    /**
     * Remove individual banner image from gallery.
     */
    public function deleteBannerImage(Request $request, string $appType): JsonResponse
    {
        $dbAppType = $this->resolveAppType($appType);
        $setting = MobileAppSetting::getOrCreate($dbAppType);

        $imagePath = $request->input('image_path');
        $group = $request->input('banner_group', 'overview');
        $column = match ($group) {
            'loan' => 'loan_banner_images',
            'chit' => 'chit_banner_images',
            'fd' => 'fd_banner_images',
            default => 'banner_images',
        };

        $currentBanners = is_array($setting->{$column}) ? $setting->{$column} : [];

        if (($key = array_search($imagePath, $currentBanners)) !== false) {
            unset($currentBanners[$key]);
            if (File::exists(public_path($imagePath))) {
                File::delete(public_path($imagePath));
            }
            $setting->{$column} = array_values($currentBanners);
            $setting->save();

            return response()->json(['success' => true, 'message' => 'Banner image removed.']);
        }

        return response()->json(['success' => false, 'message' => 'Image not found.'], 404);
    }

    /**
     * Store new Privacy Policy or Terms & Conditions entry.
     */
    public function storePolicy(Request $request, string $appType): RedirectResponse
    {
        $dbAppType = $this->resolveAppType($appType);

        $request->validate([
            'type' => 'required|in:privacy_policy,terms_conditions,about_us',
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'effective_date' => 'nullable|date',
            'content' => 'required|string',
            'status' => 'required|in:active,draft,archived',
        ]);

        MobileAppPolicy::create([
            'app_type' => $dbAppType,
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'version' => $request->input('version'),
            'effective_date' => $request->input('effective_date'),
            'content' => $request->input('content'),
            'status' => $request->input('status'),
        ]);

        $typeName = match($request->input('type')) {
            'privacy_policy' => 'Privacy Policy',
            'terms_conditions' => 'Terms & Conditions',
            'about_us' => 'About Us',
            default => 'Policy'
        };

        return redirect()->back()->with('success', "New {$typeName} record added successfully.");
    }

    /**
     * Update existing Policy or Terms record.
     */
    public function updatePolicy(Request $request, int $id): RedirectResponse
    {
        $policy = MobileAppPolicy::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'effective_date' => 'nullable|date',
            'content' => 'required|string',
            'status' => 'required|in:active,draft,archived',
        ]);

        $policy->update([
            'title' => $request->input('title'),
            'version' => $request->input('version'),
            'effective_date' => $request->input('effective_date'),
            'content' => $request->input('content'),
            'status' => $request->input('status'),
        ]);

        $typeName = match($policy->type) {
            'privacy_policy' => 'Privacy Policy',
            'terms_conditions' => 'Terms & Conditions',
            'about_us' => 'About Us',
            default => 'Policy'
        };

        return redirect()->back()->with('success', "{$typeName} record updated successfully.");
    }

    /**
     * Delete Policy or Terms record.
     */
    public function destroyPolicy(int $id): JsonResponse
    {
        $policy = MobileAppPolicy::findOrFail($id);
        $policy->delete();

        return response()->json(['success' => true, 'message' => 'Record deleted successfully.']);
    }

    /**
     * Toggle status (Active / Draft / Archived).
     */
    public function togglePolicyStatus(int $id): JsonResponse
    {
        $policy = MobileAppPolicy::findOrFail($id);
        $policy->status = $policy->status === 'active' ? 'draft' : 'active';
        $policy->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated to ' . ucfirst($policy->status),
            'status' => $policy->status
        ]);
    }

    public function storeOnboardingScreen(Request $request, string $appType): RedirectResponse
    {
        $dbAppType = $this->resolveAppType($appType);
        $setting = MobileAppSetting::getOrCreate($dbAppType);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);

        $uploadPath = public_path('uploads/mobile-apps/' . $dbAppType . '/onboarding');
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true, true);
        }

        $imageName = 'onboarding_' . time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();
        $request->file('image')->move($uploadPath, $imageName);
        $relPath = 'uploads/mobile-apps/' . $dbAppType . '/onboarding/' . $imageName;

        // Ensure storage linking
        $storageDest = storage_path('app/public/' . $relPath);
        File::ensureDirectoryExists(dirname($storageDest));
        File::copy($uploadPath . '/' . $imageName, $storageDest);

        $newScreen = [
            'id' => uniqid(),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'image' => $relPath,
        ];

        $currentScreens = is_array($setting->onboarding_screens) ? $setting->onboarding_screens : [];
        $currentScreens[] = $newScreen;

        $setting->onboarding_screens = $currentScreens;
        $setting->save();

        return redirect()->back()->with('success', 'Onboarding screen added successfully.');
    }

    public function deleteOnboardingScreen(Request $request, string $appType): JsonResponse
    {
        $dbAppType = $this->resolveAppType($appType);
        $setting = MobileAppSetting::getOrCreate($dbAppType);

        $screenId = $request->input('id');
        $currentScreens = is_array($setting->onboarding_screens) ? $setting->onboarding_screens : [];

        $filteredScreens = [];
        $deleted = false;

        foreach ($currentScreens as $screen) {
            if ($screen['id'] === $screenId) {
                if (isset($screen['image']) && File::exists(public_path($screen['image']))) {
                    File::delete(public_path($screen['image']));
                }
                $deleted = true;
            } else {
                $filteredScreens[] = $screen;
            }
        }

        if ($deleted) {
            $setting->onboarding_screens = $filteredScreens;
            $setting->save();
            return response()->json(['success' => true, 'message' => 'Onboarding screen deleted successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Onboarding screen not found.'], 404);
    }

    protected function appendBannerUploads(
        Request $request,
        string $inputName,
        array $current,
        string $uploadPath,
        string $dbAppType,
        string $prefix
    ): array {
        if (! $request->hasFile($inputName)) {
            return array_values($current);
        }

        foreach ($request->file($inputName) as $index => $bannerFile) {
            if (! $bannerFile) {
                continue;
            }
            $bannerName = $prefix . '_' . time() . '_' . $index . '.' . $bannerFile->getClientOriginalExtension();
            $bannerFile->move($uploadPath, $bannerName);
            $relPath = 'uploads/mobile-apps/' . $dbAppType . '/' . $bannerName;
            $current[] = $relPath;

            // Also copy to storage/app/public for symlink compatibility
            $storageDest = storage_path('app/public/' . $relPath);
            File::ensureDirectoryExists(dirname($storageDest));
            File::copy($uploadPath . '/' . $bannerName, $storageDest);
        }

        return array_values($current);
    }
}
