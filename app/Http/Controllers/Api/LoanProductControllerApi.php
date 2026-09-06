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
        $loans = LoanProduct::with('loanType')
            ->where(function ($q) {
                // Support both enum ('active') and legacy boolean/integer status columns
                $q->where('status', 'active')->orWhere('status', 1);
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Loan products fetched successfully.',
            'data' => LoanProductResource::collection($loans),
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
