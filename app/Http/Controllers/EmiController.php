<?php

namespace App\Http\Controllers;

use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Support\HashId;
use Carbon\Carbon;

class EmiController extends Controller
{
    /**
     * Display repayments index with stats
     */
    public function index(): View
    {
        $today = Carbon::now()->startOfDay();
        
        $currentUser = auth()->user();
        $isAgent = $currentUser->hasRole('Agent');
        $agentId = $isAgent ? optional($currentUser->agent)->id : null;
 
        $baseEmiQuery = Emi::whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery());

        if ($isAgent && $agentId) {
            $baseEmiQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            })->whereHas('activeAssignment');
        }

        $activeEmiQuery = (clone $baseEmiQuery)->where(function ($q) use ($today) {
            // Overdue payments (status != paid and due date is in the past)
            $q->where(function ($sq) use ($today) {
                $sq->where('due_date', '<', $today)
                   ->where('status', '!=', 'paid');
            })
            // OR the upcoming 1 payment of the client (earliest unpaid/partial EMI due today or in the future)
            ->orWhereIn('emis.id', function ($subQuery) use ($today) {
                $subQuery->select(DB::raw('MIN(e2.id)'))
                    ->from('emis as e2')
                    ->where('e2.status', '!=', 'paid')
                    ->where('e2.due_date', '>=', $today)
                    ->groupBy('e2.loan_account_id');
            });
        });
        
        // Calculate statistics for the restricted repayments window
        $paidCount = (clone $baseEmiQuery)->where('status', 'paid')->count();
        $overdueCount = (clone $activeEmiQuery)->overdue()->count();
        $pendingCount = (clone $activeEmiQuery)->where('status', 'pending')->where('due_date', '>=', $today)->count();
        $partialCount = (clone $activeEmiQuery)->where('status', 'partial')->where('due_date', '>=', $today)->count();

        $stats = [
            'total_emis' => $overdueCount + $pendingCount + $partialCount + $paidCount,
            'paid_emis' => $paidCount,
            'pending_emis' => $pendingCount,
            'partial_emis' => $partialCount,
            'overdue_emis' => $overdueCount,
            'total_collected' => (clone $baseEmiQuery)->where('status', 'paid')->sum('paid_amount'),
            'total_pending' => (clone $activeEmiQuery)->sum('pending_amount'),
            'reminders_2days' => (clone $activeEmiQuery)
                ->whereDate('due_date', Carbon::now()->addDays(2)->toDateString())
                ->count(),
        ];

        $locations = Location::orderBy('name')->get();
        $agents = \App\Models\Agent::where('status', 'active')->orderBy('agent_name')->get();
        $products = \App\Models\LoanProduct::with('loanType')->orderBy('loan_name')->get();
        $loanTypes = \App\Models\LoanType::where('status', 1)->orWhereHas('products')->orderBy('name')->get();

        return view('admin.emi-repayments.repayments', compact('stats', 'locations', 'agents', 'products', 'loanTypes'));
    }

    /**
     * Bulk assign EMIs to an agent
     */
    public function bulkAssignAgent(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'emi_ids' => 'required|array',
            'emi_ids.*' => 'required',
            'agent_id' => 'required|exists:agents,id',
            'remarks' => 'nullable|string'
        ]);

        $agent = \App\Models\Agent::findOrFail($request->agent_id);
        $emiIds = $request->emi_ids;
        $count = 0;

        DB::beginTransaction();
        try {
            foreach ($emiIds as $hashedId) {
                $emiId = HashId::decode($hashedId);
                $emiId = is_array($emiId) ? ($emiId[0] ?? $hashedId) : ($emiId ?? $hashedId);
                
                $emi = Emi::findOrFail($emiId);

                // Create or update assignment
                \App\Models\EmiAgentAssignment::updateOrCreate(
                    ['emi_id' => $emi->id],
                    [
                        'agent_id' => $agent->id,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                        'remarks' => $request->remarks ?: 'Bulk assigned via Repayments list'
                    ]
                );

                // Also update Client assigned_to if needed (optional but helpful)
                if ($emi->loanAccount && $emi->loanAccount->client) {
                    $emi->loanAccount->client->update(['assigned_to' => $agent->id]);
                }

                $count++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$count} EMIs to {$agent->agent_name}"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk assignment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Assignment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get EMI data for DataTable
     */
    public function getData(Request $request): JsonResponse
    {
        $columns = [
            0 => 'id',
            1 => 'account_number',
            2 => 'due_date',
            3 => 'total_amount',
            4 => 'principal_amount',
            5 => 'status',
        ];

        // Fetch company mobile for WhatsApp message
        $companyMobile = \App\Models\CompanyDetail::first()?->company_mobile ?? '[Phone Number]';

        $today = Carbon::now()->startOfDay();
        $status = $request->input('status', 'overdue'); // Default to overdue if not specified

        // Build base query
        $query = Emi::with(['loanAccount.loanApplication.client.location', 'activeAssignment.agent' => function($q) {
            $q->withTrashed();
        }])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->select('emis.*');

        // Apply agent filter if current user is an agent
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $query->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                    $q->where('assigned_to', $agentId);
                })->whereHas('activeAssignment');
            }
        }

        // Apply location filter
        if ($request->has('location_id') && $request->location_id != '') {
            $query->whereHas('loanAccount.loanApplication.client', function($q) use ($request) {
                $q->where('location_id', $request->location_id);
            });
        }

        // Apply loan type filter
        if ($request->filled('loan_type_id')) {
            $loanTypeId = $request->loan_type_id;
            $query->whereHas('loanAccount', function ($q) use ($loanTypeId) {
                $q->where(function ($sq) use ($loanTypeId) {
                    $sq->whereIn('loan_code', function ($ssq) use ($loanTypeId) {
                        $ssq->select('loan_code')->from('loan_products')->where('loan_type_id', $loanTypeId);
                    })->orWhereHas('loanApplication', function ($ssq) use ($loanTypeId) {
                        $ssq->whereIn('loan_code', function ($sssq) use ($loanTypeId) {
                            $sssq->select('loan_code')->from('loan_products')->where('loan_type_id', $loanTypeId);
                        });
                    });
                });
            });
        }

        // Apply product filter
        if ($request->has('product_id') && $request->product_id != '') {
            $productId = $request->product_id;
            $product = \App\Models\LoanProduct::find($productId);
            if ($product) {
                $query->whereHas('loanAccount', function ($q) use ($product) {
                    $q->where(function ($sq) use ($product) {
                        $sq->where('loan_code', $product->loan_code)
                          ->orWhereHas('loanApplication', function ($ssq) use ($product) {
                              $ssq->where('loan_code', $product->loan_code);
                          });
                    });
                });
            }
        }

        // Apply loan mode / type filter (EMI vs Open Loan)
        if ($request->has('loan_mode') && $request->loan_mode != '' && $request->loan_mode != 'all') {
            $loanMode = $request->loan_mode;
            $query->whereHas('loanAccount', function($q) use ($loanMode) {
                if ($loanMode === 'emi') {
                    $q->where(function($sq) {
                        $sq->where('loan_mode', 'emi')
                          ->orWhereNull('loan_mode');
                    });
                } else {
                    $q->where('loan_mode', $loanMode);
                }
            });
        }

        // Optional date filtering
        if ($request->filled('from_date') || $request->filled('to_date')) {
            if ($request->filled('from_date')) {
                $from = $this->parseFilterDate($request->input('from_date'))?->startOfDay();
                if ($from) {
                    $query->where('due_date', '>=', $from);
                }
            }

            if ($request->filled('to_date')) {
                $to = $this->parseFilterDate($request->input('to_date'))?->endOfDay();
                if ($to) {
                    $query->where('due_date', '<=', $to);
                }
            }
        }

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->whereHas('loanAccount', function($q) use ($search) {
                      $q->where('account_number', 'LIKE', "%{$search}%")
                        ->orWhere('application_number', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('loanAccount.client', function($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%")
                        ->orWhere('client_phone', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Calculate dynamic stats
        $baseCountQuery = clone $query;

        $paidCount = (clone $baseCountQuery)->where('status', 'paid')->count();

        $activeEmiQuery = (clone $baseCountQuery)->where(function ($q) use ($today) {
            // Overdue payments (status != paid and due date is in the past)
            $q->where(function ($sq) use ($today) {
                $sq->where('due_date', '<', $today)
                   ->where('status', '!=', 'paid');
            })
            // OR the upcoming 1 payment of the client (earliest unpaid/partial EMI due today or in the future)
            ->orWhereIn('emis.id', function ($subQuery) use ($today) {
                $subQuery->select(DB::raw('MIN(e2.id)'))
                    ->from('emis as e2')
                    ->where('e2.status', '!=', 'paid')
                    ->where('e2.due_date', '>=', $today)
                    ->groupBy('e2.loan_account_id');
            });
        });

        $overdueCount = (clone $activeEmiQuery)->overdue()->count();
        $pendingCount = (clone $activeEmiQuery)->where('status', 'pending')->where('due_date', '>=', $today)->count();
        $partialCount = (clone $activeEmiQuery)->where('status', 'partial')->where('due_date', '>=', $today)->count();

        // Calculate dynamic stats array
        $dynamicStats = [
            'total_emis' => $overdueCount + $pendingCount + $partialCount + $paidCount,
            'paid_emis' => $paidCount,
            'pending_emis' => $pendingCount,
            'partial_emis' => $partialCount,
            'overdue_emis' => $overdueCount,
            'total_collected' => (clone $baseCountQuery)->where('status', 'paid')->sum('paid_amount'),
            'total_pending' => (clone $activeEmiQuery)->sum('pending_amount'),
        ];

        // DataTables parameters
        $limit = $request->input('length');
        $start = $request->input('start', 0);

        // Handle 'all' tab: Client-wise summary view displaying counts for overdue, pending, partial, paid, and total
        if ($status === 'all') {
            $loanQuery = LoanAccount::with(['client.location', 'client.agent', 'loanApplication', 'emis'])
                ->whereHas('emis');

            if ($currentUser->hasRole('Agent')) {
                $agentId = optional($currentUser->agent)->id;
                if ($agentId) {
                    $loanQuery->whereHas('client', function($q) use ($agentId) {
                        $q->where('assigned_to', $agentId);
                    });
                }
            }

            if ($request->has('location_id') && $request->location_id != '') {
                $loanQuery->whereHas('client', function($q) use ($request) {
                    $q->where('location_id', $request->location_id);
                });
            }

            if ($request->filled('loan_type_id')) {
                $loanTypeId = $request->loan_type_id;
                $loanQuery->where(function ($q) use ($loanTypeId) {
                    $q->whereIn('loan_code', function ($sq) use ($loanTypeId) {
                        $sq->select('loan_code')->from('loan_products')->where('loan_type_id', $loanTypeId);
                    })->orWhereHas('loanApplication', function ($sq) use ($loanTypeId) {
                        $sq->whereIn('loan_code', function ($ssq) use ($loanTypeId) {
                            $ssq->select('loan_code')->from('loan_products')->where('loan_type_id', $loanTypeId);
                        });
                    });
                });
            }

            if ($request->has('product_id') && $request->product_id != '') {
                $productId = $request->product_id;
                $product = \App\Models\LoanProduct::find($productId);
                if ($product) {
                    $loanQuery->where(function ($q) use ($product) {
                        $q->where('loan_code', $product->loan_code)
                          ->orWhereHas('loanApplication', function ($sq) use ($product) {
                              $sq->where('loan_code', $product->loan_code);
                          });
                    });
                }
            }

            if ($request->has('loan_mode') && $request->loan_mode != '' && $request->loan_mode != 'all') {
                $loanMode = $request->loan_mode;
                if ($loanMode === 'emi') {
                    $loanQuery->where(function($sq) {
                        $sq->where('loan_mode', 'emi')
                          ->orWhereNull('loan_mode');
                    });
                } else {
                    $loanQuery->where('loan_mode', $loanMode);
                }
            }

            if ($request->filled('from_date') || $request->filled('to_date')) {
                $from = $request->filled('from_date') ? $this->parseFilterDate($request->input('from_date'))?->startOfDay() : null;
                $to = $request->filled('to_date') ? $this->parseFilterDate($request->input('to_date'))?->endOfDay() : null;
                
                $loanQuery->whereHas('emis', function($eq) use ($from, $to) {
                    if ($from) $eq->where('due_date', '>=', $from);
                    if ($to) $eq->where('due_date', '<=', $to);
                });
            }

            if (!empty($request->input('search.value'))) {
                $search = $request->input('search.value');
                $loanQuery->where(function ($q) use ($search) {
                    $q->where('account_number', 'LIKE', "%{$search}%")
                      ->orWhere('application_number', 'LIKE', "%{$search}%")
                      ->orWhereHas('client', function($cq) use ($search) {
                          $cq->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%");
                      });
                });
            }

            $totalFiltered = $loanQuery->count();
            $totalData = LoanAccount::whereHas('emis')->count();

            $loanQuery->orderBy('id', 'desc');
            if ($limit !== null && $limit !== '' && $limit != -1) {
                $loanQuery->offset((int)$start)->limit((int)$limit);
            }

            $loanAccounts = $loanQuery->get();

            $data = $loanAccounts->map(function ($account, $index) use ($start, $companyMobile, $today) {
                $client = $account->client;
                $emis = $account->emis;

                $cOverdue = $emis->filter(fn($e) => $e->status !== 'paid' && $e->due_date && $e->due_date->lt($today))->count();
                $cPending = $emis->filter(fn($e) => $e->status === 'pending' && $e->due_date && $e->due_date->gte($today))->count();
                $cPartial = $emis->filter(fn($e) => $e->status === 'partial' && $e->due_date && $e->due_date->gte($today))->count();
                $cPaid = $emis->filter(fn($e) => $e->status === 'paid')->count();
                $cTotal = $emis->count();

                $statusBadgeCombined = sprintf(
                    '<div class="d-flex flex-wrap gap-1">' .
                    '<span class="badge bg-label-danger py-1 px-2" title="Overdue EMIs">%d Overdue</span>' .
                    '<span class="badge bg-label-warning text-dark py-1 px-2" title="Pending EMIs">%d Pending</span>' .
                    '<span class="badge bg-label-info py-1 px-2" title="Partial Paid EMIs">%d Partial</span>' .
                    '<span class="badge bg-label-success py-1 px-2" title="Paid EMIs">%d Paid</span>' .
                    '<span class="badge bg-label-primary py-1 px-2" title="Total EMIs">%d Total</span>' .
                    '</div>',
                    $cOverdue,
                    $cPending,
                    $cPartial,
                    $cPaid,
                    $cTotal
                );

                $firstEmi = $emis->first();
                $earliestDue = $emis->where('status', '!=', 'paid')->sortBy('due_date')->first();
                $loanAmount = $account->loan_amount ?? optional($account->loanApplication)->loan_amount ?? 0;

                return [
                    'id' => $firstEmi ? $firstEmi->getRouteKey() : $account->getRouteKey(),
                    'sno' => $start + $index + 1,
                    'loan_account_id' => $account->getRouteKey(),
                    'account_number' => $account->account_number ?? $account->application_number ?? 'N/A',
                    'application_number' => $account->application_number ?? null,
                    'client_name' => $client->client_name ?? 'N/A',
                    'client_phone' => $client->client_phone ?? 'N/A',
                    'zone' => optional($client)->location ? $client->location->name : 'N/A',
                    'agent_name' => optional($client)->agent ? $client->agent->agent_name : 'Unassigned',
                    'loan_amount' => $loanAmount,
                    'loan_amount_formatted' => '₹' . number_format($loanAmount, 0),
                    'instalment_number' => $cTotal,
                    'principal_amount' => $emis->sum('principal_amount'),
                    'principal_amount_formatted' => '₹' . number_format($emis->sum('principal_amount'), 2),
                    'interest_amount' => $emis->sum('interest_amount'),
                    'interest_amount_formatted' => '₹' . number_format($emis->sum('interest_amount'), 2),
                    'total_amount' => $emis->sum('total_amount'),
                    'total_amount_formatted' => '₹' . number_format($emis->sum('total_amount'), 2),
                    'due_date' => $earliestDue && $earliestDue->due_date ? $earliestDue->due_date->format('d-m-Y') : ($firstEmi && $firstEmi->due_date ? $firstEmi->due_date->format('d-m-Y') : 'N/A'),
                    'paid_amount' => '<span class="fw-bold">₹' . number_format($emis->sum('paid_amount'), 2) . '</span>',
                    'paid_amount_raw' => $emis->sum('paid_amount'),
                    'paid_date_formatted' => null,
                    'company_phone' => $companyMobile,
                    'status' => 'all',
                    'status_badge' => $statusBadgeCombined,
                    'status_meta' => ['label' => 'All', 'color' => 'primary'],
                ];
            });

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => intval($totalData),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
                'stats' => $dynamicStats,
            ]);
        }

        // Now apply status filter for specific status tabs
        if ($status === 'paid') {
            $query->where('status', 'paid');
        } else {
            // Apply the active EMI restriction
            $query->where(function ($q) use ($today) {
                $q->where(function ($sq) use ($today) {
                    $sq->where('due_date', '<', $today)
                       ->where('status', '!=', 'paid');
                })
                ->orWhereIn('emis.id', function ($subQuery) use ($today) {
                    $subQuery->select(DB::raw('MIN(e2.id)'))
                        ->from('emis as e2')
                        ->where('e2.status', '!=', 'paid')
                        ->where('e2.due_date', '>=', $today)
                        ->groupBy('e2.loan_account_id');
                });
            });

            if ($status === 'overdue') {
                $query->overdue();
            } else if ($status === 'pending') {
                $query->where('status', 'pending')->where('due_date', '>=', $today);
            } else if ($status === 'partial') {
                $query->where('status', 'partial')->where('due_date', '>=', $today);
            }
        }

        // Get total counts for Datatable
        $totalFiltered = $query->count();
        $totalData = ($status === 'paid') ? $paidCount : ($overdueCount + $pendingCount + $partialCount);

        // DataTables parameters
        $limit = $request->input('length');
        $start = $request->input('start');
        
        // Dynamic map of Datatable column index to valid database columns on 'emis' table to prevent crashes
        $columnsMap = [
            0 => 'id',
            1 => 'id',
            2 => 'loan_account_id',
            3 => 'due_date',
            4 => 'id',
            5 => 'id',
            6 => 'id',
            7 => 'due_date',
            8 => 'total_amount',
            9 => 'status',
            10 => 'id',
        ];
        $orderColIndex = $request->input('order.0.column', 2);
        $order = $columnsMap[$orderColIndex] ?? 'due_date';
        $dir = $request->input('order.0.dir') ?? 'asc';

        // Apply pagination and ordering
        $emis = $query->with(['loanAccount.emis'])
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        // Format data for DataTable
        $data = $emis->map(function ($emi, $index) use ($start, $totalFiltered, $companyMobile, $today) {
            $loanAccount = $emi->loanAccount;
            $loanApplication = optional($loanAccount)->loanApplication;
            $client = optional($loanAccount)->client ?? optional($loanApplication)->client;
            $loanAmount = $loanApplication->loan_amount
                ?? optional($loanAccount)->loan_amount
                ?? 0;
            $applicationNumber = $emi->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number
                ?? null;

            $displayStatus = $emi->status;
            if ($emi->status !== 'paid' && $emi->due_date && $emi->due_date->lt($today)) {
                $displayStatus = 'overdue';
            }

            // Client-wise EMI status count breakdown for this loan account
            $loanEmis = optional($loanAccount)->emis ?? collect();
            $cOverdue = $loanEmis->filter(fn($e) => $e->status !== 'paid' && $e->due_date && $e->due_date->lt($today))->count();
            $cPending = $loanEmis->filter(fn($e) => $e->status === 'pending' && $e->due_date && $e->due_date->gte($today))->count();
            $cPartial = $loanEmis->filter(fn($e) => $e->status === 'partial' && $e->due_date && $e->due_date->gte($today))->count();
            $cPaid = $loanEmis->filter(fn($e) => $e->status === 'paid')->count();

            $singleBadge = $this->getStatusBadge($displayStatus);

            $breakdownBadges = sprintf(
                '<div class="d-flex flex-wrap gap-1 mt-1" style="font-size: 10px;">' .
                '<span class="badge bg-label-danger py-0 px-1" title="Overdue EMIs">%d Overdue</span>' .
                '<span class="badge bg-label-warning text-dark py-0 px-1" title="Pending EMIs">%d Pending</span>' .
                '<span class="badge bg-label-info py-0 px-1" title="Partial Paid EMIs">%d Partial</span>' .
                '<span class="badge bg-label-success py-0 px-1" title="Paid EMIs">%d Paid</span>' .
                '</div>',
                $cOverdue,
                $cPending,
                $cPartial,
                $cPaid
            );

            $statusBadgeCombined = '<div class="d-flex flex-column gap-1">' . $singleBadge . $breakdownBadges . '</div>';

            return [
                'id' => $emi->getRouteKey(),
                'sno' => $start + $index + 1,
                'loan_account_id' => $loanAccount ? $loanAccount->getRouteKey() : null,
                'account_number' => $loanAccount->account_number ?? 'N/A',
                'application_number' => $applicationNumber,
                'client_name' => $client->client_name ?? 'N/A',
                'client_phone' => $client->client_phone ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'agent_name' => $emi->activeAssignment && $emi->activeAssignment->agent ? $emi->activeAssignment->agent->agent_name : 'Unassigned',
                'loan_amount' => $loanAmount,
                'loan_amount_formatted' => '₹' . number_format($loanAmount, 0),
                'instalment_number' => $emi->instalment_number,
                'principal_amount' => $emi->principal_amount,
                'principal_amount_formatted' => '₹' . number_format($emi->principal_amount, 2),
                'interest_amount' => $emi->interest_amount,
                'interest_amount_formatted' => '₹' . number_format($emi->interest_amount, 2),
                'total_amount' => $emi->total_amount,
                'total_amount_formatted' => '₹' . number_format($emi->total_amount, 2),
                'due_date' => $emi->due_date->format('d-m-Y'),
                'paid_amount' => '<span class="fw-bold">' . ($emi->paid_amount > 0 ? '₹' . number_format($emi->paid_amount, 2) : '-') . '</span>' . ($emi->collections && $emi->collections->count() > 0 ? ' <i class="fa fa-info-circle"></i>' : ''),
                'paid_amount_raw' => $emi->paid_amount,
                'paid_date_formatted' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : null,
                'company_phone' => $companyMobile,
                'status' => $displayStatus,
                'status_badge' => $statusBadgeCombined,
                'status_meta' => $this->getStatusMeta($displayStatus),
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
            'stats' => $dynamicStats,
        ]);
    }

    /**
     * View detailed EMI schedule for a loan application
     */
    public function view($applicationNumber): View
    {
        $loanAccount = LoanAccount::with(['loanApplication.client', 'loanApplication.product'])
            ->where('application_number', $applicationNumber)
            ->firstOrFail();

        // Security Check: Agents can only view their assigned clients
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if (!$agentId || optional($loanAccount->loanApplication->client)->assigned_to != $agentId) {
                abort(403, 'Unauthorized access to this loan account.');
            }
        }

        $loanApplication = $loanAccount->loanApplication;

        // Ensure EMI balances are synchronized with the new non-cumulative logic
        $paymentService = app(\App\Services\LoanPaymentService::class);
        $paymentService->syncEmiBalances($loanAccount->id);
        $paymentService->syncLoanTotals($loanAccount->id);
        $loanAccount->refresh();

        $emis = $loanAccount->emis()
            ->orderBy('instalment_number')
            ->get();

        // Get the global first unpaid instalment number
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        if ($isKandhuvatti) {
            $firstUnpaid = $emis->filter(function($emi) {
                if ($emi->status === 'paid') return false;
                $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                $netPending = max(0, $emi->pending_amount - $inProgressSum);
                return $netPending > 0;
            })->sortBy('instalment_number')->first();
        } else {
            // Skip EMIs fully covered by in_progress (pending approval) collections
            $firstUnpaid = $emis->whereIn('status', ['pending', 'overdue', 'partial'])->filter(function($e) {
                $inProg = $e->collections ? $e->collections->where('status', 'in_progress')->sum('amount') : 0;
                return ($e->pending_amount - $inProg) > 0;
            })->sortBy('instalment_number')->first();
        }
        $firstUnpaidInstalment = $firstUnpaid ? $firstUnpaid->instalment_number : 999999;

        // Calculate summary
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        if ($isKandhuvatti) {
            $principalPaid = $emis->sum('principal_amount');
            $interestPaid = max(0, $loanAccount->paid_amount - $principalPaid);
            $outstanding = max(0, (float)$loanAccount->loan_amount - $principalPaid);
            
            $summary = [
                'account_number' => $loanAccount->account_number ?? $loanAccount->application_number,
                'loan_amount' => $loanApplication->loan_amount,
                'interest_rate' => $loanApplication->interest_rate,
                'tenure' => 0,
                'total_payable' => 0,
                'paid_amount' => $loanAccount->paid_amount,
                'outstanding' => $outstanding,
                'status' => $loanAccount->status,
                'total_emis' => $emis->count(),
                'paid_emis' => $emis->where('status', 'paid')->count(),
                'pending_emis' => $emis->where('status', 'pending')->count(),
                'overdue_emis' => $emis->filter(fn ($e) => $e->due_date
                    && $e->due_date->lt(now()->startOfDay())
                    && in_array($e->status, ['pending', 'overdue', 'partial'], true)
                    && (float) $e->pending_amount > 0)->count(),
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
            ];
        } else {
            $totalPayable = (float)($loanAccount->total_payable ?? $emis->sum('total_amount'));
            $paidAmount = (float)($emis->sum('paid_amount'));
            
            $principalPaid = $emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return (float)($emi->principal_amount ?? 0);
                return max(0, $alreadyPaid - $interestPart);
            });
            
            $interestPaid = $emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return $interestPart;
                return min($alreadyPaid, $interestPart);
            });

            $isReducing = $loanApplication->product && in_array($loanApplication->product->interest_type, ['reducing', 'declining_balance']);
            if ($isReducing) {
                $outstanding = max(0, (float)$loanApplication->loan_amount - $principalPaid);
            } else {
                $outstanding = max(0, $totalPayable - $paidAmount);
            }

            $summary = [
                'account_number' => $loanAccount->account_number ?? $loanAccount->application_number,
                'loan_amount' => $loanApplication->loan_amount,
                'interest_rate' => $loanApplication->interest_rate,
                'tenure' => $loanApplication->tenure_months,
                'total_payable' => $totalPayable,
                'paid_amount' => $paidAmount,
                'outstanding' => $outstanding,
                'status' => $loanAccount->status,
                'total_emis' => $emis->count(),
                'paid_emis' => $emis->where('status', 'paid')->count(),
                'pending_emis' => $emis->where('status', 'pending')->count(),
                'overdue_emis' => $emis->filter(fn ($e) => $e->due_date
                    && $e->due_date->lt(now()->startOfDay())
                    && in_array($e->status, ['pending', 'overdue', 'partial'], true)
                    && (float) $e->pending_amount > 0)->count(),
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'principal_outstanding' => max(0, (float)$loanApplication->loan_amount - $principalPaid),
                'next_emi_due_date' => $loanAccount->status === 'closed' ? 'Closed' : ($firstUnpaid ? $firstUnpaid->due_date->format('d-m-Y') : 'N/A'),
                'is_reducing' => $isReducing,
            ];
        }

        return view('admin.emi-repayments.emi-details', compact('loanAccount', 'loanApplication', 'emis', 'summary', 'firstUnpaidInstalment'));
    }

    /**
     * Get a specific EMI detail record
     */
    public function show($emiId): JsonResponse
    {
        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
        ])
            ->findOrFail($emiId);

        $statusMeta = $this->getStatusMeta($emi->status);

        $loanAccount = $emi->loanAccount;
        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;
        $loanAmount = $loanApplication->loan_amount
            ?? optional($loanAccount)->loan_amount
            ?? null;
        $applicationNumber = $emi->application_number
            ?? optional($loanAccount)->application_number
            ?? optional($loanApplication)->application_number;
        $penaltyAmount = $emi->penalty_amount ? number_format($emi->penalty_amount, 2) : null;
        $penaltyDate = $emi->last_penalty_date ? Carbon::parse($emi->last_penalty_date)->format('d-m-Y') : null;

        return response()->json([
            'success' => true,
            'emi' => [
                'id' => $emi->getRouteKey(),
                'application_number' => $applicationNumber,
                'instalment_number' => $emi->instalment_number,
                'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : null,
                'principal_amount' => number_format($emi->principal_amount, 2),
                'interest_amount' => number_format($emi->interest_amount, 2),
                'total_amount' => number_format($emi->total_amount, 2),
                'status' => $emi->status,
                'status_label' => $statusMeta['label'],
                'status_color' => $statusMeta['color'],
                'paid_amount' => $emi->paid_amount ? number_format($emi->paid_amount, 2) : null,
                'paid_date' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : null,
                'payment_method' => $emi->payment_method ? ucfirst(str_replace('_', ' ', $emi->payment_method)) : null,
                'payment_reference' => $emi->payment_reference ?: null,
                'remarks' => $emi->remarks ?: null,
                'penalty_amount' => $penaltyAmount,
                'penalty_date' => $penaltyDate,
            ],
            'loan' => [
                'product' => $product->loan_name ?? null,
                'client_name' => $client->client_name ?? null,
                'loan_amount' => $loanAmount ? number_format($loanAmount, 0) : null,
            ],
            'is_admin' => auth()->user()->hasRole('Admin'),
        ]);
    }

    /**
     * Display payment receipts index
     */
    public function receiptsIndex(): View
    {
        $currentUser = auth()->user();
        $isAgent = $currentUser->hasRole('Agent');
        $agentId = $isAgent ? optional($currentUser->agent)->id : null;

        // Base queries matching getReceiptsData exactly!
        $totalQuery = Emi::whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0);

        $monthQuery = Emi::whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0)
            ->whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year);

        $todayQuery = Emi::whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0)
            ->whereDate('paid_date', now()->toDateString());

        // Filter by agent if applicable
        if ($isAgent && $agentId) {
            $totalQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            });
            $monthQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            });
            $todayQuery->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            });
        }

        // Calculate statistics
        $stats = [
            'total_receipts' => $totalQuery->count(),
            'total_collected' => $totalQuery->sum('paid_amount'),
            'month_collected' => $monthQuery->sum('paid_amount'),
            'today_collected' => $todayQuery->sum('paid_amount'),
        ];

        return view('admin.emi-repayments.repayment-receipts', compact('stats'));
    }

    /**
     * Get receipts data for DataTable
     */
    public function getReceiptsData(Request $request): JsonResponse
    {
        $query = Emi::with(['loanAccount.loanApplication.client.location'])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['paid', 'partial'])
            ->where('paid_amount', '>', 0);

        // Filter by agent if current user is an agent
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $query->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                    $q->where('assigned_to', $agentId);
                });
            }
        }

        // Filter by payment method if provided
        if ($request->has('payment_method') && $request->payment_method != '') {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by application number if provided
        if ($request->has('application_number') && !empty($request->application_number)) {
            $query->whereHas('loanAccount', function($q) use ($request) {
                $q->where('application_number', $request->application_number);
            });
        }

        $totalFiltered = $query->count();
        $receipts = $query->orderByDesc('paid_date')->get();

        // Format data for DataTable
        $data = $receipts->map(function ($emi, $index) use ($totalFiltered) {
            $loanAccount = $emi->loanAccount;
            $loanApplication = optional($loanAccount)->loanApplication;
            $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
            $applicationNumber = $emi->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number;

            return [
                'id' => $emi->getRouteKey(),
                'sno' => $totalFiltered - $index,
                'receipt_number' => 'RCP-' . str_pad($emi->id, 6, '0', STR_PAD_LEFT),
                'client_name' => $client->client_name ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'application_number' => $applicationNumber,
                'paid_amount' => $emi->paid_amount,
                'paid_amount_formatted' => '₹' . number_format($emi->paid_amount, 2),
                'payment_method' => ucfirst(str_replace('_', ' ', $emi->payment_method ?? 'N/A')),
                'paid_date' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : 'N/A',
                'payment_reference' => $emi->payment_reference ?: 'N/A',
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Get pending EMIs for receipt creation
     */
    public function getPendingEmis(): JsonResponse
    {
        $query = Emi::with(['loanAccount.loanApplication.client'])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        // Filter by agent if current user is an agent
        $currentUser = auth()->user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $query->whereHas('loanAccount.loanApplication.client', function($q) use ($agentId) {
                    $q->where('assigned_to', $agentId);
                });
            }
        }

        $pendingEmis = $query->orderBy('due_date')
            ->get()
            ->map(function ($emi) {
                $loanAccount = $emi->loanAccount;
                $loanApplication = optional($loanAccount)->loanApplication;
                $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
                $applicationNumber = $emi->application_number
                    ?? optional($loanAccount)->application_number
                    ?? optional($loanApplication)->application_number;

                return [
                    'id' => $emi->getRouteKey(),
                    'text' => ($applicationNumber ?? 'N/A') . ' - EMI #' . $emi->instalment_number . ' (₹' . number_format($emi->total_amount, 2) . ')',
                    'client_name' => $client->client_name ?? 'N/A',
                    'application_number' => $applicationNumber,
                    'pending_amount_display' => ($emi->status === 'partial' || ($emi->status === 'paid' && $emi->pending_amount > 0)) ? '<span class="text-danger fw-bold">₹' . number_format($emi->pending_amount, 2) . '</span>' : '-',
                    'emi_amount' => $emi->total_amount,
                    'emi_amount_formatted' => '₹' . number_format($emi->total_amount, 2),
                ];
            });

        return response()->json($pendingEmis);
    }

    /**
     * Create payment receipt
     */
    public function createReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'emi_id' => 'required', // exists check handled by service or manual check
            'paid_amount' => 'required|numeric|min:0',
            'principal_amount' => 'nullable|numeric|min:0',
            'paid_date' => 'required|date',
            'payment_method' => 'required',
            'payment_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        $emiId = $request->emi_id;
        $decodedEmiId = HashId::decode($emiId);
        $decodedEmiId = is_array($decodedEmiId) ? ($decodedEmiId[0] ?? $emiId) : ($decodedEmiId ?? $emiId);

        $emi = \App\Models\Emi::findOrFail($decodedEmiId);
        $pendingAmount = $emi->total_amount - $emi->paid_amount;
        if ($request->paid_amount < $pendingAmount && floor($request->paid_amount) != $request->paid_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Partial payment amount must be a whole number.'
            ], 422);
        }
        if ($request->filled('principal_amount') && floor($request->principal_amount) != $request->principal_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Principal repayment amount must be a whole number.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Use LoanPaymentService to process the payment
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $result = $paymentService->processPayment(
                $decodedEmiId,
                $request->paid_amount,
                $request->paid_date,
                $request->payment_method,
                $request->payment_reference,
                $request->remarks ?: 'Created via Receipt',
                false, // skipHistory
                $request->principal_amount ?? 0
            );

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            DB::commit();

            $emi = Emi::find($decodedEmiId);
            $isFullyPaid = ($emi->status === 'paid');
            $successMessage = $isFullyPaid ? 'EMI fully paid successfully.' : 'Partial payment recorded successfully.';

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CreateReceipt error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create receipt: ' . $e->getMessage()
            ], 500);
        }

        // Fire notification event
        event(new \App\Events\PaymentReceivedEvent($emi, $request->paid_amount));

        $loanAccount = $emi->loanAccount->fresh();
        $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
        $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile;
        }
        $remainingBalance = $loanAccount->outstanding_amount;

        $smsData = [
            'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
            'mobile_no' => $cleanMobile,
            'account_no' => $loanAccount->account_number,
            'amount_paid' => ($loanAccount->loan_mode === 'interest_only' && $request->principal_amount > 0.001 && $request->paid_amount <= 0.001) ? $request->principal_amount : $request->paid_amount,
            'remaining_balance' => $remainingBalance,
            'loan_mode' => $loanAccount->loan_mode,
            'payment_type' => ($loanAccount->loan_mode === 'interest_only' && $request->principal_amount > 0.001) ? 'principal' : (($loanAccount->loan_mode === 'interest_only') ? 'interest' : 'emi'),
            'application_number' => $loanAccount->application_number,
            'is_partial' => !$isFullyPaid,
            'emi_balance' => $emi->pending_amount,
        ];
        $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

        return response()->json([
            'success' => true,
            'message' => $successMessage ?? 'Payment recorded successfully',
            'receipt_id' => $emi->getRouteKey(),
            'sms_data' => $smsData
        ]);
    }

    /**
     * Get receipt details for viewing
     */
    public function getReceiptDetails($id): JsonResponse
    {
        $decodedId = \App\Support\HashId::decode($id) ?? $id;
        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
        ])
            ->findOrFail($decodedId);

        $loanAccount = $emi->loanAccount;
        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;

        $paymentDate = $emi->paid_date ? $emi->paid_date->format('d-m-Y h:i A') : 'N/A';
        $disbursedDate = $loanAccount && $loanAccount->disbursed_at
            ? $loanAccount->disbursed_at->format('d-m-Y h:i A')
            : ($loanApplication && $loanApplication->disbursed_at
                ? $loanApplication->disbursed_at->format('d-m-Y h:i A')
                : 'N/A');

        $principalAmount = $emi->principal_amount ?? 0;
        $interestAmount = $emi->interest_amount ?? 0;
        $totalAmount = $emi->total_amount ?? ($principalAmount + $interestAmount);
        $paidAmount = $emi->paid_amount ?? 0;
        $outstandingAmount = max($totalAmount - $paidAmount, 0);

        $penaltyAmount = $emi->penalty_amount ?? 0;
        $totalAmount = $emi->total_amount ?? ($principalAmount + $interestAmount);
        $isOverduePayment = $emi->status === 'overdue' || ($penaltyAmount > 0 && $emi->paid_date && $emi->paid_date->gt($emi->due_date));
        $overdueAmount = $isOverduePayment ? $penaltyAmount : 0;

        $receiptData = [
            'id' => $emi->getRouteKey(),
            'receipt_number' => 'RCP-' . str_pad($emi->id, 6, '0', STR_PAD_LEFT),
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $emi->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number,
            'loan_product' => $product->loan_name ?? 'N/A',
            'instalment_number' => $emi->instalment_number,
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'emi_amount' => $totalAmount,
            'overdue_amount' => $overdueAmount,
            'show_overdue' => $isOverduePayment,
            'total_amount_display' => $totalAmount + $overdueAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'payment_method' => ucfirst(str_replace('_', ' ', $emi->payment_method ?? 'N/A')),
            'payment_reference' => $emi->payment_reference ?: 'N/A',
            'paid_date' => $paymentDate,
            'disbursed_date' => $disbursedDate,
            'status' => $emi->status,
            'status_label' => $this->getStatusMeta($emi->status)['label'],
            'status_color' => $this->getStatusMeta($emi->status)['color'],
            'remarks' => $emi->remarks ?? '-',
        ];

        return response()->json($receiptData);
    }

    /**
     * Print receipt
     */
    public function printReceipt($id)
    {
        $decodedId = \App\Support\HashId::decode($id);
        
        if (!$decodedId) {
            if (is_numeric($id)) {
                $decodedId = $id;
            } else {
                Log::error("Failed to decode EMI ID for receipt print: " . $id);
                abort(404, 'Invalid EMI Receipt ID');
            }
        }

        $emi = Emi::with([
            'loanAccount.loanApplication.client',
            'loanAccount.loanApplication.product',
            'loanAccount.client',
        ])
            ->findOrFail($decodedId);

        $loanAccount = $emi->loanAccount;
        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;

        $paymentDate = $emi->paid_date ? $emi->paid_date->format('d-m-Y h:i A') : 'N/A';
        $disbursedDate = $loanAccount && $loanAccount->disbursed_at
            ? $loanAccount->disbursed_at->format('d-m-Y h:i A')
            : ($loanApplication && $loanApplication->disbursed_at
                ? $loanApplication->disbursed_at->format('d-m-Y h:i A')
                : 'N/A');

        $principalAmount = $emi->principal_amount ?? 0;
        $interestAmount = $emi->interest_amount ?? 0;
        $totalAmount = $emi->total_amount ?? ($principalAmount + $interestAmount);
        $paidAmount = $emi->paid_amount ?? 0;
        $outstandingAmount = max($totalAmount - $paidAmount, 0);

        $penaltyAmount = $emi->penalty_amount ?? 0;
        $totalAmount = $emi->total_amount ?? ($principalAmount + $interestAmount);
        $isOverduePayment = $emi->status === 'overdue' || ($penaltyAmount > 0 && $emi->paid_date && $emi->paid_date->gt($emi->due_date));
        $overdueAmount = $isOverduePayment ? $penaltyAmount : 0;

        $receiptData = [
            'receipt_number' => 'RCP-' . str_pad($emi->id, 6, '0', STR_PAD_LEFT),
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $emi->application_number
                ?? optional($loanAccount)->application_number
                ?? optional($loanApplication)->application_number,
            'loan_product' => $product->loan_name ?? 'N/A',
            'instalment_number' => $emi->instalment_number,
            'principal_amount' => $principalAmount,
            'interest_amount' => $interestAmount,
            'emi_amount' => $totalAmount,
            'overdue_amount' => $overdueAmount,
            'show_overdue' => $isOverduePayment,
            'total_amount_display' => $totalAmount + $overdueAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'payment_method' => ucfirst(str_replace('_', ' ', $emi->payment_method ?? 'N/A')),
            'payment_reference' => $emi->payment_reference ?: 'N/A',
            'paid_date' => $paymentDate,
            'disbursed_date' => $disbursedDate,
            'status' => $emi->status,
            'status_label' => $this->getStatusMeta($emi->status)['label'],
            'status_color' => $this->getStatusMeta($emi->status)['color'],
            'remarks' => $emi->remarks ?? '-',
        ];

        return view('pdf.payment_receipt', compact('receiptData'));
    }

    public function printStatement($id)
    {
        $decodedId = \App\Support\HashId::decode($id);
        
        if (!$decodedId) {
            if (is_numeric($id)) {
                $decodedId = $id;
            } else {
                Log::error("Failed to decode EMI ID for statement print: " . $id);
                abort(404, 'Invalid EMI ID');
            }
        }

        $emi = Emi::findOrFail($decodedId);
        $loanAccount = LoanAccount::with([
            'loanApplication.client',
            'loanApplication.product',
            'client',
            'emis' => function($q) {
                $q->orderBy('instalment_number', 'asc');
            }
        ])->findOrFail($emi->loan_account_id);

        $loanApplication = optional($loanAccount)->loanApplication;
        $client = optional($loanApplication)->client ?? optional($loanAccount)->client;
        $product = optional($loanApplication)->product;

        // Calculate portions
        $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
        if ($isKandhuvatti) {
            $principalPaid = $loanAccount->emis->sum('principal_amount');
            $interestPaid = max(0, $loanAccount->paid_amount - $principalPaid);
        } else {
            $principalPaid = $loanAccount->emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return (float)($emi->principal_amount ?? 0);
                return max(0, $alreadyPaid - $interestPart);
            });
            $interestPaid = $loanAccount->emis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return $interestPart;
                return min($alreadyPaid, $interestPart);
            });
        }

        $statementData = [
            'statement_number' => 'STMT-' . str_pad($loanAccount->id, 6, '0', STR_PAD_LEFT),
            'client_name' => $client->client_name ?? 'N/A',
            'application_number' => $loanAccount->application_number,
            'loan_product' => $product->loan_name ?? 'N/A',
            'loan_amount' => $loanAccount->loan_amount,
            'interest_rate' => $loanAccount->interest_rate,
            'tenure' => $loanAccount->tenure,
            'loan_mode' => $loanAccount->loan_mode,
            'paid_amount' => $loanAccount->paid_amount,
            'outstanding_amount' => $loanAccount->outstanding_amount,
            'status' => $loanAccount->status,
            'principal_paid' => $principalPaid,
            'interest_paid' => $interestPaid,
            'disbursed_date' => $loanAccount->disbursed_at ? $loanAccount->disbursed_at->format('d-m-Y') : 'N/A',
            'emis' => $loanAccount->emis
        ];

        return view('pdf.payment_statement', compact('statementData'));
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status): string
    {
        $meta = $this->getStatusMeta($status);
        return sprintf('<span class="badge bg-label-%s">%s</span>', $meta['color'], $meta['label']);
    }

    private function getStatusMeta($status): array
    {
        $status = strtolower($status);
        $map = [
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            'partial' => ['label' => 'Partial', 'color' => 'info'],
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
        ];

        return $map[$status] ?? ['label' => 'Unknown', 'color' => 'secondary'];
    }

    private function primaryLoanAccountIdsSubquery()
    {
        return LoanAccount::selectRaw('MAX(id)')
            ->groupBy('loan_application_id');
    }

    /**
     * Process partial payment for an EMI
     */
    public function processPartialPayment(Request $request): JsonResponse
    {
        // Decode hashed IDs if present
        if ($request->has('loan_account_id')) {
            $decoded = \App\Support\HashId::decode($request->loan_account_id);
            $request->merge([
                'loan_account_id' => is_array($decoded) ? ($decoded[0] ?? $request->loan_account_id) : ($decoded ?? $request->loan_account_id)
            ]);
        }
        if ($request->has('emi_id') && !empty($request->emi_id)) {
            $decoded = \App\Support\HashId::decode($request->emi_id);
            $request->merge([
                'emi_id' => is_array($decoded) ? ($decoded[0] ?? $request->emi_id) : ($decoded ?? $request->emi_id)
            ]);
        }

        $validated = $request->validate([
            'loan_account_id' => 'required|exists:loan_accounts,id',
            'emi_id'          => 'nullable|exists:emis,id',
            'partial_amount'  => 'nullable|numeric|min:0',
            'principal_amount'=> 'nullable|numeric|min:0',
            'payment_date'    => 'required|date',
            'payment_method'  => 'required|string',
            'payment_reference'=> 'nullable|string'
        ]);

        if ($request->filled('partial_amount') && floor($request->partial_amount) != $request->partial_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Interest payment amount must be a whole number.'
            ], 422);
        }
        if ($request->filled('principal_amount') && floor($request->principal_amount) != $request->principal_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Principal repayment amount must be a whole number.'
            ], 422);
        }

        $interestAmount = (float)($validated['partial_amount'] ?? 0);
        $principalAmount = (float)($validated['principal_amount'] ?? 0);

        if ($interestAmount <= 0.001 && $principalAmount <= 0.001) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter either a partial interest amount or a principal repayment amount.'
            ], 422);
        }

        if ($interestAmount > 0.001 && $principalAmount > 0.001) {
            return response()->json([
                'success' => false,
                'message' => 'Only one payment type (Interest OR Principal) is allowed in a single transaction. Please pay only one.'
            ], 422);
        }

        try {
            $partialService = app(\App\Services\PartialPaymentConfigService::class);
            if (!$partialService->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partial payments are disabled. Enable them in Loan Configuration.',
                ], 422);
            }

            $loanAccount = LoanAccount::findOrFail($validated['loan_account_id']);
            
            // If specific EMI ID is provided, check for prior unpaid EMIs
            if (!empty($validated['emi_id'])) {
                $emi = Emi::findOrFail($validated['emi_id']);
                if ((int) $emi->loan_account_id !== (int) $loanAccount->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected EMI ID is invalid for the given loan account.'
                    ], 422);
                }
                
                $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
                $partialService = app(\App\Services\PartialPaymentConfigService::class);
                $payAmount = $interestAmount > 0.001 ? $interestAmount : $principalAmount;

                if ($isKandhuvatti) {
                    $pendingAmount = $partialService->getOutstandingDueAmount($emi, $loanAccount);
                    if ($principalAmount > 0.001) {
                        // Principal payment for Kandhuvatti
                        if ($principalAmount > ($loanAccount->outstanding_amount + 0.01)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Principal repayment amount cannot exceed the remaining outstanding loan principal (₹' . number_format($loanAccount->outstanding_amount, 2) . ').'
                            ], 422);
                        }
                    } else {
                        // Interest payment for Kandhuvatti
                        $isPartialPayment = $interestAmount < ($pendingAmount - 0.01);
                        if ($isPartialPayment) {
                            if ($validationError = $partialService->validatePartialAmount($emi, $interestAmount, $loanAccount)) {
                                return response()->json([
                                    'success' => false,
                                    'message' => $validationError,
                                ], 422);
                            }
                        } else {
                            if ($interestAmount > ($pendingAmount + 0.01)) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Interest payment cannot exceed the pending interest due (₹' . number_format($pendingAmount, 2) . ').'
                                ], 422);
                            }
                        }
                    }
                } else {
                    if ($validationError = $partialService->validatePartialAmount($emi, $payAmount, $loanAccount)) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationError,
                        ], 422);
                    }
                }
                
                $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
                    ->orderByDesc('instalment_number')
                    ->first();
                $isLoanMatured = ($lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now()));

                $unpaidPrior = false;
                if (!$isLoanMatured) {
                    if ($isKandhuvatti) {
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->where(function($q) {
                                $q->whereRaw('pending_amount - 0 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                            })
                            ->exists();
                    } else {
                        // Also ignore EMIs fully covered by pending (in_progress) agent collections
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->where(function($q) {
                                $q->whereRaw('pending_amount - 0 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                            })
                            ->exists();
                    }
                }

                if ($unpaidPrior) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please clear previous pending EMIs before paying for this instalment.'
                    ], 400);
                }

                // 3-Day Lock Logic (only applies to 'pending' EMIs; 'partial' or 'overdue' are always unlocked; never applies to open loans/Kandhuvatti)
                if ($emi->status === 'pending' && !$isKandhuvatti) {
                    $threeDaysBefore = now()->addDays(3);
                    $isWithinWindow = $emi->due_date && $emi->due_date <= $threeDaysBefore;
                    $isPreviousPaid = ($emi->instalment_number > 1) && !$unpaidPrior;

                    if (!$isWithinWindow && !$isPreviousPaid) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This EMI is currently locked. It will be available 3 days before the due date (' . $emi->due_date->format('d-m-Y') . ').'
                        ], 400);
                    }
                }
            }

            $paymentService = new \App\Services\LoanPaymentService();
            $currentUser    = auth()->user();
            $isAgent        = $currentUser->hasRole('Agent');

            // ── AGENT PATH: create pending collection for admin approval ───────────────
            if ($isAgent) {
                $agentId   = optional($currentUser->agent)->id;
                $targetEmi = !empty($validated['emi_id']) ? Emi::findOrFail($validated['emi_id']) : null;
                $payAmount = $interestAmount > 0 ? $interestAmount : $principalAmount;

                DB::beginTransaction();
                try {
                    $emiForCollection = $targetEmi ?? $loanAccount->emis()
                        ->whereIn('status', ['pending','overdue','partial'])
                        ->orderBy('instalment_number')
                        ->first();

                    if (!$emiForCollection) {
                        throw new \Exception('No pending EMI found for this loan.');
                    }

                    $pendingAmt  = max(0, $emiForCollection->pending_amount);
                    $paymentType = ($payAmount >= ($pendingAmt - 0)) ? 'full' : 'partial';

                    $existing = \App\Models\EmiCollection::where('emi_id', $emiForCollection->id)
                        ->where('status', 'in_progress')
                        ->first();

                    if ($existing) {
                        $newAmt    = $existing->amount + $payAmount;
                        $isNowFull = ($newAmt >= ($pendingAmt - 0));
                        $existing->update([
                            'amount'         => $newAmt,
                            'payment_type'   => $isNowFull ? 'full' : 'partial',
                            'payment_method' => $validated['payment_method'],
                            'collected_at'   => $validated['payment_date'],
                            'remarks'        => trim(($existing->remarks ?? '') . "\n[Agent Updated via Partial Modal]"),
                        ]);
                    } else {
                        \App\Models\EmiCollection::create([
                            'agent_id'          => $agentId,
                            'emi_id'            => $emiForCollection->id,
                            'amount'            => $payAmount,
                            'payment_method'    => $validated['payment_method'],
                            'payment_type'      => $paymentType,
                            'payment_reference' => $validated['payment_reference'] ?? null,
                            'status'            => 'in_progress',
                            'collected_at'      => $validated['payment_date'],
                            'remarks'           => '[Agent Created via Partial Modal]',
                        ]);
                    }

                    if ($agentId) {
                        \App\Models\AgentActivity::create([
                            'emi_id'      => $emiForCollection->id,
                            'agent_id'    => $agentId,
                            'type'        => 'payment',
                            'description' => '₹' . number_format($payAmount, 2),
                            'method'      => strtoupper(str_replace('_', ' ', $validated['payment_method'])),
                            'reference'   => $validated['payment_reference'] ?? null,
                            'remarks'     => null,
                            'action_at'   => $validated['payment_date'],
                        ]);
                    }

                    \App\Models\EmiAgentAssignment::where('emi_id', $emiForCollection->id)
                        ->whereIn('status', ['assigned', 'visited'])
                        ->update(['status' => 'resolved', 'resolved_at' => now()]);

                    DB::commit();
                } catch (\Exception $ex) {
                    DB::rollBack();
                    throw $ex;
                }

                // Let's generate the SMS/WhatsApp payload identical to Admin flow
                $loanAccount->refresh();
                $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
                $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                if (strlen($cleanMobile) === 10) {
                    $cleanMobile = '91' . $cleanMobile;
                }

                // For agent (pending) submissions, processPayment has NOT run yet, so
                // outstanding_amount is still the pre-payment value. Estimate expected
                // post-payment balance so the SMS shows the correct anticipated balance.
                $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
                $remainingBalance = $loanAccount->outstanding_amount;
                if ($isKandhuvatti) {
                    if (($validated['payment_type'] ?? '') === 'principal') {
                        $remainingBalance = max(0, $remainingBalance - $payAmount);
                    }
                } else {
                    $isReducing = $loanAccount->loanApplication && $loanAccount->loanApplication->product
                        && in_array($loanAccount->loanApplication->product->interest_type, ['reducing', 'declining_balance']);
                    if ($isReducing) {
                        $interestPart = (float)($emiForCollection->interest_amount ?? 0);
                        $alreadyPaid  = (float)($emiForCollection->paid_amount ?? 0);
                        $unpaidInterest = max(0, $interestPart - min($alreadyPaid, $interestPart));
                        $principalPaidHere = max(0, $payAmount - $unpaidInterest);
                        $remainingBalance = max(0, $remainingBalance - $principalPaidHere);
                    } else {
                        $remainingBalance = max(0, $remainingBalance - $payAmount);
                    }
                }

                $isFullyPaid = ($payAmount >= ($pendingAmt - 0.01));
                $emiBalance = max(0, $pendingAmt - $payAmount);

                $smsData = [
                    'client_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: ($client->client_name ?? 'Client'),
                    'mobile_no' => $cleanMobile,
                    'account_no' => $loanAccount->account_number,
                    'amount_paid' => $payAmount,
                    'remaining_balance' => $remainingBalance,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => ($isKandhuvatti && $request->payment_type === 'principal') ? 'principal' : (($isKandhuvatti) ? 'interest' : 'emi'),
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => !$isFullyPaid,
                    'emi_balance' => $emiBalance,
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

                return response()->json([
                    'success'          => true,
                    'message'          => 'Payment submitted successfully and is awaiting Admin approval.',
                    'pending_approval' => true,
                    'sms_data'         => $smsData
                ]);
            }

            // ── ADMIN / STAFF PATH: process immediately ───────────────────────────────
            if (!empty($validated['emi_id'])) {
                $result = $paymentService->processPayment(
                    $validated['emi_id'],
                    $interestAmount,
                    $validated['payment_date'],
                    $validated['payment_method'],
                    $validated['payment_reference'] ?? null,
                    'Partial payment processed via targeted modal',
                    false,
                    $principalAmount
                );
            } else {
                $result = $paymentService->processPartialPayment(
                    $loanAccount->id,
                    $interestAmount,
                    $validated['payment_date'],
                    $validated['payment_method'],
                    $validated['payment_reference'] ?? null,
                    'Partial payment processed via cascading logic'
                );
            }
            
            if ($result['success']) {
                $actualPaid = $isKandhuvatti ? ($interestAmount + $principalAmount) : ($interestAmount > 0.001 ? $interestAmount : $principalAmount);
                $this->sendPartialPaymentEmail($loanAccount, $actualPaid, $validated['payment_date'], $validated['payment_method']);
                
                $msg = 'Partial payment processed successfully';
                if ($isKandhuvatti) {
                    if ($principalAmount > 0.001) {
                        $msg = 'Principal payment processed successfully';
                    } else {
                        $msg = 'Interest payment processed successfully';
                    }
                }

                $loanAccount->refresh();
                $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
                $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                if (strlen($cleanMobile) === 10) {
                    $cleanMobile = '91' . $cleanMobile;
                }
                $remainingBalance = $loanAccount->outstanding_amount;

                $paidEmi = !empty($validated['emi_id']) 
                    ? Emi::find($validated['emi_id']) 
                    : $loanAccount->emis()->whereIn('status', ['pending','overdue','partial'])->orderBy('instalment_number')->first();
                $emiBalance = $paidEmi ? $paidEmi->fresh()->pending_amount : 0;
                $isFullyPaid = $paidEmi ? ($paidEmi->fresh()->status === 'paid') : false;

                $smsData = [
                    'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                    'mobile_no' => $cleanMobile,
                    'account_no' => $loanAccount->account_number,
                    'amount_paid' => $actualPaid,
                    'remaining_balance' => $remainingBalance,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => ($isKandhuvatti && $principalAmount > 0.001) ? 'principal' : (($isKandhuvatti) ? 'interest' : 'emi'),
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => !$isFullyPaid,
                    'emi_balance' => $emiBalance,
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

                return response()->json([
                    'success'  => true,
                    'message'  => $msg,
                    'data'     => $result['data'],
                    'sms_data' => $smsData
                ]);
            }
            
            return response()->json(['success' => false, 'message' => $result['message']], 400);
            
        } catch (\Throwable $e) {
            Log::error('Partial payment error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to process payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send partial payment confirmation email
     */
    protected function sendPartialPaymentEmail($loanAccount, $paymentAmount, $paymentDate, $paymentMethod)
    {
        try {
            $template = \App\Models\EmailTemplate::where('identifier', 'partial_payment_confirmation')->first();
            
            if (!$template || !$template->status) {
                Log::warning('Partial payment email template not found or inactive');
                return;
            }

            $client = $loanAccount->loanApplication->client;
            
            // Get the most recently updated EMI (the one that was just paid)
            $emi = \App\Models\Emi::where('loan_account_id', $loanAccount->id)
                ->orderBy('updated_at', 'desc')
                ->first();
            
            if (!$emi) {
                Log::warning('No EMI found for loan account: ' . $loanAccount->id);
                return;
            }
            
            // Get next EMI
            $nextEmi = \App\Models\Emi::where('loan_account_id', $loanAccount->id)
                ->where('instalment_number', $emi->instalment_number + 1)
                ->first();
            
            // Replace placeholders
            $subject = str_replace('{{account_number}}', $loanAccount->account_number, $template->subject);
            
            $emailContent = str_replace([
                '{{client_name}}',
                '{{account_number}}',
                '{{emi_number}}',
                '{{payment_amount}}',
                '{{payment_date}}',
                '{{payment_method}}',
                '{{total_emi_amount}}',
                '{{total_paid_amount}}',
                '{{balance_remaining}}',
                '{{emi_status}}',
                '{{next_emi_due_date}}',
                '{{next_emi_amount}}',
                '{{support_email}}',
                '{{company_name}}'
            ], [
                $client->client_name,
                $loanAccount->account_number,
                $emi->instalment_number,
                number_format($paymentAmount, 2),
                \Carbon\Carbon::parse($paymentDate)->format('d-m-Y'),
                ucfirst(str_replace('_', ' ', $paymentMethod)),
                number_format($emi->total_amount, 2),
                number_format($emi->paid_amount ?? 0, 2),
                number_format($emi->balance_forward ?? 0, 2),
                ucfirst($emi->status),
                $nextEmi ? $nextEmi->due_date->format('d-m-Y') : 'N/A',
                $nextEmi ? number_format($nextEmi->total_due ?? $nextEmi->total_amount, 2) : 'N/A',
                config('mail.from.address'),
                config('app.name')
            ], $template->email_body);
            
            // Send email using default template
            Mail::send('emails.default-email-template', [
                'emailContent' => $emailContent
            ], function($message) use ($client, $subject) {
                $message->to($client->email)
                        ->subject($subject);
            });
            
            Log::info('Partial payment email sent to: ' . $client->email);
            
        } catch (\Exception $e) {
            Log::error('Failed to send partial payment email: ' . $e->getMessage());
        }
    }

    public function getCollectionHistory($emiId): JsonResponse
    {
        $emi = Emi::findOrFail($emiId);
        
        $rawCollections = EmiCollection::where('emi_id', $emi->id)
            ->with(['agent:id,agent_name', 'verifiedBy:id,name'])
            ->orderBy('created_at', 'asc')
            ->get();
            
        $interestLimit = (float)$emi->interest_amount;
        $interestRemaining = $interestLimit;
        
        $collections = $rawCollections->map(function ($collection) use (&$interestRemaining) {
            $amount = (float)$collection->amount;
            
            // Interest portion is cleared first up to the interest limit of this EMI
            $interestPaid = min($amount, $interestRemaining);
            $interestRemaining = max(0.00, $interestRemaining - $interestPaid);
            
            $principalPaid = max(0.00, $amount - $interestPaid);
            
            $approverName = 'System';
            if ($collection->agent) {
                $approverName = $collection->agent->agent_name;
            } elseif ($collection->verifiedBy) {
                $approverName = $collection->verifiedBy->name;
            }

            return [
                'id' => $collection->id,
                'amount' => '₹' . number_format($amount, 2),
                'principal_paid' => number_format($principalPaid, 2),
                'interest_paid' => number_format($interestPaid, 2),
                'raw_principal_paid' => $principalPaid,
                'raw_interest_paid' => $interestPaid,
                'method' => ucfirst(str_replace('_', ' ', $collection->payment_method)),
                'reference' => $collection->payment_reference ?: 'N/A',
                'type' => ucfirst($collection->payment_type ?? 'N/A'),
                'date' => $collection->collected_at ? $collection->collected_at->format('d-m-Y h:i A') : 'N/A',
                'agent' => $approverName,
                'remarks' => $collection->remarks ?? '-',
                'status' => ucfirst($collection->status)
            ];
        })->reverse()->values();

        return response()->json([
            'success' => true,
            'emi_number' => $emi->instalment_number,
            'total_amount' => '₹' . number_format($emi->total_amount, 2),
            'paid_amount' => '₹' . number_format($emi->paid_amount, 2),
            'collections' => $collections,
            'is_admin' => auth()->user()->hasRole('Admin'),
        ]);
    }

    /**
     * Send prepayment confirmation email
     */
    public function sendPrepaymentEmail($loanAccount, $prepaymentData)
    {
        try {
            $template = \App\Models\EmailTemplate::where('identifier', 'prepayment_confirmation')->first();
            
            if (!$template || !$template->status) {
                Log::warning('Prepayment email template not found or inactive');
                return;
            }

            $client = $loanAccount->loanApplication->client;
            
            // Replace placeholders
            $subject = str_replace('{{account_number}}', $loanAccount->account_number, $template->subject);
            


            $emailContent = str_replace([
                '{{client_name}}',
                '{{account_number}}',
                '{{prepayment_amount}}',
                '{{prepayment_charge}}',
                '{{total_paid}}',
                '{{payment_date}}',
                '{{previous_outstanding}}',
                '{{new_outstanding}}',
                '{{previous_tenure}}',
                '{{new_tenure}}',
                '{{emi_amount}}',
                '{{principal_reduced}}',
                '{{tenure_reduced}}',
                '{{interest_saved}}',
                '{{support_email}}',
                '{{company_name}}'
            ], [
                $client->first_name . ' ' . $client->last_name,
                $loanAccount->account_number,
                number_format($prepaymentData['amount'], 2),
                number_format($prepaymentData['charge'], 2),
                number_format($prepaymentData['total'], 2),
                \Carbon\Carbon::parse($prepaymentData['date'])->format('d-m-Y'),
                number_format($prepaymentData['previous_outstanding'], 2),
                number_format($loanAccount->outstanding_amount, 2),
                $prepaymentData['previous_tenure'],
                $loanAccount->tenure,
                number_format($loanAccount->emi_amount, 2),
                number_format($prepaymentData['amount'], 2),
                $prepaymentData['previous_tenure'] - $loanAccount->tenure,
                number_format($prepaymentData['interest_saved'] ?? 0, 2),
                config('mail.from.address'),
                config('app.name')
            ], $template->email_body);
            
            // Send email
            Mail::send('emails.default-email-template', [
                'emailContent' => $emailContent
            ], function($message) use ($client, $subject) {
                $message->to($client->email)
                        ->subject($subject);
            });
            
            Log::info('Prepayment email sent to: ' . $client->email);
            
        } catch (\Exception $e) {
            Log::error('Failed to send prepayment email: ' . $e->getMessage());
        }
    }

    /**
     * Get EMIs due in the next 2 days for admin reminders
     */
    public function upcomingReminders(): JsonResponse
    {
        $targetDate = Carbon::now()->addDays(2)->toDateString();
        
        $reminders = Emi::with(['loanAccount.loanApplication.client'])
            ->whereIn('loan_account_id', $this->primaryLoanAccountIdsSubquery())
            ->whereDate('due_date', $targetDate)
            ->where('status', 'pending')
            ->get()
            ->map(function($emi) {
                return [
                    'id' => $emi->id,
                    'client' => optional($emi->loanAccount->loanApplication->client)->client_name,
                    'amount' => '₹' . number_format($emi->total_amount, 2),
                    'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : 'N/A',
                    'account' => $emi->loanAccount->account_number
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $reminders->count(),
            'data' => $reminders
        ]);
    }

    /**
     * Process bulk full payment for multiple selected EMIs
     */
    public function bulkPay(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'emi_ids' => 'required|array',
            'emi_ids.*' => 'required'
        ]);

        $emiIds = $request->emi_ids;
        $count = 0;
        $totalPaid = 0;

        DB::beginTransaction();
        try {
            $paymentService = app(\App\Services\LoanPaymentService::class);

            $decodedIds = [];
            foreach ($emiIds as $hashedId) {
                $emiId = HashId::decode($hashedId);
                $emiId = is_array($emiId) ? ($emiId[0] ?? $hashedId) : ($emiId ?? $hashedId);
                $decodedIds[] = $emiId;
            }

            // Fetch selected EMIs ordered by loan_account_id and instalment_number
            $emis = Emi::whereIn('id', $decodedIds)
                ->orderBy('loan_account_id')
                ->orderBy('instalment_number')
                ->get();

            foreach ($emis as $emi) {
                // Skip if already fully paid
                if ($emi->status === 'paid' || ($emi->total_amount - $emi->paid_amount) <= 0.001) {
                    continue;
                }

                $pendingAmount = $emi->total_amount - $emi->paid_amount;

                // Process full payment for this EMI and bypass prior check
                $result = $paymentService->processPayment(
                    $emi->id,
                    $pendingAmount,
                    Carbon::now()->toDateString(),
                    'cash',
                    'Bulk Payment',
                    'Bulk payment processed by Admin',
                    false, // skipHistory
                    0, // principal_amount
                    true // bypassPriorCheck
                );

                if (!$result['success']) {
                    throw new \Exception("Failed to pay EMI #{$emi->instalment_number} for Loan Account: " . ($emi->loanAccount->account_number ?? 'N/A') . ". Reason: " . $result['message']);
                }

                // Fire notification event
                event(new \App\Events\PaymentReceivedEvent($emi, $pendingAmount));

                $count++;
                $totalPaid += $pendingAmount;
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully processed full payments for {$count} EMIs. Total paid: ₹" . number_format($totalPaid, 2)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk payment failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Bulk payment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process bulk undo payments for multiple selected EMIs
     */
    public function bulkUndo(Request $request): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'emi_ids' => 'required|array',
            'emi_ids.*' => 'required'
        ]);

        $emiIds = $request->emi_ids;
        $count = 0;

        DB::beginTransaction();
        try {
            $paymentService = app(\App\Services\LoanPaymentService::class);

            $decodedIds = [];
            foreach ($emiIds as $hashedId) {
                $emiId = HashId::decode($hashedId);
                $emiId = is_array($emiId) ? ($emiId[0] ?? $hashedId) : ($emiId ?? $hashedId);
                $decodedIds[] = $emiId;
            }

            // Fetch selected EMIs ordered by loan_account_id and instalment_number descending (to undo the latest ones first safely!)
            $emis = Emi::whereIn('id', $decodedIds)
                ->orderBy('loan_account_id')
                ->orderByDesc('instalment_number')
                ->get();

            foreach ($emis as $emi) {
                // Skip if not fully paid (only undo paid ones)
                if ($emi->status !== 'paid') {
                    continue;
                }

                $result = $paymentService->undoEmiPayment($emi->id, 'Bulk undo processed by Admin');

                if (!$result['success']) {
                    throw new \Exception("Failed to undo EMI #{$emi->instalment_number} for Loan Account: " . ($emi->loanAccount->account_number ?? 'N/A') . ". Reason: " . $result['message']);
                }

                $count++;
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully undid payments for {$count} EMIs."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk undo failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Bulk undo failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin-only: Undo a fully paid EMI payment
     */
    public function undoPayment(Request $request, $emiId): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. Admin access only.'], 403);
        }

        $decodedEmiId = \App\Support\HashId::decode($emiId);
        $decodedEmiId = is_array($decodedEmiId) ? ($decodedEmiId[0] ?? $emiId) : ($decodedEmiId ?? $emiId);

        $reason = $request->input('reason', 'Payment undone by Admin');

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $result = $paymentService->undoEmiPayment($decodedEmiId, $reason);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }

        return response()->json(['success' => false, 'message' => $result['message']], 500);
    }

    /**
     * Admin-only: Delete a payment/collection entry
     */
    public function deleteCollection(Request $request, $collectionId): JsonResponse
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action. Admin access only.'], 403);
        }

        $decodedCollectionId = \App\Support\HashId::decode($collectionId);
        $decodedCollectionId = is_array($decodedCollectionId) ? ($decodedCollectionId[0] ?? $collectionId) : ($decodedCollectionId ?? $collectionId);

        $reason = $request->input('reason', 'Payment collection entry deleted by Admin');

        $paymentService = app(\App\Services\LoanPaymentService::class);
        $result = $paymentService->deleteEmiCollection($decodedCollectionId, $reason);

        if ($result['success']) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }

        return response()->json(['success' => false, 'message' => $result['message']], 500);
    }

    private function parseFilterDate($dateStr)
    {
        if (empty($dateStr)) return null;

        $dateStr = trim($dateStr);

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $dateStr)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $dateStr);
            } catch (\Exception $e) {}
        }

        if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $dateStr)) {
            try {
                return Carbon::createFromFormat('d-m-Y', $dateStr);
            } catch (\Exception $e) {}
        }

        try {
            return Carbon::parse($dateStr);
        } catch (\Exception $e) {
            return null;
        }
    }
}
