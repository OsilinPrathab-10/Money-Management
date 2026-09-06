<?php

namespace App\Http\Controllers;

use App\Models\EmiCollection;
use App\Models\Agent;
use App\Models\Emi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\PartialPaymentConfigService;

class AgentCollectionController extends Controller
{
    private function getStatsData()
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $currentAgentId = $isAgent ? optional($currentUser->agent)->id : null;

        $applyAgentCollectionScope = function ($query) use ($isAgent, $currentAgentId) {
            if ($isAgent && $currentAgentId) {
                $query->where(function ($q) use ($currentAgentId) {
                    $q->where('agent_id', $currentAgentId)
                        ->orWhereHas('emi.loanAccount.client', function ($cq) use ($currentAgentId) {
                            $cq->where('assigned_to', $currentAgentId)
                                ->orWhere('added_by', $currentAgentId);
                        });
                });
            }
        };

        $agentCollectedQuery = EmiCollection::whereNotNull('agent_id')
            ->whereIn('status', ['verified', 'in_progress'])
            ->where('payment_method', '!=', 'payment_link');
        if ($isAgent && $currentAgentId) {
            $agentCollectedQuery->where('agent_id', $currentAgentId);
        } else {
            $applyAgentCollectionScope($agentCollectedQuery);
        }

        $adminCollectedQuery = EmiCollection::whereNull('agent_id')
            ->whereIn('status', ['verified', 'in_progress'])
            ->where('payment_method', '!=', 'payment_link');
        if ($isAgent && $currentAgentId) {
            $adminCollectedQuery->whereHas('emi.loanAccount.client', function ($cq) use ($currentAgentId) {
                $cq->where('assigned_to', $currentAgentId)
                    ->orWhere('added_by', $currentAgentId);
            });
        } else {
            $applyAgentCollectionScope($adminCollectedQuery);
        }

        $paymentLinkQuery = EmiCollection::where('payment_method', 'payment_link')
            ->whereIn('status', ['verified', 'in_progress']);
        if ($isAgent && $currentAgentId) {
            $paymentLinkQuery->where(function ($q) use ($currentAgentId) {
                $q->where('agent_id', $currentAgentId)
                  ->orWhereHas('emi.loanAccount.client', function ($cq) use ($currentAgentId) {
                      $cq->where('assigned_to', $currentAgentId)
                         ->orWhere('added_by', $currentAgentId);
                  });
            });
        } else {
            $applyAgentCollectionScope($paymentLinkQuery);
        }

        $agentCollectedCount = $agentCollectedQuery->count();
        $agentCollectedAmount = $agentCollectedQuery->sum('amount');

        $adminCollectedCount = $adminCollectedQuery->count();
        $adminCollectedAmount = $adminCollectedQuery->sum('amount');

        $paymentLinkCount = $paymentLinkQuery->count();
        $paymentLinkAmount = $paymentLinkQuery->sum('amount');
        
        $pendingTasksCount = 0;
        if ($isAgent && $currentAgentId) {
            $pendingTasksCount = \App\Models\EmiAgentAssignment::where('agent_id', $currentAgentId)
                ->active()
                ->onActiveLoan()
                ->count();
        }

