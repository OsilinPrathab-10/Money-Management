<?php

namespace App\Support;

use App\Models\ChitCollection;
use App\Models\EmiCollection;
use App\Models\Installment;
use Illuminate\Support\Collection;

/**
 * Client ledger IN rows: keep the original incoming receipt amount,
 * and list EMI / chit splits inside that row when one payment covers several.
 */
class ClientLedgerEntries
{
    /**
     * @param  Collection<int, EmiCollection>  $emiCollections
     * @param  Collection<int, ChitCollection>  $chitCollections
     * @param  Collection<int, Installment>  $legacyInstallments
     * @return array<int, array<string, mixed>>
     */
    public static function incomingPaymentRows(
        Collection $emiCollections,
        Collection $chitCollections,
        Collection $legacyInstallments,
        int $clientId
    ): array {
        $buckets = [];

        foreach ($emiCollections as $collection) {
            $key = self::emiIncomingKey($collection, $emiCollections);
            $buckets[$key]['emi'][] = $collection;
            $buckets[$key]['chit'] = $buckets[$key]['chit'] ?? [];
            $buckets[$key]['legacy'] = $buckets[$key]['legacy'] ?? [];
        }

        foreach ($chitCollections as $collection) {
            $key = self::incomingKey($collection->remarks, $collection->payment_reference)
                ?? BulkPaymentGroup::chitGroupKey($collection)
                ?? ('chit-' . $collection->id);
            $buckets[$key]['chit'][] = $collection;
            $buckets[$key]['emi'] = $buckets[$key]['emi'] ?? [];
            $buckets[$key]['legacy'] = $buckets[$key]['legacy'] ?? [];
        }

        $coveredInstallmentIds = $chitCollections->pluck('installment_id')->filter()->unique();

        foreach ($legacyInstallments as $installment) {
            if ($coveredInstallmentIds->contains($installment->id)) {
                continue;
            }
            if ((float) $installment->clientPaidShare($clientId) <= 0.009) {
                continue;
            }

            $key = self::incomingKey($installment->remarks, $installment->reference_no)
                ?? BulkPaymentGroup::installmentGroupKey($installment)
                ?? ('inst-' . $installment->id);
            $buckets[$key]['legacy'][] = $installment;
            $buckets[$key]['emi'] = $buckets[$key]['emi'] ?? [];
            $buckets[$key]['chit'] = $buckets[$key]['chit'] ?? [];
        }

        $rows = [];
        foreach ($buckets as $items) {
            $row = self::buildRow(
                collect($items['emi'] ?? []),
                collect($items['chit'] ?? []),
                collect($items['legacy'] ?? []),
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
        return ['verified', 'paid', 'completed', 'in_progress', 'pending'];
    }

    /**
     * @return array<int, array{original: float, allocated: float, is_bulk: bool, splits: array<int, array{label: string, amount: float}>}>
     */
    public static function originalReceiptsForLoan(\App\Models\LoanAccount $loan): array
    {
        $loan->loadMissing(['loanApplication', 'emis.collections.emi.loanAccount.loanApplication']);
        $pool = collect();
        foreach ($loan->emis as $emi) {
            $pool = $pool->merge($emi->collections ?? collect());
        }
        $pool = $pool
            ->filter(fn ($col) => $col instanceof EmiCollection && in_array($col->status, self::postedCollectionStatuses(), true))
            ->values();

        $map = [];
        foreach ($loan->emis as $emi) {
            $own = $pool->where('emi_id', $emi->id);
            $allocated = round((float) $own->sum('amount'), 2);
            if ($allocated <= 0.009) {
                $allocated = round((float) ($emi->paid_amount ?? 0), 2);
            }

            $lead = $own->sortByDesc('id')->first();
            if (! $lead) {
                $map[$emi->id] = [
                    'original' => $allocated,
                    'allocated' => $allocated,
                    'is_bulk' => false,
                    'splits' => [],
                ];
                continue;
            }

            $key = self::emiIncomingKey($lead, $pool);
            $siblings = $pool->filter(fn (EmiCollection $col) => self::emiIncomingKey($col, $pool) === $key)->values();
            $splits = [];
            if ($siblings->count() > 1) {
                $splits = $siblings
                    ->sortBy(fn (EmiCollection $col) => (int) ($col->emi?->instalment_number ?? $col->id))
                    ->map(function (EmiCollection $col) {
                        $isOpen = ($col->emi?->loanAccount?->loanApplication?->loan_mode ?? 'emi') === 'interest_only';

                        return [
                            'label' => ($isOpen ? 'Cycle #' : 'EMI #') . ($col->emi?->instalment_number ?? 'N/A'),
                            'amount' => round((float) $col->amount, 2),
                        ];
                    })
                    ->values()
                    ->all();
            }

            $original = round((float) ($siblings->isNotEmpty() ? $siblings->sum('amount') : $allocated), 2);
            $map[$emi->id] = [
                'original' => $original > 0.009 ? $original : $allocated,
                'allocated' => $allocated,
                'is_bulk' => count($splits) > 1,
                'splits' => $splits,
            ];
        }

        return $map;
    }

    protected static function incomingKey(?string $remarks, ?string $reference = null): ?string
    {
        $key = BulkPaymentGroup::extractExplicitKey($remarks, $reference);

        return $key ? strtoupper($key) : null;
    }

    protected static function emiIncomingKey(EmiCollection $collection, Collection $allEmi): string
    {
        $explicit = self::incomingKey($collection->remarks, $collection->payment_reference);
        if ($explicit) {
            return $explicit;
        }

        $ref = trim((string) $collection->payment_reference);
        if ($ref !== '' && ! in_array(strtoupper($ref), ['N/A', 'NA', 'CASH', '-'], true)) {
            $sameRef = $allEmi->filter(
                fn (EmiCollection $row) => strcasecmp(trim((string) $row->payment_reference), $ref) === 0
            );
            if ($sameRef->count() > 1) {
                return 'ref:' . strtoupper($ref);
            }
        }

        $bulkKey = BulkPaymentGroup::groupKey($collection);
        if ($bulkKey) {
            return $bulkKey;
        }

        if (BulkPaymentGroup::isBulk($collection) && $collection->collected_at) {
            $loanId = $collection->emi?->loan_account_id ?? 'x';
            $who = $collection->agent_id ? ('a' . $collection->agent_id) : ('u' . ($collection->verified_by ?? '0'));

            return 'daybulk:' . $collection->collected_at->format('Y-m-d') . ':' . $who . ':loan:' . $loanId;
        }

        return 'emi-' . $collection->id;
    }

    protected static function buildRow(
        Collection $emiItems,
        Collection $chitItems,
        Collection $legacyItems,
        int $clientId
    ): ?array {
        $splits = [];
        $hasOpenLoan = false;

        foreach ($emiItems->sortBy(fn (EmiCollection $row) => (int) ($row->emi?->instalment_number ?? $row->id)) as $collection) {
            $isOpen = ($collection->emi?->loanAccount?->loanApplication?->loan_mode ?? 'emi') === 'interest_only';
            $hasOpenLoan = $hasOpenLoan || $isOpen;
            $account = $collection->emi?->loanAccount?->account_number ?? 'Loan';
            $label = ($isOpen ? 'Cycle #' : 'EMI #') . ($collection->emi?->instalment_number ?? 'N/A');
            $splits[] = [
                'kind' => $isOpen ? 'open_loan' : 'emi',
                'label' => $account . ' — ' . $label,
                'amount' => round((float) $collection->amount, 2),
            ];
        }

        foreach ($chitItems->sortBy(fn (ChitCollection $row) => (int) ($row->installment?->month_number ?? $row->id)) as $collection) {
            $groupCode = $collection->group?->group_code
                ?? $collection->installment?->group?->group_code
                ?? 'Chit';
            $month = $collection->installment?->month_number ?? 'N/A';
            $splits[] = [
                'kind' => 'chit',
                'label' => $groupCode . ' — Month #' . $month,
                'amount' => round((float) $collection->amount, 2),
            ];
        }

        foreach ($legacyItems->sortBy(fn (Installment $row) => (int) ($row->month_number ?? $row->id)) as $installment) {
            $groupCode = $installment->group?->group_code ?? 'Chit';
            $splits[] = [
                'kind' => 'chit',
                'label' => $groupCode . ' — Month #' . ($installment->month_number ?? 'N/A'),
                'amount' => round((float) $installment->clientPaidShare($clientId), 2),
            ];
        }

        $splits = array_values(array_filter($splits, fn (array $split) => $split['amount'] > 0.009));
        if ($splits === []) {
            return null;
        }

        $amount = round(array_sum(array_column($splits, 'amount')), 2);
        $leadEmi = $emiItems->sortBy('id')->first();
        $leadChit = $chitItems->sortBy('id')->first();
        $leadLegacy = $legacyItems->sortBy('id')->first();

        $date = $leadEmi?->collected_at ?: $leadEmi?->verified_at ?: $leadEmi?->created_at
            ?: $leadChit?->collected_at ?: $leadChit?->verified_at ?: $leadChit?->created_at
            ?: $leadLegacy?->paid_date ?: $leadLegacy?->updated_at;

        $method = $leadEmi?->payment_method
            ?: $leadChit?->payment_method
            ?: $leadLegacy?->payment_mode
            ?: 'Cash';

        $reference = $leadEmi?->payment_reference
            ?: $leadChit?->payment_reference
            ?: $leadLegacy?->reference_no;

        $loanRefs = $emiItems
            ->map(fn (EmiCollection $row) => $row->emi?->loanAccount?->account_number)
            ->filter()
            ->unique()
            ->values();
        $chitRefs = $chitItems
            ->map(fn (ChitCollection $row) => $row->group?->group_code ?? $row->installment?->group?->group_code)
            ->merge($legacyItems->map(fn (Installment $row) => $row->group?->group_code))
            ->filter()
            ->unique()
            ->values();

        $hasEmi = $emiItems->isNotEmpty();
        $hasChit = $chitItems->isNotEmpty() || $legacyItems->isNotEmpty();
        $isGrouped = count($splits) > 1;
        $pending = $emiItems->concat($chitItems)->contains(
            fn ($row) => in_array($row->status ?? '', ['in_progress', 'pending'], true)
        );

        if ($hasEmi && $hasChit) {
            $type = $pending ? 'Collection (Pending)' : 'Collection';
            $badge = $pending ? 'warning' : 'success';
            $refLabel = $loanRefs->merge($chitRefs)->implode(' + ') ?: 'Combined';
            $details = 'Original amount ₹' . number_format($amount, 2) . ' split across EMI and chit';
        } elseif ($hasEmi) {
            $type = $hasOpenLoan
                ? ($pending ? 'Open Loan Collection (Pending)' : 'Open Loan Collection')
                : ($pending ? 'EMI Collection (Pending)' : 'EMI Collection');
            $badge = $pending ? 'warning' : 'success';
            $refLabel = $loanRefs->implode(', ') ?: 'Loan';
            $details = $isGrouped
                ? 'Original amount ₹' . number_format($amount, 2) . ' collected for ' . count($splits) . ' EMIs'
                : (($hasOpenLoan ? 'Cycle #' : 'EMI #') . ($leadEmi?->emi?->instalment_number ?? 'N/A') . ' Payment Received');
        } else {
            $type = $pending ? 'Chit Collection (Pending)' : 'Chit Collection';
            $badge = $pending ? 'warning' : 'info';
            $refLabel = $chitRefs->implode(', ') ?: 'Chit';
            $details = $isGrouped
                ? 'Original amount ₹' . number_format($amount, 2) . ' collected for ' . count($splits) . ' installments'
                : ('Month #' . ($leadChit?->installment?->month_number ?? $leadLegacy?->month_number ?? 'N/A') . ' Installment Payment');
        }

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
