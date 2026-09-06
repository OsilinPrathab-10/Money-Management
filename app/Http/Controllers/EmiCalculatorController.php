<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\EmiCalculator;

class EmiCalculatorController extends Controller
{
    protected $emiCalculator;

    public function __construct(EmiCalculator $emiCalculator)
    {
        $this->emiCalculator = $emiCalculator;
    }

    /**
     * Display the EMI calculator page
     */
    public function index()
    {
        return view('admin.emi-repayments.emi-calculator.emi-calculator');
    }

    /**
     * Calculate EMI based on input parameters
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'principal' => 'required|numeric|min:1000',
            'annual_rate' => 'required|numeric|min:0.1|max:100',
            'term_months' => 'required|integer|min:1|max:360',
            'start_date' => 'nullable|date',
            'interest_type' => 'nullable|string|in:flat,reducing,fixed'
        ]);

        $principal = $request->input('principal');
        $annualRate = $request->input('annual_rate');
        $termMonths = $request->input('term_months');
        $frequency = $request->input('frequency', 'monthly');
        $startDate = $request->input('start_date');
        $interestType = $request->input('interest_type', 'flat');

        $result = $this->emiCalculator->generateSchedule(
            $principal,
            $annualRate,
            $termMonths,
            $startDate,
            null,
            $frequency,
            $interestType
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
