<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\Location;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\DateRangePreset;
use App\Support\ReportExporter;

class ReportsAnalyticsController extends Controller
{
    /**
     * Clients Report
     */
    public function clients(Request $request)
    {
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filterStatus = $request->input('status', 'all');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');

        // 1. Base Query for Aggregates (applied with location and date filters)
        $baseAggregateQuery = Client::query();
        
        if ($location_id) {
            $baseAggregateQuery->where('location_id', $location_id);
        }
        if ($fromDate) {
            $baseAggregateQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }
        if ($toDate) {
            $baseAggregateQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        // Gather status counts based on filters
        $rawStatusCounts = (clone $baseAggregateQuery)
            ->selectRaw('LOWER(TRIM(status)) as status, count(*) as count')
            ->groupBy('status')
            ->get();

        $statusCounts = $rawStatusCounts->pluck('count', 'status')->map(fn($count) => (int) $count);
        $totalClients = $rawStatusCounts->sum('count');
        
        // Count active clients (includes both 'active' and 'verified' status)
        $activeClients = ($statusCounts['active'] ?? 0) + ($statusCounts['verified'] ?? 0);
        
        // Count inactive clients (includes both 'inactive' and 'unverified' status)
        $inactiveClients = ($statusCounts['inactive'] ?? 0) + ($statusCounts['unverified'] ?? 0);
        
        $pendingClients = $statusCounts['pending'] ?? 0;

        // Build formatted status list for charts
        $defaultStatuses = ['active', 'inactive', 'verified', 'unverified', 'pending', 'blacklist'];
        $formatStatus = fn(string $status) => Str::title(str_replace('_', ' ', $status));

        $clientsByStatus = collect($defaultStatuses)
            ->map(function ($status) use ($statusCounts, $formatStatus) {
                $count = $statusCounts[$status] ?? 0;

                return [
                    'status' => $status,
                    'label' => $formatStatus($status),
                    'count' => $count,
                ];
            })
            ->filter(fn($item) => $item['count'] > 0)
            ->values();

        // Include any additional statuses that may exist beyond defaults
        $rawStatusCounts->each(function ($item) use (&$clientsByStatus, $defaultStatuses, $formatStatus) {
            if (!in_array($item->status, $defaultStatuses, true)) {
                $clientsByStatus->push([
                    'status' => $item->status,
                    'label' => $formatStatus($item->status),
                    'count' => (int) $item->count,
                ]);
            }
        });

        // Filters for table
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');

        $clientsTableQuery = Client::with('user');

        if ($location_id) {
            $clientsTableQuery->where('location_id', $location_id);
        }

        if ($filterStatus !== 'all') {
            $clientsTableQuery->whereRaw('LOWER(TRIM(status)) = ?', [strtolower($filterStatus)]);
        }

        if ($fromDate) {
            $clientsTableQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $clientsTableQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        switch ($sortOption) {
            case 'oldest':
                $clientsTableQuery->orderBy('created_at', 'asc');
                break;
            case 'status_asc':
                $clientsTableQuery->orderBy('status')->orderBy('created_at', 'desc');
                break;
            case 'status_desc':
                $clientsTableQuery->orderBy('status', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $clientsTableQuery->orderBy('created_at', 'desc');
                break;
        }

        $latestClients = $clientsTableQuery->paginate(15)->withQueryString();

        $availableStatuses = $statusCounts->keys()->map(fn($status) => [
            'value' => $status,
            'label' => $formatStatus($status)
        ]);

        // Verification status - based on KYC details (respecting filters)
        $verifiedClients = (clone $baseAggregateQuery)->whereHas('kycDetail', function($query) {
            $query->where('status', 'verified');
        })->count();
        
        // Count clients with KYC but not verified (rejected or pending)
        $unverifiedClients = (clone $baseAggregateQuery)->whereHas('kycDetail', function($query) {
            $query->whereIn('status', ['rejected', 'pending', 'unverified']);
        })->count();
        
        // Also count clients without any KYC details as unverified
        $clientsWithoutKyc = (clone $baseAggregateQuery)->doesntHave('kycDetail')->count();
        $unverifiedClients += $clientsWithoutKyc;

        // Clients with active loans
        $clientsWithLoans = Client::whereHas('loanApplications', function($query) {
            $query->where('status', 'disbursed');
        })->count();

        $locations = Location::orderBy('name')->get();

        return view('admin.report-analytics.clients.clients', compact(
            'totalClients',
            'activeClients',
            'inactiveClients',
            'pendingClients',
            'clientsByStatus',
            'verifiedClients',
            'unverifiedClients',
            'clientsWithLoans',
            'latestClients',
            'availableStatuses',
            'filterStatus',
            'fromDate',
            'toDate',
            'sortOption',
            'locations'
        ));
    }

    /**
     * Export Clients Report
     */
    public function exportClients(Request $request)
    {
        $format = $request->get('format', 'csv');
        
        $clientsQuery = Client::with('user');

        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $status = $request->input('status');
        $sort = $request->input('sort');
        $location_id = $request->input('location_id');

        if ($location_id) {
            $clientsQuery->where('location_id', $location_id);
        }

        if ($status && $status !== 'all') {
            $clientsQuery->where('status', $status);
        }

        if ($fromDate) {
            $clientsQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $clientsQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        switch ($sort) {
            case 'oldest':
                $clientsQuery->orderBy('created_at', 'asc');
                break;
            case 'status_asc':
                $clientsQuery->orderBy('status')->orderBy('created_at', 'desc');
                break;
            case 'status_desc':
                $clientsQuery->orderBy('status', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $clientsQuery->orderBy('created_at', 'desc');
                break;
        }

        $clientsQuery = Client::with(['user', 'location']);
        $clients = $clientsQuery
            ->select('clients.*')
            ->get()
            ->map(function($client, $index) {
                $name = $client->client_name ?: ($client->user?->name ?? 'N/A');
                $email = $client->client_email ?: ($client->user?->email ?? 'N/A');
                $phone = $client->client_phone ?: ($client->alternate_phone ?? 'N/A');
                $location = $client->location?->name ?? 'N/A';

                return [
                    'S.No' => $index + 1,
                    'Client Name' => $name,
                    'Email' => $email,
                    'Phone' => $phone,
                    'Location / Branch' => $location,
                    'Aadhaar Number' => $client->aadhaar_number ?? 'N/A',
                    'City' => $client->city ?? 'N/A',
                    'District' => $client->district ?? 'N/A',
                    'Pincode' => $client->pincode ?? 'N/A',
                    'Status' => ucfirst($client->status ?? 'active'),
                    'Registered Date' => $client->created_at ? $client->created_at->format('Y-m-d') : 'N/A',
                ];
            });

        return $this->exportData($clients, 'clients_report', $format);
    }

    /**
     * Closed / fully repaid / overdue status used by the loan report.
     */
    private function loanReportStatus($loan): string
    {
        $status = strtolower(trim((string) ($loan->status ?? 'active')));
        if (in_array($status, ['closed', 'completed', 'foreclosed'], true)
            || $loan->closed_at !== null
            || $loan->is_foreclosed
            || (float) ($loan->outstanding_amount ?? 0) <= 0.05) {
            return 'closed';
        }

        $today = Carbon::now()->startOfDay();
        $hasOverdue = $loan->relationLoaded('emis')
            ? $loan->emis->contains(function ($emi) use ($today) {
                if (! in_array(strtolower((string) $emi->status), ['pending', 'overdue', 'partial'], true)) {
                    return false;
                }
                if ((float) ($emi->pending_amount ?? 0) <= 0) {
                    return false;
                }
                $due = $emi->due_date ? Carbon::parse($emi->due_date)->startOfDay() : null;

                return $due && $due->lt($today);
            })
            : $loan->emis()->where(function ($q) {
                $this->constrainOverdueEmis($q);
            })->exists();

        return $hasOverdue ? 'overdue' : ($status !== '' ? $status : 'active');
    }

    /**
     * Closed / fully repaid loans.
     */
    private function constrainClosedLoans($query): void
    {
        $query->where(function ($q) {
            $q->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('closed', 'completed', 'foreclosed')")
                ->orWhereNotNull('closed_at')
                ->orWhere('is_foreclosed', 1)
                ->orWhere('outstanding_amount', '<=', 0.05);
        });
    }

    /**
     * Loans that are still open (not closed or fully repaid).
     */
    private function constrainOpenLoans($query): void
    {
        $query->where(function ($q) {
            $q->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('closed', 'completed', 'foreclosed')")
                ->whereNull('closed_at')
                ->where(function ($foreclosed) {
                    $foreclosed->where('is_foreclosed', 0)->orWhereNull('is_foreclosed');
                })
                ->where('outstanding_amount', '>', 0.05);
        });
    }

    /**
     * Unpaid EMIs that are past their due date.
     */
    private function constrainOverdueEmis($query): void
    {
        $today = Carbon::now()->startOfDay()->toDateString();
        $query->whereDate('due_date', '<', $today)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->where('pending_amount', '>', 0);
    }

    /**
     * Apply common filters for Loans Report and Exports.
     */
    private function applyLoanFilterQuery($query, Request $request, bool $includeStatus = true)
    {
        $status = $request->input('status', 'all');
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $location_id = $request->input('location_id');
        $loan_product_id = $request->input('loan_product_id');

        if ($includeStatus && $status && $status !== 'all') {
            $statusLower = strtolower(trim($status));
            if ($statusLower === 'closed') {
                $this->constrainClosedLoans($query);
            } elseif ($statusLower === 'active') {
                $this->constrainOpenLoans($query);
                $query->whereDoesntHave('emis', fn ($eq) => $this->constrainOverdueEmis($eq));
            } elseif ($statusLower === 'overdue') {
                $this->constrainOpenLoans($query);
                $query->whereHas('emis', fn ($eq) => $this->constrainOverdueEmis($eq));
            } else {
                $query->whereRaw('LOWER(TRIM(status)) = ?', [$statusLower]);
            }
        }

        if ($location_id) {
            $query->whereHas('client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($loan_product_id) {
            $selectedProduct = is_numeric($loan_product_id)
                ? LoanProduct::find($loan_product_id)
                : LoanProduct::where('loan_code', $loan_product_id)->first();

            if ($selectedProduct) {
                $query->where(function($q) use ($selectedProduct, $loan_product_id) {
                    $q->where('loan_code', $selectedProduct->loan_code)
                      ->orWhere('loan_code', $loan_product_id);
                    if (Schema::hasColumn('loan_accounts', 'loan_product_id')) {
                        $q->orWhere('loan_product_id', $selectedProduct->id);
                    }
                });
            } else {
                $query->where(function($q) use ($loan_product_id) {
                    $q->where('loan_code', $loan_product_id);
                    if (Schema::hasColumn('loan_accounts', 'loan_product_id')) {
                        $q->orWhere('loan_product_id', $loan_product_id);
                    }
                });
            }
        }

        if ($fromDate) {
            $query->where(function($q) use ($fromDate) {
                $q->whereDate('created_at', '>=', $fromDate)
                  ->orWhereDate('disbursed_at', '>=', $fromDate);
            });
        }

        if ($toDate) {
            $query->where(function($q) use ($toDate) {
                $q->whereDate('created_at', '<=', $toDate)
                  ->orWhereDate('disbursed_at', '<=', $toDate);
            });
        }
    }

    /**
     * Loans Report
     */
    public function loans(Request $request)
    {
        // Filters for loans table and aggregates
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filterStatus = $request->input('status', 'all');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');
        $loan_product_id = $request->input('loan_product_id');

        // Loan products for dropdown filter
        $loanProducts = LoanProduct::orderBy('loan_name')->get();

        // Base query for aggregates (same location / product / period as the table)
        $baseLoanQuery = LoanAccount::query();
        $this->applyLoanFilterQuery($baseLoanQuery, $request, false);

        $totalLoans = (clone $baseLoanQuery)->count();

        $closedLoans = (clone $baseLoanQuery);
        $this->constrainClosedLoans($closedLoans);
        $closedLoans = $closedLoans->count();

        $overdueLoans = (clone $baseLoanQuery);
        $this->constrainOpenLoans($overdueLoans);
        $overdueLoans = $overdueLoans->whereHas('emis', fn ($eq) => $this->constrainOverdueEmis($eq))->count();

        $onTrackLoans = (clone $baseLoanQuery);
        $this->constrainOpenLoans($onTrackLoans);
        $onTrackLoans = $onTrackLoans->whereDoesntHave('emis', fn ($eq) => $this->constrainOverdueEmis($eq))->count();

        // Active in the chart is on-track only. Overdue stays a separate slice so
        // ApexCharts Total (sum of slices) equals unique loans, not Active+Overdue.
        $activeLoans = $onTrackLoans;
        $formatLoanStatus = fn (string $status) => Str::title(str_replace('_', ' ', $status));

        $loansByStatus = collect([
            ['status' => 'active', 'label' => 'Active', 'count' => $activeLoans],
            ['status' => 'closed', 'label' => 'Closed', 'count' => $closedLoans],
            ['status' => 'overdue', 'label' => 'Overdue', 'count' => $overdueLoans],
        ])->filter(fn ($item) => $item['count'] > 0)->values();

        $loanStatusCounts = collect([
            'active' => $activeLoans,
            'closed' => $closedLoans,
            'overdue' => $overdueLoans,
        ]);

        // Total loan amount disbursed
        $totalDisbursed = (clone $baseLoanQuery)->sum('loan_amount');
        $totalOutstanding = (clone $baseLoanQuery)->where(function($q) {
            $q->whereRaw("LOWER(TRIM(status)) IN ('active', 'disbursed', 'approved', 'process')")
              ->whereNull('closed_at')
              ->where('is_foreclosed', 0);
        })->sum('outstanding_amount');
        $totalPaid = (clone $baseLoanQuery)->sum('paid_amount');

        // Loans disbursed per month (last 12 months)
        $rawLoansPerMonth = (clone $baseLoanQuery)->select(
                DB::raw('DATE_FORMAT(disbursed_at, "%Y-%m") as month'),
                DB::raw('count(*) as count'),
                DB::raw('sum(loan_amount) as total_amount')
            )
            ->whereNotNull('disbursed_at')
            ->where('disbursed_at', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        $monthsWindow = collect(range(0, 11))->map(fn($i) => Carbon::now()->subMonths(11 - $i)->startOfMonth());

        $loansPerMonth = $monthsWindow->map(function (Carbon $date) use ($rawLoansPerMonth) {
            $monthKey = $date->format('Y-m');
            $matching = $rawLoansPerMonth->firstWhere('month', $monthKey);

            return [
                'month' => $date->format('M Y'),
                'count' => $matching ? (int) $matching->count : 0,
                'total_amount' => $matching ? (float) $matching->total_amount : 0,
            ];
        });

        // Loans by product
        $loansByProduct = (clone $baseLoanQuery)->select('loan_code', DB::raw('count(*) as count'))
            ->groupBy('loan_code')
            ->get();

        // Average loan amount
        $avgLoanAmount = (float) ((clone $baseLoanQuery)->avg('loan_amount') ?? 0);

        // Filters for loans table
        $loansTableQuery = LoanAccount::with(['client.user', 'loanProduct', 'emis']);
        $this->applyLoanFilterQuery($loansTableQuery, $request);

        switch ($sortOption) {
            case 'oldest':
                $loansTableQuery->orderBy('created_at', 'asc');
                break;
            case 'amount_high':
                $loansTableQuery->orderBy('loan_amount', 'desc');
                break;
            case 'amount_low':
                $loansTableQuery->orderBy('loan_amount', 'asc');
                break;
            case 'status_asc':
                $loansTableQuery->orderBy('status')->orderBy('created_at', 'desc');
                break;
            case 'status_desc':
                $loansTableQuery->orderBy('status', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $loansTableQuery->orderBy('created_at', 'desc');
                break;
        }

        $latestLoans = $loansTableQuery->paginate(15)->withQueryString();

        $defaultStatuses = ['active', 'closed', 'overdue', 'pending', 'disbursed', 'approved'];
        $dbStatuses = $loanStatusCounts->keys()->toArray();
        $allStatuses = collect($defaultStatuses)
            ->merge($dbStatuses)
            ->unique()
            ->values();

        $availableStatuses = $allStatuses->map(fn($status) => [
            'value' => $status,
            'label' => $formatLoanStatus($status)
        ]);

        // Locations for Area filter dropdown
        $locations = Location::all();

        return view('admin.report-analytics.loans.loans', compact(
            'totalLoans',
            'activeLoans',
            'closedLoans',
            'overdueLoans',
            'loansByStatus',
            'totalDisbursed',
            'totalOutstanding',
            'totalPaid',
            'loansPerMonth',
            'loansByProduct',
            'avgLoanAmount',
            'latestLoans',
            'availableStatuses',
            'filterStatus',
            'fromDate',
            'toDate',
            'sortOption',
            'locations',
            'loanProducts',
            'loan_product_id'
        ));
    }

    /**
     * Export Loans Report
     */
    public function exportLoans(Request $request)
    {
        $format = $request->get('format', 'csv');
        $sort = $request->input('sort');

        $loansQuery = LoanAccount::with(['client.user', 'client.location', 'loanProduct', 'emis']);
        $this->applyLoanFilterQuery($loansQuery, $request);

        switch ($sort) {
            case 'oldest':
                $loansQuery->orderBy('created_at', 'asc');
                break;
            case 'amount_high':
                $loansQuery->orderBy('loan_amount', 'desc');
                break;
            case 'amount_low':
                $loansQuery->orderBy('loan_amount', 'asc');
                break;
            case 'status_asc':
                $loansQuery->orderBy('status')->orderBy('created_at', 'desc');
                break;
            case 'status_desc':
                $loansQuery->orderBy('status', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $loansQuery->orderBy('created_at', 'desc');
                break;
        }
        
        $today = Carbon::today();
        $loans = $loansQuery
            ->select('loan_accounts.*')
            ->get()
            ->map(function($loan, $index) use ($today) {
                $effectiveStatus = $this->loanReportStatus($loan);

                $clientName = $loan->client?->client_name ?: ($loan->client?->user?->name ?? 'N/A');
                $clientPhone = $loan->client?->client_phone ?: ($loan->client?->alternate_phone ?? 'N/A');
                $productName = $loan->loanProduct?->loan_name ?? $loan->loan_code ?? 'N/A';
                $overdueAmount = $loan->emis
                    ->filter(function ($emi) use ($today) {
                        if (! in_array(strtolower((string) $emi->status), ['pending', 'overdue', 'partial'], true)) {
                            return false;
                        }
                        $due = $emi->due_date ? Carbon::parse($emi->due_date)->startOfDay() : null;

                        return $due && $due->lt($today);
                    })
                    ->sum('pending_amount');
                $loanEndDate = $loan->emis->max('due_date');

                return [
                    'S.No' => $index + 1,
                    'Account Number' => $loan->account_number ?? $loan->customer_loan_account_number ?? $loan->id,
                    'Client' => $clientName,
                    'Phone' => $clientPhone,
                    'Loan Product' => $productName,
                    'Loan Amount (₹)' => number_format((float) ($loan->loan_amount ?? 0), 2),
                    'Total Paid (₹)' => number_format((float) $loan->total_paid, 2),
                    'Outstanding (₹)' => number_format((float) ($loan->outstanding_amount ?? 0), 2),
                    'Overdue (₹)' => number_format((float) $overdueAmount, 2),
                    'Loan End Date' => $loanEndDate ? Carbon::parse($loanEndDate)->format('d M Y') : 'N/A',
                    'Status' => ucfirst(str_replace('_', ' ', $effectiveStatus)),
                ];
            });

        $statusLower = strtolower(trim((string) $request->input('status', 'all')));
        $title = $statusLower === 'overdue' ? 'Overdue Loans' : $this->getReportTitle('loans_report');

        return ReportExporter::download(
            $loans,
            $format,
            'loans_report',
            $title,
            $request->except(['format', 'page', '_export'])
        );
    }

    /**
     * Applications Report
     */
    public function applications(Request $request)
    {
        // Filters for applications table and aggregates
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filterStatus = $request->input('status', 'all');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        // Base Query for Aggregates
        $baseAppQuery = LoanApplication::query();
        
        if ($location_id) {
            $baseAppQuery->whereHas('client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }
        if ($product_id) {
            $baseAppQuery->whereHas('product', function($q) use ($product_id) {
                $q->where('id', $product_id);
            });
        }
        if ($fromDate) {
            $baseAppQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }
        if ($toDate) {
            $baseAppQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        // Applications by status (filtered)
        $rawApplicationStatusCounts = (clone $baseAppQuery)
            ->selectRaw('LOWER(TRIM(status)) as status, count(*) as count')
            ->groupBy('status')
            ->get();

        $applicationStatusCounts = $rawApplicationStatusCounts->pluck('count', 'status')->map(fn($count) => (int) $count);

        $totalApplications = $rawApplicationStatusCounts->sum('count');
        $pendingApplications = $applicationStatusCounts['pending'] ?? 0;
        $approvedApplications = $applicationStatusCounts['approved'] ?? 0;
        $processApplications = $applicationStatusCounts['process'] ?? 0;
        $disbursedApplications = $applicationStatusCounts['disbursed'] ?? 0;
        $rejectedApplications = $applicationStatusCounts['rejected'] ?? 0;

        $applicationStatusOrder = ['pending', 'process', 'approved', 'disbursed', 'rejected'];
        $formatApplicationStatus = fn(string $status) => Str::title(str_replace('_', ' ', $status));

        $applicationsByStatus = collect($applicationStatusOrder)
            ->map(function ($status) use ($applicationStatusCounts, $formatApplicationStatus) {
                $count = $applicationStatusCounts[$status] ?? 0;

                return [
                    'status' => $status,
                    'label' => $formatApplicationStatus($status),
                    'count' => $count,
                ];
            })
            ->filter(fn($item) => $item['count'] > 0)
            ->values();

        $rawApplicationStatusCounts->each(function ($item) use (&$applicationsByStatus, $applicationStatusOrder, $formatApplicationStatus) {
            if (!in_array($item->status, $applicationStatusOrder, true)) {
                $applicationsByStatus->push([
                    'status' => $item->status,
                    'label' => $formatApplicationStatus($item->status),
                    'count' => (int) $item->count,
                ]);
            }
        });

        // Applications per month (last 12 months)
        $rawApplicationsPerMonth = LoanApplication::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        $applicationsMonthsWindow = collect(range(0, 11))->map(fn($i) => Carbon::now()->subMonths(11 - $i)->startOfMonth());

        $applicationsPerMonth = $applicationsMonthsWindow->map(function (Carbon $date) use ($rawApplicationsPerMonth) {
            $monthKey = $date->format('Y-m');
            $matching = $rawApplicationsPerMonth->firstWhere('month', $monthKey);

            return [
                'month' => $date->format('M Y'),
                'count' => $matching ? (int) $matching->count : 0,
            ];
        });

        // Applications by loan product
        $applicationsByProduct = LoanApplication::select('loan_code', DB::raw('count(*) as count'))
            ->groupBy('loan_code')
            ->get();

        // Average processing time (from pending to disbursed)
        $avgProcessingTime = LoanApplication::whereNotNull('disbursed_at')
            ->select(DB::raw('AVG(DATEDIFF(disbursed_at, created_at)) as avg_days'))
            ->first()
            ->avg_days ?? 0;

        // Approval rate
        $approvalRate = $totalApplications > 0 
            ? (($approvedApplications + $disbursedApplications) / $totalApplications) * 100 
            : 0;

        // Filters for applications table
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        $applicationsTableQuery = LoanApplication::with(['client.user']);

        if ($location_id) {
            $applicationsTableQuery->whereHas('client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($product_id) {
            $applicationsTableQuery->whereHas('product', function($q) use ($product_id) {
                $q->where('id', $product_id);
            });
        }

        if ($filterStatus !== 'all') {
            $applicationsTableQuery->where('status', $filterStatus);
        }

        if ($fromDate) {
            $applicationsTableQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $applicationsTableQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        switch ($sortOption) {
            case 'oldest':
                $applicationsTableQuery->orderBy('created_at', 'asc');
                break;
            case 'name_asc':
                $applicationsTableQuery
                    ->leftJoin('clients', 'loan_applications.client_id', '=', 'clients.id')
                    ->select('loan_applications.*')
                    ->orderBy('clients.client_name', 'asc')
                    ->orderBy('loan_applications.created_at', 'desc');
                break;
            case 'name_desc':
                $applicationsTableQuery
                    ->leftJoin('clients', 'loan_applications.client_id', '=', 'clients.id')
                    ->select('loan_applications.*')
                    ->orderBy('clients.client_name', 'desc')
                    ->orderBy('loan_applications.created_at', 'desc');
                break;
            case 'amount_high':
                $applicationsTableQuery->orderBy('loan_amount', 'desc');
                break;
            case 'amount_low':
                $applicationsTableQuery->orderBy('loan_amount', 'asc');
                break;
            case 'status_asc':
                $applicationsTableQuery->orderBy('status')->orderBy('created_at', 'desc');
                break;
            case 'status_desc':
                $applicationsTableQuery->orderBy('status', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $applicationsTableQuery->orderBy('created_at', 'desc');
                break;
        }

        $latestApplications = $applicationsTableQuery->paginate(15)->withQueryString();

        $availableStatuses = $applicationStatusCounts->keys()->map(fn($status) => [
            'value' => $status,
            'label' => $formatApplicationStatus($status)
        ]);

        $locations = Location::orderBy('name')->get();
        $products = LoanProduct::orderBy('loan_name')->get();

        return view('admin.report-analytics.applications.applications', compact(
            'totalApplications',
            'pendingApplications',
            'approvedApplications',
            'processApplications',
            'disbursedApplications',
            'rejectedApplications',
            'applicationsByStatus',
            'applicationsPerMonth',
            'applicationsByProduct',
            'avgProcessingTime',
            'approvalRate',
            'latestApplications',
            'availableStatuses',
            'filterStatus',
            'fromDate',
            'toDate',
            'sortOption',
            'locations',
            'products'
        ));
    }

    /**
     * Export Applications Report
     */
    public function exportApplications(Request $request)
    {
        $format = $request->get('format', 'csv');
        
        $applicationsQuery = LoanApplication::with(['client.user', 'product']);

        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $status = $request->input('status');
        $sort = $request->input('sort');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        if ($location_id) {
            $applicationsQuery->whereHas('client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($product_id) {
            $applicationsQuery->whereHas('product', function($q) use ($product_id) {
                $q->where('id', $product_id);
            });
        }

        if ($status && $status !== 'all') {
            $applicationsQuery->where('status', $status);
        }

        if ($fromDate) {
            $applicationsQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $applicationsQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        switch ($sort) {
            case 'oldest':
                $applicationsQuery->orderBy('created_at', 'asc');
                break;
            case 'amount_high':
                $applicationsQuery->orderBy('loan_amount', 'desc');
                break;
            case 'amount_low':
                $applicationsQuery->orderBy('loan_amount', 'asc');
                break;
            case 'status_asc':
                $applicationsQuery->orderBy('status')->orderBy('created_at', 'desc');
                break;
            case 'status_desc':
                $applicationsQuery->orderBy('status', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $applicationsQuery->orderBy('created_at', 'desc');
                break;
        }

        $applications = $applicationsQuery
            ->get()
            ->map(function($app, $index) {
                return [
                    'S.No' => $index + 1,
                    'Application No' => $app->application_number,
                    'Client Name' => $app->client?->client_name ?: ($app->client?->user?->name ?? 'N/A'),
                    'Loan Product' => $app->product?->loan_name ?? $app->loan_code,
                    'Amount (₹)' => number_format($app->loan_amount, 0),
                    'Tenure' => $app->tenure . ' months',
                    'Status' => ucfirst($app->status),
                    'Applied Date' => $app->created_at->format('Y-m-d'),
                ];
            });

        return $this->exportData($applications, 'applications_report', $format);
    }

    /**
     * EMI Report
     */
    public function emi(Request $request)
    {
        // Filters for EMI table and aggregates
        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $filterStatus = $request->input('status', 'all');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        // Base Query for Aggregates
        $baseEmiQuery = Emi::query();
        
        if ($location_id) {
            $baseEmiQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }
        if ($product_id) {
            $baseEmiQuery->whereHas('loanAccount.loanApplication.product', function($q) use ($product_id) {
                $q->where('id', $product_id);
            });
        }
        if ($fromDate) {
            $baseEmiQuery->where('due_date', '>=', Carbon::parse($fromDate)->startOfDay());
        }
        if ($toDate) {
            $baseEmiQuery->where('due_date', '<=', Carbon::parse($toDate)->endOfDay());
        }

        // EMIs by status (filtered)
        $rawEmiStatusCounts = (clone $baseEmiQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        $emiStatusCounts = $rawEmiStatusCounts->pluck('count', 'status')->map(fn($count) => (int) $count);

        $totalEmis = $rawEmiStatusCounts->sum('count');
        
        // Recalculate counts with true overdue logic
        $paidEmis = (clone $baseEmiQuery)->where('status', 'paid')->count();
        $partialEmis = (clone $baseEmiQuery)->where('status', 'partial')
            ->where('due_date', '>=', now()->startOfDay())->count();
        $pendingEmis = (clone $baseEmiQuery)->where('status', 'pending')
            ->where('due_date', '>=', now()->startOfDay())->count();
        $overdueEmis = (clone $baseEmiQuery)->where(function($q) {
            $q->where('status', 'overdue')
              ->orWhere(function($sq) {
                  $sq->whereIn('status', ['pending', 'partial'])
                     ->where('due_date', '<', now()->startOfDay());
              });
        })->count();

        $emiStatusCounts = collect([
            'paid' => $paidEmis,
            'pending' => $pendingEmis,
            'overdue' => $overdueEmis,
            'partial' => $partialEmis
        ]);

        $emiStatusOrder = ['paid', 'pending', 'overdue', 'partial'];
        $formatEmiStatus = function(string $status) {
            if ($status === 'partial') return 'Partially Paid';
            return Str::title(str_replace('_', ' ', $status));
        };

        $emisByStatus = collect($emiStatusOrder)
            ->map(function ($status) use ($emiStatusCounts, $formatEmiStatus) {
                $count = $emiStatusCounts[$status] ?? 0;

                return [
                    'status' => $status,
                    'label' => $formatEmiStatus($status),
                    'count' => $count,
                ];
            })
            ->filter(fn($item) => $item['count'] > 0)
            ->values();

        // Total EMI amounts
        $totalEmiAmount = Emi::sum('total_amount');
        $paidEmiAmount = Emi::where('status', 'paid')->sum('paid_amount') + Emi::where('status', 'partial')->sum('partial_paid_amount');
        $pendingEmiAmount = Emi::where('status', 'pending')->sum('total_amount');
        $overdueEmiAmount = Emi::where('status', 'overdue')->sum('total_amount');

        // EMI collection per month (last 12 months)
        $windowStart = Carbon::now()->subMonths(11)->startOfMonth();
        $windowEnd = Carbon::now()->endOfMonth();

        $rawEmiDuePerMonth = Emi::select(
                DB::raw('DATE_FORMAT(due_date, "%Y-%m") as month'),
                DB::raw('count(*) as count'),
                DB::raw('sum(total_amount) as total_amount')
            )
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$windowStart, $windowEnd])
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get()
            ->keyBy('month');

        $rawEmiCollectedPerMonth = \App\Models\EmiCollection::whereIn('status', ['verified', 'in_progress', 'approved', 'success', 'paid'])
            ->where(function ($q) use ($windowStart, $windowEnd) {
                $q->whereBetween('collected_at', [$windowStart, $windowEnd])
                  ->orWhere(function ($q2) use ($windowStart, $windowEnd) {
                      $q2->whereNull('collected_at')
                         ->whereBetween('created_at', [$windowStart, $windowEnd]);
                  });
            })
            ->select(
                DB::raw('DATE_FORMAT(COALESCE(collected_at, created_at), "%Y-%m") as month'),
                DB::raw('SUM(amount) as collected_amount')
            )
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $emiMonthsWindow = collect(range(0, 11))->map(fn($i) => Carbon::now()->subMonths(11 - $i)->startOfMonth());

        $emiCollectionPerMonth = $emiMonthsWindow->map(function (Carbon $date) use ($rawEmiDuePerMonth, $rawEmiCollectedPerMonth) {
            $monthKey = $date->format('Y-m');
            $dueMatching = $rawEmiDuePerMonth->get($monthKey);
            $colMatching = $rawEmiCollectedPerMonth->get($monthKey);

            return [
                'month' => $date->format('M Y'),
                'count' => $dueMatching ? (int) $dueMatching->count : 0,
                'total_amount' => $dueMatching ? (float) $dueMatching->total_amount : 0,
                'collected_amount' => $colMatching ? (float) $colMatching->collected_amount : 0,
            ];
        });

        // Collection rate
        $collectionRate = $totalEmiAmount > 0 
            ? ($paidEmiAmount / $totalEmiAmount) * 100 
            : 0;

        // Upcoming EMIs (due today or within next 30 days, status pending or partial)
        $todayStart = Carbon::today()->startOfDay();
        $upcomingEnd = Carbon::today()->addDays(30)->endOfDay();

        $upcomingEmisQuery = (clone $baseEmiQuery)
            ->whereIn('status', ['pending', 'partial'])
            ->whereBetween('due_date', [$todayStart, $upcomingEnd]);

        $upcomingEmis = (clone $upcomingEmisQuery)->count();
        $upcomingEmiAmount = (clone $upcomingEmisQuery)->sum('total_amount');

        // Filters for EMI table
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        $emisTableQuery = Emi::with(['loanAccount.loanApplication.client.user']);

        if ($location_id) {
            $emisTableQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($product_id) {
            $emisTableQuery->whereHas('loanAccount.loanApplication.product', function($q) use ($product_id) {
                $q->where('id', $product_id);
            });
        }

        if ($filterStatus !== 'all') {
            if ($filterStatus === 'overdue') {
                $emisTableQuery->where(function($q) {
                    $q->where('status', 'overdue')
                      ->orWhere(function($sq) {
                          $sq->whereIn('status', ['pending', 'partial'])
                             ->where('due_date', '<', now()->startOfDay());
                      });
                });
            } else {
                $emisTableQuery->where('status', $filterStatus);
            }
        }

        if ($fromDate) {
            $emisTableQuery->where('due_date', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $emisTableQuery->where('due_date', '<=', Carbon::parse($toDate)->endOfDay());
        }

        switch ($sortOption) {
            case 'oldest':
                $emisTableQuery->orderBy('due_date', 'asc');
                break;
            case 'amount_high':
                $emisTableQuery->orderBy('total_amount', 'desc');
                break;
            case 'amount_low':
                $emisTableQuery->orderBy('total_amount', 'asc');
                break;
            case 'status_asc':
                $emisTableQuery->orderBy('status')->orderBy('due_date', 'desc');
                break;
            case 'status_desc':
                $emisTableQuery->orderBy('status', 'desc')->orderBy('due_date', 'desc');
                break;
            case 'newest':
            default:
                $emisTableQuery->orderBy('due_date', 'desc');
                break;
        }

        $latestEmis = $emisTableQuery->paginate(15)->withQueryString();

        $availableStatuses = $emiStatusCounts->keys()->map(fn($status) => [
            'value' => $status,
            'label' => $formatEmiStatus($status)
        ]);

        $locations = Location::orderBy('name')->get();
        $products = LoanProduct::orderBy('loan_name')->get();

        return view('admin.report-analytics.emi.emi', compact(
            'totalEmis',
            'paidEmis',
            'pendingEmis',
            'overdueEmis',
            'partialEmis',
            'emisByStatus',
            'totalEmiAmount',
            'paidEmiAmount',
            'pendingEmiAmount',
            'overdueEmiAmount',
            'emiCollectionPerMonth',
            'collectionRate',
            'upcomingEmis',
            'upcomingEmiAmount',
            'latestEmis',
            'availableStatuses',
            'filterStatus',
            'fromDate',
            'toDate',
            'sortOption',
            'locations',
            'products'
        ));
    }

    /**
     * Export EMI Report
     */
    public function exportEmi(Request $request)
    {
        $format = $request->get('format', 'csv');
        
        $emisQuery = Emi::with(['loanAccount.client.user', 'loanAccount.client.location', 'loanAccount.loanApplication.client.user', 'loanAccount.loanApplication.client.location']);

        [$fromDate, $toDate] = DateRangePreset::applyToRequest($request);
        $status = $request->input('status');
        $sort = $request->input('sort');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        if ($location_id) {
            $emisQuery->whereHas('loanAccount.client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($product_id) {
            $emisQuery->whereHas('loanAccount', function($q) use ($product_id) {
                $q->where('loan_product_id', $product_id)
                  ->orWhereHas('loanApplication', fn($app) => $app->where('loan_product_id', $product_id));
            });
        }

        if ($status && $status !== 'all') {
            $emisQuery->where('status', $status);
        }

        if ($fromDate) {
            $emisQuery->where('due_date', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $emisQuery->where('due_date', '<=', Carbon::parse($toDate)->endOfDay());
        }

        switch ($sort) {
            case 'oldest':
                $emisQuery->orderBy('due_date', 'asc');
                break;
            case 'amount_high':
                $emisQuery->orderBy('total_amount', 'desc');
                break;
            case 'amount_low':
                $emisQuery->orderBy('total_amount', 'asc');
                break;
            case 'status_asc':
                $emisQuery->orderBy('status')->orderBy('due_date', 'desc');
                break;
            case 'status_desc':
                $emisQuery->orderBy('status', 'desc')->orderBy('due_date', 'desc');
                break;
            case 'newest':
            default:
                $emisQuery->orderBy('due_date', 'desc');
                break;
        }

        $emis = $emisQuery
            ->get()
            ->map(function($emi, $index) {
                $loanAccount = $emi->loanAccount;
                $loanApplication = optional($loanAccount)->loanApplication;
                $client = optional($loanAccount)->client ?? optional($loanApplication)->client;

                $clientName = optional($client)->client_name ?: (optional($client)->user?->name ?? 'N/A');
                $clientPhone = optional($client)->client_phone ?: (optional($client)->alternate_phone ?? 'N/A');
                $location = optional($client)->location?->name ?? 'N/A';

                return [
                    'S.No' => $index + 1,
                    'Loan Account No' => optional($loanAccount)->account_number ?? optional($loanAccount)->customer_loan_account_number ?? 'N/A',
                    'Application No' => $emi->application_number
                        ?? optional($loanAccount)->application_number
                        ?? optional($loanApplication)->application_number ?? 'N/A',
                    'Client Name' => $clientName,
                    'Client Phone' => $clientPhone,
                    'Location / Branch' => $location,
                    'Instalment' => '#' . $emi->instalment_number,
                    'Due Date' => $emi->due_date ? $emi->due_date->format('Y-m-d') : 'N/A',
                    'EMI Amount (₹)' => number_format($emi->total_amount ?? 0, 2),
                    'Principal (₹)' => number_format($emi->principal_amount ?? 0, 2),
                    'Interest (₹)' => number_format($emi->interest_amount ?? 0, 2),
                    'Status' => ucfirst($emi->status ?? 'unknown'),
                    'Paid Date' => $emi->paid_date ? $emi->paid_date->format('Y-m-d') : 'N/A',
                ];
            });

        return $this->exportData($emis, 'emi_report', $format);
    }

    /**
     * Helper function to export data in different formats
     */
    private function exportData($data, $filename, $format = 'csv')
    {
        return ReportExporter::download(
            $data,
            $format,
            $filename,
            $this->getReportTitle($filename),
            request()->except(['format', 'page', '_export'])
        );
    }


    /**
     * Get report title based on filename
     */
    private function getReportTitle($filename)
    {
        $titles = [
            'clients_report' => 'Clients Report',
            'applications_report' => 'Loan Applications Report',
            'loans_report' => 'Loans Report',
            'emi_report' => 'EMI Report'
        ];
        
        return $titles[$filename] ?? 'Report';
    }


}