        return [
            'agentCollectedCount' => $agentCollectedCount,
            'agentCollectedAmount' => (float)$agentCollectedAmount,
            'adminCollectedCount' => $adminCollectedCount,
            'adminCollectedAmount' => (float)$adminCollectedAmount,
            'paymentLinkCount' => $paymentLinkCount,
            'paymentLinkAmount' => (float)$paymentLinkAmount,
            'pendingTasksCount' => $pendingTasksCount,
        ];
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getStatsData()
        ]);
    }

    public function index()
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $currentAgentId = $isAgent ? optional($currentUser->agent)->id : null;

        $stats = $this->getStatsData();
        $agentCollectedCount = $stats['agentCollectedCount'];
        $agentCollectedAmount = $stats['agentCollectedAmount'];
        $adminCollectedCount = $stats['adminCollectedCount'];
        $adminCollectedAmount = $stats['adminCollectedAmount'];
        $paymentLinkCount = $stats['paymentLinkCount'];
        $paymentLinkAmount = $stats['paymentLinkAmount'];
        $pendingTasksCount = $stats['pendingTasksCount'];

        $agents = Agent::where('status', 'active')->orderBy('agent_name')->get();
        
        $myAssignments = collect();
        if ($isAgent && $currentAgentId) {
            $myAssignments = \App\Models\EmiAgentAssignment::with([
                'emi.loanAccount.client',
                'emi.loanAccount',
            ])
                ->where('agent_id', $currentAgentId)
                ->active()
                ->onActiveLoan()
                ->orderBy('assigned_at', 'desc')
                ->get();
        }

        $partialPaymentGlobal = app(PartialPaymentConfigService::class)->getGlobalSettings();

        return view('admin.agents.agent-collections.agent-collection', compact(
            'agentCollectedCount',
            'agentCollectedAmount',
            'adminCollectedCount',
            'adminCollectedAmount',
            'paymentLinkCount',
            'paymentLinkAmount',
            'agents',
            'isAgent',
            'currentAgentId',
            'pendingTasksCount',
            'myAssignments',
            'partialPaymentGlobal'
        ));
    }

    public function list(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $columns = [
            0 => 'id',
            1 => 'client_name',
            2 => 'agent_id',
            3 => 'emi_id',
            4 => 'amount',
            5 => 'payment_method',
            6 => 'payment_type',
            7 => 'status',
            8 => 'collected_at',
        ];

        $query = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy']);
        
        // Filter by agent if current user is an agent
        $currentUser = Auth::user();
        $agentId = null;
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                // Show only this agent's own collections
                $query->where('agent_id', $agentId);
            }
        }
        // Initialize total records based on visibility (agent filter applied)
        $totalData = $query->count();
        $totalFiltered = $totalData;

        // Handle status filter
        if ($request->has('status') && !empty($request->status)) {
            $statusVal = $request->status;
            if ($statusVal === 'pending') {
                $query->where('status', 'in_progress');
            } else {
                $query->where('status', $statusVal);
            }
        }

        // Handle collector filter
        if ($request->has('collector') && !empty($request->collector)) {
            $collectorVal = $request->collector;
            if ($collectorVal === 'agent') {
                $query->whereNotNull('agent_id');
            } elseif ($collectorVal === 'admin') {
                $query->whereNull('agent_id');
            }
        }

        // Handle method filter
        if ($request->has('method') && !empty($request->method)) {
            $methodVal = $request->method;
            if ($methodVal === 'payment_link') {
                $query->where('payment_method', 'payment_link');
            } elseif (str_starts_with($methodVal, 'agent_')) {
                $actualMethod = str_replace('agent_', '', $methodVal);
                $query->whereNotNull('agent_id')->where('payment_method', $actualMethod);
            } elseif (str_starts_with($methodVal, 'admin_')) {
                $actualMethod = str_replace('admin_', '', $methodVal);
                $query->whereNull('agent_id')->where('payment_method', $actualMethod);
            }
        }

        // Log agent filter application
        Log::info('Agent collections filtered by assigned client', ['agent_id' => $agentId ?? null]);
        $limit = $request->input('length', 20);
        $start = $request->input('start', 0);
        
        $orderIndex = $request->input('order.0.column', 0);
        $dir = $request->input('order.0.dir', 'desc');

        // Update totalFiltered after status filter but before search
        $totalFiltered = $query->count();

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhere('amount', 'LIKE', "%{$search}%")
                  ->orWhereHas('agent', function ($q) use ($search) {
                      $q->where('agent_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('emi.loanAccount.client', function ($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  });
            });

            $totalFiltered = $query->count();
            // Log filtered count
            Log::info('Agent collections search applied', ['search' => $search, 'filtered_count' => $totalFiltered]);
        }

        // Apply ordering to collections query
        $collectionsQuery = clone $query;
        if ($orderIndex == 1) {
            // Join clients to sort by client name
            $collectionsQuery->join('emis', 'emi_collections.emi_id', '=', 'emis.id')
                ->join('loan_accounts', 'emis.loan_account_id', '=', 'loan_accounts.id')
                ->join('clients', 'loan_accounts.client_id', '=', 'clients.id')
                ->orderBy('clients.client_name', $dir)
                ->select('emi_collections.*');
        } elseif ($orderIndex == 2) {
            // Left join agents to sort by agent name
            $collectionsQuery->leftJoin('agents', 'emi_collections.agent_id', '=', 'agents.id')
                ->orderBy('agents.agent_name', $dir)
                ->select('emi_collections.*');
        } else {
            $orderColumn = $columns[$orderIndex] ?? 'id';
            $collectionsQuery->orderBy($orderColumn, $dir);
        }

        $collections = $collectionsQuery->offset($start)
            ->limit($limit)
            ->get();

        // Group multi-EMI collections
        $grouped = [];
        $processedIds = [];
        $processedEmiIds = [];
        
        foreach ($collections as $collection) {
            // Skip if already processed
            if (in_array($collection->id, $processedIds)) {
                continue;
            }

            // If Agent is logged in, prevent duplicate EMI IDs in the collections list
            if ($currentUser->hasRole('Agent') && $collection->emi_id) {
                if (in_array($collection->emi_id, $processedEmiIds)) {
                    continue;
                }
                $processedEmiIds[] = $collection->emi_id;
            }
            
            // Extract group ID from remarks
            $groupId = null;
            if (preg_match('/Group:\s*(\S+)/', $collection->remarks ?? '', $matches)) {
                $groupId = $matches[1];
            }
            
            if ($groupId) {
                // Find all collections with the same group ID
                $relatedCollections = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy'])
                    ->where('remarks', 'LIKE', "%Group: {$groupId}%")
                    ->get();
                
                // Get client info from first collection
                $clientName = $collection->emi && $collection->emi->loanAccount && $collection->emi->loanAccount->client
                    ? $collection->emi->loanAccount->client->client_name
                    : 'N/A';
                
                // Calculate total amount
                $totalAmount = $relatedCollections->sum('amount');
                $emiIds = $relatedCollections->pluck('emi_id')->toArray();
                
                // Mark all as processed
                foreach ($relatedCollections as $rc) {
                    $processedIds[] = $rc->id;
                }
                
                // Add grouped entry
                $grouped[] = [
                    'id' => $collection->getRouteKey(), // Obfuscated ID for view link
                    'agent_name' => $collection->agent ? $collection->agent->agent_name : ($collection->verifiedBy ? 'Admin: ' . $collection->verifiedBy->name : 'Admin'),
                    'client_name' => $clientName,
                    'emi_id' => 'Multiple (' . count($emiIds) . ' EMIs)', // Show count instead of IDs
                    'real_emi_id' => $collection->emi_id,
                    'amount' => $totalAmount,
                    'payment_method' => $collection->payment_method,
                    'payment_type' => $collection->payment_type,
                    'status' => $collection->status,
                    'collected_at' => $collection->collected_at ? $collection->collected_at->toIso8601String() : '',
                    'created_at' => $collection->created_at ? $collection->created_at->toIso8601String() : '',
                    'is_grouped' => true,
                    'emi_count' => count($emiIds),
                    'group_id' => $groupId,
                    'action' => '',
                ];
            } else {
                // Single EMI collection
                $processedIds[] = $collection->id;
                
                $grouped[] = [
                    'id' => $collection->getRouteKey(),
                    'agent_name' => $collection->agent ? $collection->agent->agent_name : ($collection->verifiedBy ? 'Admin: ' . $collection->verifiedBy->name : 'Admin'),
                    'client_name' => $collection->emi && $collection->emi->loanAccount && $collection->emi->loanAccount->client
                        ? $collection->emi->loanAccount->client->client_name
                        : 'N/A',
                    'emi_id' => '#' . $collection->emi_id,
                    'real_emi_id' => $collection->emi_id,
                    'amount' => $collection->amount,
                    'payment_method' => $collection->payment_method,
                    'payment_type' => $collection->payment_type,
                    'status' => $collection->status,
                    'collected_at' => $collection->collected_at ? $collection->collected_at->toIso8601String() : '',
                    'created_at' => $collection->created_at ? $collection->created_at->toIso8601String() : '',
                    'is_grouped' => false,
                    'emi_count' => 1,
                    'group_id' => null,
                    'action' => '',
                ];
            }
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $grouped,
        ]);
    }

    public function show($id)
    {
        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);
        $collection = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy'])->findOrFail($realId);
        
        // Extract group ID from remarks
        $groupId = null;
        if (preg_match('/Group:\s*(\S+)/', $collection->remarks ?? '', $matches)) {
            $groupId = $matches[1];
        }
        
        $isMultiEmi = !is_null($groupId);
        $relatedCollections = [];
        
        if ($isMultiEmi) {
            // Fetch all related collections with the same group ID
            $relatedCollections = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy'])
                ->where('remarks', 'LIKE', "%Group: {$groupId}%")
                ->get();
        }
        
        return view('admin.agents.agent-collections.view', compact('collection', 'relatedCollections', 'isMultiEmi'));
    }

    public function verify(Request $request, $id)
    {
        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);
        $request->validate([
            'status' => 'required|in:verified,rejected',
            'remarks' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $collection = EmiCollection::with('emi.loanAccount')
                ->lockForUpdate()
                ->findOrFail($realId);

            if ($collection->status === 'verified' || $collection->status === 'rejected') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This payment collection has already been processed.'
                ], 400);
            }
            
            if (!in_array($collection->payment_method, ['in_hand', 'cash', 'upi', 'bank_transfer', 'direct', 'payment_link', 'cheque'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Only collected payments can be verified'
                ], 400);
            }

            // Preserve existing remarks (especially Group ID) and append new ones
            $existingRemarks = $collection->remarks ?? '';
            $newRemarks = $request->remarks ?? '';
            
            // Combine remarks: keep existing + add new if provided
            $combinedRemarks = trim($existingRemarks . ($newRemarks ? "\n" . $newRemarks : ''));
            
            // Update collection status
            $collection->update([
                'status' => $request->status,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'remarks' => $combinedRemarks
            ]);

            // Only update EMI if verified
            if ($request->status === 'verified') {
                $emi = $collection->emi;
                $loanAccount = $emi->loanAccount;

                // Check if this is a multi-EMI collection (created by distributePaymentAcrossEmis)
                $isMultiEmiCollection = strpos($collection->remarks ?? '', 'Auto-distributed') !== false;
                
                if ($isMultiEmiCollection) {
                    // Multi-EMI collection - update the EMI status
                    Log::info('Admin verifying multi-EMI in-hand collection', [
                        'collection_id' => $collection->id,
                        'emi_id' => $collection->emi_id,
                        'amount' => $collection->amount,
                    ]);
                    
                    // Update EMI based on collection amount
                    $emi->paid_amount = ($emi->paid_amount ?? 0) + $collection->amount;
                    $emi->pending_amount = max(0, $emi->pending_amount - $collection->amount);
                    
                    if ($emi->pending_amount <= 0) {
                        $emi->status = 'paid';
                        $emi->paid_date = $collection->collected_at;
                    } elseif ($emi->paid_amount > 0) {
                        $emi->status = 'partial';
                        $emi->is_partial_paid = true;
                        $emi->partial_paid_date = $collection->collected_at;
                        $emi->partial_paid_amount = $emi->paid_amount;
                    }
                    
                    $emi->payment_method = 'in_hand';
                    $emi->payment_reference = "Collection ID: {$collection->id}";
                    $emi->save();
                    
                    // Update assignment if paid
                    if ($emi->status === 'paid') {
                        \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                            ->where('agent_id', $collection->agent_id)
                            ->update([
                                'status' => 'resolved',
                                'resolved_at' => now(),
                            ]);
                    }
                    
                    Log::info('Multi-EMI collection EMI updated', [
                        'emi_id' => $emi->id,
                        'emi_status' => $emi->status,
                    ]);
                    
                } else {
                    // Single EMI collection (both interest-only and standard)
                    // We use LoanPaymentService for ALL payments to ensure standard distribution, history, and validations
                    $paymentService = app(\App\Services\LoanPaymentService::class);
                    
                    $result = $paymentService->processPayment(
                        $emi->id,
                        $collection->amount,
                        $collection->collected_at->format('Y-m-d'),
                        'in_hand',
                        "Collection ID: {$collection->id}",
                        "Admin verified in-hand collection",
                        true // skipHistory to avoid duplicate records
                    );
                    
                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }
                    
                    $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
                    if ($isKandhuvatti && isset($result['remarks'])) {
                        $calcRemarks = $result['remarks'];
                        $finalRemarks = trim($combinedRemarks . ($combinedRemarks ? ' | ' : '') . $calcRemarks);
                        $collection->update(['remarks' => $finalRemarks]);
                    }
                    
                    // Refresh EMI to get latest status
                    $emi->refresh();
                    
                    // Resolve the agent assignment upon collection verification
                    \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                        ->where('agent_id', $collection->agent_id)
                        ->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'remarks' => trim(($emi->remarks ?? '') . ' [Resolved via admin verified in-hand collection]')
                        ]);
                    
                    Log::info('Agent assignment resolved', [
                        'emi_id' => $emi->id,
                        'agent_id' => $collection->agent_id,
                    ]);
                }

                // Sync loan account totals and EMI balances (runs for BOTH single and multi-EMI collections)
                $paymentService = app(\App\Services\LoanPaymentService::class);
                $paymentService->syncEmiBalances($loanAccount->id);
                $paymentService->syncLoanTotals($loanAccount->id);
            } else {
                Log::info('In-hand collection rejected by admin', [
                    'collection_id' => $collection->id,
                    'emi_id' => $collection->emi_id,
                    'remarks' => $request->remarks,
                ]);

                // If rejected, set assignment status back to 'assigned' so the agent can retry
                \App\Models\EmiAgentAssignment::where('emi_id', $collection->emi_id)
                    ->where('agent_id', $collection->agent_id)
                    ->update([
                        'status' => 'assigned',
                        'resolved_at' => null,
                    ]);
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Collection ' . ($request->status === 'verified' ? 'verified' : 'rejected') . ' successfully'
                ]);
            }

            return redirect()->route('agent-collections.show', $collection->id)
                ->with('success', 'Collection ' . ($request->status === 'verified' ? 'approved' : 'rejected') . ' successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Admin verification failed', [
                'collection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification failed: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('agent-collections.show', $id)
                ->with('error', 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Repay a rejected collection — Admin only
     * Re-processes the rejected EmiCollection via LoanPaymentService and marks it verified.
     * The reprocess amount is capped to the EMI's actual pending balance to prevent overpayment.
     */
    public function repay(Request $request, $id)
    {
        if (!Auth::user()->hasRole('Admin') && !Auth::user()->hasRole('Staff') && !Auth::user()->hasRole('Agent')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $realId = \App\Support\HashId::decode((string) $id);
        $realId = is_array($realId) ? ($realId[0] ?? $id) : ($realId ?? $id);

        $collection = EmiCollection::with('emi.loanAccount')->findOrFail($realId);

        if (Auth::user()->hasRole('Agent')) {
            $agent = Auth::user()->agent;
            if (!$agent || $collection->agent_id !== $agent->id) {
                return response()->json(['success' => false, 'message' => 'You can only re-pay your own collections.'], 403);
            }
        }

        if ($collection->status !== 'rejected') {
            return response()->json(['success' => false, 'message' => 'Only rejected collections can be repaid.'], 400);
        }

        DB::beginTransaction();
        try {
            $emi         = $collection->emi;
            $loanAccount = $emi->loanAccount;

            // Calculate the actual EMI pending amount dynamically
            // Sum only verified/paid collections (exclude rejected and the current rejected one)
            $verifiedPaid = EmiCollection::where('emi_id', $emi->id)
                ->where('status', 'verified')
                ->sum('amount');

            $emiTotalDue = (float) $emi->total_amount + (float) $emi->penalty_amount;
            $emiPending  = max(0, $emiTotalDue - $verifiedPaid);

            // Cap the reprocess amount to the EMI's actual pending balance
            $rejectedAmount    = (float) $collection->amount;
            $reprocessAmount   = min($rejectedAmount, $emiPending);

            if ($reprocessAmount <= 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This EMI has no remaining balance to reprocess. The EMI is already fully paid.'
                ], 400);
            }

            $newRemarks = $request->input('remarks');
            $existingRemarks = $collection->remarks ?? '';
            $combinedRemarks = trim($existingRemarks . ($newRemarks ? "\n" . $newRemarks : ''));
            $systemRemarks = "[Re-verified after rejection. Original rejected: ₹" . number_format($rejectedAmount, 2) . ", Reprocessed: ₹" . number_format($reprocessAmount, 2) . "]";
            $finalRemarks = trim($combinedRemarks . "\n" . $systemRemarks);

            // Re-mark the collection as verified but update the amount to the capped value
            $collection->update([
                'status'      => 'verified',
                'amount'      => $reprocessAmount,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'remarks'     => $finalRemarks,
            ]);

            // Process the payment via LoanPaymentService with the capped amount
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $result = $paymentService->processPayment(
                $emi->id,
                $reprocessAmount,
                $collection->collected_at->format('Y-m-d'),
                $collection->payment_method,
                $collection->payment_reference,
                'Admin repaid after rejection (capped to EMI balance)',
                true // skipHistory — collection record already exists
            );

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Sync Kandhuvatti details into remarks if applicable
            $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
            if ($isKandhuvatti && isset($result['remarks'])) {
                $calcRemarks = $result['remarks'];
                $finalRemarks = trim($finalRemarks . ' | ' . $calcRemarks);
                $collection->update(['remarks' => $finalRemarks]);
            }

            // Resolve agent assignment
            \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                ->where('agent_id', $collection->agent_id)
                ->update(['status' => 'resolved', 'resolved_at' => now()]);

            // Sync totals
            $paymentService->syncEmiBalances($loanAccount->id);
            $paymentService->syncLoanTotals($loanAccount->id);

            DB::commit();

            $message = 'Collection re-processed and verified for ₹' . number_format($reprocessAmount, 2) . '.';
            if ($reprocessAmount < $rejectedAmount) {
                $message .= ' (Original rejected amount was ₹' . number_format($rejectedAmount, 2) . ', only ₹' . number_format($reprocessAmount, 2) . ' applied to EMI balance.)';
            }

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect()->route('agent-collections.show', $collection->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Collection repay failed', ['collection_id' => $id, 'error' => $e->getMessage()]);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Repay failed: ' . $e->getMessage()], 500);
            }

            return redirect()->route('agent-collections.show', $id)
                ->with('error', 'Repay failed: ' . $e->getMessage());
        }
    }

    public function bulkVerify(Request $request)
    {
        if (!Auth::user()->hasRole('Admin') && !Auth::user()->hasRole('Staff')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'collection_ids'   => 'required|array|min:1',
            'collection_ids.*' => 'required',
            'remarks'          => 'nullable|string|max:500',
        ]);

        $ids     = $request->collection_ids;
        $remarks = $request->remarks ?? '';

        $verified = 0;
        $skipped  = 0;
        $errors   = [];

        DB::beginTransaction();
        try {
            foreach ($ids as $rawId) {
                // Decode hashed ID
                $realId = \App\Support\HashId::decode((string) $rawId);
                $realId = is_array($realId) ? ($realId[0] ?? $rawId) : ($realId ?? $rawId);

                $collection = EmiCollection::with('emi.loanAccount')->find($realId);

                if (!$collection) {
                    $skipped++;
                    continue;
                }

                // Only process in_progress / pending collections
                if (!in_array($collection->status, ['in_progress', 'pending'])) {
                    $skipped++;
                    continue;
                }

                // Preserve existing remarks + append new ones
                $existingRemarks = $collection->remarks ?? '';
                $combinedRemarks = trim($existingRemarks . ($remarks ? "\n" . $remarks : ''));

                $collection->update([
                    'status'      => 'verified',
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                    'remarks'     => $combinedRemarks,
                ]);

                $emi         = $collection->emi;
                $loanAccount = $emi->loanAccount;

                if (!$emi || !$loanAccount) {
                    $skipped++;
                    continue;
                }

                // Use LoanPaymentService to apply the payment (covers both Kandhuvatti & Standard EMI)
                $paymentService = app(\App\Services\LoanPaymentService::class);
                $result = $paymentService->processPayment(
                    $emi->id,
                    $collection->amount,
                    $collection->collected_at->format('Y-m-d'),
                    $collection->payment_method,
                    $collection->payment_reference,
                    "Bulk verified by admin" . ($remarks ? ": {$remarks}" : ''),
                    true // skipHistory — collection record already exists
                );

                if (!$result['success']) {
                    $errors[] = "Collection #{$rawId}: " . $result['message'];
                    continue;
                }

                // Sync Kandhuvatti details into remarks if applicable
                $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
                if ($isKandhuvatti && isset($result['remarks'])) {
                    $calcRemarks = $result['remarks'];
                    $finalRemarks = trim($combinedRemarks . ($combinedRemarks ? ' | ' : '') . $calcRemarks);
                    $collection->update(['remarks' => $finalRemarks]);
                }

                // Resolve agent assignment
                \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                    ->where('agent_id', $collection->agent_id)
                    ->update([
                        'status'      => 'resolved',
                        'resolved_at' => now(),
                        'remarks'     => trim(($emi->remarks ?? '') . ' [Resolved via bulk verification]'),
                    ]);

                $verified++;
            }

            DB::commit();

            $message = "Bulk verify complete. Verified: {$verified}";
            if ($skipped > 0)  $message .= ", Skipped: {$skipped}";
            if (!empty($errors)) $message .= ". Some errors: " . implode('; ', array_slice($errors, 0, 3));

            return response()->json([
                'success'  => true,
                'message'  => $message,
                'verified' => $verified,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk verify failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Bulk verify failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Search for pending EMIs for manual collection creation
     */
    public function searchEmis(Request $request)
    {
        $search = $request->input('q');
        $agentIdFilter = $request->input('agent_id');
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $currentAgentId = $isAgent ? optional($currentUser->agent)->id : $agentIdFilter;
        
        $action = $request->input('action', 'collection');
        
        $query = Emi::with(['loanAccount.client'])
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->where(function($q) {
                // Hide EMIs that are already fully covered by pending (in_progress) collections
                $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
            });

        // Filter by agent if provided or if user is an agent (skip for assignment action)
        if ($currentAgentId && $action !== 'assign') {
            $query->where(function($q) use ($currentAgentId) {
                $q->whereHas('loanAccount.client', function($query) use ($currentAgentId) {
                    $query->where('assigned_to', $currentAgentId)
                          ->orWhere('added_by', $currentAgentId);
                })
                ->orWhereHas('activeAssignment', function($query) use ($currentAgentId) {
                    $query->where('agent_id', $currentAgentId);
                });
            });
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereHas('loanAccount.client', function($query) use ($search) {
                    $query->where('client_name', 'LIKE', "%{$search}%")
                          ->orWhere('client_phone', 'LIKE', "%{$search}%")
                          ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('loanAccount', function($query) use ($search) {
                    $query->where('account_number', 'LIKE', "%{$search}%");
                })
                ->orWhere('emis.id', 'LIKE', "%{$search}%");
            });
        }

        $page = $request->input('page', 1);
        $perPage = 50;

        $emis = $query->orderByRaw("CASE WHEN status = 'partial' THEN 1 ELSE 2 END ASC")
            ->orderBy('instalment_number')
            ->paginate($perPage, ['*'], 'page', $page);
            
        $formattedResults = $emis->getCollection()->map(function($emi) {
            $clientName = $emi->loanAccount?->client?->client_name ?? 'N/A';
            $accNo = $emi->loanAccount?->account_number ?? 'N/A';
            $status = strtoupper($emi->status);
            
            // Sequential lock: Check if any previous EMI is unpaid AND not fully covered by pending (in_progress) collections
            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                ->where('instalment_number', '<', $emi->instalment_number)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->exists();

            $isDisabled = ($emi->status === 'paid' || $unpaidPrior);
            $inProgressSum = $emi->collections()->where('status', 'in_progress')->sum('amount');
            $netPending = max(0, $emi->pending_amount - $inProgressSum);
            
            $label = ($emi->status === 'partial') ? 'Balance' : 'Pending';
            $displayText = "[#{$accNo}] {$clientName} - EMI #{$emi->instalment_number} ({$status}) - {$label}: ₹" . number_format($netPending, 2);
            if ($unpaidPrior) {
                $displayText .= " (PREVIOUS EMI PENDING)";
            }

            return [
                'id' => $emi->id,
                'text' => $displayText,
                'amount' => $netPending,
                'disabled' => $isDisabled
            ];
        });

        return response()->json([
            'results' => $formattedResults,
            'pagination' => [
                'more' => $emis->hasMorePages()
            ]
        ]);
    }

    /**
     * Store a manually created collection
     */
    public function store(Request $request)
    {
        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'emi_id' => 'required|exists:emis,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:in_hand,upi,bank_transfer,cash,cheque,direct,payment_link',
            'payment_type' => 'nullable|in:full,partial',
            'payment_reference' => 'nullable|string|max:100',
            'collected_at' => 'required|date',
            'remarks' => 'nullable|string'
        ]);
        
        $emi = Emi::findOrFail($request->emi_id);

        // Check for unpaid EMIs prior to this one (ignoring those fully covered by pending (in_progress) collections)
        $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
            ->orderByDesc('instalment_number')
            ->first();
        $isLoanMatured = ($lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now()));

        $unpaidPrior = false;
        if (!$isLoanMatured) {
            $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                ->where('instalment_number', '<', $emi->instalment_number)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->exists();
        }

        if ($unpaidPrior) {
            return response()->json([
                'success' => false,
                'message' => 'Please clear previous pending EMIs before paying for this instalment.'
            ], 400);
        }

        $loanAccount = $emi->loanAccount;
        $partialService = app(PartialPaymentConfigService::class);
        $pendingAmount = $partialService->getOutstandingDueAmount($emi, $loanAccount);
        $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');

        if ($isKandhuvatti) {
            // For open loans, if amount is less than pending interest due, it's a partial payment
            $isPartialPayment = $request->amount < ($pendingAmount - 0.01);
            
            if ($isPartialPayment) {
                if (!$partialService->isActive()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Partial payments are disabled. Enable them in Loan Configuration.',
                    ], 422);
                }

                if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $loanAccount)) {
                    return response()->json([
                        'success' => false,
                        'message' => $validationError,
                    ], 422);
                }
            } else {
                // If amount is greater than pending interest, the excess is principal payment.
                // It cannot exceed the remaining outstanding principal.
                $excess = $request->amount - $pendingAmount;
                if ($excess > ($loanAccount->outstanding_amount + 0.01)) {
                    $maxAllowable = $pendingAmount + $loanAccount->outstanding_amount;
                    return response()->json([
                        'success' => false,
                        'message' => 'Collection amount cannot exceed the total outstanding due (Interest: ₹' . number_format($pendingAmount, 2) . ' + Principal: ₹' . number_format($loanAccount->outstanding_amount, 2) . ' = Total: ₹' . number_format($maxAllowable, 2) . ').'
                    ], 400);
                }
            }
        } else {
            $paymentType = $request->payment_type ?: ($request->amount < $pendingAmount ? 'partial' : 'full');
            $isPartialPayment = $paymentType === 'partial' || $request->amount < ($pendingAmount - 0.01);

            if ($isPartialPayment) {
                if (!$partialService->isActive()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Partial payments are disabled. Enable them in Loan Configuration.',
                    ], 422);
                }

                if ($validationError = $partialService->validatePartialAmount($emi, (float) $request->amount, $loanAccount)) {
                    return response()->json([
                        'success' => false,
                        'message' => $validationError,
                    ], 422);
                }
            } elseif ($request->amount > ($pendingAmount + 1)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Collection amount cannot exceed the pending EMI amount (₹' . number_format($pendingAmount, 0) . ').'
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            $paymentType = $request->input('payment_type');
            if (!$paymentType) {
                $paymentType = ($request->amount < $pendingAmount) ? 'partial' : 'full';
            }
            
            $isAgent = Auth::user()->hasRole('Agent');
            $status = ($isAgent || $request->payment_method == 'in_hand') ? 'in_progress' : 'verified';
            $remarksPrefix = $isAgent ? '[Agent Created]' : '[Admin Created]';

            // Find existing pending collection for this EMI to avoid duplicates
            $collection = EmiCollection::where('emi_id', $emi->id)
                ->where('status', 'in_progress')
                ->first();

            if ($collection) {
                $newTotalAmount = $collection->amount + $request->amount;
                $isNowFull = ($newTotalAmount >= ($pendingAmount - 0.01));
                
                // If it's now full, and we are not restricted to 'in_progress', we can verify it
                if ($isNowFull && $status === 'verified') {
                    $newStatus = 'verified';
                } else {
                    $newStatus = 'in_progress';
                }

                $collection->update([
                    'amount' => $newTotalAmount,
                    'payment_type' => $isNowFull ? 'full' : 'partial',
                    'payment_method' => $request->payment_method,
                    'status' => $newStatus,
                    'collected_at' => $request->collected_at,
                    'remarks' => trim(($collection->remarks ?? '') . "\n" . ($request->remarks ?? 'Additional collection') . ' ' . $remarksPrefix),
                    'verified_by' => ($newStatus === 'verified') ? Auth::id() : $collection->verified_by,
                    'verified_at' => ($newStatus === 'verified') ? now() : $collection->verified_at,
                ]);
            } else {
                $isAutoVerified = ($status === 'verified');
                $collection = EmiCollection::create([
                    'agent_id' => $request->agent_id,
                    'emi_id' => $request->emi_id,
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method,
                    'payment_type' => $paymentType,
                    'payment_reference' => $request->payment_reference,
                    'status' => $status,
                    'collected_at' => $request->collected_at,
                    'remarks' => trim(($request->remarks ?? '') . ' ' . $remarksPrefix),
                    'verified_by' => $isAutoVerified ? Auth::id() : null,
                    'verified_at' => $isAutoVerified ? now() : null,
                ]);
            }

            // Record this specific payment in AgentActivity for history popup
            $activityAgentId = $request->agent_id ?? (Auth::user()->agent?->id ?? null);
            if ($activityAgentId) {
                \App\Models\AgentActivity::create([
                    'emi_id' => $emi->id,
                    'agent_id' => $activityAgentId,
                    'type' => 'payment',
                    'description' => "₹" . number_format($request->amount, 2),
                    'method' => strtoupper(str_replace('_', ' ', $request->payment_method)),
                    'reference' => $request->payment_reference,
                    'remarks' => $request->remarks,
                    'action_at' => $request->collected_at,
                ]);
            }
            
            // Update assignment status if exists
            \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                ->whereIn('status', ['assigned', 'visited'])
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now()
                ]);
            
            $isAutoVerified = ($collection->status === 'verified');
            
            // If auto-verified, use LoanPaymentService to process the payment and trigger all business rules
            if ($isAutoVerified) {
                $paymentService = app(\App\Services\LoanPaymentService::class);
                $result = $paymentService->processPayment(
                    $emi->id,
                    $collection->amount,
                    $collection->collected_at->format('Y-m-d'),
                    $collection->payment_method,
                    $collection->payment_reference,
                    $collection->remarks,
                    true // skipHistory since we already created the collection record above
                );

                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }
            }
            
            $loanAccount = $emi->loanAccount->fresh();
            $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
            $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
            $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
            if (strlen($cleanMobile) === 10) {
                $cleanMobile = '91' . $cleanMobile;
            }
            $remainingBalance = $loanAccount->outstanding_amount;
            
            $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
            
            // Adjust remaining balance for unverified payments so the SMS reflects the expected outcome
            if (!$isAutoVerified) {
                if ($isKandhuvatti) {
                    if ($request->payment_type === 'principal') {
                        $remainingBalance = max(0, $remainingBalance - $request->amount);
                    }
                } else {
                    $isReducing = $loanAccount->loanApplication && $loanAccount->loanApplication->product && in_array($loanAccount->loanApplication->product->interest_type, ['reducing', 'declining_balance']);
                    if ($isReducing) {
                        $interestPart = (float)($emi->interest_amount ?? 0);
                        $alreadyPaid = (float)($emi->paid_amount ?? 0);
                        $unpaidInterest = max(0, $interestPart - min($alreadyPaid, $interestPart));
                        
                        $principalPaidInThisTransaction = max(0, $request->amount - $unpaidInterest);
                        $remainingBalance = max(0, $remainingBalance - $principalPaidInThisTransaction);
                    } else {
                        // Standard EMI logic: outstanding is reduced by the entire amount paid
                        $remainingBalance = max(0, $remainingBalance - $request->amount);
                    }
                }
            }
            
            if ($isKandhuvatti) {
                $isFullyPaid = $request->amount >= ($pendingAmount - 0.01);
            } else {
                $isFullyPaid = $paymentType === 'full' || $request->amount >= ($pendingAmount - 0.01);
            }
            
            $emiBalance = max(0, $pendingAmount - $request->amount);

            $smsData = [
                'client_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: ($client->client_name ?? 'Client'),
                'mobile_no' => $cleanMobile,
                'account_no' => $loanAccount->account_number,
                'amount_paid' => $request->amount,
                'remaining_balance' => $remainingBalance,
                'loan_mode' => $loanAccount->loan_mode,
                'payment_type' => ($isKandhuvatti && $request->payment_type === 'principal') ? 'principal' : (($isKandhuvatti) ? 'interest' : 'emi'),
                'application_number' => $loanAccount->application_number,
                'is_partial' => !$isFullyPaid,
                'emi_balance' => $emiBalance,
            ];
            $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Collection added successfully',
                'sms_data' => $smsData
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to add collection: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Assign a single EMI to an agent
     */
    public function assign(Request $request)
    {
        if (!auth()->user()->hasRole('Admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'emi_id' => 'required|exists:emis,id',
            'remarks' => 'nullable|string'
        ]);
    
        $emi = Emi::findOrFail($request->emi_id);

        // Check if the EMI is already paid
        if ($emi->status === 'paid' || $emi->pending_amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot assign a fully paid EMI.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create or update assignment
            \App\Models\EmiAgentAssignment::updateOrCreate(
                ['emi_id' => $emi->id],
                [
                    'agent_id' => $request->agent_id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'remarks' => trim(($request->remarks ?? '') . ' [Assigned via Agent Collections]')
                ]
            );

            // Also update Client assigned_to if needed
            if ($emi->loanAccount && $emi->loanAccount->client) {
                $emi->loanAccount->client->update(['assigned_to' => $request->agent_id]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'EMI assigned successfully to the agent.']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to assign loan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get payment history for a specific collection (via EMI ID)
     */
    public function getHistory($emiId)
    {
        $collections = EmiCollection::with(['agent', 'verifiedBy'])
            ->where('emi_id', $emiId)
            ->orderBy('collected_at', 'desc')
            ->get()
            ->map(function($collection) {
                $statusLabel = match(strtolower($collection->status)) {
                    'verified', 'completed' => 'Verified',
                    'in_progress', 'pending' => 'Pending',
                    'rejected' => 'Rejected',
                    default => ucfirst($collection->status)
                };

                return [
                    'amount' => '₹' . number_format($collection->amount, 2) . ' (' . $statusLabel . ')',
                    'method' => strtoupper(str_replace('_', ' ', $collection->payment_method)),
                    'reference' => $collection->payment_reference ?? 'N/A',
                    'remarks' => $collection->remarks ?? 'N/A',
                    'date' => $collection->collected_at ? $collection->collected_at->format('d-m-Y H:i') : $collection->created_at->format('d-m-Y H:i'),
                    'agent' => $collection->agent?->agent_name ?? ($collection->verifiedBy?->name ?? 'Admin')
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => $collections
        ]);
    }

    /**
     * Get details for a specific EMI (used for pre-filling forms)
     */
    public function getEmiInfo($id)
    {
        $emi = Emi::with(['loanAccount.client'])->findOrFail($id);
        $clientName = $emi->loanAccount?->client?->client_name ?? 'N/A';
        $accNo = $emi->loanAccount?->account_number ?? 'N/A';
        
        // Calculate net pending (total amount - paid amount - in_progress collections)
        $inProgressSum = \App\Models\EmiCollection::where('emi_id', $emi->id)
            ->whereIn('status', ['in_progress', 'verified', 'completed'])
            ->sum('amount');
            
        $loanAccount = $emi->loanAccount;
        $partialService = app(PartialPaymentConfigService::class);
        $netPending = $partialService->getOutstandingDueAmount($emi, $loanAccount);
        $partialRules = $partialService->rulesForEmi($emi, $loanAccount);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $emi->id,
                'text' => "[#{$accNo}] {$clientName} - EMI #{$emi->instalment_number} - Pending: ₹" . number_format($netPending, 2),
                'amount' => $netPending,
                'agent_id' => $emi->loanAccount?->agent_id,
                'partial_payment' => $partialRules,
            ]
        ]);
    }

    /**
     * Partial payment rules for an EMI (admin/agent collection UI).
     */
    public function partialPaymentRules($id)
    {
        $emi = Emi::with('loanAccount')->findOrFail($id);
        $partialService = app(PartialPaymentConfigService::class);

        return response()->json([
            'success' => true,
            'data' => $partialService->rulesForEmi($emi, $emi->loanAccount),
        ]);
    }
}
