<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CreditCardCustomer;
use App\Models\CardCash\CustomerCreditCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCardCustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = CreditCardCustomer::withCount('leads')
            ->with(['cards', 'leads.returnSettlement', 'leads.billPayment', 'leads.swipeTransaction']);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_number', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $customers = $query->latest('id')->paginate((int) $request->input('per_page', 15));

        return view('admin.card-to-cash.customers.index', compact('customers'));
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('card-cash.customers.index', ['open_modal' => 'add_customer']);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('phone_number')) {
            $cleaned = preg_replace('/\D/', '', (string) $request->input('phone_number'));
            if (strlen($cleaned) === 12 && str_starts_with($cleaned, '91')) {
                $cleaned = substr($cleaned, 2);
            }
            $request->merge(['phone_number' => $cleaned]);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone_number' => 'required|string|regex:/^[0-9]{10}$/|unique:credit_card_customers,phone_number',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'phone_number.regex' => 'The mobile number must be exactly 10 digits (numbers only).',
        ]);

        $validated['customer_number'] = CreditCardCustomer::generateCustomerNumber();
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $customer = CreditCardCustomer::create($validated);

        return redirect()->route('card-cash.customers.index')
            ->with('success', "Credit card customer {$customer->customer_name} created successfully.");
    }

    public function show(Request $request, int $id)
    {
        $customer = CreditCardCustomer::with([
            'cards',
            'leads.billPayment',
            'leads.swipeTransaction',
            'leads.returnSettlement',
            'creator',
        ])->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => true,
                'customer' => $customer,
                'cards' => $customer->cards,
                'leads' => $customer->leads,
            ]);
        }

        return redirect()->route('card-cash.customers.index', ['view_customer' => $id]);
    }

    public function storeCard(Request $request, int $id): RedirectResponse
    {
        $customer = CreditCardCustomer::findOrFail($id);

        if ($request->filled('card_number')) {
            $lastFour = CustomerCreditCard::lastFourDigits($request->input('card_number'));
            $request->merge(['card_number' => $lastFour ?? preg_replace('/\D/', '', (string) $request->input('card_number'))]);
        }

        if ($request->filled('card_holder_phone')) {
            $cleanedPhone = preg_replace('/\D/', '', (string) $request->input('card_holder_phone'));
            if (strlen($cleanedPhone) === 12 && str_starts_with($cleanedPhone, '91')) {
                $cleanedPhone = substr($cleanedPhone, 2);
            }
            $request->merge(['card_holder_phone' => $cleanedPhone]);
        }

        $validated = $request->validate([
            'card_name' => 'required|string|max:255',
            'card_number' => 'required|string|regex:/^[0-9]{4}$/',
            'csr_bank_name' => 'required|string|max:255',
            'card_holder_phone' => 'nullable|string|regex:/^[0-9]{10}$/',
            'card_network' => 'nullable|string|max:50',
            'card_type' => 'nullable|string|max:50',
        ], [
            'card_number.regex' => 'Enter the last 4 digits of the card number.',
            'card_holder_phone.regex' => 'The cardholder mobile number must be exactly 10 digits (numbers only).',
        ]);

        $card = CustomerCreditCard::findForCustomerByLastFour($customer->id, $validated['card_number']);
        if (!$card) {
            $card = CustomerCreditCard::create([
                'customer_id' => $customer->id,
                'card_number' => $validated['card_number'],
                'card_name' => $validated['card_name'],
                'csr_bank_name' => $validated['csr_bank_name'],
                'card_holder_phone' => $validated['card_holder_phone'] ?? null,
                'card_network' => $validated['card_network'] ?? null,
                'card_type' => $validated['card_type'] ?? 'credit',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }

        return redirect()->route('card-cash.customers.index', ['view_customer' => $customer->id])
            ->with('success', "Card {$card->card_name} ({$card->masked_card_number}) saved for customer {$customer->customer_name}.");
    }

    public function edit(Request $request, int $id)
    {
        $customer = CreditCardCustomer::findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => true,
                'customer' => $customer,
            ]);
        }

        return redirect()->route('card-cash.customers.index', ['edit_customer' => $id]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $customer = CreditCardCustomer::findOrFail($id);

        if ($request->has('phone_number')) {
            $cleaned = preg_replace('/\D/', '', (string) $request->input('phone_number'));
            if (strlen($cleaned) === 12 && str_starts_with($cleaned, '91')) {
                $cleaned = substr($cleaned, 2);
            }
            $request->merge(['phone_number' => $cleaned]);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone_number' => 'required|string|regex:/^[0-9]{10}$/|unique:credit_card_customers,phone_number,' . $customer->id,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive,blocked',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'phone_number.regex' => 'The mobile number must be exactly 10 digits (numbers only).',
        ]);

        $validated['updated_by'] = auth()->id();
        $customer->update($validated);

        return redirect()->route('card-cash.customers.show', $customer->id)
            ->with('success', "Customer details updated successfully.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $customer = CreditCardCustomer::findOrFail($id);
        $name = $customer->customer_name;
        $customer->delete();

        return redirect()->route('card-cash.customers.index')
            ->with('success', "Customer {$name} deleted successfully.");
    }

    public function searchAjax(Request $request): JsonResponse
    {
        $term = trim($request->input('q', ''));
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $customers = CreditCardCustomer::where('phone_number', 'LIKE', "%{$term}%")
            ->orWhere('customer_name', 'LIKE', "%{$term}%")
            ->orWhere('customer_number', 'LIKE', "%{$term}%")
            ->take(10)
            ->get(['id', 'customer_number', 'customer_name', 'phone_number', 'email', 'address']);

        return response()->json($customers);
    }
}
