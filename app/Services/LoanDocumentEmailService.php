<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\LoanAccount;
use App\Models\ClientLoanDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LoanDocumentEmailService
{
    /**
     * Send loan documents email with attachments
     *
     * @param int $loanAccountId
     * @param string $templateIdentifier
     * @return array
     */
    public function sendLoanDocumentsEmail($loanAccountId, $templateIdentifier = 'loan_documents')
    {
        try {
            // 1. Fetch loan account with client
            $loanAccount = LoanAccount::with('loanApplication.client')->findOrFail($loanAccountId);
            $client = $loanAccount->loanApplication->client;

            // 2. Check if client exists
            if (!$client) {
                Log::warning('Client not found for loan account', [
                    'loan_account_id' => $loanAccountId
                ]);

                return [
                    'success' => false,
                    'message' => 'Client not found for this loan account'
                ];
            }

            // 3. Check if client has email
            if (empty($client->client_email)) {
                Log::warning('Client does not have an email address', [
                    'loan_account_id' => $loanAccountId,
                    'client_id' => $client->id
                ]);

                return [
                    'success' => false,
                    'message' => 'Client does not have an email address'
                ];
            }

            // 4. Get email template
            $emailTemplate = EmailTemplate::where('identifier', $templateIdentifier)
                ->where('status', true)
                ->first();

            if (!$emailTemplate) {
                return [
                    'success' => false,
                    'message' => "Email template '{$templateIdentifier}' not found or inactive"
                ];
            }

            // 5. Get client loan documents
            $documents = ClientLoanDocument::where('loan_account_id', $loanAccountId)->get();

            // Filter documents based on template type
            if ($templateIdentifier === 'loan_foreclosed') {
                // For foreclosure, only include: Foreclosure_letter, noc, loan_closure_certificate
                $documents = $documents->whereIn('document_type', [
                    'Foreclosure_letter',
                    'noc',
                    'loan_closure_certificate'
                ]);
            } elseif ($templateIdentifier === 'loan_closed') {
                // For loan closure, only include: noc, loan_closure_certificate
                $documents = $documents->whereIn('document_type', [
                    'noc',
                    'loan_closure_certificate'
                ]);
            }
            // For 'loan_documents' template, include all documents (no filtering)

            // Filter valid documents that exist on filesystem
            $validDocuments = $documents->filter(function($document) {
                return $document->fileExists();
            });

            if ($validDocuments->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No valid documents found for this loan'
                ];
            }

            // 6. Replace placeholders in subject and body
            $placeholders = DocumentPlaceholderService::getReplacements($loanAccount);
            
            $subject = DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->subject,
                $placeholders
            );

            $body = DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->email_body,
                $placeholders
            );

            // 7. Log documents to be attached
            Log::info('Documents to be attached', [
                'loan_account_id' => $loanAccountId,
                'documents' => $validDocuments->map(function($doc) {
                    return [
                        'type' => $doc->document_type,
                        'file_name' => $doc->file_name,
                        'file_path' => $doc->file_path,
                        'full_path' => $doc->full_path,
                        'exists' => $doc->fileExists()
                    ];
                })->toArray()
            ]);

            // 8. Send email with documents
            Mail::to($client->client_email)->send(
                new \App\Mail\LoanDocumentsMail($loanAccount, $client, $subject, $body, $validDocuments)
            );

            // 9. Log success
            Log::info('Loan documents email sent successfully', [
                'loan_account_id' => $loanAccountId,
                'client_email' => $client->client_email,
                'documents_count' => $validDocuments->count()
            ]);

            return [
                'success' => true,
                'message' => 'Loan documents email sent successfully',
                'documents_sent' => $validDocuments->count()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send loan documents email', [
                'loan_account_id' => $loanAccountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send loan documents emails for multiple loan accounts
     *
     * @param array $loanAccountIds
     * @return array
     */
    public function sendBulkLoanDocumentsEmails(array $loanAccountIds)
    {
        $results = [
            'success' => true,
            'total' => count($loanAccountIds),
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($loanAccountIds as $loanAccountId) {
            $result = $this->sendLoanDocumentsEmail($loanAccountId);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['success'] = false;
            }

            $results['details'][] = [
                'loan_account_id' => $loanAccountId,
                'result' => $result
            ];
        }

        return $results;
    }

    /**
     * Send payment receipt email after EMI payment
     *
     * @param int $emiId
     * @return array
     */
    public function sendPaymentReceiptEmail($emiId)
    {
        try {
            // 1. Fetch EMI with loan account, client, and product
            $emi = \App\Models\Emi::with([
                'loanAccount.loanApplication.client',
                'loanAccount.loanApplication.product',
                'loanAccount.client'
            ])->findOrFail($emiId);
            
            $loanAccount = $emi->loanAccount;
            $loanApplication = optional($loanAccount)->loanApplication;
            $client = optional($loanApplication)->client ?? optional($loanAccount)->client;

            // 2. Check if client exists and has email
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Client not found for this EMI'
                ];
            }

            if (empty($client->client_email)) {
                return [
                    'success' => false,
                    'message' => 'Client does not have an email address'
                ];
            }

            // 3. Get email template
            $emailTemplate = EmailTemplate::where('identifier', 'loan_repayment')
                ->where('status', true)
                ->first();

            if (!$emailTemplate) {
                return [
                    'success' => false,
                    'message' => 'Payment receipt email template not found or inactive'
                ];
            }

            // 4. Replace placeholders in subject and body
            $placeholders = DocumentPlaceholderService::getReplacements($loanAccount);
            
            // Override payment_date with actual EMI paid date
            $placeholders['{{payment_date}}'] = $emi->paid_date ? $emi->paid_date->format('d-m-Y') : now()->format('d-m-Y');
            
            $subject = DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->subject,
                $placeholders
            );

            $body = DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->email_body,
                $placeholders
            );

            // 5. Generate payment receipt PDF
            // Prepare receipt data
            $receiptData = [
                'receipt_number'     => 'RCP-' . str_pad($emiId, 6, '0', STR_PAD_LEFT),
                'paid_date'          => $emi->paid_date,
                'payment_reference'  => $emi->payment_reference,
                'payment_method'     => $emi->payment_method,
                'application_number' => $loanAccount->application_number,
                'account_number'     => $loanAccount->account_number,
                'disbursed_date'     => $loanAccount->disbursed_at,
                'principal_amount'   => $emi->principal_amount,
                'interest_amount'    => $emi->interest_amount,
                'emi_amount'         => $emi->total_amount,
                'paid_amount'        => $emi->paid_amount,
                'overdue_amount'     => $emi->penalty_amount ?? 0,
                'show_overdue'       => ($emi->penalty_amount ?? 0) > 0,
            ];
            
            // Generate HTML using API controller's views
            $pdfBody = view('pdf.payment-receipt-api', compact('receiptData', 'client', 'loanAccount'))->render();
            $html = view('pdf.dynamic_document', [
                'title'  => 'Payment Receipt',
                'body'   => $pdfBody,
                'client' => $client,
                'loan'   => $loanAccount,
            ])->render();
            
            // Generate PDF using mPDF
            try {
                $receiptNumber = $receiptData['receipt_number'];
                $pdfFileName = 'Payment_Receipt_' . $receiptNumber . '.pdf';
                $tempPdfPath = storage_path('app/temp/' . $pdfFileName);
                
                // Ensure temp directory exists
                if (!file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0755, true);
                }
                
                $mpdf = new \Mpdf\Mpdf([
                    'default_font' => 'dejavusans',
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 10,
                ]);
                
                $mpdf->WriteHTML($html);
                $mpdf->Output($tempPdfPath, 'F');
            } catch (\Exception $pdfError) {
                Log::error('mPDF generation failed', [
                    'error' => $pdfError->getMessage(),
                    'trace' => $pdfError->getTraceAsString()
                ]);
                throw new \Exception('PDF generation failed: ' . $pdfError->getMessage());
            }
            
            // 6. Send email with PDF attachment
            Mail::to($client->client_email)->send(
                new \App\Mail\PaymentReceiptMail($loanAccount, $client, $emi, $subject, $body, $tempPdfPath, $pdfFileName)
            );
            
            // Clean up temp file
            if (file_exists($tempPdfPath)) {
                unlink($tempPdfPath);
            }

            return [
                'success' => true,
                'message' => 'Payment receipt email sent successfully with PDF attachment'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send payment receipt email', [
                'emi_id' => $emiId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
}
