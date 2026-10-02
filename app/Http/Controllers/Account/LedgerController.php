<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankAccount;
use App\Models\Client;
use App\Services\Account\AccountExportService;
use App\Services\Account\AccountingTags;
use App\Services\Account\OperationalLedgerService;
use App\Support\DateRangePreset;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LedgerController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100, 250, 500];

    public function __construct(
        protected AccountExportService $exportService,
        protected OperationalLedgerService $operationalLedger
    ) {
        //
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-ledger'), 403);

        $creatorId = creatorId();

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status' => 'nullable|string|in:all,draft,approved,posted',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'entry_tag' => 'nullable|string|max:40',
            'bank_account_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'source' => 'nullable|string|in:all,Bank,Revenue,Expense,Dividend',
            'search' => 'nullable|string|max:255',
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $status = $validated['status'] ?? 'posted';
        $moduleTag = $validated['module_tag'] ?? 'all';
        $entryTag = $validated['entry_tag'] ?? null;
        $bankAccountId = isset($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null;
        $clientId = isset($validated['client_id']) ? (int) $validated['client_id'] : null;
        $source = $validated['source'] ?? 'all';
        $search = $validated['search'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $built = $this->operationalLedger->build(
            $creatorId,
            $fromDate,
            $toDate,
            $moduleTag,
            $entryTag,
            $bankAccountId,
            $search,
            $status,
            $clientId,
            $source
        );

        $page = max(1, (int) $request->get('page', 1));
        $slice = $built['rows']->forPage($page, $perPage)->values();
        $ledger = new LengthAwarePaginator(
            $slice,
            $built['rows']->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $bankAccounts = BankAccount::query()
            ->where('created_by', $creatorId)
            ->where('is_active', true)
            ->select('id', 'account_name', 'bank_name')
            ->orderBy('account_name')
            ->get();

        $clients = Client::query()
            ->select('id', 'client_name')
            ->orderBy('client_name')
            ->limit(2000)
            ->get();

        return view('admin.account.ledger.index', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'status' => $status,
            'moduleTag' => $moduleTag,
            'entryTag' => $entryTag,
            'bankAccountId' => $bankAccountId,
            'clientId' => $clientId,
            'source' => $source,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'bankAccounts' => $bankAccounts,
            'clients' => $clients,
            'moduleOptions' => AccountingTags::modules(),
            'entryOptions' => AccountingTags::entries(),
            'sourceOptions' => ['Bank', 'Revenue', 'Expense', 'Dividend'],
            'ledger' => $ledger,
            'totals' => [
                'total_debit' => $built['totals']['debit'],
                'total_credit' => $built['totals']['credit'],
                'bank_debit' => $built['totals']['bank_debit'],
                'bank_credit' => $built['totals']['bank_credit'],
                'revenue' => $built['totals']['revenue'],
                'expense' => $built['totals']['expense'],
                'chit_fees' => $built['totals']['chit_fees'],
                'loan_fees' => $built['totals']['loan_fees'],
                'fd_fees' => $built['totals']['fd_fees'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-ledger'), 403);

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status' => 'nullable|string|in:all,draft,approved,posted',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'entry_tag' => 'nullable|string|max:40',
            'bank_account_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'source' => 'nullable|string|in:all,Bank,Revenue,Expense,Dividend',
            'search' => 'nullable|string|max:255',
        ]);

        $creatorId = creatorId();
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $status = $validated['status'] ?? 'posted';
        $moduleTag = $validated['module_tag'] ?? 'all';
        $entryTag = $validated['entry_tag'] ?? null;
        $bankAccountId = isset($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null;
        $clientId = isset($validated['client_id']) ? (int) $validated['client_id'] : null;
        $source = $validated['source'] ?? 'all';
        $search = $validated['search'] ?? null;

        $built = $this->operationalLedger->build(
            $creatorId,
            $fromDate,
            $toDate,
            $moduleTag,
            $entryTag,
            $bankAccountId,
            $search,
            $status,
            $clientId,
            $source
        );

        $rows = $built['rows']->map(function ($row) {
            $bankLabel = $row['bank'] ?? '—';
            if (! empty($row['bank_name'])) {
                $bankLabel .= ' (' . $row['bank_name'] . ')';
            }

            return [
                'date' => $row['date'] ?? '—',
                'module' => $row['module_tag'] ?? '—',
                'entry' => $row['entry_tag'] ?? '—',
                'bank' => $bankLabel,
                'ref' => $row['ref'] ?? '—',
                'source' => $row['source'] ?? '—',
                'debit' => (float) ($row['debit'] ?? 0),
                'credit' => (float) ($row['credit'] ?? 0),
                'description' => $row['description'] ?? '',
                'status' => $row['status'] ?? '—',
            ];
        })->all();

        return $this->exportService->exportByFormat(
            $validated['format'],
            'admin.account.ledger.exports.ledger',
            [
                'pageTitle' => __('Operational Ledger'),
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'status' => $status,
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'status' => $status,
                    'module_tag' => $moduleTag,
                    'entry_tag' => $entryTag,
                    'bank_account_id' => $bankAccountId,
                    'client_id' => $clientId,
                    'source' => $source,
                    'search' => $search,
                ],
                'rows' => $rows,
                'totals' => [
                    'total_debit' => $built['totals']['debit'],
                    'total_credit' => $built['totals']['credit'],
                ],
            ],
            'ledger-' . $fromDate . '-to-' . $toDate
        );
    }
}
