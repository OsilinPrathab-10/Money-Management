<?php

namespace App\Support;

use App\Models\EmiCollection;
use Illuminate\Support\Collection;

/**
 * Client ledger IN rows: keep the original incoming receipt amount,
 * and list EMI splits inside that row when one payment covers several.
 */
class ClientLedgerEntries
{
    /**
     * @param  Collection<int, EmiCollection>  $emiCollections
     * @return array<int, array<string, mixed>>
     */
    public static function incomingPaymentRows(
        Collection $emiCollections,
        mixed $p2 = null,
        mixed $p3 = null,
        ?int $p4 = null
    ): array {
        $clientId = is_int($p2) ? $p2 : ($p4 ?? (is_numeric($p2) ? (int) $p2 : 0));
        $buckets = [];

        foreach ($emiCollections as $collection) {
            $key = self::emiIncomingKey($collection, $emiCollections);
            $buckets[$key]['emi'][] = $collection;
        }

        $rows = [];
        foreach ($buckets as $items) {
            $row = self::buildRow(
                collect($items['emi'] ?? []),
                $clientId
            );
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public static function postedCollectionStatuses(): array
    {
        return ['verified', 'completed', 'paid'];
    }

    public static function extractExplicitKey(?string $remarks, ?string $paymentReference = null): ?string
    {
        return BulkPaymentGroup::extractExplicitKey($remarks, $paymentReference);
    }

    public static function incomingKey(?string $remarks, ?string $paymentReference = null): ?string
    {
        return self::extractExplicitKey($remarks, $paymentReference);
    }

    public static function emiIncomingKey(EmiCollection $collection, ?Collection $allCollections = null): string
    {
        $explicit = self::incomingKey($collection->remarks, $collection->payment_reference);
        if ($explicit) {
            return $explicit . ':emi';
        }

        if (BulkPaymentGroup::isBulkLoan($collection)) {
            $groupKey = BulkPaymentGroup::loanGroupKey($collection, $allCollections);
            if ($groupKey) {
                return $groupKey . ':emi';
            }
        }

        $ref = trim((string) ($collection->payment_reference ?? ''));
        if ($ref !== '') {
            return 'REF-' . $ref . ':emi';
        }

        $at = $collection->collected_at ?: $collection->created_at;
        $bucket = $at ? intdiv($at->getTimestamp(), 15) : 0;
        $account = $collection->emi?->loanAccount?->account_number ?? 'x';

        return 'COL-' . ($collection->agent_id ?? '0') . '-' . $bucket . '-emi-' . $account;
    }

    protected static function buildRow(
        Collection $emiItems,
        int $clientId
    ): ?array {
        $splits = [];
        $hasOpenLoan = false;

        foreach ($emiItems as $collection) {
            $account = $collection->emi?->loanAccount?->account_number ?? 'Loan';
            $isOpen = ($collection->emi?->loanAccount?->loan_mode ?? '') === 'interest_only';
            if ($isOpen) {
                $hasOpenLoan = true;
            }
            $label = ($isOpen ? 'Cycle #' : 'EMI #') . ($collection->emi?->instalment_number ?? 'N/A');
            $splits[] = [
                'kind' => $isOpen ? 'open_loan' : 'emi',
                'label' => $account . ' — ' . $label,
                'amount' => round((float) $collection->amount, 2),
            ];
        }

        $splits = array_values(array_filter($splits, fn (array $split) => $split['amount'] > 0.009));
        if ($splits === []) {
            return null;
        }

        $amount = round(array_sum(array_column($splits, 'amount')), 2);
        $leadEmi = $emiItems->sortBy('id')->first();

        $date = $leadEmi?->collected_at ?: $leadEmi?->verified_at ?: $leadEmi?->created_at;
        $method = $leadEmi?->payment_method ?: 'Cash';
        $reference = $leadEmi?->payment_reference;

        $loanRefs = $emiItems
            ->map(fn (EmiCollection $row) => $row->emi?->loanAccount?->account_number)
            ->filter()
            ->unique()
            ->values();

        $isGrouped = count($splits) > 1;
        $pending = $emiItems->contains(
            fn ($row) => in_array($row->status ?? '', ['in_progress', 'pending'], true)
        );

        $type = $hasOpenLoan
            ? ($pending ? 'Open Loan Collection (Pending)' : 'Open Loan Collection')
            : ($pending ? 'EMI Collection (Pending)' : 'EMI Collection');
        $badge = $pending ? 'warning' : 'success';
        $refLabel = $loanRefs->implode(', ') ?: 'Loan';
        $details = $isGrouped
            ? 'Original amount ₹' . number_format($amount, 2) . ' collected for ' . count($splits) . ' EMIs'
            : (($hasOpenLoan ? 'Cycle #' : 'EMI #') . ($leadEmi?->emi?->instalment_number ?? 'N/A') . ' Payment Received');

        if ($reference) {
            $details .= ' (Ref: ' . $reference . ')';
        }

        return [
            'date' => $date,
            'type' => $type,
            'reference' => $refLabel,
            'details' => $details,
            'method' => ucfirst(str_replace('_', ' ', (string) $method)),
            'flow' => 'IN',
            'amount' => $amount,
            'badge_color' => $badge,
            'splits' => $isGrouped ? $splits : [],
        ];
    }
}