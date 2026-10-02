<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositRenewal;
use App\Models\FixedDepositScheme;
use App\Models\FixedDepositTransaction;
use App\Models\WalletTransaction;
use App\Support\DateRangePreset;
use App\Support\ReportExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FixedDepositReportsController extends Controller
{
    public const REPORTS = [
        'register' => 'Fixed Deposit Register',
        'active' => 'Active Deposits',
        'matured' => 'Matured Deposits',
        'closed' => 'Closed Deposits',
        'premature' => 'Premature Withdrawals',
        'renewals' => 'Renewal Report',
        'interest_earned' => 'Interest Earned',
        'interest_payable' => 'Interest Payable',
        'interest_summary' => 'Interest Summary',
        'maturity_today' => "Today's Maturity",
        'maturity_upcoming' => 'Upcoming Maturity',
        'maturity_overdue' => 'Overdue Maturity',
        'transactions' => 'FD Transaction History',
        'wallet_credits' => 'Wallet Credits from FD',
        'chit_adjustments' => 'Chit Adjustments from FD',
        'financial_summary' => 'Financial Summary',
    ];

    public function index()
    {
        $reports = self::REPORTS;

        return view('admin.fd.reports.index', compact('reports'));
    }

    public function show(Request $request, string $report)
    {
        if (!isset(self::REPORTS[$report])) {
            abort(404);
        }

        $data = $this->buildReport($request, $report);
        $title = self::REPORTS[$report];
        $schemes = FixedDepositScheme::orderBy('name')->get(['id', 'name']);
        $clients = Client::orderBy('client_name')->limit(300)->get(['id', 'client_name']);

        return view('admin.fd.reports.show', array_merge($data, compact('report', 'title', 'schemes', 'clients')));
    }

    public function export(Request $request, string $report)
    {
        if (!isset(self::REPORTS[$report])) {
            abort(404);
        }

        $format = $request->get('format', 'pdf');
        $data = $this->buildReport($request, $report);
        $title = self::REPORTS[$report];
        $headers = $data['headers'];
        $exportRows = collect($data['rows'])->map(function ($row) use ($headers) {
            $values = array_pad(array_values(is_array($row) ? $row : (array) $row), count($headers), '');

            return array_combine($headers, array_slice($values, 0, count($headers))) ?: [];
        });

        return ReportExporter::download(
            $exportRows,
            $format,
            str_replace(' ', '_', strtolower($title)),
            $title,
            $request->except(['format', 'page'])
        );
    }

    private function buildReport(Request $request, string $report): array
    {
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : null;
        $clientId = $request->client_id;
        $schemeId = $request->scheme_id;
        $status = $request->status;

        $fdQuery = FixedDeposit::with(['client.user', 'scheme']);

        if ($clientId) {
            $fdQuery->where('client_id', $clientId);
        }
        if ($schemeId) {
            $fdQuery->where('scheme_id', $schemeId);
        }
        if ($status) {
            $fdQuery->where('status', $status);
        }
        if ($from) {
            $fdQuery->whereDate('deposit_date', '>=', $from);
        }
        if ($to) {
            $fdQuery->whereDate('deposit_date', '<=', $to);
        }

        $today = Carbon::today();
        $headers = [];
        $rows = [];
        $summary = [];

        switch ($report) {
            case 'active':
                $items = (clone $fdQuery)->where('status', 'active')->latest()->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;

            case 'matured':
                $items = (clone $fdQuery)->where('status', 'matured')->latest()->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;

            case 'closed':
                $items = (clone $fdQuery)->whereIn('status', ['closed', 'premature_closed'])->latest()->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;

            case 'premature':
                $items = (clone $fdQuery)->where('status', 'premature_closed')->latest()->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Closure Amt', 'Closure Date', 'Status'];
                $rows = $items->map(fn ($fd) => [
                    $fd->fd_number,
                    $this->resolveClientName($fd->client),
                    $fd->scheme->name ?? '—',
                    number_format((float) $fd->deposit_amount, 2),
                    number_format((float) ($fd->closure_amount ?? 0), 2),
                    optional($fd->closure_date)->format('d M Y') ?? '—',
                    $fd->status_label,
                ])->all();
                break;

            case 'renewals':
                $items = FixedDepositRenewal::with(['oldDeposit.client.user', 'newDeposit'])
                    ->when($from, fn ($q) => $q->where('renewed_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('renewed_at', '<=', $to))
                    ->latest('renewed_at')
                    ->get();
                $headers = ['Old FD', 'New FD', 'Customer', 'Type', 'Principal', 'Interest', 'Renewed At'];
                $rows = $items->map(fn ($r) => [
                    $r->oldDeposit->fd_number ?? '—',
                    $r->newDeposit->fd_number ?? '—',
                    $this->resolveClientName($r->oldDeposit?->client),
                    $r->renewal_type,
                    number_format((float) $r->principal_carried, 2),
                    number_format((float) $r->interest_carried, 2),
                    optional($r->renewed_at)->format('d M Y H:i'),
                ])->all();
                break;

            case 'interest_payable':
                $items = (clone $fdQuery)->where('status', 'active')->get();
                $headers = ['FD Number', 'Customer', 'Principal', 'Rate %', 'Interest Payable', 'Maturity Date'];
                $rows = $items->map(fn ($fd) => [
                    $fd->fd_number,
                    $this->resolveClientName($fd->client),
                    number_format((float) $fd->deposit_amount, 2),
                    number_format((float) $fd->interest_rate, 2),
                    number_format((float) $fd->interest_amount, 2),
                    optional($fd->maturity_date)->format('d M Y'),
                ])->all();
                $summary['total_interest_payable'] = $items->sum('interest_amount');
                break;

            case 'interest_earned':
            case 'interest_summary':
                $items = (clone $fdQuery)->whereNotNull('maturity_processed_at')->get();
                $headers = ['FD Number', 'Customer', 'Principal', 'Interest Paid', 'Processed At', 'Status'];
                $rows = $items->map(fn ($fd) => [
                    $fd->fd_number,
                    $this->resolveClientName($fd->client),
                    number_format((float) $fd->deposit_amount, 2),
                    number_format((float) $fd->interest_amount, 2),
                    optional($fd->maturity_processed_at)->format('d M Y'),
                    $fd->status_label,
                ])->all();
                $summary['total_interest_paid'] = $items->sum('interest_amount');
                break;

            case 'maturity_today':
                $items = FixedDeposit::with(['client.user', 'scheme'])
                    ->where('status', 'active')
                    ->whereDate('maturity_date', $today)
                    ->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;

            case 'maturity_upcoming':
                $items = FixedDeposit::with(['client.user', 'scheme'])
                    ->where('status', 'active')
                    ->whereDate('maturity_date', '>', $today)
                    ->whereDate('maturity_date', '<=', $today->copy()->addDays(30))
                    ->orderBy('maturity_date')
                    ->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;

            case 'maturity_overdue':
                $items = FixedDeposit::with(['client.user', 'scheme'])
                    ->where('status', 'active')
                    ->whereDate('maturity_date', '<', $today)
                    ->orderBy('maturity_date')
                    ->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;

            case 'transactions':
                $items = FixedDepositTransaction::with(['fixedDeposit.client.user'])
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->latest()
                    ->get();
                $headers = ['Date', 'FD Number', 'Customer', 'Type', 'Amount', 'Interest', 'Penalty', 'Description'];
                $rows = $items->map(fn ($t) => [
                    $t->created_at?->format('d M Y H:i'),
                    $t->fixedDeposit->fd_number ?? '—',
                    $this->resolveClientName($t->fixedDeposit?->client),
                    $t->transaction_type,
                    number_format((float) $t->amount, 2),
                    number_format((float) $t->interest_amount, 2),
                    number_format((float) $t->penalty_amount, 2),
                    $t->description,
                ])->all();
                break;

            case 'wallet_credits':
                $items = WalletTransaction::with('client.user')
                    ->where('type', 'credit')
                    ->where('reference_type', 'fixed_deposit')
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->latest()
                    ->get();
                $headers = ['Date', 'Customer', 'Amount', 'Balance After', 'Description'];
                $rows = $items->map(fn ($t) => [
                    $t->created_at?->format('d M Y H:i'),
                    $this->resolveClientName($t->client),
                    number_format((float) $t->amount, 2),
                    number_format((float) $t->balance_after, 2),
                    $t->description,
                ])->all();
                break;

            case 'chit_adjustments':
                $items = FixedDepositTransaction::with(['fixedDeposit.client.user'])
                    ->where('transaction_type', 'chit_adjustment')
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->latest()
                    ->get();
                $headers = ['Date', 'FD Number', 'Customer', 'Amount', 'Description'];
                $rows = $items->map(fn ($t) => [
                    $t->created_at?->format('d M Y H:i'),
                    $t->fixedDeposit->fd_number ?? '—',
                    $this->resolveClientName($t->fixedDeposit?->client),
                    number_format((float) $t->amount, 2),
                    $t->description,
                ])->all();
                break;

            case 'financial_summary':
                $headers = ['Metric', 'Amount'];
                $rows = [
                    ['Total Deposits', number_format((float) FixedDeposit::sum('deposit_amount'), 2)],
                    ['Total Interest Liability (Active)', number_format((float) FixedDeposit::where('status', 'active')->sum('interest_amount'), 2)],
                    ['Total Interest Paid', number_format((float) FixedDeposit::whereNotNull('maturity_processed_at')->sum('interest_amount'), 2)],
                    ['Maturity Payments', number_format((float) FixedDepositTransaction::where('transaction_type', 'maturity')->sum('amount'), 2)],
                    ['Wallet Credits from FD', number_format((float) WalletTransaction::where('reference_type', 'fixed_deposit')->where('type', 'credit')->sum('amount'), 2)],
                    ['Chit Adjustments from FD', number_format((float) FixedDepositTransaction::where('transaction_type', 'chit_adjustment')->sum('amount'), 2)],
                ];
                break;

            case 'register':
            default:
                $items = (clone $fdQuery)->latest()->get();
                $headers = ['FD Number', 'Customer', 'Scheme', 'Deposit', 'Interest', 'Maturity Amt', 'Overdue', 'Maturity Date', 'Status'];
                $rows = $items->map(fn ($fd) => $this->fdRow($fd))->all();
                break;
        }

        return compact('headers', 'rows', 'summary', 'from', 'to', 'clientId', 'schemeId', 'status');
    }

    private function resolveClientName($client): string
    {
        if (!$client) return '—';
        return $client->client_name ?: ($client->user?->name ?? '—');
    }

    private function fdRow(FixedDeposit $fd): array
    {
        return [
            $fd->fd_number,
            $this->resolveClientName($fd->client),
            $fd->scheme->name ?? '—',
            number_format((float) $fd->deposit_amount, 2),
            number_format((float) $fd->interest_amount, 2),
            number_format((float) $fd->maturity_amount, 2),
            ($fd->status === 'active' && $fd->maturity_date && \Carbon\Carbon::parse($fd->maturity_date)->isPast()) ? number_format((float) $fd->maturity_amount, 2) : '0.00',
            optional($fd->maturity_date)->format('d M Y'),
            $fd->status_label,
        ];
    }

}
