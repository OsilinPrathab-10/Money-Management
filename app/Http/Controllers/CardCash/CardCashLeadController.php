<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CreditCardCustomer;
use App\Models\CardCash\CustomerCreditCard;
use App\Models\User;
use App\Services\CardCash\CardCashLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardCashLeadController extends Controller
{
    public function __construct(
        protected CardCashLeadService $leadService
    ) {}

    public function index(Request $request): View
    {
        $query = CardCashLead::with(['customer', 'assignedStaff', 'billPayment', 'swipeTransaction', 'returnSettlement']);

        // Search by lead number, customer name, phone, card name
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('lead_number', 'LIKE', "%{$search}%")
                  ->orWhere('card_name', 'LIKE', "%{$search}%")
                  ->orWhere('csr_bank_name', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('customer_name', 'LIKE', "%{$search}%")
                         ->orWhere('customer_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Filter by Transaction Type
        if ($type = $request->input('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        // Filter by Status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by Staff
        if ($userId = $request->input('assigned_user_id')) {
            $query->where('assigned_user_id', $userId);
        }

        // Filter by Date Range
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('lead_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('lead_date', '<=', $toDate);
        }

        // withQueryString keeps the active filters (and view mode) on page 2 onwards.
        $leads = $query->latest('id')
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();
        $staffUsers = User::orderBy('name')->get();
        $selectedCustomerId = $request->input('customer_id');
        $selectedCustomer = $selectedCustomerId ? CreditCardCustomer::with('cards')->find($selectedCustomerId) : null;
        $customers = CreditCardCustomer::with('cards')->latest('id')->take(100)->get();

        return view('admin.card-to-cash.leads.index', compact('leads', 'staffUsers', 'customers', 'selectedCustomer'));
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('card-cash.leads.index', array_merge($request->all(), ['open_modal' => 'add_lead']));
    }

    /**
     * Return JSON of saved cards for a customer (for dynamic UI selector)
     */
    public function customerCards(int $customerId): JsonResponse
    {
        $cards = CustomerCreditCard::where('customer_id', $customerId)
            ->where('status', 'active')
            ->latest('id')
            ->get();

        $formatted = $cards->map(function ($card) {
            return [
                'id' => $card->id,
                'card_name' => $card->card_name,
                'card_number' => $card->last_four,
                'masked_card_number' => $card->masked_card_number,
                'csr_bank_name' => $card->csr_bank_name,
                'card_holder_phone' => $card->card_holder_phone,
                'card_network' => $card->card_network,
                'display_label' => $card->display_label,
            ];
        });

        return response()->json([
            'status' => true,
            'cards' => $formatted,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // If an existing customer card is selected, merge card details if not filled
        if ($request->filled('customer_card_id')) {
            $savedCard = CustomerCreditCard::find($request->input('customer_card_id'));
            if ($savedCard) {
                if (!$request->filled('card_name')) {
                    $request->merge(['card_name' => $savedCard->card_name]);
                }
                if (!$request->filled('card_number')) {
                    $request->merge(['card_number' => $savedCard->last_four]);
                }
                if (!$request->filled('csr_bank_name')) {
                    $request->merge(['csr_bank_name' => $savedCard->csr_bank_name]);
                }
                if (!$request->filled('card_holder_phone') && $savedCard->card_holder_phone) {
                    $request->merge(['card_holder_phone' => $savedCard->card_holder_phone]);
                }
            }
        }

        // Clean phone number (strip non-digits, strip leading country code if 12 digits starting with 91)
        if ($request->filled('phone_number')) {
            $cleanedPhone = preg_replace('/\D/', '', (string) $request->input('phone_number'));
            if (strlen($cleanedPhone) === 12 && str_starts_with($cleanedPhone, '91')) {
                $cleanedPhone = substr($cleanedPhone, 2);
            }
            $request->merge(['phone_number' => $cleanedPhone]);
        }

        // Clean cardholder phone (strip non-digits, strip leading country code if 12 digits starting with 91)
        if ($request->filled('card_holder_phone')) {
            $cleanedCardHolderPhone = preg_replace('/\D/', '', (string) $request->input('card_holder_phone'));
            if (strlen($cleanedCardHolderPhone) === 12 && str_starts_with($cleanedCardHolderPhone, '91')) {
                $cleanedCardHolderPhone = substr($cleanedCardHolderPhone, 2);
            }
            $request->merge(['card_holder_phone' => $cleanedCardHolderPhone]);
        }

        // Capture last 4 digits only (full PAN is not stored)
        if ($request->filled('card_number')) {
            $lastFour = CustomerCreditCard::lastFourDigits($request->input('card_number'));
            $request->merge(['card_number' => $lastFour ?? preg_replace('/\D/', '', (string) $request->input('card_number'))]);
        }

        // Clean and format requested amount (strip commas, ensure exact 2 decimals)
        if ($request->filled('requested_amount')) {
            $rawAmount = str_replace([',', ' '], '', (string) $request->input('requested_amount'));
            $request->merge(['requested_amount' => round((float) $rawAmount, 2)]);
        }

        if (!$request->filled('due_date')) {
            $request->merge(['due_date' => null]);
        }

        $validated = $request->validate([
            'credit_card_customer_id' => 'nullable|exists:credit_card_customers,id',
            'customer_card_id' => 'nullable|exists:credit_card_customer_cards,id',
            'customer_name' => 'required_without:credit_card_customer_id|nullable|string|max:255',
            'phone_number' => 'required_without:credit_card_customer_id|nullable|string|regex:/^[0-9]{10}$/',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'card_name' => 'required|string|max:255',
            'card_number' => 'required|string|regex:/^[0-9]{4}$/',
            'csr_bank_name' => 'required|string|max:255',
            'card_holder_phone' => 'nullable|string|regex:/^[0-9]{10}$/',
            'transaction_type' => 'required|in:bill_payment,swipe',
            'requested_amount' => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'phone_number.regex' => 'The mobile number must be exactly 10 digits (numbers only).',
            'card_number.regex' => 'Enter the last 4 digits of the card number.',
            'card_holder_phone.regex' => 'The cardholder mobile number must be exactly 10 digits (numbers only).',
        ]);

        $validated['requested_amount'] = number_format((float) $validated['requested_amount'], 2, '.', '');

        $lead = $this->leadService->createLead($validated, auth()->id());

        return redirect()->route('card-cash.processing.process', $lead->id)
            ->with('success', "Lead #{$lead->lead_number} created successfully! You can now proceed to process.");
    }

    public function show(Request $request, int $id)
    {
        $lead = CardCashLead::with([
            'customer.cards',
            'customerCard',
            'assignedStaff',
            'billPayment.paymentSource',
            'swipeTransaction.gateway',
            'swipeTransaction.wallet',
            'returnSettlement.wallet',
            'activityLogs.user',
            'creator',
        ])->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => true,
                'lead' => $lead,
                'customer' => $lead->customer,
                'customer_card' => $lead->customerCard,
                'bill_payment' => $lead->billPayment,
                'swipe_transaction' => $lead->swipeTransaction,
                'return_settlement' => $lead->returnSettlement,
                'assigned_staff' => $lead->assignedStaff,
                'activity_logs' => $lead->activityLogs,
                'settlement_display' => $lead->settlementDisplay(),
            ]);
        }

        return redirect()->route('card-cash.leads.index', ['view_lead' => $id]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $lead = CardCashLead::findOrFail($id);

        if ($request->filled('phone_number')) {
            $cleanedPhone = preg_replace('/\D/', '', (string) $request->input('phone_number'));
            if (strlen($cleanedPhone) === 12 && str_starts_with($cleanedPhone, '91')) {
                $cleanedPhone = substr($cleanedPhone, 2);
            }
            $request->merge(['phone_number' => $cleanedPhone]);
        }

        if ($request->has('card_holder_phone')) {
            $cleanedCardHolderPhone = $request->filled('card_holder_phone')
                ? preg_replace('/\D/', '', (string) $request->input('card_holder_phone'))
                : null;
            if ($cleanedCardHolderPhone && strlen($cleanedCardHolderPhone) === 12 && str_starts_with($cleanedCardHolderPhone, '91')) {
                $cleanedCardHolderPhone = substr($cleanedCardHolderPhone, 2);
            }
            $request->merge(['card_holder_phone' => $cleanedCardHolderPhone]);
        }

        if ($request->filled('card_number')) {
            $lastFour = CustomerCreditCard::lastFourDigits($request->input('card_number'));
            $request->merge(['card_number' => $lastFour ?? preg_replace('/\D/', '', (string) $request->input('card_number'))]);
        }

        if ($request->filled('requested_amount')) {
            $rawAmount = str_replace([',', ' '], '', (string) $request->input('requested_amount'));
            $request->merge(['requested_amount' => round((float) $rawAmount, 2)]);
        }

        if ($request->has('due_date') && !$request->filled('due_date')) {
            $request->merge(['due_date' => null]);
        }

        $validated = $request->validate([
            'card_name' => 'sometimes|required|string|max:255',
            'card_number' => 'sometimes|required|string|regex:/^[0-9]{4}$/',
            'csr_bank_name' => 'sometimes|required|string|max:255',
            'card_holder_phone' => 'nullable|string|regex:/^[0-9]{10}$/',
            'transaction_type' => 'sometimes|required|in:bill_payment,swipe',
            'requested_amount' => 'sometimes|required|numeric|min:1',
            'due_date' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'card_number.regex' => 'Enter the last 4 digits of the card number.',
            'card_holder_phone.regex' => 'The cardholder mobile number must be exactly 10 digits (numbers only).',
        ]);

        $oldAmount = (float) $lead->requested_amount;
        $newAmount = isset($validated['requested_amount']) ? round((float) $validated['requested_amount'], 2) : $oldAmount;

        $lead->fill($validated);
        $lead->requested_amount = number_format($newAmount, 2, '.', '');
        $lead->updated_by = auth()->id();
        $lead->save();

        if (round($oldAmount, 2) !== round($newAmount, 2)) {
            $this->leadService->logActivity(
                leadId: $lead->id,
                action: 'lead_updated',
                description: "Lead requested amount updated from ₹" . number_format($oldAmount, 2) . " to ₹" . number_format($newAmount, 2),
                oldStatus: $lead->status,
                newStatus: $lead->status,
                userId: auth()->id(),
                metadata: ['old_amount' => $oldAmount, 'new_amount' => $newAmount]
            );
        }

        return back()->with('success', "Lead #{$lead->lead_number} updated successfully! Requested amount: ₹" . number_format($lead->requested_amount, 2));
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $request->validate(['assigned_user_id' => 'required|exists:users,id']);

        $lead = CardCashLead::findOrFail($id);
        $this->leadService->assignLead($lead, (int) $request->input('assigned_user_id'), auth()->id());

        return back()->with('success', "Lead #{$lead->lead_number} assigned successfully.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $lead = CardCashLead::findOrFail($id);
        $leadNumber = $lead->lead_number;
        $lead->delete();

        return redirect()->route('card-cash.leads.index')
            ->with('success', "Lead #{$leadNumber} deleted successfully.");
    }
}
