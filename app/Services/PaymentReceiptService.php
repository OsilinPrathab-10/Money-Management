<?php

namespace App\Services;

use App\Http\Controllers\EmiController;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\FixedDepositTransaction;
use App\Models\LoanAccount;
use App\Support\BulkPaymentGroup;
use App\Support\DateRangePreset;
use App\Support\HashId;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PaymentReceiptService
{
    public const MODULES = ['all', 'loan', 'fd'];

    public const PRINT_MODULES = ['loan', 'fd'];

    public const FD_TYPES = [
        'creation',
        'monthly_interest',
        'maturity',
        'premature',
        'renewal',
        'closure',
    ];

    public function normalizeModule(?string $module): string
    {
        $module = strtolower(trim((string) $module));

        return in_array($module, self::MODULES, true) ? $module : 'all';
    }

    public function stats(string $module, Request $request): array
    {
        $module = $this->listingModule($module, $request);

        return match ($module) {
            'all' => $this->allStats($request),
            'fd' => $this->fdStats($request),
            default => $this->loanStats($request),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRows(string $module, Request $request): array
    {
        $module = $this->listingModule($module, $request);

        $rows = match ($module) {
            'all' => $this->allRows($request),
            'fd' => $this->fdRows($request),
            default => $this->loanRows($request),
        };

        return $this->filterRows($rows, $request);
    }

    public function paginateRows(string $module, Request $request, int $perPage = 25): LengthAwarePaginator
    {
        $rows = $this->listRows($module, $request);
        $perPage = (int) $request->input('per_page', $perPage);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
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

    protected function listingModule(string $module, Request $request): string
    {
        $type = strtolower(trim((string) $request->input('type', '')));
        if (in_array($type, self::PRINT_MODULES, true)) {
            return $type;
        }

        return $this->normalizeModule($module);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $module, mixed $id, ?string $bulkKey = null, ?int $collectionId = null): array
    {
        $module = in_array(strtolower(trim((string) $module)), self::PRINT_MODULES, true)
            ? strtolower(trim((string) $module))
            : 'loan';
        $decodedId = $this->decodeId($id);
        $bulkKey = $bulkKey ? strtoupper(trim($bulkKey)) : null;
        $collectionId = $collectionId ?: (request()->filled('collection_id') ? (int) request('collection_id') : null);

        return match ($module) {
            'fd' => $this->fdPayload($decodedId),
            default => $this->loanPayload($decodedId, $bulkKey, $collectionId),
        };
    }

    public function accountHeading(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'all' => 'Account / Ref',
            'fd' => 'FD Number',
            default => 'Application No.',
        };
    }

    public function moduleTitle(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'all' => 'All Payment Receipts',
            'fd' => 'FD Payment Receipts',
            default => 'Loan Payment Receipts',
        };
    }

    public function moduleLabel(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'fd' => 'FD',
            'loan' => 'Loan',
            default => 'All',
        };
    }

    /**
     * @return array{total_receipts: int|float, total_collected: float, month_collected: float, today_collected: float}
     */
    protected function allStats(Request $request): array
    {
        $parts = [$this->loanStats($request), $this->fdStats($request)];

        return [
            'total_receipts' => array_sum(array_column($parts, 'total_receipts')),
            'total_collected' => array_sum(array_column($parts, 'total_collected')),
            'month_collected' => array_sum(array_column($parts, 'month_collected')),
            'today_collected' => array_sum(array_column($parts, 'today_collected')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function allRows(Request $request): array
    {
        $rows = array_merge(
            $this->loanRows($request),
            $this->fdRows($request)
        );

        usort($rows, function (array $left, array $right) {
            return strcmp((string) ($right['paid_date_sort'] ?? ''), (string) ($left['paid_date_sort'] ?? ''));
        });

        return $this->numberRows($rows);
    }

    protected function loanStats(Request $request): array
    {
        $base = fn () => $this->loanBaseQuery($request);
        $today = Carbon::now()->toDateString();
        $thisMonth = now()->month;
        $thisYear = now()->year;

        $baseEmiQuery = (clone $base());
        $baseEmiIds = (clone $baseEmiQuery)->select('emis.id');

        $collectionsQuery = EmiCollection::whereIn('status', BulkPaymentGroup::postedStatuses())
            ->whereIn('emi_id', $baseEmiIds);

        // Sum of collections for today
        $todayCollSum = (clone $collectionsQuery)
            ->where(function ($q) use ($today) {
                $q->whereDate('collected_at', $today)
                    ->orWhere(function ($sq) use ($today) {
                        $sq->whereNull('collected_at')->whereDate('created_at', $today);
                    });
            })
            ->sum('amount');

        // Sum of legacy EMIs without collections paid today
        $todayLegacySum = (clone $base())
            ->whereDoesntHave('collections')
            ->whereDate('paid_date', $today)
            ->sum('paid_amount');

        $todayCollected = round((float) $todayCollSum + (float) $todayLegacySum, 2);

        // Month collections
        $monthCollSum = (clone $collectionsQuery)
            ->where(function ($q) use ($thisMonth, $thisYear) {
                $q->where(function ($sq) use ($thisMonth, $thisYear) {
                    $sq->whereNotNull('collected_at')
                        ->whereMonth('collected_at', $thisMonth)
                        ->whereYear('collected_at', $thisYear);
                })->orWhere(function ($sq) use ($thisMonth, $thisYear) {
                    $sq->whereNull('collected_at')
                        ->whereMonth('created_at', $thisMonth)
                        ->whereYear('created_at', $thisYear);
                });
            })
            ->sum('amount');

        $monthLegacySum = (clone $base())
            ->whereDoesntHave('collections')
            ->whereMonth('paid_date', $thisMonth)
            ->whereYear('paid_date', $thisYear)
            ->sum('paid_amount');

        $monthCollected = round((float) $monthCollSum + (float) $monthLegacySum, 2);

        // Total collected
        $totalCollSum = (clone $collectionsQuery)->sum('amount');
        $totalLegacySum = (clone $base())->whereDoesntHave('collections')->sum('paid_amount');
        $totalCollected = round((float) $totalCollSum + (float) $totalLegacySum, 2);

        $totalReceipts = (clone $collectionsQuery)->count() + (clone $base())->whereDoesntHave('collections')->count();

        return [
            'total_receipts' => $totalReceipts,
            'total_collected' => $totalCollected,
            'month_collected' => $monthCollected,
            'today_collected' => $todayCollected,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loanRows(Request $request): array
    {
        $query = $this->loanBaseQuery($request)
            ->with(['loanAccount.loanApplication.client.location']);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('application_number')) {
            $applicationNumber = $request->application_number;
            $query->whereHas('loanAccount', function ($q) use ($applicationNumber) {
                $q->where('application_number', $applicationNumber);
            });
        }

        $this->applyDateFilter($query, $request, 'paid_date');

        $receipts = $query->orderByDesc('paid_date')->orderByDesc('id')->get();
        $emiIds = $receipts->pluck('id')->filter()->all();
        $collections = collect();
        if ($emiIds) {
            $collections = EmiCollection::with(['emi.loanAccount.loanApplication.client.location'])
                ->whereIn('emi_id', $emiIds)
                ->whereIn('status', BulkPaymentGroup::postedStatuses())
                ->get();
        }

        $bulkEmiIds = [];
        $rows = [];
        foreach (BulkPaymentGroup::groupCollections($collections) as $group) {
            if (empty($group['is_bulk'])) {
                continue;
            }

            /** @var EmiCollection $leadCollection */
            $leadCollection = $group['lead'];
            $siblings = BulkPaymentGroup::findSiblings($leadCollection);
            if ($siblings->count() < 2) {
                continue;
            }

            $memberEmiIds = $siblings->pluck('emi_id')->unique()->filter()->all();
            $memberEmis = $receipts->whereIn('id', $memberEmiIds)->sortBy('instalment_number');
            $lead = $memberEmis->first() ?? $receipts->firstWhere('id', $leadCollection->emi_id);
            if (!$lead) {
                continue;
            }

            foreach ($memberEmiIds as $memberId) {
                $bulkEmiIds[$memberId] = true;
            }

            $loanAccount = $lead->loanAccount;
            $loanApplication = optional($loanAccount)->loanApplication;
            $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
            $applicationNumber = $lead->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number;
            $bulkKey = BulkPaymentGroup::extractExplicitKey($leadCollection->remarks, $leadCollection->payment_reference);

            $paidAt = $this->resolveAccurateTimestamp(
                $lead->paid_date,
                $leadCollection->collected_at ?: $leadCollection->created_at,
                $lead->updated_at,
                $lead->created_at
            );

            $rows[] = $this->listRow([
                'id' => $lead->getRouteKey(),
                'receipt_number' => 'RCP-' . str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'location_id' => optional($client)->location_id,
                'application_number' => $applicationNumber,
                'paid_amount' => round((float) $siblings->sum('amount'), 2),
                'payment_method' => $leadCollection->payment_method ?: $lead->payment_method,
                'paid_date' => $paidAt,
                'payment_reference' => $leadCollection->payment_reference ?: $lead->payment_reference,
                'is_bulk' => true,
                'bulk_key' => $bulkKey,
                'emi_split' => BulkPaymentGroup::emiSplitLabel($siblings),
                'emi_splits' => BulkPaymentGroup::emiSplits($siblings),
                'emi_count' => $siblings->count(),
                'module' => 'loan',
            ]);
        }

        foreach ($receipts as $emi) {
            if (isset($bulkEmiIds[$emi->id])) {
                continue;
            }

            $loanAccount = $emi->loanAccount;
            $loanApplication = optional($loanAccount)->loanApplication;
            $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
            $applicationNumber = $emi->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number;

            $emiCollections = $collections->where('emi_id', $emi->id);

            if ($emiCollections->count() > 1) {
                foreach ($emiCollections->sortByDesc('id') as $col) {
                    $paidAt = $this->resolveAccurateTimestamp(
                        $col->collected_at ?: $col->created_at,
                        $emi->paid_date,
                        $col->updated_at,
                        $col->created_at
                    );

                    $rows[] = $this->listRow([
                        'id' => $emi->getRouteKey(),
                        'collection_id' => $col->id,
                        'receipt_number' => 'RCP-' . str_pad((string) $emi->id, 6, '0', STR_PAD_LEFT) . '-C' . $col->id,
                        'client_name' => $client->client_name ?? 'N/A',
                        'zone' => optional($client)->location ? $client->location->name : 'N/A',
                        'location_id' => optional($client)->location_id,
                        'application_number' => $applicationNumber,
                        'paid_amount' => (float) $col->amount,
                        'payment_method' => $col->payment_method ?: $emi->payment_method,
                        'paid_date' => $paidAt,
                        'payment_reference' => $col->payment_reference ?: $emi->payment_reference,
                        'is_bulk' => false,
                        'emi_split' => $emi->instalment_number ? ('EMI #' . $emi->instalment_number . ' (Part)') : null,
                        'module' => 'loan',
                    ]);
                }
            } else {
                $singleCollection = $emiCollections->first();
                $paidAt = $this->resolveAccurateTimestamp(
                    $singleCollection?->collected_at ?: $singleCollection?->created_at,
                    $emi->paid_date,
                    $emi->updated_at,
                    $emi->created_at
                );

                $rows[] = $this->listRow([
                    'id' => $emi->getRouteKey(),
                    'collection_id' => $singleCollection?->id,
                    'receipt_number' => 'RCP-' . str_pad((string) $emi->id, 6, '0', STR_PAD_LEFT),
                    'client_name' => $client->client_name ?? 'N/A',
                    'zone' => optional($client)->location ? $client->location->name : 'N/A',
                    'location_id' => optional($client)->location_id,
                    'application_number' => $applicationNumber,
                    'paid_amount' => $singleCollection ? (float) $singleCollection->amount : (float) $emi->paid_amount,
                    'payment_method' => $singleCollection?->payment_method ?: $emi->payment_method,
                    'paid_date' => $paidAt,
                    'payment_reference' => $singleCollection?->payment_reference ?: $emi->payment_reference,
                    'is_bulk' => false,
                    'emi_split' => $emi->instalment_number ? ('EMI #' . $emi->instalment_number) : null,
                    'module' => 'loan',
                ]);
            }
        }

        usort($rows, function (array $left, array $right) {
            return strcmp((string) ($right['paid_date_sort'] ?? ''), (string) ($left['paid_date_sort'] ?? ''));
        });

        return $this->numberRows($rows);
    }

    protected function loanPayload(int $id, ?string $bulkKey = null, ?int $collectionId = null): array
    {
        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
            'collections.emi',
        ])->findOrFail($id);

        $payload = app(EmiController::class)->buildReceiptPayload($emi, $bulkKey, $collectionId);
        $payload['module'] = 'loan';
        $payload['account_label'] = $payload['account_label'] ?? 'Loan ID';
        $payload['start_date_label'] = $payload['start_date_label'] ?? 'Disbursement Date';
        $payload['receipt_title'] = $payload['receipt_title'] ?? 'LOAN PAYMENT RECEIPT';

        return $payload;
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fdStats(Request $request): array
    {
        $base = fn () => $this->fdBaseQuery($request);
        $today = Carbon::now()->toDateString();

        return [
            'total_receipts' => (clone $base())->count(),
            'total_collected' => (clone $base())->sum('amount'),
            'month_collected' => (clone $base())
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'),
            'today_collected' => (clone $base())->whereDate('created_at', $today)->sum('amount'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fdRows(Request $request): array
    {
        $query = $this->fdBaseQuery($request)
            ->with(['fixedDeposit.client.location', 'fixedDeposit.scheme']);

        if ($request->filled('payment_method')) {
            $query->where('payment_mode', $request->payment_method);
        }

        $this->applyDateFilter($query, $request, 'created_at');

        $transactions = $query->orderByDesc('id')->get();
        $rows = [];

        foreach ($transactions as $txn) {
            $fd = $txn->fixedDeposit;
            $client = $fd?->client;

            $rows[] = $this->listRow([
                'id' => HashId::encode($txn->id),
                'receipt_number' => 'FDP-' . str_pad((string) $txn->id, 6, '0', STR_PAD_LEFT),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'location_id' => optional($client)->location_id,
                'application_number' => $fd?->fd_number ?? 'N/A',
                'paid_amount' => $txn->amount,
                'payment_method' => $txn->payment_mode,
                'paid_date' => $txn->created_at,
                'payment_reference' => $txn->reference,
                'is_bulk' => false,
                'emi_split' => $this->fdTypeLabel($txn->transaction_type),
                'module' => 'fd',
            ]);
        }

        return $this->numberRows($rows);
    }

    protected function fdPayload(int $id): array
    {
        $txn = FixedDepositTransaction::with(['fixedDeposit.client', 'fixedDeposit.scheme'])
            ->findOrFail($id);

        $fd = $txn->fixedDeposit;
        $client = $fd?->client;
        $scheme = $fd?->scheme;
        $type = (string) $txn->transaction_type;
        $amount = (float) $txn->amount;
        $isCreation = $type === 'creation';
        $principal = $isCreation ? $amount : (float) ($txn->principal_amount ?? 0);
        $interest = $isCreation ? 0.0 : (float) ($txn->interest_amount ?? 0);
        $penalty = (float) ($txn->penalty_amount ?? 0);
        $statusMeta = $this->statusMeta('paid');
        $txnTime = $txn->created_at ? $txn->created_at->timezone('Asia/Kolkata') : null;
        $paymentDate = $txnTime ? $txnTime->format('d-m-Y h:i A') : 'N/A';
        $paymentDateOnly = $txnTime ? $txnTime->format('d-m-Y') : 'N/A';
        $paymentTimeOnly = $txnTime ? $txnTime->format('h:i:s A') : '';

        return [
            'id' => HashId::encode($txn->id),
            'receipt_number' => 'FDP-' . str_pad((string) $txn->id, 6, '0', STR_PAD_LEFT),
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $fd?->fd_number ?? 'N/A',
            'account_number' => $fd?->fd_number,
            'loan_product' => $scheme->name ?? 'Fixed Deposit',
            'instalment_number' => null,
            'instalment_label' => $this->fdTypeLabel($type),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'emi_amount' => $amount,
            'due_amount' => $amount,
            'total_due_amount' => $amount,
            'previously_paid' => 0.0,
            'previous_paid_amount' => 0.0,
            'current_payment' => $amount,
            'today_payment' => $amount,
            'overdue_amount' => $penalty,
            'show_overdue' => $penalty > 0,
            'total_amount_display' => $amount,
            'paid_amount' => $amount,
            'total_paid_amount' => $amount,
            'outstanding_amount' => 0,
            'payment_method' => $this->formatMethod($txn->payment_mode),
            'payment_reference' => $txn->reference ?: 'N/A',
            'paid_date' => $paymentDate,
            'paid_date_only' => $paymentDateOnly,
            'paid_time' => $paymentTimeOnly,
            'paid_time_only' => $paymentTimeOnly,
            'disbursed_date' => $fd && $fd->deposit_date ? $fd->deposit_date->format('d-m-Y') : 'N/A',
            'status' => 'paid',
            'status_label' => $statusMeta['label'],
            'status_color' => $statusMeta['color'],
            'remarks' => $txn->description ?? '-',
            'is_bulk' => false,
            'emi_splits' => [],
            'split_item_label' => 'Txn',
            'module' => 'fd',
            'account_label' => 'FD Number',
            'start_date_label' => 'Deposit Date',
            'receipt_title' => 'FD PAYMENT RECEIPT',
        ];
    }

    protected function loanBaseQuery(Request $request)
    {
        $query = Emi::query()
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0);

        $this->applyAgentScope($query, $request, 'loan');
        $this->applyClientZoneFilters($query, $request, 'loan');

        return $query;
    }


    protected function fdBaseQuery(Request $request)
    {
        $query = FixedDepositTransaction::query()
            ->whereIn('transaction_type', self::FD_TYPES)
            ->where('amount', '>', 0)
            ->whereHas('fixedDeposit');

        $this->applyAgentScope($query, $request, 'fd');
        $this->applyClientZoneFilters($query, $request, 'fd');

        return $query;
    }

    protected function applyClientZoneFilters($query, Request $request, string $module): void
    {
        $clientName = trim((string) $request->input('client_name', ''));
        $locationId = $request->input('location_id');
        $relation = match ($module) {
            'fd' => 'fixedDeposit.client',
            default => 'loanAccount.loanApplication.client',
        };

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

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function filterRows(array $rows, Request $request): array
    {
        $clientName = mb_strtolower(trim((string) $request->input('client_name', '')));
        $locationId = $request->input('location_id');
        $zone = trim((string) $request->input('zone', ''));

        $filtered = array_values(array_filter($rows, function (array $row) use ($clientName, $locationId, $zone) {
            if ($clientName !== '' && !str_contains(mb_strtolower((string) ($row['client_name'] ?? '')), $clientName)) {
                return false;
            }
            if ($locationId !== null && $locationId !== '' && $row['location_id'] !== null && $row['location_id'] !== ''
                && (string) $row['location_id'] !== (string) $locationId) {
                return false;
            }
            if ($zone !== '' && strcasecmp((string) ($row['zone'] ?? ''), $zone) !== 0) {
                return false;
            }

            return true;
        }));

        return $this->numberRows($filtered);
    }

    protected function applyAgentScope($query, Request $request, string $module): void
    {
        $user = auth()->user();
        $agentId = null;

        if ($user && $user->hasRole('Agent')) {
            $agentId = optional($user->agent)->id;
        } elseif ($request->filled('agent_id')) {
            $agentId = $request->input('agent_id');
        }

        if (!$agentId) {
            return;
        }

        if ($module === 'loan') {
            $query->whereHas('loanAccount.loanApplication.client', function ($q) use ($agentId) {
                $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
            });
            return;
        }


        $query->whereHas('fixedDeposit.client', function ($q) use ($agentId) {
            $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
        });
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

    protected function primaryLoanAccountIdsSubquery()
    {
        return LoanAccount::selectRaw('MAX(id)')->groupBy('loan_application_id');
    }

    protected function listRow(array $row): array
    {
        $amount = (float) ($row['paid_amount'] ?? 0);
        $rawPaid = $row['paid_date'] ?? null;
        $tz = 'Asia/Kolkata';

        $carbon = null;
        if ($rawPaid instanceof Carbon || $rawPaid instanceof \DateTimeInterface) {
            $carbon = Carbon::parse($rawPaid)->timezone($tz);
        } elseif (!empty($rawPaid) && $rawPaid !== 'N/A') {
            try {
                $carbon = Carbon::parse((string) $rawPaid)->timezone($tz);
            } catch (\Throwable) {
            }
        }

        if ($carbon) {
            $sortDate = $carbon->format('Y-m-d H:i:s');
            $paidDate = $carbon->format('d-m-Y');
            $paidTime = $carbon->format('h:i A');
            $paidTimeSec = $carbon->format('h:i:s A');
            $paidDatetime = $carbon->format('d-m-Y h:i A');
        } else {
            $sortDate = '';
            $paidDate = 'N/A';
            $paidTime = '';
            $paidTimeSec = '';
            $paidDatetime = 'N/A';
        }

        $module = $row['module'] ?? 'loan';
        $id = $row['id'] ?? null;

        $row['paid_amount'] = $amount;
        $row['paid_amount_formatted'] = '₹' . number_format($amount, 2);
        $row['payment_method'] = $this->formatMethod($row['payment_method'] ?? null);
        $row['paid_date'] = $paidDate;
        $row['paid_time'] = $paidTime;
        $row['paid_time_sec'] = $paidTimeSec;
        $row['paid_datetime'] = $paidDatetime;
        $row['paid_date_sort'] = $sortDate;
        $row['payment_reference'] = !empty($row['payment_reference']) ? $row['payment_reference'] : 'N/A';
        $row['application_number'] = $row['application_number'] ?? 'N/A';
        $row['location_id'] = $row['location_id'] ?? null;
        $row['module'] = $module;
        $row['module_label'] = $this->moduleLabel($module);
        $printParams = ['module' => $module, 'id' => $id];
        $printUrl = route('payment-receipts.print', $printParams, false);
        $publicUrl = route('public-payment-receipts.print', $printParams, false);
        $bulkKey = trim((string) ($row['bulk_key'] ?? ''));
        $collectionId = $row['collection_id'] ?? null;
        if ($bulkKey !== '') {
            $query = '?bulk=' . urlencode($bulkKey);
            $printUrl .= $query;
            $publicUrl .= $query;
        } elseif (!empty($row['is_bulk'])) {
            $query = '?bulk=1';
            $printUrl .= $query;
            $publicUrl .= $query;
        } elseif ($collectionId) {
            $query = '?collection_id=' . urlencode((string) $collectionId);
            $printUrl .= $query;
            $publicUrl .= $query;
        }
        $row['receipt_url'] = $publicUrl;
        $row['receipt_view_url'] = $publicUrl;
        $row['receipt_print_url'] = $printUrl;

        return $row;
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

    protected function formatMethod(?string $method): string
    {
        $method = trim((string) $method);

        return $method !== '' ? ucfirst(str_replace('_', ' ', $method)) : 'N/A';
    }

    protected function fdTypeLabel(?string $type): string
    {
        return match ($type) {
            'creation' => 'Deposit',
            'monthly_interest' => 'Monthly Interest',
            'maturity' => 'Maturity Payout',
            'premature' => 'Premature Closure',
            'renewal' => 'Renewal',
            'closure' => 'Closure',
            default => $type ? ucfirst(str_replace('_', ' ', $type)) : 'FD Payment',
        };
    }

    /**
     * @return array{label: string, color: string}
     */
    protected function statusMeta(?string $status): array
    {
        $status = strtolower((string) $status);

        return match ($status) {
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            'partial' => ['label' => 'Partial', 'color' => 'info'],
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'secondary'],
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
            default => ['label' => $status !== '' ? ucfirst($status) : 'Paid', 'color' => 'secondary'],
        };
    }

    public function decodeId(mixed $id): int
    {
        $decoded = HashId::decode((string) $id);
        if ($decoded) {
            return $decoded;
        }

        if (is_numeric($id)) {
            return (int) $id;
        }

        abort(404, 'Invalid receipt ID');
    }

    public function resolveAccurateTimestamp(mixed $paidDate, mixed $collectedAt = null, mixed $updatedAt = null, mixed $createdAt = null): Carbon
    {
        $tz = 'Asia/Kolkata';

        // 1. If explicit collectedAt timestamp is provided and has real time (not 00:00:00)
        $carbonColl = null;
        if ($collectedAt) {
            try {
                $carbonColl = Carbon::parse($collectedAt)->timezone($tz);
                if ($carbonColl->hour !== 0 || $carbonColl->minute !== 0 || $carbonColl->second !== 0) {
                    return $carbonColl;
                }
            } catch (\Throwable) {
            }
        }

        // 2. Parse paidDate
        $carbonPaid = null;
        if ($paidDate instanceof Carbon || $paidDate instanceof \DateTimeInterface) {
            $carbonPaid = Carbon::parse($paidDate)->timezone($tz);
        } elseif (!empty($paidDate) && $paidDate !== 'N/A') {
            try {
                $carbonPaid = Carbon::parse((string) $paidDate)->timezone($tz);
            } catch (\Throwable) {
            }
        }

        // If paidDate has time (not 00:00:00)
        if ($carbonPaid && ($carbonPaid->hour !== 0 || $carbonPaid->minute !== 0 || $carbonPaid->second !== 0)) {
            return $carbonPaid;
        }

        $baseDate = $carbonColl ?: $carbonPaid;

        // 3. Fallback to createdAt (time when record was created in database)
        if ($createdAt) {
            try {
                $cCreated = Carbon::parse($createdAt)->timezone($tz);
                if ($cCreated->hour !== 0 || $cCreated->minute !== 0 || $cCreated->second !== 0) {
                    if ($baseDate) {
                        return Carbon::parse($baseDate->toDateString() . ' ' . $cCreated->format('H:i:s'), $tz);
                    }
                    return $cCreated;
                }
            } catch (\Throwable) {
            }
        }

        // 4. Fallback to updatedAt
        if ($updatedAt) {
            try {
                $cUpdated = Carbon::parse($updatedAt)->timezone($tz);
                if ($cUpdated->hour !== 0 || $cUpdated->minute !== 0 || $cUpdated->second !== 0) {
                    if ($baseDate) {
                        return Carbon::parse($baseDate->toDateString() . ' ' . $cUpdated->format('H:i:s'), $tz);
                    }
                    return $cUpdated;
                }
            } catch (\Throwable) {
            }
        }

        return $baseDate ?: now()->timezone($tz);
    }
}
