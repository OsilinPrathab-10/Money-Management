<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\LoanAccount;
use App\Models\LoanDocumentTemplate;
use App\Models\ClientLoanDocument;
use App\Models\FileSystemCredential;
use App\Models\Appearance; 
use App\Models\Emi;
use App\Models\EmiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Log;
use App\Helpers\AppearanceHelper;
use Illuminate\Support\Str;
use App\Services\DocumentPlaceholderService;
use App\Services\LoanDocumentService;

class ClientViewLoansController extends Controller
{
    protected $documentService;

    public function __construct(LoanDocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index($id)
    {
        $decodedId = \App\Support\HashId::decode($id) ?? $id;
        $client = Client::with(['kycDetail'])->findOrFail($decodedId);
        $loanAccounts = LoanAccount::with(['product.loanType', 'loanApplication.product.loanType'])
            ->where('client_id', $decodedId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $loanApplications = \App\Models\LoanApplication::with('product.loanType')
            ->where('client_id', $decodedId)
            ->orderBy('created_at', 'desc')
            ->get();

        $loanTypes = \App\Models\LoanType::orderBy('name')->get();
        
        return view('admin.clients.client-view-loans', compact('client', 'loanAccounts', 'loanApplications', 'loanTypes'));
    }

    public function getEmiDetails($loanId): JsonResponse
    {
        $loanAccount = LoanAccount::with(['emis' => function($query) {
            $query->orderBy('instalment_number', 'asc');
        }])->findOrFail($loanId);

        $emis = $loanAccount->emis
            ->sortBy('instalment_number')
            ->unique('instalment_number')
            ->values()
            ->map(function($emi) {
            return [
                'instalment_number' => $emi->instalment_number,
                'due_date' => $emi->due_date->format('d-m-Y'),
                'principal_amount' => number_format($emi->principal_amount, 2),
                'interest_amount' => number_format($emi->interest_amount, 2),
                'total_amount' => number_format($emi->total_amount, 2),
                'paid_amount' => number_format($emi->paid_amount ?? 0, 2),
                'collections_count' => $emi->collections->count(),
                'status' => $emi->status,
                'paid_date' => $emi->paid_date ? $emi->paid_date->format('d-m-Y') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'loan_account' => [
                'account_number' => $loanAccount->account_number,
                'application_number' => $loanAccount->application_number,
                'loan_amount' => number_format($loanAccount->loan_amount, 0),
                'interest_rate' => $loanAccount->interest_rate,
                'tenure' => $loanAccount->tenure,
                'total_payable' => number_format($loanAccount->total_payable, 2),
                'paid_amount' => number_format($loanAccount->paid_amount, 2),
                'outstanding_amount' => number_format($loanAccount->outstanding_amount, 2),
                'remaining_principal_balance' => (float)$loanAccount->remaining_principal_balance,
                'principal_allocated' => (float)$loanAccount->principal_allocated,
                'principal_pending' => (float)$loanAccount->principal_pending,
                'status' => $loanAccount->status,
            ],
            'emis' => $emis
        ]);
    }

    public function emiDetailsPage($loanId)
    {
        $decodedLoanId = \App\Support\HashId::decode($loanId) ?? $loanId;
        $loanAccount = LoanAccount::with([
            'loanApplication.client',
            'loanApplication.product',
            'clientLoanDocuments' // Add relationship to fetch saved documents
        ])->findOrFail($decodedLoanId);

        $client = $loanAccount->loanApplication->client;
        
        // Ensure EMI balances are synchronized with the new non-cumulative logic
        $paymentService = app(\App\Services\LoanPaymentService::class);
        $paymentService->syncEmiBalances($decodedLoanId);
        $paymentService->syncLoanTotals($decodedLoanId);
        $loanAccount->refresh();

        // Paginate EMIs with configurable per_page limit
        $perPage = (int) request('per_page', 10);
        if (!in_array($perPage, [10, 20, 25, 50, 100, 200, 500, 1000], true)) {
            $perPage = 10;
        }
        $emis = Emi::with('collections')
            ->where('loan_account_id', $decodedLoanId)
            ->orderBy('instalment_number', 'asc')
            ->paginate($perPage)
            ->withQueryString();
        
        // Get available document templates based on loan status and product
        $availableTemplates = $this->documentService->getAvailableDocuments($loanAccount);
        
        // Get saved client loan documents
        $savedDocuments = $loanAccount->clientLoanDocuments;
        
        // Get the global first unpaid instalment number to handle sequential locking correctly across pagination
        $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
        if ($isKandhuvatti) {
            $firstUnpaid = Emi::where('loan_account_id', $decodedLoanId)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->orderBy('instalment_number', 'asc')
                ->first();
        } else {
            // Skip EMIs fully covered by in_progress (pending approval) collections
            $firstUnpaid = Emi::where('loan_account_id', $decodedLoanId)
                ->whereIn('status', ['pending', 'overdue', 'partial'])
                ->where(function($q) {
                    $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                })
                ->orderBy('instalment_number', 'asc')
                ->first();
        }
        $firstUnpaidInstalment = $firstUnpaid ? $firstUnpaid->instalment_number : 999999;

        // Calculate principal and interest paid for summary
        $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
        if ($isKandhuvatti) {
            $principalPaid = Emi::where('loan_account_id', $decodedLoanId)->sum('principal_amount');
            $interestPaid = max(0, (float)$loanAccount->paid_amount - $principalPaid);
        } else {
            $allEmis = Emi::where('loan_account_id', $decodedLoanId)->get();
            $principalPaid = $allEmis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return (float)($emi->principal_amount ?? 0);
                return max(0, $alreadyPaid - $interestPart);
            });
            $interestPaid = $allEmis->sum(function($emi) {
                $alreadyPaid = (float)($emi->paid_amount ?? 0);
                $interestPart = (float)($emi->interest_amount ?? 0);
                if ($emi->status === 'paid') return $interestPart;
                return min($alreadyPaid, $interestPart);
            });
        }
        $partialPaymentConfig = \App\Models\LoanConfiguration::getPartialPaymentConfig();

        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)
            ->orderBy('account_name')
            ->get();

        $walletBalance = 0.0;
        if ($loanAccount->client_id) {
            $walletBalance = app(\App\Services\FixedDeposit\WalletService::class)
                ->balanceForClient((int) $loanAccount->client_id);
        }

        return view('admin.clients.client-loan-emi-details', compact(
            'loanAccount', 
            'client', 
            'emis', 
            'availableTemplates', 
            'savedDocuments', 
            'partialPaymentConfig', 
            'firstUnpaidInstalment',
            'principalPaid',
            'interestPaid',
            'bankAccounts',
            'walletBalance',
        ));
    }

