<?php

namespace App\Support;

use App\Models\Emi;
use App\Models\EmiCollection;
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
            || str_contains($remarks, self::FAMILY_MARKER)
            || str_contains($remarks, '[Bulk EMI Pay]')
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

    /**
     * @return array<int, string>
     */
    public static function postedStatuses(): array
    {
        return ['verified', 'paid', 'completed'];
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
