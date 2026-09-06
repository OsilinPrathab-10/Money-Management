<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\PolicyPage;
use Illuminate\Http\Request;

class PolicyPageControllerApi extends Controller
{
    /**
     * Get all active policy pages
     */
    public function index()
    {
        $pages = PolicyPage::where('status', 'active')
            ->orderBy('order', 'asc')
            ->select('id', 'name', 'slug', 'icon','content')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Policy pages fetched successfully.',
            'data' => $pages,
        ]);
    }

    /**
     * Get a specific policy page by slug
     */
    public function show($slug)
    {
        $page = PolicyPage::where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (!$page) {
            return response()->json([
                'status' => false,
                'message' => 'Page not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Page fetched successfully.',
            'data' => $page,
        ]);
    }
}
