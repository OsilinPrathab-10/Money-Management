<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\Faq;
use Illuminate\Support\Facades\Auth;

class PolicyControllerApi extends Controller
{
    public function getPolicies($slug = null)
    {
        // If a slug is provided → return that specific page
        if ($slug) {
            $page = Page::where('slug', $slug)->first();

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found.',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $page,
            ], 200);
        }

        // If no slug → return all pages
        $pages = Page::select('id', 'title', 'slug')->get();

        return response()->json([
            'success' => true,
            'data' => $pages,
        ], 200);
    }

    /**
     * Record user agreement or disagreement
     */
    public function acceptPolicies(Request $request)
    {
        $validated = $request->validate([
            'accepted_terms' => 'required|boolean',
            'accepted_privacy' => 'required|boolean',
        ]);

        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($validated['accepted_terms'] && $validated['accepted_privacy']) {
            $client->update([
                'accepted_terms' => true,
                'accepted_privacy' => true,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'You have successfully accepted the Terms & Privacy Policy.',
            ]);
        }

        $client->update([
            'accepted_terms' => false,
            'accepted_privacy' => false,
        ]);

        return response()->json([
            'status' => false,
            'message' => 'You must accept the Terms & Privacy Policy to continue.',
        ], 403);
    }

    public function faq()
    {
        $faqs = Faq::orderBy('order', 'asc')->get(['id', 'question', 'answer']);

        return response()->json([
            'status' => true,
            'message' => 'FAQs fetched successfully.',
            'data' => $faqs,
        ]);
    }
}
