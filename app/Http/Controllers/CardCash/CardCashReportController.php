<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashSwipeTransaction;
use App\Models\CardCash\CreditCardCustomer;
use App\Models\CardCash\CreditCardWallet;
use App\Models\CardCash\CreditCardWalletTransaction;
use App\Models\User;
use App\Support\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CardCashReportController extends Controller
{
    public function index(Request $request): View
    {
        $leads = $this->buildReportQuery($request)->paginate((int) $request->input('per_page', 25));

        // Aggregate summary metrics on filtered data
        $summaryQuery = $this->buildReportQuery($request);
        $totalRequested = (clone $summaryQuery)->sum('requested_amount');
        $totalCompleted = (clone $summaryQuery)->where('status', 'completed')->sum('requested_amount');
        
        $totalReturns = (clone $summaryQuery)->whereHas('returnSettlement', function ($rq) {
            $rq->where('status', 'success');
        })->with('returnSettlement')->get()->sum(function ($l) {
            return optional($l->returnSettlement)->return_amount ?? 0;
        });

        $totalCharges = (clone $summaryQuery)->whereHas('returnSettlement', function ($rq) {
            $rq->where('status', 'success');
        })->with('returnSettlement')->get()->sum(function ($l) {
            return optional($l->returnSettlement)->charges ?? 0;
        });

        $reconciliation = $this->buildReconciliationMetrics($request, $summaryQuery, (float) $totalRequested, (float) $totalReturns, (float) $totalCharges);

        $customers = CreditCardCustomer::orderBy('customer_name')->get(['id', 'customer_name', 'customer_number']);
        $staffUsers = User::orderBy('name')->get();

        return view('admin.card-to-cash.reports.index', array_merge(compact(
            'leads',
            'totalRequested',
            'totalCompleted',
            'totalReturns',
            'totalCharges',
            'customers',
            'staffUsers'
        ), $reconciliation));
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $rows = $this->prepareExportRows($request);
        $filename = 'card_to_cash_report_' . date('Y_m_d_His') . '.xlsx';
        $title = 'Card to Cash Financial & Operations Report';

        return ReportExporter::excel($rows, $filename, $title);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->prepareExportRows($request);
        $filename = 'card_to_cash_report_' . date('Y_m_d_His') . '.csv';

        return ReportExporter::csv($rows, $filename);
    }

    protected function buildReportQuery(Request $request)
    {
        $query = CardCashLead::with([
            'customer',
            'assignedStaff',
            'billPayment.paymentSource',
            'swipeTransaction.gateway',
            'swipeTransaction.wallet',
            'returnSettlement.wallet',
        ]);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('lead_number', 'LIKE', "%{$search}%")
                  ->orWhere('card_name', 'LIKE', "%{$search}%")
                  ->orWhere('csr_bank_name', 'LIKE', "%{$search}%")
                  ->orWhere('phone_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('customer_name', 'LIKE', "%{$search}%")
                         ->orWhere('customer_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('credit_card_customer_id', $customerId);
        }

        if ($type = $request->input('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($staffId = $request->input('assigned_user_id')) {
            $query->where('assigned_user_id', $staffId);
        }

        if ($returnMethod = $request->input('return_method')) {
            $query->whereHas('returnSettlement', function ($rq) use ($returnMethod) {
                $rq->where('return_method', $returnMethod);
            });
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('lead_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('lead_date', '<=', $toDate);
        }

        return $query->latest('id');
    }

    protected function prepareExportRows(Request $request): array
    {
        $leads = $this->buildReportQuery($request)->get();
        $rows = [];

        $totalRequested = 0.0;
        $totalReturns = 0.0;
        $totalReturnCharges = 0.0;

        foreach ($leads as $l) {
            $requested = (float) $l->requested_amount;
            $returnAmount = (float) (optional($l->returnSettlement)->return_amount ?? 0);
            $returnCharges = (float) (optional($l->returnSettlement)->charges ?? 0);
            $swipeCharges = (float) (optional($l->swipeTransaction)->charges ?? 0);
            $cardCharges = $swipeCharges + $returnCharges;
            $differenceAmount = $requested - $returnAmount;

            $totalRequested += $requested;
            $totalReturns += $returnAmount;
            $totalReturnCharges += $returnCharges;

            $rows[] = [
                'Lead Number' => $l->lead_number,
                'Date' => $l->lead_date ? $l->lead_date->format('Y-m-d H:i') : '',
                'Customer' => optional($l->customer)->customer_name ?? '',
                'Customer Number' => optional($l->customer)->customer_number ?? '',
                'Phone' => $l->phone_number,
                'Card Name' => $l->card_name,
                'CSR Bank' => $l->csr_bank_name,
                'Type' => $l->transaction_type === 'bill_payment' ? 'Bill Payment' : 'Swipe',
                'Requested Amount' => $requested,
                'Status' => $l->status_label,
                'Assigned Staff' => optional($l->assignedStaff)->name ?? 'Unassigned',
                'Payment Source / Gateway' => $l->transaction_type === 'bill_payment'
                    ? optional(optional($l->billPayment)->paymentSource)->source_name
                    : optional(optional($l->swipeTransaction)->gateway)->gateway_name,
                'Return Method' => optional($l->returnSettlement)->return_method ? strtoupper($l->returnSettlement->return_method) : 'N/A',
                'Return Amount' => $returnAmount,
                'Difference Amount' => $differenceAmount,
                'Card Charges' => $cardCharges,
                'Charges Earned' => $returnCharges,
            ];
        }

        $reconciliation = $this->buildReconciliationMetrics(
            $request,
            $this->buildReportQuery($request),
            $totalRequested,
            $totalReturns,
            $totalReturnCharges
        );

        $blank = [
            'Lead Number' => '',
            'Date' => '',
            'Customer' => '',
            'Customer Number' => '',
            'Phone' => '',
            'Card Name' => '',
            'CSR Bank' => '',
            'Type' => '',
            'Requested Amount' => '',
            'Status' => '',
            'Assigned Staff' => '',
            'Payment Source / Gateway' => '',
            'Return Method' => '',
            'Return Amount' => '',
            'Difference Amount' => '',
            'Card Charges' => '',
            'Charges Earned' => '',
        ];

        $summaryRows = [
            array_merge($blank, [
                'Lead Number' => 'WALLET TOTAL',
                'Requested Amount' => $reconciliation['walletTotal'],
            ]),
            array_merge($blank, [
                'Lead Number' => 'IN/OUT DIFFERENCE',
                'Requested Amount' => $reconciliation['inOutDifference'],
            ]),
            array_merge($blank, [
                'Lead Number' => 'TOTAL DIFFERENCE AMOUNT',
                'Difference Amount' => $reconciliation['totalDifferenceAmount'],
                'Requested Amount' => $totalRequested,
                'Return Amount' => $totalReturns,
            ]),
            array_merge($blank, [
                'Lead Number' => 'CARD CHARGES',
                'Card Charges' => $reconciliation['cardCharges'],
                'Charges Earned' => $totalReturnCharges,
            ]),
        ];

        return array_merge($summaryRows, $rows);
    }

    /**
     * Wallet and charge totals used for reconciliation on screen and export.
     *
     * @return array{walletTotal: float, inOutDifference: float, totalDifferenceAmount: float, cardCharges: float}
     */
    protected function buildReconciliationMetrics(
        Request $request,
        $summaryQuery,
        float $totalRequested,
        float $totalReturns,
        float $totalReturnCharges
    ): array {
        $walletTotal = (float) CreditCardWallet::query()->sum('current_balance');

        $txnQuery = CreditCardWalletTransaction::query();
        if ($fromDate = $request->input('from_date')) {
            $txnQuery->whereDate('transaction_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $txnQuery->whereDate('transaction_date', '<=', $toDate);
        }

        $walletIn = (float) (clone $txnQuery)->where('transaction_type', 'credit')->sum('amount');
        $walletOut = (float) (clone $txnQuery)->where('transaction_type', 'debit')->sum('amount');
        $inOutDifference = $walletIn - $walletOut;

        $leadIds = (clone $summaryQuery)->pluck('id');
        $swipeCharges = $leadIds->isEmpty()
            ? 0.0
            : (float) CardCashSwipeTransaction::query()->whereIn('lead_id', $leadIds)->sum('charges');

        return [
            'walletTotal' => $walletTotal,
            'walletIn' => $walletIn,
            'walletOut' => $walletOut,
            'inOutDifference' => $inOutDifference,
            'totalDifferenceAmount' => $totalRequested - $totalReturns,
            'cardCharges' => $swipeCharges + $totalReturnCharges,
        ];
    }
}
