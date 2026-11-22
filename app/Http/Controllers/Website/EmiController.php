<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Services\EmiCalculator;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmiController extends Controller
{
    public function show(WebsiteService $website): View
    {
        return view('website.emi-calculator', ['brand' => $website->brand()]);
    }

    public function calculate(Request $request, EmiCalculator $calculator)
    {
        $data = $request->validate([
            'principal' => 'required|numeric|min:1000',
            'annual_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1|max:360',
        ]);

        $result = $calculator->generateSchedule(
            (float) $data['principal'],
            (float) $data['annual_rate'],
            (int) $data['term_months'],
            null,
            null,
            'monthly',
            'reducing'
        );

        return response()->json(['success' => true, 'data' => $result]);
    }
}
