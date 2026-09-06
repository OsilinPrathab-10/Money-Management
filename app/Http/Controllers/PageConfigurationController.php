<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PageConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageConfigurationController extends Controller
{
    public function index()
    {
        $pageConfiguration = PageConfiguration::ordered()->get();
        return view('admin.setup-configuration.page-configuration.page-configuration', compact('pageConfiguration'));
    }
    
    public function create()
    {
        return view('admin.setup-configuration.page-configuration.page-configuration-edit', [
            'pageId' => null,
            'pageName' => 'New Policy Page',
            'pageContent' => '',
            'pageStatus' => 'active'
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'content' => 'required',
                'status' => 'required|in:active,inactive'
            ]);

            $validated['slug'] = Str::slug($request->name);
            $validated['icon'] = $request->icon ?? 'ri-file-text-line';
            $validated['order'] = PageConfiguration::max('order') + 1;

            PageConfiguration::create($validated);

            return redirect()->route('page-configuration')->with('success', 'Policy page created successfully!');
        } catch (\Exception $e) {
            return redirect()->route('page-configuration')->with('error', 'Failed to create policy page: ' . $e->getMessage());
        }
    }
    
    public function edit($id)
    {
        $page = PageConfiguration::findOrFail($id);

        return view('admin.setup-configuration.page-configuration.page-configuration-edit', [
            'pageId' => $page->id,
            'pageName' => $page->name,
            'pageContent' => $page->content,
            'pageStatus' => $page->status
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $page = PageConfiguration::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'content' => 'required',
                'status' => 'required|in:active,inactive'
            ]);

            $validated['slug'] = Str::slug($request->name);

            $page->update($validated);

            return redirect()->route('page-configuration')->with('success', 'Policy page updated successfully!');
        } catch (\Exception $e) {
            return redirect()->route('page-configuration')->with('error', 'Failed to update policy page: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $page = PageConfiguration::findOrFail($id);
            $page->delete();

            return response()->json(['success' => true, 'message' => 'Policy page deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete policy page: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a policy page for public access
     */
    public function show($slug)
    {
        try {
            $page = PageConfiguration::where('slug', $slug)
                ->where('status', 'active')
                ->firstOrFail();

            return view('public.public-page', compact('page'));
        } catch (\Exception $e) {
            abort(404, 'Page not found');
        }
    }

}