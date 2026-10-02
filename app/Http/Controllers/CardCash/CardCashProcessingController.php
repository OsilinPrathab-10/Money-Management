<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashPaymentSource;
use App\Models\CardCash\CardCashSetting;
use App\Models\CardCash\CardCashWithdrawalGateway;
use App\Models\CardCash\CreditCardWallet;
use App\Models\User;
use App\Services\CardCash\CardCashLeadService;
use App\Services\CardCash\CardCashNotificationService;
use App\Services\CardCash\CardCashReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardCashProcessingController extends Controller
{
    public function __construct(
        protected CardCashLeadService $leadService,
        protected CardCashReturnService $returnService,
        protected CardCashNotificationService $notificationService
    ) {}

    public function index(Request $request): View
    {
        $query = CardCashLead::with(['customer', 'assignedStaff', 'billPayment', 'swipeTransaction', 'returnSettlement'])
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected']);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('lead_number', 'LIKE', "%{$search}%")
                  ->orWhere('card_name', 'LIKE', "%{$search}%")
                  ->orWhere('csr_bank_name', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('customer_name', 'LIKE', "%{$search}%")
                         ->orWhere('phone_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($type = $request->input('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($userId = $request->input('assigned_user_id')) {
            $query->where('assigned_user_id', $userId);
        }

        $leads = $query->latest('id')->paginate((int) $request->input('per_page', 15));
        $staffUsers = User::orderBy('name')->get();

        return view('admin.card-to-cash.processing.index', compact('leads', 'staffUsers'));
    }

    public function process(int $id): View
    {
        $lead = CardCashLead::with([
            'customer',
            'assignedStaff',
            'billPayment.paymentSource',
            'billPayment.wallet',
            'swipeTransaction.gateway',
            'swipeTransaction.wallet',
            'returnSettlement.wallet',
            'activityLogs.user',
        ])->findOrFail($id);

        // Dynamically auto-synchronize lead status based on actual transaction state
        if ($lead->returnSettlement && $lead->status !== 'completed') {
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'completed',
                description: 'Dynamic status update: Customer return settlement completed.',
                userId: auth()->id()
            );
            $lead->refresh();
        } elseif (($lead->billPayment || $lead->swipeTransaction) && !$lead->returnSettlement && !in_array($lead->status, ['return_pending', 'payment_success', 'completed', 'cancelled', 'rejected', 'failed'], true)) {
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'return_pending',
                description: 'Dynamic status update: Payment/swipe recorded, awaiting customer return settlement.',
                userId: auth()->id()
            );
            $lead->refresh();
        } elseif (!$lead->billPayment && !$lead->swipeTransaction && $lead->status === 'new') {
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'processing',
                description: 'Dynamic status update: Lead opened for processing by ' . (auth()->user()->name ?? 'Staff'),
                userId: auth()->id()
            );
            $lead->refresh();
        }

        // Dropdown sources for Bill Payment (include linked wallet so source can show in debit details)
        $paymentSources = CardCashPaymentSource::with('wallet')->active()->get();

        // Dropdowns for Swipe
        $withdrawalGateways = CardCashWithdrawalGateway::with('company')->active()->get();
        $wallets = CreditCardWallet::active()->get();

        // Return calculation preview
        $cardReturnPercentage = (float) CardCashSetting::get('card_return_percentage', 99.00);
        $returnPreview = $this->returnService->calculateReturn($lead->requested_amount, 'card', $cardReturnPercentage);

        // Pre-generate WhatsApp link
        $whatsAppMessage = $this->notificationService->buildWhatsAppMessage($lead, $lead->billPayment, $lead->returnSettlement);
        $whatsAppLink = $this->notificationService->generateWhatsAppLink($lead->phone_number, $whatsAppMessage);

        $staffUsers = User::orderBy('name')->get();

        return view('admin.card-to-cash.processing.process', compact(
            'lead',
            'paymentSources',
            'withdrawalGateways',
            'wallets',
            'cardReturnPercentage',
            'returnPreview',
            'whatsAppMessage',
            'whatsAppLink',
            'staffUsers'
        ));
    }

    public function changeStatus(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'status' => 'required|string',
            'remarks' => 'nullable|string|max:500',
        ]);

        $lead = CardCashLead::findOrFail($id);

        try {
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: $request->input('status'),
                description: $request->input('remarks') ?: "Status updated to " . $request->input('status'),
                userId: auth()->id()
            );

            return back()->with('success', "Lead status changed to " . $lead->status_label);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
