<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Models\DividendDistribution;
use App\Models\Installment;
use App\Models\Account\BankAccount;
use App\Services\PartialPaymentConfigService;
use Carbon\Carbon;

class ClientViewChitsController extends Controller
{
    public function __construct(
        protected PartialPaymentConfigService $partialPaymentConfig
    ) {}

    public function index($id)
    {
        Installment::applyAutomatedPenalties();

        $decodedId = \App\Support\HashId::decode($id) ?? $id;
        $client = Client::with(['kycDetail'])->findOrFail($decodedId);
        $clientId = (int) $decodedId;

        // Primary + shared memberships involving this client
        $enrollments = GroupMember::with(['group.scheme', 'group.dividends', 'group.branch', 'shares.client', 'payouts'])
            ->involvingClient($clientId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (GroupMember $member) use ($clientId) {
                $member->setAttribute('ownership_percentage', $member->ownershipPercentageFor($clientId));
                $member->setAttribute('share_of_chit', $member->amountForClient((float) ($member->group->chit_value ?? 0), $clientId));

                return $member;
            });

        $memberIds = $enrollments->pluck('id');

        $payouts = Payout::with(['group', 'auction', 'winner.shares'])
            ->whereIn('winner_member_id', $memberIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Payout $payout) use ($clientId) {
                $pct = $payout->winner ? $payout->winner->ownershipPercentageFor($clientId) : 100;
                $payout->setAttribute('ownership_percentage', $pct);
                $payout->setAttribute('client_share_amount', $payout->winner
                    ? $payout->winner->amountForClient((float) $payout->payout_amount, $clientId)
                    : (float) $payout->payout_amount);

                return $payout;
            });

        $dividends = DividendDistribution::with(['dividend.group', 'member.shares'])
            ->whereIn('member_id', $memberIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (DividendDistribution $div) use ($clientId) {
                $pct = $div->member ? $div->member->ownershipPercentageFor($clientId) : 100;
                $div->setAttribute('ownership_percentage', $pct);
                $div->setAttribute('client_share_amount', $div->member
                    ? $div->member->amountForClient((float) $div->amount, $clientId)
                    : (float) $div->amount);

                return $div;
            });

        // Fetch Installments for this client's chit memberships
        $installments = Installment::with(['member.client', 'member.shares', 'group.scheme', 'sharePayments', 'collections'])
            ->whereIn('member_id', $memberIds)
            ->orderBy('due_date', 'asc')
            ->orderBy('month_number', 'asc')
            ->get();

        $today = Carbon::now()->startOfDay();
        $baseName = $client->client_name ?? 'Client';
        $installments->each(function ($inst) use ($today, $baseName, $clientId) {
            if (!in_array($inst->status, ['paid', 'waived'], true) && $inst->due_date && $inst->due_date->lt($today)) {
                $inst->display_status = 'overdue';
            } else {
                $inst->display_status = $inst->status;
            }
            $inst->client_display_name = $baseName;
            $inst->ownership_percentage = $inst->member
                ? $inst->member->ownershipPercentageFor($clientId)
                : 100;
            $inst->client_share_amount = $inst->member
                ? $inst->member->displayAmountForInstallment($inst, $clientId)
                : (float) $inst->amount;
            $inst->client_share_penalty = $inst->member
                ? $inst->member->penaltyAmountForClient((float) $inst->penalty_amount, $clientId)
                : (float) $inst->penalty_amount;
            $inst->client_share_paid = $inst->member
                ? $inst->clientPaidShare($clientId)
                : (float) $inst->paid_amount;
            $inst->client_share_balance = $inst->member
                ? $inst->clientBalanceShare($clientId)
                : (float) $inst->balance;
            // Align balance when displayed principal differs from stored seat amount (legacy / share %).
            if (
                $inst->member
                && (float) $inst->paid_amount < 0.01
                && abs((float) $inst->amount - (float) $inst->client_share_amount) > 0.05
            ) {
                $inst->client_share_balance = max(
                    0,
                    round((float) $inst->client_share_amount + (float) $inst->client_share_penalty - (float) $inst->client_share_paid, 2)
                );
            }
            $inst->viewing_client_id = $clientId;
            $inst->penalty_amount = $inst->client_share_penalty;

            $latestCollection = $inst->collections ? $inst->collections->sortByDesc('id')->first() : null;
            $latestSharePayment = $inst->sharePayments ? $inst->sharePayments->sortByDesc('id')->first() : null;
            $paidDt = $latestCollection?->collected_at 
                ?? $latestCollection?->created_at 
                ?? $latestSharePayment?->created_at
                ?? ($inst->paid_date ? \Carbon\Carbon::parse($inst->paid_date) : null)
                ?? (in_array($inst->status, ['paid', 'partial'], true) ? $inst->updated_at : null);
            $inst->paid_datetime_formatted = $paidDt ? $paidDt->format('d-m-Y h:i A') : null;
        });

        $partialPaymentConfig = $this->partialPaymentConfig->getGlobalSettings();
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('account_name')->get();

        return view('admin.clients.client-view-chits', compact(
            'client',
            'enrollments',
            'payouts',
            'dividends',
            'installments',
            'partialPaymentConfig',
            'bankAccounts'
        ));
    }
}
