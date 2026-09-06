<?php

namespace App\Services;

use App\Models\LoanAccount;
use App\Helpers\AppearanceHelper;
use Illuminate\Support\Facades\DB;
use App\Services\EmiCalculator;

class DocumentPlaceholderService
{
    /**
     * Get all placeholder replacements for a loan account
     * 
     * @param LoanAccount $loanAccount
     * @return array
     */
    public static function getReplacements(LoanAccount $loanAccount): array
    {
        // Load necessary relationships
        $loanAccount->load([
            'loanApplication.client.kycDetail',
            'loanApplication.client.employeeInformation',
            'loanApplication.client.nominee',
            'loanApplication.product',
            'loanApplication.applicationDetail'
        ]);

        $client = $loanAccount->loanApplication->client;
        $kyc = $client->kycDetail;
        $employeeInfo = $client->employeeInformation;
        $nominee = $client->nominee;
        $product = $loanAccount->loanApplication->product;
        $application = $loanAccount->loanApplication;
        $applicationDetail = $application->applicationDetail;
        
        // Extract vehicle/course details from JSON if available
        $details = $applicationDetail->details ?? [];

        // Calculate EMI (use the same generator used in the loan schedule)
        $principal = (float) ($loanAccount->loan_amount ?? 0);
        $annualRate = (float) ($loanAccount->interest_rate ?? $product->interest_rate ?? 0);
        $tenureMonths = (int) ($loanAccount->tenure ?? $application->tenure ?? 0);
        $loanMode = strtolower((string) ($loanAccount->loan_mode ?? ''));

        $termUnit = strtolower((string) ($product->term_unit ?? 'months'));
        $frequency = 'monthly';
        if (in_array($termUnit, ['week', 'weeks', 'weekly'], true)) {
            $frequency = 'weekly';
        } elseif (in_array($termUnit, ['day', 'days', 'daily'], true)) {
            $frequency = 'daily';
        }

        $emiFrequencyLabel = ucfirst($frequency);

        $emiAmount = 0;
        if ($principal > 0 && $annualRate > 0 && $tenureMonths > 0) {
            $startDate = $loanAccount->disbursed_at
                ? $loanAccount->disbursed_at->format('Y-m-d')
                : ($loanAccount->created_at ? $loanAccount->created_at->format('Y-m-d') : now()->toDateString());

            $emiDay = (int) ($loanAccount->emi_day ?? $application->emi_day ?? 1);

            $emiCalculator = new EmiCalculator();
            $result = $emiCalculator->generateSchedule(
                principal: $principal,
                annualRate: $annualRate,
                term: $tenureMonths,
                startDate: $startDate,
                emiDay: $emiDay,
                frequency: $frequency,
                interestType: $product->interest_type ?? 'flat'
            );

            $emiAmount = (float) ($result['emi'] ?? 0);
        }

        if ($emiAmount <= 0) {
            $emiAmount = (float) ($loanAccount->emi_amount ?? 0);
        }

        if ($emiAmount <= 0 && $principal > 0 && $annualRate > 0) {
            if ($tenureMonths <= 0 || in_array($loanMode, ['interest_only', 'kanduvatti', 'kandhuvatti'], true)) {
                $emiAmount = round($principal * ($annualRate / 100), 2);
            }
        }

        $isDisbursed = in_array(strtolower($loanAccount->status ?? ''), ['active', 'disbursed', 'closed', 'foreclosed']);

        if (isset($details['applied_processing_fee'])) {
            $processingFee = (float) $details['applied_processing_fee'];
            $documentCharges = (float) ($details['applied_document_charges'] ?? 0);
            $otherCharges = (float) ($details['applied_other_charges'] ?? 0);
            $totalCharges = $processingFee + $documentCharges + $otherCharges;
            $netDisbursal = $principal - $totalCharges;
        } elseif ($isDisbursed && $loanAccount->disbursed_amount > 0) {
            // If disbursed, calculate from account data
            $netDisbursal = (float) $loanAccount->disbursed_amount;
            $totalCharges = max($principal - $netDisbursal, 0);
            // Fallback: if we don't have detail breakdown but have disbursed_amount, 
            // we assume the difference is processing fee (common pattern)
            $processingFee = $totalCharges;
            $documentCharges = 0;
            $otherCharges = 0;
        } else {
            // Fallback for pending/preview: use product defaults
            $processingFee = (float) ($product->processing_fee ?? 0);
            $documentCharges = (float) ($product->document_charges ?? 0);
            $otherCharges = (float) ($product->other_charges ?? 0);
            $totalCharges = $processingFee + $documentCharges + $otherCharges;
            $netDisbursal = $principal - $totalCharges;
        }


        // Address Construction
        $addressParts = [
            $client->address,
            $client->city,
            $client->state,
            $client->pincode
        ];
        $fullAddress = implode(', ', array_filter($addressParts));

        $replacements = [
            // Client Details
            '{{client_name}}' => $client->client_name ?? 'N/A',
            '{{client_dob}}' => $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('d-m-Y') : 'N/A',
            '{{dob}}' => $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('d-m-Y') : 'N/A',
            '{{client_mobile}}' => $client->client_phone ?? 'N/A',
            '{{mobile_number}}' => $client->client_phone ?? 'N/A',
            '{{client_gender}}' => $client->gender ? ucfirst($client->gender) : 'N/A',
            '{{gender}}' => $client->gender ? ucfirst($client->gender) : 'N/A',
            '{{client_marital_status}}' => $client->marital_status ? ucfirst($client->marital_status) : 'N/A',
            '{{marital_status}}' => $client->marital_status ? ucfirst($client->marital_status) : 'N/A',
            '{{client_pan}}' => $kyc->pan_number ?? 'N/A',
            '{{pan_number}}' => $kyc->pan_number ?? 'N/A',
            '{{client_email}}' => $client->client_email ?? 'N/A',
            '{{email}}' => $client->client_email ?? 'N/A',
            '{{client_address}}' => $fullAddress ?: 'N/A',
            '{{residence_address}}' => $fullAddress ?: 'N/A',
            
            // Employment Details
            '{{client_employment_type}}' => $employeeInfo->employment_type ?? 'N/A',
            '{{employment_status}}' => $employeeInfo->employment_type ?? 'N/A',
            '{{client_employment_status}}' => $employeeInfo->employment_type ?? 'N/A',
            '{{client_monthly_salary}}' => $employeeInfo->monthly_salary ? '₹' . number_format($employeeInfo->monthly_salary, 2) : 'N/A',
            '{{client_company_name}}' => $employeeInfo->company_name ?? 'N/A',
            '{{company_name}}' => $employeeInfo->company_name ?? AppearanceHelper::get('title', 'Loan App'),
            '{{work_email}}' => $employeeInfo->work_email ?? 'N/A',
            '{{client_work_email}}' => $employeeInfo->work_email ?? 'N/A',
            '{{work_address}}' => $employeeInfo->work_address ?? 'N/A',
            '{{client_work_address}}' => $employeeInfo->work_address ?? 'N/A',

            // Bank Details
            '{{client_bank_name}}' => $kyc->bank_name ?? 'N/A',
            '{{bank_name}}' => $kyc->bank_name ?? 'N/A',
            '{{client_account_number}}' => $kyc->account_number ?? 'N/A',
            '{{account_number}}' => $kyc->account_number ?? 'N/A',
            '{{client_ifsc_code}}' => $kyc->ifsc_code ?? 'N/A',
            '{{ifsc_code}}' => $kyc->ifsc_code ?? 'N/A',
            '{{client_micr_code}}' => $kyc->micr_code ?? 'N/A',
            '{{micr_code}}' => $kyc->micr_code ?? 'N/A',

            // Loan Details
            '{{agreement_no}}' => $loanAccount->account_number ?? 'N/A',
            '{{loan_number}}' => $loanAccount->account_number ?? 'N/A',
            '{{loan_account_no}}' => $loanAccount->account_number ?? 'N/A',
            '{{amount_financed}}' => '₹' . number_format($loanAccount->loan_amount ?? 0, 2),
            '{{loan_amount}}' => number_format($loanAccount->loan_amount ?? 0, 2),
            '{{sanctioned_amount}}' => number_format($loanAccount->loan_amount ?? 0, 2),
            '{{emi_amount}}' => number_format($emiAmount, 2),
            '{{tenure_months}}' => $tenureMonths,
            '{{tenure}}' => $tenureMonths,
            '{{net_disbursed_amount}}' => '₹' . number_format($netDisbursal, 2),
            '{{net_disbursal_amount}}' => number_format($netDisbursal, 2),
            '{{net_disbursal}}' => number_format($netDisbursal, 2),
            '{{disbursed_amount}}' => number_format($netDisbursal, 2),
            '{{processing_fee}}' => number_format($processingFee, 2),
            '{{applied_processing_fee}}' => number_format($processingFee, 2),
            '{{processing_charges}}' => number_format($processingFee, 2),
            '{{document_charges}}' => number_format($documentCharges, 2),
            '{{applied_document_charges}}' => number_format($documentCharges, 2),
            '{{other_charges}}' => number_format($otherCharges, 2),
            '{{applied_other_charges}}' => number_format($otherCharges, 2),
            '{{total_charges}}' => number_format($processingFee + $documentCharges + $otherCharges, 2),
            '{{total_deductions}}' => number_format($processingFee + $documentCharges + $otherCharges, 2),
            '{{applied_total_charges}}' => number_format($processingFee + $documentCharges + $otherCharges, 2),
            '{{interest_rate}}' => $loanAccount->interest_rate ?? $product->interest_rate ?? '0',
            '{{lender_name}}' => AppearanceHelper::get('title', 'Loan App'),
            '{{current_date}}' => now()->format('d-m-Y'),
            '{{date}}' => now()->format('d-m-Y'),
            '{{status}}' => $loanAccount->status ? ucfirst($loanAccount->status) : 'N/A',
            '{{disbursed_date}}' => $loanAccount->disbursed_at ? $loanAccount->disbursed_at->format('d-m-Y') : 'N/A',
            '{{application_date}}' => $loanAccount->created_at ? $loanAccount->created_at->format('d-m-Y') : 'N/A',
            '{{agreement_date}}' => $loanAccount->disbursed_at ? $loanAccount->disbursed_at->format('d-m-Y') : ($loanAccount->created_at ? $loanAccount->created_at->format('d-m-Y') : 'N/A'),
            '{{application_number}}' => $application->application_number ?? 'N/A',
            '{{loan_application_number}}' => $application->application_number ?? 'N/A',

            // Product Details
            '{{product_name}}' => $product->loan_name ?? 'N/A',
            '{{loan_name}}' => $product->loan_name ?? 'N/A',
            '{{loan_code}}' => $product->loan_code ?? 'N/A',
            '{{loan_description}}' => $product->description ?? 'N/A',
            '{{loan_interest_rate}}' => $product->interest_rate ?? 'N/A',
            
            // Repayment/Statement Specific
            '{{total_payable}}' => number_format($loanAccount->total_payable ?? 0, 2),
            '{{outstanding_amount}}' => number_format($loanAccount->due_amount ?? $loanAccount->outstanding_amount ?? 0, 2),
            '{{paid_amount}}' => number_format($loanAccount->paid_amount ?? 0, 2),
            '{{total_amount_paid}}' => number_format($loanAccount->paid_amount ?? 0, 2),
            '{{emi_frequency}}' => $emiFrequencyLabel,
            '{{total_installments}}' => $loanAccount->emis()->count(),
            '{{generated_on}}' => now()->format('d-m-Y H:i:s'),

            // NOC Specific
            '{{customer_name}}' => $client->client_name ?? '',
            '{{customer_address_line1}}' => $client->address ?? '',
            '{{customer_address_line2}}' => '',
            '{{customer_city}}' => $client->city ?? '',
            '{{customer_state}}' => $client->state ?? '',
            '{{customer_pincode}}' => $client->pincode ?? '',
            '{{loan_type}}' => $product->loan_name ?? '',
            '{{loan_closed_date}}' => $loanAccount->closed_at ? $loanAccount->closed_at->format('d-m-Y') : now()->format('d-m-Y'),
            '{{closure_date}}' => $loanAccount->closed_at ? $loanAccount->closed_at->format('d-m-Y') : now()->format('d-m-Y'),
            '{{sign_name}}' => 'Admin',
            '{{sign_designation}}' => 'Authorized Signatory',
            '{{sign_seal}}' => '[SEAL]',

            // Vehicle Details (from loan_application_details.details JSON)
            '{{vehicle_type}}' => $details['vehicle_type'] ?? 'N/A',
            '{{vehicle_model}}' => $details['vehicle_model'] ?? 'N/A',
            '{{model_name}}' => $details['model_name'] ?? $details['vehicle_model'] ?? 'N/A',
            '{{vehicle_brand}}' => $details['vehicle_brand'] ?? 'N/A',
            '{{vehicle_year}}' => $details['vehicle_year'] ?? 'N/A',
            '{{manufacturing_year}}' => $details['manufacturing_year'] ?? $details['vehicle_year'] ?? 'N/A',
            '{{vehicle_registration_no}}' => $details['registration'] ?? 'N/A',
            '{{registration}}' => $details['registration'] ?? 'N/A',
            '{{vehicle_chassis_no}}' => $details['vehicle_chassis_no'] ?? 'N/A',
            '{{vehicle_engine_no}}' => $details['vehicle_engine_no'] ?? 'N/A',

            // Education Loan - Course Details (from loan_application_details.details JSON)
            '{{course_name}}' => $details['course_name'] ?? 'N/A',
            '{{institution_name}}' => $details['institution_name'] ?? 'N/A',
            '{{course_duration}}' => $details['course_duration'] ?? 'N/A',
            '{{course_type}}' => $details['course_type'] ?? 'N/A',
            '{{annual_fee}}' => $details['annual_fee'] ?? 'N/A',

            // Education Loan - Guarantor/Nominee Details
            '{{guarantor_name}}' => $nominee->nominee1_name ?? 'N/A',
            '{{nominee1_name}}' => $nominee->nominee1_name ?? 'N/A',
            '{{guarantor_relationship}}' => $nominee->nominee1_relationship ?? 'N/A',
            '{{nominee1_relationship}}' => $nominee->nominee1_relationship ?? 'N/A',
            '{{guarantor_mobile}}' => $nominee->nominee1_mobile ?? 'N/A',
            '{{nominee1_mobile}}' => $nominee->nominee1_mobile ?? 'N/A',
            '{{guarantor_occupation}}' => 'N/A', // Not available in current schema
            '{{guardian_address}}' => $client->address ?? 'N/A',

            // Business Loan - Business Details (from loan_application_details.details JSON)
            '{{business_name}}' => $details['business_name'] ?? 'N/A',
            '{{business_type}}' => $details['business_type'] ?? 'N/A',
            '{{business_address}}' => $details['business_address'] ?? 'N/A',
            '{{years_in_business}}' => $details['years_in_business'] ?? 'N/A',
            '{{annual_turnover}}' => $details['annual_turnover'] ?? 'N/A',
            '{{client_designation}}' => $details['client_designation'] ?? $employeeInfo->designation ?? 'N/A',
            
            // Company/App Details (from company_details table)
            '{{app_name}}' => \App\Models\CompanyDetail::first()->company_name ?? AppearanceHelper::get('title', 'Loan App'),
            '{{company_name}}' => \App\Models\CompanyDetail::first()->company_name ?? AppearanceHelper::get('title', 'Loan App'),
            '{{company_slogan}}' => \App\Models\CompanyDetail::first()->company_slogan ?? '',
            '{{company_email}}' => \App\Models\CompanyDetail::first()->company_email ?? 'N/A',
            '{{company_mobile}}' => \App\Models\CompanyDetail::first()->company_mobile ?? 'N/A',
            '{{alternate_mobile}}' => \App\Models\CompanyDetail::first()->alternate_mobile ?? 'N/A',
            '{{company_website}}' => \App\Models\CompanyDetail::first()->website_url ?? config('app.url'),
            '{{support_email}}' => \App\Models\CompanyDetail::first()->support_email ?? 'N/A',
            '{{support_mobile}}' => \App\Models\CompanyDetail::first()->support_mobile ?? 'N/A',
            '{{website_url}}' => \App\Models\CompanyDetail::first()->website_url ?? config('app.url'),
            '{{address_line1}}' => \App\Models\CompanyDetail::first()->address_line1 ?? '',
            '{{address_line2}}' => \App\Models\CompanyDetail::first()->address_line2 ?? '',
            '{{company_city}}' => \App\Models\CompanyDetail::first()->city ?? '',
            '{{company_state}}' => \App\Models\CompanyDetail::first()->state ?? '',
            '{{company_pincode}}' => \App\Models\CompanyDetail::first()->pincode ?? '',
            '{{company_country}}' => \App\Models\CompanyDetail::first()->country ?? 'India',
            '{{gst_number}}' => \App\Models\CompanyDetail::first()->gst_number ?? '',
            '{{pan_number}}' => \App\Models\CompanyDetail::first()->pan_number ?? '',
            '{{cin_number}}' => \App\Models\CompanyDetail::first()->cin_number ?? '',
            '{{facebook_url}}' => \App\Models\CompanyDetail::first()->facebook_url ?? '',
            '{{twitter_url}}' => \App\Models\CompanyDetail::first()->twitter_url ?? '',
            '{{linkedin_url}}' => \App\Models\CompanyDetail::first()->linkedin_url ?? '',
            '{{instagram_url}}' => \App\Models\CompanyDetail::first()->instagram_url ?? '',
            
            '{{logo_url}}' => \Illuminate\Support\Facades\DB::table('appearances')->value('logo') ? url('storage/' . \Illuminate\Support\Facades\DB::table('appearances')->value('logo')) : url('images/logo.png'),
            '{{company_logo}}' => \Illuminate\Support\Facades\DB::table('appearances')->value('logo') ? url('storage/' . \Illuminate\Support\Facades\DB::table('appearances')->value('logo')) : url('images/logo.png'),
            '{{company_logo_url}}' => \Illuminate\Support\Facades\DB::table('appearances')->value('logo') ? url('storage/' . \Illuminate\Support\Facades\DB::table('appearances')->value('logo')) : url('images/logo.png'),
            
            // Payment date for payment receipts
            '{{payment_date}}' => now()->format('d-m-Y'),
            '{{sanction_validity_days}}' => '10',
        ];


        // Calculate foreclosure-specific values
        // Formula: foreclosure_amount = outstanding + (outstanding × percentage / 100) = outstanding × (1 + percentage / 100)
        // So: outstanding = foreclosure_amount / (1 + percentage / 100)
        $outstandingBeforeForeclosure = '0.00';
        $foreclosureCharges = '0.00';
        
        if ($loanAccount->is_foreclosed && $loanAccount->foreclosure_amount) {
            $chargesPercentage = $loanAccount->foreclosure_charges_percentage ?? $loanAccount->getForeclosureChargesPercentage();
            $multiplier = 1 + ($chargesPercentage / 100);
            $outstanding = $loanAccount->foreclosure_amount / $multiplier;
            $charges = $loanAccount->foreclosure_amount - $outstanding;
            
            $outstandingBeforeForeclosure = number_format($outstanding, 2);
            $foreclosureCharges = number_format($charges, 2);
        } elseif ($loanAccount->outstanding_amount) {
            $outstandingBeforeForeclosure = number_format($loanAccount->outstanding_amount, 2);
        }
        
        
        // Add foreclosure-specific placeholders
        $replacements['{{outstanding_before_foreclosure}}'] = $outstandingBeforeForeclosure;
        $replacements['{{foreclosure_charges}}'] = $foreclosureCharges;
        $replacements['{{total_foreclosure_amount}}'] = number_format($loanAccount->foreclosure_amount ?? 0, 2);
        $replacements['{{foreclosure_payment_date}}'] = $loanAccount->closed_at ? $loanAccount->closed_at->format('d-m-Y') : now()->format('d-m-Y');
        $replacements['{{foreclosure_date}}'] = $loanAccount->closed_at ? $loanAccount->closed_at->format('d-m-Y') : now()->format('d-m-Y');
        $replacements['{{foreclosure_notes}}'] = $loanAccount->foreclosure_notes ?? 'N/A';

        $emis = $loanAccount->emis()->orderBy('instalment_number')->get();
        $paidCount = $emis->where('status', 'paid')->count();
        $totalEmiCount = $emis->count();
        $lastEmi = $emis->last();
        $loanStart = $loanAccount->disbursed_at ?? $loanAccount->created_at;
        $totalPaid = (float) ($loanAccount->paid_amount ?? $emis->sum(fn ($e) => (float) ($e->paid_amount ?? 0)));

        $replacements['{{total_paid}}'] = number_format($totalPaid, 2);
        $replacements['{{loan_start_date}}'] = $loanStart ? $loanStart->format('d-m-Y') : 'N/A';
        $replacements['{{loan_end_date}}'] = $lastEmi && $lastEmi->due_date
            ? $lastEmi->due_date->format('d-m-Y')
            : 'N/A';
        $replacements['{{total_emis}}'] = (string) ($totalEmiCount > 0 ? $totalEmiCount : ($tenureMonths > 0 ? $tenureMonths : 'N/A'));
        $replacements['{{emis_paid}}'] = (string) $paidCount;
        $replacements['{{emis_remaining}}'] = (string) max(0, ($totalEmiCount > 0 ? $totalEmiCount : $tenureMonths) - $paidCount);
        $replacements['{{transaction_rows}}'] = self::buildStatementTransactionTable($loanAccount, $emis);
        $replacements['{{emi_amount}}'] = number_format($emiAmount, 2);

        return $replacements;
    }

