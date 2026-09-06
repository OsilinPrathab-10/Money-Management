<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Location;

class LocationControllerApi extends Controller
{
    public function index(Request $request)
    {
        $query = Location::query();

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        $locations = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }
}
