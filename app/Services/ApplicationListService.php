<?php

namespace App\Services;

use App\Models\FixedDepositApplication;
use App\Models\LoanApplication;
use App\Support\DateRangePreset;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ApplicationListService
{
    public const MODULES = ['all', 'loan', 'fd'];

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
            'fd' => $this->fdRows($request),
            default => array_merge(
                $this->loanRows($request),
                $this->fdRows($request)
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
            'fd' => 'FD Applications',
            default => 'All Applications',
        };
    }

    public function productHeading(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'loan' => 'Loan Name',
            'fd' => 'FD Scheme',
            default => 'Product / Scheme',
        };
    }

    protected function listingModule(string $module, Request $request): string
    {
        $type = strtolower(trim((string) $request->input('type', '')));
        if (in_array($type, ['loan', 'fd'], true)) {
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
    protected function numberRows(array $rows): array
    {
        $total = count($rows);

        return collect($rows)->values()->map(function (array $row, int $index) use ($total) {
            $row['sno'] = $total - $index;

            return $row;
        })->all();
    }
}
