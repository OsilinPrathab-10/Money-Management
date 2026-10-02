<?php

namespace App\Http\Controllers;

use App\Models\FixedDepositScheme;
use App\Services\FixedDeposit\FixedDepositAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FixedDepositSchemeController extends Controller
{
    public function __construct(protected FixedDepositAuditService $auditService)
    {
    }

    public function index(Request $request)
    {
        $query = FixedDepositScheme::latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('scheme_code', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $schemes = $query->paginate(15)->withQueryString();

        return view('admin.fd.schemes.index', compact('schemes'));
    }

    public function create()
    {
        return view('admin.fd.schemes.form', array_merge($this->formData(), ['mode' => 'create']));
    }

    public function store(Request $request)
    {
        $validated = $this->validateScheme($request);
        $validated['scheme_code'] = FixedDepositScheme::generateCode();
        $validated['created_by'] = Auth::id();
        $validated['premature_withdrawal_allowed'] = $request->boolean('premature_withdrawal_allowed');
        $validated['auto_renewal'] = $request->boolean('auto_renewal');

        if (!$validated['premature_withdrawal_allowed']) {
            $validated['premature_penalty_type'] = null;
            $validated['premature_penalty_value'] = null;
        }

        if (!$validated['auto_renewal']) {
            $validated['renewal_type'] = null;
        }

        $scheme = FixedDepositScheme::create($validated);

        $this->auditService->log('Scheme Created', null, $scheme->id, null, $scheme->toArray());

        return redirect()->route('fd.schemes.index')->with('success', 'Fixed Deposit scheme created successfully.');
    }

    public function show(FixedDepositScheme $scheme)
    {
        $scheme->loadCount('deposits');

        return view('admin.fd.schemes.form', array_merge($this->formData(), compact('scheme'), ['mode' => 'show']));
    }

    public function edit(FixedDepositScheme $scheme)
    {
        return view('admin.fd.schemes.form', array_merge($this->formData(), compact('scheme'), ['mode' => 'edit']));
    }

    public function update(Request $request, FixedDepositScheme $scheme)
    {
        $previous = $scheme->toArray();
        $validated = $this->validateScheme($request, $scheme->id);
        $validated['premature_withdrawal_allowed'] = $request->boolean('premature_withdrawal_allowed');
        $validated['auto_renewal'] = $request->boolean('auto_renewal');

        if (!$validated['premature_withdrawal_allowed']) {
            $validated['premature_penalty_type'] = null;
            $validated['premature_penalty_value'] = null;
        }

        if (!$validated['auto_renewal']) {
            $validated['renewal_type'] = null;
        }

        $rateChanged = (float) $scheme->interest_rate !== (float) $validated['interest_rate'];
        $scheme->update($validated);

        $this->auditService->log(
            $rateChanged ? 'Interest Rate Changed' : 'Scheme Updated',
            null,
            $scheme->id,
            $previous,
            $scheme->fresh()->toArray()
        );

        return redirect()->route('fd.schemes.index')->with('success', 'Scheme updated successfully.');
    }

    public function destroy(FixedDepositScheme $scheme)
    {
        if ($scheme->deposits()->exists()) {
            $this->auditService->log('Delete Attempt', null, $scheme->id, $scheme->toArray(), null, 'Blocked: scheme has deposits');

            return back()->with('error', 'Cannot delete scheme with existing Fixed Deposits.');
        }

        $this->auditService->log('Scheme Deleted', null, $scheme->id, $scheme->toArray(), null);
        $scheme->delete();

        return redirect()->route('fd.schemes.index')->with('success', 'Scheme deleted successfully.');
    }

    private function validateScheme(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'deposit_type' => ['required', Rule::in(array_keys(FixedDepositScheme::depositTypes()))],
            'min_deposit_amount' => ['required', 'numeric', 'min:1'],
            'max_deposit_amount' => ['required', 'numeric', 'gte:min_deposit_amount'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_frequency' => ['required', Rule::in(array_keys(FixedDepositScheme::frequencies()))],
            'min_tenure' => ['required', 'integer', 'min:1'],
            'max_tenure' => ['required', 'integer', 'gte:min_tenure'],
            'tenure_type' => ['required', Rule::in(array_keys(FixedDepositScheme::tenureTypes()))],
            'premature_withdrawal_allowed' => ['sometimes', 'boolean'],
            'premature_penalty_type' => ['nullable', Rule::in(['percentage', 'fixed']), 'required_if:premature_withdrawal_allowed,1'],
            'premature_penalty_value' => ['nullable', 'numeric', 'min:0', 'required_if:premature_withdrawal_allowed,1'],
            'auto_renewal' => ['sometimes', 'boolean'],
            'renewal_type' => ['nullable', Rule::in(array_keys(FixedDepositScheme::renewalTypes())), 'required_if:auto_renewal,1'],
            'default_payout_option' => ['required', Rule::in(array_keys(FixedDepositScheme::payoutOptions()))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
        ]);
    }

    private function formData(): array
    {
        return [
            'depositTypes' => FixedDepositScheme::depositTypes(),
            'frequencies' => FixedDepositScheme::frequencies(),
            'tenureTypes' => FixedDepositScheme::tenureTypes(),
            'payoutOptions' => FixedDepositScheme::payoutOptions(),
            'renewalTypes' => FixedDepositScheme::renewalTypes(),
        ];
    }
}
