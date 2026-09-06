<?php

namespace App\Http\Controllers;

use App\Helpers\AppearanceHelper;
use App\Helpers\SettingsHelper;
use App\Models\Appearance;
use App\Models\CompanyDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WebsiteSetupController extends Controller
{
    /**
     * Homepage Setup - Logo and Text Settings + Company Details
     */
    public function homepage()
    {
        $appearance = Appearance::where('type', 'web')->firstOrCreate(
            ['type' => 'web'],
            [
                'primary_color' => '#696cff',
                'secondary_color' => '#8592a3',
                'title' => 'Loan App',
                'subtitle' => '',
                'logo' => '',
                'logo_dark' => '',
                'favicon' => '',
                'footer_text' => ''
            ]
        );

        // Get or create company details
        $companyDetail = CompanyDetail::firstOrCreate(
            ['id' => 1],
            [
                'company_name' => $appearance->title ?? 'Loan App',
                'company_slogan' => $appearance->subtitle ?? '',
                'company_email' => '',
                'company_mobile' => '',
                'website_url' => config('app.url'),
                'country' => 'India'
            ]
        );

        return view('admin.website-setup.homepage', compact('appearance', 'companyDetail'));
    }

    /**
     * Update Homepage Settings - Saves to both appearance and company_details tables
     */
    public function updateHomepage(Request $request)
    {
        $request->validate([
            // Appearance table fields
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'logo_dark' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'favicon' => 'nullable|image|mimes:png,jpg,jpeg,ico|max:1024',
            
            // Company Details fields
            'company_name' => 'required|string|max:255',
            'company_slogan' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_mobile' => 'required|string|digits:10',
            'alternate_mobile' => 'nullable|string|digits:10',
            'support_email' => 'nullable|email|max:255',
            'support_mobile' => 'nullable|string|digits:10',
            'website_url' => 'nullable|url|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:15',
            'country' => 'nullable|string|max:100',
            'facebook_url' => 'nullable|url|max:255',
            'twitter_url' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'agent_contact_email' => 'nullable|email|max:255',
            'agent_contact_mobile' => 'nullable|string|digits:10',
            'working_hours' => 'nullable|string|max:255',
            // India-specific tax/company IDs
            'gst_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'
            ],
            'pan_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'
            ],
            'cin_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/'
            ],
        ]);

        // Update Appearance table
        $appearance = Appearance::where('type', 'web')->firstOrFail();
        $appearanceData = [];

        // Handle logo upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            if ($appearance->logo && Storage::disk('public')->exists($appearance->logo)) {
                Storage::disk('public')->delete($appearance->logo);
            }
            $logoPath = $request->file('logo')->store('admin/logos', 'public');
            $appearanceData['logo'] = $logoPath;
        }

        // Handle dark logo upload
        if ($request->hasFile('logo_dark')) {
            if ($appearance->logo_dark && Storage::disk('public')->exists($appearance->logo_dark)) {
                Storage::disk('public')->delete($appearance->logo_dark);
            }
            $appearanceData['logo_dark'] = $request->file('logo_dark')->store('admin/logos', 'public');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            if ($appearance->favicon && Storage::disk('public')->exists($appearance->favicon)) {
                Storage::disk('public')->delete($appearance->favicon);
            }
            $appearanceData['favicon'] = $request->file('favicon')->store('admin/logos', 'public');
        }

        // Save title and subtitle to appearance table (from company name/slogan)
        $appearanceData['title'] = $request->company_name;
        $appearanceData['subtitle'] = $request->company_slogan;

        $appearance->update($appearanceData);

        // Update Company Details table
        $companyDetail = CompanyDetail::firstOrCreate(['id' => 1]);
        
        $companyData = [
            'company_name' => $request->company_name,
            'company_slogan' => $request->company_slogan,
            'company_email' => $request->company_email,
            'company_mobile' => $request->company_mobile,
            'alternate_mobile' => $request->alternate_mobile,
            'support_email' => $request->support_email,
            'support_mobile' => $request->support_mobile,
            'website_url' => $request->website_url ?? config('app.url'),
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'country' => 'India', // Always India
            'gst_number' => $request->gst_number,
            'pan_number' => $request->pan_number,
            'cin_number' => $request->cin_number,
            'facebook_url' => $request->facebook_url,
            'twitter_url' => $request->twitter_url,
            'linkedin_url' => $request->linkedin_url,
            'instagram_url' => $request->instagram_url,
            'agent_contact_email' => $request->agent_contact_email,
            'agent_contact_mobile' => $request->agent_contact_mobile,
            'working_hours' => $request->working_hours,
        ];

        // Store logo path in company_details table as well
        if ($logoPath) {
            $companyData['logo_path'] = $logoPath;
        }

        $companyDetail->update($companyData); 
        SettingsHelper::clearCache();

        return redirect()->route('website-homepage')->with('success', 'Company details updated successfully!');
    }

    /**
     * Appearance Setup - Theme Customization
     */
    public function appearance()
    {
        // Get from database with type='web' for admin panel
        $appearance = Appearance::where('type', 'web')->firstOrCreate(
            ['type' => 'web'],
            [
                'primary_color' => '#696cff',
                'secondary_color' => '#8592a3',
                'title' => 'Loan App',
                'subtitle' => '',
                'logo' => '',
                'logo_dark' => '',
                'favicon' => '',
                'footer_text' => '',
                'theme_mode' => 'light'
            ]
        );

        return view('admin.website-setup.appearance', compact('appearance'));
    }

    /**
     * Update Appearance Settings
     */
    public function updateAppearance(Request $request)
    {
        $request->validate([
            'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_mode' => 'nullable|string|in:light,dark,system',
            'loader_animation' => 'nullable|string|in:loader1,loader2,loader3,loader_favicon',
        ]);

        // Get or create web appearance record
        $appearance = Appearance::where('type', 'web')->firstOrFail();
        
        // Update primary color
        $appearance->primary_color = $request->primary_color;
        
        // Update secondary color if provided
        if ($request->has('secondary_color')) {
            $appearance->secondary_color = $request->secondary_color;
        }
        
        // Update theme mode if provided
        if ($request->has('theme_mode')) {
            $appearance->theme_mode = $request->theme_mode;
        }
        
        // Update loader animation if provided
        if ($request->has('loader_animation')) {
            $appearance->loader_animation = $request->loader_animation;
        }
        
        $appearance->save();

        // Keep mobile app theme in sync with admin appearance colors
        Appearance::updateOrCreate(
            ['type' => 'app'],
            [
                'primary_color' => $appearance->primary_color,
                'secondary_color' => $appearance->secondary_color,
            ]
        );
        
        // Sync with AppearanceHelper cache
        AppearanceHelper::update([
            'primary_color' => $appearance->primary_color,
            'secondary_color' => $appearance->secondary_color,
            'theme_mode' => $appearance->theme_mode,
            'loader_animation' => $appearance->loader_animation,
        ]);
        
        // Clear settings cache so changes take effect immediately
        SettingsHelper::clearCache();
        
        return response()->json([
            'success' => true,
            'message' => 'Appearance settings updated successfully!'
        ]);
    }
}
