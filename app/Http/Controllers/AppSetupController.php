<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Slide;
use App\Models\Appearance;
use App\Models\ApplicationInfo;
use Illuminate\Support\Facades\Storage;

class AppSetupController extends Controller
{
    /**
     * Display slide list
     */
    public function slideIndex()
    {
        $slides = Slide::orderBy('id', 'desc')->get();
        return view('admin.app-setup.slides.slides', compact('slides'));
    }

    /**
     * Show create slide form
     */
    public function slideCreate()
    {
        return view('admin.app-setup.slides.slides-edit', [
            'slideId' => null,
            'slideTitle' => '',
            'slideDescription' => '',
            'slideImage' => null,
            'slideType' => 'onboarding',
        ]);
    }

    /**
     * Store new slide
     */
    public function slideStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:onboarding,banner,other',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('slides', 'public');
                $validated['image_path'] = $imagePath;
            }

            Slide::create($validated);

            return redirect()->route('app-setup-slides')->with('success', 'Slide created successfully!');
        } catch (\Exception $e) {
            return redirect()->route('app-setup-slides')->with('error', 'Failed to create slide: ' . $e->getMessage());
        }
    }

    /**
     * Show edit slide form
     */
    public function slideEdit($id)
    {
        $slide = Slide::findOrFail($id);

        return view('admin.app-setup.slides.slides-edit', [
            'slideId' => $slide->getRouteKey(),
            'slideTitle' => $slide->title,
            'slideDescription' => $slide->description,
            'slideImage' => $slide->image_path,
            'slideType' => $slide->type ?? 'onboarding',
        ]);
    }

    /**
     * Update slide
     */
    public function slideUpdate(Request $request, $id)
    {
        try {
            $slide = Slide::findOrFail($id);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:onboarding,banner,other',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($request->hasFile('image')) {
                // Delete old image
                if ($slide->image_path) {
                    Storage::disk('public')->delete($slide->image_path);
                }

                $imagePath = $request->file('image')->store('slides', 'public');
                $validated['image_path'] = $imagePath;
            }

            $slide->update($validated);

            return redirect()->route('app-setup-slides')->with('success', 'Slide updated successfully!');
        } catch (\Exception $e) {
            return redirect()->route('app-setup-slides')->with('error', 'Failed to update slide: ' . $e->getMessage());
        }
    }

    /**
     * Delete slide
     */
    public function slideDestroy($id)
    {
        try {
            $slide = Slide::findOrFail($id);

            // Delete image
            if ($slide->image_path) {
                Storage::disk('public')->delete($slide->image_path);
            }

            $slide->delete();

            return response()->json(['success' => true, 'message' => 'Slide deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete slide: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display appearance settings
     */
    public function appearanceIndex()
    {
        $appearance = Appearance::firstOrCreate(['type' => 'app'], [
            'primary_color' => '#00A0EA',
            'secondary_color' => '#00A79D',
            'title' => config('app.name'),
            'type' => 'app',
            'logo' => '',
            'favicon' => '',
            'footer_text' => ''
        ]);

        return view('admin.app-setup.appearance.appearance', compact('appearance'));
    }

    public function appInfoIndex()
    {
        $appInfo = ApplicationInfo::firstOrCreate(
            ['platform' => 'android'],
            [
                'version_name' => '1.0.0',
                'version_code' => 1,
                'app_name' => config('app.name'),
                'package_name' => null,
                'release_notes' => null,
                'force_update' => false,
            ]
        );

        return view('admin.app-setup.app-info.app-info', compact('appInfo'));
    }

    /**
     * Update appearance settings
     */
    public function appearanceUpdate(Request $request)
    {
        $section = $request->input('section', 'colors');

        try {
            $appearance = Appearance::where('type', 'app')->firstOrFail();

            if (in_array($section, ['colors', 'all'], true)) {
                $validatedColors = $request->validate([
                    'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                    'secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                ]);

                $appearance->fill($validatedColors);
            }

            if (in_array($section, ['branding', 'all'], true)) {
                $validatedBranding = $request->validate([
                    'title' => 'required|string|max:255',
                    'footer_text' => 'nullable|string|max:255',
                    'logo' => 'nullable|image|mimes:jpeg,png,svg|max:4096',
                    'favicon' => 'nullable|image|mimes:jpeg,png,ico,svg|max:2048',
                ]);

                if ($request->hasFile('logo')) {
                    if ($appearance->logo) {
                        Storage::disk('public')->delete($appearance->logo);
                    }
                    $validatedBranding['logo'] = $request->file('logo')->store('app/appearance', 'public');
                }

                if ($request->hasFile('favicon')) {
                    if ($appearance->favicon) {
                        Storage::disk('public')->delete($appearance->favicon);
                    }
                    $validatedBranding['favicon'] = $request->file('favicon')->store('app/appearance', 'public');
                }

                $appearance->fill($validatedBranding);
            }

            $appearance->save();

            if ($section === 'colors') {
                return redirect()->back()->with('success', 'Color configuration saved successfully!');
            }

            if ($section === 'branding') {
                return redirect()->back()->with('success', 'Branding details updated successfully!');
            }

            if ($section === 'all') {
                return redirect()->back()->with('success', 'Appearance settings saved successfully!');
            }

            return redirect()->back()->with('warning', 'No changes were made.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update appearance: ' . $e->getMessage());
        }
    }

    public function appInfoUpdate(Request $request)
    {
        try {
            $validatedAppInfo = $request->validate([
                'app_name' => 'required|string|max:255',
                'version_name' => 'required|string|max:50',
                'version_code' => 'required|integer|min:1',
                'package_name' => 'nullable|string|max:255',
                'release_notes' => 'nullable|string',
                'force_update' => 'nullable|boolean',
            ]);

            ApplicationInfo::updateOrCreate(
                ['platform' => 'android'],
                array_merge($validatedAppInfo, ['force_update' => $request->boolean('force_update')])
            );

            return redirect()->route('app-setup-app-info')->with('success', 'Application information saved successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update application info: ' . $e->getMessage());
        }
    }
}
