<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\BankAccount;
use App\Models\Account\Expense;
use App\Models\Account\Revenue;
use App\Models\Client;
use App\Services\Account\AccountExportService;
use App\Services\Account\AccountingTags;
use App\Support\DateRangePreset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ProfitLossController extends Controller
{
    public function __construct(protected AccountExportService $exportService)
    {
        //
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-profit-loss'), 403);

        $filters = $this->validatedFilters($request);
        $built = $this->buildReport($filters);

        $creatorId = creatorId();

        return view('admin.account.profit-loss.index', [
            'fromDate' => $filters['from_date'],
            'toDate' => $filters['to_date'],
            'statusMode' => $filters['status_mode'],
            'moduleTag' => $filters['module_tag'],
            'bankAccountId' => $filters['bank_account_id'],
            'clientId' => $filters['client_id'],
            'search' => $filters['search'],
            'moduleOptions' => AccountingTags::modules(),
            'bankAccounts' => BankAccount::query()
                ->where('created_by', $creatorId)
                ->where('is_active', true)
                ->select('id', 'account_name', 'bank_name')
                ->orderBy('account_name')
                ->get(),
            'clients' => Client::query()
                ->select('id', 'client_name')
                ->orderBy('client_name')
                ->limit(2000)
                ->get(),
            'totals' => $built['totals'],
            'moduleBreakdown' => $built['module_breakdown'],
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account-profit-loss'), 403);

        $request->validate(['format' => 'required|in:pdf,csv,xlsx']);
        $filters = $this->validatedFilters($request);
        $built = $this->buildReport($filters);

        $rows = [
            [
                'metric' => __('Revenue'),
                'amount' => $built['totals']['total_revenue'],
            ],
            [
                'metric' => __('Expense'),
                'amount' => $built['totals']['total_expense'],
            ],
            [
                'metric' => __('Net profit (Revenue - Expense)'),
                'amount' => $built['totals']['net_profit'],
            ],
        ];

        foreach ($built['module_breakdown'] as $row) {
            $rows[] = [
                'metric' => $row['module'] . ' — ' . __('Revenue'),
                'amount' => $row['revenue'],
            ];
            $rows[] = [
                'metric' => $row['module'] . ' — ' . __('Expense'),
                'amount' => $row['expense'],
            ];
            $rows[] = [
                'metric' => $row['module'] . ' — ' . __('Net'),
                'amount' => $row['net'],
            ];
        }

        return $this->exportService->exportByFormat(
            $request->input('format'),
            'admin.account.profit-loss.exports.profit-loss',
            [
                'pageTitle' => __('Profit & Loss'),
                'fromDate' => $filters['from_date'],
                'toDate' => $filters['to_date'],
                'statusMode' => $filters['status_mode'],
                'search' => $filters['search'],
                'filters' => [
                    'from_date' => $filters['from_date'],
                    'to_date' => $filters['to_date'],
                    'status_mode' => $filters['status_mode'],
                    'module_tag' => $filters['module_tag'],
                    'bank_account_id' => $filters['bank_account_id'],
                    'client_id' => $filters['client_id'],
                    'search' => $filters['search'],
                ],
                'rows' => $rows,
                'totals' => [
                    'total_revenue' => $built['totals']['total_revenue'],
                    'total_expense' => $built['totals']['total_expense'],
                    'net_profit' => $built['totals']['net_profit'],
                ],
            ],
            'profit-loss-' . $filters['from_date'] . '-to-' . $filters['to_date']
        );
    }

    /**
     * @return array{
     *   from_date:string,
     *   to_date:string,
     *   status_mode:string,
     *   module_tag:string,
     *   bank_account_id:?int,
     *   client_id:?int,
     *   search:?string
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'status_mode' => 'nullable|string|in:posted,all',
            'module_tag' => 'nullable|string|in:all,CHIT,LOAN,FD,TRANSFER,OTHER',
            'bank_account_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
        ]);

        [$from, $to] = DateRangePreset::applyToRequest($request);

        return [
            'from_date' => $from,
            'to_date' => $to,
            'status_mode' => $validated['status_mode'] ?? 'posted',
            'module_tag' => $validated['module_tag'] ?? 'all',
            'bank_account_id' => isset($validated['bank_account_id']) ? (int) $validated['bank_account_id'] : null,
            'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            'search' => $validated['search'] ?? null,
        ];
    }

    /**
     * @param  array{
     *   from_date:string,
     *   to_date:string,
     *   status_mode:string,
     *   module_tag:string,
     *   bank_account_id:?int,
     *   client_id:?int,
     *   search:?string
     * }  $filters
     * @return array{totals: array, module_breakdown: array<int, array>}
     */
    private function buildReport(array $filters): array
    {
        $creatorId = creatorId();

        $revenuesQuery = Revenue::query()
            ->where('created_by', $creatorId);
        if (! empty($filters['from_date'])) {
            $revenuesQuery->whereDate('revenue_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $revenuesQuery->whereDate('revenue_date', '<=', $filters['to_date']);
        }

        $expensesQuery = Expense::query()
            ->where('created_by', $creatorId);
        if (! empty($filters['from_date'])) {
            $expensesQuery->whereDate('expense_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $expensesQuery->whereDate('expense_date', '<=', $filters['to_date']);
        }

        if ($filters['status_mode'] === 'posted') {
            $revenuesQuery->where('status', 'posted');
            $expensesQuery->where('status', 'posted');
        }

        $this->applyCommonFilters($revenuesQuery, $filters, 'revenue');
        $this->applyCommonFilters($expensesQuery, $filters, 'expense');

        $totalRevenue = (float) ($revenuesQuery->sum('amount') ?? 0);
        $totalExpense = (float) ($expensesQuery->sum('amount') ?? 0);

        $modules = AccountingTags::modules();
        $moduleBreakdown = [];

        foreach ($modules as $module) {
            // When filtering by one module, still show all cards but only the selected has data.
            if ($filters['module_tag'] !== 'all' && $filters['module_tag'] !== $module) {
                $moduleBreakdown[] = [
                    'module' => $module,
                    'revenue' => 0.0,
                    'expense' => 0.0,
                    'net' => 0.0,
                ];
                continue;
            }

            $rev = (clone $revenuesQuery);
            $exp = (clone $expensesQuery);

            if ($module === AccountingTags::MODULE_OTHER) {
                $rev->where(function ($q) {
                    $q->where('module_tag', AccountingTags::MODULE_OTHER)
                        ->orWhereNull('module_tag')
                        ->orWhere('module_tag', '');
                });
                $exp->where(function ($q) {
                    $q->where('module_tag', AccountingTags::MODULE_OTHER)
                        ->orWhereNull('module_tag')
                        ->orWhere('module_tag', '');
                });
            } else {
                $rev->where('module_tag', $module);
                $exp->where('module_tag', $module);
            }

            $revAmt = (float) $rev->sum('amount');
            $expAmt = (float) $exp->sum('amount');

            $moduleBreakdown[] = [
                'module' => $module,
                'revenue' => $revAmt,
                'expense' => $expAmt,
                'net' => $revAmt - $expAmt,
            ];
        }

        return [
            'totals' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'net_profit' => $totalRevenue - $totalExpense,
            ],
            'module_breakdown' => $moduleBreakdown,
        ];
    }

    private function applyCommonFilters(Builder $query, array $filters, string $type): void
    {
        if (($filters['module_tag'] ?? 'all') !== 'all') {
            $module = $filters['module_tag'];
            if ($module === AccountingTags::MODULE_OTHER) {
                $query->where(function ($q) {
                    $q->where('module_tag', AccountingTags::MODULE_OTHER)
                        ->orWhereNull('module_tag')
                        ->orWhere('module_tag', '');
                });
            } else {
                $query->where('module_tag', $module);
            }
        }

        if (! empty($filters['bank_account_id'])) {
            $query->where('bank_account_id', $filters['bank_account_id']);
        }

        if (! empty($filters['client_id'])) {
            $client = Client::query()->select('id', 'client_name')->find($filters['client_id']);
            $patterns = array_values(array_filter([
                $client?->client_name ? trim((string) $client->client_name) : null,
                'client #' . $filters['client_id'],
                'Client #' . $filters['client_id'],
                '#' . $filters['client_id'],
            ]));

            if (empty($patterns)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($patterns) {
                    foreach ($patterns as $pattern) {
                        $q->orWhere('description', 'like', '%' . $pattern . '%');
                    }
                });
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $numberColumn = $type === 'revenue' ? 'revenue_number' : 'expense_number';
            $query->where(function ($q) use ($search, $numberColumn) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere($numberColumn, 'like', '%' . $search . '%');
            });
        }
    }
}
