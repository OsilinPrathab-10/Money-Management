<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;

class PageControllerApi extends Controller
{
    public function getPage($id = null)
    {
        // If an ID is provided → return that specific page
        if ($id) {
            $page = Page::find($id);

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $page,
            ], 200);
        }

        // If no ID → return all pages
        $pages = Page::select('id', 'title', 'slug')->get();

        return response()->json([
            'success' => true,
            'data' => $pages,
        ], 200);
    }
}
