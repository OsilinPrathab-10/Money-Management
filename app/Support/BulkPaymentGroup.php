<?php

namespace App\Support;

use App\Models\ChitCollection;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\Installment;
use Illuminate\Support\Collection;

/**
 * Groups EMI collections that were paid together in one admin/agent bulk collection.
 *
 * New bulk pays stamp `Group: BULK-xxxxx` in remarks (and often as payment_reference).
 * Older rows are matched by bulk remarks / "Bulk Payment" reference plus a short time window.
 */
class BulkPaymentGroup
{
    public const AGENT_MARKER = '[Agent Bulk Collected]';
    public const ADMIN_MARKER = 'Bulk payment processed';
    public const CHIT_ADMIN_MARKER = '[Bulk day/week collection]';
    public const FAMILY_MARKER = '[Family bulk payment';

    public static function generateKey(): string
    {
        return 'BULK-' . strtoupper(bin2hex(random_bytes(5)));
    }

    public static function appendRemarks(?string $remarks, string $groupKey, string $marker): string
    {
        return trim(trim((string) $remarks) . ' ' . $marker . ' Group: ' . $groupKey);
    }

    public static function extractExplicitKey(?string $remarks, ?string $paymentReference = null): ?string
    {
        if (preg_match('/Group:\s*(\S+)/', (string) $remarks, $matches)) {
            return rtrim($matches[1], '.,;');
        }

        $ref = trim((string) $paymentReference);
        if ($ref !== '' && preg_match('/^(BULK-[A-Za-z0-9]+|FAM-\d+-\d+)$/i', $ref)) {
            return strtoupper($ref);
        }

        return null;
    }

    public static function isBulkPayload(?string $remarks, ?string $paymentReference = null): bool
    {
        $remarks = (string) $remarks;
        $ref = trim((string) $paymentReference);

        return str_contains($remarks, self::AGENT_MARKER)
            || str_contains($remarks, self::ADMIN_MARKER)
            || str_contains($remarks, self::CHIT_ADMIN_MARKER)
            || str_contains($remarks, self::FAMILY_MARKER)
            || str_contains($remarks, '[Bulk EMI Pay]')
            || str_contains($remarks, '[Bulk Chit Pay]')
            || str_contains($remarks, '[Bulk Open Loan Pay]')
            || str_contains($remarks, 'Group: BULK-')
            || str_contains($remarks, 'Group:')
            || str_starts_with(strtoupper($ref), 'BULK-')
            || str_starts_with(strtoupper($ref), 'FAM-')
            || strcasecmp($ref, 'Bulk Payment') === 0;
    }

    public static function isBulk(?EmiCollection $collection): bool
    {
        return $collection && self::isBulkPayload($collection->remarks, $collection->payment_reference);
    }

    public static function isBulkChit(?ChitCollection $collection): bool
    {
        return $collection && self::isBulkPayload($collection->remarks, $collection->payment_reference);
    }

    /**
     * Stable key used to bucket sibling collections. Null = not part of a bulk group.
     */
    public static function groupKey(EmiCollection $collection): ?string
    {
        if (!self::isBulk($collection)) {
            return null;
        }

        $loanId = $collection->emi?->loan_account_id ?? 'x';
        $explicit = self::extractExplicitKey($collection->remarks, $collection->payment_reference);
        if ($explicit) {
            return $explicit . ':loan:' . $loanId;
        }

        $collector = $collection->agent_id
            ? ('a' . $collection->agent_id)
            : ('u' . ($collection->verified_by ?? '0'));
        $bucket = $collection->created_at ? intdiv($collection->created_at->getTimestamp(), 5) : 0;

        return 'HIST-' . $collector . '-' . $bucket . '-loan-' . $loanId;
    }

