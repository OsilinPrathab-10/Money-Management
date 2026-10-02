<?php

namespace App\Services\Account;

use App\Models\Account\AccountCategory;
use App\Models\Account\ChartOfAccount;
use App\Models\Account\JournalEntry;
use App\Models\Account\JournalEntryItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * YearEndClosingService
 *
 * At the end of each financial year this service accumulates the net revenue
 * (total revenue GL credits minus expense GL debits) earned during the year and
 * transfers it to the Retained Earnings / Capital account via a balanced
 * closing journal entry.
 *
 * Accounting entry posted:
 *   Dr  each revenue account  (zeroes the credit balance for the year)
 *   Cr  Retained Earnings 3200  (accumulated profit transferred to capital)
 *
 * If expenses exceed revenue the entry is reversed:
 *   Dr  Retained Earnings 3200
 *   Cr  each revenue account  (net loss absorbed by capital)
 */
class YearEndClosingService
{
    /**
     * Compute the revenue and expense balances for the financial year
     * derived from posted journal entry items — does NOT post anything.
     *
     * @param  int    $year        Calendar / financial year (e.g. 2025)
     * @param  int    $creatorId   Company / creator ID
     * @return array{
     *   year: int,
     *   fy_start: string,
     *   fy_end: string,
     *   revenue_accounts: array,
     *   expense_accounts: array,
     *   total_revenue: float,
     *   total_expense: float,
     *   net_profit: float,
     *   already_closed: bool,
     *   closing_journal_id: int|null
     * }
     */
    public function preview(int $year, int $creatorId): array
    {
        $fyStart = "{$year}-01-01";
        $fyEnd   = "{$year}-12-31";

        $revenueAccounts = $this->getAccountBalancesForCategory('revenue', $fyStart, $fyEnd, $creatorId);
        $expenseAccounts = $this->getAccountBalancesForCategory('expenses', $fyStart, $fyEnd, $creatorId);

        $totalRevenue = collect($revenueAccounts)->sum('fy_balance');
        $totalExpense = collect($expenseAccounts)->sum('fy_balance');
        $netProfit    = $totalRevenue - $totalExpense;

        // Check if a closing entry already exists for this FY
        $existingJournal = JournalEntry::where('created_by', $creatorId)
            ->where('reference_type', 'year_end_close')
            ->where('reference_id', $year)
            ->first();

        return [
            'year'                => $year,
            'fy_start'            => $fyStart,
            'fy_end'              => $fyEnd,
            'revenue_accounts'    => $revenueAccounts,
            'expense_accounts'    => $expenseAccounts,
            'total_revenue'       => $totalRevenue,
            'total_expense'       => $totalExpense,
            'net_profit'          => $netProfit,
            'already_closed'      => $existingJournal !== null,
            'closing_journal_id'  => $existingJournal?->id,
        ];
    }

    /**
     * Post the year-end closing journal entry.
     * Debits every revenue GL account for its FY balance and credits
     * Retained Earnings (3200) with the net profit (or the reverse for a loss).
     *
     * @throws \Exception if already closed, nothing to close, or RE account missing
     */
    public function close(int $year, int $creatorId): JournalEntry
    {
        $data = $this->preview($year, $creatorId);

        if ($data['already_closed']) {
            throw new \Exception("Financial year {$year} has already been closed.");
        }

        $netProfit = $data['net_profit'];

        if (abs($netProfit) < 0.01) {
            throw new \Exception("Net profit for {$year} is zero — nothing to transfer.");
        }

        // Retained Earnings account (3200) is the target capital account
        $retainedEarnings = ChartOfAccount::where('account_code', '3200')
            ->where('created_by', $creatorId)
            ->first();

        if (!$retainedEarnings) {
            throw new \Exception("Retained Earnings account (3200) not found. Please set up the chart of accounts first.");
        }

        return DB::transaction(function () use ($year, $creatorId, $data, $netProfit, $retainedEarnings) {
            $fyEnd      = $data['fy_end'];
            $totalDebit = abs($netProfit);
            $totalCredit = abs($netProfit);

            $journalEntry = JournalEntry::create([
                'journal_date'   => $fyEnd,
                'entry_type'     => 'automatic',
                'reference_type' => 'year_end_close',
                'reference_id'   => $year,
                'description'    => "Year-End Closing Entry — FY {$year}: Revenue transferred to Retained Earnings",
                'total_debit'    => $totalDebit,
                'total_credit'   => $totalCredit,
                'status'         => 'posted',
                'creator_id'     => Auth::id(),
                'created_by'     => $creatorId,
            ]);

            if ($netProfit > 0) {
                // Profitable year: Dr each revenue account, Cr Retained Earnings
                foreach ($data['revenue_accounts'] as $acct) {
                    if ($acct['fy_balance'] <= 0.01) {
                        continue;
                    }
                    JournalEntryItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $acct['id'],
                        'description'      => "FY {$year} closing — " . $acct['account_name'],
                        'debit_amount'     => $acct['fy_balance'],
                        'credit_amount'    => 0,
                        'creator_id'       => Auth::id(),
                        'created_by'       => $creatorId,
                    ]);
                }

                // Cr Retained Earnings with total revenue minus total expense (net profit)
                JournalEntryItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id'       => $retainedEarnings->id,
                    'description'      => "FY {$year} net profit transferred to Retained Earnings",
                    'debit_amount'     => 0,
                    'credit_amount'    => $netProfit,
                    'creator_id'       => Auth::id(),
                    'created_by'       => $creatorId,
                ]);

