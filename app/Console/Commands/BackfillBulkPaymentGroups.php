<?php

namespace App\Console\Commands;

use App\Models\ChitCollection;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\Installment;
use App\Support\BulkPaymentGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillBulkPaymentGroups extends Command
{
    protected $signature = 'bulk:backfill-groups
        {--dry-run : Show what would be grouped without writing}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Stamp Group: BULK-xxxxx on past loan/chit bulk payments so they list and print as one cumulative receipt';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Dry run — no database changes will be saved.'
            : 'Backfilling past bulk payment group keys...');

        $loanClusters = $this->loanClusters();
        $chitCollectionClusters = $this->chitCollectionClusters();
        $installmentClusters = $this->installmentClusters();

        $loanCount = $loanClusters->sum(fn (Collection $items) => $items->count());
        $chitColCount = $chitCollectionClusters->sum(fn (Collection $items) => $items->count());
        $instCount = $installmentClusters->sum(fn (Collection $items) => $items->count());

        $this->table(
            ['Type', 'Bulk groups (2+ items)', 'Rows to stamp'],
            [
                ['Loan EMI collections', $loanClusters->count(), $loanCount],
                ['Chit collections', $chitCollectionClusters->count(), $chitColCount],
                ['Chit installments', $installmentClusters->count(), $instCount],
            ]
        );

        if ($loanClusters->isEmpty() && $chitCollectionClusters->isEmpty() && $installmentClusters->isEmpty()) {
            $this->info('No past bulk payments need a group key. They may already have Group: BULK-… or they were single collections.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->previewClusters('Loan', $loanClusters, fn (EmiCollection $row) => sprintf(
                '#%s EMI %s ₹%s',
                $row->id,
                $row->emi?->instalment_number ?? '?',
                number_format((float) $row->amount, 2)
            ));
            $this->previewClusters('Chit collection', $chitCollectionClusters, fn (ChitCollection $row) => sprintf(
                '#%s Inst %s ₹%s',
                $row->id,
                $row->installment?->month_number ?? '?',
                number_format((float) $row->amount, 2)
            ));
            $this->previewClusters('Chit installment', $installmentClusters, fn (Installment $row) => sprintf(
                '#%s Inst %s ₹%s',
                $row->id,
                $row->month_number,
                number_format((float) $row->paid_amount, 2)
            ));

            $this->warn('Dry run only. To apply: php artisan bulk:backfill-groups');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Stamp these past bulk payments with a shared Group: BULK- key?', true)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        $stamped = 0;
        DB::transaction(function () use ($loanClusters, $chitCollectionClusters, $installmentClusters, &$stamped) {
            $stamped += $this->stampLoanClusters($loanClusters);
            $stamped += $this->stampChitCollectionClusters($chitCollectionClusters);
            $stamped += $this->stampInstallmentClusters($installmentClusters);
        });

        $this->info("Stamped {$stamped} past bulk payment row(s). Refresh Agent Collections and Payment Receipts to see cumulative totals.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Collection<int, EmiCollection>>
     */
    private function loanClusters(): Collection
    {
        $rows = EmiCollection::with('emi')
            ->orderBy('id')
            ->get()
            ->filter(function (EmiCollection $row) {
                if (! BulkPaymentGroup::isBulk($row)) {
                    return false;
                }
                $key = BulkPaymentGroup::extractExplicitKey($row->remarks, $row->payment_reference);

                return ! $key || ! str_starts_with(strtoupper((string) $key), 'BULK-');
            })
            ->values();

        return $this->clusterByGap($rows, function (EmiCollection $row) {
            return implode(':', [
                $row->emi?->loan_account_id ?? 'x',
                $row->agent_id ?: ('u' . ($row->verified_by ?? '0')),
                $row->payment_method ?? '',
            ]);
        }, fn (EmiCollection $row) => $row->created_at);
    }

    /**
     * @return Collection<int, Collection<int, ChitCollection>>
     */
    private function chitCollectionClusters(): Collection
    {
        $rows = ChitCollection::with('installment')
            ->orderBy('id')
            ->get()
            ->filter(function (ChitCollection $row) {
                if (! BulkPaymentGroup::isBulkChit($row)) {
                    return false;
                }
                $key = BulkPaymentGroup::extractExplicitKey($row->remarks, $row->payment_reference);

                return ! $key || ! str_starts_with(strtoupper((string) $key), 'BULK-');
            })
            ->values();

        return $this->clusterByGap($rows, function (ChitCollection $row) {
            return implode(':', [
                $row->group_id ?? 'x',
                $row->member_id ?? 'x',
                $row->agent_id ?: ('u' . ($row->verified_by ?? '0')),
                $row->payment_method ?? '',
            ]);
        }, fn (ChitCollection $row) => $row->created_at);
    }

    /**
     * @return Collection<int, Collection<int, Installment>>
     */
    private function installmentClusters(): Collection
    {
        $rows = Installment::with('group')
            ->where(function ($q) {
                $q->where('remarks', 'like', '%' . BulkPaymentGroup::CHIT_ADMIN_MARKER . '%')
                    ->orWhere('remarks', 'like', '%' . BulkPaymentGroup::AGENT_MARKER . '%')
                    ->orWhere('remarks', 'like', '%' . BulkPaymentGroup::FAMILY_MARKER . '%')
                    ->orWhere('reference_no', 'like', 'BULK-%')
                    ->orWhere('reference_no', 'like', 'FAM-%');
            })
            ->orderBy('id')
            ->get()
            ->filter(function (Installment $row) {
                if (! BulkPaymentGroup::isBulkPayload($row->remarks, $row->reference_no)) {
                    return false;
                }
                $key = BulkPaymentGroup::extractExplicitKey($row->remarks, $row->reference_no);

                return ! $key || ! str_starts_with(strtoupper((string) $key), 'BULK-');
            })
            ->values();

        $byReference = $rows->groupBy(function (Installment $row) {
            $ref = trim((string) ($row->reference_no ?? ''));
            if ($ref === '' || strcasecmp($ref, 'Bulk Payment') === 0) {
                return 'nogroup-' . $row->id;
            }

            return $ref . ':m' . $row->member_id . ':g' . $row->group_id;
        });

        $clusters = collect();
        foreach ($byReference as $key => $group) {
            if (str_starts_with((string) $key, 'nogroup-')) {
                continue;
            }
            if ($group->count() > 1) {
                $clusters->push($group->values());
            }
        }

        $ungrouped = $rows->filter(function (Installment $row) {
            $ref = trim((string) ($row->reference_no ?? ''));

            return $ref === '' || strcasecmp($ref, 'Bulk Payment') === 0;
        })->values();

        $timeClusters = $this->clusterByGap($ungrouped, function (Installment $row) {
            return implode(':', [
                $row->group_id ?? 'x',
                $row->member_id ?? 'x',
                $row->paid_date?->format('Y-m-d') ?? 'x',
            ]);
        }, fn (Installment $row) => $row->updated_at ?? $row->paid_date);

        return $clusters->concat($timeClusters)->values();
    }

    /**
     * Cluster rows that share a bucket key and sit within 45 seconds of the previous row.
     *
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, Collection>
     */
    private function clusterByGap(Collection $rows, callable $bucketKey, callable $timestamp): Collection
    {
        $clusters = collect();

        foreach ($rows->groupBy($bucketKey) as $bucket) {
            $sorted = $bucket->sortBy(function ($row) use ($timestamp) {
                $at = $timestamp($row);

                return ($at ? $at->getTimestamp() : 0) . '-' . $row->id;
            })->values();

            $current = collect();
            $previousAt = null;

            foreach ($sorted as $row) {
                $at = $timestamp($row);
                $gap = ($previousAt && $at)
                    ? abs($at->getTimestamp() - $previousAt->getTimestamp())
                    : 0;

                if ($current->isNotEmpty() && $gap > 45) {
                    if ($current->count() > 1) {
                        $clusters->push($current);
                    }
                    $current = collect();
                }

                $current->push($row);
                $previousAt = $at ?? $previousAt;
            }

            if ($current->count() > 1) {
                $clusters->push($current);
            }
        }

        return $clusters;
    }

    private function previewClusters(string $label, Collection $clusters, callable $line): void
    {
        foreach ($clusters->take(8) as $i => $items) {
            $total = $items->sum(function ($row) {
                return (float) ($row->amount ?? $row->paid_amount ?? 0);
            });
            $this->line(sprintf(
                '  %s group %d (%d items, ₹%s): %s',
                $label,
                $i + 1,
                $items->count(),
                number_format($total, 2),
                $items->map($line)->implode(', ')
            ));
        }

        if ($clusters->count() > 8) {
            $this->line('  … ' . ($clusters->count() - 8) . ' more ' . strtolower($label) . ' group(s)');
        }
    }

    private function stampLoanClusters(Collection $clusters): int
    {
        $stamped = 0;

        foreach ($clusters as $items) {
            $groupKey = BulkPaymentGroup::generateKey();
            foreach ($items as $row) {
                /** @var EmiCollection $row */
                $remarks = $this->appendGroup($row->remarks, $groupKey);
                $reference = $this->bulkReference($row->payment_reference, $groupKey);
                $row->update([
                    'remarks' => $remarks,
                    'payment_reference' => $reference,
                ]);

                if ($row->emi_id) {
                    Emi::where('id', $row->emi_id)
                        ->where(function ($q) {
                            $q->whereNull('payment_reference')
                                ->orWhere('payment_reference', '')
                                ->orWhere('payment_reference', 'Bulk Payment');
                        })
                        ->update(['payment_reference' => $groupKey]);
                }
                $stamped++;
            }
        }

        return $stamped;
    }

    private function stampChitCollectionClusters(Collection $clusters): int
    {
        $stamped = 0;

        foreach ($clusters as $items) {
            $groupKey = BulkPaymentGroup::generateKey();
            foreach ($items as $row) {
                /** @var ChitCollection $row */
                $row->update([
                    'remarks' => $this->appendGroup($row->remarks, $groupKey),
                    'payment_reference' => $this->bulkReference($row->payment_reference, $groupKey),
                ]);
                $stamped++;
            }
        }

        return $stamped;
    }

    private function stampInstallmentClusters(Collection $clusters): int
    {
        $stamped = 0;

        foreach ($clusters as $items) {
            $groupKey = BulkPaymentGroup::generateKey();
            foreach ($items as $row) {
                /** @var Installment $row */
                $row->update([
                    'remarks' => $this->appendGroup($row->remarks, $groupKey),
                    'reference_no' => $this->bulkReference($row->reference_no, $groupKey),
                ]);
                $stamped++;
            }
        }

        return $stamped;
    }

    private function appendGroup(?string $remarks, string $groupKey): string
    {
        $remarks = trim((string) $remarks);
        if (preg_match('/Group:\s*BULK-/i', $remarks)) {
            return $remarks;
        }

        return trim($remarks . ' Group: ' . $groupKey);
    }

    private function bulkReference(?string $current, string $groupKey): string
    {
        $current = trim((string) $current);
        if ($current === '' || strcasecmp($current, 'Bulk Payment') === 0) {
            return $groupKey;
        }

        return $current;
    }
}
