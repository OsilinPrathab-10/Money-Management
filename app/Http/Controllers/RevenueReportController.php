<?php

namespace App\Http\Controllers;

use App\Services\RevenueReportService;
use App\Support\DateRangePreset;
use App\Support\ReportExporter;
use Illuminate\Http\Request;

class RevenueReportController extends Controller
{
    public function __construct(protected RevenueReportService $reportService)
    {
    }

    /**
     * Index view for tab-wise revenue report (Loan / Chit / Fixed Deposit).
     */
    public function index(Request $request)
    {
        $tab = $this->resolveTab($request->input('tab', 'loan'));
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filters = [
            'search' => $request->input('search'),
            'loan_mode' => $request->input('loan_mode', 'all'),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        $report = match ($tab) {
            'chit' => $this->reportService->chitReport($filters),
            'fd' => $this->reportService->fdReport($filters),
            default => $this->reportService->loanReport($filters),
        };

        $viewData = [
            'tab' => $tab,
            'items' => $report['items'],
            'totals' => $report['totals'],
            'overallTotalRevenue' => $report['overall_total'],
            'search' => $filters['search'],
            'loanMode' => $filters['loan_mode'],
            'fromDate' => $filters['from_date'],
            'toDate' => $filters['to_date'],
        ];

        if ($request->ajax()) {
            $partial = match ($tab) {
                'chit' => 'admin.revenue.chit-table',
                'fd' => 'admin.revenue.fd-table',
                default => 'admin.revenue.table',
            };

            return view($partial, $viewData)->render();
        }

        return view('admin.revenue.index', $viewData);
    }

    /**
     * Export revenue report for the active tab.
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $tab = $this->resolveTab($request->input('tab', 'loan'));
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filters = [
            'search' => $request->input('search'),
            'loan_mode' => $request->input('loan_mode', 'all'),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        $data = match ($tab) {
            'chit' => $this->reportService->chitExportRows($filters),
            'fd' => $this->reportService->fdExportRows($filters),
            default => $this->reportService->loanExportRows($filters),
        };

        $filename = match ($tab) {
            'chit' => 'chit_revenue_report',
            'fd' => 'fd_revenue_report',
            default => 'loan_revenue_report',
        };

        return $this->exportData($data, $filename, $format, $tab);
    }

    private function resolveTab(?string $tab): string
    {
        return in_array($tab, ['loan', 'chit', 'fd'], true) ? $tab : 'loan';
    }

    private function exportData($data, $filename, $format = 'csv', string $tab = 'loan')
    {
        $title = match ($tab) {
            'chit' => 'Chit Revenue Report',
            'fd' => 'Fixed Deposit Revenue Report',
            default => 'Loan Revenue Report',
        };

        return ReportExporter::download(
            $data,
            $format,
            $filename,
            $title,
            request()->except(['format', 'page'])
        );
    }
}

