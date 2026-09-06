<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slide;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\SlideResource;

class SlideController extends Controller
{
    public function slideImgs()
    {
        $slides = Slide::where('type', 'onboarding')->get();

        return response()->json([
            'status' => true,
            'message' => 'Slide images retrieved successfully',
            'data' => SlideResource::collection($slides)
        ]);
    }

    // Store new onboarding image
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // store file
        $path = $request->file('image')->store('onboarding', 'public');

        $onboarding = Slide::create([
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $path,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Onboarding slide created successfully',
            'data' => $onboarding
        ]);
    }

    // Delete onboarding image
    public function destroy($id)
    {
        $onboarding = Slide::findOrFail($id);

        // delete image file
        Storage::disk('public')->delete($onboarding->image_path);
        $onboarding->delete();

        return response()->json([
            'status' => true,
            'message' => 'Onboarding slide deleted successfully'
        ]);
    }
}
