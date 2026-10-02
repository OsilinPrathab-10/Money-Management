<?php

namespace App\Services\Account;

/**
 * Module / entry tags for Day Book and operational Ledger.
 */
class AccountingTags
{
    public const MODULE_CHIT = 'CHIT';
    public const MODULE_LOAN = 'LOAN';
    public const MODULE_FD = 'FD';
    public const MODULE_TRANSFER = 'TRANSFER';
    public const MODULE_OTHER = 'OTHER';

    public const ENTRY_INSTALLMENT = 'INSTALLMENT';
    public const ENTRY_SETTLEMENT = 'SETTLEMENT';
    public const ENTRY_TRANSFER = 'TRANSFER';
    public const ENTRY_DISBURSEMENT = 'DISBURSEMENT';
    public const ENTRY_EMI = 'EMI';
    public const ENTRY_DEPOSIT = 'DEPOSIT';
    public const ENTRY_PAYOUT = 'PAYOUT';
    public const ENTRY_PROC_FEE = 'PROC_FEE';
    public const ENTRY_DOC_FEE = 'DOC_FEE';
    public const ENTRY_OTHER_FEE = 'OTHER_FEE';
    public const ENTRY_BANK_FEE = 'BANK_FEE';
    public const ENTRY_FOREMAN_COMM = 'FOREMAN_COMM';
    public const ENTRY_DIVIDEND = 'DIVIDEND';
    public const ENTRY_REVERSAL = 'REVERSAL';
    public const ENTRY_WALLET_WD = 'WALLET_WD';
    public const ENTRY_INTEREST = 'INTEREST';
    public const ENTRY_FORECLOSE = 'FORECLOSE';
    public const ENTRY_WITHDRAWAL = 'WITHDRAWAL';

    public static function modules(): array
    {
        return [
            self::MODULE_CHIT,
            self::MODULE_LOAN,
            self::MODULE_FD,
            self::MODULE_TRANSFER,
            self::MODULE_OTHER,
        ];
    }

    public static function entries(): array
    {
        return [
            self::ENTRY_INSTALLMENT,
            self::ENTRY_SETTLEMENT,
            self::ENTRY_TRANSFER,
            self::ENTRY_DISBURSEMENT,
            self::ENTRY_EMI,
            self::ENTRY_DEPOSIT,
            self::ENTRY_WITHDRAWAL,
            self::ENTRY_PAYOUT,
            self::ENTRY_PROC_FEE,
            self::ENTRY_DOC_FEE,
            self::ENTRY_OTHER_FEE,
            self::ENTRY_BANK_FEE,
            self::ENTRY_FOREMAN_COMM,
            self::ENTRY_DIVIDEND,
            self::ENTRY_REVERSAL,
            self::ENTRY_WALLET_WD,
            self::ENTRY_INTEREST,
            self::ENTRY_FORECLOSE,
        ];
    }

    public static function moduleBadgeClass(string $module): string
    {
        return match (strtoupper($module)) {
            self::MODULE_CHIT => 'bg-label-primary',
            self::MODULE_LOAN => 'bg-label-info',
            self::MODULE_FD => 'bg-label-warning',
            self::MODULE_TRANSFER => 'bg-label-dark',
            default => 'bg-label-secondary',
        };
    }

    public static function entryLabel(string $entry): string
    {
        return match (strtoupper($entry)) {
            self::ENTRY_INSTALLMENT => 'Installment',
            self::ENTRY_SETTLEMENT => 'Settlement',
            self::ENTRY_TRANSFER => 'Transfer',
            self::ENTRY_DISBURSEMENT => 'Disbursement',
            self::ENTRY_EMI => 'EMI',
            self::ENTRY_DEPOSIT => 'Deposit',
            self::ENTRY_WITHDRAWAL => 'Withdrawal',
            self::ENTRY_PAYOUT => 'Payout',
            self::ENTRY_PROC_FEE => 'Processing fee',
            self::ENTRY_DOC_FEE => 'Document fee',
            self::ENTRY_OTHER_FEE => 'Other fee',
            self::ENTRY_BANK_FEE => 'Banking charges',
            self::ENTRY_FOREMAN_COMM => 'Foreman commission',
            self::ENTRY_DIVIDEND => 'Dividend',
            self::ENTRY_REVERSAL => 'Reversal',
            self::ENTRY_WALLET_WD => 'Wallet withdrawal',
            self::ENTRY_INTEREST => 'Interest',
            self::ENTRY_FORECLOSE => 'Foreclosure',
            default => $entry,
        };
    }

    /**
     * Display name of who collected the payment (collector / agent / logged-in user).
     */
    public static function collectorDisplayName(?int $userId = null, ?string $fallbackName = null): string
    {
        if ($fallbackName !== null && trim($fallbackName) !== '') {
            return trim($fallbackName);
        }

        try {
            if ($userId) {
                $user = \App\Models\User::query()->select('id', 'name')->find($userId);
                if ($user && trim((string) $user->name) !== '') {
                    return trim((string) $user->name);
                }
            }

            $auth = \Illuminate\Support\Facades\Auth::user();
            if ($auth && trim((string) ($auth->name ?? '')) !== '') {
                return trim((string) $auth->name);
            }
        } catch (\Throwable) {
            // ignore
        }

        return 'System';
    }

