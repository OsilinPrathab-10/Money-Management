<?php

namespace App\Http\Controllers;

use App\Models\ChitCollection;
use App\Models\ChitScheme;
use App\Models\FixedDeposit;
use App\Models\FixedDepositApplication;
use App\Models\FixedDepositScheme;
use App\Models\FixedDepositTransaction;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Location;
use App\Support\DateRangePreset;
use App\Support\ReportExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleReportAnalyticsController extends Controller
{
    public function chitApplications(Request $request)
    {
        return $this->render($request, [
            'title' => 'Applications Report & Analytics',
            'subtitle' => 'Comprehensive overview of chit application statistics',
            'tableTitle' => 'Latest Applications',
            'entityLabel' => 'Applications',
            'filterRoute' => 'chit.reports.applications',
            'exportRoute' => 'chit.reports.applications.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'created_at',
            'amountColumn' => null,
            'statusOrder' => ['applied', 'approved', 'active', 'rejected', 'transferred'],
            'query' => GroupMember::query()->with(['client', 'group.scheme']),
            'locationRelation' => 'client',
            'schemeFilter' => fn (Builder $q, $id) => $q->whereHas('group', fn ($g) => $g->where('scheme_id', $id)),
            'schemes' => fn () => ChitScheme::orderBy('name')->get(['id', 'name']),
            'amountSort' => 'id',
            'mapRow' => function (GroupMember $row) {
                return [
                    $row->id,
                    $row->member_number ?: ('CHIT-' . $row->id),
                    $this->clientCell($row->client),
                    $row->group?->group_code ?? 'N/A',
                    $row->group?->scheme?->name ?? 'N/A',
                    ucfirst(str_replace('_', ' ', (string) $row->status)),
                    $row->status,
                ];
            },
            'columns' => ['S.No', 'Member No', 'Client Name', 'Group', 'Scheme', 'Status'],
            'exportMap' => fn (GroupMember $row) => [
                'Member No' => $row->member_number ?: ('CHIT-' . $row->id),
                'Client Name' => $row->client?->client_name ?? 'N/A',
                'Mobile' => $row->client?->client_phone ?? 'N/A',
                'Group' => $row->group?->group_code ?? 'N/A',
                'Scheme' => $row->group?->scheme?->name ?? 'N/A',
                'Status' => ucfirst((string) $row->status),
                'Applied On' => optional($row->created_at)->format('Y-m-d'),
            ],
            'filename' => 'chit_application_report',
            'stats' => function (Builder $base) {
                $counts = $this->statusCounts($base, 'status');

                return [
                    ['title' => 'Pending Applications', 'subtitle' => 'Awaiting review', 'value' => $counts['applied'] ?? 0, 'icon' => 'ri-time-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                    ['title' => 'Approved & Active', 'subtitle' => 'Successfully enrolled', 'value' => ($counts['approved'] ?? 0) + ($counts['active'] ?? 0), 'icon' => 'ri-check-double-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'Rejected Applications', 'subtitle' => 'Not approved', 'value' => $counts['rejected'] ?? 0, 'icon' => 'ri-close-line', 'bg' => '#fee3e3', 'color' => '#d93025'],
                    ['title' => 'Transferred', 'subtitle' => 'Moved to another group', 'value' => $counts['transferred'] ?? 0, 'icon' => 'ri-share-forward-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                ];
            },
        ]);
    }

    public function chitAccounts(Request $request)
    {
        return $this->render($request, [
            'title' => 'Chit Report & Analytics',
            'subtitle' => 'Comprehensive overview of chit portfolio and performance',
            'tableTitle' => 'Latest Chit Accounts',
            'entityLabel' => 'Chit Accounts',
            'filterRoute' => 'chit.reports.accounts',
            'exportRoute' => 'chit.reports.accounts.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'created_at',
            'statusOrder' => ['active', 'approved', 'completed', 'defaulted', 'frozen'],
            'query' => GroupMember::query()->with(['client', 'group.scheme'])->whereNotIn('status', ['rejected']),
            'locationRelation' => 'client',
            'schemeFilter' => fn (Builder $q, $id) => $q->whereHas('group', fn ($g) => $g->where('scheme_id', $id)),
            'schemes' => fn () => ChitScheme::orderBy('name')->get(['id', 'name']),
            'mapRow' => function (GroupMember $row) {
                return [
                    $row->id,
                    $this->clientCell($row->client),
                    $row->group?->group_code ?? 'N/A',
                    $row->group?->scheme?->name ?? 'N/A',
                    $row->member_number ?: 'N/A',
                    ucfirst(str_replace('_', ' ', (string) $row->status)),
                    $row->status,
                ];
            },
            'columns' => ['S.No', 'Client Name', 'Group', 'Scheme', 'Member No', 'Status'],
            'exportMap' => fn (GroupMember $row) => [
                'Client Name' => $row->client?->client_name ?? 'N/A',
                'Mobile' => $row->client?->client_phone ?? 'N/A',
                'Group' => $row->group?->group_code ?? 'N/A',
                'Scheme' => $row->group?->scheme?->name ?? 'N/A',
                'Member No' => $row->member_number ?: 'N/A',
                'Status' => ucfirst((string) $row->status),
            ],
            'filename' => 'chit_account_report',
            'stats' => function (Builder $base) {
                $counts = $this->statusCounts($base, 'status');

                return [
                    ['title' => 'Active Accounts', 'subtitle' => 'Currently running', 'value' => ($counts['active'] ?? 0) + ($counts['approved'] ?? 0), 'icon' => 'ri-check-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'Completed', 'subtitle' => 'Fully settled', 'value' => $counts['completed'] ?? 0, 'icon' => 'ri-checkbox-circle-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                    ['title' => 'Defaulted', 'subtitle' => 'Overdue accounts', 'value' => $counts['defaulted'] ?? 0, 'icon' => 'ri-error-warning-line', 'bg' => '#fee3e3', 'color' => '#d93025'],
                    ['title' => 'Frozen', 'subtitle' => 'Temporarily paused', 'value' => $counts['frozen'] ?? 0, 'icon' => 'ri-pause-circle-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                ];
            },
        ]);
    }

    public function chitInstallments(Request $request)
    {
        return $this->render($request, [
            'title' => 'Installment Report & Analytics',
            'subtitle' => 'Comprehensive overview of chit installment collection and performance',
            'tableTitle' => 'Latest Installments',
            'entityLabel' => 'Installments',
            'filterRoute' => 'chit.reports.installments-analytics',
            'exportRoute' => 'chit.reports.installments-analytics.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'due_date',
            'amountColumn' => 'amount',
            'paidAmountColumn' => 'paid_amount',
            'collectedLabel' => 'Paid',
            'outstandingLabel' => 'Outstanding',
            'statusOrder' => ['paid', 'pending', 'overdue', 'partial'],
            'query' => Installment::query()->with(['member.client', 'group.scheme']),
            'locationRelation' => 'member.client',
            'schemeFilter' => fn (Builder $q, $id) => $q->whereHas('group', fn ($g) => $g->where('scheme_id', $id)),
            'schemes' => fn () => ChitScheme::orderBy('name')->get(['id', 'name']),
            'mapRow' => function (Installment $row) {
                return [
                    $row->id,
                    $row->member?->client?->client_name ?? 'N/A',
                    $row->member?->client?->client_phone ?? 'N/A',
                    '₹' . number_format((float) $row->amount, 2),
                    '₹' . number_format((float) $row->paid_amount, 2),
                    optional($row->due_date)->format('d M Y') ?: 'N/A',
                    ucfirst(str_replace('_', ' ', (string) $row->status)),
                    $row->status,
                ];
            },
            'columns' => ['S.No', 'Customer Name', 'Contact', 'Installment Amount', 'Paid Amount', 'Due Date', 'Status'],
            'exportMap' => fn (Installment $row) => [
                'Customer Name' => $row->member?->client?->client_name ?? 'N/A',
                'Contact' => $row->member?->client?->client_phone ?? 'N/A',
                'Group' => $row->group?->group_code ?? 'N/A',
                'Amount' => number_format((float) $row->amount, 2),
                'Paid Amount' => number_format((float) $row->paid_amount, 2),
                'Due Date' => optional($row->due_date)->format('Y-m-d'),
                'Status' => ucfirst((string) $row->status),
            ],
            'filename' => 'chit_installment_report',
            'stats' => function (Builder $base) {
                $paid = (clone $base)->where('status', 'paid')->count();
                $pending = (clone $base)->where('status', 'pending')->count();
                $overdue = (clone $base)->where('status', 'overdue')->count();
                $partial = (clone $base)->where('status', 'partial')->count();

                return [
                    ['title' => 'Paid Installments', 'subtitle' => 'Successfully collected', 'value' => $paid, 'icon' => 'ri-check-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'Pending Installments', 'subtitle' => 'Yet to collect', 'value' => $pending, 'icon' => 'ri-time-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                    ['title' => 'Overdue Installments', 'subtitle' => 'Past due date', 'value' => $overdue, 'icon' => 'ri-error-warning-line', 'bg' => '#fee3e3', 'color' => '#d93025'],
                    ['title' => 'Partially Paid', 'subtitle' => 'Partial collection', 'value' => $partial, 'icon' => 'ri-pie-chart-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                ];
            },
        ]);
    }

    public function chitPayments(Request $request)
    {
        return $this->render($request, [
            'title' => 'Payment Report & Analytics',
            'subtitle' => 'Comprehensive overview of chit collection payments',
            'tableTitle' => 'Latest Payments',
            'entityLabel' => 'Payments',
            'filterRoute' => 'chit.reports.payments',
            'exportRoute' => 'chit.reports.payments.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'collected_at',
            'amountColumn' => 'amount',
            'collectedStatuses' => ['verified'],
            'collectedLabel' => 'Verified',
            'outstandingLabel' => 'Pending',
            'statusOrder' => ['verified', 'in_progress', 'rejected', 'pending'],
            'query' => ChitCollection::query()->with(['client', 'group.scheme', 'member']),
            'locationRelation' => 'client',
            'schemeFilter' => fn (Builder $q, $id) => $q->whereHas('group', fn ($g) => $g->where('scheme_id', $id)),
            'schemes' => fn () => ChitScheme::orderBy('name')->get(['id', 'name']),
            'mapRow' => function (ChitCollection $row) {
                $client = $row->client ?: $row->member?->client;

                return [
                    $row->id,
                    $this->clientCell($client),
                    $row->group?->group_code ?? 'N/A',
                    '₹' . number_format((float) $row->amount, 2),
                    strtoupper((string) ($row->payment_method ?: 'N/A')),
                    optional($row->collected_at)->format('d M Y') ?: 'N/A',
                    ucfirst(str_replace('_', ' ', (string) $row->status)),
                    $row->status,
                ];
            },
            'columns' => ['S.No', 'Client Name', 'Group', 'Amount', 'Payment Mode', 'Collected On', 'Status'],
            'exportMap' => fn (ChitCollection $row) => [
                'Client Name' => $row->client?->client_name ?? 'N/A',
                'Group' => $row->group?->group_code ?? 'N/A',
                'Amount' => number_format((float) $row->amount, 2),
                'Payment Mode' => $row->payment_method ?: 'N/A',
                'Collected On' => optional($row->collected_at)->format('Y-m-d'),
                'Status' => ucfirst((string) $row->status),
            ],
            'filename' => 'chit_payment_report',
            'stats' => function (Builder $base) {
                $counts = $this->statusCounts($base, 'status');
                $total = (clone $base)->sum('amount');

                return [
                    ['title' => 'Verified Payments', 'subtitle' => 'Confirmed collections', 'value' => $counts['verified'] ?? 0, 'icon' => 'ri-check-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'In Progress', 'subtitle' => 'Awaiting verification', 'value' => $counts['in_progress'] ?? 0, 'icon' => 'ri-loader-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                    ['title' => 'Rejected', 'subtitle' => 'Not accepted', 'value' => $counts['rejected'] ?? 0, 'icon' => 'ri-close-line', 'bg' => '#fee3e3', 'color' => '#d93025'],
                    ['title' => 'Total Collected', 'subtitle' => 'Filtered amount', 'value' => '₹' . number_format((float) $total, 2), 'icon' => 'ri-money-rupee-circle-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                ];
            },
        ]);
    }

    public function fdApplications(Request $request)
    {
        return $this->render($request, [
            'title' => 'Applications Report & Analytics',
            'subtitle' => 'Comprehensive overview of fixed deposit application statistics',
            'tableTitle' => 'Latest Applications',
            'entityLabel' => 'Applications',
            'filterRoute' => 'fd.reports.applications',
            'exportRoute' => 'fd.reports.applications.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'created_at',
            'amountColumn' => 'deposit_amount',
            'collectedStatuses' => ['approved', 'booked'],
            'collectedLabel' => 'Approved / Booked',
            'outstandingLabel' => 'Pending',
            'statusOrder' => ['pending', 'approved', 'booked', 'rejected'],
            'query' => FixedDepositApplication::query()->with(['client', 'scheme']),
            'locationRelation' => 'client',
            'schemeFilter' => fn (Builder $q, $id) => $q->where('scheme_id', $id),
            'schemes' => fn () => FixedDepositScheme::orderBy('name')->get(['id', 'name']),
            'mapRow' => function (FixedDepositApplication $row) {
                return [
                    $row->id,
                    $row->application_number ?: ('FDA-' . $row->id),
                    $this->clientCell($row->client),
                    $row->scheme?->name ?? 'N/A',
                    '₹' . number_format((float) $row->deposit_amount, 0),
                    ucfirst(str_replace('_', ' ', (string) $row->status)),
                    $row->status,
                ];
            },
            'columns' => ['S.No', 'Application Number', 'Client Name', 'Scheme', 'Deposit Amount', 'Status'],
            'exportMap' => fn (FixedDepositApplication $row) => [
                'Application Number' => $row->application_number,
                'Client Name' => $row->client?->client_name ?? 'N/A',
                'Scheme' => $row->scheme?->name ?? 'N/A',
                'Deposit Amount' => number_format((float) $row->deposit_amount, 2),
                'Status' => ucfirst((string) $row->status),
                'Applied On' => optional($row->created_at)->format('Y-m-d'),
            ],
            'filename' => 'fd_application_report',
            'stats' => function (Builder $base) {
                $counts = $this->statusCounts($base, 'status');

                return [
                    ['title' => 'Pending Applications', 'subtitle' => 'Awaiting review', 'value' => $counts['pending'] ?? 0, 'icon' => 'ri-time-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                    ['title' => 'Approved', 'subtitle' => 'Ready to book', 'value' => $counts['approved'] ?? 0, 'icon' => 'ri-check-double-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                    ['title' => 'Booked', 'subtitle' => 'Deposit created', 'value' => $counts['booked'] ?? 0, 'icon' => 'ri-checkbox-circle-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'Rejected', 'subtitle' => 'Not approved', 'value' => $counts['rejected'] ?? 0, 'icon' => 'ri-close-line', 'bg' => '#fee3e3', 'color' => '#d93025'],
                ];
            },
        ]);
    }

    public function fdDeposits(Request $request)
    {
        return $this->render($request, [
            'title' => 'FD Report & Analytics',
            'subtitle' => 'Comprehensive overview of fixed deposit portfolio and performance',
            'tableTitle' => 'Latest Deposits',
            'entityLabel' => 'Deposits',
            'filterRoute' => 'fd.reports.deposits',
            'exportRoute' => 'fd.reports.deposits.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'created_at',
            'amountColumn' => 'deposit_amount',
            'collectedStatuses' => ['closed', 'matured', 'premature_closed'],
            'collectedLabel' => 'Closed / Matured',
            'outstandingLabel' => 'Active',
            'statusOrder' => ['active', 'matured', 'closed', 'premature_closed', 'renewed'],
            'query' => FixedDeposit::query()->with(['client', 'scheme']),
            'locationRelation' => 'client',
            'schemeFilter' => fn (Builder $q, $id) => $q->where('scheme_id', $id),
            'schemes' => fn () => FixedDepositScheme::orderBy('name')->get(['id', 'name']),
            'mapRow' => function (FixedDeposit $row) {
                return [
                    $row->id,
                    $row->fd_number ?: ('FD-' . $row->id),
                    $this->clientCell($row->client),
                    $row->scheme?->name ?? 'N/A',
                    '₹' . number_format((float) $row->deposit_amount, 0),
                    ucfirst(str_replace('_', ' ', (string) $row->status)),
                    $row->status,
                ];
            },
            'columns' => ['S.No', 'FD Number', 'Client Name', 'Scheme', 'Deposit Amount', 'Status'],
            'exportMap' => fn (FixedDeposit $row) => [
                'FD Number' => $row->fd_number,
                'Client Name' => $row->client?->client_name ?? 'N/A',
                'Scheme' => $row->scheme?->name ?? 'N/A',
                'Deposit Amount' => number_format((float) $row->deposit_amount, 2),
                'Maturity Amount' => number_format((float) $row->maturity_amount, 2),
                'Status' => ucfirst(str_replace('_', ' ', (string) $row->status)),
            ],
            'filename' => 'fd_deposit_report',
            'stats' => function (Builder $base) {
                $counts = $this->statusCounts($base, 'status');

                return [
                    ['title' => 'Active Deposits', 'subtitle' => 'Currently running', 'value' => $counts['active'] ?? 0, 'icon' => 'ri-check-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'Matured', 'subtitle' => 'Reached maturity', 'value' => $counts['matured'] ?? 0, 'icon' => 'ri-calendar-check-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                    ['title' => 'Closed', 'subtitle' => 'Fully closed', 'value' => ($counts['closed'] ?? 0) + ($counts['premature_closed'] ?? 0), 'icon' => 'ri-lock-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                    ['title' => 'Renewed', 'subtitle' => 'Rolled to new FD', 'value' => $counts['renewed'] ?? 0, 'icon' => 'ri-refresh-line', 'bg' => '#e3e7ff', 'color' => '#4b49ac'],
                ];
            },
        ]);
    }

    public function fdPayments(Request $request)
    {
        return $this->render($request, [
            'title' => 'Payment Report & Analytics',
            'subtitle' => 'Comprehensive overview of fixed deposit payments and interest',
            'tableTitle' => 'Latest Payments',
            'entityLabel' => 'Payments',
            'filterRoute' => 'fd.reports.payments',
            'exportRoute' => 'fd.reports.payments.export',
            'schemeLabel' => 'Scheme',
            'dateColumn' => 'created_at',
            'amountColumn' => 'amount',
            'paidAmountColumn' => 'interest_amount',
            'outstandingAmountColumn' => 'principal_amount',
            'collectedLabel' => 'Interest',
            'outstandingLabel' => 'Principal',
            'statusOrder' => [],
            'statusColumn' => 'transaction_type',
            'query' => FixedDepositTransaction::query()->with(['fixedDeposit.client', 'fixedDeposit.scheme']),
            'locationRelation' => 'fixedDeposit.client',
            'schemeFilter' => fn (Builder $q, $id) => $q->whereHas('fixedDeposit', fn ($d) => $d->where('scheme_id', $id)),
            'schemes' => fn () => FixedDepositScheme::orderBy('name')->get(['id', 'name']),
            'mapRow' => function (FixedDepositTransaction $row) {
                return [
                    $row->id,
                    $this->clientCell($row->fixedDeposit?->client),
                    $row->fixedDeposit?->fd_number ?? 'N/A',
                    ucfirst(str_replace('_', ' ', (string) $row->transaction_type)),
                    '₹' . number_format((float) $row->amount, 2),
                    strtoupper((string) ($row->payment_mode ?: 'N/A')),
                    optional($row->created_at)->format('d M Y') ?: 'N/A',
                    $row->transaction_type,
                ];
            },
            'columns' => ['S.No', 'Client Name', 'FD Number', 'Type', 'Amount', 'Payment Mode', 'Date'],
            'exportMap' => fn (FixedDepositTransaction $row) => [
                'Client Name' => $row->fixedDeposit?->client?->client_name ?? 'N/A',
                'FD Number' => $row->fixedDeposit?->fd_number ?? 'N/A',
                'Type' => $row->transaction_type,
                'Amount' => number_format((float) $row->amount, 2),
                'Payment Mode' => $row->payment_mode ?: 'N/A',
                'Date' => optional($row->created_at)->format('Y-m-d'),
            ],
            'filename' => 'fd_payment_report',
            'stats' => function (Builder $base) {
                $total = (clone $base)->sum('amount');
                $interest = (clone $base)->sum('interest_amount');
                $count = (clone $base)->count();

                return [
                    ['title' => 'Transactions', 'subtitle' => 'Filtered count', 'value' => $count, 'icon' => 'ri-exchange-line', 'bg' => '#e3f0ff', 'color' => '#185adb'],
                    ['title' => 'Total Amount', 'subtitle' => 'Filtered amount', 'value' => '₹' . number_format((float) $total, 2), 'icon' => 'ri-money-rupee-circle-line', 'bg' => '#e2f4ea', 'color' => '#1e8449'],
                    ['title' => 'Interest Amount', 'subtitle' => 'Interest portion', 'value' => '₹' . number_format((float) $interest, 2), 'icon' => 'ri-percent-line', 'bg' => '#fff5d7', 'color' => '#d98500'],
                    ['title' => 'Average Ticket', 'subtitle' => 'Per transaction', 'value' => '₹' . number_format($count ? $total / $count : 0, 2), 'icon' => 'ri-bar-chart-line', 'bg' => '#e3e7ff', 'color' => '#4b49ac'],
                ];
            },
        ]);
    }

    protected function render(Request $request, array $cfg)
    {
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filterStatus = $request->input('status', 'all');
        $sortOption = $request->input('sort', 'newest');
        $dateColumn = $cfg['dateColumn'];
        $amountColumn = $cfg['amountColumn'] ?? null;
        $statusColumn = $cfg['statusColumn'] ?? 'status';

        $fresh = $cfg['query'];
        $base = clone $fresh;
        $this->applyCommonFilters($base, $request, $cfg, false);

        $stats = is_callable($cfg['stats']) ? $cfg['stats']($base) : [];
        $byStatus = $this->chartStatus(clone $base, $statusColumn, $cfg['statusOrder'] ?? []);
        $perMonth = $this->chartMonths(clone $fresh, $dateColumn, $cfg);
        [$collectedAmount, $outstandingAmount] = $this->amountSplit(clone $base, $cfg);
        $showAmountChart = ! empty($amountColumn);
        $entityLabel = $cfg['entityLabel'] ?? 'Records';

        $tableQuery = clone $fresh;
        $this->applyCommonFilters($tableQuery, $request, $cfg, true);
        $this->applySort($tableQuery, $sortOption, $dateColumn, $amountColumn);

        $isExport = $request->boolean('_export') || str_ends_with((string) $request->route()?->getName(), '.export');
        if ($isExport) {
            $rows = $tableQuery->get()->map($cfg['exportMap']);

            return $this->exportData($rows, $cfg['filename'], $request->get('format', 'csv'), $cfg['title']);
        }

        $records = $tableQuery->paginate(15)->withQueryString();
        $tableRows = $records->getCollection()->values()->map(function ($row, $index) use ($cfg, $records) {
            $cells = ($cfg['mapRow'])($row);
            $status = array_pop($cells);

            return [
                'sno' => $records->firstItem() + $index,
                'cells' => array_slice($cells, 1),
                'status' => $status,
                'status_label' => Str::title(str_replace('_', ' ', (string) $status)),
            ];
        });

        $availableStatuses = collect($byStatus)->map(fn ($item) => [
            'value' => $item['status'],
            'label' => $item['label'],
        ]);

        return view('admin.report-analytics.module', [
            'pageTitle' => $cfg['title'],
            'pageSubtitle' => $cfg['subtitle'],
            'stats' => $stats,
            'pieTitle' => $entityLabel . ' by Status',
            'statsTitle' => $entityLabel . ' Statistics',
            'byStatus' => $byStatus,
            'trendTitle' => $entityLabel . ' Trend (Last 12 Months)',
            'perMonth' => $perMonth,
            'showAmountChart' => $showAmountChart,
            'collectedAmount' => $collectedAmount,
            'outstandingAmount' => $outstandingAmount,
            'collectedLabel' => $cfg['collectedLabel'] ?? 'Paid',
            'outstandingLabel' => $cfg['outstandingLabel'] ?? 'Outstanding',
            'countSeriesName' => $entityLabel,
            'tableTitle' => $cfg['tableTitle'],
            'columns' => $cfg['columns'],
            'records' => $records,
            'tableRows' => $tableRows,
            'availableStatuses' => $availableStatuses,
            'filterStatus' => $filterStatus,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'sortOption' => $sortOption,
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'schemes' => ($cfg['schemes'])(),
            'schemeLabel' => $cfg['schemeLabel'],
            'filterRoute' => $cfg['filterRoute'],
            'exportRoute' => $cfg['exportRoute'],
            'chartId' => Str::slug($cfg['filename']),
        ]);
    }

    protected function clientCell($client): string
    {
        $name = is_object($client) ? ($client->client_name ?? 'N/A') : 'N/A';
        $phone = is_object($client) ? (string) ($client->client_phone ?? '') : '';

        return $name . '||' . $phone;
    }

    protected function amountSplit(Builder $base, array $cfg): array
    {
        $col = $cfg['amountColumn'] ?? null;
        $paidCol = $cfg['paidAmountColumn'] ?? null;
        $outstandingCol = $cfg['outstandingAmountColumn'] ?? null;
        $statusCol = $cfg['statusColumn'] ?? 'status';
        $collectedStatuses = $cfg['collectedStatuses'] ?? [];

        if (! $col && ! $paidCol) {
            return [0.0, 0.0];
        }

        if ($paidCol && $outstandingCol) {
            return [
                (float) (clone $base)->sum($paidCol),
                (float) (clone $base)->sum($outstandingCol),
            ];
        }

        $total = $col ? (float) (clone $base)->sum($col) : 0.0;

        if ($paidCol) {
            $collected = (float) (clone $base)->sum($paidCol);

            return [$collected, max(0.0, $total - $collected)];
        }

        if ($col && $collectedStatuses) {
            $collected = (float) (clone $base)->whereIn($statusCol, $collectedStatuses)->sum($col);

            return [$collected, max(0.0, $total - $collected)];
        }

        return [$total, 0.0];
    }

    protected function applyCommonFilters(Builder $query, Request $request, array $cfg, bool $withStatus): void
    {
        $locationId = $request->input('location_id');
        $schemeId = $request->input('scheme_id');
        $filterStatus = $request->input('status', 'all');
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $dateColumn = $cfg['dateColumn'];
        $statusColumn = $cfg['statusColumn'] ?? 'status';

        if ($locationId && ! empty($cfg['locationRelation'])) {
            $query->whereHas($cfg['locationRelation'], fn ($q) => $q->where('location_id', $locationId));
        }
        if ($schemeId && isset($cfg['schemeFilter'])) {
            ($cfg['schemeFilter'])($query, $schemeId);
        }
        if ($withStatus && $filterStatus && $filterStatus !== 'all') {
            $query->where($statusColumn, $filterStatus);
        }
        if ($fromDate) {
            $query->where($dateColumn, '>=', Carbon::parse($fromDate)->startOfDay());
        }
        if ($toDate) {
            $query->where($dateColumn, '<=', Carbon::parse($toDate)->endOfDay());
        }
    }

    protected function applySort(Builder $query, string $sort, string $dateColumn, ?string $amountColumn): void
    {
        match ($sort) {
            'oldest' => $query->orderBy($dateColumn, 'asc'),
            'amount_high' => $query->orderBy($amountColumn ?: 'id', 'desc'),
            'amount_low' => $query->orderBy($amountColumn ?: 'id', 'asc'),
            'status_asc' => $query->orderBy($query->getModel()->getTable() === 'fixed_deposit_transactions' ? 'transaction_type' : 'status')->orderBy($dateColumn, 'desc'),
            'status_desc' => $query->orderBy($query->getModel()->getTable() === 'fixed_deposit_transactions' ? 'transaction_type' : 'status', 'desc')->orderBy($dateColumn, 'desc'),
            default => $query->orderBy($dateColumn, 'desc'),
        };
    }

    protected function statusCounts(Builder $base, string $column): array
    {
        $col = '`' . str_replace('`', '', $column) . '`';

        return (clone $base)
            ->selectRaw("LOWER(TRIM({$col})) as status_key, count(*) as count")
            ->groupByRaw("LOWER(TRIM({$col}))")
            ->pluck('count', 'status_key')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    protected function chartStatus(Builder $base, string $column, array $order): array
    {
        $counts = $this->statusCounts($base, $column);
        $items = [];
        foreach ($order ?: array_keys($counts) as $status) {
            $count = $counts[$status] ?? 0;
            if ($count <= 0) {
                continue;
            }
            $items[] = [
                'status' => $status,
                'label' => Str::title(str_replace('_', ' ', $status)),
                'count' => $count,
            ];
        }
        foreach ($counts as $status => $count) {
            if ($order && in_array($status, $order, true)) {
                continue;
            }
            if ($count > 0) {
                $items[] = [
                    'status' => $status,
                    'label' => Str::title(str_replace('_', ' ', (string) $status)),
                    'count' => $count,
                ];
            }
        }

        return $items;
    }

    protected function chartMonths(Builder $query, string $dateColumn, array $cfg): array
    {
        $col = '`' . str_replace('`', '', $dateColumn) . '`';
        $amountColumn = $cfg['amountColumn'] ?? null;
        $from = Carbon::now()->subMonths(11)->startOfMonth();
        $selects = [
            DB::raw("DATE_FORMAT({$col}, '%Y-%m') as month"),
            DB::raw('count(*) as count'),
        ];
        if ($amountColumn) {
            $amt = '`' . str_replace('`', '', $amountColumn) . '`';
            $selects[] = DB::raw("COALESCE(SUM({$amt}), 0) as total_amount");
        }

        $raw = (clone $query)
            ->select($selects)
            ->whereNotNull($dateColumn)
            ->where($dateColumn, '>=', $from)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        return collect(range(0, 11))->map(function ($i) use ($raw) {
            $date = Carbon::now()->subMonths(11 - $i)->startOfMonth();
            $key = $date->format('Y-m');

            return [
                'month' => $date->format('M Y'),
                'count' => (int) ($raw[$key]->count ?? 0),
                'total_amount' => (float) ($raw[$key]->total_amount ?? 0),
            ];
        })->all();
    }

    protected function exportData($data, string $filename, string $format, string $title)
    {
        return ReportExporter::download(
            $data,
            $format,
            $filename,
            $title,
            request()->except(['format', 'page', '_export'])
        );
    }

}