                // Also offset expenses on the debit side: Cr each expense account to zero it
                foreach ($data['expense_accounts'] as $acct) {
                    if ($acct['fy_balance'] <= 0.01) {
                        continue;
                    }
                    JournalEntryItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $acct['id'],
                        'description'      => "FY {$year} closing — " . $acct['account_name'],
                        'debit_amount'     => 0,
                        'credit_amount'    => $acct['fy_balance'],
                        'creator_id'       => Auth::id(),
                        'created_by'       => $creatorId,
                    ]);
                }
            } else {
                // Loss year: Dr Retained Earnings with net loss, Cr each expense account
                $netLoss = abs($netProfit);

                JournalEntryItem::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id'       => $retainedEarnings->id,
                    'description'      => "FY {$year} net loss absorbed by Retained Earnings",
                    'debit_amount'     => $netLoss,
                    'credit_amount'    => 0,
                    'creator_id'       => Auth::id(),
                    'created_by'       => $creatorId,
                ]);

                foreach ($data['expense_accounts'] as $acct) {
                    if ($acct['fy_balance'] <= 0.01) {
                        continue;
                    }
                    JournalEntryItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $acct['id'],
                        'description'      => "FY {$year} closing — " . $acct['account_name'],
                        'debit_amount'     => 0,
                        'credit_amount'    => $acct['fy_balance'],
                        'creator_id'       => Auth::id(),
                        'created_by'       => $creatorId,
                    ]);
                }

                foreach ($data['revenue_accounts'] as $acct) {
                    if ($acct['fy_balance'] <= 0.01) {
                        continue;
                    }
                    JournalEntryItem::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $acct['id'],
                        'description'      => "FY {$year} closing — " . $acct['account_name'],
                        'debit_amount'     => $acct['fy_balance'],
                        'credit_amount'    => 0,
                        'creator_id'       => Auth::id(),
                        'created_by'       => $creatorId,
                    ]);
                }
            }

            // Update current_balance on all affected GL accounts
            $this->updateAccountBalances($journalEntry);

            return $journalEntry;
        });
    }

    /**
     * Compute the FY balance of each GL account belonging to the given category type
     * by summing journal entry items posted within the FY date range.
     */
    private function getAccountBalancesForCategory(string $categoryType, string $fyStart, string $fyEnd, int $creatorId): array
    {
        // Resolve category IDs for this company
        $categoryIds = AccountCategory::where('created_by', $creatorId)
            ->where('type', $categoryType)
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        // Resolve account type IDs
        $accountTypeIds = DB::table('account_types')
            ->whereIn('category_id', $categoryIds)
            ->where('created_by', $creatorId)
            ->pluck('id');

        if ($accountTypeIds->isEmpty()) {
            return [];
        }

        // Get GL accounts in this category
        $accounts = ChartOfAccount::whereIn('account_type_id', $accountTypeIds)
            ->where('created_by', $creatorId)
            ->where('is_active', true)
            ->get();

        $result = [];
        foreach ($accounts as $account) {
            // Sum credits and debits for this account within the FY
            $totals = DB::table('journal_entry_items')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                // Exclude previous year-end closing entries from the FY balance calculation
                ->where('journal_entries.reference_type', '!=', 'year_end_close')
                ->where('journal_entries.created_by', $creatorId)
                ->whereBetween('journal_entries.journal_date', [$fyStart, $fyEnd])
                ->where('journal_entry_items.account_id', $account->id)
                ->selectRaw('COALESCE(SUM(credit_amount), 0) as total_credit, COALESCE(SUM(debit_amount), 0) as total_debit')
                ->first();

            // FY balance = net activity (credit accounts like revenue have positive balance when credits > debits)
            $fyBalance = ($account->normal_balance === 'credit')
                ? ($totals->total_credit - $totals->total_debit)
                : ($totals->total_debit - $totals->total_credit);

            $result[] = [
                'id'           => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'fy_balance'   => round((float) $fyBalance, 2),
            ];
        }

        // Only return accounts that had activity
        return array_values(array_filter($result, fn($a) => $a['fy_balance'] > 0.005));
    }

    /**
     * Adjust current_balance on each GL account touched by the journal entry.
     * Mirrors the logic in JournalService::updateAccountBalances().
     */
    private function updateAccountBalances(JournalEntry $journalEntry): void
    {
        $journalEntry->load('items.account');

        foreach ($journalEntry->items as $item) {
            $account = $item->account;
            if (!$account) {
                continue;
            }

            if ($account->normal_balance === 'debit') {
                $account->current_balance += ($item->debit_amount - $item->credit_amount);
            } else {
                $account->current_balance += ($item->credit_amount - $item->debit_amount);
            }

            $account->save();
        }
    }
}
