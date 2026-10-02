<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Emi;
use App\Models\LoanProduct;
use App\Models\Bank;
use App\Http\Resources\DashboardResource;
use App\Http\Resources\LoanProductResource;
use App\Http\Resources\SlideResource;
use App\Models\Slide;

class DashboardControllerApi extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $client = $user->client
            ?? Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Client record not found for this user.'
            ], 404);
        }

        // 1. Loans Array & Totals
        $loanAccounts = \App\Models\LoanAccount::with(['product', 'loanApplication.product'])
            ->where('client_id', $client->id)
            ->latest()
            ->get();

        $formattedLoans = $loanAccounts->map(function ($loan) {
            return [
                'id' => $loan->id,
                'account_number' => $loan->account_number,
                'application_number' => $loan->application_number,
                'loan_code' => $loan->loan_code,
                'loan_name' => optional($loan->product ?? optional($loan->loanApplication)->product)->loan_name ?? 'Loan Account',
                'loan_amount' => (float) $loan->loan_amount,
                'disbursed_amount' => (float) $loan->disbursed_amount,
                'interest_rate' => (float) $loan->interest_rate,
                'tenure' => (int) $loan->tenure,
                'emi_amount' => (float) $loan->emi_amount,
                'total_payable' => (float) $loan->total_payable,
                'paid_amount' => (float) $loan->paid_amount,
                'outstanding_amount' => (float) $loan->outstanding_amount,
                'penalty' => (float) $loan->penalty,
                'status' => $loan->status,
                'disbursed_at' => optional($loan->disbursed_at)?->format('Y-m-d'),
                'closed_at' => optional($loan->closed_at)?->format('Y-m-d'),
            ];
        })->values()->all();

        // 2. Chits Array & Totals
        $chitQuery = \App\Models\GroupMember::with(['group.scheme', 'installments']);
        if (method_exists(\App\Models\GroupMember::class, 'scopeInvolvingClient')) {
            $chitQuery->involvingClient($client->id);
        } else {
            $chitQuery->where('client_id', $client->id);
        }
        $chitMemberships = $chitQuery->latest()->get();

        $formattedChits = $chitMemberships->map(function ($member) use ($client) {
            $group = $member->group;
            return [
                'id' => $member->id,
                'member_number' => $member->member_number ?? $member->ticket_number,
                'ticket_number' => $member->ticket_number ?? $member->member_number,
                'group_id' => $member->group_id,
                'group_name' => optional($group)->group_name ?? optional(optional($group)->scheme)->name ?? 'Chit Group',
                'group_code' => optional($group)->group_code,
                'scheme_name' => optional(optional($group)->scheme)->name,
                'chit_amount' => (float) (optional($group)->chit_value ?? optional($group)->total_amount ?? optional(optional($group)->scheme)->chit_value ?? 0),
                'monthly_installment' => (float) $member->apiInstallmentAmountForClient((int) $client->id, $group),
                'total_months' => (int) (optional($group)->total_months ?? 0),
                'paid_amount' => (float) ($member->installments ? $member->installments->sum('paid_amount') : ($member->total_paid_amount ?? 0)),
                'pending_amount' => (float) ($member->installments ? $member->installments->whereNotIn('status', ['paid', 'waived'])->sum('balance') : ($member->pending_amount ?? 0)),
                'status' => $member->persistCustomerFacingStatus(),
                'status_label' => $member->customerFacingStatusLabel(),
                'joined_at' => optional($member->joined_at ?? $member->created_at)?->format('Y-m-d'),
            ];
        })->values()->all();

        // 3. Fixed Deposits Array & Totals
        $fdAccounts = \App\Models\FixedDeposit::with('scheme')
            ->where('client_id', $client->id)
            ->latest()
            ->get();

        $formattedFds = $fdAccounts->map(function ($fd) {
            return [
                'id' => $fd->id,
                'fd_number' => $fd->fd_number,
                'scheme_id' => $fd->scheme_id,
                'scheme_name' => optional($fd->scheme)->name,
                'deposit_amount' => (float) $fd->deposit_amount,
                'deposit_date' => optional($fd->deposit_date)?->format('Y-m-d'),
                'start_date' => optional($fd->start_date)?->format('Y-m-d'),
                'maturity_date' => optional($fd->maturity_date)?->format('Y-m-d'),
                'tenure' => (int) $fd->tenure,
                'tenure_type' => $fd->tenure_type,
                'interest_rate' => (float) $fd->interest_rate,
                'interest_type' => $fd->interest_type,
                'interest_frequency' => $fd->interest_frequency,
                'interest_amount' => (float) $fd->interest_amount,
                'maturity_amount' => (float) $fd->maturity_amount,
                'interest_paid_to_wallet' => (float) $fd->interest_paid_to_wallet,
                'last_interest_payout_date' => optional($fd->last_interest_payout_date)?->format('Y-m-d'),
                'payout_option' => $fd->payout_option,
                'auto_renewal' => (bool) $fd->auto_renewal,
                'renewal_type' => $fd->renewal_type,
                'nominee_name' => $fd->nominee_name,
                'nominee_relation' => $fd->nominee_relation,
                'status' => $fd->status,
                'total_days' => $fd->total_days,
                'completed_days' => $fd->completed_days,
                'remaining_days' => $fd->remaining_days,
                'tenure_progress_percentage' => $fd->tenure_progress_percentage,
                'tenure_progress' => $fd->tenure_progress,
                'certificate_url' => route('fd.deposits.certificate', $fd->id),
            ];
        })->values()->all();

        // 4. Summaries & Aggregates
        $loansCount = count($formattedLoans);
        $activeLoansCount = $loanAccounts->where('status', 'active')->count();
        $totalLoanAmount = (float) $loanAccounts->sum('loan_amount');
        $totalLoanOutstanding = (float) $loanAccounts->sum('outstanding_amount');
        $totalLoanPaid = (float) $loanAccounts->sum('paid_amount');

        $chitsCount = count($formattedChits);
        $activeChitsCount = $chitMemberships->filter(function ($m) {
            return in_array($m->customerFacingStatus(), ['active', 'approved'], true);
        })->count();
        $totalChitAmount = (float) $chitMemberships->sum(fn ($m) => optional($m->group)->chit_value ?? optional($m->group)->total_amount ?? optional(optional($m->group)->scheme)->chit_value ?? 0);
        $totalChitPaid = (float) $chitMemberships->sum(fn ($m) => $m->installments ? $m->installments->sum('paid_amount') : ($m->total_paid_amount ?? 0));

        $fdsCount = count($formattedFds);
        $activeFdsCount = $fdAccounts->where('status', 'active')->count();
        $maturedFdsCount = $fdAccounts->where('status', 'matured')->count();
        $totalFdDepositAmount = (float) $fdAccounts->sum('deposit_amount');
        $totalFdMaturityAmount = (float) $fdAccounts->sum('maturity_amount');

        $overdueCount = Emi::whereHas('loanAccount', function ($q) use ($client) {
                $q->where('client_id', $client->id);
            })
            ->where('status', 'overdue')
            ->count();

        $hasAccounts = ($loansCount > 0 || $chitsCount > 0 || $fdsCount > 0);
        $publicLink = $hasAccounts
            ? route('public.view-client-schedule', \App\Support\HashId::encode($client->id))
            : null;

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Dashboard data fetched successfully',
            'data' => [
                'client' => new DashboardResource($client, $overdueCount, $publicLink),
                'public_link' => $publicLink,
                'summary' => [
                    'total_active_accounts' => $activeLoansCount + $activeChitsCount + $activeFdsCount,
                    'total_loans_count' => $loansCount,
                    'active_loans_count' => $activeLoansCount,
                    'total_loan_amount' => $totalLoanAmount,
                    'total_loan_outstanding' => $totalLoanOutstanding,
                    'total_loan_paid' => $totalLoanPaid,

                    'total_chits_count' => $chitsCount,
                    'active_chits_count' => $activeChitsCount,
                    'total_chit_amount' => $totalChitAmount,
                    'total_chit_paid' => $totalChitPaid,

                    'total_fds_count' => $fdsCount,
                    'active_fds_count' => $activeFdsCount,
                    'matured_fds_count' => $maturedFdsCount,
                    'total_fd_deposit_amount' => $totalFdDepositAmount,
                    'total_fd_maturity_amount' => $totalFdMaturityAmount,
                ],
                'loan_summary' => [
                    'count' => $loansCount,
                    'active_count' => $activeLoansCount,
                    'total_loan_amount' => $totalLoanAmount,
                    'total_outstanding_amount' => $totalLoanOutstanding,
                    'total_paid_amount' => $totalLoanPaid,
                ],
                'chit_summary' => [
                    'count' => $chitsCount,
                    'active_count' => $activeChitsCount,
                    'total_chit_amount' => $totalChitAmount,
                    'total_paid_amount' => $totalChitPaid,
                ],
                'fd_summary' => [
                    'count' => $fdsCount,
                    'active_count' => $activeFdsCount,
                    'matured_count' => $maturedFdsCount,
                    'total_deposit_amount' => $totalFdDepositAmount,
                    'total_maturity_amount' => $totalFdMaturityAmount,
                ],
                'loans' => $formattedLoans,
                'loan_accounts' => $formattedLoans,
                'chits' => $formattedChits,
                'chit_accounts' => $formattedChits,
                'fixed_deposits' => $formattedFds,
                'fds' => $formattedFds,
                'fd_accounts' => $formattedFds,
                'banners' => SlideResource::collection(
                    Slide::where('type', 'banner')->get()
                )
            ],
        ], 200);
    }

    public function getAllBankDetails(Request $request)
    {
        $searchTerm = $request->query('search');
        $ifscCode = $request->query('ifsc');

        $query = Bank::query();

        if ($searchTerm) {
            $needle = mb_strtolower($searchTerm);
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(bank_name) LIKE ?', ['%' . $needle . '%'])
                  ->orWhereRaw('LOWER(CAST(bank_id AS CHAR)) LIKE ?', ['%' . $needle . '%']);
            });
        }

        if ($ifscCode) {
            $query->whereRaw('LOWER(ifsc_code) LIKE ?', ['%' . mb_strtolower($ifscCode) . '%']);
        }

        $banks = $query->get()->map(function ($bank) {
            return [
                'BankID' => $bank->bank_id,
                'BankName' => $bank->bank_name,
                'IFSCCODE' => $bank->ifsc_code,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Bank details fetched successfully.',
            'total' => count($banks),
            'data' => $banks
        ]);
    }
}