    public static function findSiblings(EmiCollection $collection): Collection
    {
        if (!self::isBulk($collection)) {
            return collect([$collection]);
        }

        $collection->loadMissing('emi');

        $query = EmiCollection::with(['emi.loanAccount.client', 'agent', 'verifiedBy']);
        $explicit = self::extractExplicitKey($collection->remarks, $collection->payment_reference);

        if ($explicit) {
            $query->where(function ($q) use ($explicit) {
                $q->where('remarks', 'like', '%Group: ' . $explicit . '%')
                    ->orWhere('payment_reference', $explicit);
            });
        } else {
            $start = $collection->created_at?->copy()->subSeconds(5);
            $end = $collection->created_at?->copy()->addSeconds(5);

            $query->where(function ($q) {
                $q->where('remarks', 'like', '%' . self::AGENT_MARKER . '%')
                    ->orWhere('remarks', 'like', '%' . self::ADMIN_MARKER . '%')
                    ->orWhere('payment_reference', 'Bulk Payment')
                    ->orWhere('payment_reference', 'like', 'BULK-%');
            });

            if ($collection->agent_id) {
                $query->where('agent_id', $collection->agent_id);
            } else {
                $query->where(function ($q) use ($collection) {
                    $q->whereNull('agent_id');
                    if ($collection->verified_by) {
                        $q->where('verified_by', $collection->verified_by);
                    }
                });
            }

            if ($start && $end) {
                $query->whereBetween('created_at', [$start, $end]);
            }
        }

        if ($collection->emi?->loan_account_id) {
            $loanId = $collection->emi->loan_account_id;
            $query->whereHas('emi', fn ($q) => $q->where('loan_account_id', $loanId));
        }

        $siblings = $query->orderBy('id')->get();
        $target = self::groupKey($collection);

        $matched = $siblings
            ->filter(fn (EmiCollection $row) => self::groupKey($row) === $target)
            ->values();

        if ($matched->isEmpty()) {
            return collect([$collection]);
        }

        if (!$matched->contains(fn (EmiCollection $row) => (int) $row->id === (int) $collection->id)) {
            $matched->push($collection);
        }

        return $matched->sortBy('id')->values();
    }

    public static function findChitSiblings(ChitCollection $collection): Collection
    {
        if (!self::isBulkChit($collection)) {
            return collect([$collection]);
        }

        $query = ChitCollection::with(['installment.member.shares', 'installment.sharePayments', 'installment.group', 'agent', 'client']);
        $explicit = self::extractExplicitKey($collection->remarks, $collection->payment_reference);

        if ($explicit) {
            $query->where(function ($q) use ($explicit) {
                $q->where('remarks', 'like', '%Group: ' . $explicit . '%')
                    ->orWhere('payment_reference', $explicit);
            });
        } else {
            $start = $collection->created_at?->copy()->subSeconds(5);
            $end = $collection->created_at?->copy()->addSeconds(5);

            $query->where(function ($q) {
                $q->where('remarks', 'like', '%' . self::AGENT_MARKER . '%')
                    ->orWhere('remarks', 'like', '%' . self::ADMIN_MARKER . '%')
                    ->orWhere('remarks', 'like', '%' . self::CHIT_ADMIN_MARKER . '%')
                    ->orWhere('payment_reference', 'Bulk Payment')
                    ->orWhere('payment_reference', 'like', 'BULK-%');
            });

            if ($collection->agent_id) {
                $query->where('agent_id', $collection->agent_id);
            } else {
                $query->where(function ($q) use ($collection) {
                    $q->whereNull('agent_id');
                    if ($collection->verified_by) {
                        $q->where('verified_by', $collection->verified_by);
                    }
                });
            }

            if ($start && $end) {
                $query->whereBetween('created_at', [$start, $end]);
            }
        }

        if ($collection->group_id) {
            $query->where('group_id', $collection->group_id);
        }
        if ($collection->member_id) {
            $query->where('member_id', $collection->member_id);
        }

        $siblings = $query->orderBy('id')->get();
        $target = self::chitGroupKey($collection);

        $matched = $siblings
            ->filter(fn (ChitCollection $row) => self::chitGroupKey($row) === $target)
            ->values();

        if ($matched->isEmpty()) {
            return collect([$collection]);
        }

        if (!$matched->contains(fn (ChitCollection $row) => (int) $row->id === (int) $collection->id)) {
            $matched->push($collection);
        }

        return $matched->sortBy('id')->values();
    }