    /**
     * Short bank-tx description: Chit IC — group M# — client — by collector
     * $month may be a single month or a list of months for bulk / split payments.
     */
    public static function chitIcDescription(
        string $groupCode,
        int|string|array|null $month,
        string $clientName,
        ?int $collectedByUserId = null,
        ?string $collectorName = null,
        bool $reversed = false
    ): string {
        $prefix = $reversed ? 'Chit IC Rev' : 'Chit IC';
        $by = self::collectorDisplayName($collectedByUserId, $collectorName);
        $monthPart = self::formatMonthPart($month);

        return "{$prefix} — {$groupCode} {$monthPart} — {$clientName} — by {$by}";
    }

    /**
     * Bulk chit collection covering multiple groups / months / members in one payment.
     *
     * @param  list<array{group_code?: string, month?: int|string|null, client_name?: string}>  $lines
     */
    public static function chitIcBulkDescription(
        array $lines,
        ?int $collectedByUserId = null,
        ?string $collectorName = null
    ): string {
        $by = self::collectorDisplayName($collectedByUserId, $collectorName);
        $parts = [];

        foreach ($lines as $line) {
            $group = trim((string) ($line['group_code'] ?? 'GRP'));
            $client = trim((string) ($line['client_name'] ?? 'Member'));
            $monthPart = self::formatMonthPart($line['month'] ?? null);
            $parts[] = trim("{$group} {$monthPart} ({$client})");
        }

        $parts = array_values(array_unique(array_filter($parts)));
        $summary = $parts ? implode('; ', $parts) : 'multiple installments';

        return "Chit IC Bulk — {$summary} — by {$by}";
    }

    /**
     * Short bank-tx description: Loan IC — account — client — EMI #n — by collector
     * $emiNumber may be a single EMI or a list for cascade / bulk payments.
     */
    public static function loanIcDescription(
        string $accountNumber,
        string $clientName,
        int|string|array|null $emiNumber = null,
        ?int $collectedByUserId = null,
        ?string $collectorName = null,
        bool $reversed = false
    ): string {
        $prefix = $reversed ? 'Loan IC Rev' : 'Loan IC';
        $by = self::collectorDisplayName($collectedByUserId, $collectorName);
        $emiPart = self::formatEmiPart($emiNumber);

        return "{$prefix} — {$accountNumber} — {$clientName}{$emiPart} — by {$by}";
    }

    /**
     * Bulk loan collection covering EMIs across one or more accounts in one payment.
     *
     * @param  list<array{account_number?: string, client_name?: string, emis?: list<int|string>}>  $lines
     */
    public static function loanIcBulkDescription(
        array $lines,
        ?int $collectedByUserId = null,
        ?string $collectorName = null
    ): string {
        $by = self::collectorDisplayName($collectedByUserId, $collectorName);
        $parts = [];

        foreach ($lines as $line) {
            $account = trim((string) ($line['account_number'] ?? 'N/A'));
            $client = trim((string) ($line['client_name'] ?? 'Client'));
            $emiPart = ltrim(self::formatEmiPart($line['emis'] ?? null), ' —');
            $parts[] = trim("{$account} {$emiPart} ({$client})");
        }

        $parts = array_values(array_unique(array_filter($parts)));
        $summary = $parts ? implode('; ', $parts) : 'multiple EMIs';

        return "Loan IC Bulk — {$summary} — by {$by}";
    }

    public static function loanForeclosureDescription(
        string $accountNumber,
        string $clientName,
        ?int $collectedByUserId = null,
        ?string $collectorName = null,
        bool $reversed = false
    ): string {
        $prefix = $reversed ? 'Loan FC Rev' : 'Loan FC';
        $by = self::collectorDisplayName($collectedByUserId, $collectorName);

        return "{$prefix} — {$accountNumber} — {$clientName} — by {$by}";
    }

    /**
     * Compact list: [1,2,3,5,6] => "1–3, 5–6"
     */
    public static function formatNumberList(array $numbers): string
    {
        $nums = array_values(array_unique(array_map('intval', $numbers)));
        sort($nums);

        if ($nums === []) {
            return '';
        }

        $ranges = [];
        $start = $nums[0];
        $prev = $nums[0];

        for ($i = 1, $len = count($nums); $i < $len; $i++) {
            if ($nums[$i] === $prev + 1) {
                $prev = $nums[$i];
                continue;
            }
            $ranges[] = $start === $prev ? (string) $start : "{$start}–{$prev}";
            $start = $prev = $nums[$i];
        }
        $ranges[] = $start === $prev ? (string) $start : "{$start}–{$prev}";

        return implode(', ', $ranges);
    }

    protected static function formatMonthPart(int|string|array|null $month): string
    {
        if (is_array($month)) {
            $list = self::formatNumberList($month);

            return $list !== '' ? "M{$list}" : 'M?';
        }

        if ($month === null || $month === '') {
            return 'M?';
        }

        return 'M' . $month;
    }

    protected static function formatEmiPart(int|string|array|null $emiNumber): string
    {
        if (is_array($emiNumber)) {
            $list = self::formatNumberList($emiNumber);

            return $list !== '' ? " — EMI #{$list}" : '';
        }

        if ($emiNumber === null || $emiNumber === '') {
            return '';
        }

        return " — EMI #{$emiNumber}";
    }

    /**
     * Company bank debit description for transfer / NEFT charges (not deducted from client).
     */
    public static function bankTransferChargesDescription(
        string $moduleLabel,
        string $accountOrGroup,
        string $clientName,
        float $transferAmount,
        float $chargeAmount
    ): string {
        $transfer = number_format(round($transferAmount, 2), 2, '.', '');
        $charge = number_format(round($chargeAmount, 2), 2, '.', '');

        return "Bank transfer charges — {$moduleLabel} {$accountOrGroup} — {$clientName} — transfer ₹{$transfer} — charge ₹{$charge}";
    }
}
