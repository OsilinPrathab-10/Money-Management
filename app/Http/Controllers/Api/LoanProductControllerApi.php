<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanProduct;
use App\Http\Resources\LoanProductResource;

class LoanProductControllerApi extends Controller
{
    public function index(Request $request)
    {
        $query = LoanProduct::with('loanType')
            ->where(function ($q) {
                // Support both enum ('active') and legacy boolean/integer status columns
                $q->where('status', 'active')->orWhere('status', 1);
            });

        if ($request->filled('loan_type_id')) {
            $query->where('loan_type_id', $request->input('loan_type_id'));
        }

        if ($request->filled('loan_code')) {
            $query->where('loan_code', $request->input('loan_code'));
        }

        $loans = $query->orderBy('loan_name')->get();

        $loanModes = [
            ['value' => 'emi', 'label' => 'EMI', 'name' => 'Standard EMI', 'description' => 'Standard EMI (Principal + Interest)'],
            ['value' => 'interest_only', 'label' => 'Open Loan', 'name' => 'Open Loan', 'description' => 'Open Loan / Kandhuvatti (Monthly Interest Only)'],
        ];

        return response()->json([
            'status' => true,
            'message' => 'Loan products fetched successfully.',
            'data' => LoanProductResource::collection($loans),
            'loan_modes' => $loanModes,
        ]);
    }

    /**
     * Get single loan product (full detail)
     */
    public function show($id)
    {
        $loan = LoanProduct::with('loanType')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Loan product details fetched successfully.',
            'data' => new LoanProductResource($loan),
        ]);
    }
}
