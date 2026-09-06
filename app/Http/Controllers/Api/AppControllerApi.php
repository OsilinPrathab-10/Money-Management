<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appearance;

class AppControllerApi extends Controller
{
   public function getAppColor()
    {
        $appColor = Appearance::select('primary_color', 'secondary_color', 'logo')
            ->where('type', 'app')
            ->first();

        if ($appColor && $appColor->logo) {
            $appColor->logo = asset('storage/' . $appColor->logo);
            // OR if using storage disk
            // $appColor->logo = Storage::url($appColor->logo);
        }

        return response()->json([
            'app_color' => $appColor,
        ]);
    }

    public function getLocations()
    {
        $locations = \App\Models\Location::select('id', 'name', 'city', 'state')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Locations fetched successfully',
            'data' => $locations
        ]);
    }
}
