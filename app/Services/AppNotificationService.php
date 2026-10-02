<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Agent;
use App\Models\AgentNotification;
use App\Models\Client;
use App\Models\CustomerNotification;
use App\Models\Emi;
use App\Models\FixedDepositApplication;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\LoanApplication;
use App\Models\Payout;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AppNotificationService
{
    public function __construct(
        protected PushNotificationService $push
    ) {}

    public static function detectSource(): string
    {
        $user = Auth::user();
        if (! $user) {
            return 'system';
        }

        if ($user instanceof Agent) {
            return 'agent';
        }

        $path = (string) request()->path();
        if (str_contains($path, 'api/customer')) {
            return 'customer';
        }
        if (str_contains($path, 'api/agent')) {
            return 'agent';
        }

        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('Agent') && ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff'])) {
                return 'agent';
            }
            if ($user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff'])) {
                return 'admin';
            }
        }

        if ($user->client ?? null) {
            return 'customer';
        }

        return 'admin';
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            'customer' => 'customer app',
            'agent' => 'agent app',
            'admin' => 'admin panel',
            default => $source,
        };
    }

    public function newLoanApplication(LoanApplication $application, string $source = 'admin'): void
    {
        $application->loadMissing('client', 'product');
        $client = $application->client;
        $name = $this->clientName($client);
        $amount = $this->money($application->loan_amount);
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'new_loan_application',
            'title' => 'New Loan Application',
            'admin_message' => "{$name} submitted a loan application of {$amount} from the {$label}.",
            'customer_title' => 'Loan Application Submitted',
            'customer_message' => "Your loan application {$this->ref($application->application_number)} for {$amount} has been submitted.",
            'agent_title' => 'Client Loan Application',
            'agent_message' => "{$name} submitted a loan application of {$amount} via the {$label}.",
            'link' => $this->safeRoute('loan-application-view', $application->id),
            'icon' => 'ri-file-list-3-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'loan_application',
            'data' => [
                'screen' => 'loan_application',
                'application_id' => (string) $application->id,
                'application_number' => (string) ($application->application_number ?? ''),
                'source' => $source,
            ],
        ]);
    }

    public function newChitApplication(GroupMember $member, string $source = 'admin'): void
    {
        $member->loadMissing('client', 'group.scheme');
        $client = $member->client;
        $name = $this->clientName($client);
        $group = $member->group?->group_code ?? ('Group #' . $member->group_id);
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'new_chit_application',
            'title' => 'New Chit Application',
            'admin_message' => "{$name} applied for chit group {$group} from the {$label}.",
            'customer_title' => 'Chit Application Submitted',
            'customer_message' => "Your chit application for group {$group} has been submitted and is pending approval.",
            'agent_title' => 'Client Chit Application',
            'agent_message' => "{$name} applied for chit group {$group} via the {$label}.",
            'link' => $this->safeRoute('chit.applications.show', $member),
            'icon' => 'ri-group-line',
            'related_id' => $member->id,
            'unique' => true,
            'fcm_type' => 'chit_application',
            'data' => [
                'screen' => 'chit_application',
                'member_id' => (string) $member->id,
                'group_id' => (string) $member->group_id,
                'client_id' => (string) ($client->id ?? ''),
                'customer_name' => $name,
                'source' => $source,
            ],
            'extra_agent_ids' => array_filter([(int) ($member->referred_by_agent_id ?? 0)]),
        ]);
    }

    public function newFdApplication(FixedDepositApplication $application, string $source = 'admin'): void
    {
        $application->loadMissing('client', 'scheme');
        $client = $application->client;
        $name = $this->clientName($client);
        $amount = $this->money($application->deposit_amount);
        $scheme = $application->scheme?->name ?? 'FD scheme';
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'new_fd_application',
            'title' => 'New FD Application',
            'admin_message' => "{$name} applied for {$scheme} ({$amount}) from the {$label}.",
            'customer_title' => 'FD Application Submitted',
            'customer_message' => "Your FD application {$this->ref($application->application_number)} for {$amount} has been submitted.",
            'agent_title' => 'Client FD Application',
            'agent_message' => "{$name} applied for {$scheme} ({$amount}) via the {$label}.",
            'link' => $this->safeRoute('fd.applications.show', $application),
            'icon' => 'ri-safe-2-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'fd_application',
            'data' => [
                'screen' => 'fd_application',
                'application_id' => (string) $application->id,
                'application_number' => (string) ($application->application_number ?? ''),
                'source' => $source,
            ],
        ]);
    }

    public function newSettlementApplication(Payout $payout, string $source = 'admin'): void
    {
        $payout->loadMissing('winner.client', 'group');
        $client = $payout->winner?->client;
        $name = $this->clientName($client);
        $group = $payout->group?->group_code ?? ('Group #' . $payout->group_id);
        $amount = $this->money($payout->payout_amount);
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'new_settlement_application',
            'title' => 'New Chit Settlement Application',
            'admin_message' => "{$name} applied for chit settlement in {$group} ({$amount}) from the {$label}.",
            'customer_title' => 'Settlement Application Submitted',
            'customer_message' => "Your chit settlement application for {$group} ({$amount}) has been submitted.",
            'agent_title' => 'Client Settlement Application',
            'agent_message' => "{$name} applied for settlement in {$group} ({$amount}) via the {$label}.",
            'link' => $this->safeRoute('chit.settlement-applications.show', $payout),
            'icon' => 'ri-hand-coin-line',
            'related_id' => $payout->id,
            'unique' => true,
            'fcm_type' => 'chit_settlement_application',
            'data' => [
                'screen' => 'chit_settlement',
                'payout_id' => (string) $payout->id,
                'source' => $source,
            ],
        ]);
    }

    public function loanApproved(Client $client, LoanApplication $application): void
    {
        $amount = $this->money($application->loan_amount);
        $ref = $this->ref($application->application_number);

        $this->notifyAll($client, [
            'type' => 'loan_application_approved',
            'title' => 'Loan Application Approved',
            'admin_message' => "{$this->clientName($client)} loan application {$ref} ({$amount}) was approved.",
            'customer_title' => 'Loan Application Approved',
            'customer_message' => "Your loan application {$ref} was approved.",
            'agent_title' => 'Client Loan Approved',
            'agent_message' => "{$this->clientName($client)} loan application {$ref} was approved.",
            'link' => $this->safeRoute('loan-application-view', $application->id),
            'icon' => 'ri-checkbox-circle-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'approved',
            'data' => [
                'screen' => 'approved',
                'application_number' => (string) ($application->application_number ?? ''),
            ],
        ]);
    }

    public function loanRejected(Client $client, LoanApplication $application, ?string $reason = null): void
    {
        $ref = $this->ref($application->application_number);
        $reasonText = $reason ? " Reason: {$reason}" : '';

        $this->notifyAll($client, [
            'type' => 'loan_application_rejected',
            'title' => 'Loan Application Rejected',
            'admin_message' => "{$this->clientName($client)} loan application {$ref} was rejected.{$reasonText}",
            'customer_title' => 'Loan Application Rejected',
            'customer_message' => "Your loan application {$ref} was rejected.{$reasonText}",
            'agent_title' => 'Client Loan Rejected',
            'agent_message' => "{$this->clientName($client)} loan application {$ref} was rejected.",
            'link' => $this->safeRoute('loan-application-view', $application->id),
            'icon' => 'ri-close-circle-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'rejected',
            'data' => [
                'screen' => 'rejected',
                'application_number' => (string) ($application->application_number ?? ''),
            ],
        ]);
    }

    public function loanDisbursed(Client $client, LoanApplication $application): void
    {
        $amount = $this->money($application->loan_amount);
        $ref = $this->ref($application->application_number);

        $this->notifyAll($client, [
            'type' => 'loan_disbursed',
            'title' => 'Loan Disbursed',
            'admin_message' => "Loan {$ref} for {$this->clientName($client)} ({$amount}) was disbursed.",
            'customer_title' => 'Loan Disbursed',
            'customer_message' => "Your loan application {$ref} was disbursed.",
            'agent_title' => 'Client Loan Disbursed',
            'agent_message' => "Loan {$ref} for {$this->clientName($client)} ({$amount}) was disbursed.",
            'link' => $this->safeRoute('loan-application-view', $application->id),
            'icon' => 'ri-bank-card-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'disbursed',
            'data' => [
                'screen' => 'disbursed',
                'application_number' => (string) ($application->application_number ?? ''),
            ],
        ]);
    }

    public function chitApproved(GroupMember $member): void
    {
        $member->loadMissing('client', 'group');
        $client = $member->client;
        $group = $member->group?->group_code ?? ('Group #' . $member->group_id);

        $this->notifyAll($client, [
            'type' => 'chit_application_approved',
            'title' => 'Chit Application Approved',
            'admin_message' => "{$this->clientName($client)} chit application for {$group} was approved.",
            'customer_title' => 'Chit Application Approved',
            'customer_message' => "Your chit application for group {$group} was approved.",
            'agent_title' => 'Client Chit Approved',
            'agent_message' => "{$this->clientName($client)} chit application for {$group} was approved.",
            'link' => $this->safeRoute('chit.applications.show', $member),
            'icon' => 'ri-checkbox-circle-line',
            'related_id' => $member->id,
            'unique' => true,
            'fcm_type' => 'chit_approved',
            'data' => [
                'screen' => 'chit_application',
                'member_id' => (string) $member->id,
                'client_id' => (string) ($client->id ?? ''),
                'customer_name' => $this->clientName($client),
            ],
            'extra_agent_ids' => array_filter([(int) ($member->referred_by_agent_id ?? 0)]),
        ]);
    }

    public function chitRejected(GroupMember $member): void
    {
        $member->loadMissing('client', 'group');
        $client = $member->client;
        $group = $member->group?->group_code ?? ('Group #' . $member->group_id);

        $this->notifyAll($client, [
            'type' => 'chit_application_rejected',
            'title' => 'Chit Application Rejected',
            'admin_message' => "{$this->clientName($client)} chit application for {$group} was rejected.",
            'customer_title' => 'Chit Application Rejected',
            'customer_message' => "Your chit application for group {$group} was rejected.",
            'agent_title' => 'Client Chit Rejected',
            'agent_message' => "{$this->clientName($client)} chit application for {$group} was rejected.",
            'link' => $this->safeRoute('chit.applications.show', $member),
            'icon' => 'ri-close-circle-line',
            'related_id' => $member->id,
            'unique' => true,
            'fcm_type' => 'chit_rejected',
            'data' => [
                'screen' => 'chit_application',
                'member_id' => (string) $member->id,
                'client_id' => (string) ($client->id ?? ''),
                'customer_name' => $this->clientName($client),
            ],
            'extra_agent_ids' => array_filter([(int) ($member->referred_by_agent_id ?? 0)]),
        ]);
    }

    public function fdApproved(FixedDepositApplication $application): void
    {
        $application->loadMissing('client', 'scheme');
        $client = $application->client;
        $ref = $this->ref($application->application_number);
        $amount = $this->money($application->deposit_amount);

        $this->notifyAll($client, [
            'type' => 'fd_application_approved',
            'title' => 'FD Application Approved',
            'admin_message' => "{$this->clientName($client)} FD application {$ref} ({$amount}) was approved.",
            'customer_title' => 'FD Application Approved',
            'customer_message' => "Your FD application {$ref} was approved.",
            'agent_title' => 'Client FD Approved',
            'agent_message' => "{$this->clientName($client)} FD application {$ref} was approved.",
            'link' => $this->safeRoute('fd.applications.show', $application),
            'icon' => 'ri-checkbox-circle-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'fd_approved',
            'data' => [
                'screen' => 'fd_application',
                'application_id' => (string) $application->id,
            ],
        ]);
    }

    public function fdRejected(FixedDepositApplication $application, ?string $reason = null): void
    {
        $application->loadMissing('client');
        $client = $application->client;
        $ref = $this->ref($application->application_number);
        $reasonText = $reason ? " Reason: {$reason}" : '';

        $this->notifyAll($client, [
            'type' => 'fd_application_rejected',
            'title' => 'FD Application Rejected',
            'admin_message' => "{$this->clientName($client)} FD application {$ref} was rejected.{$reasonText}",
            'customer_title' => 'FD Application Rejected',
            'customer_message' => "Your FD application {$ref} was rejected.{$reasonText}",
            'agent_title' => 'Client FD Rejected',
            'agent_message' => "{$this->clientName($client)} FD application {$ref} was rejected.",
            'link' => $this->safeRoute('fd.applications.show', $application),
            'icon' => 'ri-close-circle-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'fd_rejected',
            'data' => [
                'screen' => 'fd_application',
                'application_id' => (string) $application->id,
            ],
        ]);
    }

    public function fdBooked(FixedDepositApplication $application): void
    {
        $application->loadMissing('client', 'scheme');
        $client = $application->client;
        $ref = $this->ref($application->application_number);
        $amount = $this->money($application->deposit_amount);

        $this->notifyAll($client, [
            'type' => 'fd_booked',
            'title' => 'FD Booked',
            'admin_message' => "FD {$ref} for {$this->clientName($client)} ({$amount}) was booked.",
            'customer_title' => 'Fixed Deposit Booked',
            'customer_message' => "Your fixed deposit {$ref} has been booked.",
            'agent_title' => 'Client FD Booked',
            'agent_message' => "FD {$ref} for {$this->clientName($client)} ({$amount}) was booked.",
            'link' => $this->safeRoute('fd.applications.show', $application),
            'icon' => 'ri-safe-2-line',
            'related_id' => $application->id,
            'unique' => true,
            'fcm_type' => 'fd_booked',
            'data' => [
                'screen' => 'fd_account',
                'application_id' => (string) $application->id,
                'fixed_deposit_id' => (string) ($application->fixed_deposit_id ?? ''),
            ],
        ]);
    }

    public function emiCollected(Emi $emi, float $amount, string $source = 'admin'): void
    {
        $emi->loadMissing('loanAccount.client', 'loanAccount.loanApplication.client');
        $loanAccount = $emi->loanAccount;
        $client = optional($loanAccount?->loanApplication)->client ?? $loanAccount?->client;
        $name = $this->clientName($client);
        $money = $this->money($amount);
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        $instalmentLabel = $isKandhuvatti ? 'Kandhuvatti cycle' : 'EMI';
        $title = $isKandhuvatti ? 'Kandhuvatti Payment' : 'EMI Payment';
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'payment_received',
            'title' => $title,
            'admin_message' => "{$money} received from {$name} for {$instalmentLabel} #{$emi->instalment_number} via the {$label}.",
            'customer_title' => $title . ' Received',
            'customer_message' => "We received your {$instalmentLabel} payment of {$money}.",
            'agent_title' => $title,
            'agent_message' => "{$money} collected from {$name} for {$instalmentLabel} #{$emi->instalment_number}.",
            'link' => $this->safeRoute('view-receipt', $emi->id),
            'icon' => 'ri-money-rupee-circle-line',
            'related_id' => $emi->id,
            'unique' => false,
            'fcm_type' => 'emi_payment',
            'data' => [
                'screen' => 'emi_payment',
                'emi_id' => (string) $emi->id,
                'amount' => (string) $amount,
                'source' => $source,
            ],
        ]);
    }

    /**
     * One customer + agent + admin notification for a bulk EMI collection.
     *
     * @param  \Illuminate\Support\Collection<int, Emi>  $emis
     */
    public function emiBulkCollected($emis, float $amount, ?Agent $collectingAgent = null, string $source = 'admin'): void
    {
        $emis = collect($emis)->filter()->values();
        $first = $emis->first();
        if (! $first) {
            return;
        }

        if ($emis->count() === 1) {
            $this->emiCollected($first, $amount, $source);
            if ($collectingAgent) {
                $first->loadMissing('loanAccount.client');
                $client = $first->loanAccount?->client;
                if ($client && (int) $client->assigned_to !== (int) $collectingAgent->id) {
                    $this->notifyAgent(
                        $collectingAgent,
                        'EMI Payment Verified',
                        $this->money($amount) . ' verified for ' . $this->clientName($client) . ' (EMI #' . $first->instalment_number . ').',
                        'emi_payment',
                        ['screen' => 'emi_payment', 'emi_id' => (string) $first->id],
                        $first->id
                    );
                }
            }

            return;
        }

        $first->loadMissing('loanAccount.client', 'loanAccount.loanApplication.client');
        $loanAccount = $first->loanAccount;
        $client = optional($loanAccount?->loanApplication)->client ?? $loanAccount?->client;
        $name = $this->clientName($client);
        $money = $this->money($amount);
        $emiLabel = $this->formatEmiNumberList($emis);
        $count = $emis->count();
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'payment_received',
            'title' => 'EMI Payment',
            'admin_message' => "{$money} received from {$name} for {$count} EMIs ({$emiLabel}) via the {$label}.",
            'customer_title' => 'EMI Payment Received',
            'customer_message' => "We received your payment of {$money} for {$emiLabel}.",
            'agent_title' => 'EMI Payment Verified',
            'agent_message' => "{$money} verified for {$name} ({$emiLabel}).",
            'link' => $this->safeRoute('view-receipt', $first->id),
            'icon' => 'ri-money-rupee-circle-line',
            'related_id' => $first->id,
            'unique' => false,
            'fcm_type' => 'emi_payment',
            'data' => [
                'screen' => 'emi_payment',
                'emi_id' => (string) $first->id,
                'amount' => (string) $amount,
                'emi_count' => (string) $count,
                'source' => $source,
            ],
        ]);

        if ($collectingAgent && $client && (int) $client->assigned_to !== (int) $collectingAgent->id) {
            $this->notifyAgent(
                $collectingAgent,
                'EMI Payment Verified',
                "{$money} verified for {$name} ({$emiLabel}).",
                'emi_payment',
                [
                    'screen' => 'emi_payment',
                    'emi_id' => (string) $first->id,
                    'amount' => (string) $amount,
                ],
                $first->id
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Emi>  $emis
     */
    protected function formatEmiNumberList($emis): string
    {
        $nums = collect($emis)
            ->map(fn ($emi) => (int) ($emi->instalment_number ?? 0))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($nums === []) {
            return 'EMI payment';
        }

        $ranges = [];
        $start = $end = $nums[0];
        for ($i = 1, $len = count($nums); $i < $len; $i++) {
            if ($nums[$i] === $end + 1) {
                $end = $nums[$i];
                continue;
            }
            $ranges[] = $start === $end ? "#{$start}" : "#{$start}–#{$end}";
            $start = $end = $nums[$i];
        }
        $ranges[] = $start === $end ? "#{$start}" : "#{$start}–#{$end}";

        return (count($nums) === 1 ? 'EMI ' : 'EMIs ') . implode(', ', $ranges);
    }

    public function chitInstallmentCollected(Installment $installment, float $amount, ?Client $client = null, string $source = 'admin', ?Agent $collectingAgent = null, bool $verified = false): void
    {
        $installment->loadMissing('member.client', 'group');
        $client = $client ?: $installment->member?->client;
        $name = $this->clientName($client);
        $group = $installment->group?->group_code ?? ('Group #' . $installment->group_id);
        $money = $this->money($amount);
        $label = self::sourceLabel($source);
        $agentTitle = $verified ? 'Chit Payment Verified' : 'Chit Installment Collected';
        $agentMessage = $verified
            ? "{$money} verified for {$name} ({$group} month {$installment->month_number})."
            : "{$money} collected from {$name} for {$group} month {$installment->month_number}.";

        $this->notifyAll($client, [
            'type' => 'chit_installment_collected',
            'title' => $verified ? 'Chit Payment Verified' : 'Chit Installment Collected',
            'admin_message' => $verified
                ? "{$money} verified from {$name} for {$group} month {$installment->month_number}."
                : "{$money} collected from {$name} for {$group} month {$installment->month_number} via the {$label}.",
            'customer_title' => 'Chit Installment Received',
            'customer_message' => "We received your chit installment of {$money} for {$group} (month {$installment->month_number}).",
            'agent_title' => $agentTitle,
            'agent_message' => $agentMessage,
            'link' => $this->safeRoute('chit.installments.index'),
            'icon' => 'ri-money-rupee-circle-line',
            'related_id' => $installment->id,
            'unique' => false,
            'fcm_type' => 'chit_installment',
            'data' => [
                'screen' => 'chit_installment',
                'installment_id' => (string) $installment->id,
                'amount' => (string) $amount,
                'source' => $source,
            ],
        ]);

        if ($collectingAgent && $client && (int) $client->assigned_to !== (int) $collectingAgent->id) {
            $this->notifyAgent(
                $collectingAgent,
                $agentTitle,
                $agentMessage,
                'chit_installment',
                [
                    'screen' => 'chit_installment',
                    'installment_id' => (string) $installment->id,
                    'amount' => (string) $amount,
                ],
                $installment->id
            );
        }
    }

    /**
     * One customer + agent + admin notification for a bulk (or single) chit collection verify.
     *
     * @param  \Illuminate\Support\Collection<int, Installment>  $installments
     */
    public function chitBulkCollected($installments, float $amount, ?Agent $collectingAgent = null, string $source = 'admin'): void
    {
        $installments = collect($installments)->filter()->values();
        $first = $installments->first();
        if (! $first) {
            return;
        }

        if ($installments->count() === 1) {
            $this->chitInstallmentCollected($first, $amount, $first->member?->client, $source, $collectingAgent, true);

            return;
        }

        $first->loadMissing('member.client', 'group');
        $client = $first->member?->client;
        $name = $this->clientName($client);
        $group = $first->group?->group_code ?? ('Group #' . $first->group_id);
        $money = $this->money($amount);
        $monthLabel = $this->formatChitMonthList($installments);
        $count = $installments->count();
        $label = self::sourceLabel($source);

        $this->notifyAll($client, [
            'type' => 'chit_installment_collected',
            'title' => 'Chit Payment Verified',
            'admin_message' => "{$money} received from {$name} for {$group} {$monthLabel} ({$count} installments) via the {$label}.",
            'customer_title' => 'Chit Installment Received',
            'customer_message' => "We received your payment of {$money} for {$group} ({$monthLabel}).",
            'agent_title' => 'Chit Payment Verified',
            'agent_message' => "{$money} verified for {$name} ({$group} {$monthLabel}).",
            'link' => $this->safeRoute('chit.installments.index'),
            'icon' => 'ri-money-rupee-circle-line',
            'related_id' => $first->id,
            'unique' => false,
            'fcm_type' => 'chit_installment',
            'data' => [
                'screen' => 'chit_installment',
                'installment_id' => (string) $first->id,
                'amount' => (string) $amount,
                'installment_count' => (string) $count,
                'source' => $source,
            ],
        ]);

        if ($collectingAgent && $client && (int) $client->assigned_to !== (int) $collectingAgent->id) {
            $this->notifyAgent(
                $collectingAgent,
                'Chit Payment Verified',
                "{$money} verified for {$name} ({$group} {$monthLabel}).",
                'chit_installment',
                [
                    'screen' => 'chit_installment',
                    'installment_id' => (string) $first->id,
                    'amount' => (string) $amount,
                ],
                $first->id
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Installment>  $installments
     */
    protected function formatChitMonthList($installments): string
    {
        $nums = collect($installments)
            ->map(fn ($row) => (int) ($row->month_number ?? 0))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($nums === []) {
            return 'chit installment';
        }

        $ranges = [];
        $start = $end = $nums[0];
        for ($i = 1, $len = count($nums); $i < $len; $i++) {
            if ($nums[$i] === $end + 1) {
                $end = $nums[$i];
                continue;
            }
            $ranges[] = $start === $end ? "#{$start}" : "#{$start}–#{$end}";
            $start = $end = $nums[$i];
        }
        $ranges[] = $start === $end ? "#{$start}" : "#{$start}–#{$end}";

        return (count($nums) === 1 ? 'month ' : 'months ') . implode(', ', $ranges);
    }

    public function settlementPaid(Payout $payout): void
    {
        $payout->loadMissing('winner.client', 'group');
        $client = $payout->winner?->client;
        $name = $this->clientName($client);
        $group = $payout->group?->group_code ?? ('Group #' . $payout->group_id);
        $amount = $this->money($payout->net_payout_amount ?: $payout->payout_amount);

        $this->notifyAll($client, [
            'type' => 'chit_settlement_paid',
            'title' => 'Chit Settlement Paid',
            'admin_message' => "Settlement of {$amount} paid to {$name} for {$group} (month {$payout->month_number}).",
            'customer_title' => 'Chit Settlement Paid',
            'customer_message' => "Your chit settlement of {$amount} for {$group} has been paid.",
            'agent_title' => 'Client Settlement Paid',
            'agent_message' => "Settlement of {$amount} was paid to {$name} for {$group}.",
            'link' => $this->safeRoute('chit.settlement-applications.show', $payout),
            'icon' => 'ri-hand-coin-line',
            'related_id' => $payout->id,
            'unique' => true,
            'fcm_type' => 'chit_settlement_paid',
            'data' => [
                'screen' => 'chit_settlement',
                'payout_id' => (string) $payout->id,
            ],
        ]);
    }

    public function clientAssigned(Client $client, Agent $agent): void
    {
        $name = $this->clientName($client);
        $agentName = $agent->agent_name ?? $agent->user?->name ?? 'agent';

        $this->notifyAdmin(
            'client_assigned',
            'Client Assigned to Agent',
            "{$name} was assigned to {$agentName}.",
            $this->safeRoute('client-view-account', $client->id),
            $client->id,
            'ri-user-shared-line',
            false
        );

        $this->notifyCustomer(
            $client,
            'Agent Assigned',
            "{$agentName} has been assigned as your agent.",
            'client_assigned',
            [
                'screen' => 'profile',
                'agent_id' => (string) $agent->id,
            ]
        );

        $this->notifyAgent(
            $agent,
            'New Client Assigned',
            "{$name} has been assigned to you.",
            'client_assigned',
            [
                'screen' => 'client',
                'client_id' => (string) $client->id,
            ],
            $client->id,
            'high'
        );
    }

    public function kycApproved(Client $client): void
    {
        $this->notifyAll($client, [
            'type' => 'kyc_approved',
            'title' => 'KYC Approved',
            'admin_message' => "KYC for {$this->clientName($client)} was approved.",
            'customer_title' => 'KYC Approved',
            'customer_message' => 'Congratulations! Your KYC has been approved. You can now apply for loans, chits and other services.',
            'agent_title' => 'Client KYC Approved for',
            'agent_message' => "KYC for {$this->clientName($client)} was approved.",
            'link' => $this->safeRoute('client-view-kyc', $client->id),
            'icon' => 'ri-shield-check-line',
            'related_id' => $client->id,
            'unique' => true,
            'fcm_type' => 'kyc-approved',
            'data' => ['screen' => 'kyc-approved'],
        ]);
    }

    public function kycRejected(Client $client, ?string $reason = null): void
    {
        $reasonText = $reason ? " Reason: {$reason}" : '';

        $this->notifyAll($client, [
            'type' => 'kyc_rejected',
            'title' => 'KYC Rejected',
            'admin_message' => "KYC for {$this->clientName($client)} was rejected.{$reasonText}",
            'customer_title' => 'KYC Rejected',
            'customer_message' => "Your KYC was rejected.{$reasonText}",
            'agent_title' => 'Client KYC Rejected',
            'agent_message' => "KYC for {$this->clientName($client)} was rejected.",
            'link' => $this->safeRoute('client-view-kyc', $client->id),
            'icon' => 'ri-shield-cross-line',
            'related_id' => $client->id,
            'unique' => true,
            'fcm_type' => 'kyc-rejected',
            'data' => [
                'screen' => 'kyc-rejected',
                'reason' => (string) $reason,
            ],
        ]);
    }

    public function newSupportTicket(SupportTicket $ticket, string $source = 'customer'): void
    {
        $ticket->loadMissing('client');
        $client = $ticket->client;
        $name = $this->clientName($client);

        $this->notifyAll($client, [
            'type' => 'support_ticket',
            'title' => "New Support Ticket #{$ticket->ticket_number}",
            'admin_message' => "{$name} created support ticket #{$ticket->ticket_number}: {$ticket->subject}",
            'customer_title' => 'Support Ticket Submitted',
            'customer_message' => "Your support ticket #{$ticket->ticket_number} for '{$ticket->subject}' has been submitted successfully.",
            'agent_title' => 'Support Ticket Created',
            'agent_message' => "{$name} created support ticket #{$ticket->ticket_number}.",
            'link' => url("support/tickets/{$ticket->id}"),
            'icon' => 'ri-customer-service-2-line',
            'related_id' => $ticket->id,
            'fcm_type' => 'support_ticket',
            'data' => [
                'screen' => 'support_ticket_details',
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => (string) $ticket->ticket_number,
                'source' => $source,
            ],
        ]);
    }

    public function supportTicketReply(SupportTicket $ticket, SupportTicketReply $reply, string $source = 'admin'): void
    {
        $ticket->loadMissing('client');
        $client = $ticket->client;
        $name = $this->clientName($client);

        if ($source === 'admin') {
            $msgSnippet = \Illuminate\Support\Str::limit($reply->message, 100);
            $this->notifyCustomer(
                $client,
                "New Reply on Ticket #{$ticket->ticket_number}",
                "Support Team: {$msgSnippet}",
                'support_ticket_reply',
                [
                    'screen' => 'support_ticket_details',
                    'ticket_id' => (string) $ticket->id,
                    'ticket_number' => (string) $ticket->ticket_number,
                    'reply_id' => (string) $reply->id,
                ]
            );
        } else {
            $msgSnippet = \Illuminate\Support\Str::limit($reply->message, 100);
            $this->notifyAdmin(
                'support_ticket_reply',
                "Customer Replied on Ticket #{$ticket->ticket_number}",
                "{$name} replied: {$msgSnippet}",
                url("support/tickets/{$ticket->id}"),
                $ticket->id,
                'ri-question-answer-line'
            );
        }
    }

    public function supportTicketStatusUpdated(SupportTicket $ticket, string $newStatus): void
    {
        $ticket->loadMissing('client');
        $client = $ticket->client;
        $statusLabel = ucfirst($newStatus);

        $this->notifyCustomer(
            $client,
            "Ticket #{$ticket->ticket_number} Updated",
            "Your support ticket #{$ticket->ticket_number} status is now {$statusLabel}.",
            'support_ticket_status',
            [
                'screen' => 'support_ticket_details',
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => (string) $ticket->ticket_number,
                'status' => $newStatus,
            ]
        );
    }

    /**
     * @param  array{
     *   type: string,
     *   title: string,
     *   admin_message: string,
     *   customer_title?: string,
     *   customer_message?: string,
     *   agent_title?: string,
     *   agent_message?: string,
     *   link?: string|null,
     *   icon?: string,
     *   related_id?: int|null,
     *   unique?: bool,
     *   fcm_type?: string,
     *   data?: array
     * }  $payload
     */
    public function notifyAll(?Client $client, array $payload): void
    {
        try {
            $this->notifyAdmin(
                $payload['type'],
                $payload['title'],
                $payload['admin_message'],
                $payload['link'] ?? null,
                $payload['related_id'] ?? null,
                $payload['icon'] ?? 'ri-notification-3-line',
                (bool) ($payload['unique'] ?? false)
            );

            if ($client) {
                $this->notifyCustomer(
                    $client,
                    $payload['customer_title'] ?? $payload['title'],
                    $payload['customer_message'] ?? $payload['admin_message'],
                    $payload['fcm_type'] ?? $payload['type'],
                    $payload['data'] ?? []
                );

                $this->notifyAssignedAgent(
                    $client,
                    $payload['agent_title'] ?? $payload['title'],
                    $payload['agent_message'] ?? $payload['admin_message'],
                    $payload['fcm_type'] ?? $payload['type'],
                    $payload['data'] ?? [],
                    $payload['related_id'] ?? null,
                    'normal',
                    $payload['extra_agent_ids'] ?? []
                );
            }
        } catch (Throwable $e) {
            Log::error('App notification fan-out failed: ' . $e->getMessage(), [
                'type' => $payload['type'] ?? null,
            ]);
        }
    }

    public function notifyAdmin(
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?int $relatedId = null,
        string $icon = 'ri-notification-3-line',
        bool $unique = false
    ): ?AdminNotification {
        try {
            if ($unique && $relatedId) {
                $exists = AdminNotification::where('type', $type)
                    ->where('related_id', $relatedId)
                    ->exists();
                if ($exists) {
                    return AdminNotification::where('type', $type)
                        ->where('related_id', $relatedId)
                        ->latest('id')
                        ->first();
                }
            }

            $notification = AdminNotification::create([
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'icon' => $icon,
                'related_id' => $relatedId,
            ]);

            $this->pushAdmins($title, $message, $type, [
                'related_id' => (string) ($relatedId ?? ''),
            ]);

            return $notification;
        } catch (Throwable $e) {
            Log::error('Failed to create admin notification: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Save an in-app inbox row for the client, then try FCM.
     *
     * @return array{saved: bool, push: bool, push_error: ?string}
     */
    public function notifyCustomer(?Client $client, string $title, string $body, string $type = 'general', array $data = []): array
    {
        if (! $client) {
            return ['saved' => false, 'push' => false, 'push_error' => 'Client not found'];
        }

        // Prevent duplicate customer notifications within a 10-second window
        $recentDuplicate = CustomerNotification::where('client_id', $client->id)
            ->where('notification_type', $type)
            ->where('title', $title)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->exists();

        if ($recentDuplicate) {
            Log::info('Skipped duplicate customer notification within 10s window', [
                'client_id' => $client->id,
                'type' => $type,
                'title' => $title,
            ]);

            return ['saved' => false, 'push' => false, 'push_error' => 'Duplicate notification skipped'];
        }

        $saved = false;
        try {
            CustomerNotification::create([
                'client_id' => $client->id,
                'notification_type' => $type,
                'notification_id' => $type . '_' . $client->id . '_' . now()->timestamp . '_' . uniqid(),
                'title' => $title,
                'message' => $body,
                'notification_type_label' => str_replace('_', ' ', $type),
                'icon' => 'bell',
                'priority' => 'medium',
                'action_data' => array_merge($data, ['type' => $type]),
            ]);
            $saved = true;
        } catch (Throwable $e) {
            Log::error('Failed to create customer notification: ' . $e->getMessage(), [
                'client_id' => $client->id,
            ]);
        }

        $pushOk = false;
        $pushError = null;
        try {
            $result = $this->push->sendToCustomer($client, $title, $body, $type, $data);
            $pushOk = ! empty($result['success']);
            $pushError = $pushOk ? null : (string) ($result['error'] ?? 'Push not delivered');
        } catch (Throwable $e) {
            $pushError = $e->getMessage();
            Log::error('Customer push failed: ' . $pushError, ['client_id' => $client->id]);
        }

        return [
            'saved' => $saved,
            'push' => $pushOk,
            'push_error' => $pushError,
        ];
    }

    public function notifyAssignedAgent(
        ?Client $client,
        string $title,
        string $body,
        string $type,
        array $data = [],
        ?int $relatedId = null,
        string $priority = 'high',
        array $extraAgentIds = []
    ): void {
        foreach ($this->resolveClientAgents($client, $extraAgentIds) as $agent) {
            $skipPush = $client
                && $agent->user_id
                && $client->user_id
                && (int) $agent->user_id === (int) $client->user_id;

            $this->notifyAgent($agent, $title, $body, $type, $data, $relatedId, $priority, $skipPush);
        }
    }

    /**
     * Assigned agent, added-by agent, and extra ids (e.g. chit referrer).
     * Resolves both agents.id and users.id because assigned_to is mixed in live data.
     *
     * @param  list<int|string|null>  $extraAgentIds
     * @return \Illuminate\Support\Collection<int, Agent>
     */
    protected function resolveClientAgents(?Client $client, array $extraAgentIds = [])
    {
        $rawIds = $extraAgentIds;
        if ($client) {
            $rawIds[] = $client->assigned_to;
            $rawIds[] = $client->added_by;
        }

        $rawIds = array_values(array_unique(array_filter(array_map('intval', $rawIds))));
        $agents = collect();

        foreach ($rawIds as $id) {
            $agent = Agent::find($id) ?: Agent::where('user_id', $id)->first();
            if ($agent && ! $agents->firstWhere('id', $agent->id)) {
                $agents->push($agent);
            }
        }

        return $agents;
    }

    public function notifyAgent(
        Agent $agent,
        string $title,
        string $body,
        string $type,
        array $data = [],
        ?int $relatedId = null,
        string $priority = 'high',
        bool $skipPush = false
    ): void {
        // Prevent duplicate agent notifications within a 10-second window
        $recentDuplicate = AgentNotification::where('agent_id', $agent->id)
            ->where('notification_type', $type)
            ->where('title', $title)
            ->where('created_at', '>=', now()->subSeconds(10))
            ->exists();

        if ($recentDuplicate) {
            Log::info('Skipped duplicate agent notification within 10s window', [
                'agent_id' => $agent->id,
                'type' => $type,
                'title' => $title,
            ]);

            return;
        }

        $notificationId = $type . '_' . ($relatedId ?: $agent->id) . '_' . now()->timestamp . '_' . uniqid();

        try {
            AgentNotification::create([
                'agent_id' => $agent->id,
                'notification_type' => $type,
                'notification_id' => $notificationId,
                'title' => $title,
                'message' => $body,
                'notification_type_label' => str_replace('_', ' ', $type),
                'icon' => 'bell',
                'priority' => $priority,
                'action_data' => array_merge($data, ['type' => $type]),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to store agent notification: ' . $e->getMessage(), [
                'agent_id' => $agent->id,
                'type' => $type,
            ]);
        }

        if ($skipPush) {
            return;
        }

        try {
            $this->push->sendToAgent($agent, $title, $body, $type, $data);
        } catch (Throwable $e) {
            Log::error('Agent push notification failed: ' . $e->getMessage(), ['agent_id' => $agent->id]);
        }
    }

    protected function pushAdmins(string $title, string $body, string $type, array $data = []): void
    {
        try {
            if (! method_exists(User::class, 'role')) {
                return;
            }

            $admins = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Admin', 'Staff', 'Super Admin', 'admin', 'staff']))->get();
            foreach ($admins as $admin) {
                $this->push->sendToUser($admin->id, $title, $body, $type, array_merge($data, [
                    'audience' => 'admin',
                ]));
            }
        } catch (Throwable $e) {
            Log::info('Admin FCM fan-out skipped: ' . $e->getMessage());
        }
    }

    protected function clientName(?Client $client): string
    {
        return $client->client_name ?? 'Client';
    }

    protected function money($amount): string
    {
        return '₹' . number_format((float) $amount, 2);
    }

    protected function ref(?string $value): string
    {
        return $value ?: '';
    }

    protected function safeRoute(string $name, mixed $params = null): ?string
    {
        try {
            return $params === null ? route($name) : route($name, $params);
        } catch (Throwable $e) {
            return null;
        }
    }
}
