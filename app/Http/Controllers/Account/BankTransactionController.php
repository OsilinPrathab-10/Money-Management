<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankTransaction;
use App\Models\Account\BankAccount;
use App\Services\Account\AccountExportService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        if(Auth::user()->can('manage-bank-transactions')){
            $query = BankTransaction::with(['bankAccount'])
                ->where('created_by', creatorId());
            // Apply filters
            if ($request->bank_account_id) {
                $query->where('bank_account_id', $request->bank_account_id);
            }
            if ($request->transaction_type) {
                $query->where('transaction_type', $request->transaction_type);
            }
            if ($request->search) {
                $query->where('reference_number', 'like', '%' . $request->search . '%')
                     ->orWhere('description', 'like', '%' . $request->search . '%');
            }

            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            if ($sortField) {
                $query->orderBy($sortField, $sortDirection);
            }

            $transactions = $query->paginate($request->get('per_page', 20));
            $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

            return view('admin.account.bank-transactions.index', [
                'transactions' => $transactions,
                'bankAccounts' => $bankAccounts,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function markReconciled($id)
    {
        if(Auth::user()->can('reconcile-bank-transactions')){
            $transaction = BankTransaction::where('id', $id)
                ->where('created_by', creatorId())
                ->first();

            if($transaction && $transaction->reconciliation_status === 'unreconciled') {
                $transaction->reconciliation_status = 'reconciled';
                $transaction->save();

                return back()->with('success', __('Transaction marked as reconciled'));
            }

            return back()->with('error', __('Transaction not found or already reconciled'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
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
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = BankTransaction::with(['bankAccount'])
            ->where('created_by', creatorId());

        if (!empty($validated['bank_account_id'])) {
            $query->where('bank_account_id', (int) $validated['bank_account_id']);
        }
        if (!empty($validated['transaction_type'])) {
            $query->where('transaction_type', $validated['transaction_type']);
        }
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('reference_number', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('description', 'like', '%' . $validated['search'] . '%');
            });
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $transactions = $query->get();
        $totalAmount = (float) ($transactions->sum('amount') ?? 0);

        $rows = $transactions->map(function ($t) {
            return [
                'date' => $t->transaction_date?->format('Y-m-d') ?? '',
                'bank' => $t->bankAccount?->account_name ?? '—',
                'type' => $t->transaction_type ?? '—',
                'reference' => $t->reference_number ?? '—',
                'description' => (string) ($t->description ?? ''),
                'amount' => '₹' . number_format((float) ($t->amount ?? 0), 2),
                'reconciled' => $t->reconciliation_status ?? '—',
            ];
        })->values()->all();

        $rows[] = [
            'date' => '',
            'bank' => '',
            'type' => __('TOTAL'),
            'reference' => '',
            'description' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
            'reconciled' => '',
        ];

        $columns = [
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'bank', 'label' => __('Bank')],
            ['key' => 'type', 'label' => __('Type')],
            ['key' => 'reference', 'label' => __('Reference')],
            ['key' => 'description', 'label' => __('Description')],
            ['key' => 'amount', 'label' => __('Amount'), 'class' => 'text-end'],
            ['key' => 'reconciled', 'label' => __('Reconciled')],
        ];

        $subtitleParts = [];
        if (!empty($validated['bank_account_id'])) {
            $subtitleParts[] = 'Bank Account ID: ' . $validated['bank_account_id'];
        }
        if (!empty($validated['transaction_type'])) {
            $subtitleParts[] = 'Type: ' . $validated['transaction_type'];
        }
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Bank transactions'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'bank-transactions-export'
        );
    }
}