    public function generateDocument($loanId, $documentType)
    {
        try {
            $decodedLoanId = \App\Support\HashId::decode($loanId) ?? $loanId;
            // Use the service to generate, save to storage, and record in DB
            $document = $this->documentService->generateAndSaveDocument($decodedLoanId, $documentType);
            
            // Get the loan account to construct the file path for response
            $loanAccount = LoanAccount::findOrFail($decodedLoanId);
            $filePath = "loan_documents/{$loanAccount->account_number}/{$document['fileName']}";

            return response()->json([
                'success' => true,
                'message' => $document['message'],
                'pdf_url' => asset('storage/' . $filePath),
                'file_name' => $document['fileName']
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateDocument error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate document: ' . $e->getMessage()
            ], 500);
        }
    }

    public function viewDocument($loanId, $documentType)
    {
        try {
            return $this->streamLoanDocument($loanId, $documentType, 'inline');
        } catch (\Exception $e) {
            Log::error('ViewDocument error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Document error</title></head><body style="font-family:sans-serif;padding:40px;">'
                . '<h3>Unable to open loan document</h3>'
                . '<p>' . e($e->getMessage()) . '</p>'
                . '<p>Please click <strong>Regenerate All</strong> on the loan account page and try again.</p>'
                . '</body></html>',
                500
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    public function downloadDocument($loanId, $documentType)
    {
        try {
            return $this->streamLoanDocument($loanId, $documentType, 'attachment');
        } catch (\Exception $e) {
            Log::error('DownloadDocument error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Document error</title></head><body style="font-family:sans-serif;padding:40px;">'
                . '<h3>Unable to download loan document</h3>'
                . '<p>' . e($e->getMessage()) . '</p>'
                . '</body></html>',
                500
            )->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    /**
     * Generate (or refresh) a loan PDF and stream it. Old cached files can be hundreds
     * of blank pages from a previous layout bug, so view/download always regenerates.
     */
    private function streamLoanDocument($loanId, $documentType, string $disposition)
    {
        $decodedLoanId = \App\Support\HashId::decode((string) $loanId) ?? $loanId;
        $documentType = urldecode((string) $documentType);

        Log::info('StreamLoanDocument called', [
            'loanId' => $loanId,
            'decodedLoanId' => $decodedLoanId,
            'documentType' => $documentType,
            'disposition' => $disposition,
        ]);

        $document = $this->documentService->generateAndSaveDocument((int) $decodedLoanId, $documentType);

        return response($document['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $document['fileName'] . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Process EMI payment
     * - Agents: auto-verify and process payment immediately
     * - Admin/Staff: processes payment immediately
     */
    public function payEmi(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'emi_id'           => 'required',
                'paid_amount'      => 'required|numeric|min:0.01',
                'principal_amount' => 'nullable|numeric|min:0',
                'paid_date'        => 'required|date',
                'payment_method'   => 'required|in:cash,upi,bank_transfer,in_hand',
                'payment_reference'=> 'nullable|string|max:255',
                'remarks'          => 'nullable|string',
                'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
            ]);

            $emiId       = $validated['emi_id'];
            $decodedEmiId = \App\Support\HashId::decode($emiId) ?? $emiId;
            $emi         = Emi::with('loanAccount')->findOrFail($decodedEmiId);
            $loanAccount = $emi->loanAccount;
            $currentUser = auth()->user();
            $isAgent     = $currentUser->hasRole('Agent');

            // ── AGENT PATH: auto-verify and process payment immediately ───────────────
            if ($isAgent) {
                $agent   = optional($currentUser->agent);
                $agentId = $agent->id ?? null;

                DB::beginTransaction();
                try {
                    $pendingAmount = max(0, $emi->pending_amount);
                    $paymentType   = ((float)$validated['paid_amount'] >= ($pendingAmount - 0.01)) ? 'full' : 'partial';

                    $existing = \App\Models\EmiCollection::where('emi_id', $emi->id)
                        ->where('status', 'in_progress')
                        ->first();

                    if ($existing) {
                        $newAmount = $existing->amount + (float)$validated['paid_amount'];
                        $isNowFull = ($newAmount >= ($pendingAmount - 0.01));
                        $existing->update([
                            'amount'         => $newAmount,
                            'payment_type'   => $isNowFull ? 'full' : 'partial',
                            'payment_method' => $validated['payment_method'],
                            'collected_at'   => $validated['paid_date'],
                            'status'         => 'verified',
                            'verified_by'    => auth()->id(),
                            'verified_at'    => now(),
                            'remarks'        => trim(($existing->remarks ?? '') . "\n[Agent Updated via Client EMI View]"),
                            'bank_account_id'=> $validated['internal_bank_account_id'] ?? $existing->bank_account_id,
                        ]);
                        $collection = $existing;
                    } else {
                        $collection = \App\Models\EmiCollection::create([
                            'agent_id'          => $agentId,
                            'emi_id'            => $emi->id,
                            'amount'            => $validated['paid_amount'],
                            'payment_method'    => $validated['payment_method'],
                            'payment_type'      => $paymentType,
                            'payment_reference' => $validated['payment_reference'] ?? null,
                            'status'            => 'verified',
                            'collected_at'      => $validated['paid_date'],
                            'verified_by'       => auth()->id(),
                            'verified_at'       => now(),
                            'remarks'           => trim(($validated['remarks'] ?? '') . ' [Agent Collected via Client EMI View]'),
                            'bank_account_id'   => $validated['internal_bank_account_id'] ?? null,
                        ]);
                    }

                    if ($agentId) {
                        \App\Models\AgentActivity::create([
                            'emi_id'      => $emi->id,
                            'agent_id'    => $agentId,
                            'type'        => 'payment',
                            'description' => '₹' . number_format($validated['paid_amount'], 2),
                            'method'      => strtoupper(str_replace('_', ' ', $validated['payment_method'])),
                            'reference'   => $validated['payment_reference'] ?? null,
                            'remarks'     => $validated['remarks'] ?? null,
                            'action_at'   => $validated['paid_date'],
                        ]);
                    }

                    \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                        ->whereIn('status', ['assigned', 'visited'])
                        ->update(['status' => 'resolved', 'resolved_at' => now()]);

                    $paymentService = app(\App\Services\LoanPaymentService::class);
                    $result = $paymentService->processPayment(
                        $decodedEmiId,
                        $validated['paid_amount'],
                        $validated['paid_date'],
                        $validated['payment_method'],
                        $validated['payment_reference'],
                        $collection->remarks,
                        true,
                        $validated['principal_amount'] ?? 0,
                        false,
                        $validated['internal_bank_account_id'] ?? null
                    );

                    if (!$result['success']) {
                        throw new \Exception($result['message']);
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                $emi->refresh();
                $loanAccount->refresh();
                $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
                $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                if (strlen($cleanMobile) === 10) {
                    $cleanMobile = '91' . $cleanMobile;
                }

                $smsData = [
                    'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                    'mobile_no' => $cleanMobile,
                    'account_no' => $loanAccount->account_number,
                    'amount_paid' => ($loanAccount->loan_mode === 'interest_only' && ($validated['principal_amount'] ?? 0) > 0.001 && $validated['paid_amount'] <= 0.001) ? $validated['principal_amount'] : $validated['paid_amount'],
                    'remaining_balance' => $loanAccount->outstanding_amount,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => ($loanAccount->loan_mode === 'interest_only' && ($validated['principal_amount'] ?? 0) > 0.001) ? 'principal' : (($loanAccount->loan_mode === 'interest_only') ? 'interest' : 'emi'),
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => ($emi->status !== 'paid'),
                    'emi_balance' => max(0, $emi->pending_amount),
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

                return response()->json([
                    'success' => true,
                    'message' => 'Repayment processed successfully.',
                    'sms_data' => $smsData
                ]);
            }

            // ── ADMIN / STAFF PATH ────────────────────────────────────────────────────
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $result = $paymentService->processPayment(
                $decodedEmiId,
                $validated['paid_amount'],
                $validated['paid_date'],
                $validated['payment_method'],
                $validated['payment_reference'],
                $validated['remarks'] ?? 'Paid via Client Portal',
                false,
                $validated['principal_amount'] ?? 0,
                false,
                $validated['internal_bank_account_id'] ?? null
            );

            if (!$result['success']) {
                return response()->json(['success' => false, 'message' => $result['message']], 400);
            }

            $emi->refresh();
            $loanAccount->refresh();
            $isFullyPaid = ($emi->status === 'paid');

            if ($loanAccount) {
                $paymentService->syncLoanTotals($loanAccount->id);
                $loanAccount->refresh();

                $pendingEmis = $loanAccount->emis()->whereIn('status', ['pending', 'overdue', 'partial'])->count();
                if ($pendingEmis === 0 && $loanAccount->outstanding_amount <= 0.01) {
                    $loanAccount->status   = 'closed';
                    $loanAccount->closed_at = $loanAccount->closed_at ?? now();
                    $loanAccount->save();

                    try {
                        event(new \App\Events\GenerateDocument($loanAccount));
                        sleep(3);
                        $emailService = app(\App\Services\LoanDocumentEmailService::class);
                        $emailService->sendLoanDocumentsEmail($loanAccount->id, 'loan_closed');
                    } catch (\Exception $e) {
                        Log::error('Loan closure email exception', ['loan_id' => $loanAccount->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            try {
                $emailService = app(\App\Services\LoanDocumentEmailService::class);
                $emailService->sendPaymentReceiptEmail($emi->id);
            } catch (\Exception $e) {
                Log::error('Payment receipt email exception', ['emi_id' => $emi->id, 'error' => $e->getMessage()]);
            }

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
                'amount_paid' => ($loanAccount->loan_mode === 'interest_only' && ($validated['principal_amount'] ?? 0) > 0.001 && $validated['paid_amount'] <= 0.001) ? $validated['principal_amount'] : $validated['paid_amount'],
                'remaining_balance' => $remainingBalance,
                'loan_mode' => $loanAccount->loan_mode,
                'payment_type' => ($loanAccount->loan_mode === 'interest_only' && ($validated['principal_amount'] ?? 0) > 0.001) ? 'principal' : (($loanAccount->loan_mode === 'interest_only') ? 'interest' : 'emi'),
                'application_number' => $loanAccount->application_number,
                'is_partial' => !$isFullyPaid,
                'emi_balance' => $emi->pending_amount,
            ];
            $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

            return response()->json([
                'success' => true,
                'message' => $isFullyPaid ? 'EMI fully paid successfully.' : 'Partial payment recorded successfully.',
                'sms_data' => $smsData
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . collect($e->errors())->flatten()->implode(', '),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayEmi error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to process payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get payment history for a specific EMI (for client portal)
     */
    public function getEmiHistory($emiId): JsonResponse
    {
        try {
            $decodedEmiId = \App\Support\HashId::decode($emiId) ?? $emiId;
            $emi = Emi::findOrFail($decodedEmiId);
            
            // Check if this EMI belongs to the client (Security check)
            // Assuming the client is authenticated
            $client = auth()->user()->client; // Or however client is linked to user
            
            // If admin is viewing client portal, we might need a different check
            // For now, just load the collections
            
            $rawCollections = EmiCollection::where('emi_id', $emi->id)
                ->with(['agent:id,agent_name', 'verifiedBy:id,name'])
                ->orderBy('created_at', 'asc')
                ->get();
            
            $interestLimit = (float)$emi->interest_amount;
            $interestRemaining = $interestLimit;
            
            $collections = $rawCollections->map(function ($c) use (&$interestRemaining, $emi) {
                $amount = (float)$c->amount;
                
                $isKandhuvatti = ($emi->loanAccount && $emi->loanAccount->loan_mode === 'interest_only');
                if ($isKandhuvatti) {
                    $interestPaid = 0.00;
                    $principalPaid = 0.00;
                    $hasStructuredRemarks = false;
                    
                    if (preg_match('/Interest:\s*₹?\s*([0-9,.]+)/', $c->remarks ?? '', $intMatches)) {
                        $interestPaid = (float) str_replace(',', '', $intMatches[1]);
                        $hasStructuredRemarks = true;
                    }
                    if (preg_match('/Principal:\s*₹?\s*([0-9,.]+)/', $c->remarks ?? '', $priMatches)) {
                        $principalPaid = (float) str_replace(',', '', $priMatches[1]);
                        $hasStructuredRemarks = true;
                    }
                    
                    if (!$hasStructuredRemarks) {
                        $emiPrincipal = (float)($emi->principal_amount ?? 0);
                        if ($emiPrincipal > 0) {
                            $principalPaid = min($amount, $emiPrincipal);
                            $interestPaid = max(0.00, $amount - $principalPaid);
                        } else {
                            $interestPaid = $amount;
                        }
                    }
                    
                    $amount = $interestPaid;
                } else {
                    // Interest portion is cleared first up to the interest limit of this EMI
                    $interestPaid = min($amount, $interestRemaining);
                    $interestRemaining = max(0.00, $interestRemaining - $interestPaid);
                    
                    $principalPaid = max(0.00, $amount - $interestPaid);
                }
                
                $approverName = 'System';
                $role = 'System';
                if ($c->agent) {
                    $approverName = $c->agent->agent_name;
                    $role = 'Agent';
                } elseif ($c->verifiedBy) {
                    $approverName = $c->verifiedBy->name;
                    $role = 'Admin';
                }
                return [
                    'id' => $c->getRouteKey(),
                    'date' => $c->collected_at ? $c->collected_at->format('d-m-Y') : $c->created_at->format('d-m-Y'),
                    'amount' => number_format($amount, 2),
                    'principal_paid' => number_format($principalPaid, 2),
                    'interest_paid' => number_format($interestPaid, 2),
                    'raw_principal_paid' => $principalPaid,
                    'raw_interest_paid' => $interestPaid,
                    'is_kandhuvatti' => $isKandhuvatti,
                    'method' => ucfirst(str_replace('_', ' ', $c->payment_method)),
                    'reference' => $c->payment_reference ?: 'N/A',
                    'type' => ucfirst(str_replace('_', ' ', $c->payment_type)),
                    'agent' => $approverName,
                    'role' => $role,
                    'status' => $c->status,
                ];
            })->reverse()->values();

            return response()->json([
                'success' => true,
                'status' => $emi->status,
                'status_label' => $this->getStatusMeta($emi->status)['label'],
                'status_color' => $this->getStatusMeta($emi->status)['color'],
                'paid_amount' => number_format($emi->paid_amount, 2),
                'total_amount' => number_format($emi->total_amount, 2),
                'collections' => $collections
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getStatusMeta($status): array
    {
        $map = [
            'paid'    => ['label' => 'Paid',    'color' => 'success'],
            'partial' => ['label' => 'Partial', 'color' => 'info'],
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
        ];

        return $map[$status] ?? ['label' => 'Unknown', 'color' => 'secondary'];
    }

    public function generateOpenLoanCycles(Request $request, $loanId)
    {
        try {
            $decodedLoanId = \App\Support\HashId::decode($loanId) ?? $loanId;
            $loanAccount = LoanAccount::with(['emis', 'loanApplication'])->findOrFail($decodedLoanId);

            $cycleService = app(\App\Services\OpenLoanCycleService::class);
            if (! $cycleService->isOpenLoan($loanAccount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cycles can only be generated for Open Loans (Interest-Only).',
                ], 422);
            }

            if (strtolower((string) $loanAccount->status) !== 'active' || $loanAccount->closed_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot generate cycles for an inactive or closed loan.',
                ], 422);
            }

            if ($cycleService->outstandingPrincipal($loanAccount) <= 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remaining principal is ₹0.00. No further interest cycles are needed.',
                ], 422);
            }

            $validated = $request->validate([
                'cycle_count' => 'required|integer|min:1|max:365',
            ]);

            $count = (int) $validated['cycle_count'];
            $created = $cycleService->generateManualCycles($loanAccount, $count);

            if ($created <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No new cycles were generated. Please check loan balance or status.',
                ], 400);
            }

            // Keep balances and totals in sync
            $paymentService = app(\App\Services\LoanPaymentService::class);
            $paymentService->syncEmiBalances($loanAccount->id);
            $paymentService->syncLoanTotals($loanAccount->id);

            $frequency = $cycleService->frequencyFor($loanAccount);
            $unitLabel = match ($frequency) {
                'daily' => 'day(s)',
                'weekly' => 'week(s)',
                default => 'month(s)',
            };

            return response()->json([
                'success' => true,
                'message' => "Successfully generated {$created} interest cycle(s) ({$count} {$unitLabel}).",
                'created_count' => $created,
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => collect($ve->errors())->flatten()->first() ?? 'Invalid cycle count provided.',
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to generate open loan cycles: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate cycles: ' . $e->getMessage(),
            ], 500);
        }
    }
}
