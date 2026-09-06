<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\Appearance;
use App\Models\Location;
use App\Models\LoanProduct;
use App\Models\LoanType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Helpers\AppearanceHelper;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportsAnalyticsController extends Controller
{
    /**
     * Clients Report
     */
    public function clients(Request $request)
    {
        // Filters for table and aggregates
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
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

        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
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

        $clients = $clientsQuery
            ->select('clients.*')
            ->get()
            ->map(function($client, $index) {
                return [
                    'S.No' => $index + 1,
                    'Name' => $client->user?->name ?? 'N/A',
                    'Email' => $client->user?->email ?? 'N/A',
                    'Phone' => $client->client_phone ?? 'N/A',
                    'Status' => ucfirst($client->status),
                    'Registered Date' => $client->created_at->format('Y-m-d'),
                ];
            });

        return $this->exportData($clients, 'clients_report', $format);
    }

    /**
     * Loans Report
     */
    public function loans(Request $request)
    {
        // Filters for loans table and aggregates
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sortOption = $request->input('sort', 'newest');
        $location_id = $request->input('location_id');
        $loan_type_id = $request->input('loan_type_id');
        $product_id = $request->input('product_id');
        $loan_mode = $request->input('loan_mode');

        // Common filter application helper
        $applyFilters = function ($query) use ($location_id, $loan_type_id, $product_id, $loan_mode, $fromDate, $toDate) {
            if ($location_id) {
                $query->whereHas('client', function ($q) use ($location_id) {
                    $q->where('location_id', $location_id);
                });
            }

            if ($loan_type_id) {
                $query->where(function ($q) use ($loan_type_id) {
                    $q->whereIn('loan_code', function ($sq) use ($loan_type_id) {
                        $sq->select('loan_code')->from('loan_products')->where('loan_type_id', $loan_type_id);
                    })->orWhereHas('loanApplication', function ($sq) use ($loan_type_id) {
                        $sq->whereIn('loan_code', function ($ssq) use ($loan_type_id) {
                            $ssq->select('loan_code')->from('loan_products')->where('loan_type_id', $loan_type_id);
                        });
                    });
                });
            }

            if ($product_id) {
                $product = LoanProduct::find($product_id);
                if ($product) {
                    $query->where(function ($q) use ($product) {
                        $q->where('loan_code', $product->loan_code)
                          ->orWhereHas('loanApplication', function ($sq) use ($product) {
                              $sq->where('loan_code', $product->loan_code);
                          });
                    });
                }
            }

            if ($loan_mode && $loan_mode !== 'all') {
                if ($loan_mode === 'emi') {
                    $query->where(function ($sq) {
                        $sq->where('loan_mode', 'emi')
                          ->orWhereNull('loan_mode');
                    });
                } else {
                    $query->where('loan_mode', $loan_mode);
                }
            }

            if ($fromDate) {
                $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
            }

            if ($toDate) {
                $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
            }
        };

        // Base Query for Aggregates
        $baseLoanQuery = LoanAccount::query();
        $applyFilters($baseLoanQuery);

        // Loans by status (filtered)
        $rawLoanStatusCounts = (clone $baseLoanQuery)
            ->selectRaw('LOWER(TRIM(status)) as status, count(*) as count')
            ->groupBy('status')
            ->get();

        $loanStatusCounts = $rawLoanStatusCounts->pluck('count', 'status')->map(fn($count) => (int) $count);

        $totalLoans = $rawLoanStatusCounts->sum('count');
        $activeLoans = $loanStatusCounts['active'] ?? 0;
        $closedLoans = $loanStatusCounts['closed'] ?? 0;
        $overdueLoans = $loanStatusCounts['overdue'] ?? 0;

        $loanStatusOrder = ['active', 'closed', 'overdue', 'pending'];
        $formatLoanStatus = fn(string $status) => Str::title(str_replace('_', ' ', $status));

        $loansByStatus = collect($loanStatusOrder)
            ->map(function ($status) use ($loanStatusCounts, $formatLoanStatus) {
                $count = $loanStatusCounts[$status] ?? 0;

                return [
                    'status' => $status,
                    'label' => $formatLoanStatus($status),
                    'count' => $count,
                ];
            })
            ->filter(fn($item) => $item['count'] > 0)
            ->values();

        $rawLoanStatusCounts->each(function ($item) use (&$loansByStatus, $loanStatusOrder, $formatLoanStatus) {
            if (!in_array($item->status, $loanStatusOrder, true)) {
                $loansByStatus->push([
                    'status' => $item->status,
                    'label' => $formatLoanStatus($item->status),
                    'count' => (int) $item->count,
                ]);
            }
        });

        // Total loan amount disbursed
        $totalDisbursed = (clone $baseLoanQuery)->sum('loan_amount');
        $totalOutstanding = (clone $baseLoanQuery)->where('status', 'active')->sum('outstanding_amount');
        $totalPaid = (clone $baseLoanQuery)->sum('paid_amount');

        // Loans disbursed per month (last 12 months)
        $rawLoansPerMonth = (clone $baseLoanQuery)
            ->select(
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
        $loansByProduct = (clone $baseLoanQuery)
            ->select('loan_code', DB::raw('count(*) as count'))
            ->groupBy('loan_code')
            ->get();

        // Average loan amount
        $avgLoanAmount = (float) ((clone $baseLoanQuery)->avg('loan_amount') ?? 0);

        // Filters for loans table
        $loansTableQuery = LoanAccount::with(['client.user']);
        $applyFilters($loansTableQuery);

        if ($filterStatus !== 'all') {
            $loansTableQuery->where('status', $filterStatus);
        }

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

        $availableStatuses = $loanStatusCounts->keys()->map(fn($status) => [
            'value' => $status,
            'label' => $formatLoanStatus($status)
        ]);

        // Dropdown collections
        $locations = Location::all();
        $loanTypes = LoanType::where('status', 1)->orWhereHas('products')->orderBy('name')->get();
        $products = LoanProduct::with('loanType')->orderBy('loan_name')->get();

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
            'loanTypes',
            'products'
        ));
    }

    /**
     * Export Loans Report
     */
    public function exportLoans(Request $request)
    {
        $format = $request->get('format', 'csv');
        
        $loansQuery = LoanAccount::with(['client.user']);

        $status = $request->input('status');
        $location_id = $request->input('location_id');
        $loan_type_id = $request->input('loan_type_id');
        $product_id = $request->input('product_id');
        $loan_mode = $request->input('loan_mode');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sort = $request->input('sort');

        if ($location_id) {
            $loansQuery->whereHas('client', function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($loan_type_id) {
            $loansQuery->where(function ($q) use ($loan_type_id) {
                $q->whereIn('loan_code', function ($sq) use ($loan_type_id) {
                    $sq->select('loan_code')->from('loan_products')->where('loan_type_id', $loan_type_id);
                })->orWhereHas('loanApplication', function ($sq) use ($loan_type_id) {
                    $sq->whereIn('loan_code', function ($ssq) use ($loan_type_id) {
                        $ssq->select('loan_code')->from('loan_products')->where('loan_type_id', $loan_type_id);
                    });
                });
            });
        }

        if ($product_id) {
            $product = LoanProduct::find($product_id);
            if ($product) {
                $loansQuery->where(function ($q) use ($product) {
                    $q->where('loan_code', $product->loan_code)
                      ->orWhereHas('loanApplication', function ($sq) use ($product) {
                          $sq->where('loan_code', $product->loan_code);
                      });
                });
            }
        }

        if ($loan_mode && $loan_mode !== 'all') {
            if ($loan_mode === 'emi') {
                $loansQuery->where(function ($sq) {
                    $sq->where('loan_mode', 'emi')
                      ->orWhereNull('loan_mode');
                });
            } else {
                $loansQuery->where('loan_mode', $loan_mode);
            }
        }

        if ($status && $status !== 'all') {
            $loansQuery->where('status', $status);
        }

        if ($fromDate) {
            $loansQuery->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate) {
            $loansQuery->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

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
        
        $loans = $loansQuery
            ->select('loan_accounts.*')
            ->get()
            ->map(function($loan, $index) {
                return [
                    'S.No' => $index + 1,
                    'Client' => $loan->client?->user?->name ?? 'N/A',
                    'Loan Code' => $loan->loan_code,
                    'Amount (₹)' => number_format($loan->loan_amount, 0),
                    'Outstanding (₹)' => number_format($loan->outstanding_amount, 2),
                    'Status' => ucfirst($loan->status),
                    'Disbursed Date' => $loan->disbursed_at ? $loan->disbursed_at->format('Y-m-d') : 'N/A',
                ];
            });

        return $this->exportData($loans, 'loans_report', $format);
    }

    /**
     * Applications Report
     */
    public function applications(Request $request)
    {
        // Filters for applications table and aggregates
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
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
                    ->leftJoin('users', 'clients.user_id', '=', 'users.id')
                    ->select('loan_applications.*')
                    ->orderBy('users.name', 'asc')
                    ->orderBy('loan_applications.created_at', 'desc');
                break;
            case 'name_desc':
                $applicationsTableQuery
                    ->leftJoin('clients', 'loan_applications.client_id', '=', 'clients.id')
                    ->leftJoin('users', 'clients.user_id', '=', 'users.id')
                    ->select('loan_applications.*')
                    ->orderBy('users.name', 'desc')
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

        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
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
                    'Client Name' => $app->client?->user?->name ?? 'N/A',
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
        $filterStatus = $request->input('status', 'all');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
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
        $paidEmiAmount = Emi::where('status', 'paid')->sum('paid_amount');
        $pendingEmiAmount = Emi::where('status', 'pending')->sum('total_amount');
        $overdueEmiAmount = Emi::where('status', 'overdue')->sum('total_amount');

        // EMI collection per month (last 12 months)
        $rawEmiCollectionPerMonth = Emi::select(
                DB::raw('DATE_FORMAT(due_date, "%Y-%m") as month'),
                DB::raw('count(*) as count'),
                DB::raw('sum(total_amount) as total_amount'),
                DB::raw('sum(CASE WHEN status = "paid" THEN paid_amount ELSE 0 END) as collected_amount')
            )
            ->whereNotNull('due_date')
            ->where('due_date', '>=', Carbon::now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        $emiMonthsWindow = collect(range(0, 11))->map(fn($i) => Carbon::now()->subMonths(11 - $i)->startOfMonth());

        $emiCollectionPerMonth = $emiMonthsWindow->map(function (Carbon $date) use ($rawEmiCollectionPerMonth) {
            $monthKey = $date->format('Y-m');
            $matching = $rawEmiCollectionPerMonth->firstWhere('month', $monthKey);

            return [
                'month' => $date->format('M Y'),
                'count' => $matching ? (int) $matching->count : 0,
                'total_amount' => $matching ? (float) $matching->total_amount : 0,
                'collected_amount' => $matching ? (float) $matching->collected_amount : 0,
            ];
        });

        // Collection rate
        $collectionRate = $totalEmiAmount > 0 
            ? ($paidEmiAmount / $totalEmiAmount) * 100 
            : 0;

        // Upcoming EMIs (next 30 days)
        $upcomingEmis = Emi::where('status', 'pending')
            ->whereBetween('due_date', [Carbon::now(), Carbon::now()->addDays(30)])
            ->count();

        $upcomingEmiAmount = Emi::where('status', 'pending')
            ->whereBetween('due_date', [Carbon::now(), Carbon::now()->addDays(30)])
            ->sum('total_amount');

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
        
        $emisQuery = Emi::with(['loanAccount.loanApplication.client.user']);

        $status = $request->input('status');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $sort = $request->input('sort');
        $location_id = $request->input('location_id');
        $product_id = $request->input('product_id');

        if ($location_id) {
            $emisQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        if ($product_id) {
            $emisQuery->whereHas('loanAccount.loanApplication.product', function($q) use ($product_id) {
                $q->where('id', $product_id);
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
                $client = optional($loanApplication)->client;
                $user = optional($client)->user;

                return [
                    'S.No' => $index + 1,
                    'Application No' => $emi->application_number
                        ?? optional($loanAccount)->application_number
                        ?? optional($loanApplication)->application_number,
                    'Client Name' => $user->name
                        ?? optional($client)->client_name
                        ?? 'N/A',
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
        if ($format === 'csv') {
            return $this->exportCSV($data, $filename);
        } elseif ($format === 'excel') {
            return $this->exportExcel($data, $filename);
        } elseif ($format === 'pdf') {
            return $this->exportPDF($data, $filename);
        }

        return response()->json(['error' => 'Invalid format'], 400);
    }

    /**
     * Export as CSV
     */
    private function exportCSV($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}_" . date('Y-m-d') . ".csv\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
            }
            
            // Add data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export as Excel (using CSV with .xlsx extension for simplicity)
     */
    private function exportExcel($data, $filename)
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}_" . date('Y-m-d') . ".xls\"",
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
            }
            
            // Add data
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export as PDF using dynamic_document template
     */
    private function exportPDF($data, $filename)
    {
        try {
            $reportTitle = $this->getReportTitle($filename);
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

    /**
     * Generate HTML table for report data
     */
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

    /**
     * Resolve logo data for PDF
     */
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

        // Fallback to URL if file exists in public but path resolution failed
        return [
            'logo' => asset('storage/' . $logoPath),
            'is_base64' => false
        ];
    }

}
