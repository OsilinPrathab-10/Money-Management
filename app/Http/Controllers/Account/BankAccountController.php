<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateBankAccount;
use App\Events\Account\DestroyBankAccount;
use App\Events\Account\UpdateBankAccount;
use App\Http\Requests\Account\StoreBankAccountRequest;
use App\Http\Requests\Account\UpdateBankAccountRequest;
use App\Models\Account\BankAccount;
use App\Models\Account\ChartOfAccount;
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
                ->with(['gl_account'])
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

            $usedGlIds = BankAccount::where('created_by', creatorId())->pluck('gl_account_id')->toArray();
            
            $chartofaccounts = ChartOfAccount::where('created_by', creatorId())
                ->where('is_active', true)
                ->whereHas('accountType.category', function($q) {
                    $q->where('type', 'assets');
                })
                ->whereNotIn('id', $usedGlIds)
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get();

            return view('admin.account.bank-accounts.index', [
                'bankaccounts' => $bankaccounts,
                'chartofaccounts' => $chartofaccounts,
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
            ->with(['gl_account'])
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
                'gl' => $ba->gl_account?->account_code ? ($ba->gl_account->account_code . ' — ' . $ba->gl_account->account_name) : '—',
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
            'gl' => '',
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
            ['key' => 'gl', 'label' => __('GL')],
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

        $usedGlIds = BankAccount::where('created_by', creatorId())
            ->where('id', '!=', $bankaccount->id)
            ->pluck('gl_account_id')
            ->toArray();

        $chartofaccounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->whereHas('accountType.category', function($q) {
                $q->where('type', 'assets');
            })
            ->whereNotIn('id', $usedGlIds)
            ->select('id', 'account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        return view('admin.account.bank-accounts.edit', [
            'bankaccount' => $bankaccount->load('gl_account'),
            'chartofaccounts' => $chartofaccounts,
        ]);
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
            $bankaccount->opening_balance = $validated['opening_balance'];
            $bankaccount->current_balance = $validated['current_balance'];
            $bankaccount->iban = $validated['iban'] ?? null;
            $bankaccount->swift_code = $validated['swift_code'] ?? null;
            $bankaccount->routing_number = $validated['routing_number'] ?? null;
            $bankaccount->is_active = $validated['is_active'];
            $bankaccount->gl_account_id = $validated['gl_account_id'];
            $bankaccount->creator_id = Auth::id();
            $bankaccount->created_by = creatorId();
            $bankaccount->save();

            // Sync with GL Account
            if ($bankaccount->opening_balance > 0) {
                $glAccount = \App\Models\Account\ChartOfAccount::find($bankaccount->gl_account_id);
                if ($glAccount) {
                    $glAccount->opening_balance += $bankaccount->opening_balance;
                    $glAccount->current_balance += $bankaccount->opening_balance;
                    $glAccount->save();
                }

                // Add Opening Balance to Bank Transaction Log
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

            $oldGlId = $bankaccount->getOriginal('gl_account_id');
            $oldOpeningBalance = $bankaccount->getOriginal('opening_balance');
            
            $bankaccount->account_number = $validated['account_number'];
            $bankaccount->account_name = $validated['account_name'];
            $bankaccount->bank_name = $validated['bank_name'];
            $bankaccount->branch_name = $validated['branch_name'] ?? null;
            $bankaccount->account_type = $validated['account_type'];
            $bankaccount->payment_gateway = $validated['payment_gateway'] ?? null;
            $bankaccount->opening_balance = $validated['opening_balance'];
            
            // Adjust current balance by the difference in opening balance
            $balanceDifference = $validated['opening_balance'] - $oldOpeningBalance;
            $bankaccount->current_balance = $bankaccount->current_balance + $balanceDifference;
            
            $bankaccount->iban = $validated['iban'] ?? null;
            $bankaccount->swift_code = $validated['swift_code'] ?? null;
            $bankaccount->routing_number = $validated['routing_number'] ?? null;
            $bankaccount->is_active = $validated['is_active'];
            $bankaccount->gl_account_id = $validated['gl_account_id'];
            $bankaccount->save();

            $newGlId = $bankaccount->gl_account_id;
            $newOpeningBalance = $bankaccount->opening_balance;

            // Sync GL Account
            if ($oldGlId != $newGlId) {
                // Subtract from old GL
                if ($oldOpeningBalance > 0) {
                    $oldGl = \App\Models\Account\ChartOfAccount::find($oldGlId);
                    if ($oldGl) {
                        $oldGl->opening_balance -= $oldOpeningBalance;
                        $oldGl->current_balance -= $oldOpeningBalance;
                        $oldGl->save();
                    }
                }
                // Add to new GL
                if ($newOpeningBalance > 0) {
                    $newGl = \App\Models\Account\ChartOfAccount::find($newGlId);
                    if ($newGl) {
                        $newGl->opening_balance += $newOpeningBalance;
                        $newGl->current_balance += $newOpeningBalance;
                        $newGl->save();
                    }
                }
            } else if ($oldOpeningBalance != $newOpeningBalance) {
                // Update same GL
                $glAccount = \App\Models\Account\ChartOfAccount::find($newGlId);
                if ($glAccount) {
                    $diff = $newOpeningBalance - $oldOpeningBalance;
                    $glAccount->opening_balance += $diff;
                    $glAccount->current_balance += $diff;
                    $glAccount->save();
                }
            }

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

            return redirect()->back()->with('success', __('The bank account details are updated successfully.'));
        }
        else{
            return redirect()->route('account.bank-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(BankAccount $bankaccount)
    {
        if(Auth::user()->can('delete-bank-accounts')){
            DestroyBankAccount::dispatch($bankaccount);
            
            // Reverse GL Account sync
            if ($bankaccount->opening_balance > 0) {
                $glAccount = \App\Models\Account\ChartOfAccount::find($bankaccount->gl_account_id);
                if ($glAccount) {
                    $glAccount->opening_balance -= $bankaccount->opening_balance;
                    $glAccount->current_balance -= $bankaccount->opening_balance;
                    $glAccount->save();
                }
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
}