    /**
     * Group a loaded collection set in memory (preserves original order of first-seen groups).
     *
     * @return Collection<int, array{lead: EmiCollection, items: Collection, is_bulk: bool}>
     */
    public static function groupCollections(Collection $collections): Collection
    {
        $buckets = [];
        foreach ($collections as $collection) {
            $key = self::groupKey($collection);
            if ($key) {
                $buckets[$key][] = $collection;
            }
        }

        $used = [];
        $grouped = collect();

        foreach ($collections as $collection) {
            if (isset($used[$collection->id])) {
                continue;
            }

            $key = self::groupKey($collection);
            $bucket = ($key && isset($buckets[$key])) ? collect($buckets[$key]) : collect([$collection]);
            $isBulk = $bucket->count() > 1;

            if (!$isBulk) {
                $used[$collection->id] = true;
                $grouped->push([
                    'lead' => $collection,
                    'items' => collect([$collection]),
                    'is_bulk' => false,
                ]);
                continue;
            }

            $items = $bucket->sortBy('id')->values();
            foreach ($items as $item) {
                $used[$item->id] = true;
            }

            $grouped->push([
                'lead' => $items->first(),
                'items' => $items,
                'is_bulk' => true,
            ]);
        }

        return $grouped;
    }

    public static function emiNumbers(Collection $collections): array
    {
        return $collections
            ->map(fn (EmiCollection $row) => $row->emi?->instalment_number)
            ->filter(fn ($number) => $number !== null && $number !== '')
            ->map(fn ($number) => (int) $number)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function emiSplitLabel(Collection $collections): string
    {
        $numbers = collect(self::emiNumbers($collections));

        if ($numbers->isEmpty()) {
            $count = $collections->count();

            return $count > 1 ? ('Multiple (' . $count . ' EMIs)') : 'N/A';
        }

        return 'EMI #' . $numbers->implode(', #');
    }

    /**
     * @return array<int, array{instalment_number: int|null, amount: float}>
     */
    public static function emiSplits(Collection $collections): array
    {
        return $collections
            ->sortBy(fn (EmiCollection $row) => (int) ($row->emi?->instalment_number ?? $row->emi_id ?? 0))
            ->map(fn (EmiCollection $row) => [
                'instalment_number' => $row->emi?->instalment_number,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();
    }

    public static function combinedStatus(Collection $collections): string
    {
        $statuses = $collections->pluck('status')->filter()->unique()->values();
        if ($statuses->contains('in_progress') || $statuses->contains('pending')) {
            return 'in_progress';
        }
        if ($statuses->contains('rejected')) {
            return 'rejected';
        }

        return (string) ($statuses->first() ?? 'verified');
    }

    public static function combinedPaymentType(Collection $collections): string
    {
        $types = $collections->pluck('payment_type')->filter()->unique()->values();
        if ($types->contains('partial')) {
            return 'partial';
        }
        if ($types->contains('overdue')) {
            return 'overdue';
        }

        return (string) ($types->first() ?? 'full');
    }

    public static function chitGroupKey(ChitCollection $collection): ?string
    {
        if (!self::isBulkChit($collection)) {
            return null;
        }

        $scope = ($collection->group_id ?? 'x') . ':m' . ($collection->member_id ?? 'x');
        $explicit = self::extractExplicitKey($collection->remarks, $collection->payment_reference);
        if ($explicit) {
            return $explicit . ':chit:' . $scope;
        }

        $collector = $collection->agent_id
            ? ('a' . $collection->agent_id)
            : ('u' . ($collection->verified_by ?? '0'));
        $bucket = $collection->created_at ? intdiv($collection->created_at->getTimestamp(), 5) : 0;

        return 'HIST-' . $collector . '-' . $bucket . '-chit-' . $scope;
    }

    /**
     * @return Collection<int, array{lead: ChitCollection, items: Collection, is_bulk: bool}>
     */
    public static function groupChitCollections(Collection $collections): Collection
    {
        $buckets = [];
        foreach ($collections as $collection) {
            $key = self::chitGroupKey($collection);
            if ($key) {
                $buckets[$key][] = $collection;
            }
        }

        $used = [];
        $grouped = collect();

        foreach ($collections as $collection) {
            if (isset($used[$collection->id])) {
                continue;
            }

            $key = self::chitGroupKey($collection);
            $bucket = ($key && isset($buckets[$key])) ? collect($buckets[$key]) : collect([$collection]);
            $isBulk = $bucket->count() > 1;

            if (!$isBulk) {
                $used[$collection->id] = true;
                $grouped->push([
                    'lead' => $collection,
                    'items' => collect([$collection]),
                    'is_bulk' => false,
                ]);
                continue;
            }

            $items = $bucket->sortBy('id')->values();
            foreach ($items as $item) {
                $used[$item->id] = true;
            }

            $grouped->push([
                'lead' => $items->first(),
                'items' => $items,
                'is_bulk' => true,
            ]);
        }

        return $grouped;
    }

    /**
     * Latest in-progress collection, or the latest verified bulk collection.
     */
    public static function displayChitCollection(Installment $installment): ?ChitCollection
    {
        $pool = self::installmentCollectionPool($installment);
        if ($pool->isEmpty()) {
            return null;
        }

        $pending = $pool->first(fn (ChitCollection $row) => in_array($row->status, ['in_progress', 'pending'], true));
        if ($pending) {
            return $pending;
        }

        return $pool
            ->filter(fn (ChitCollection $row) => in_array($row->status, ['verified', 'paid'], true) && self::isBulkChit($row))
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Amount that belongs to this installment inside its current collection/bulk group.
     */
    public static function installmentBulkAmount(Installment $installment): float
    {
        $collection = self::displayChitCollection($installment);
        if ($collection) {
            return round((float) $collection->amount, 2);
        }

        return round((float) $installment->paid_amount, 2);
    }

    public static function chitInstallmentTotal(Collection $installments): float
    {
        return round((float) $installments->sum(fn (Installment $row) => self::installmentBulkAmount($row)), 2);
    }

    public static function installmentGroupKey(Installment $installment): ?string
    {
        $scope = ($installment->group_id ?? 'x') . ':m' . ($installment->member_id ?? 'x');
        $collection = self::displayChitCollection($installment);

        if ($collection && self::isBulkChit($collection)) {
            $key = self::chitGroupKey($collection);
            if ($key) {
                return $key;
            }
        }

        if (self::isBulkPayload($installment->remarks, $installment->reference_no)) {
            $explicit = self::extractExplicitKey($installment->remarks, $installment->reference_no);
            if ($explicit) {
                return $explicit . ':chit:' . $scope;
            }

            $ref = trim((string) ($installment->reference_no ?? ''));
            if ($ref !== '') {
                return $ref . ':chit:' . $scope;
            }

            $paid = $installment->paid_date?->format('Ymd') ?? 'x';

            return 'HIST-INST-' . $paid . '-chit-' . $scope;
        }

        return null;
    }

    /**
     * @return Collection<int, ChitCollection>
     */
    protected static function installmentCollectionPool(Installment $installment): Collection
    {
        if ($installment->relationLoaded('collections') && $installment->collections->isNotEmpty()) {
            return $installment->collections->sortByDesc('id')->values();
        }

        if ($installment->relationLoaded('pendingCollections') && $installment->pendingCollections->isNotEmpty()) {
            return $installment->pendingCollections->sortByDesc('id')->values();
        }

        return $installment->collections()->orderByDesc('id')->get();
    }

    /**
     * @return Collection<int, array{lead: Installment, items: Collection, is_bulk: bool}>
     */
    public static function groupInstallments(Collection $installments): Collection
    {
        $buckets = [];
        foreach ($installments as $installment) {
            $key = self::installmentGroupKey($installment);
            if ($key) {
                $buckets[$key][] = $installment;
            }
        }

        $used = [];
        $grouped = collect();

        foreach ($installments as $installment) {
            if (isset($used[$installment->id])) {
                continue;
            }

            $key = self::installmentGroupKey($installment);
            $bucket = ($key && isset($buckets[$key])) ? collect($buckets[$key]) : collect([$installment]);
            $isBulk = $bucket->count() > 1;

            if (!$isBulk) {
                $used[$installment->id] = true;
                $grouped->push([
                    'lead' => $installment,
                    'items' => collect([$installment]),
                    'is_bulk' => false,
                ]);
                continue;
            }

            $items = $bucket->sortBy('month_number')->values();
            foreach ($items as $item) {
                $used[$item->id] = true;
            }

            $grouped->push([
                'lead' => $items->first(),
                'items' => $items,
                'is_bulk' => true,
            ]);
        }

        return $grouped;
    }

    public static function findInstallmentSiblings(Installment $installment): Collection
    {
        $key = self::installmentGroupKey($installment);
        if (!$key) {
            return collect([$installment]);
        }

        $siblings = Installment::with([
            'member.client',
            'group',
            'collections',
            'pendingCollections.agent',
            'pendingCollections.client',
        ])
            ->where('group_id', $installment->group_id)
            ->where('member_id', $installment->member_id)
            ->get()
            ->filter(fn (Installment $row) => self::installmentGroupKey($row) === $key)
            ->sortBy('month_number')
            ->values();

        if ($siblings->isEmpty()) {
            return collect([$installment]);
        }

        if (!$siblings->contains(fn (Installment $row) => (int) $row->id === (int) $installment->id)) {
            $siblings->push($installment);
        }

        return $siblings->sortBy('month_number')->values();
    }

    public static function chitInstallmentSplitLabel(Collection $installments): string
    {
        $groupCode = $installments->first()?->group?->group_code ?? 'Chit';
        $months = $installments
            ->map(fn (Installment $row) => $row->month_number)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($months->isEmpty()) {
            $count = $installments->count();

            return $count > 1 ? ($groupCode . ' - Bulk (' . $count . ')') : $groupCode;
        }

        return $groupCode . ' - Inst #' . $months->implode(', #');
    }

    /**
     * @return array<int, array{instalment_number: int|null, amount: float}>
     */
    public static function chitInstallmentSplits(Collection $installments): array
    {
        return $installments
            ->sortBy(fn (Installment $row) => (int) ($row->month_number ?? 0))
            ->map(fn (Installment $row) => [
                'instalment_number' => $row->month_number,
                'amount' => self::installmentBulkAmount($row),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function postedStatuses(): array
    {
        return ['verified', 'paid', 'completed'];
    }

    public static function chitCollectionTotal(Collection $collections): float
    {
        return round((float) $collections->sum(fn ($row) => (float) ($row->amount ?? 0)), 2);
    }

    /**
     * @return array<int, array{instalment_number: int|null, amount: float}>
     */
    public static function chitCollectionSplits(Collection $collections): array
    {
        return $collections
            ->sortBy(fn (ChitCollection $row) => (int) ($row->installment?->month_number ?? $row->id ?? 0))
            ->map(fn (ChitCollection $row) => [
                'instalment_number' => $row->installment?->month_number,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();
    }

    public static function chitCollectionSplitLabel(Collection $collections): string
    {
        $installments = $collections
            ->map(fn (ChitCollection $row) => $row->installment)
            ->filter();

        if ($installments->isEmpty()) {
            $count = $collections->count();

            return $count > 1 ? ('Bulk (' . $count . ')') : 'Chit';
        }

        return self::chitInstallmentSplitLabel($installments);
    }

    public static function latestPostedBulkChitCollection(Installment $installment, ?string $bulkKey = null): ?ChitCollection
    {
        $pool = self::installmentCollectionPool($installment)
            ->filter(fn (ChitCollection $row) => in_array($row->status, self::postedStatuses(), true) && self::isBulkChit($row));

        if ($bulkKey && $bulkKey !== '1' && strcasecmp($bulkKey, 'true') !== 0) {
            $pool = $pool->filter(function (ChitCollection $row) use ($bulkKey) {
                $key = self::extractExplicitKey($row->remarks, $row->payment_reference);

                return $key && strcasecmp($key, $bulkKey) === 0;
            });
        }

        return $pool->sortByDesc('id')->first();
    }

    public static function latestPostedBulkLoanCollection(Emi $emi, ?string $bulkKey = null): ?EmiCollection
    {
        $emi->loadMissing('collections.emi');
        $pool = $emi->collections
            ->filter(fn (EmiCollection $row) => in_array($row->status, self::postedStatuses(), true) && self::isBulk($row));

        if ($bulkKey && $bulkKey !== '1' && strcasecmp($bulkKey, 'true') !== 0) {
            $pool = $pool->filter(function (EmiCollection $row) use ($bulkKey) {
                $key = self::extractExplicitKey($row->remarks, $row->payment_reference);

                return $key && strcasecmp($key, $bulkKey) === 0;
            });
        }

        return $pool->sortByDesc('id')->first();
    }
}
