<?php

namespace App\Services\Account;

use App\Models\Account\BankTransaction;
use App\Models\Account\Expense;
use App\Models\Account\Revenue;
use App\Models\ChitDividendPoolEntry;
use App\Models\Client;
use Illuminate\Support\Collection;

/**
 * Builds a unified tagged operational ledger from bank, revenue, expense, and dividend memo rows.
 */
class OperationalLedgerService
{
    /**
     * @return array{rows: Collection, totals: array}
     */
    public function build(
        int $creatorId,
        ?string $fromDate,
        ?string $toDate,
        string $moduleTag = 'all',
        ?string $entryTag = null,
        ?int $bankAccountId = null,
        ?string $search = null,
        string $status = 'posted',
        ?int $clientId = null,
        string $source = 'all'
    ): array {
        $rows = collect();
        $clientMatchers = $this->clientMatchers($clientId);

        $bankQuery = BankTransaction::with(['bankAccount:id,account_name,bank_name'])
            ->where('created_by', $creatorId)
            ->where('transaction_status', '!=', 'cancelled');
        if ($fromDate) {
            $bankQuery->whereDate('transaction_date', '>=', $fromDate);
        }
        if ($toDate) {
            $bankQuery->whereDate('transaction_date', '<=', $toDate);
        }

        if ($bankAccountId) {
            $bankQuery->where('bank_account_id', $bankAccountId);
        }
        $this->applyTags($bankQuery, $moduleTag, $entryTag);
        $this->applyClientToQuery($bankQuery, $clientMatchers);
        if ($search) {
            $bankQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('bankAccount', function ($bq) use ($search) {
                        $bq->where('account_name', 'like', '%' . $search . '%')
                            ->orWhere('bank_name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($source === 'all' || $source === 'Bank') {
            foreach ($bankQuery->orderBy('transaction_date')->orderBy('id')->get() as $tx) {
                $isCredit = $tx->transaction_type === 'credit';
                $rows->push([
                    'date' => $tx->transaction_date?->format('Y-m-d'),
                    'sort_ts' => ($tx->transaction_date?->format('Y-m-d') ?? '') . '-' . str_pad((string) $tx->id, 12, '0', STR_PAD_LEFT),
                    'module_tag' => $tx->module_tag,
                    'entry_tag' => $tx->entry_tag,
                    'ref' => $tx->reference_number,
                    'description' => $tx->description,
                    'debit' => $isCredit ? 0.0 : (float) $tx->amount,
                    'credit' => $isCredit ? (float) $tx->amount : 0.0,
                    'source' => 'Bank',
                    'bank' => $tx->bankAccount?->account_name,
                    'bank_name' => $tx->bankAccount?->bank_name,
                    'status' => $tx->transaction_status,
                    'client_id' => $clientId,
                ]);
            }
        }

        $revQuery = Revenue::with(['category:id,category_name', 'bankAccount:id,account_name,bank_name'])
            ->where('created_by', $creatorId);
        if ($fromDate) {
            $revQuery->whereDate('revenue_date', '>=', $fromDate);
        }
        if ($toDate) {
            $revQuery->whereDate('revenue_date', '<=', $toDate);
        }

        if ($status !== 'all') {
            $revQuery->where('status', $status);
        }
        if ($bankAccountId) {
            $revQuery->where('bank_account_id', $bankAccountId);
        }
        $this->applyTags($revQuery, $moduleTag, $entryTag);
        $this->applyClientToQuery($revQuery, $clientMatchers);
        if ($search) {
            $revQuery->where(function ($q) use ($search) {
                $q->where('revenue_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('bankAccount', function ($bq) use ($search) {
                        $bq->where('account_name', 'like', '%' . $search . '%')
                            ->orWhere('bank_name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($source === 'all' || $source === 'Revenue') {
            foreach ($revQuery->orderBy('revenue_date')->orderBy('id')->get() as $r) {
                $rows->push([
                    'date' => $r->revenue_date?->format('Y-m-d'),
                    'sort_ts' => ($r->revenue_date?->format('Y-m-d') ?? '') . '-R' . str_pad((string) $r->id, 12, '0', STR_PAD_LEFT),
                    'module_tag' => $r->module_tag,
                    'entry_tag' => $r->entry_tag,
                    'ref' => $r->reference_number ?: $r->revenue_number,
                    'description' => ($r->category?->category_name ? ($r->category->category_name . ' — ') : '') . ($r->description ?? ''),
                    'debit' => 0.0,
                    'credit' => (float) $r->amount,
                    'source' => 'Revenue',
                    'bank' => $r->bankAccount?->account_name,
                    'bank_name' => $r->bankAccount?->bank_name,
                    'status' => $r->status,
                    'client_id' => $clientId,
                ]);
            }
        }

        $expQuery = Expense::with(['category:id,category_name', 'bankAccount:id,account_name,bank_name'])
            ->where('created_by', $creatorId);
        if ($fromDate) {
            $expQuery->whereDate('expense_date', '>=', $fromDate);
        }
        if ($toDate) {
            $expQuery->whereDate('expense_date', '<=', $toDate);
        }

        if ($status !== 'all') {
            $expQuery->where('status', $status);
        }
        if ($bankAccountId) {
            $expQuery->where('bank_account_id', $bankAccountId);
        }
        $this->applyTags($expQuery, $moduleTag, $entryTag);
        $this->applyClientToQuery($expQuery, $clientMatchers);
        if ($search) {
            $expQuery->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', '%' . $search . '%')
                    ->orWhere('reference_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('bankAccount', function ($bq) use ($search) {
                        $bq->where('account_name', 'like', '%' . $search . '%')
                            ->orWhere('bank_name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($source === 'all' || $source === 'Expense') {
            foreach ($expQuery->orderBy('expense_date')->orderBy('id')->get() as $e) {
                $rows->push([
                    'date' => $e->expense_date?->format('Y-m-d'),
                    'sort_ts' => ($e->expense_date?->format('Y-m-d') ?? '') . '-E' . str_pad((string) $e->id, 12, '0', STR_PAD_LEFT),
                    'module_tag' => $e->module_tag,
                    'entry_tag' => $e->entry_tag,
                    'ref' => $e->reference_number ?: $e->expense_number,
                    'description' => ($e->category?->category_name ? ($e->category->category_name . ' — ') : '') . ($e->description ?? ''),
                    'debit' => (float) $e->amount,
                    'credit' => 0.0,
                    'source' => 'Expense',
                    'bank' => $e->bankAccount?->account_name,
                    'bank_name' => $e->bankAccount?->bank_name,
                    'status' => $e->status,
                    'client_id' => $clientId,
                ]);
            }
        }

        if (($source === 'all' || $source === 'Dividend')
            && ($moduleTag === 'all' || $moduleTag === AccountingTags::MODULE_CHIT)
        ) {
            if (! $entryTag || $entryTag === AccountingTags::ENTRY_DIVIDEND) {
                // Dividend memo lines are group-level; skip when a specific client is selected.
                if (! $clientId) {
                    $divQuery = ChitDividendPoolEntry::with(['group:id,group_code'])
                        ->where('created_by', $creatorId);
                    if ($fromDate) {
                        $divQuery->whereDate('created_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $divQuery->whereDate('created_at', '<=', $toDate);
                    }

                    if ($search) {
                        $divQuery->where(function ($q) use ($search) {
                            $q->where('remarks', 'like', '%' . $search . '%')
                                ->orWhereHas('group', function ($gq) use ($search) {
                                    $gq->where('group_code', 'like', '%' . $search . '%');
                                });
                        });
                    }

                    foreach ($divQuery->orderBy('created_at')->orderBy('id')->get() as $d) {
                        $groupCode = $d->group?->group_code ?? ('GRP-' . $d->group_id);
                        $isCredit = $d->entry_type === ChitDividendPoolEntry::TYPE_CREDIT;
                        $date = optional($d->created_at)->format('Y-m-d');
                        $rows->push([
                            'date' => $date,
                            'sort_ts' => ($date ?? '') . '-D' . str_pad((string) $d->id, 12, '0', STR_PAD_LEFT),
                            'module_tag' => AccountingTags::MODULE_CHIT,
                            'entry_tag' => AccountingTags::ENTRY_DIVIDEND,
                            'ref' => 'DIV-' . $d->id,
                            'description' => ($d->remarks ?: $d->entry_type_label) . ' — ' . $groupCode,
                            'debit' => $isCredit ? 0.0 : (float) $d->amount,
                            'credit' => $isCredit ? (float) $d->amount : 0.0,
                            'source' => 'Dividend',
                            'bank' => null,
                            'bank_name' => null,
                            'status' => 'memo',
                            'client_id' => null,
                        ]);
                    }
                }
            }
        }

        $rows = $rows->sortByDesc('sort_ts')->values();

        $totals = [
            'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'),
            'bank_debit' => (float) $rows->where('source', 'Bank')->sum('debit'),
            'bank_credit' => (float) $rows->where('source', 'Bank')->sum('credit'),
            'revenue' => (float) $rows->where('source', 'Revenue')->sum('credit'),
            'expense' => (float) $rows->where('source', 'Expense')->sum('debit'),
            'chit_fees' => (float) $rows->where('module_tag', AccountingTags::MODULE_CHIT)
                ->whereIn('entry_tag', [
                    AccountingTags::ENTRY_PROC_FEE,
                    AccountingTags::ENTRY_DOC_FEE,
                    AccountingTags::ENTRY_OTHER_FEE,
                    AccountingTags::ENTRY_BANK_FEE,
                    AccountingTags::ENTRY_FOREMAN_COMM,
                ])->sum('credit'),
            'loan_fees' => (float) $rows->where('module_tag', AccountingTags::MODULE_LOAN)
                ->whereIn('entry_tag', [
                    AccountingTags::ENTRY_PROC_FEE,
                    AccountingTags::ENTRY_DOC_FEE,
                    AccountingTags::ENTRY_OTHER_FEE,
                    AccountingTags::ENTRY_BANK_FEE,
                    AccountingTags::ENTRY_INTEREST,
                    AccountingTags::ENTRY_FORECLOSE,
                ])->sum('credit'),
            'fd_fees' => (float) $rows->where('module_tag', AccountingTags::MODULE_FD)
                ->whereIn('entry_tag', [
                    AccountingTags::ENTRY_PROC_FEE,
                    AccountingTags::ENTRY_DOC_FEE,
                    AccountingTags::ENTRY_OTHER_FEE,
                    AccountingTags::ENTRY_BANK_FEE,
                ])->sum('credit'),
        ];

        return compact('rows', 'totals');
    }

    /**
     * @return array{id:int,name:?string,patterns:array<int,string>}|null
     */
    protected function clientMatchers(?int $clientId): ?array
    {
        if (! $clientId) {
            return null;
        }

        $client = Client::query()->select('id', 'client_name')->find($clientId);
        if (! $client) {
            return ['id' => $clientId, 'name' => null, 'patterns' => []];
        }

        $name = trim((string) $client->client_name);
        $patterns = array_values(array_filter([
            $name !== '' ? $name : null,
            'client #' . $clientId,
            'Client #' . $clientId,
            '#' . $clientId,
        ]));

        return [
            'id' => $clientId,
            'name' => $name !== '' ? $name : null,
            'patterns' => $patterns,
        ];
    }

    protected function applyClientToQuery($query, ?array $clientMatchers): void
    {
        if ($clientMatchers === null) {
            return;
        }

        if (empty($clientMatchers['patterns'])) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($clientMatchers) {
            foreach ($clientMatchers['patterns'] as $pattern) {
                $q->orWhere('description', 'like', '%' . $pattern . '%');
            }
        });
    }

    protected function applyTags($query, string $moduleTag, ?string $entryTag): void
    {
        if ($moduleTag !== 'all') {
            $query->where('module_tag', $moduleTag);
        }
        if ($entryTag) {
            $query->where('entry_tag', $entryTag);
        }
    }
}
