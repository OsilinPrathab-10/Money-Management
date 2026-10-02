<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashSwipeTransaction;
use App\Models\CardCash\CardCashWithdrawalGateway;
use App\Models\CardCash\CreditCardWallet;
use App\Services\CardCash\CardCashSwipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CardCashSwipeController extends Controller
{
    public function __construct(
        protected CardCashSwipeService $swipeService
    ) {}

    public function index(Request $request): View
    {
        $query = CardCashSwipeTransaction::with(['lead', 'customer', 'gateway', 'wallet', 'processor']);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_reference', 'LIKE', "%{$search}%")
                  ->orWhere('gateway_reference', 'LIKE', "%{$search}%")
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

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($gatewayId = $request->input('withdrawal_gateway_id')) {
            $query->where('withdrawal_gateway_id', $gatewayId);
        }

        if ($walletId = $request->input('wallet_id')) {
            $query->where('wallet_id', $walletId);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('processed_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('processed_at', '<=', $toDate);
        }

        $swipes = $query->latest('id')->paginate((int) $request->input('per_page', 15));
        $gateways = CardCashWithdrawalGateway::all();
        $wallets = CreditCardWallet::all();

        return view('admin.card-to-cash.swipes.index', compact('swipes', 'gateways', 'wallets'));
    }

    public function processSwipe(Request $request, int $leadId): RedirectResponse
    {
        $validated = $request->validate([
            'withdrawal_gateway_id' => 'required|exists:card_cash_withdrawal_gateways,id',
            'wallet_id' => 'required_without:is_split_wallet|nullable',
            'is_split_wallet' => 'nullable|boolean',
            'split_wallets' => 'nullable|array',
            'swipe_amount' => 'required|numeric|min:1',
            'charges' => 'nullable|numeric|min:0',
            'charges_percentage' => 'nullable|numeric|min:0|max:100',
            'transaction_reference' => 'nullable|string|max:100',
            'gateway_reference' => 'nullable|string|max:100',
            'screenshot_proof' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $lead = CardCashLead::findOrFail($leadId);

        try {
            $proofFile = $request->file('screenshot_proof');
            $swipe = $this->swipeService->processSwipe($lead, $validated, $proofFile, auth()->id());

            return redirect()->route('card-cash.processing.process', $lead->id)
                ->with('success', "Swipe of ₹" . number_format($swipe->swipe_amount, 2) . " processed successfully! Wallet balance debited. Awaiting return settlement.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function uploadProof(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'screenshot_proof' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ]);

        $swipe = CardCashSwipeTransaction::findOrFail($id);

        $directory = 'card-cash/swipes';
        Storage::disk('public')->makeDirectory($directory);

        if ($swipe->screenshot_proof && Storage::disk('public')->exists($swipe->screenshot_proof)) {
            Storage::disk('public')->delete($swipe->screenshot_proof);
        }

        $path = $request->file('screenshot_proof')->store($directory, 'public');
        $swipe->screenshot_proof = $path;
        $swipe->save();

        return back()->with('success', 'Swipe slip screenshot uploaded successfully.');
    }
}
