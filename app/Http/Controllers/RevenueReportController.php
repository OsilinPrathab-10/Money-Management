<?php

namespace App\Http\Controllers;

use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\Appearance;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Helpers\AppearanceHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class RevenueReportController extends Controller
{
    /**
     * Index View for Revenue Report
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $loanMode = $request->input('loan_mode', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Base query with eager-loaded relations
        $query = LoanAccount::with(['client.user', 'loanApplication.applicationDetail', 'emis']);

        // Filter by Date Range (disbursed_at)
        if ($fromDate) {
            $query->whereDate('disbursed_at', '>=', Carbon::parse($fromDate));
        }
        if ($toDate) {
            $query->whereDate('disbursed_at', '<=', Carbon::parse($toDate));
        }

        // Filter by Loan Mode (Type)
        if ($loanMode !== 'all') {
            $query->where('loan_mode', $loanMode);
        }

        // Filter by Search (Customer Name, Loan Code, Account Number)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_code', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('client_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        // Calculate aggregates across the ENTIRE filtered set
        $allFilteredLoans = (clone $query)->get();

        $totalProcessingFees = 0;
        $totalDocumentCharges = 0;
        $totalOtherCharges = 0;
        $totalInterestCollected = 0;
        $totalForeclosureRevenue = 0;
        $totalPenaltyAmount = 0;

        foreach ($allFilteredLoans as $loan) {
            $details = $loan->loanApplication->applicationDetail->details ?? [];
            $processingFee = (float)($details['applied_processing_fee'] ?? 0);
            $documentCharges = (float)($details['applied_document_charges'] ?? 0);
            $otherCharges = (float)($details['applied_other_charges'] ?? 0);

            $interestCollected = $loan->emis->sum(function ($emi) use ($loan) {
                if ($loan->loan_mode === 'interest_only') {
                    return max(0.00, (float)$emi->paid_amount - (float)$emi->principal_amount);
                } else {
                    return min((float)$emi->paid_amount, (float)$emi->interest_amount);
                }
            });

            // Foreclosure revenue: charges portion from foreclosed loans
            $foreclosureRevenue = 0;
            if ($loan->is_foreclosed && $loan->foreclosure_amount > 0) {
                $chargesPercentage = $loan->foreclosure_charges_percentage ?? $loan->getForeclosureChargesPercentage();
                $multiplier = 1 + ($chargesPercentage / 100);
                $outstanding = $loan->foreclosure_amount / $multiplier;
                $foreclosureRevenue = $loan->foreclosure_amount - $outstanding;
            }

            // Penalty amount: sum of all EMI penalties
            $penaltyAmount = $loan->emis->sum('penalty_amount');

            $totalProcessingFees += $processingFee;
            $totalDocumentCharges += $documentCharges;
            $totalOtherCharges += $otherCharges;
            $totalInterestCollected += $interestCollected;
            $totalForeclosureRevenue += $foreclosureRevenue;
            $totalPenaltyAmount += $penaltyAmount;
        }

        $overallTotalRevenue = $totalProcessingFees + $totalDocumentCharges + $totalOtherCharges + $totalInterestCollected + $totalForeclosureRevenue + $totalPenaltyAmount;

        // Paginate for UI list
        $loans = $query->orderBy('disbursed_at', 'desc')->paginate(15)->withQueryString();

        // Inject computed fields into the paginated items
        $loans->getCollection()->transform(function ($loan) {
            $details = $loan->loanApplication->applicationDetail->details ?? [];
            $loan->processing_fee = (float)($details['applied_processing_fee'] ?? 0);
            $loan->document_charges = (float)($details['applied_document_charges'] ?? 0);
            $loan->other_charges = (float)($details['applied_other_charges'] ?? 0);
            
            $loan->interest_collected = $loan->emis->sum(function ($emi) use ($loan) {
                if ($loan->loan_mode === 'interest_only') {
                    return max(0.00, (float)$emi->paid_amount - (float)$emi->principal_amount);
                } else {
                    return min((float)$emi->paid_amount, (float)$emi->interest_amount);
                }
            });

            // Foreclosure revenue: charges portion from foreclosed loans
            $loan->foreclosure_revenue = 0;
            if ($loan->is_foreclosed && $loan->foreclosure_amount > 0) {
                $chargesPercentage = $loan->foreclosure_charges_percentage ?? $loan->getForeclosureChargesPercentage();
                $multiplier = 1 + ($chargesPercentage / 100);
                $outstanding = $loan->foreclosure_amount / $multiplier;
                $loan->foreclosure_revenue = $loan->foreclosure_amount - $outstanding;
            }

            // Penalty amount: sum of all EMI penalties
            $loan->penalty_collected = $loan->emis->sum('penalty_amount');

            $loan->total_revenue = $loan->processing_fee + $loan->document_charges + $loan->other_charges + $loan->interest_collected + $loan->foreclosure_revenue + $loan->penalty_collected;
            return $loan;
        });

        // If AJAX request (like standard reports table reload), return table partial
        if ($request->ajax()) {
            return view('admin.revenue.table', compact('loans'))->render();
        }

        return view('admin.revenue.index', compact(
            'loans',
            'search',
            'loanMode',
            'fromDate',
            'toDate',
            'totalProcessingFees',
            'totalDocumentCharges',
            'totalOtherCharges',
            'totalInterestCollected',
            'totalForeclosureRevenue',
            'totalPenaltyAmount',
            'overallTotalRevenue'
        ));
    }

    /**
     * Export Revenue Report
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $search = $request->input('search');
        $loanMode = $request->input('loan_mode', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $query = LoanAccount::with(['client.user', 'loanApplication.applicationDetail', 'emis']);

        if ($fromDate) {
            $query->whereDate('disbursed_at', '>=', Carbon::parse($fromDate));
        }
        if ($toDate) {
            $query->whereDate('disbursed_at', '<=', Carbon::parse($toDate));
        }
        if ($loanMode !== 'all') {
            $query->where('loan_mode', $loanMode);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_code', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($cq) use ($search) {
                      $cq->where('client_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        $loans = $query->orderBy('disbursed_at', 'desc')->get();

        $data = $loans->map(function ($loan, $index) {
            $details = $loan->loanApplication->applicationDetail->details ?? [];
            $processingFee = (float)($details['applied_processing_fee'] ?? 0);
            $documentCharges = (float)($details['applied_document_charges'] ?? 0);
            $otherCharges = (float)($details['applied_other_charges'] ?? 0);

            $interestCollected = $loan->emis->sum(function ($emi) use ($loan) {
                if ($loan->loan_mode === 'interest_only') {
                    return max(0.00, (float)$emi->paid_amount - (float)$emi->principal_amount);
                } else {
                    return min((float)$emi->paid_amount, (float)$emi->interest_amount);
                }
            });

            // Foreclosure revenue
            $foreclosureRevenue = 0;
            if ($loan->is_foreclosed && $loan->foreclosure_amount > 0) {
                $chargesPercentage = $loan->foreclosure_charges_percentage ?? $loan->getForeclosureChargesPercentage();
                $multiplier = 1 + ($chargesPercentage / 100);
                $outstanding = $loan->foreclosure_amount / $multiplier;
                $foreclosureRevenue = $loan->foreclosure_amount - $outstanding;
            }

            // Penalty amount
            $penaltyAmount = $loan->emis->sum('penalty_amount');

            $totalRevenue = $processingFee + $documentCharges + $otherCharges + $interestCollected + $foreclosureRevenue + $penaltyAmount;

            return [
                'S.No' => $index + 1,
                'Customer Name' => $loan->client->user->name ?? $loan->client->client_name ?? 'N/A',
                'Account Number' => $loan->account_number ?? $loan->id,
                'Loan Code' => $loan->loan_code,
                'Loan Type' => $loan->loan_mode === 'interest_only' ? 'Open Loan' : 'EMI',
                'EMI / Cycle Amount (₹)' => number_format($loan->emi_amount, 2),
                'Processing Fee (₹)' => number_format($processingFee, 2),
                'Document Charges (₹)' => number_format($documentCharges, 2),
                'Other Charges (₹)' => number_format($otherCharges, 2),
                'Interest Collected (₹)' => number_format($interestCollected, 2),
                'Foreclose Revenue (₹)' => number_format($foreclosureRevenue, 2),
                'Penalty Amount (₹)' => number_format($penaltyAmount, 2),
                'Total Revenue (₹)' => number_format($totalRevenue, 2),
            ];
        });

        return $this->exportData($data, 'revenue_report', $format);
    }

    /**
     * Export Helper Methods
     */
    private function exportData($data, $filename, $format = 'csv')
    {
        if ($format === 'csv') {
            return $this->exportCSV($data, $filename);
        } elseif ($format === 'excel') {
            return $this->exportExcel($data, $filename);
        } elseif ($format === 'pdf') {
            return $this->exportPDF($data, $filename);
        }

        return response()->json(['error' => 'Invalid format'], 400);
    }

    private function exportCSV($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}_" . date('Y-m-d') . ".csv\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
            }
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcel($data, $filename)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}_" . date('Y-m-d') . ".xls\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
            }
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportPDF($data, $filename)
    {
        try {
            $reportTitle = 'Revenue Report';
            $tableHtml = $this->generateReportTable($data);
            $logoData = $this->resolveLogoData();
            
            $pdf = Pdf::loadView('pdf.dynamic_document', [
                'header' => '',
                'footer' => '',
                'body' => $tableHtml,
                'logo' => ($logoData['is_base64'] ?? false) ? $logoData['logo'] : null,
                'is_base64' => $logoData['is_base64'] ?? false,
                'loan' => (object)['application_number' => 'N/A'],
                'client' => (object)[],
                'title' => $reportTitle,
                'company' => [
                    'name' => AppearanceHelper::get('title', 'Loan App'),
                    'subtitle' => AppearanceHelper::get('subtitle', '')
                ],
                'companyName' => AppearanceHelper::get('title', 'Loan App'),
                'applicationNumber' => 'N/A',
                'clientName' => 'System',
                'consentTimestamp' => now()->format('d-m-Y H:i:s'),
                'registeredMobile' => 'N/A',
                'clientIp' => request()->ip()
            ])->setPaper('A4', 'portrait')
              ->setOptions([
                  'isHtml5ParserEnabled' => true,
                  'isRemoteEnabled' => true,
                  'defaultFont' => 'sans-serif',
                  'tempDir' => storage_path('app/public'),
                  'chroot'  => [
                      base_path(),
                      public_path(),
                      storage_path('app/public')
                  ],
              ]);

            $pdf->setPaper('A4', 'portrait');
            $fileName = "{$filename}_" . date('Y-m-d') . ".pdf";
            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('PDF Export Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            $errorMsg = 'Failed to generate PDF. Please ensure the PDF engine is correctly configured.';
            if (app()->environment('local') || config('app.debug')) {
                $errorMsg .= ' Error: ' . $e->getMessage();
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMsg
            ], 500);
        }
    }

    private function generateReportTable($data)
    {
        if ($data->isEmpty()) {
            return '<p class="text-center text-muted">No data available for this report.</p>';
        }

        $headers = array_keys($data->first());
        
        $html = '<div>';
        $html .= '<table class="table table-striped table-bordered" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        
        // Table headers
        $html .= '<thead style="background-color: #f8f9fa;">';
        $html .= '<tr>';
        foreach ($headers as $header) {
            $align = 'left';
            if (in_array($header, ['S.No', 'Sl. No', 'Sl No', 'ID'], true)) $align = 'center';
            if (strpos($header, 'Amount') !== false || strpos($header, '₹') !== false || $header === 'Principal' || $header === 'Interest') $align = 'right';
            
            $html .= '<th style="padding: 10px 5px; border: 1px solid #dee2e6; font-weight: 600; text-align: ' . $align . '; font-size: 11px;">' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Table body
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $key => $cell) {
                $align = 'left';
                if (in_array($key, ['S.No', 'Sl. No', 'Sl No', 'ID'], true)) $align = 'center';
                if (strpos($key, 'Amount') !== false || strpos($key, '₹') !== false || $key === 'Principal' || $key === 'Interest') $align = 'right';

                $html .= '<td style="padding: 8px 5px; border: 1px solid #dee2e6; font-size: 10px; text-align: ' . $align . ';">' . htmlspecialchars($cell ?? 'N/A') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        $html .= '</div>';
        
        // Add summary info
        $html .= '<div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">';
        $html .= '<p style="margin: 0; font-size: 14px;"><strong>Total Records:</strong> ' . $data->count() . '</p>';
        $html .= '<p style="margin: 5px 0 0 0; font-size: 12px; color: #6c757d;">Generated on: ' . now()->format('d-m-Y H:i:s') . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    private function resolveLogoData(): array
    {
        $appearance = Appearance::where('type', 'web')->first();
        if (!$appearance || !$appearance->logo) {
            return ['logo' => null, 'is_base64' => false];
        }

        $logoPath = $appearance->logo;
        $candidatePaths = [
            storage_path('app/public/' . $logoPath),
            public_path('storage/' . $logoPath),
            public_path($logoPath)
        ];

        foreach ($candidatePaths as $path) {
            if ($path && file_exists($path) && is_file($path)) {
                try {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

                    return [
                        'logo' => $base64,
                        'is_base64' => true
                    ];
                } catch (\Exception $e) {
                    Log::warning('Failed to base64 encode logo: ' . $e->getMessage());
                }
            }
        }

        return [
            'logo' => asset('storage/' . $logoPath),
            'is_base64' => false
        ];
    }
}
