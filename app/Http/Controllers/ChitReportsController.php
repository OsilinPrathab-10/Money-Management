<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Payout;
use App\Models\User;
use App\Support\DateRangePreset;
use App\Support\ReportExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChitReportsController extends Controller
{
    private const REPORTS = [
        'groups' => ['title' => 'Group Performance', 'icon' => 'ri-pages-line', 'description' => 'Group progress, occupancy, collections and settlements.'],
        'members' => ['title' => 'Member Status', 'icon' => 'ri-user-3-line', 'description' => 'Membership, approval, auction and payment status.'],
        'installments' => ['title' => 'Installment & Aging', 'icon' => 'ri-calendar-check-line', 'description' => 'Overdue, pending, partial, paid and upcoming installments.'],
        'collections' => ['title' => 'Collection Register', 'icon' => 'ri-hand-coin-line', 'description' => 'Collections by date, group, collector and payment mode.'],
        'auctions' => ['title' => 'Auction Analysis', 'icon' => 'ri-auction-line', 'description' => 'Auction schedule, bids, winners and discounts.'],
        'settlements' => ['title' => 'Settlement Register', 'icon' => 'ri-money-rupee-circle-line', 'description' => 'Payout status, recipient, commission and payment details.'],
    ];

    public function index()
    {
        return view('admin.chit.reports.index', ['reports' => self::REPORTS]);
    }

    public function show(Request $request, string $report)
    {
        DateRangePreset::applyToRequest($request, 'date_from', 'date_to');
        $definition = $this->definition($report);
        $query = $this->reportQuery($request, $report);
        $summary = $this->summary($report, clone $query);
        $records = $query->paginate(25)->withQueryString();
        $columns = $this->columns($report);
        $tableRows = $records->getCollection()->map(fn ($record) => $this->row($report, $record));

        return view('admin.chit.reports.show', array_merge(
            $this->filterOptions($report),
            compact('report', 'definition', 'records', 'summary', 'columns', 'tableRows'),
            ['reports' => self::REPORTS]
        ));
    }

    public function export(Request $request, string $report)
    {
        $definition = $this->definition($report);
        $records = $this->reportQuery($request, $report)->get();
        $columns = $this->columns($report);
        $rows = $records->map(fn ($record) => $this->row($report, $record));
        $format = strtolower((string) $request->get('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422, 'Unsupported export format.');

        $filename = 'chit-' . $report . '-report';
        $exportRows = $rows->map(function ($row) use ($columns) {
            $out = [];
            foreach ($columns as $key => $label) {
                $out[$label] = $row[$key] ?? '';
            }

            return $out;
        });

        return ReportExporter::download(
            $exportRows,
            $format,
            $filename,
            $definition['title'],
            $request->except(['format', 'page'])
        );
    }

    private function definition(string $report): array
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        return self::REPORTS[$report];
    }

    private function filterOptions(string $report): array
    {
        $statusOptions = match ($report) {
            'groups' => ['forming', 'active', 'completed', 'terminated'],
            'members' => ['applied', 'approved', 'active', 'rejected', 'defaulted', 'completed', 'withdrawn'],
            'installments' => ['overdue', 'pending', 'partial', 'paid', 'upcoming', 'waived'],
            'auctions' => ['scheduled', 'open', 'completed', 'cancelled'],
            'settlements' => ['pending', 'processing', 'paid', 'failed', 'cancelled'],
            default => [],
        };

        return [
            'groups' => ChitGroup::query()->orderBy('id', 'desc')->get(['id', 'group_code', 'scheme_id']),
            'schemes' => ChitScheme::query()->orderBy('name')->get(['id', 'name']),
            'collectors' => $report === 'collections'
                ? User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['Admin', 'Staff']))->orderBy('name')->get(['id', 'name'])
                : collect(),
            'statusOptions' => $statusOptions,
            'paymentModes' => ['cash', 'bank_transfer', 'upi'],
        ];
    }

    private function reportQuery(Request $request, string $report): Builder
    {
        $this->definition($report);

        return match ($report) {
            'groups' => $this->groupQuery($request),
            'members' => $this->memberQuery($request),
            'installments' => $this->installmentQuery($request, false),
            'collections' => $this->installmentQuery($request, true),
            'auctions' => $this->auctionQuery($request),
            'settlements' => $this->settlementQuery($request),
        };
    }

    private function applyGroupAndSchemeFilters(Builder $query, Request $request, string $groupColumn = 'group_id'): void
    {
        $query->when($request->filled('group_id'), fn ($q) => $q->where($groupColumn, $request->integer('group_id')));
        $query->when($request->filled('scheme_id'), function ($q) use ($request) {
            $q->whereHas('group', fn ($group) => $group->where('scheme_id', $request->integer('scheme_id')));
        });
    }

    private function applyDateFilters(Builder $query, Request $request, string $column): void
    {
        [$from, $to] = DateRangePreset::applyToRequest($request, 'date_from', 'date_to');
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }

    private function groupQuery(Request $request): Builder
    {
        $query = ChitGroup::query()
            ->with('scheme:id,name')
            ->withCount(['members' => fn ($q) => $q->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])])
            ->withSum('installments as scheduled_amount', 'amount')
            ->withSum('installments as collected_amount', 'paid_amount')
            ->withSum(['payouts as settled_amount' => fn ($q) => $q->where('status', 'paid')], 'payout_amount')
            ->orderByDesc('start_date');

        $query->when($request->filled('scheme_id'), fn ($q) => $q->where('scheme_id', $request->integer('scheme_id')));
        $query->when($request->filled('group_id'), fn ($q) => $q->whereKey($request->integer('group_id')));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%' . $request->string('search') . '%';
            $q->where(fn ($sub) => $sub->where('group_code', 'like', $search)
                ->orWhereHas('scheme', fn ($scheme) => $scheme->where('name', 'like', $search)));
        });
        $this->applyDateFilters($query, $request, 'start_date');

        return $query;
    }

    private function memberQuery(Request $request): Builder
    {
        $query = GroupMember::query()
            ->with(['group:id,group_code,scheme_id,start_date,total_months', 'group.scheme:id,name', 'client.user', 'installments'])
            ->withCount(['installments as paid_installments' => fn ($q) => $q->where('status', 'paid')])
            ->withSum('installments as collected_amount', 'paid_amount')
            ->orderByDesc('joined_date');

        $this->applyGroupAndSchemeFilters($query, $request);
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%' . $request->string('search') . '%';
            $q->where(fn ($sub) => $sub->where('member_number', 'like', $search)
                ->orWhereHas('client', fn ($client) => $client->where('client_name', 'like', $search)->orWhere('client_phone', 'like', $search))
                ->orWhereHas('group', fn ($group) => $group->where('group_code', 'like', $search)));
        });
        $this->applyDateFilters($query, $request, 'joined_date');

        return $query;
    }

    private function installmentQuery(Request $request, bool $collectionsOnly): Builder
    {
        $query = Installment::query()
            ->with(['group:id,group_code,scheme_id', 'group.scheme:id,name', 'member.client.user', 'collectedBy:id,name'])
            ->orderByDesc($collectionsOnly ? 'paid_date' : 'due_date');

        $this->applyGroupAndSchemeFilters($query, $request);
        $this->applyDateFilters($query, $request, $collectionsOnly ? 'paid_date' : 'due_date');
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%' . $request->string('search') . '%';
            $q->where(fn ($sub) => $sub->where('reference_no', 'like', $search)
                ->orWhereHas('member.client', fn ($client) => $client->where('client_name', 'like', $search)->orWhere('client_phone', 'like', $search))
                ->orWhereHas('group', fn ($group) => $group->where('group_code', 'like', $search)));
        });

        if ($collectionsOnly) {
            $query->where('paid_amount', '>', 0)
                ->when($request->filled('payment_mode'), fn ($q) => $q->where('payment_mode', $request->string('payment_mode')))
                ->when($request->filled('collector_id'), fn ($q) => $q->where('collected_by', $request->integer('collector_id')));
        } elseif ($request->filled('status')) {
            $this->applyInstallmentStatus($query, (string) $request->string('status'));
        }

        return $query;
    }

    private function applyInstallmentStatus(Builder $query, string $status): void
    {
        $today = Carbon::today();
        $monthEnd = $today->copy()->endOfMonth();

        match ($status) {
            'overdue' => $query->whereDate('due_date', '<', $today)->whereNotIn('status', ['paid', 'waived'])->whereRaw('(amount + penalty_amount - paid_amount) > 0'),
            'pending' => $query->whereBetween('due_date', [$today, $monthEnd])->where('status', 'pending'),
            'upcoming' => $query->whereDate('due_date', '>', $monthEnd)->whereNotIn('status', ['paid', 'waived']),
            default => $query->where('status', $status),
        };
    }

    private function auctionQuery(Request $request): Builder
    {
        $query = Auction::query()
            ->with(['group:id,group_code,scheme_id', 'group.scheme:id,name', 'winner.client.user'])
            ->withCount('bids')
            ->orderByDesc('auction_date');

        $this->applyGroupAndSchemeFilters($query, $request);
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%' . $request->string('search') . '%';
            $q->where(fn ($sub) => $sub->where('location', 'like', $search)
                ->orWhereHas('group', fn ($group) => $group->where('group_code', 'like', $search))
                ->orWhereHas('winner.client', fn ($client) => $client->where('client_name', 'like', $search)));
        });
        $this->applyDateFilters($query, $request, 'auction_date');

        return $query;
    }

    private function settlementQuery(Request $request): Builder
    {
        $query = Payout::query()
            ->with(['group:id,group_code,scheme_id', 'group.scheme:id,name', 'winner.client.user', 'processedBy:id,name'])
            ->orderByDesc(DB::raw('COALESCE(paid_date, created_at)'));

        $this->applyGroupAndSchemeFilters($query, $request);
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('payment_mode'), fn ($q) => $q->where('payment_mode', $request->string('payment_mode')));
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = '%' . $request->string('search') . '%';
            $q->where(fn ($sub) => $sub->where('payout_code', 'like', $search)
                ->orWhere('reference_no', 'like', $search)
                ->orWhereHas('group', fn ($group) => $group->where('group_code', 'like', $search))
                ->orWhereHas('winner.client', fn ($client) => $client->where('client_name', 'like', $search)));
        });
        $this->applyDateFilters($query, $request, 'paid_date');

        return $query;
    }

    private function summary(string $report, Builder $query): array
    {
        $count = (clone $query)->reorder()->count();

        return match ($report) {
            'groups' => [
                ['label' => 'Groups', 'value' => number_format($count)],
                ['label' => 'Chit Value', 'value' => $this->currency((clone $query)->reorder()->sum('chit_value'))],
            ],
            'members' => [
                ['label' => 'Memberships', 'value' => number_format($count)],
                ['label' => 'Auction Winners', 'value' => number_format((clone $query)->reorder()->where('has_won_auction', true)->count())],
            ],
            'installments' => [
                ['label' => 'Installments', 'value' => number_format($count)],
                ['label' => 'Total Due', 'value' => $this->currency((clone $query)->reorder()->sum(DB::raw('amount + penalty_amount')))],
                ['label' => 'Outstanding', 'value' => $this->currency((clone $query)->reorder()->sum(DB::raw('amount + penalty_amount - paid_amount')))],
            ],
            'collections' => [
                ['label' => 'Collection Records', 'value' => number_format($count)],
                ['label' => 'Collected Amount', 'value' => $this->currency((clone $query)->reorder()->sum('paid_amount'))],
            ],
            'auctions' => [
                ['label' => 'Auctions', 'value' => number_format($count)],
                ['label' => 'Winning Bids', 'value' => $this->currency((clone $query)->reorder()->sum('winning_bid'))],
                ['label' => 'Total Discount', 'value' => $this->currency((clone $query)->reorder()->sum('discount'))],
            ],
            'settlements' => [
                ['label' => 'Settlements', 'value' => number_format($count)],
                ['label' => 'Payout Amount', 'value' => $this->currency((clone $query)->reorder()->sum('payout_amount'))],
                ['label' => 'Commission', 'value' => $this->currency((clone $query)->reorder()->sum('commission_amount'))],
            ],
        };
    }

    private function columns(string $report): array
    {
        return match ($report) {
            'groups' => ['group' => 'Group', 'scheme' => 'Scheme', 'status' => 'Status', 'period' => 'Progress', 'members' => 'Members', 'chit_value' => 'Chit Value', 'scheduled' => 'Scheduled', 'collected' => 'Collected', 'settled' => 'Settled', 'balance' => 'Cash Balance'],
            'members' => ['member' => 'Member No.', 'client' => 'Customer', 'phone' => 'Phone', 'group' => 'Group', 'scheme' => 'Scheme', 'joined' => 'Joined Date', 'end_date' => 'End Date', 'status' => 'Status', 'paid_periods' => 'Paid Periods', 'collected' => 'Collected', 'overdue' => 'Overdue', 'auction' => 'Auction'],
            'installments' => ['due_date' => 'Due Date', 'group' => 'Group', 'member' => 'Member', 'customer' => 'Customer', 'period' => 'Period', 'amount' => 'Amount', 'penalty' => 'Penalty', 'paid' => 'Paid', 'balance' => 'Balance', 'status' => 'Status'],
            'collections' => ['paid_date' => 'Paid Date', 'group' => 'Group', 'customer' => 'Customer', 'period' => 'Period', 'amount' => 'Paid Amount', 'mode' => 'Mode', 'reference' => 'Reference', 'collector' => 'Collector', 'status' => 'Status'],
            'auctions' => ['date' => 'Auction Date', 'group' => 'Group', 'scheme' => 'Scheme', 'period' => 'Period', 'status' => 'Status', 'bids' => 'Bids', 'winner' => 'Winner', 'winning_bid' => 'Winning Bid', 'discount' => 'Discount', 'location' => 'Location'],
            'settlements' => ['code' => 'Payout Code', 'type' => 'Settlement Type', 'date' => 'Paid Date', 'group' => 'Group', 'period' => 'Period', 'recipient' => 'Recipient', 'chit_value' => 'Chit Value', 'commission' => 'Commission', 'payout' => 'Payout', 'mode' => 'Mode', 'status' => 'Status', 'processed_by' => 'Processed By'],
        };
    }

    private function resolveClientName($client): string
    {
        if (!$client) return '—';
        return $client->client_name ?: ($client->user?->name ?? '—');
    }

    private function resolveClientPhone($client): string
    {
        if (!$client) return '—';
        return $client->client_phone ?: ($client->alternate_phone ?? '—');
    }

    private function row(string $report, $record): array
    {
        return match ($report) {
            'groups' => [
                'group' => $record->group_code, 'scheme' => $record->scheme?->name, 'status' => Str::headline($record->status),
                'period' => $record->current_month . '/' . $record->total_months, 'members' => $record->members_count . '/' . $record->total_members,
                'chit_value' => $this->currency($record->chit_value), 'scheduled' => $this->currency($record->scheduled_amount),
                'collected' => $this->currency($record->collected_amount), 'settled' => $this->currency($record->settled_amount),
                'balance' => $this->currency((float) $record->collected_amount - (float) $record->settled_amount),
            ],
            'members' => [
                'member' => $record->member_number, 'client' => $this->resolveClientName($record->client), 'phone' => $this->resolveClientPhone($record->client),
                'group' => $record->group?->group_code, 'scheme' => $record->group?->scheme?->name,
                'joined' => $record->joined_date?->format('d M Y'), 
                'end_date' => ($record->group && $record->group->start_date && $record->group->total_months) ? \Carbon\Carbon::parse($record->group->start_date)->addMonths($record->group->total_months)->format('d M Y') : 'N/A',
                'status' => Str::headline($record->status),
                'paid_periods' => $record->paid_installments, 'collected' => $this->currency($record->collected_amount),
                'overdue' => $this->currency($record->installments->where('due_date', '<', \Carbon\Carbon::today())->whereNotIn('status', ['paid', 'waived'])->sum(fn($i) => $i->amount + $i->penalty_amount - $i->paid_amount)),
                'auction' => $record->has_won_auction ? 'Won' : 'Not won',
            ],
            'installments' => [
                'due_date' => $record->due_date?->format('d M Y'), 'group' => $record->group?->group_code,
                'member' => $record->member?->member_number, 'customer' => $this->resolveClientName($record->member?->client),
                'period' => $record->month_number, 'amount' => $this->currency($record->amount), 'penalty' => $this->currency($record->penalty_amount),
                'paid' => $this->currency($record->paid_amount), 'balance' => $this->currency($record->balance), 'status' => Str::headline($record->status),
            ],
            'collections' => [
                'paid_date' => $record->paid_date?->format('d M Y'), 'group' => $record->group?->group_code,
                'customer' => $this->resolveClientName($record->member?->client), 'period' => $record->month_number,
                'amount' => $this->currency($record->paid_amount), 'mode' => Str::headline($record->payment_mode),
                'reference' => $record->reference_no, 'collector' => $record->collectedBy?->name, 'status' => Str::headline($record->status),
            ],
            'auctions' => [
                'date' => $record->auction_date?->format('d M Y'), 'group' => $record->group?->group_code,
                'scheme' => $record->group?->scheme?->name, 'period' => $record->month_number, 'status' => Str::headline($record->status),
                'bids' => $record->bids_count, 'winner' => $this->resolveClientName($record->winner?->client),
                'winning_bid' => $this->currency($record->winning_bid), 'discount' => $this->currency($record->discount), 'location' => $record->location,
            ],
            'settlements' => [
                'code' => $record->payout_code,
                'type' => $record->payout_kind_label,
                'date' => $record->paid_date?->format('d M Y'), 'group' => $record->group?->group_code,
                'period' => $record->month_number, 'recipient' => $this->resolveClientName($record->winner?->client),
                'chit_value' => $this->currency($record->chit_value), 'commission' => $this->currency($record->commission_amount),
                'payout' => $this->currency($record->payout_amount), 'mode' => Str::headline($record->payment_mode),
                'status' => Str::headline($record->status), 'processed_by' => $record->processedBy?->name,
            ],
        };
    }

    private function currency($amount): string
    {
        return '₹ ' . number_format((float) $amount, 2);
    }
}
