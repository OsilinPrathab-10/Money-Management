<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanAccount;
use App\Models\Client;
use App\Models\Emi;
use App\Models\LoanConfiguration;
use App\Services\LoanDocumentService;
use App\Services\LoanDocumentEmailService;
use App\Services\LoanPaymentService;
use App\Services\PartialPaymentConfigService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;



class LoanAccountsController extends Controller
{
    protected $documentService;

    public function __construct(LoanDocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display all loan accounts with statistics
     */
    public function index()
    {
        $currentUser = Auth::user();
        $isAgent = $currentUser->hasRole('Agent');
        $agentId = $isAgent ? optional($currentUser->agent)->id : null;

        $activeQuery = LoanAccount::where('status', 'active');
        $closedQuery = LoanAccount::where('status', 'closed');

        if ($isAgent && $agentId) {
            $activeQuery->whereHas('client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('added_by', $agentId);
            });
            $closedQuery->whereHas('client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('added_by', $agentId);
            });
        }

        $activeLoans = $activeQuery->count();
        $closedLoans = $closedQuery->count();

        return view('admin.loan-management.loan-accounts.loan-accounts', compact(
            'activeLoans',
            'closedLoans'
        ));
    }

    /**
     * Get loan accounts data for DataTables (AJAX)
     */
    public function data(Request $request)
    {
        $columns = [
            1 => 'account_number',
            3 => 'client_id', // Client Name
            6 => 'loan_amount',
            10 => 'status',
        ];

        // Build base query
        $query = LoanAccount::with([
            'client.location',
            'loanApplication.product',
            'emis'
        ]);

        $currentUser = Auth::user();
        if ($currentUser->hasRole('Agent')) {
            $agentId = optional($currentUser->agent)->id;
            if ($agentId) {
                $query->whereHas('client', function($q) use ($agentId) {
                    $q->where('assigned_to', $agentId)
                      ->orWhere('added_by', $agentId);
                });
            }
        }

        // Filter by status if provided
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Date range filtering
        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('disbursed_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('disbursed_at', '<=', $request->to_date);
        }

        // Get total counts
        $totalData = $query->count();
        $totalFiltered = $totalData;

        // DataTables parameters
        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'created_at';
        $dir = $request->input('order.0.dir') ?? 'desc';

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'LIKE', "%{$search}%")
                  ->orWhere('application_number', 'LIKE', "%{$search}%")
                  ->orWhere('loan_amount', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('loanApplication.product', function($q) use ($search) {
                      $q->where('loan_name', 'LIKE', "%{$search}%");
                  });
            });

            $totalFiltered = $query->count();
        }

        // Apply pagination and ordering
        $loanAccounts = $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        // Calculate EMI amount and format data
        $data = $loanAccounts->map(function ($loan, $index) use ($start, $totalData) {
            $emiCount = $loan->emis->count();
            if ($loan->loan_mode === 'interest_only') {
                $nextEmi = $loan->emis->where('status', '!=', 'paid')->sortBy('instalment_number')->first();
                $emiAmount = $nextEmi ? $nextEmi->interest_amount : ($loan->outstanding_amount * ($loan->interest_rate / 100));
            } else {
                $emiAmount = $emiCount > 0 ? $loan->total_payable / $emiCount : 0;
            }

            return [
                'id' => $loan->getRouteKey(),
                'sno' => $totalData - $start - $index,
                'account_number' => $loan->account_number,
                'application_number' => $loan->application_number ?? 'N/A',
                'client_name' => $loan->client->client_name ?? 'N/A',
                'zone' => $loan->client->location->name ?? 'N/A',
                'client_phone' => $loan->client->client_phone ?? 'N/A',
                'customer_id' => $loan->client->client_phone ?? 'N/A',
                'client_id' => $loan->client ? $loan->client->getRouteKey() : null,
                'loan_name' => optional($loan->loanApplication->product)->loan_name ?? 'N/A',
                'loan_amount' => $loan->loan_amount,
                'loan_amount_formatted' => '₹' . number_format($loan->loan_amount, 0),
                'total_payable' => $loan->total_payable,
                'total_payable_formatted' => '₹' . number_format($loan->total_payable, 2),
                'outstanding_amount' => $loan->outstanding_amount,
                'outstanding_amount_formatted' => '₹' . number_format($loan->outstanding_amount, 2),
                'emi_amount' => $emiAmount,
                'emi_amount_formatted' => '₹' . number_format($emiAmount, 2),
                'tenure' => $loan->tenure,
                'tenure_formatted' => $loan->tenure . ' ' . (optional($loan->loanApplication)->term_unit ?? 'months'),
                'interest_rate' => $loan->interest_rate,
                'status' => $loan->status,
                'status_label' => ucfirst($loan->status),
                'disbursed_at' => $loan->disbursed_at ? $loan->disbursed_at->format('d-m-Y') : 'N/A',
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }


    /**
     * Display individual loan account details
     */
    public function view($id)
    {
        $loanAccount = LoanAccount::with([
            'emis' => function($query) {
                $query->orderBy('instalment_number', 'asc');
            },
            'loanApplication.client',
            'client', // Add relationship to fetch saved documents
            'loanApplication.product',
            'clientLoanDocuments' // Add relationship to fetch saved documents
        ])->findOrFail($id);
        
        // Agent guard: agents can only view loans for clients they added or are assigned to
        if (auth()->user()->hasRole('Agent')) {
            $agentId = optional(auth()->user()->agent)->id;
            $client = $loanAccount->client;
            if (!$agentId || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
                abort(403, 'You do not have permission to view this loan account.');
            }
        }

        $client = $loanAccount->client ?? ($loanAccount->loanApplication ? $loanAccount->loanApplication->client : null);
        $emis = $loanAccount->emis->sortBy('instalment_number');

        // Get available document templates based on loan status and product
        $availableTemplates = $this->documentService->getAvailableDocuments($loanAccount);

        // Get saved client loan documents
        $savedDocuments = $loanAccount->clientLoanDocuments;

        return view('admin.loan-management.loan-accounts.view-loan-account', compact(
            'loanAccount',
            'client',
            'emis',
            'availableTemplates',
            'savedDocuments' // Pass saved documents to view
        ));
    }

    /**
     * Regenerate all documents for a loan account
     */
    public function regenerateDocuments($id)
    {
        try {
            $loanAccount = LoanAccount::findOrFail($id);
            $this->documentService->generateAndSaveAllDocuments($loanAccount);
            
            return response()->json([
                'success' => true,
                'message' => 'All documents regenerated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to regenerate documents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate documents: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update global foreclosure configuration
     */
    public function updateForeclosureConfig(Request $request)
    {
        $request->validate([
            'eligibility_months' => 'nullable|integer|min:1',
            'charges_percentage' => 'nullable|numeric|min:0|max:100',
            'foreclosure_notes' => 'nullable|string|max:1000'
        ]);

        try {
            // Update config file
            $configPath = config_path('foreclosure.php');
            $eligibilityMonths = $request->eligibility_months ? $request->eligibility_months : 'null';
            $chargesPercentage = $request->charges_percentage ?? 0;

            $config = "<?php\n\nreturn [\n";
            $config .= "    'eligibility_months' => {$eligibilityMonths},\n";
            $config .= "    'charges_percentage' => {$chargesPercentage},\n";
            $config .= "];\n";

            $result = file_put_contents($configPath, $config);

            if ($result === false) {
                throw new \Exception('Failed to write config file');
            }

            // Clear config cache
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Global foreclosure configuration updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get foreclosure information for a loan account
     */
    public function foreclosureInfo($id)
    {
        $loanAccount = LoanAccount::with('emis')->findOrFail($id);

        $eligibilityMonths = $loanAccount->getForeclosureEligibilityMonths();
        $chargesPercentage = $loanAccount->getForeclosureChargesPercentage();

        $paidEmisCount = $loanAccount->emis()->where('status', 'paid')->count();
        $isEligible = $paidEmisCount >= $eligibilityMonths;

        // Check if the ongoing EMI is partially paid
        $ongoingEmi = $loanAccount->emis()
            ->whereNotIn('status', ['paid', 'carried_forward'])
            ->orderBy('instalment_number', 'asc')
            ->first();

        $hasPartialEmi = false;
        if ($ongoingEmi && ($ongoingEmi->status === 'partial' || $ongoingEmi->is_partial_paid || ($ongoingEmi->paid_amount > 0 && $ongoingEmi->pending_amount > 0))) {
            $hasPartialEmi = true;
            $isEligible = false;
        }

        $amounts = app(LoanPaymentService::class)->calculateForeclosureAmounts($loanAccount, [
            'charges_percentage' => $chargesPercentage,
        ]);

        $outstandingAmount = $amounts['outstanding_amount'];
        $interestOutstanding = $amounts['interest_outstanding'];
        $foreclosureCharges = $amounts['foreclosure_charges'];
        $totalAmount = $amounts['total_amount'];

        $application = $loanAccount->loanApplication;
        $termUnit = $application ? strtolower((string)$application->term_unit) : 'monthly';
        $displayUnit = match($termUnit) {
            'daily', 'day', 'days' => 'days',
            'weekly', 'week', 'weeks' => 'weeks',
            default => 'months'
        };
        $cycleLabel = in_array($termUnit, ['week', 'weeks', 'weekly']) ? 'week' : (in_array($termUnit, ['day', 'days', 'daily']) ? 'day' : 'month');

        $interestLabel = $amounts['includes_current_month_interest']
            ? "Interest outstanding (incl. current {$cycleLabel})"
            : 'Interest outstanding';

        $breakdown = [
            [
                'key' => 'principal_outstanding',
                'label' => 'Principal outstanding',
                'amount' => $outstandingAmount,
                'formatted' => number_format($outstandingAmount, 0),
            ],
            [
                'key' => 'interest_outstanding',
                'label' => $interestLabel,
                'amount' => $interestOutstanding,
                'formatted' => number_format($interestOutstanding, 0),
            ],
            [
                'key' => 'foreclosure_charges',
                'label' => 'Foreclosure charges',
                'percentage' => $chargesPercentage,
                'amount' => $foreclosureCharges,
                'formatted' => number_format($foreclosureCharges, 0),
            ],
        ];

        return response()->json([
            'eligibility_months'   => $eligibilityMonths,
            'charges_percentage'   => $chargesPercentage,
            'paid_emis_count'      => $paidEmisCount,
            'is_eligible'          => $isEligible,
            'has_partial_emi'      => $hasPartialEmi,
            'outstanding_amount'   => $outstandingAmount,
            'interest_outstanding' => $interestOutstanding,
            'foreclosure_charges'  => $foreclosureCharges,
            'total_amount'         => $totalAmount,
            'includes_current_month_interest' => $amounts['includes_current_month_interest'],
            'currency_symbol'      => '₹',
            'breakdown'            => $breakdown,
            'total_payable'        => $totalAmount,
            'total_payable_formatted' => number_format($totalAmount, 0),
            'eligibility_unit'     => $displayUnit,
        ]);
    }

    /**
     * Get prepayment information for a loan account
     */
    public function prepaymentInfo(Request $request, $id)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01'
        ]);

        $amount = $request->input('amount');

        $loanAccount = LoanAccount::with('emis')->findOrFail($id);

        $eligibilityMonths = $loanAccount->getPrepaymentEligibilityMonths();
        $chargesPercentage = $loanAccount->getPrepaymentChargesPercentage();

        $paidEmisCount = $loanAccount->emis()->where('status', 'paid')->count();
        $isEligible = $paidEmisCount >= $eligibilityMonths;

        $unpaidEmis = $loanAccount->emis()->where('status', '!=', 'paid')->get();
        $sumOfEmiPrincipals = $unpaidEmis->sum(function($emi) {
            $alreadyPaid = (float)($emi->paid_amount ?? 0);
            $interestPart = (float)($emi->interest_amount ?? 0);
            $principalPaid = max(0, $alreadyPaid - $interestPart);
            return max(0, (float)($emi->principal_amount ?? 0) - $principalPaid);
        });

        if ($sumOfEmiPrincipals <= 0.01) {
            $ratio = ($loanAccount->loan_amount > 0 && $loanAccount->total_payable > 0) 
                     ? ($loanAccount->loan_amount / $loanAccount->total_payable) 
                     : 0.85;
            $outstandingAmount = round($loanAccount->outstanding_amount * $ratio, 2);
        } else {
            $outstandingAmount = round($sumOfEmiPrincipals, 2);
        }

        $lastPaidEmi = $loanAccount->emis()
            ->where('status', 'paid')
            ->orderByDesc('paid_date')
            ->first();

        $fromDate = $lastPaidEmi
            ? \Carbon\Carbon::parse($lastPaidEmi->paid_date)
            : \Carbon\Carbon::parse($loanAccount->disbursed_at);

        $days = $fromDate->diffInDays(now());

        $annualRate = (float) $loanAccount->interest_rate; // %
        $dailyRate = $annualRate / 100 / 365;

        $interestOutstanding = round(
            $outstandingAmount * $dailyRate * $days,
            2
        );

        // compute min and max
        $emiAmount = $loanAccount->emi_amount ?? optional($loanAccount->emis->first())->total_amount ?? 0;
        $minPrepayment = round((float) $emiAmount, 2);

        $prepaymentChargesOnOutstanding = round(($outstandingAmount * $chargesPercentage) / 100, 2);
        $maxPrepayment = round($outstandingAmount + $interestOutstanding + $prepaymentChargesOnOutstanding, 2);

        // if no amount provided, return min/max and eligibility
        if ($amount === null) {
            return response()->json([
                'eligibility_months'   => $eligibilityMonths,
                'charges_percentage'   => $chargesPercentage,
                'paid_emis_count'      => $paidEmisCount,
                'is_eligible'          => $isEligible,
                'outstanding_amount'   => $outstandingAmount,
                'interest_outstanding' => $interestOutstanding,
                'min_amount'           => $minPrepayment,
                'max_amount'           => $maxPrepayment,
            ]);
        }

        $amount = round((float) $amount, 2);

        if ($amount < $minPrepayment || $amount > $maxPrepayment) {
            return response()->json([
                'success' => false,
                'message' => "Amount must be between {$minPrepayment} and {$maxPrepayment}"
            ], 422);
        }

        // Charges usually apply on the prepayment amount — compute on amount
        $prepaymentCharges = round(($amount * $chargesPercentage) / 100, 2);

        // interest portion prorated by amount over outstanding
        $interestPortion = $outstandingAmount > 0
            ? round(($amount / $outstandingAmount) * $interestOutstanding, 2)
            : 0;

        $totalForAmount = round($amount + $interestPortion + $prepaymentCharges, 2);

        // Amount applied to principal is the base amount (since charges and interest are added to total_payable)
        $adjustmentTowardsPrincipal = $amount;

        $currentOutstandingPrincipal = $outstandingAmount;
        $revisedPrincipal = round(max(0, $currentOutstandingPrincipal - $adjustmentTowardsPrincipal), 2);

        return response()->json([
            'eligibility_months'             => $eligibilityMonths,
            'charges_percentage'             => $chargesPercentage,
            'paid_emis_count'                => $paidEmisCount,
            'is_eligible'                    => $isEligible,
            'outstanding_amount'             => $outstandingAmount,
            'interest_outstanding'           => $interestOutstanding,
            'amount'                         => $amount,
            'total_payable_amount'           => $totalForAmount,
            'prepayment_charge_amount'       => $prepaymentCharges,
            'prepayment_charge_percentage'   => $chargesPercentage,
            'interest_portion'               => $interestPortion,
            'adjustment_towards_principal'   => $adjustmentTowardsPrincipal,
            'current_outstanding_principal'  => $currentOutstandingPrincipal,
            'revised_principal'              => $revisedPrincipal,
            'min_amount'                     => $minPrepayment,
            'max_amount'                     => $maxPrepayment,
        ]);
    }

    /**
     * Return minimum partial EMI amount based on admin-configured percentage.
     * Accepts: GET /api/loans/emi/{emi_id}/partial-min
     */
    public function emiPartialMinAmount(Request $request, $emi_id)
    {
        $emi = Emi::with('loanAccount')->findOrFail($emi_id);
        $partialService = app(PartialPaymentConfigService::class);
        $rules = $partialService->rulesForEmi($emi);

        return response()->json(array_merge([
            'emi_id' => $emi->getRouteKey(),
            'emi_total' => round((float) $emi->total_amount, 2),
            'pending_amount' => $rules['outstanding_due'],
            'currency_symbol' => '₹',
            'pending_amount_formatted' => number_format($rules['outstanding_due'], 0),
            'minimum_partial_amount_formatted' => number_format($rules['minimum_partial_amount'], 0),
        ], $rules));
    }

    public function foreclose(Request $request, $id)
    {
        $validated = $request->validate([
            'eligibility_months' => 'nullable|integer|min:1',
            'charges_percentage' => 'nullable|numeric|min:0|max:100',
            'override_mode' => 'nullable|boolean',
            'foreclosure_notes' => 'nullable|string'
        ]);

        // Prepare options for service
        $options = [
            'override_mode' => $request->boolean('override_mode'),
            'eligibility_months' => $validated['eligibility_months'] ?? null,
            'charges_percentage' => $validated['charges_percentage'] ?? null,
            'foreclosure_notes' => $validated['foreclosure_notes'] ?? null,
        ];

        // Call service
        $loanPaymentService = app(\App\Services\LoanPaymentService::class);
        $result = $loanPaymentService->foreclose($id, $options);

        // Return response
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }
    }

    /**
     * Process prepayment for a loan account
     */
    public function processPrepayment(Request $request, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
            'payment_date' => 'required|date'
        ]);

        try {
            // Call prepayment service
            $loanPaymentService = app(\App\Services\LoanPaymentService::class);
            $result = $loanPaymentService->processPrepayment(
                (int)$id,
                (float)$validated['amount'],    
                (string)$validated['payment_date'],
                (string)$validated['payment_method'],
                $validated['payment_reference'] ?? null,
                $validated['remarks'] ?? null
            );

            // Return response
            if ($result['success']) {
                // Use fresh() to reload from DB after the payment service has updated outstanding_amount
                $loanAccount = LoanAccount::with(['client', 'loanApplication'])->findOrFail($id)->fresh();
                $client = $loanAccount->client ?? $loanAccount->loanApplication?->client;
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
                    'amount_paid' => $validated['amount'],
                    'remaining_balance' => $remainingBalance,
                    'loan_mode' => $loanAccount->loan_mode,
                    'payment_type' => 'principal',
                    'application_number' => $loanAccount->application_number,
                    'is_partial' => false,
                    'emi_balance' => 0,
                ];
                $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getRepaymentMessages($smsData));

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data'],
                    'sms_data' => $smsData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Prepayment processing failed', [
                'loan_account_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Prepayment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send loan documents email with attachments
     */
    public function sendLoanDocumentsEmail($id)
    {
        try {
            $emailService = app(LoanDocumentEmailService::class);
            $result = $emailService->sendLoanDocumentsEmail($id);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'documents_sent' => $result['documents_sent'] ?? 0
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }
}
