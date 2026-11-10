<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\FixedDeposit;
use App\Models\FixedDepositApplication;
use App\Support\HashId;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class PublicClientScheduleController extends Controller
{
    /**
     * View public repayment schedule for a client (Loans).
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

                // 6. Fallback lookup by client ID / customer_id / user_id / phone
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

            $today = Carbon::now()->startOfDay();

            // 2. Fetch Fixed Deposits for this client
            $fixedDeposits = \App\Models\FixedDeposit::with('scheme')
                ->where('client_id', $client->id)
                ->orderBy('id', 'desc')
                ->get();

            // 3. Compute consolidated statistics for Loans + FDs
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

            $summary = [
                'has_loans' => $loans->count() > 0,
                'has_fds' => $fixedDeposits->count() > 0,
                'total_loans' => $loans->count(),
                'total_fds' => $fixedDeposits->count(),
                'total_payable_sum' => $loansPayable,
                'total_paid' => $loansPaid,
                'total_balance' => $loansBalance,
                'total_overdue' => $loansOverdueCount,
            ];

            return view('public.client-schedule', compact(
                'client',
                'loans',
                'fixedDeposits',
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
