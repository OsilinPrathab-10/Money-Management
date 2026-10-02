<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateBankAccount;
use App\Events\Account\DestroyBankAccount;
use App\Events\Account\UpdateBankAccount;
use App\Http\Requests\Account\StoreBankAccountRequest;
use App\Http\Requests\Account\UpdateBankAccountRequest;
use App\Models\Account\BankAccount;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BankAccountController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-bank-accounts')){
            $bankaccounts = BankAccount::query()
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-bank-accounts')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-bank-accounts')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('account_number'), function($q) {
                    $q->where(function($query) {
                    $query->where('account_number', 'like', '%' . request('account_number') . '%');
                    $query->orWhere('account_name', 'like', '%' . request('account_number') . '%');
                    $query->orWhere('bank_name', 'like', '%' . request('account_number') . '%');
                    });
                })
                ->when(request('bank_name'), fn($q) => $q->where('bank_name', 'like', '%' . request('bank_name') . '%'))
                ->when(request('account_type') !== null && request('account_type') !== '', fn($q) => $q->where('account_type', request('account_type')))
                ->when(request('is_active') !== null && request('is_active') !== '', fn($q) => $q->where('is_active', request('is_active') === '1'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 20))
                ->withQueryString();

            return view('admin.account.bank-accounts.index', [
                'bankaccounts' => $bankaccounts,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-bank-accounts')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:csv,xlsx',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_type' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = BankAccount::query()
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-bank-accounts')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-bank-accounts')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($validated['account_number']), function ($q) use ($validated) {
                $term = (string) $validated['account_number'];
                $q->where(function ($query) use ($term) {
                    $query->where('account_number', 'like', '%' . $term . '%')
                        ->orWhere('account_name', 'like', '%' . $term . '%')
                        ->orWhere('bank_name', 'like', '%' . $term . '%');
                });
            })
            ->when(!empty($validated['bank_name']), fn($q) => $q->where('bank_name', 'like', '%' . $validated['bank_name'] . '%'))
            ->when(!empty($validated['account_type']), fn($q) => $q->where('account_type', $validated['account_type']))
            ->when(isset($validated['is_active']) && $validated['is_active'] !== '', fn($q) => $q->where('is_active', $validated['is_active'] === '1'));

        if (!empty($validated['sort'])) {
            $query->orderBy($validated['sort'], $validated['direction'] ?? 'asc');
        } else {
            $query->latest('id');
        }

        $bankaccounts = $query->get();

        $totalOpening = (float) ($bankaccounts->sum('opening_balance') ?? 0);
        $totalCurrent = (float) ($bankaccounts->sum('current_balance') ?? 0);

        $rows = $bankaccounts->map(function ($ba) {
            return [
                'account' => $ba->account_number,
                'name' => $ba->account_name,
                'bank' => $ba->bank_name,
                'branch' => $ba->branch_name ?? '—',
                'type' => $ba->account_type,
                'opening' => '₹' . number_format((float) ($ba->opening_balance ?? 0), 2),
                'current' => '₹' . number_format((float) ($ba->current_balance ?? 0), 2),
                'active' => $ba->is_active ? __('Yes') : __('No'),
            ];
        })->values()->all();

        $rows[] = [
            'account' => __('TOTAL'),
            'name' => '',
            'bank' => '',
            'branch' => '',
            'type' => '',
            'opening' => '₹' . number_format($totalOpening, 2),
            'current' => '₹' . number_format($totalCurrent, 2),
            'active' => '',
        ];

        $columns = [
            ['key' => 'account', 'label' => __('Account #')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'bank', 'label' => __('Bank')],
            ['key' => 'branch', 'label' => __('Branch')],
            ['key' => 'type', 'label' => __('Type')],
            ['key' => 'opening', 'label' => __('Opening'), 'class' => 'text-end'],
            ['key' => 'current', 'label' => __('Current'), 'class' => 'text-end'],
            ['key' => 'active', 'label' => __('Active')],
        ];

        $subtitleParts = [];
        if (!empty($validated['account_number'])) {
            $subtitleParts[] = 'Search: ' . $validated['account_number'];
        }
        if (!empty($validated['bank_name'])) {
            $subtitleParts[] = 'Bank: ' . $validated['bank_name'];
        }
        if (!empty($validated['account_type'])) {
            $subtitleParts[] = 'Type: ' . $validated['account_type'];
        }
        if (($validated['is_active'] ?? null) !== null && $validated['is_active'] !== '') {
            $subtitleParts[] = 'Active: ' . (string) $validated['is_active'];
        }

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Bank accounts'),
                'subtitle' => !empty($subtitleParts) ? implode(' | ', $subtitleParts) : null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'bank-accounts-export'
        );
    }

    public function edit(BankAccount $bankaccount)
    {
        if (! Auth::user()->can('edit-bank-accounts')) {
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }

        if (Auth::user()->can('manage-any-bank-accounts')) {
            if ((int) $bankaccount->created_by !== (int) creatorId()) {
                abort(403);
            }
        } elseif (Auth::user()->can('manage-own-bank-accounts')) {
            if ((int) $bankaccount->creator_id !== (int) Auth::id()) {
                abort(403);
            }
        } else {
            abort(403);
        }

        // Edit uses the shared bank account modal on the index page.
        return redirect()->route('account.bank-accounts.index');
    }

    public function store(StoreBankAccountRequest $request)
    {
        if(Auth::user()->can('create-bank-accounts')){
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', false);

            $bankaccount = new BankAccount();
            $bankaccount->account_number = $validated['account_number'];
            $bankaccount->account_name = $validated['account_name'];
            $bankaccount->bank_name = $validated['bank_name'];
            $bankaccount->branch_name = $validated['branch_name'] ?? null;
            $bankaccount->account_type = $validated['account_type'];
            $bankaccount->payment_gateway = $validated['payment_gateway'] ?? null;
            $bankaccount->upi_id = $validated['upi_id'] ?? null;
            
            if ($request->hasFile('qr_code')) {
                $bankaccount->qr_code = $request->file('qr_code')->store('bank_accounts/qrcodes', 'public');
            }

            $bankaccount->opening_balance = $validated['opening_balance'];
            $bankaccount->current_balance = $validated['current_balance'];
            $bankaccount->iban = $validated['iban'] ?? null;
            $bankaccount->swift_code = $validated['swift_code'] ?? null;
            $bankaccount->routing_number = $validated['routing_number'] ?? null;
            $bankaccount->ifsc_code = $validated['ifsc_code'] ?? null;
            $bankaccount->is_active = $validated['is_active'];
            $bankaccount->gl_account_id = null;
            $bankaccount->creator_id = Auth::id();
            $bankaccount->created_by = creatorId();
            $bankaccount->save();

            $this->ensureGLAccount($bankaccount);

            // Add Opening Balance to Bank Transaction Log
            if ($bankaccount->opening_balance > 0) {
                $initialTransaction = new \App\Models\Account\BankTransaction();
                $initialTransaction->bank_account_id = $bankaccount->id;
                $initialTransaction->transaction_date = now();
                $initialTransaction->transaction_type = 'credit';
                $initialTransaction->reference_number = 'OPENING-BAL';
                $initialTransaction->description = 'Opening Balance';
                $initialTransaction->amount = $bankaccount->opening_balance;
                $initialTransaction->running_balance = $bankaccount->opening_balance;
                $initialTransaction->transaction_status = 'cleared';
                $initialTransaction->reconciliation_status = 'unreconciled';
                $initialTransaction->created_by = creatorId();
                $initialTransaction->save();
            }

            CreateBankAccount::dispatch($request, $bankaccount);

            return redirect()->route('account.bank-accounts.index')->with('success', __('The bank account has been created successfully.'));
        }
        else{
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankaccount)
    {
        if(Auth::user()->can('edit-bank-accounts')){
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', false);

            $oldOpeningBalance = $bankaccount->getOriginal('opening_balance');
            
            $bankaccount->account_number = $validated['account_number'];
            $bankaccount->account_name = $validated['account_name'];
            $bankaccount->bank_name = $validated['bank_name'];
            $bankaccount->branch_name = $validated['branch_name'] ?? null;
            $bankaccount->account_type = $validated['account_type'];
            $bankaccount->payment_gateway = $validated['payment_gateway'] ?? null;
            $bankaccount->upi_id = $validated['upi_id'] ?? null;
            
            if ($request->hasFile('qr_code')) {
                if ($bankaccount->qr_code && \Illuminate\Support\Facades\Storage::disk('public')->exists($bankaccount->qr_code)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($bankaccount->qr_code);
                }
                $bankaccount->qr_code = $request->file('qr_code')->store('bank_accounts/qrcodes', 'public');
            }

            $bankaccount->opening_balance = $validated['opening_balance'];
            
            // Adjust current balance by the difference in opening balance
            $balanceDifference = $validated['opening_balance'] - $oldOpeningBalance;
            $bankaccount->current_balance = $bankaccount->current_balance + $balanceDifference;
            
            $bankaccount->iban = $validated['iban'] ?? null;
            $bankaccount->swift_code = $validated['swift_code'] ?? null;
            $bankaccount->routing_number = $validated['routing_number'] ?? null;
            $bankaccount->ifsc_code = $validated['ifsc_code'] ?? null;
            $bankaccount->is_active = $validated['is_active'];
            $bankaccount->save();

            $this->ensureGLAccount($bankaccount);

            // If opening balance changed, log an adjustment transaction
            if ($balanceDifference != 0) {
                $lastTransaction = \App\Models\Account\BankTransaction::where('bank_account_id', $bankaccount->id)
                    ->orderBy('id', 'desc')
                    ->first();
                
                $runningBalance = $lastTransaction ? $lastTransaction->running_balance + $balanceDifference : $bankaccount->current_balance;

                $adjTransaction = new \App\Models\Account\BankTransaction();
                $adjTransaction->bank_account_id = $bankaccount->id;
                $adjTransaction->transaction_date = now();
                $adjTransaction->transaction_type = $balanceDifference > 0 ? 'credit' : 'debit';
                $adjTransaction->reference_number = 'ADJ-OPENING';
                $adjTransaction->description = 'Opening Balance Adjustment';
                $adjTransaction->amount = abs($balanceDifference);
                $adjTransaction->running_balance = $runningBalance;
                $adjTransaction->transaction_status = 'cleared';
                $adjTransaction->reconciliation_status = 'unreconciled';
                $adjTransaction->created_by = creatorId();
                $adjTransaction->save();
            }

            UpdateBankAccount::dispatch($request, $bankaccount);

            return redirect()->route('account.bank-accounts.index')->with('success', __('The bank account details are updated successfully.'));
        }
        else{
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(BankAccount $bankaccount)
    {
        if(Auth::user()->can('delete-bank-accounts')){
            DestroyBankAccount::dispatch($bankaccount);

            if ($bankaccount->qr_code && \Illuminate\Support\Facades\Storage::disk('public')->exists($bankaccount->qr_code)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($bankaccount->qr_code);
            }

            $bankaccount->delete();

            return redirect()->back()->with('success', __('The bank account has been deleted.'));
        }
        else{
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function bankAccounts()
    {
        $bankAccounts = BankAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->select('id', 'account_name', 'account_number')
            ->get();

        return response()->json($bankAccounts);
    }

    private function ensureGLAccount(BankAccount $bankaccount)
    {
        // GL Account creation logic has been disabled as requested
        /*
        if (!$bankaccount->gl_account_id) {
            $accountType = \App\Models\Account\AccountType::where(function($q) {
                $q->where('code', 'BANK_CASH')->orWhere('name', 'like', '%Bank%');
            })->where('created_by', creatorId())->first();
            
            if (!$accountType) {
                $accountType = \App\Models\Account\AccountType::firstOrCreate([
                    'name' => 'Bank & Cash Accounts',
                    'created_by' => creatorId(),
                ], [
                    'code' => 'BANK_CASH',
                    'description' => 'Liquid assets in banks and petty cash',
                ]);
            }
            
            $glCode = '101' . str_pad($bankaccount->id, 3, '0', STR_PAD_LEFT);
            $glAccount = \App\Models\Account\ChartOfAccount::create([
                'account_code' => $glCode,
                'account_name' => $bankaccount->bank_name . ' - ' . $bankaccount->account_number,
                'level' => 1,
                'normal_balance' => 'debit',
                'opening_balance' => $bankaccount->opening_balance,
                'current_balance' => $bankaccount->current_balance,
                'is_active' => true,
                'is_system_account' => true,
                'account_type_id' => $accountType->id,
                'creator_id' => $bankaccount->creator_id,
                'created_by' => $bankaccount->created_by,
            ]);
            $bankaccount->gl_account_id = $glAccount->id;
            $bankaccount->save();
        } else {
            $glAccount = \App\Models\Account\ChartOfAccount::find($bankaccount->gl_account_id);
            if ($glAccount) {
                $glAccount->account_name = $bankaccount->bank_name . ' - ' . $bankaccount->account_number;
                $glAccount->opening_balance = $bankaccount->opening_balance;
                $glAccount->current_balance = $bankaccount->current_balance;
                $glAccount->is_active = $bankaccount->is_active;
                $glAccount->save();
            }
        }
        */
    }

    public function transaction(Request $request, BankAccount $bankaccount)
    {
        if(!Auth::user()->can('manage-bank-accounts')){
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'transaction_type' => 'required|in:deposit,withdrawal',
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request, $bankaccount) {
                    if ($request->transaction_type === 'withdrawal' && $value > $bankaccount->current_balance) {
                        $fail(__('The withdrawal amount cannot exceed the current balance (:balance).', ['balance' => '₹' . number_format($bankaccount->current_balance, 2)]));
                    }
                },
            ],
            'person_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $bankaccount) {
            $amount = (float) $validated['amount'];
            
            if ($validated['transaction_type'] === 'deposit') {
                $bankaccount->current_balance += $amount;
            } else {
                $bankaccount->current_balance -= $amount;
            }
            
            $bankaccount->save();
            
            $personLabel = $validated['transaction_type'] === 'deposit' ? 'Deposited by' : 'Withdrawn by';
            $fullDescription = "{$personLabel}: {$validated['person_name']}";
            if (!empty($validated['description'])) {
                $fullDescription .= " | {$validated['description']}";
            }

            \App\Models\Account\BankTransaction::create([
                'bank_account_id' => $bankaccount->id,
                'transaction_date' => now(),
                'transaction_type' => $validated['transaction_type'] === 'deposit' ? 'credit' : 'debit',
                'amount' => $amount,
                'running_balance' => $bankaccount->current_balance,
                'description' => $fullDescription,
                'reference_number' => $validated['reference_number'],
                'module_tag' => \App\Services\Account\AccountingTags::MODULE_OTHER,
                'entry_tag' => $validated['transaction_type'] === 'deposit'
                    ? \App\Services\Account\AccountingTags::ENTRY_DEPOSIT
                    : \App\Services\Account\AccountingTags::ENTRY_WITHDRAWAL,
                'transaction_status' => 'cleared',
                'reconciliation_status' => 'unreconciled',
                'created_by' => creatorId(),
            ]);
        });

        return redirect()->back()->with('success', __('Transaction processed successfully.'));
    }
}

