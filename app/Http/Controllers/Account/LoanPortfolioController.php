<?php

namespace App\Http\Controllers\Account;

use App\Models\Emi;
use App\Models\LoanAccount;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class LoanPortfolioController extends Controller
{
    /**
     * List loan accounts from the core loan app (`loan_accounts` table).
     */
    public function loanAccounts(Request $request)
    {
        // Same access as accounting dashboard (Admin bypasses via AccountModuleServiceProvider).
        abort_unless(
            Auth::user()->can('manage-account-dashboard')
                || Auth::user()->can('view-account-loan-accounts'),
            403
        );

        $query = LoanAccount::query()
            ->with('client')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search')) {
            $s = '%' . (string) $request->input('search') . '%';
            $query->where(function ($q) use ($s) {
                $q->where('account_number', 'like', $s)
                    ->orWhere('loan_code', 'like', $s)
                    ->orWhere('application_number', 'like', $s)
                    ->orWhereHas('client', function ($cq) use ($s) {
                        $cq->where('client_name', 'like', $s)
                            ->orWhere('client_phone', 'like', $s);
                    });
            });
        }

        $loanAccounts = $query
            ->paginate((int) $request->get('per_page', 20))
            ->withQueryString();

        return view('admin.account.loan-portfolio.loan-accounts', [
            'loanAccounts' => $loanAccounts,
        ]);
    }

    /**
     * List EMIs from the core loan app (`emis` table).
     */
    public function emis(Request $request)
    {
        abort_unless(
            Auth::user()->can('manage-account-dashboard')
                || Auth::user()->can('view-account-emis'),
            403
        );

        $query = Emi::query()
            ->with(['loanAccount.client'])
            ->orderByDesc('due_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('loan_account_id')) {
            $query->where('loan_account_id', (int) $request->get('loan_account_id'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $s = '%' . $term . '%';
            $query->where(function ($q) use ($s, $term) {
                if ($term !== '' && ctype_digit($term)) {
                    $q->where('instalment_number', (int) $term);
                } else {
                    $q->where('instalment_number', 'like', $s);
                }
                $q->orWhere('payment_reference', 'like', $s)
                    ->orWhereHas('loanAccount', function ($lq) use ($s) {
                        $lq->where('account_number', 'like', $s)
                            ->orWhere('loan_code', 'like', $s);
                    });
            });
        }

        $emis = $query
            ->paginate((int) $request->get('per_page', 20))
            ->withQueryString();

        return view('admin.account.loan-portfolio.emis', [
            'emis' => $emis,
        ]);
    }

    public function loanAccountsExport(Request $request, AccountExportService $exportService)
    {
        abort_unless(
            Auth::user()->can('manage-account-dashboard')
                || Auth::user()->can('view-account-loan-accounts'),
            403
        );

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'status' => 'nullable|string|in:active,closed,defaulted,foreclosed',
            'search' => 'nullable|string|max:255',
        ]);

        $query = LoanAccount::query()
            ->with('client')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $s = '%' . $term . '%';
            $query->where(function ($q) use ($s, $term) {
                $q->where('account_number', 'like', $s)
                    ->orWhere('loan_code', 'like', $s)
                    ->orWhere('application_number', 'like', $s)
                    ->orWhereHas('client', function ($cq) use ($s) {
                        $cq->where('client_name', 'like', $s)
                            ->orWhere('client_phone', 'like', $s);
                    });
            });
        }

        $loanAccounts = $query->get();

        $totalLoan = (float) ($loanAccounts->sum('loan_amount') ?? 0);
        $totalOutstanding = (float) ($loanAccounts->sum('outstanding_amount') ?? 0);
        $totalEmi = (float) ($loanAccounts->sum('emi_amount') ?? 0);

        $rows = $loanAccounts->map(function ($la) {
            return [
                'account_number' => $la->account_number,
                'client' => $la->client?->client_name ?? '—',
                'loan_code' => $la->loan_code ?? '—',
                'loan_amount' => '₹' . number_format((float) ($la->loan_amount ?? 0), 2),
                'outstanding' => '₹' . number_format((float) ($la->outstanding_amount ?? 0), 2),
                'emi' => '₹' . number_format((float) ($la->emi_amount ?? 0), 2),
                'status' => $la->status ?? '—',
                'disbursed_at' => $la->disbursed_at ? $la->disbursed_at->format('Y-m-d') : '—',
            ];
        })->values()->all();

        $rows[] = [
            'account_number' => __('TOTAL'),
            'client' => '',
            'loan_code' => '',
            'loan_amount' => '₹' . number_format($totalLoan, 2),
            'outstanding' => '₹' . number_format($totalOutstanding, 2),
            'emi' => '₹' . number_format($totalEmi, 2),
            'status' => '',
            'disbursed_at' => '',
        ];

        $columns = [
            ['key' => 'account_number', 'label' => __('Account #')],
            ['key' => 'client', 'label' => __('Client')],
            ['key' => 'loan_code', 'label' => __('Loan code')],
            ['key' => 'loan_amount', 'label' => __('Loan amount'), 'class' => 'text-end'],
            ['key' => 'outstanding', 'label' => __('Outstanding'), 'class' => 'text-end'],
            ['key' => 'emi', 'label' => __('EMI'), 'class' => 'text-end'],
            ['key' => 'status', 'label' => __('Status')],
            ['key' => 'disbursed_at', 'label' => __('Disbursed')],
        ];

        $subtitleParts = [];
        if (!empty($validated['status'])) {
            $subtitleParts[] = 'Status: ' . $validated['status'];
        }
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Loan accounts'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'loan-accounts-export'
        );
    }

    public function emisExport(Request $request, AccountExportService $exportService)
    {
        abort_unless(
            Auth::user()->can('manage-account-dashboard')
                || Auth::user()->can('view-account-emis'),
            403
        );

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'status' => 'nullable|string|in:pending,paid,partial,overdue,waived',
            'loan_account_id' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
        ]);

        $query = Emi::query()
            ->with(['loanAccount.client'])
            ->orderByDesc('due_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('loan_account_id')) {
            $query->where('loan_account_id', (int) $request->input('loan_account_id'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $s = '%' . $term . '%';
            $query->where(function ($q) use ($s, $term) {
                if ($term !== '' && ctype_digit($term)) {
                    $q->where('instalment_number', (int) $term);
                } else {
                    $q->where('instalment_number', 'like', $s);
                }

                $q->orWhere('payment_reference', 'like', $s)
                    ->orWhereHas('loanAccount', function ($lq) use ($s) {
                        $lq->where('account_number', 'like', $s)
                            ->orWhere('loan_code', 'like', $s);
                    });
            });
        }

        $emis = $query->get();

        $totalDue = (float) ($emis->sum('total_amount') ?? 0);
        $totalPaid = (float) ($emis->sum('paid_amount') ?? 0);
        $totalPending = (float) ($emis->sum('pending_amount') ?? 0);

        $rows = $emis->map(function ($emi) {
            return [
                'id' => $emi->id,
                'loan_account' => $emi->loanAccount?->account_number ?? '—',
                'client' => $emi->loanAccount?->client?->client_name ?? '—',
                'instalment' => $emi->instalment_number,
                'total_due' => '₹' . number_format((float) ($emi->total_amount ?? 0), 2),
                'paid' => '₹' . number_format((float) ($emi->paid_amount ?? 0), 2),
                'pending' => '₹' . number_format((float) ($emi->pending_amount ?? 0), 2),
                'due_date' => $emi->due_date ? $emi->due_date->format('Y-m-d') : '—',
                'status' => $emi->status ?? '—',
                'risk' => $emi->risk_level_label ?? '—',
            ];
        })->values()->all();

        $rows[] = [
            'id' => __('TOTAL'),
            'loan_account' => '',
            'client' => '',
            'instalment' => '',
            'total_due' => '₹' . number_format($totalDue, 2),
            'paid' => '₹' . number_format($totalPaid, 2),
            'pending' => '₹' . number_format($totalPending, 2),
            'due_date' => '',
            'status' => '',
            'risk' => '',
        ];

        $columns = [
            ['key' => 'id', 'label' => __('#')],
            ['key' => 'loan_account', 'label' => __('Loan account')],
            ['key' => 'client', 'label' => __('Client')],
            ['key' => 'instalment', 'label' => __('Instalment')],
            ['key' => 'total_due', 'label' => __('Total due'), 'class' => 'text-end'],
            ['key' => 'paid', 'label' => __('Paid'), 'class' => 'text-end'],
            ['key' => 'pending', 'label' => __('Pending'), 'class' => 'text-end'],
            ['key' => 'due_date', 'label' => __('Due date')],
            ['key' => 'status', 'label' => __('Status')],
            ['key' => 'risk', 'label' => __('Risk')],
        ];

        $subtitleParts = [];
        if (!empty($validated['status'])) {
            $subtitleParts[] = 'Status: ' . $validated['status'];
        }
        if (!empty($validated['loan_account_id'])) {
            $subtitleParts[] = 'Loan account ID: ' . $validated['loan_account_id'];
        }
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('EMIs'),
                'subtitle' => implode(' | ', $subtitleParts) ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'emis-export'
        );
    }
}

