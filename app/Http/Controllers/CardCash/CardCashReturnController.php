<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashReturn;
use App\Services\CardCash\CardCashReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardCashReturnController extends Controller
{
    public function __construct(
        protected CardCashReturnService $returnService
    ) {}

    public function index(Request $request): View
    {
        $query = CardCashReturn::with(['lead.customer', 'customer', 'processor', 'wallet']);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_reference', 'LIKE', "%{$search}%")
                  ->orWhere('upi_id', 'LIKE', "%{$search}%")
                  ->orWhere('account_holder_name', 'LIKE', "%{$search}%")
                  ->orWhere('account_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('lead', function ($lq) use ($search) {
                      $lq->where('lead_number', 'LIKE', "%{$search}%")
                         ->orWhere('card_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('customer_name', 'LIKE', "%{$search}%")
                         ->orWhere('phone_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($method = $request->input('return_method')) {
            $query->where('return_method', $method);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('processed_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('processed_at', '<=', $toDate);
        }

        $returns = $query->latest('id')->paginate((int) $request->input('per_page', 15));

        return view('admin.card-to-cash.returns.index', compact('returns'));
    }

    public function calculatePreview(Request $request): JsonResponse
    {
        $amount = (float) $request->input('amount', 0);
        $method = $request->input('return_method', 'card');
        $percentage = $request->has('return_percentage') ? (float) $request->input('return_percentage') : null;
        $isSplit = (bool) $request->input('is_split', false) || $method === 'split';
        $cardAmt = $request->has('card_amount') ? (float) $request->input('card_amount') : null;
        $cashAmt = $request->has('cash_amount') ? (float) $request->input('cash_amount') : null;

        $calc = $this->returnService->calculateReturn(
            $amount,
            $method,
            $percentage,
            $isSplit,
            null,
            null,
            $cardAmt,
            $cashAmt
        );

        return response()->json([
            'status' => true,
            'data' => $calc,
        ]);
    }

    public function processReturn(Request $request, int $leadId): RedirectResponse
    {
        $validated = $request->validate([
            'return_method' => 'required|in:card,wallet,upi,imps,other,split',
            'wallet_id' => 'nullable|integer',
            'is_split_wallet' => 'nullable|boolean',
            'split_wallets' => 'nullable|array',
            'is_split' => 'nullable|boolean',
            'card_amount' => 'required_if:return_method,split|nullable|numeric|min:0',
            'cash_amount' => 'required_if:return_method,split|nullable|numeric|min:0',
            'return_percentage' => 'nullable|numeric|min:1|max:100',
            'gross_amount' => 'required|numeric|min:1',
            'return_amount' => 'nullable|numeric|min:1',
            'charges' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:100',
            'upi_id' => 'required_if:return_method,upi|nullable|string|max:100',
            'bank_name' => 'required_if:return_method,imps|nullable|string|max:100',
            'account_holder_name' => 'required_if:return_method,imps|nullable|string|max:100',
            'account_number' => 'required_if:return_method,imps|nullable|string|max:50',
            'ifsc_code' => 'required_if:return_method,imps|nullable|string|max:20',
            'payment_proof' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $lead = CardCashLead::findOrFail($leadId);

        try {
            $proofFile = $request->file('payment_proof');
            $return = $this->returnService->processReturn($lead, $validated, $proofFile, auth()->id());

            return redirect()->route('card-cash.leads.show', $lead->id)
                ->with('success', "Return payment of ₹" . number_format($return->return_amount, 2) . " settled successfully! Lead #{$lead->lead_number} is now Completed.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
