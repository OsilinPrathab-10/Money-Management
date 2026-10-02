<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Support\HashId;
use Illuminate\View\View;
use Carbon\Carbon;

class PublicChitController extends Controller
{
    public function viewSchedule(string $token): View
    {
        try {
            $rawToken = base64_decode($token, true);
            $hashString = ($rawToken !== false && ! empty($rawToken)) ? $rawToken : $token;
            $clientId = HashId::decode($hashString) ?? HashId::decode($token) ?? (is_numeric($hashString) ? (int) $hashString : null);

            if (! $clientId) {
                abort(404, 'Invalid chit schedule link.');
            }

            $client = Client::findOrFail($clientId);

            $memberships = GroupMember::with(['group.scheme', 'group.dividends', 'shares.client'])
                ->involvingClient($clientId)
                ->whereIn('status', ['active', 'approved', 'applied'])
                ->whereHas('group', function ($g) {
                    $g->whereNotIn('status', ['completed', 'closed', 'terminated']);
                })
                ->orderBy('group_id')
                ->get()
                ->reject(function ($membership) {
                    $mStatus = strtolower((string) $membership->status);
                    if (in_array($mStatus, ['completed', 'closed', 'terminated', 'withdrawn', 'cancelled', 'rejected'], true)) {
                        return true;
                    }
                    $gStatus = strtolower((string) ($membership->group?->status ?? ''));
                    if (in_array($gStatus, ['completed', 'closed', 'terminated'], true)) {
                        return true;
                    }
                    if (method_exists($membership, 'hasCompletedTenure') && $membership->hasCompletedTenure()) {
                        return true;
                    }
                    if (method_exists($membership, 'customerFacingStatus') && in_array($membership->customerFacingStatus(), ['completed', 'closed'], true)) {
                        return true;
                    }
                    return false;
                })
                ->values();

            $memberIds = $memberships->pluck('id');

            $payoutsGrouped = \App\Models\Payout::with('auction')
                ->whereIn('winner_member_id', $memberIds)
                ->get()
                ->groupBy('winner_member_id');

            $auctionsGrouped = \App\Models\Auction::whereIn('winner_member_id', $memberIds)
                ->get()
                ->groupBy('winner_member_id');

            $memberships->each(function ($membership) use ($payoutsGrouped, $auctionsGrouped, $client) {
                $mPayouts = $payoutsGrouped->get($membership->id, collect());
                $mAuctions = $auctionsGrouped->get($membership->id, collect());

                $membership->won_payout = $mPayouts->first(fn ($p) => in_array(strtolower($p->status ?? ''), ['paid', 'completed', 'disbursed'], true));
                $membership->pending_payout = $mPayouts->first(fn ($p) => in_array(strtolower($p->status ?? ''), ['pending', 'processing', 'submitted', 'requested'], true));
                $membership->won_auction = $mAuctions->first();
                $membership->payouts_by_month = $mPayouts->keyBy('month_number');
                $membership->auctions_by_month = $mAuctions->keyBy('month_number');

                $pct = $membership->ownershipPercentageFor((int) $client->id);
                $membership->ownership_percentage = $pct;
                $membership->client_chit_value = $membership->amountForClient((float) ($membership->group?->chit_value ?? 0), (int) $client->id);
                $membership->client_monthly_amount = $membership->installmentAmountForClient((int) $client->id, $membership->group, $membership->group?->current_month ?? 1);
            });

            $installments = Installment::with(['member.client', 'member.shares', 'group.scheme', 'sharePayments'])
                ->whereIn('member_id', $memberIds)
                ->whereNotIn('status', ['waived'])
                ->whereNull('deleted_at')
                ->orderBy('group_id')
                ->orderBy('member_id')
                ->orderBy('month_number')
                ->get();

            $today = Carbon::now()->startOfDay();
            $installments->each(function ($inst) use ($today, $client) {
                if (! in_array($inst->status, ['paid', 'waived'], true) && $inst->due_date && $inst->due_date->lt($today)) {
                    $inst->display_status = 'overdue';
                } else {
                    $inst->display_status = $inst->status;
                }

                $pct = $inst->member ? $inst->member->ownershipPercentageFor((int) $client->id) : 100;
                $inst->ownership_percentage = $pct;
                $inst->client_share_amount = $inst->member
                    ? $inst->member->amountForClient((float) $inst->amount, (int) $client->id)
                    : (float) $inst->amount;
                $inst->client_share_paid = $inst->member
                    ? $inst->clientPaidShare((int) $client->id)
                    : (float) $inst->paid_amount;
                $inst->client_share_balance = $inst->member
                    ? $inst->clientBalanceShare((int) $client->id)
                    : (float) $inst->balance;
            });

            // Group by member_id so multiple seats (A, B) in the same group are separate
            $byMember = $installments->groupBy('member_id');

            // Build seat letter map for clients with multiple memberships in the same group
            $seatLetterMap = [];
            $memberships->groupBy('group_id')->each(function ($siblings) use (&$seatLetterMap) {
                if ($siblings->count() <= 1) {
                    return;
                }
                $siblings->sortBy('id')->values()->each(function ($member, $index) use (&$seatLetterMap) {
                    $seatLetterMap[$member->id] = chr(65 + $index);
                });
            });

            $summary = [
                'total_groups' => $memberships->pluck('group_id')->unique()->count(),
                'total_memberships' => $memberships->count(),
                'total_installments' => $installments->count(),
                'paid' => $installments->where('display_status', 'paid')->count(),
                'pending' => $installments->where('display_status', 'pending')->count(),
                'overdue' => $installments->where('display_status', 'overdue')->count(),
                'partial' => $installments->where('display_status', 'partial')->count(),
                'total_paid_amount' => (float) $installments->sum(fn ($i) => isset($i->client_share_paid) ? (float) $i->client_share_paid : (float) $i->paid_amount),
                'total_balance' => (float) $installments->sum(fn ($i) => isset($i->client_share_balance) ? max(0, (float) $i->client_share_balance) : max(0, (float) $i->balance)),
            ];

            $progressPercent = $summary['total_installments'] > 0
                ? round(($summary['paid'] / $summary['total_installments']) * 100)
                : 0;

            return view('public.chit-schedule', compact(
                'client',
                'memberships',
                'installments',
                'byMember',
                'seatLetterMap',
                'summary',
                'progressPercent'
            ));
        } catch (\Exception $e) {
            abort(404, 'Invalid chit schedule link.');
        }
    }
}
