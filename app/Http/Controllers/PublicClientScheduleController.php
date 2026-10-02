<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\FixedDeposit;
use App\Models\FixedDepositApplication;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Support\HashId;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class PublicClientScheduleController extends Controller
{
    /**
     * View public repayment schedule for a client (combining Loans and Chits).
     */
    public function viewSchedule(string $token): View
    {
        try {
            $client = null;

            // Build list of candidate raw strings to try (direct token, base64 decoded, url decoded)
            $candidates = [];
            $trimmed = trim($token);
            if (!empty($trimmed)) {
                $candidates[] = $trimmed;
            }

            $decoded = @base64_decode($trimmed, true);
            if ($decoded !== false && !empty($decoded) && ctype_print($decoded)) {
                $decTrimmed = trim($decoded);
                if (!in_array($decTrimmed, $candidates, true)) {
                    $candidates[] = $decTrimmed;
                }
            }

            $urlDecoded = urldecode($trimmed);
            if ($urlDecoded !== $trimmed && !empty($urlDecoded)) {
                if (!in_array(trim($urlDecoded), $candidates, true)) {
                    $candidates[] = trim($urlDecoded);
                }
                $b64Url = @base64_decode($urlDecoded, true);
                if ($b64Url !== false && !empty($b64Url) && ctype_print($b64Url)) {
                    $b64UrlTrimmed = trim($b64Url);
                    if (!in_array($b64UrlTrimmed, $candidates, true)) {
                        $candidates[] = $b64UrlTrimmed;
                    }
                }
            }

            foreach ($candidates as $cand) {
                if (empty($cand)) {
                    continue;
                }

                // 1. Try decoding via HashId and checking if client exists
                $hashId = HashId::decode($cand);
                if ($hashId) {
                    $client = Client::find($hashId);
                    if ($client) {
                        break;
                    }
                }

                // 2. Try direct numeric client ID
                if (is_numeric($cand)) {
                    $client = Client::find((int) $cand);
                    if ($client) {
                        break;
                    }
                }

                // 3. Try lookup by loan application_number or account_number
                $loan = LoanAccount::with('client')
                    ->where('application_number', $cand)
                    ->orWhere('account_number', $cand)
                    ->first();
                if ($loan && $loan->client) {
                    $client = $loan->client;
                    break;
                }

                // 4. Try lookup by Loan Application
                $loanApp = LoanApplication::with('client')
                    ->where('application_number', $cand)
                    ->first();
                if ($loanApp && $loanApp->client) {
                    $client = $loanApp->client;
                    break;
                }

                // 5. Try lookup by FD Application or Fixed Deposit Account
                $fdApp = FixedDepositApplication::with('client')
                    ->where('application_number', $cand)
                    ->first();
                if ($fdApp && $fdApp->client) {
                    $client = $fdApp->client;
                    break;
                }
                $fd = FixedDeposit::with('client')
                    ->where('account_number', $cand)
                    ->first();
                if ($fd && $fd->client) {
                    $client = $fd->client;
                    break;
                }

                // 6. Try lookup by Chit Group Member (member_number)
                $gm = GroupMember::with('client')
                    ->where('member_number', $cand)
                    ->first();
                if ($gm && $gm->client) {
                    $client = $gm->client;
                    break;
                }

                // 7. Fallback lookup by client ID / customer_id / user_id / phone
                $cleanCustomerCandidate = preg_replace('/^APP/i', '', $cand);
                $client = Client::where('id', $cand)
                    ->orWhere('user_id', $cand)
                    ->orWhere('client_phone', $cand)
                    ->orWhere('customer_id', $cand)
                    ->orWhere('customer_id', $cleanCustomerCandidate)
                    ->first();
                if ($client) {
                    break;
                }
            }

            if (!$client) {
                abort(404, 'Invalid repayment schedule link.');
            }

            // 1. Fetch Active Loan Accounts & EMIs for this client (exclude closed/foreclosed loans)
            $loans = LoanAccount::with(['client', 'emis.collections', 'loanApplication.product', 'loanProduct'])
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['closed', 'foreclosed'])
                ->where(function ($q) {
                    $q->whereNull('is_foreclosed')->orWhere('is_foreclosed', false);
                })
                ->whereNull('closed_at')
                ->orderBy('id', 'desc')
                ->get()
                ->reject(function ($loan) {
                    if (in_array(strtolower((string) $loan->status), ['closed', 'foreclosed'], true)) {
                        return true;
                    }
                    if (!empty($loan->is_foreclosed) || !empty($loan->closed_at)) {
                        return true;
                    }
                    // Also check if all EMIs are paid and no outstanding balance remains
                    if ($loan->emis && $loan->emis->count() > 0) {
                        $hasUnpaidEmis = $loan->emis->contains(function ($emi) {
                            return !in_array(strtolower((string) $emi->status), ['paid'], true)
                                && ((float) $emi->total_amount - (float) $emi->paid_amount) > 0.05;
                        });
                        if (!$hasUnpaidEmis && (float) ($loan->outstanding_amount ?? 0) <= 0.05) {
                            return true;
                        }
                    }
                    return false;
                })
                ->values();

            // The public link shows dues only: unpaid instalments up to the end of
            // the current month, so past-month overdues remain visible while paid
            // and future-month instalments are hidden.
            $loans->each(function ($loan) {
                $loan->due_emis = $loan->currentlyDueEmis();
            });

            // 2. Fetch Active Chit Memberships for this client (exclude closed/completed/terminated chits)
            $memberships = GroupMember::with(['group.scheme', 'group.dividends', 'shares.client'])
                ->involvingClient($client->id)
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

            // Fetch Payouts and Auctions for Chit Memberships
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
                $membership->client_chit_value = $membership->amountForClient((float)($membership->group?->chit_value ?? 0), (int) $client->id);
                $membership->client_monthly_amount = $membership->installmentAmountForClient((int) $client->id, $membership->group, $membership->group?->current_month ?? 1);
            });

            // Fetch installments strictly for active memberships only (do not include closed chits)
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

            $byMember = $installments->groupBy('member_id');
            $seatLetterMap = [];
            $memberships->groupBy('group_id')->each(function ($siblings) use (&$seatLetterMap) {
                if ($siblings->count() <= 1) {
                    return;
                }
                $siblings->sortBy('id')->values()->each(function ($member, $index) use (&$seatLetterMap) {
                    $seatLetterMap[$member->id] = chr(65 + $index);
                });
            });

            // 3. Fetch Fixed Deposits for this client
            $fixedDeposits = \App\Models\FixedDeposit::with('scheme')
                ->where('client_id', $client->id)
                ->orderBy('id', 'desc')
                ->get();

            // 4. Compute consolidated statistics across Loans + Chits + FDs
            $loansPayable = 0;
            $loansPaid = 0;
            $loansBalance = 0;
            $loansOverdueCount = 0;

            foreach ($loans as $l) {
                $lPayable = (float) ($l->total_payable ?: $l->loan_amount ?: 0);
                $lPaid = (float) ($l->total_paid ?? $l->paid_amount ?? $l->emis->sum('paid_amount'));
                $lBal = (isset($l->outstanding_amount) && (float) $l->outstanding_amount >= 0)
                    ? (float) $l->outstanding_amount
                    : $l->emis->sum(fn ($e) => max(0, (float) $e->total_amount - (float) $e->paid_amount));

                if ($lPayable < ($lPaid + $lBal)) {
                    $lPayable = $lPaid + $lBal;
                }

                $loansPayable += $lPayable;
                $loansPaid += $lPaid;
                $loansBalance += $lBal;

                foreach ($l->emis as $e) {
                    if ($e->status === 'overdue' || ($e->status !== 'paid' && $e->due_date && Carbon::parse($e->due_date)->lt($today))) {
                        $loansOverdueCount++;
                    }
                }
            }

            $chitsPaid = (float) $installments->sum(fn ($i) => isset($i->client_share_paid) ? (float) $i->client_share_paid : (float) $i->paid_amount);
            $chitsBalance = (float) $installments->sum(fn ($i) => isset($i->client_share_balance) ? max(0, (float) $i->client_share_balance) : max(0, (float) $i->balance));
            $chitsOverdueCount = $installments->where('display_status', 'overdue')->count();

            $summary = [
                'has_loans' => $loans->count() > 0,
                'has_chits' => $memberships->count() > 0,
                'has_fds' => $fixedDeposits->count() > 0,
                'total_loans' => $loans->count(),
                'total_chits' => $memberships->count(),
                'total_fds' => $fixedDeposits->count(),
                'total_payable_sum' => $loansPayable + ($chitsPaid + $chitsBalance),
                'total_paid' => $loansPaid + $chitsPaid,
                'total_balance' => $loansBalance + $chitsBalance,
                'total_overdue' => $loansOverdueCount + $chitsOverdueCount,
            ];

            return view('public.client-schedule', compact(
                'client',
                'loans',
                'memberships',
                'fixedDeposits',
                'installments',
                'byMember',
                'seatLetterMap',
                'summary'
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Public client schedule error: ' . $e->getMessage(), [
                'token' => $token,
                'trace' => $e->getTraceAsString(),
            ]);
            if (config('app.debug')) {
                throw $e;
            }
            abort(404, 'Invalid repayment schedule link.');
        }
    }
}
