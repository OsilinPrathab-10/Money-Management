<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankAccount;
use App\Models\Account\BankTransaction;
use App\Services\Account\AccountExportService;
use App\Services\Account\AccountingTags;
use App\Support\DateRangePreset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::user()->can('manage-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'bank_account_id' => 'nullable|integer',
            'transaction_type' => 'nullable|string|in:credit,debit',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'entry_tag' => 'nullable|string|max:40',
            'search' => 'nullable|string|max:255',
            'date_preset' => 'nullable|string|max:50',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:10|max:200',
        ]);

        $moduleTag = $validated['module_tag'] ?? 'all';
        $entryTag = $validated['entry_tag'] ?? null;

        [$dateFrom, $dateTo] = DateRangePreset::applyToRequest($request, 'date_from', 'date_to');

        $query = BankTransaction::with(['bankAccount:id,account_name,bank_name,account_type'])
            ->where('created_by', creatorId());

        if (! empty($validated['bank_account_id'])) {
            $query->where('bank_account_id', (int) $validated['bank_account_id']);
        }
        if (! empty($validated['transaction_type'])) {
            $query->where('transaction_type', $validated['transaction_type']);
        }
        if ($moduleTag !== 'all') {
            if ($moduleTag === 'TRANSFER') {
                $query->where(function ($q) {
                    $q->where('module_tag', 'TRANSFER')
                      ->orWhere('entry_tag', AccountingTags::ENTRY_TRANSFER)
                      ->orWhere('reference_number', 'like', 'BT-%');
                });
            } else {
                $query->where('module_tag', $moduleTag);
            }
        }
        if ($entryTag) {
            $query->where('entry_tag', $entryTag);
        }
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('bankAccount', function ($bq) use ($search) {
                        $bq->where('account_name', 'like', '%' . $search . '%')
                            ->orWhere('bank_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $sortField = $validated['sort'] ?? 'transaction_date';
        $sortDirection = $validated['direction'] ?? 'desc';
        $allowedSort = ['transaction_date', 'amount', 'created_at', 'transaction_type', 'module_tag'];
        if (! in_array($sortField, $allowedSort, true)) {
            $sortField = 'transaction_date';
        }
        $query->orderBy($sortField, $sortDirection)->orderByDesc('id');

        $transactions = $query->paginate((int) ($validated['per_page'] ?? 20))->withQueryString();
        $bankAccounts = BankAccount::where('is_active', true)
            ->where('created_by', creatorId())
            ->select('id', 'account_name', 'bank_name', 'current_balance')
            ->orderBy('account_name')
            ->get();

        $selectedAccountId = ! empty($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null;
        $selectedBankAccount = $selectedAccountId ? $bankAccounts->firstWhere('id', $selectedAccountId) : null;
        $totalBankBalance = (float) $bankAccounts->sum('current_balance');

        $totalsQuery = clone $query;
        $totalCredits = (float) (clone $totalsQuery)->where('transaction_type', 'credit')->sum('amount');
        $totalDebits = (float) (clone $totalsQuery)->where('transaction_type', 'debit')->sum('amount');

        return view('admin.account.bank-transactions.index', [
            'transactions' => $transactions,
            'bankAccounts' => $bankAccounts,
            'selectedBankAccount' => $selectedBankAccount,
            'totalBankBalance' => $totalBankBalance,
            'totalCredits' => $totalCredits,
            'totalDebits' => $totalDebits,
            'moduleTag' => $moduleTag,
            'entryTag' => $entryTag,
            'moduleOptions' => AccountingTags::modules(),
            'entryOptions' => AccountingTags::entries(),
        ]);
    }

    public function markReconciled($id)
    {
        if (Auth::user()->can('reconcile-bank-transactions')) {
            $transaction = BankTransaction::where('id', $id)
                ->where('created_by', creatorId())
                ->first();

            if ($transaction && $transaction->reconciliation_status === 'unreconciled') {
                $transaction->reconciliation_status = 'reconciled';
                $transaction->save();

                return back()->with('success', __('Transaction marked as reconciled'));
            }

            return back()->with('error', __('Transaction not found or already reconciled'));
        }

        return back()->with('error', __('Permission denied'));
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-bank-transactions')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'bank_account_id' => 'nullable|integer',
            'transaction_type' => 'nullable|string|max:50',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'entry_tag' => 'nullable|string|max:40',
            'search' => 'nullable|string|max:255',
            'date_preset' => 'nullable|string|max:50',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $moduleTag = $validated['module_tag'] ?? 'all';
        $entryTag = $validated['entry_tag'] ?? null;

        [$dateFrom, $dateTo] = DateRangePreset::applyToRequest($request, 'date_from', 'date_to');

        $query = BankTransaction::with(['bankAccount:id,account_name,bank_name,current_balance'])
            ->where('created_by', creatorId());

        if (! empty($validated['bank_account_id'])) {
            $query->where('bank_account_id', (int) $validated['bank_account_id']);
        }
        if (! empty($validated['transaction_type'])) {
            $query->where('transaction_type', $validated['transaction_type']);
        }
        if ($moduleTag !== 'all') {
            if ($moduleTag === 'TRANSFER') {
                $query->where(function ($q) {
                    $q->where('module_tag', 'TRANSFER')
                      ->orWhere('entry_tag', AccountingTags::ENTRY_TRANSFER)
                      ->orWhere('reference_number', 'like', 'BT-%');
                });
            } else {
                $query->where('module_tag', $moduleTag);
            }
        }
        if ($entryTag) {
            $query->where('entry_tag', $entryTag);
        }
        if ($dateFrom) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('bankAccount', function ($bq) use ($search) {
                        $bq->where('account_name', 'like', '%' . $search . '%')
                            ->orWhere('bank_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $sortField = $validated['sort'] ?? 'transaction_date';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection)->orderByDesc('id');

        $transactions = $query->get();
        $totalCredit = (float) $transactions->where('transaction_type', 'credit')->sum('amount');
        $totalDebit = (float) $transactions->where('transaction_type', 'debit')->sum('amount');

        $rows = $transactions->map(function ($t) {
            $bankLabel = $t->bankAccount?->account_name ?? '—';
            if ($t->bankAccount?->bank_name) {
                $bankLabel .= ' (' . $t->bankAccount->bank_name . ')';
            }
            $isCredit = $t->transaction_type === 'credit';
            $isDebit = $t->transaction_type === 'debit';

            return [
                'date' => $t->transaction_date?->format('Y-m-d') ?? '',
                'bank' => $bankLabel,
                'module' => $t->module_tag ?? '—',
                'entry' => $t->entry_tag ? AccountingTags::entryLabel($t->entry_tag) : '—',
                'reference' => $t->reference_number ?? '—',
                'description' => (string) ($t->description ?? ''),
                'credit' => $isCredit ? '₹' . number_format((float) ($t->amount ?? 0), 2) : '—',
                'debit' => $isDebit ? '₹' . number_format((float) ($t->amount ?? 0), 2) : '—',
                'balance' => '₹' . number_format((float) ($t->running_balance ?? 0), 2),
                'reconciled' => $t->reconciliation_status ?? '—',
            ];
        })->values()->all();

        $rows[] = [
            'date' => '',
            'bank' => '',
            'module' => '',
            'entry' => '',
            'reference' => __('TOTAL'),
            'description' => '',
            'credit' => '₹' . number_format($totalCredit, 2),
            'debit' => '₹' . number_format($totalDebit, 2),
            'balance' => '',
            'reconciled' => '',
        ];

        $columns = [
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'bank', 'label' => __('Bank')],
            ['key' => 'module', 'label' => __('Module')],
            ['key' => 'entry', 'label' => __('Entry')],
            ['key' => 'reference', 'label' => __('Reference')],
            ['key' => 'description', 'label' => __('Description')],
            ['key' => 'credit', 'label' => __('Credit'), 'class' => 'text-end'],
            ['key' => 'debit', 'label' => __('Debit'), 'class' => 'text-end'],
            ['key' => 'balance', 'label' => __('Balance'), 'class' => 'text-end'],
            ['key' => 'reconciled', 'label' => __('Reconciled')],
        ];

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Bank Transactions Report'),
                'subtitle' => __('Generated on ') . now()->format('d M Y, h:i A'),
                'paperOrientation' => 'landscape',
                'columns' => $columns,
                'rows' => $rows,
            ],
            'bank-transactions-' . now()->format('Y-m-d')
        );
    }
}
