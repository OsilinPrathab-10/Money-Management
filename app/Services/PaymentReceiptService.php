<?php

namespace App\Services;

use App\Http\Controllers\EmiController;
use App\Models\ChitCollection;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\FixedDepositTransaction;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Support\BulkPaymentGroup;
use App\Support\DateRangePreset;
use App\Support\HashId;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PaymentReceiptService
{
    public const MODULES = ['all', 'loan', 'chit', 'fd'];

    public const PRINT_MODULES = ['loan', 'chit', 'fd'];

    public const FD_TYPES = [
        'creation',
        'monthly_interest',
        'maturity',
        'premature',
        'renewal',
        'closure',
        'chit_adjustment',
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
            'chit' => $this->chitStats($request),
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
            'chit' => $this->chitRows($request),
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
    public function payload(string $module, mixed $id, ?string $bulkKey = null): array
    {
        $module = in_array(strtolower(trim((string) $module)), self::PRINT_MODULES, true)
            ? strtolower(trim((string) $module))
            : 'loan';
        $decodedId = $this->decodeId($id);
        $bulkKey = $bulkKey ? strtoupper(trim($bulkKey)) : null;

        return match ($module) {
            'chit' => $this->chitPayload($decodedId, $bulkKey),
            'fd' => $this->fdPayload($decodedId),
            default => $this->loanPayload($decodedId, $bulkKey),
        };
    }

    public function accountHeading(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'all' => 'Account / Ref',
            'chit' => 'Group Code',
            'fd' => 'FD Number',
            default => 'Application No.',
        };
    }

    public function moduleTitle(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'all' => 'All Payment Receipts',
            'chit' => 'Chit Payment Receipts',
            'fd' => 'FD Payment Receipts',
            default => 'Loan Payment Receipts',
        };
    }

    public function moduleLabel(string $module): string
    {
        return match ($this->normalizeModule($module)) {
            'chit' => 'Chit',
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
        $parts = [$this->loanStats($request), $this->chitStats($request), $this->fdStats($request)];

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
            $this->chitRows($request),
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

        return [
            'total_receipts' => (clone $base())->count(),
            'total_collected' => (clone $base())->sum('paid_amount'),
            'month_collected' => (clone $base())
                ->whereMonth('paid_date', now()->month)
                ->whereYear('paid_date', now()->year)
                ->sum('paid_amount'),
            'today_collected' => (clone $base())->whereDate('paid_date', $today)->sum('paid_amount'),
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

            $singleCollection = $collections->where('emi_id', $emi->id)->sortByDesc('id')->first();
            $paidAt = $this->resolveAccurateTimestamp(
                $emi->paid_date,
                $singleCollection?->collected_at ?: $singleCollection?->created_at,
                $emi->updated_at,
                $emi->created_at
            );

            $rows[] = $this->listRow([
                'id' => $emi->getRouteKey(),
                'receipt_number' => 'RCP-' . str_pad((string) $emi->id, 6, '0', STR_PAD_LEFT),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'location_id' => optional($client)->location_id,
                'application_number' => $applicationNumber,
                'paid_amount' => $emi->paid_amount,
                'payment_method' => $singleCollection?->payment_method ?: $emi->payment_method,
                'paid_date' => $paidAt,
                'payment_reference' => $singleCollection?->payment_reference ?: $emi->payment_reference,
                'is_bulk' => false,
                'emi_split' => $emi->instalment_number ? ('EMI #' . $emi->instalment_number) : null,
                'module' => 'loan',
            ]);
        }

        usort($rows, function (array $left, array $right) {
            return strcmp((string) ($right['paid_date_sort'] ?? ''), (string) ($left['paid_date_sort'] ?? ''));
        });

        return $this->numberRows($rows);
    }

    protected function loanPayload(int $id, ?string $bulkKey = null): array
    {
        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
            'collections.emi',
        ])->findOrFail($id);

        $payload = app(EmiController::class)->buildReceiptPayload($emi, $bulkKey);
        $payload['module'] = 'loan';
        $payload['account_label'] = $payload['account_label'] ?? 'Loan ID';
        $payload['start_date_label'] = $payload['start_date_label'] ?? 'Disbursement Date';
        $payload['receipt_title'] = $payload['receipt_title'] ?? 'LOAN PAYMENT RECEIPT';

        return $payload;
    }

    protected function chitStats(Request $request): array
    {
        $base = fn () => $this->chitBaseQuery($request);
        $today = Carbon::now()->toDateString();

        return [
            'total_receipts' => (clone $base())->count(),
            'total_collected' => (clone $base())->sum('paid_amount'),
            'month_collected' => (clone $base())
                ->whereMonth('paid_date', now()->month)
                ->whereYear('paid_date', now()->year)
                ->sum('paid_amount'),
            'today_collected' => (clone $base())->whereDate('paid_date', $today)->sum('paid_amount'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function chitRows(Request $request): array
    {
        $query = $this->chitBaseQuery($request)
            ->with(['member.client.location', 'group', 'pendingCollections', 'collections']);

        if ($request->filled('payment_method')) {
            $query->where('payment_mode', $request->payment_method);
        }

        $this->applyDateFilter($query, $request, 'paid_date');

        $installments = $query->orderByDesc('paid_date')->orderByDesc('id')->get();
        $installmentIds = $installments->pluck('id')->filter()->all();
        $collections = collect();
        if ($installmentIds) {
            $collections = ChitCollection::with(['installment.member.client.location', 'installment.group', 'client'])
                ->whereIn('installment_id', $installmentIds)
                ->whereIn('status', BulkPaymentGroup::postedStatuses())
                ->get();
        }

        $bulkInstallmentIds = [];
        $rows = [];
        foreach (BulkPaymentGroup::groupChitCollections($collections) as $group) {
            if (empty($group['is_bulk'])) {
                continue;
            }

            $items = $group['items'];
            /** @var ChitCollection $leadCollection */
            $leadCollection = $group['lead'];
            $siblings = BulkPaymentGroup::findChitSiblings($leadCollection)
                ->filter(fn (ChitCollection $row) => in_array($row->status, BulkPaymentGroup::postedStatuses(), true));
            if ($siblings->count() < 2) {
                $siblings = $items;
            }
            if ($siblings->count() < 2) {
                continue;
            }

            $lead = $installments->firstWhere('id', $leadCollection->installment_id)
                ?? $siblings->first()?->installment
                ?? $leadCollection->installment;
            if (!$lead) {
                continue;
            }

            foreach ($siblings as $collection) {
                if ($collection->installment_id) {
                    $bulkInstallmentIds[$collection->installment_id] = true;
                }
            }

            $client = $lead->member?->client ?? $leadCollection->client;
            $chitGroup = $lead->group ?? $leadCollection->installment?->group;
            $bulkKey = BulkPaymentGroup::extractExplicitKey($leadCollection->remarks, $leadCollection->payment_reference);
            $paidAt = $this->resolveAccurateTimestamp(
                $lead->paid_date,
                $leadCollection->collected_at ?: $leadCollection->created_at,
                $lead->updated_at,
                $lead->created_at
            );

            $rows[] = $this->listRow([
                'id' => HashId::encode($lead->id),
                'receipt_number' => 'RCP-B-' . str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'location_id' => optional($client)->location_id,
                'application_number' => $chitGroup?->group_code ?? 'N/A',
                'paid_amount' => BulkPaymentGroup::chitCollectionTotal($siblings),
                'payment_method' => $leadCollection->payment_method ?: $lead->payment_mode,
                'paid_date' => $paidAt,
                'payment_reference' => $leadCollection->payment_reference ?: $lead->reference_no,
                'is_bulk' => true,
                'bulk_key' => $bulkKey,
                'emi_split' => BulkPaymentGroup::chitCollectionSplitLabel($siblings),
                'emi_splits' => BulkPaymentGroup::chitCollectionSplits($siblings),
                'emi_count' => $siblings->count(),
                'module' => 'chit',
            ]);
        }

        $remainingInstallments = $installments->filter(fn ($inst) => !isset($bulkInstallmentIds[$inst->id]))->values();
        foreach (BulkPaymentGroup::groupInstallments($remainingInstallments) as $group) {
            if (empty($group['is_bulk'])) {
                continue;
            }

            $items = $group['items'];
            $lead = $group['lead'];
            $siblings = BulkPaymentGroup::findInstallmentSiblings($lead);
            if ($siblings->count() < 2) {
                $siblings = $items;
            }
            if ($siblings->count() < 2) {
                continue;
            }

            foreach ($siblings as $sib) {
                $bulkInstallmentIds[$sib->id] = true;
            }

            $client = $lead->member?->client;
            $chitGroup = $lead->group;
            $bulkKey = BulkPaymentGroup::extractExplicitKey($lead->remarks, $lead->reference_no);
            $leadColl = $collections->where('installment_id', $lead->id)->sortByDesc('id')->first();
            $paidAt = $this->resolveAccurateTimestamp(
                $lead->paid_date,
                $leadColl?->collected_at ?: $leadColl?->created_at,
                $lead->updated_at,
                $lead->created_at
            );

            $rows[] = $this->listRow([
                'id' => HashId::encode($lead->id),
                'receipt_number' => 'RCP-B-' . str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'location_id' => optional($client)->location_id,
                'application_number' => $chitGroup?->group_code ?? 'N/A',
                'paid_amount' => BulkPaymentGroup::chitInstallmentTotal($siblings),
                'payment_method' => $leadColl?->payment_method ?: $lead->payment_mode,
                'paid_date' => $paidAt,
                'payment_reference' => $leadColl?->payment_reference ?: $lead->reference_no,
                'is_bulk' => true,
                'bulk_key' => $bulkKey,
                'emi_split' => BulkPaymentGroup::chitInstallmentSplitLabel($siblings),
                'emi_splits' => BulkPaymentGroup::chitInstallmentSplits($siblings),
                'emi_count' => $siblings->count(),
                'module' => 'chit',
            ]);
        }

        foreach ($installments as $lead) {
            if (isset($bulkInstallmentIds[$lead->id])) {
                continue;
            }

            $client = $lead->member?->client;
            $chitGroup = $lead->group;
            $leadColl = $collections->where('installment_id', $lead->id)->sortByDesc('id')->first();
            $paidAt = $this->resolveAccurateTimestamp(
                $lead->paid_date,
                $leadColl?->collected_at ?: $leadColl?->created_at,
                $lead->updated_at,
                $lead->created_at
            );

            $rows[] = $this->listRow([
                'id' => HashId::encode($lead->id),
                'receipt_number' => $leadColl?->payment_reference ?: ($lead->reference_no ?: ('RCP-' . str_pad((string) $lead->id, 6, '0', STR_PAD_LEFT))),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'location_id' => optional($client)->location_id,
                'application_number' => $chitGroup?->group_code ?? 'N/A',
                'paid_amount' => $lead->paid_amount,
                'payment_method' => $leadColl?->payment_method ?: $lead->payment_mode,
                'paid_date' => $paidAt,
                'payment_reference' => $leadColl?->payment_reference ?: $lead->reference_no,
                'is_bulk' => false,
                'emi_split' => $lead->month_number ? ('Inst #' . $lead->month_number) : null,
                'module' => 'chit',
            ]);
        }

        usort($rows, function (array $left, array $right) {
            return strcmp((string) ($right['paid_date_sort'] ?? ''), (string) ($left['paid_date_sort'] ?? ''));
        });

        return $this->numberRows($rows);
    }

    protected function chitPayload(int $id, ?string $bulkKey = null): array
    {
        $installment = Installment::withTrashed()->with([
            'member.client',
            'group',
            'pendingCollections',
            'collections.installment',
        ])->findOrFail($id);

        $leadCollection = BulkPaymentGroup::latestPostedBulkChitCollection($installment, $bulkKey);
        $siblingCollections = $leadCollection
            ? BulkPaymentGroup::findChitSiblings($leadCollection)->filter(
                fn (ChitCollection $row) => in_array($row->status, BulkPaymentGroup::postedStatuses(), true)
            )
            : collect();

        $siblingInstallments = collect();
        if ($siblingCollections->count() <= 1) {
            $siblingInstallments = BulkPaymentGroup::findInstallmentSiblings($installment);
            if ($bulkKey && $bulkKey !== '1' && strcasecmp($bulkKey, 'true') !== 0) {
                $siblingInstallments = $siblingInstallments->filter(function (Installment $row) use ($bulkKey) {
                    $key = BulkPaymentGroup::extractExplicitKey($row->remarks, $row->reference_no);

                    return $key && strcasecmp($key, $bulkKey) === 0;
                });
            }
        }

        $forceSingle = request()->query('single') || request()->query('bulk') === '0';
        $isBulk = $forceSingle ? false : ($siblingCollections->count() > 1 || $siblingInstallments->count() > 1);

        $client = $installment->member?->client;
        $group = $installment->group;

        $items = [];
        if ($isBulk) {
            if ($siblingCollections->count() > 1) {
                $leadId = $siblingCollections->first()?->installment_id ?? $installment->id;
                $splits = BulkPaymentGroup::chitCollectionSplits($siblingCollections);
                $paidAmount = BulkPaymentGroup::chitCollectionTotal($siblingCollections);
                $totalAmount = $paidAmount;
                $instalmentLabel = BulkPaymentGroup::chitCollectionSplitLabel($siblingCollections);
                $resolvedPaidAt = $this->resolveAccurateTimestamp(
                    $installment->paid_date,
                    $leadCollection?->collected_at ?: $leadCollection?->created_at,
                    $installment->updated_at,
                    $installment->created_at
                );
                $paymentMethod = $leadCollection?->payment_method ?: $installment->payment_mode;
                $paymentReference = $leadCollection?->payment_reference ?: $installment->reference_no;

                $sortedColls = $siblingCollections->sortBy(fn (ChitCollection $row) => (int) ($row->installment?->month_number ?? $row->installment_id ?? 0))->values();
                foreach ($sortedColls as $c) {
                    $inst = $c->installment;
                    $cGroup = $inst?->group ?? $group;
                    $cClient = $c->client ?? $inst?->member?->client ?? $client;
                    $items[] = [
                        'type' => 'chit',
                        'account_number' => $cGroup?->group_code ?? 'Chit',
                        'client_name' => $cClient?->client_name ?? ($client->client_name ?? 'N/A'),
                        'instalment_no' => 'Inst #' . ($inst?->month_number ?? 1),
                        'due_amount' => round((float) ($inst?->amount ?? $c->amount), 2),
                        'paid_amount' => round((float) $c->amount, 2),
                    ];
                }
            } else {
                $leadId = $siblingInstallments->first()?->id ?? $installment->id;
                $splits = BulkPaymentGroup::chitInstallmentSplits($siblingInstallments);
                $paidAmount = BulkPaymentGroup::chitInstallmentTotal($siblingInstallments);
                $totalAmount = $paidAmount;
                $instalmentLabel = BulkPaymentGroup::chitInstallmentSplitLabel($siblingInstallments);
                $latestColl = $installment->collections ? $installment->collections->filter(fn ($c) => in_array($c->status, BulkPaymentGroup::postedStatuses(), true))->sortByDesc('id')->first() : null;
                $resolvedPaidAt = $this->resolveAccurateTimestamp(
                    $installment->paid_date,
                    $latestColl?->collected_at ?: $latestColl?->created_at,
                    $installment->updated_at,
                    $installment->created_at
                );
                $paymentMethod = $installment->payment_mode;
                $paymentReference = $installment->reference_no;

                $sortedInsts = $siblingInstallments->sortBy('month_number')->values();
                foreach ($sortedInsts as $inst) {
                    $instGroup = $inst->group ?? $group;
                    $instClient = $inst->member?->client ?? $client;
                    $items[] = [
                        'type' => 'chit',
                        'account_number' => $instGroup?->group_code ?? 'Chit',
                        'client_name' => $instClient?->client_name ?? ($client->client_name ?? 'N/A'),
                        'instalment_no' => 'Inst #' . ($inst->month_number ?? 1),
                        'due_amount' => round((float) ($inst->amount ?? $inst->paid_amount), 2),
                        'paid_amount' => round(BulkPaymentGroup::installmentBulkAmount($inst), 2),
                    ];
                }
            }

            $overdueAmount = 0.0;
            $status = 'paid';
            $statusMeta = $this->statusMeta('paid');
            $receiptNumber = 'RCP-B-' . str_pad((string) $leadId, 6, '0', STR_PAD_LEFT);
            $receiptTitle = 'CHIT BULK PAYMENT RECEIPT';
        } else {
            $splits = [];
            $paidAmount = (float) ($installment->paid_amount ?? 0);
            $totalAmount = (float) ($installment->amount ?? $paidAmount);
            $overdueAmount = (float) ($installment->penalty_amount ?? 0);
            $status = (string) $installment->status;
            $statusMeta = $this->statusMeta($status);
            $latestColl = $installment->collections ? $installment->collections->filter(fn ($c) => in_array($c->status, BulkPaymentGroup::postedStatuses(), true))->sortByDesc('id')->first() : null;
            $resolvedPaidAt = $this->resolveAccurateTimestamp(
                $installment->paid_date,
                $latestColl?->collected_at ?: $latestColl?->created_at,
                $installment->updated_at,
                $installment->created_at
            );
            $paymentMethod = $latestColl?->payment_method ?: $installment->payment_mode;
            $paymentReference = $latestColl?->payment_reference ?: $installment->reference_no;
            $instalmentLabel = $installment->month_number ? ('Inst #' . $installment->month_number) : 'N/A';
            $receiptNumber = $installment->reference_no ?: ('RCP-' . str_pad((string) $installment->id, 6, '0', STR_PAD_LEFT));
            $receiptTitle = 'CHIT PAYMENT RECEIPT';
        }

        $paymentDate = $resolvedPaidAt ? $resolvedPaidAt->format('d-m-Y h:i A') : 'N/A';
        $paymentDateOnly = $resolvedPaidAt ? $resolvedPaidAt->format('d-m-Y') : 'N/A';
        $paymentTimeOnly = $resolvedPaidAt ? $resolvedPaidAt->format('h:i:s A') : '';
        $outstandingAmount = $isBulk ? 0 : max(0, ($totalAmount + $overdueAmount) - $paidAmount);

        return [
            'id' => HashId::encode($installment->id),
            'receipt_number' => $receiptNumber,
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $group?->group_code ?? 'N/A',
            'account_number' => $group?->group_code,
            'loan_product' => 'Chit Group: ' . ($group?->group_name ?? 'N/A'),
            'instalment_number' => $installment->month_number,
            'instalment_label' => $instalmentLabel,
            'principal_amount' => $totalAmount,
            'interest_amount' => 0,
            'emi_amount' => $totalAmount,
            'overdue_amount' => $overdueAmount,
            'show_overdue' => $overdueAmount > 0,
            'total_amount_display' => $paidAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'payment_method' => $this->formatMethod($paymentMethod),
            'payment_reference' => $paymentReference ?: 'N/A',
            'paid_date' => $paymentDate,
            'paid_date_only' => $paymentDateOnly,
            'paid_time' => $paymentTimeOnly,
            'paid_time_only' => $paymentTimeOnly,
            'disbursed_date' => $group && $group->start_date ? $group->start_date->format('d-m-Y h:i A') : 'N/A',
            'status' => $status,
            'status_label' => $isBulk ? 'Bulk Paid' : $statusMeta['label'],
            'status_color' => $statusMeta['color'],
            'remarks' => $installment->remarks ?? '-',
            'is_bulk' => $isBulk,
            'items' => $items,
            'emi_splits' => $splits,
            'split_item_label' => 'Inst',
            'module' => 'chit',
            'account_label' => 'Group Code',
            'start_date_label' => 'Group Start Date',
            'receipt_title' => $receiptTitle,
        ];
    }

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
            'overdue_amount' => $penalty,
            'show_overdue' => $penalty > 0,
            'total_amount_display' => $amount,
            'paid_amount' => $amount,
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

    protected function chitBaseQuery(Request $request)
    {
        $query = Installment::withTrashed()
            ->whereHas('group')
            ->whereHas('member')
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0);

        $this->applyAgentScope($query, $request, 'chit');
        $this->applyClientZoneFilters($query, $request, 'chit');

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
            'chit' => 'member.client',
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

        if ($module === 'chit') {
            $query->whereHas('member', function ($mq) use ($agentId) {
                $mq->where('referred_by_agent_id', $agentId)
                    ->orWhereHas('client', function ($cq) use ($agentId) {
                        $cq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                    });
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
        if ($bulkKey !== '') {
            $query = '?bulk=' . urlencode($bulkKey);
            $printUrl .= $query;
            $publicUrl .= $query;
        } elseif (!empty($row['is_bulk'])) {
            $query = '?bulk=1';
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
            'chit_adjustment' => 'Chit Adjustment',
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
