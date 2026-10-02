<?php

namespace App\Services;

use App\Models\FixedDepositApplication;
use App\Models\GroupMember;
use App\Models\LoanApplication;
use App\Models\Payout;
use App\Support\DateRangePreset;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ApplicationListService
{
    public const MODULES = ['all', 'loan', 'chit', 'fd', 'settlement'];

    public function normalizeModule(?string $module): string
    {
        $module = strtolower(trim((string) $module));

        return in_array($module, self::MODULES, true) ? $module : 'all';
    }

    public function stats(string $module, Request $request): array
    {
        return $this->statsFromRows($this->listRows($module, $request));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total:int,pending:int,approved:int,rejected:int}
     */
    public function statsFromRows(array $rows): array
    {
        $pending = 0;
        $approved = 0;
        $rejected = 0;

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (in_array($status, ['pending', 'applied', 'requested'], true)) {
                $pending++;
            } elseif (in_array($status, ['rejected', 'cancelled', 'failed'], true)) {
                $rejected++;
            } else {
                $approved++;
            }
        }

        return [
            'total' => count($rows),
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRows(string $module, Request $request): array
    {
        $module = $this->listingModule($module, $request);

        $rows = match ($module) {
            'loan' => $this->loanRows($request),
            'chit' => $this->chitRows($request),
            'fd' => $this->fdRows($request),
            'settlement' => $this->canListSettlements() ? $this->settlementRows($request) : [],
            default => array_merge(
                $this->loanRows($request),
                $this->chitRows($request),
                $this->fdRows($request),
                $this->canListSettlements() ? $this->settlementRows($request) : []
            ),
        };

        if ($module === 'all') {
            usort($rows, function (array $left, array $right) {
                return strcmp((string) ($right['applied_sort'] ?? ''), (string) ($left['applied_sort'] ?? ''));
            });
        }

        return $this->numberRows($rows);
    }

    public function paginateRows(string $module, Request $request, int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginateListed($this->listRows($module, $request), $request, $perPage);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function paginateListed(array $rows, Request $request, int $perPage = 25): LengthAwarePaginator
    {
        $perPage = (int) $request->input('per_page', $perPage);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        $page = max(1, (int) $request->input('page', 1));
        $total = count($rows);
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->except('page'),
        ]);
    }

    public function moduleTitle(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'loan' => 'Loan Applications',
            'chit' => 'Chit Applications',
            'fd' => 'FD Applications',
            'settlement' => 'Settlement Applications',
            default => 'All Applications',
        };
    }

    public function productHeading(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'loan' => 'Loan Name',
            'chit' => 'Scheme',
            'fd' => 'FD Scheme',
            'settlement' => 'Group / Scheme',
            default => 'Product / Scheme',
        };
    }

    protected function listingModule(string $module, Request $request): string
    {
        $type = strtolower(trim((string) $request->input('type', '')));
        if (in_array($type, ['loan', 'chit', 'fd', 'settlement'], true)) {
            return $type;
        }

        return $this->normalizeModule($module);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loanRows(Request $request): array
    {
        $query = LoanApplication::query()->with(['client.location', 'product']);
        $this->applyAgentScope($query, 'loan');
        $this->applyClientZoneFilters($query, $request, 'client');
        $this->applyDateFilter($query, $request, 'created_at');
        $this->applyStatusFilter($query, $request, 'loan');

        return $query->orderByDesc('id')->get()->map(function (LoanApplication $application) {
            $client = $application->client;
            $applied = $application->applied_at ?? $application->created_at;

            return $this->listRow([
                'module' => 'loan',
                'application_number' => $application->application_number ?: ('LN-' . $application->id),
                'client_name' => $client?->client_name ?? 'N/A',
                'client_phone' => $client?->client_phone ?? 'N/A',
                'zone' => optional($client?->location)->name ?? 'N/A',
                'product' => optional($application->product)->loan_name ?: ($application->loan_code ?: 'N/A'),
                'amount' => (float) ($application->loan_amount ?? 0),
                'status' => strtolower((string) $application->status),
                'status_label' => $application->status_label,
                'status_color' => $application->status_color,
                'applied_at' => $applied,
                'view_url' => route('loan-application-view', $application->getRouteKey()),
            ]);
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function chitRows(Request $request): array
    {
        $query = GroupMember::query()
            ->with(['client.location', 'group.scheme'])
            ->whereHas('group')
            ->whereHas('client');

        $this->applyAgentScope($query, 'chit');
        $this->applyClientZoneFilters($query, $request, 'client');
        $this->applyDateFilter($query, $request, 'created_at');

        if ($request->filled('status')) {
            $this->applyStatusFilter($query, $request, 'chit');
        } else {
            $query->whereIn('status', ['applied', 'approved', 'active', 'rejected']);
        }

        return $query->orderByDesc('id')->get()->map(function (GroupMember $member) {
            $client = $member->client;
            $group = $member->group;
            $scheme = $group?->scheme;
            $applied = $member->created_at;
            $product = $scheme?->name ?: 'N/A';

            return $this->listRow([
                'module' => 'chit',
                'application_number' => $member->application_number,
                'client_name' => $client?->client_name ?? 'N/A',
                'client_phone' => $client?->client_phone ?? 'N/A',
                'zone' => optional($client?->location)->name ?? 'N/A',
                'product' => $product,
                'amount' => (float) ($group?->chit_value ?? 0),
                'status' => strtolower((string) $member->status),
                'status_label' => ucfirst((string) $member->status),
                'status_color' => $member->status_badge,
                'applied_at' => $applied,
                'view_url' => route('chit.applications.show', $member),
            ]);
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fdRows(Request $request): array
    {
        $query = FixedDepositApplication::query()->with(['client.location', 'scheme']);
        $this->applyAgentScope($query, 'fd');
        $this->applyClientZoneFilters($query, $request, 'client');
        $this->applyDateFilter($query, $request, 'created_at');
        $this->applyStatusFilter($query, $request, 'fd');

        return $query->orderByDesc('id')->get()->map(function (FixedDepositApplication $application) {
            $client = $application->client;
            $applied = $application->applied_at ?? $application->created_at;

            return $this->listRow([
                'module' => 'fd',
                'application_number' => $application->application_number ?: ('FD-' . $application->id),
                'client_name' => $client?->client_name ?? 'N/A',
                'client_phone' => $client?->client_phone ?? 'N/A',
                'zone' => optional($client?->location)->name ?? 'N/A',
                'product' => optional($application->scheme)->name ?? 'N/A',
                'amount' => (float) ($application->deposit_amount ?? 0),
                'status' => strtolower((string) $application->status),
                'status_label' => $application->status_label,
                'status_color' => $application->status_color,
                'applied_at' => $applied,
                'view_url' => route('fd.applications.show', $application),
            ]);
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function settlementRows(Request $request): array
    {
        $query = Payout::query()
            ->with(['group.scheme', 'winner.client.location', 'winner.shares.client', 'initiatedBy'])
            ->whereHas('group');

        $this->applyAgentScope($query, 'settlement');
        $this->applyDateFilter($query, $request, 'created_at');
        $this->applyStatusFilter($query, $request, 'settlement');

        $clientName = trim((string) $request->input('client_name', ''));
        $locationId = $request->input('location_id');
        if ($clientName !== '') {
            $query->where(function ($q) use ($clientName) {
                $q->whereHas('winner.client', function ($c) use ($clientName) {
                    $c->where('client_name', 'like', '%' . $clientName . '%');
                })->orWhereHas('winner.shares.client', function ($c) use ($clientName) {
                    $c->where('client_name', 'like', '%' . $clientName . '%');
                });
            });
        }
        if ($locationId !== null && $locationId !== '') {
            $query->where(function ($q) use ($locationId) {
                $q->whereHas('winner.client', function ($c) use ($locationId) {
                    $c->where('location_id', $locationId);
                })->orWhereHas('winner.shares.client', function ($c) use ($locationId) {
                    $c->where('location_id', $locationId);
                });
            });
        }

        return $query->orderByDesc('id')->get()->map(function (Payout $payout) {
            $member = $payout->winner;
            $client = $member?->client ?? $member?->shares?->first()?->client;
            $clientName = $member
                ? ($member->is_shared
                    ? ($member->owners_display ?? 'Shared Members')
                    : ($member->displayClientName() ?? ($client?->client_name ?? 'N/A')))
                : 'N/A';
            $group = $payout->group;
            $scheme = $group?->scheme;
            $product = trim(($group?->group_code ?? '') . ($scheme?->name ? (' / ' . $scheme->name) : ''));
            $monthNumber = $payout->month_number;
            $monthLabel = ($monthNumber && $group)
                ? ('Month ' . $monthNumber . ' — ' . $group->periodCalendarLabel((int) $monthNumber))
                : null;
            $chitValue = (float) ($payout->chit_value ?? $group?->chit_value ?? 0);
            $applicant = $payout->initiatedBy;
            $canAction = in_array($payout->status, ['pending', 'processing', 'applied', 'requested'], true);
            $approveUrl = ($canAction && $group && $member)
                ? route('chit.settlements.confirm', [$group, $member]) . '?payout_id=' . $payout->id
                : null;
            $cancelUrl = $canAction ? route('chit.settlements.cancel', $payout) : null;

            $row = $this->listRow([
                'module' => 'settlement',
                'application_number' => $payout->payout_code ?: ('PAY-' . $payout->id),
                'client_name' => $clientName,
                'client_phone' => $client?->client_phone ?? 'N/A',
                'zone' => optional($client?->location)->name ?? 'N/A',
                'product' => ($product !== '' ? $product : 'N/A'),
                'amount' => (float) ($payout->payout_amount ?? 0),
                'status' => strtolower((string) $payout->status),
                'status_label' => $payout->status_label,
                'status_color' => $payout->status_badge,
                'applied_at' => $payout->created_at,
                'view_url' => route('chit.settlement-applications.show', $payout),
            ]);

            return array_merge($row, [
                'payout_id' => $payout->id,
                'payout_code' => $payout->payout_code ?: ('PAY-' . $payout->id),
                'member_number' => $member?->display_member_number ?? $member?->member_number ?? '—',
                'source' => $payout->source,
                'source_label' => $payout->source_label,
                'source_badge' => $payout->source_badge,
                'applicant_login' => $applicant?->name ?: ($applicant?->phone ?: ($applicant?->email ?: null)),
                'group_code' => $group?->group_code ?? '—',
                'scheme_name' => $scheme?->name ?? '—',
                'chit_value' => $chitValue,
                'chit_value_formatted' => '₹' . number_format($chitValue, 2),
                'month_number' => $monthNumber,
                'month_label' => $monthLabel,
                'payout_kind_label' => $payout->payout_kind_label,
                'payout_kind_badge' => $payout->payout_kind_badge,
                'can_action' => $canAction,
                'approve_url' => $approveUrl,
                'cancel_url' => $cancelUrl,
            ]);
        })->all();
    }

    public function canListSettlements(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']));
    }

    /**
     * @return array{total:int,pending:int,approved:int,rejected:int}
     */
    public function settlementTabCounts(Request $request): array
    {
        $withoutStatus = $request->duplicate($request->except('status'));

        return $this->statsFromRows($this->settlementRows($withoutStatus));
    }

    protected function applyClientZoneFilters($query, Request $request, string $relation): void
    {
        $clientName = trim((string) $request->input('client_name', ''));
        $locationId = $request->input('location_id');

        if ($clientName !== '') {
            $query->whereHas($relation, function ($q) use ($clientName) {
                $q->where('client_name', 'like', '%' . $clientName . '%');
            });
        }

        if ($locationId !== null && $locationId !== '') {
            $query->whereHas($relation, function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            });
        }
    }

    protected function applyStatusFilter($query, Request $request, string $module): void
    {
        $status = strtolower(trim((string) $request->input('status', '')));
        if ($status === '') {
            return;
        }

        $map = match ($status) {
            'pending' => $module === 'chit'
                ? ['applied']
                : ($module === 'settlement' ? ['pending', 'applied', 'requested'] : ['pending', 'applied']),
            'approved' => $module === 'settlement' ? ['paid', 'processing'] : ['approved'],
            'process', 'in_progress' => $module === 'settlement' ? ['processing'] : ['process', 'in_progress'],
            'active' => ['active'],
            'booked' => ['booked'],
            'disbursed' => ['disbursed'],
            'rejected' => $module === 'settlement' ? ['cancelled', 'failed'] : ['rejected'],
            default => [$status],
        };

        $query->whereIn('status', $map);
    }

    protected function applyDateFilter($query, Request $request, string $column): void
    {
        [$from, $to] = DateRangePreset::resolve($request);

        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }

    protected function applyAgentScope($query, string $module): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole('Agent') || $user->hasAnyRole(['Admin', 'Staff', 'Super Admin'])) {
            return;
        }

        $agentId = optional($user->agent)->id;
        $userId = $user->id;
        $userName = $user->name;

        if ($module === 'chit' || $module === 'settlement') {
            if ($module === 'settlement') {
                if (! $agentId) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                $query->where(function ($q) use ($agentId) {
                    $q->whereHas('winner.client', function ($c) use ($agentId) {
                        $c->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                    })->orWhereHas('winner.shares.client', function ($c) use ($agentId) {
                        $c->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                    });
                });

                return;
            }

            if (! $agentId || ! $userId) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->where(function ($q) use ($userId, $agentId, $userName) {
                $q->where('applied_by', $userId);
                if ($userName) {
                    $q->orWhere(function ($legacy) use ($agentId, $userName) {
                        $legacy->whereNull('applied_by')
                            ->where('referred_by_agent_id', $agentId)
                            ->where('remarks', 'like', 'Applied by ' . $userName . '%');
                    });
                }
            });

            return;
        }

        if (! $agentId) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('client', function ($q) use ($agentId) {
            $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function listRow(array $row): array
    {
        $applied = $row['applied_at'] ?? null;
        $sortDate = '';
        $displayDate = 'N/A';
        if ($applied instanceof Carbon || $applied instanceof \DateTimeInterface) {
            $carbon = Carbon::parse($applied);
            $sortDate = $carbon->format('Y-m-d H:i:s');
            $displayDate = $carbon->format('d-m-Y');
        } elseif ($applied) {
            try {
                $carbon = Carbon::parse((string) $applied);
                $sortDate = $carbon->format('Y-m-d H:i:s');
                $displayDate = $carbon->format('d-m-Y');
            } catch (\Throwable) {
                $displayDate = (string) $applied;
            }
        }

        $module = $row['module'] ?? 'loan';
        $amount = (float) ($row['amount'] ?? 0);

        return [
            'module' => $module,
            'module_label' => match ($module) {
                'chit' => 'Chit',
                'fd' => 'FD',
                'settlement' => 'Settlement',
                default => 'Loan',
            },
            'application_number' => $row['application_number'] ?? 'N/A',
            'client_name' => $row['client_name'] ?? 'N/A',
            'client_phone' => $row['client_phone'] ?? 'N/A',
            'zone' => $row['zone'] ?? 'N/A',
            'product' => $row['product'] ?? 'N/A',
            'amount' => $amount,
            'amount_formatted' => '₹' . number_format($amount, 2),
            'status' => $row['status'] ?? 'pending',
            'status_label' => $row['status_label'] ?? ucfirst((string) ($row['status'] ?? 'pending')),
            'status_color' => $row['status_color'] ?? 'secondary',
            'applied_at' => $displayDate,
            'applied_sort' => $sortDate,
            'view_url' => $row['view_url'] ?? '#',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function numberRows(array $rows): array
    {
        $total = count($rows);

        return collect($rows)->values()->map(function (array $row, int $index) use ($total) {
            $row['sno'] = $total - $index;

            return $row;
        })->all();
    }
}