    /**
     * Replace placeholders in content
     * 
     * @param string $content
     * @param array $replacements
     * @return string
     */
    /**
     * Build transaction table HTML for loan statement documents.
     */
    public static function buildStatementTransactionTable(LoanAccount $loanAccount, $emis): string
    {
        $rows = '';
        $disbursedDate = $loanAccount->disbursed_at ?? $loanAccount->created_at ?? now();
        $disbursedDateLabel = $disbursedDate->format('d-m-Y');
        
        $balance = (float)($loanAccount->loan_amount ?? 0);
        
        // 1. Disbursement row (Debit)
        $rows .= '<tr>';
        $rows .= '<td style="padding:8px;">' . e($disbursedDateLabel) . '</td>';
        $rows .= '<td style="padding:8px;"><strong>Loan Principal Disbursed</strong></td>';
        $rows .= '<td style="padding:8px;text-align:right;">₹' . number_format($balance, 2) . '</td>';
        $rows .= '<td style="padding:8px;text-align:right;">—</td>';
        $rows .= '<td style="padding:8px;text-align:right;">₹' . number_format($balance, 2) . '</td>';
        $rows .= '</tr>';
        
        // 2. Verified Collections
        $collections = \App\Models\EmiCollection::whereIn('emi_id', $emis->pluck('id'))
            ->where('status', 'verified')
            ->orderBy('collected_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();
            
        if ($collections->isEmpty()) {
            // Fallback: Check if there are any EMIs marked as paid directly without collections
            $directPayments = [];
            foreach ($emis as $emi) {
                if ($emi->paid_amount > 0) {
                    $directPayments[] = [
                        'date' => $emi->paid_date ?? $emi->due_date ?? now(),
                        'particulars' => 'EMI #' . $emi->instalment_number . ' Repayment',
                        'amount' => (float)$emi->paid_amount
                    ];
                }
            }
            
            // Sort by date
            usort($directPayments, function($a, $b) {
                return strcmp($a['date']->format('YmdHis'), $b['date']->format('YmdHis'));
            });
            
            foreach ($directPayments as $payment) {
                $balance = max(0, $balance - $payment['amount']);
                $rows .= '<tr>';
                $rows .= '<td style="padding:8px;">' . e($payment['date']->format('d-m-Y')) . '</td>';
                $rows .= '<td style="padding:8px;">' . e($payment['particulars']) . '</td>';
                $rows .= '<td style="padding:8px;text-align:right;">—</td>';
                $rows .= '<td style="padding:8px;text-align:right;">₹' . number_format($payment['amount'], 2) . '</td>';
                $rows .= '<td style="padding:8px;text-align:right;"><strong>₹' . number_format($balance, 2) . '</strong></td>';
                $rows .= '</tr>';
            }
        } else {
            foreach ($collections as $c) {
                $emi = $emis->firstWhere('id', $c->emi_id);
                $instNo = $emi ? $emi->instalment_number : '';
                $particulars = "EMI #{$instNo} Repayment (" . ucfirst(str_replace('_', ' ', $c->payment_method)) . ")";
                
                $amount = (float)$c->amount;
                $balance = max(0, $balance - $amount);
                
                $date = $c->collected_at ?? $c->created_at ?? now();
                $dateLabel = $date->format('d-m-Y');
                
                $rows .= '<tr>';
                $rows .= '<td style="padding:8px;">' . e($dateLabel) . '</td>';
                $rows .= '<td style="padding:8px;">' . e($particulars) . '</td>';
                $rows .= '<td style="padding:8px;text-align:right;">—</td>';
                $rows .= '<td style="padding:8px;text-align:right;">₹' . number_format($amount, 2) . '</td>';
                $rows .= '<td style="padding:8px;text-align:right;"><strong>₹' . number_format($balance, 2) . '</strong></td>';
                $rows .= '</tr>';
            }
        }
        
        // 3. Foreclosure acknowledgement
        if ($loanAccount->is_foreclosed) {
            $foreclosureDate = $loanAccount->closed_at ?? now();
            $foreclosureDateLabel = $foreclosureDate->format('d-m-Y');
            
            $rows .= '<tr style="background:#fff2f2;">';
            $rows .= '<td style="padding:8px;">' . e($foreclosureDateLabel) . '</td>';
            $rows .= '<td style="padding:8px;color:#ff3e1d;"><strong>Loan Foreclosed / Settled</strong></td>';
            $rows .= '<td style="padding:8px;text-align:right;">—</td>';
            $rows .= '<td style="padding:8px;text-align:right;">—</td>';
            $rows .= '<td style="padding:8px;text-align:right;color:#ff3e1d;"><strong>₹0.00</strong></td>';
            $rows .= '</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;font-size:13px;" border="1">'
            . '<thead><tr style="background:#f2f2f2;">'
            . '<th style="padding:8px;text-align:left;">Date</th>'
            . '<th style="padding:8px;text-align:left;">Particulars</th>'
            . '<th style="padding:8px;text-align:right;">Debit (₹)</th>'
            . '<th style="padding:8px;text-align:right;">Credit (₹)</th>'
            . '<th style="padding:8px;text-align:right;">Balance (₹)</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    public static function replacePlaceholders(string $content, array $replacements): string
    {
        $allReplacements = [];
        foreach ($replacements as $key => $value) {
            $allReplacements[$key] = $value;
            
            // If key is like {{placeholder}}, also support {placeholder}
            if (preg_match('/^\{\{(.*)\}\}$/', $key, $matches)) {
                $singleBraceKey = '{' . $matches[1] . '}';
                if (!isset($replacements[$singleBraceKey])) {
                    $allReplacements[$singleBraceKey] = $value;
                }
            }
        }
        
        return str_replace(array_keys($allReplacements), array_values($allReplacements), $content);
    }
}
