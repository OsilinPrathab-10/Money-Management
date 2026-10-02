<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanProduct;
use App\Models\LoanType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LoanProductsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:loan-product.create')->only(['store']);
        $this->middleware('permission:loan-product.update')->only(['update', 'toggleStatus']);
        $this->middleware('permission:loan-product.delete')->only(['destroy']);
    }

    public function index()
    {
        $loanTypes = LoanType::where('status', 1)->get();
        return view('admin.loan-management.loan-products', compact('loanTypes'));
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loanName' => 'required|string|max:255',
            'loanType' => 'required|exists:loan_types,id',
            'loanCode' => 'nullable|string|max:50|unique:loan_products,loan_code',
            'loanAmountMin' => 'required|numeric|min:0',
            'loanAmountMax' => 'required|numeric|gte:loanAmountMin',
            'interestRate' => 'required|numeric|min:0|max:100',
            'interestType' => 'required|string|in:flat,reducing,fixed',
            'termUnit' => 'required|string|in:days,weeks,months',
            'minTenure' => 'required|integer|min:1',
            'maxTenure' => 'required|integer|gte:minTenure',
            'processingFee' => 'nullable|numeric|min:0',
            'documentCharges' => 'nullable|numeric|min:0',
            'otherCharges' => 'nullable|numeric|min:0',
            'bankingCharges' => 'nullable|numeric|min:0',
            'penaltyRate' => 'nullable|numeric|min:0',
            'gracePeriod' => 'nullable|integer|min:0',
            'requireCollateral' => 'required|in:0,1',
            'defaultTerm' => 'nullable|integer|min:0',
            'description' => 'required|string|min:5',
        ]);

        DB::beginTransaction();
        try {
            $loan = new LoanProduct();
            $loan->loan_name = $validated['loanName'];
            $loan->loan_type_id = $validated['loanType'];

            if ($request->filled('loanCode')) {
                $loan->loan_code = $request->input('loanCode');     
            } else {
                $loan->loan_code = $this->generateLoanCode();
            }
            $loan->loan_amount_min = $validated['loanAmountMin'];
            $loan->loan_amount_max = $validated['loanAmountMax'];
            $loan->interest_rate = $validated['interestRate'];
            $loan->interest_type = $validated['interestType'];
            $loan->term_unit = $validated['termUnit'];
            $loan->min_tenture = $validated['minTenure'];
            $loan->max_tenture = $validated['maxTenure'];
            $loan->processing_fee = $request->filled('processingFee') ? $request->input('processingFee') : null;
            $loan->document_charges = $request->filled('documentCharges') ? $request->input('documentCharges') : null;
            $loan->other_charges = $request->filled('otherCharges') ? $request->input('otherCharges') : null;
            $loan->banking_charges = $request->filled('bankingCharges') ? $request->input('bankingCharges') : null;
            $loan->penalty_rate = $request->filled('penaltyRate') ? $request->input('penaltyRate') : 0;
            $loan->grace_period_days = $request->filled('gracePeriod') ? $request->input('gracePeriod') : 0;
            $loan->require_collateral = (bool) $request->input('requireCollateral', 0);
            $loan->default_term = $request->filled('defaultTerm') ? $request->input('defaultTerm') : null;
            $loan->description = $validated['description'];
            // Ensure newly created products are visible in user app by default
            $loan->status = 'active';
            $loan->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Loan product created successfully.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Loan product creation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create loan product. Please try again.'
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $realId = \App\Support\HashId::decode($id) ?? $id;
        $loanProduct = LoanProduct::findOrFail($realId);

        $validated = $request->validate([
            'loanName' => 'required|string|max:255',
            'loanType' => 'required|exists:loan_types,id',
            'loanCode' => 'nullable|string|max:50|unique:loan_products,loan_code,' . $loanProduct->id,
            'loanAmountMin' => 'required|numeric|min:0',
            'loanAmountMax' => 'required|numeric|gte:loanAmountMin',
            'interestRate' => 'required|numeric|min:0|max:100',
            'interestType' => 'required|string|in:flat,reducing,fixed',
            'termUnit' => 'required|string|in:days,weeks,months',
            'minTenure' => 'required|integer|min:1',
            'maxTenure' => 'required|integer|gte:minTenure',
            'processingFee' => 'nullable|numeric|min:0',
            'documentCharges' => 'nullable|numeric|min:0',
            'otherCharges' => 'nullable|numeric|min:0',
            'bankingCharges' => 'nullable|numeric|min:0',
            'penaltyRate' => 'nullable|numeric|min:0',
            'gracePeriod' => 'nullable|integer|min:0',
            'requireCollateral' => 'nullable|in:0,1',
            'defaultTerm' => 'nullable|integer|min:0',
            'description' => 'required|string|min:5',
        ]);

        try {
            DB::beginTransaction();

            $oldLoanCode = $loanProduct->loan_code;
            $newLoanCode = $validated['loanCode'] ?? $loanProduct->loan_code;

            $loanProduct->loan_name = $validated['loanName'];
            $loanProduct->loan_type_id = $validated['loanType'];
            $loanProduct->loan_code = $newLoanCode;
            $loanProduct->loan_amount_min = $validated['loanAmountMin'];
            $loanProduct->loan_amount_max = $validated['loanAmountMax'];
            $loanProduct->interest_rate = $validated['interestRate'];
            $loanProduct->interest_type = $validated['interestType'];
            $loanProduct->term_unit = $validated['termUnit'];
            $loanProduct->min_tenture = $validated['minTenure'];
            $loanProduct->max_tenture = $validated['maxTenure'];
            $loanProduct->processing_fee = $request->filled('processingFee') ? $request->input('processingFee') : null;
            $loanProduct->document_charges = $request->filled('documentCharges') ? $request->input('documentCharges') : null;
            $loanProduct->other_charges = $request->filled('otherCharges') ? $request->input('otherCharges') : null;
            $loanProduct->banking_charges = $request->filled('bankingCharges') ? $request->input('bankingCharges') : null;
            $loanProduct->penalty_rate = $request->filled('penaltyRate') ? $request->input('penaltyRate') : 0;
            $loanProduct->grace_period_days = $request->filled('gracePeriod') ? $request->input('gracePeriod') : 0;
            if ($request->has('requireCollateral')) {
                $loanProduct->require_collateral = (bool) $request->input('requireCollateral');
            }
            $loanProduct->default_term = $request->filled('defaultTerm') ? $request->input('defaultTerm') : null;
            $loanProduct->description = $validated['description'];
            $loanProduct->save();

            if ($oldLoanCode !== $newLoanCode) {
                DB::table('loan_accounts')
                    ->where('loan_code', $oldLoanCode)
                    ->update(['loan_code' => $newLoanCode]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan product updated successfully.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Loan product update failed', [
                'loan_product_id' => $loanProduct->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update loan product. Please try again.'
            ], 500);
        }
    }

    public function view($id)
    {
        $realId = \App\Support\HashId::decode($id) ?? $id;
        $loanProduct = LoanProduct::with('loanType')->findOrFail($realId);
        $loanTypes = LoanType::where('status', 1)->orderBy('name')->get();

        return view('admin.loan-management.loan-product-view', compact('loanProduct', 'loanTypes'));
    }

    public function getData(Request $request)
    {
        $columns = [
            0 => 'id',
            1 => 'loan_code',
            2 => 'loan_name',
            3 => 'loan_type_id',
            4 => 'status',
            5 => 'id',
        ];

        // Total records without filtering
        $totalData = LoanProduct::count();
        $totalFiltered = $totalData;

        // DataTables parameters
        $limit = $request->input('length');
        $start = $request->input('start');
        $columnIndex = $request->input('order.0.column');
        $order = $columns[$columnIndex] ?? 'id';
        $dir = $request->input('order.0.dir') ?? 'desc';

        // Build query
        $query = LoanProduct::with('loanType');

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('loan_products.loan_code', 'LIKE', "%{$search}%")
                    ->orWhere('loan_products.loan_name', 'LIKE', "%{$search}%")
                    ->orWhere('loan_products.status', 'LIKE', "%{$search}%")
                    ->orWhereHas('loanType', function ($tq) use ($search) {
                        $tq->where('name', 'LIKE', "%{$search}%");
                    });
            });

            $totalFiltered = $query->count();
        }

        // Apply ordering
        if ($order === 'loan_type_id') {
            $query->leftJoin('loan_types', 'loan_products.loan_type_id', '=', 'loan_types.id')
                ->select('loan_products.*')
                ->orderBy('loan_types.name', $dir);
        } else {
            $query->orderBy('loan_products.' . $order, $dir);
        }

        // Apply pagination
        $loanProducts = $query->offset($start)
            ->limit($limit)
            ->get();

        $data = $loanProducts->map(function ($product, $index) use ($start) {
            return [
                's_no' => $start + $index + 1,
                'id' => $product->getRouteKey(),
                'loan_code' => $product->loan_code ?? 'N/A',
                'name' => $product->loan_name,
                'loan_type' => $product->loanType->name ?? 'N/A',
                'icon' => $product->loan_type_icon_url,
                'status' => ucfirst($product->status),
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    public function destroy($id)
    {
        try {
            $realId = \App\Support\HashId::decode((string)$id);
            
            // If decoding fails, check if it's a numeric ID
            if ($realId === null && ctype_digit((string)$id)) {
                $realId = (int)$id;
            }

            if ($realId === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid loan product ID'
                ], 400);
            }

            $loan = LoanProduct::findOrFail($realId);
            $deleted = $loan->delete();

            if (!$deleted) {
                throw new \Exception("Model delete() returned false");
            }

            return response()->json([
                'success' => true,
                'message' => 'Loan product deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Loan product deletion failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $realId = \App\Support\HashId::decode($id) ?? $id;
            $loan = LoanProduct::findOrFail($realId);
            $raw = $request->input('status');
            $normalized = in_array($raw, ['active', 1, '1', true, 'true'], true) ? 'active' : 'inactive';
            $loan->status = $normalized;
            $loan->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'status' => $loan->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    // Generate unique loan code
    private function generateLoanCode()
    {
        // Get the last loan product including soft-deleted ones
        $lastLoan = LoanProduct::withTrashed()->orderBy('id', 'desc')->first();

        if ($lastLoan && $lastLoan->loan_code) {
            // Extract number from last code (e.g., LP001 -> 001)
            preg_match('/\d+/', $lastLoan->loan_code, $matches);
            if (!empty($matches)) {
                $lastNumber = intval($matches[0]);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
        } else {
            $newNumber = 1;
        }

        // Generate new code with format LP001, LP002, etc.
        $loanCode = 'LP' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        // Check if code already exists (safety check including soft-deleted records)
        while (LoanProduct::withTrashed()->where('loan_code', $loanCode)->exists()) {
            $newNumber++;
            $loanCode = 'LP' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        }

        return $loanCode;
    }
}
