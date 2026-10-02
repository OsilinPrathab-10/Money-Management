<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositScheme;
use App\Models\GroupMember;
use App\Services\FixedDeposit\FixedDepositAuditService;
use App\Services\FixedDeposit\FixedDepositInterestService;
use App\Services\FixedDeposit\FixedDepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Throwable;

class FixedDepositController extends Controller
{
    public function __construct(
        protected FixedDepositService $fdService,
        protected FixedDepositInterestService $interestService,
        protected FixedDepositAuditService $auditService,
    ) {}

    public function index(Request $request)
    {
        $query = FixedDeposit::with(['client', 'scheme'])->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('fd_number', 'like', "%{$s}%")
                    ->orWhereHas('client', fn ($c) => $c->where('client_name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->scheme_id);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        $deposits = $query
            ->withCount([
                'transactions as processed_transactions_count' => fn ($q) => $q->where('transaction_type', '!=', 'creation'),
                'interestPayouts',
            ])
            ->paginate(15)
            ->withQueryString();
        $schemes = FixedDepositScheme::orderBy('name')->get(['id', 'name', 'scheme_code']);

        return view('admin.fd.deposits.index', compact('deposits', 'schemes'));
    }

    public function create()
    {
        $schemes = FixedDepositScheme::active()->orderBy('name')->get();
        $clients = Client::orderBy('client_name')->limit(500)->get(['id', 'client_name', 'client_phone']);
        $payoutOptions = FixedDepositScheme::payoutOptions();
        $renewalTypes = FixedDepositScheme::renewalTypes();
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        return view('admin.fd.deposits.form', compact('schemes', 'clients', 'payoutOptions', 'renewalTypes', 'bankAccounts') + ['mode' => 'create']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'scheme_id' => ['required', 'exists:fixed_deposit_schemes,id'],
            'deposit_amount' => ['required', 'numeric', 'min:1'],
            'deposit_date' => ['required', 'date'],
            'start_date' => ['required', 'date'],
            'tenure' => ['required', 'integer', 'min:1'],
            'nominee_name' => ['nullable', 'string', 'max:255'],
            'nominee_relation' => ['nullable', 'string', 'max:100'],
            'payout_option' => ['required', Rule::in(array_keys(FixedDepositScheme::payoutOptions()))],
            'auto_renewal' => ['sometimes', 'boolean'],
            'renewal_type' => ['nullable', Rule::in(array_keys(FixedDepositScheme::renewalTypes()))],
            'remarks' => ['nullable', 'string'],
            'payment_mode' => ['nullable', Rule::in(['cash', 'upi', 'bank_transfer'])],
            'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ]);

        $validated['auto_renewal'] = $request->boolean('auto_renewal');
        $validated['payment_mode'] = $validated['payment_mode'] ?? 'cash';

        try {
            $fd = $this->fdService->create($validated);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('fd.deposits.show', $fd)
            ->with('success', 'Fixed Deposit created successfully. Certificate is ready.');
    }

    public function edit(FixedDeposit $deposit)
    {
        if (!$deposit->canEdit()) {
            return redirect()
                ->route('fd.deposits.show', $deposit)
                ->with('error', 'This Fixed Deposit cannot be edited.');
        }

        $schemes = FixedDepositScheme::query()
            ->where(function ($q) use ($deposit) {
                $q->where('status', 'active')->orWhere('id', $deposit->scheme_id);
            })
            ->orderBy('name')
            ->get();
        $clients = Client::orderBy('client_name')->limit(500)->get(['id', 'client_name', 'client_phone']);
        $payoutOptions = FixedDepositScheme::payoutOptions();
        $renewalTypes = FixedDepositScheme::renewalTypes();
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->orderBy('bank_name')->get();
        $creationTxn = $deposit->transactions()->where('transaction_type', 'creation')->latest('id')->first();
        $lockFinancials = !$deposit->canChangeFinancials();

        return view('admin.fd.deposits.form', compact(
            'deposit',
            'schemes',
            'clients',
            'payoutOptions',
            'renewalTypes',
            'bankAccounts',
            'creationTxn',
            'lockFinancials'
        ) + ['mode' => 'edit']);
    }

    public function update(Request $request, FixedDeposit $deposit)
    {
        if (!$deposit->canEdit()) {
            return back()->with('error', 'This Fixed Deposit cannot be edited.');
        }

        $financialRules = $deposit->canChangeFinancials()
            ? [
                'scheme_id' => ['required', 'exists:fixed_deposit_schemes,id'],
                'deposit_amount' => ['required', 'numeric', 'min:1'],
                'deposit_date' => ['required', 'date'],
                'start_date' => ['required', 'date'],
                'tenure' => ['required', 'integer', 'min:1'],
                'payment_mode' => ['nullable', Rule::in(['cash', 'upi', 'bank_transfer'])],
                'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            ]
            : [];

        $validated = $request->validate(array_merge([
            'nominee_name' => ['nullable', 'string', 'max:255'],
            'nominee_relation' => ['nullable', 'string', 'max:100'],
            'payout_option' => ['required', Rule::in(array_keys(FixedDepositScheme::payoutOptions()))],
            'auto_renewal' => ['sometimes', 'boolean'],
            'renewal_type' => ['nullable', Rule::in(array_keys(FixedDepositScheme::renewalTypes()))],
            'remarks' => ['nullable', 'string'],
        ], $financialRules));

        $validated['auto_renewal'] = $request->boolean('auto_renewal');
        if ($deposit->canChangeFinancials()) {
            $validated['payment_mode'] = $validated['payment_mode'] ?? 'cash';
        }

        try {
            $this->fdService->update($deposit, $validated);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('fd.deposits.show', $deposit)
            ->with('success', 'Fixed Deposit updated successfully.');
    }

    public function show(FixedDeposit $deposit)
    {
        $deposit->load([
            'client',
            'scheme',
            'transactions' => fn ($q) => $q->latest(),
            'interestPayouts' => fn ($q) => $q->latest(),
            'chitAllocations.chitGroup',
            'renewalsAsOld.newDeposit',
            'renewedFrom',
            'creator',
            'closedByUser',
        ]);

        $chitGroups = GroupMember::with('group')
            ->where('client_id', $deposit->client_id)
            ->whereIn('status', ['active', 'approved'])
            ->get()
            ->pluck('group')
            ->filter()
            ->unique('id')
            ->values();

        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        return view('admin.fd.deposits.form', compact('deposit', 'chitGroups', 'bankAccounts') + ['mode' => 'show']);
    }

    public function certificate(FixedDeposit $deposit)
    {
        $deposit->load(['client', 'scheme', 'creator']);
        $company = \App\Models\CompanyDetail::query()->first();
        $reportBranding = app(\App\Services\ReportBrandingService::class)->get();

        return view('admin.fd.deposits.certificate', compact('deposit', 'company', 'reportBranding'));
    }

    public function calculate(Request $request)
    {
        $request->validate([
            'scheme_id' => ['required', 'exists:fixed_deposit_schemes,id'],
            'deposit_amount' => ['required', 'numeric', 'min:1'],
            'tenure' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
        ]);

        $scheme = FixedDepositScheme::findOrFail($request->scheme_id);
        $calc = $this->fdService->previewCalculation(
            $scheme,
            (float) $request->deposit_amount,
            (int) $request->tenure,
            $request->start_date
        );

        return response()->json([
            'interest_rate' => (float) $scheme->interest_rate,
            'interest_type' => $scheme->deposit_type,
            'interest_type_label' => $scheme->deposit_type_label,
            'interest_frequency' => $scheme->interest_frequency,
            'interest_frequency_label' => $scheme->interest_frequency_label,
            'interest_amount' => $calc['interest_amount'],
            'maturity_amount' => $calc['maturity_amount'],
            'maturity_date' => $calc['maturity_date']->format('Y-m-d'),
            'maturity_date_formatted' => $calc['maturity_date']->format('d M Y'),
            'min_deposit' => (float) $scheme->min_deposit_amount,
            'max_deposit' => (float) $scheme->max_deposit_amount,
            'min_tenure' => $scheme->min_tenure,
            'max_tenure' => $scheme->max_tenure,
            'tenure_type' => $scheme->tenure_type,
            'auto_renewal' => (bool) $scheme->auto_renewal,
            'renewal_type' => $scheme->renewal_type,
            'default_payout_option' => $scheme->default_payout_option,
            'premature_allowed' => (bool) $scheme->premature_withdrawal_allowed,
        ]);
    }

    public function processMaturity(Request $request, FixedDeposit $deposit)
    {
        $validated = $request->validate([
            'payout_option' => ['required', Rule::in(['wallet', 'chit', 'cash', 'bank_transfer'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'customer_bank_name' => ['nullable', 'string', 'max:255'],
            'customer_account_number' => ['nullable', 'string', 'max:64'],
            'customer_ifsc_code' => ['nullable', 'string', 'max:32'],
            'customer_branch_name' => ['nullable', 'string', 'max:255'],
            'customer_holder_name' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:10240'],
            'utr_reference' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'chit_group_id' => ['nullable', 'exists:chit_groups,id'],
            'allocation_type' => ['nullable', Rule::in(['pending_installment', 'advance', 'settlement', 'join_new', 'chit_wallet'])],
            'allocation_amount' => ['nullable', 'numeric', 'min:0'],
            'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'processing_fee' => ['nullable', 'numeric', 'min:0'],
            'document_charges' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'banking_charges' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->hasFile('payment_proof')) {
            $validated['payment_proof'] = $request->file('payment_proof');
        }

        try {
            $this->fdService->processMaturity($deposit, $validated);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Maturity processed successfully.');
    }

    public function prematureWithdraw(Request $request, FixedDeposit $deposit)
    {
        $validated = $request->validate([
            'withdrawal_date' => ['nullable', 'date'],
            'payout_option' => ['required', Rule::in(['wallet', 'chit', 'cash', 'bank_transfer'])],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'customer_bank_name' => ['nullable', 'string', 'max:255'],
            'customer_account_number' => ['nullable', 'string', 'max:64'],
            'customer_ifsc_code' => ['nullable', 'string', 'max:32'],
            'customer_branch_name' => ['nullable', 'string', 'max:255'],
            'customer_holder_name' => ['nullable', 'string', 'max:255'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:10240'],
            'utr_reference' => ['nullable', 'string', 'max:100'],
            'chit_group_id' => ['nullable', 'exists:chit_groups,id'],
            'allocation_type' => ['nullable', Rule::in(['pending_installment', 'advance', 'settlement', 'join_new', 'chit_wallet'])],
            'allocation_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'processing_fee' => ['nullable', 'numeric', 'min:0'],
            'document_charges' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'banking_charges' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->hasFile('payment_proof')) {
            $validated['payment_proof'] = $request->file('payment_proof');
        }

        try {
            $result = $this->fdService->prematureWithdraw($deposit, $validated);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('fd.deposits.premature-receipt', $result['fd'])
            ->with('success', 'Premature withdrawal processed. Payable: ₹' . number_format($result['payable'], 2));
    }

    public function prematureReceipt(FixedDeposit $deposit)
    {
        $deposit->load(['client', 'scheme', 'transactions' => fn ($q) => $q->where('transaction_type', 'premature')->latest()]);
        $txn = $deposit->transactions->first();

        return view('admin.fd.deposits.premature-receipt', compact('deposit', 'txn'));
    }

    public function renew(Request $request, FixedDeposit $deposit)
    {
        $validated = $request->validate([
            'renewal_type' => ['required', Rule::in(['principal_only', 'principal_interest'])],
        ]);

        try {
            $newFd = $this->fdService->renew($deposit, $validated['renewal_type']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('fd.deposits.show', $newFd)
            ->with('success', 'Fixed Deposit renewed as ' . $newFd->fd_number);
    }

    public function close(Request $request, FixedDeposit $deposit)
    {
        $validated = $request->validate([
            'closure_date' => ['required', 'date'],
            'closure_amount' => ['required', 'numeric', 'min:0'],
            'payment_mode' => ['required', 'string', 'max:50'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'processing_fee' => ['nullable', 'numeric', 'min:0'],
            'document_charges' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'banking_charges' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->fdService->close($deposit, $validated);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Fixed Deposit closed successfully.');
    }

    public function destroy(FixedDeposit $deposit)
    {
        if (!$deposit->canDelete()) {
            $this->auditService->log('Delete Attempt', $deposit->id, $deposit->scheme_id, $deposit->toArray(), null, 'Blocked');

            return back()->with('error', 'Closed, cancelled, or processed Fixed Deposits cannot be deleted.');
        }

        if (!Auth::user()?->hasRole('Admin') && !Auth::user()?->hasRole('Super Admin')) {
            return back()->with('error', 'Only Admin can delete Fixed Deposits.');
        }

        try {
            $this->fdService->delete($deposit);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('fd.deposits.index')->with('success', 'Fixed Deposit deleted.');
    }

    public function toggleAutoRenewal(Request $request, FixedDeposit $deposit)
    {
        if ($deposit->status !== 'active') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auto renewal can only be toggled for active Fixed Deposits.',
                ], 422);
            }

            return back()->with('error', 'Auto renewal can only be toggled for active Fixed Deposits.');
        }

        $autoRenewal = $request->has('auto_renewal')
            ? $request->boolean('auto_renewal')
            : !$deposit->auto_renewal;

        $deposit->auto_renewal = $autoRenewal;
        if ($request->filled('renewal_type')) {
            $deposit->renewal_type = $request->input('renewal_type');
        }
        $deposit->save();

        $this->auditService->log('Toggle Auto Renewal', $deposit->id, $deposit->scheme_id, null, [
            'auto_renewal' => $deposit->auto_renewal,
            'renewal_type' => $deposit->renewal_type,
        ], 'Success');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Auto renewal updated successfully.',
                'auto_renewal' => (bool) $deposit->auto_renewal,
                'status_label' => $deposit->auto_renewal ? 'Enabled' : 'Disabled',
                'deposit' => $deposit,
            ]);
        }

        return back()->with('success', 'Auto renewal updated successfully.');
    }
}
