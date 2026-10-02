<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashBank;
use App\Models\CardCash\CardCashCompany;
use App\Models\CardCash\CardCashPaymentSource;
use App\Models\CardCash\CardCashSetting;
use App\Models\CardCash\CardCashWithdrawalGateway;
use App\Models\CardCash\CreditCardWallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardCashSettingsController extends Controller
{
    public function index(): View
    {
        $settings = CardCashSetting::all()->keyBy('key');
        $gateways = CardCashWithdrawalGateway::with('company')->get();
        $companies = CardCashCompany::withCount('gateways')->orderBy('company_name')->get();
        $banks = CardCashBank::orderBy('bank_name')->get();
        $paymentSources = CardCashPaymentSource::with('wallet')->get();
        $wallets = CreditCardWallet::active()->get();

        return view('admin.card-to-cash.settings.index', compact(
            'settings',
            'gateways',
            'companies',
            'banks',
            'paymentSources',
            'wallets'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'card_return_percentage' => 'required|numeric|min:1|max:100',
            'min_amount' => 'nullable|numeric|min:1',
            'max_amount' => 'nullable|numeric|min:1',
            'upi_return_enabled' => 'nullable|boolean',
            'imps_return_enabled' => 'nullable|boolean',
            'other_return_enabled' => 'nullable|boolean',
            'whatsapp_enabled' => 'nullable|boolean',
        ]);

        CardCashSetting::set('card_return_percentage', $validated['card_return_percentage'], 'Default Card Return %');
        CardCashSetting::set('min_amount', $validated['min_amount'] ?? 1000, 'Minimum amount');
        CardCashSetting::set('max_amount', $validated['max_amount'] ?? 1000000, 'Maximum amount');
        CardCashSetting::set('upi_return_enabled', $request->has('upi_return_enabled') ? '1' : '0', 'UPI return method');
        CardCashSetting::set('imps_return_enabled', $request->has('imps_return_enabled') ? '1' : '0', 'IMPS return method');
        CardCashSetting::set('other_return_enabled', $request->has('other_return_enabled') ? '1' : '0', 'Other return method');
        CardCashSetting::set('whatsapp_enabled', $request->has('whatsapp_enabled') ? '1' : '0', 'WhatsApp notifications');

        return back()->with('success', 'Card to Cash settings saved successfully.');
    }

    // =========================================================================
    // 1. WITHDRAWAL GATEWAYS CRUD
    // =========================================================================
    public function storeWithdrawalGateway(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gateway_name' => 'required|string|max:255',
            'gateway_code' => 'required|string|max:50|unique:card_cash_withdrawal_gateways,gateway_code',
            'company_id' => 'nullable|exists:card_cash_companies,id',
            'debit_percentage' => 'nullable|numeric|min:0|max:100',
            'credit_percentage' => 'nullable|numeric|min:0|max:100',
            'prepaid_percentage' => 'nullable|numeric|min:0|max:100',
            'business_percentage' => 'nullable|numeric|min:0|max:100',
            'wallet_supported' => 'nullable|boolean',
            'remarks' => 'nullable|string|max:500',
        ]);

        $validated['gateway_code'] = strtoupper($validated['gateway_code']);
        $validated['debit_percentage'] = $validated['debit_percentage'] ?? 0.00;
        $validated['credit_percentage'] = $validated['credit_percentage'] ?? 0.00;
        $validated['prepaid_percentage'] = $validated['prepaid_percentage'] ?? 0.00;
        $validated['business_percentage'] = $validated['business_percentage'] ?? 0.00;
        $validated['status'] = 'active';
        $validated['wallet_supported'] = $request->has('wallet_supported');
        $validated['created_by'] = auth()->id();

        CardCashWithdrawalGateway::create($validated);

        return back()->with('success', "Withdrawal gateway '{$validated['gateway_name']}' added successfully.");
    }

    public function updateWithdrawalGateway(Request $request, int $id): RedirectResponse
    {
        $gateway = CardCashWithdrawalGateway::findOrFail($id);

        $validated = $request->validate([
            'gateway_name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:card_cash_companies,id',
            'debit_percentage' => 'nullable|numeric|min:0|max:100',
            'credit_percentage' => 'nullable|numeric|min:0|max:100',
            'prepaid_percentage' => 'nullable|numeric|min:0|max:100',
            'business_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
            'wallet_supported' => 'nullable|boolean',
            'remarks' => 'nullable|string|max:500',
        ]);

        $validated['debit_percentage'] = $validated['debit_percentage'] ?? 0.00;
        $validated['credit_percentage'] = $validated['credit_percentage'] ?? 0.00;
        $validated['prepaid_percentage'] = $validated['prepaid_percentage'] ?? 0.00;
        $validated['business_percentage'] = $validated['business_percentage'] ?? 0.00;
        $validated['wallet_supported'] = $request->has('wallet_supported');

        $gateway->update($validated);

        return back()->with('success', "Withdrawal gateway '{$gateway->gateway_name}' updated successfully.");
    }

    public function destroyWithdrawalGateway(int $id): RedirectResponse
    {
        $gateway = CardCashWithdrawalGateway::findOrFail($id);
        $name = $gateway->gateway_name;
        $gateway->delete();

        return back()->with('success', "Withdrawal gateway '{$name}' deleted successfully.");
    }

    // =========================================================================
    // 2. COMPANIES MASTER CRUD
    // =========================================================================
    public function storeCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_code' => 'nullable|string|max:50|unique:card_cash_companies,company_code',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (!empty($validated['company_code'])) {
            $validated['company_code'] = strtoupper($validated['company_code']);
        }
        $validated['status'] = 'active';
        $validated['created_by'] = auth()->id();

        CardCashCompany::create($validated);

        return back()->with('success', "Company '{$validated['company_name']}' created successfully.");
    }

    public function updateCompany(Request $request, int $id): RedirectResponse
    {
        $company = CardCashCompany::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_code' => 'nullable|string|max:50|unique:card_cash_companies,company_code,' . $company->id,
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'status' => 'required|in:active,inactive',
            'remarks' => 'nullable|string|max:500',
        ]);

        if (!empty($validated['company_code'])) {
            $validated['company_code'] = strtoupper($validated['company_code']);
        }

        $company->update($validated);

        return back()->with('success', "Company '{$company->company_name}' updated successfully.");
    }

    public function destroyCompany(int $id): RedirectResponse
    {
        $company = CardCashCompany::findOrFail($id);
        $name = $company->company_name;

        // Check if company has gateways linked
        if ($company->gateways()->count() > 0) {
            return back()->with('error', "Cannot delete company '{$name}' because it has linked withdrawal gateways. Please reassign or delete the gateways first.");
        }

        $company->delete();

        return back()->with('success', "Company '{$name}' deleted successfully.");
    }

    // =========================================================================
    // 3. BANK NAMES MASTER CRUD
    // =========================================================================
    public function storeBank(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:50',
            'ifsc_prefix' => 'nullable|string|max:20',
        ]);

        if (!empty($validated['bank_code'])) {
            $validated['bank_code'] = strtoupper($validated['bank_code']);
        }
        if (!empty($validated['ifsc_prefix'])) {
            $validated['ifsc_prefix'] = strtoupper($validated['ifsc_prefix']);
        }
        $validated['status'] = 'active';
        $validated['created_by'] = auth()->id();

        CardCashBank::create($validated);

        return back()->with('success', "Bank '{$validated['bank_name']}' added successfully.");
    }

    public function updateBank(Request $request, int $id): RedirectResponse
    {
        $bank = CardCashBank::findOrFail($id);

        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:50',
            'ifsc_prefix' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        if (!empty($validated['bank_code'])) {
            $validated['bank_code'] = strtoupper($validated['bank_code']);
        }
        if (!empty($validated['ifsc_prefix'])) {
            $validated['ifsc_prefix'] = strtoupper($validated['ifsc_prefix']);
        }

        $bank->update($validated);

        return back()->with('success', "Bank '{$bank->bank_name}' updated successfully.");
    }

    public function destroyBank(int $id): RedirectResponse
    {
        $bank = CardCashBank::findOrFail($id);
        $name = $bank->bank_name;
        $bank->delete();

        return back()->with('success', "Bank '{$name}' deleted successfully.");
    }

    // =========================================================================
    // 4. PAYMENT SOURCES CRUD
    // =========================================================================
    public function storePaymentSource(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_name' => 'required|string|max:255',
            'source_type' => 'required|in:gateway,account,wallet',
            'account_number_or_reference' => 'nullable|string|max:100',
            'wallet_id' => 'nullable|exists:credit_card_wallets,id',
            'remarks' => 'nullable|string|max:500',
        ]);

        $validated['status'] = 'active';
        $validated['created_by'] = auth()->id();

        CardCashPaymentSource::create($validated);

        return back()->with('success', "Payment source '{$validated['source_name']}' added successfully.");
    }

    public function updatePaymentSource(Request $request, int $id): RedirectResponse
    {
        $source = CardCashPaymentSource::findOrFail($id);

        $validated = $request->validate([
            'source_name' => 'required|string|max:255',
            'source_type' => 'required|in:gateway,account,wallet',
            'account_number_or_reference' => 'nullable|string|max:100',
            'wallet_id' => 'nullable|exists:credit_card_wallets,id',
            'status' => 'required|in:active,inactive',
            'remarks' => 'nullable|string|max:500',
        ]);

        $source->update($validated);

        return back()->with('success', "Payment source '{$source->source_name}' updated successfully.");
    }

    public function destroyPaymentSource(int $id): RedirectResponse
    {
        $source = CardCashPaymentSource::findOrFail($id);
        $name = $source->source_name;
        $source->delete();

        return back()->with('success', "Payment source '{$name}' deleted successfully.");
    }
}
