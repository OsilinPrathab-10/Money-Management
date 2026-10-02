<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashBillPayment;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashPaymentSource;
use App\Services\CardCash\CardCashBillPaymentService;
use App\Services\CardCash\CardCashNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardCashBillPaymentController extends Controller
{
    public function __construct(
        protected CardCashBillPaymentService $billPaymentService,
        protected CardCashNotificationService $notificationService
    ) {}

    public function index(Request $request): View
    {
        $query = CardCashBillPayment::with(['lead', 'customer', 'paymentSource', 'wallet', 'processor']);

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

        if ($sourceId = $request->input('payment_source_id')) {
            $query->where('payment_source_id', $sourceId);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        $payments = $query->latest('id')->paginate((int) $request->input('per_page', 15));
        $paymentSources = CardCashPaymentSource::all();

        return view('admin.card-to-cash.bill-payments.index', compact('payments', 'paymentSources'));
    }

    public function processPayment(Request $request, int $leadId): RedirectResponse
    {
        // Gracefully handle external gateway prefix if passed in wallet_id
        if ($request->filled('wallet_id') && str_starts_with($request->input('wallet_id'), 'ext_')) {
            $request->merge([
                'payment_source_id' => (int) str_replace('ext_', '', $request->input('wallet_id')),
                'wallet_id' => null,
            ]);
        }

        $validated = $request->validate([
            'payment_source_id' => 'nullable|exists:card_cash_payment_sources,id',
            'wallet_id' => 'nullable|exists:credit_card_wallets,id',
            'is_split_wallet' => 'nullable|boolean',
            'split_wallets' => 'nullable|array',
            'split_wallets.*.wallet_id' => 'nullable|exists:credit_card_wallets,id',
            'split_wallets.*.amount' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|min:1',
            'transaction_reference' => 'nullable|string|max:100',
            'gateway_reference' => 'nullable|string|max:100',
            'payment_date' => 'nullable|date',
            'screenshot_proof' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'remarks' => 'nullable|string|max:1000',
        ]);

        // Ensure at least one debit wallet or payment source is selected
        $isSplit = !empty($validated['is_split_wallet']);
        if (!$isSplit && empty($validated['wallet_id']) && empty($validated['payment_source_id'])) {
            return back()->withInput()->with('error', 'Please select a Debit Wallet or Payment Source.');
        }

        $lead = CardCashLead::findOrFail($leadId);

        try {
            $proofFile = $request->file('screenshot_proof');
            $payment = $this->billPaymentService->processBillPayment($lead, $validated, $proofFile, auth()->id());

            // Build WhatsApp info for feedback
            $whatsApp = $this->notificationService->notifyLead($lead, auth()->id());

            return redirect()->route('card-cash.processing.process', $lead->id)
                ->with('success', "Bill Payment of ₹" . number_format($payment->amount, 2) . " processed successfully! Awaiting customer return settlement.")
                ->with('whatsapp_link', $whatsApp['whatsapp_link']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function uploadProof(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'screenshot_proof' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ]);

        $payment = CardCashBillPayment::findOrFail($id);
        $this->billPaymentService->uploadProof($payment, $request->file('screenshot_proof'), auth()->id());

        return back()->with('success', 'Payment proof screenshot uploaded successfully.');
    }

    public function sendWhatsApp(Request $request, int $leadId): RedirectResponse
    {
        $lead = CardCashLead::with(['customer', 'billPayment', 'returnSettlement'])->findOrFail($leadId);
        $res = $this->notificationService->notifyLead($lead, auth()->id());

        return redirect()->away($res['whatsapp_link']);
    }
}
