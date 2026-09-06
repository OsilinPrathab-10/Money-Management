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
        $loanAccounts = LoanAccount::where('client_id', $decodedId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $loanApplications = \App\Models\LoanApplication::with('product')
            ->where('client_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('admin.clients.client-view-loans', compact('client', 'loanAccounts', 'loanApplications'));
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

        // Paginate EMIs - 10 per page
        $emis = Emi::with('collections')
            ->where('loan_account_id', $decodedLoanId)
            ->orderBy('instalment_number', 'asc')
            ->paginate(10);
        
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

        return view('admin.clients.client-loan-emi-details', compact(
            'loanAccount', 
            'client', 
            'emis', 
            'availableTemplates', 
            'savedDocuments', 
            'partialPaymentConfig', 
            'firstUnpaidInstalment',
            'principalPaid',
            'interestPaid'
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
            $decodedLoanId = \App\Support\HashId::decode($loanId) ?? $loanId;
            Log::info('ViewDocument called', ['loanId' => $loanId, 'decodedLoanId' => $decodedLoanId, 'documentType' => $documentType]);
            
            // Check if document already exists in database
            $existingDocument = $this->documentService->getExistingDocument($decodedLoanId, $documentType);
            
            if ($existingDocument && Storage::disk('public')->exists($existingDocument->file_path)) {
                // Serve existing document from storage
                Log::info('Serving existing document from storage', ['file_path' => $existingDocument->file_path]);
                
                $fileContent = Storage::disk('public')->get($existingDocument->file_path);
                
                return response($fileContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $existingDocument->file_name . '"'
                ]);
            }
            
            // Check if template exists
            $template = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($documentType)])->first();
            if (!$template && $documentType === 'loan_agreement') {
                $loanAccount = LoanAccount::find($decodedLoanId);
                $loanProduct = $loanAccount->loanApplication->product ?? null;
                if ($loanProduct) {
                    $productSpecificType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name ?? ''));
                    $template = LoanDocumentTemplate::whereRaw('LOWER(type) = ?', [strtolower($productSpecificType)])->first();
                    if ($template) {
                        $documentType = $productSpecificType;
                    }
                }
            }
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document template not found. Please create a template for "' . ucfirst(str_replace('_', ' ', $documentType)) . '" first in the admin panel.',
                    'redirect' => route('loan-document-templates.index')
                ], 404);
            }
            
            // Generate new document and save to database
            $document = $this->documentService->generateAndSaveDocument($decodedLoanId, $documentType);

            return response($document['binary'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $document['fileName'] . '"'
            ]);
        } catch (\Exception $e) {
            Log::error('ViewDocument error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Failed to view document: ' . $e->getMessage());
        }
    }

    public function downloadDocument($loanId, $documentType)
    {
        try {
            $decodedLoanId = \App\Support\HashId::decode($loanId) ?? $loanId;
            // Check if document already exists in database
            $existingDocument = $this->documentService->getExistingDocument($decodedLoanId, $documentType);
            
            if ($existingDocument && Storage::disk('public')->exists($existingDocument->file_path)) {
                // Serve existing document from storage
                Log::info('Downloading existing document from storage', ['file_path' => $existingDocument->file_path]);
                
                $fileContent = Storage::disk('public')->get($existingDocument->file_path);
                
                return response($fileContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $existingDocument->file_name . '"'
                ]);
            }
            
            // Check if template exists
            $template = LoanDocumentTemplate::where('type', $documentType)->first();
            if (!$template && $documentType === 'loan_agreement') {
                $loanAccount = LoanAccount::find($loanId);
                $loanProduct = $loanAccount->loanApplication->product ?? null;
                if ($loanProduct) {
                    $productSpecificType = 'loan_agreement_' . strtolower(str_replace(' ', '_', $loanProduct->loan_name ?? ''));
                    $template = LoanDocumentTemplate::where('type', $productSpecificType)->first();
                    if ($template) {
                        $documentType = $productSpecificType;
                    }
                }
            }
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document template not found. Please create a template for "' . ucfirst(str_replace('_', ' ', $documentType)) . '" first in the admin panel.',
                    'redirect' => route('loan-document-templates.index')
                ], 404);
            }
            
            // Generate new document and save to database
            $document = $this->documentService->generateAndSaveDocument($loanId, $documentType);

            return response($document['binary'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $document['fileName'] . '"'
            ]);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to download document: ' . $e->getMessage());
        }
    }

    /**
     * Process EMI payment
     * - Agents: creates a pending EmiCollection (awaits admin approval)
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
                'remarks'          => 'nullable|string'
            ]);

            $emiId       = $validated['emi_id'];
            $decodedEmiId = \App\Support\HashId::decode($emiId) ?? $emiId;
            $emi         = Emi::with('loanAccount')->findOrFail($decodedEmiId);
            $loanAccount = $emi->loanAccount;
            $currentUser = auth()->user();
            $isAgent     = $currentUser->hasRole('Agent');

            // ── AGENT PATH ────────────────────────────────────────────────────────────
            // Create a pending collection; admin must approve before the EMI is marked paid
            if ($isAgent) {
                $agent   = optional($currentUser->agent);
                $agentId = $agent->id ?? null;

                DB::beginTransaction();
                try {
                    $pendingAmount = max(0, $emi->pending_amount);
                    $paymentType   = ((float)$validated['paid_amount'] >= ($pendingAmount - 0.01)) ? 'full' : 'partial';

                    // Upsert: accumulate onto any existing in_progress collection for this EMI
                    $existing = \App\Models\EmiCollection::where('emi_id', $emi->id)
                        ->where('status', 'in_progress')
                        ->first();

                    if ($existing) {
                        $newAmount = $existing->amount + (float)$validated['paid_amount'];
                        $isNowFull = ($newAmount >= ($pendingAmount - 0.01));
                        $existing->update([
                            'amount'       => $newAmount,
                            'payment_type' => $isNowFull ? 'full' : 'partial',
                            'payment_method'=> $validated['payment_method'],
                            'collected_at' => $validated['paid_date'],
                            'remarks'      => trim(($existing->remarks ?? '') . "\n[Agent Updated via Client EMI View]"),
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
                            'status'            => 'in_progress',
                            'collected_at'      => $validated['paid_date'],
                            'remarks'           => trim(($validated['remarks'] ?? '') . ' [Agent Created via Client EMI View]'),
                        ]);
                    }

                    // Log agent activity
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

                    // Mark active assignment as resolved
                    \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                        ->whereIn('status', ['assigned', 'visited'])
                        ->update(['status' => 'resolved', 'resolved_at' => now()]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                $client = $loanAccount->loanApplication->client ?? $loanAccount->client;
                $mobileNo = $client->mobile_no ?? $client->client_phone ?? '';
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
                if (strlen($cleanMobile) === 10) {
                    $cleanMobile = '91' . $cleanMobile;
                }

                // For agent (pending) submissions, processPayment has NOT run yet, so
                // outstanding_amount is still the pre-payment value. Estimate the expected
                // post-payment balance so the SMS shows the correct anticipated balance.
                $isKandhuvatti = ($loanAccount->loan_mode === 'interest_only');
                $amountPaidForSms = ($isKandhuvatti && ($validated['principal_amount'] ?? 0) > 0.001 && $validated['paid_amount'] <= 0.001)
                    ? $validated['principal_amount']
                    : $validated['paid_amount'];
                $paymentTypeForSms = ($isKandhuvatti && ($validated['principal_amount'] ?? 0) > 0.001) ? 'principal'
                    : ($isKandhuvatti ? 'interest' : 'emi');
                $remainingBalance = $loanAccount->outstanding_amount;
                if ($isKandhuvatti) {
                    if ($paymentTypeForSms === 'principal') {
                        $remainingBalance = max(0, $remainingBalance - $amountPaidForSms);
                    }
                } else {
                    $isReducing = $loanAccount->loanApplication && $loanAccount->loanApplication->product
                        && in_array($loanAccount->loanApplication->product->interest_type, ['reducing', 'declining_balance']);
                    if ($isReducing) {
                        $interestPart   = (float)($emi->interest_amount ?? 0);
                        $alreadyPaid    = (float)($emi->paid_amount ?? 0);
                        $unpaidInterest = max(0, $interestPart - min($alreadyPaid, $interestPart));
                        $principalHere  = max(0, $amountPaidForSms - $unpaidInterest);
                        $remainingBalance = max(0, $remainingBalance - $principalHere);
                    } else {
                        $remainingBalance = max(0, $remainingBalance - $amountPaidForSms);
                    }
                }

                $smsData = [
                    'client_name' => ($client->first_name ?? '') . ' ' . ($client->last_name ?? ''),
                    'mobile_no' => $cleanMobile,
                    'account_no' => $loanAccount->account_number,
                    'amount_paid' => $amountPaidForSms,
                    'remaining_balance' => $remainingBalance,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => $paymentTypeForSms,
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => ($paymentType === 'partial'),
                    'emi_balance' => max(0, $emi->pending_amount - $validated['paid_amount']),
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

                return response()->json([
                    'success' => true,
                    'message' => 'Repayment submitted successfully and is awaiting Admin approval.',
                    'pending_approval' => true,
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
                $validated['principal_amount'] ?? 0
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
}
