<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ClientLoanDocument;
use App\Models\LoanAccount;
use App\Support\CollectedDocuments;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoanStatementMail;

class ClientDocumentControllerApi extends Controller
{
    public function getDocuments($loanAccountId)
    {
        $user = Auth::user();
        $clientId = $user->client->id;

        $loanAccount = LoanAccount::with(['loanApplication.disbursementDetail', 'clientLoanDocuments'])
            ->where('id', $loanAccountId)
            ->where('client_id', $clientId)
            ->first();

        if (! $loanAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Loan account not found.',
            ], 404);
        }

        $generated = $loanAccount->clientLoanDocuments
            ->filter(fn ($doc) => $doc->isVisible())
            ->values();

        $collected = CollectedDocuments::forLoanDisbursement(
            $loanAccount->loanApplication?->disbursementDetail,
            $loanAccount->loanApplication
        );

        return response()->json([
            'success' => true,
            'message' => 'Documents fetched successfully.',
            'data' => $generated,
            'disbursement_documents' => $collected,
            'documents' => array_values(array_merge(
                $collected,
                $generated->map(function ($doc) {
                    return [
                        'type' => $doc->document_type,
                        'title' => $doc->document_title ?: ucfirst(str_replace('_', ' ', (string) $doc->document_type)),
                        'file_name' => $doc->file_name,
                        'file_path' => $doc->file_path,
                        'file_url' => $doc->file_url,
                        'url' => $doc->file_url,
                    ];
                })->all()
            )),
        ], 200);
    }

    public function downloadStatement(Request $request, $loanId)
    {
        $data = $this->generateStatementPdf($request, $loanId);

        return response()->json([
            'status' => true,
            'message' => 'Statement generated successfully',
            'document_url' => asset('storage/' . $data['file_path']),
        ]);
    }

    public function emailStatement(Request $request, $loanId)
    {
        try {
            $data = $this->generateStatementPdf($request, $loanId);
            $client = $data['client'];
            $loanAccount = LoanAccount::findOrFail($loanId);

            if (!$client->client_email) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client email not found.',
                ], 422);
            }

            // Get email template
            $emailTemplate = \App\Models\EmailTemplate::where('identifier', 'loan_statement')
                ->where('status', true)
                ->first();

            if (!$emailTemplate) {
                return response()->json([
                    'status' => false,
                    'message' => 'Loan statement email template not found or inactive.',
                ], 422);
            }

            // Replace placeholders
            $placeholders = \App\Services\DocumentPlaceholderService::getReplacements($loanAccount);
            
            $subject = \App\Services\DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->subject,
                $placeholders
            );

            $body = \App\Services\DocumentPlaceholderService::replacePlaceholders(
                $emailTemplate->email_body,
                $placeholders
            );

            // Get full path to PDF
            $pdfPath = storage_path('app/public/' . $data['file_path']);

            // Send email with PDF attachment
            Mail::to($client->client_email)->send(
                new LoanStatementMail(
                    $loanAccount,
                    $client,
                    $subject,
                    $body,
                    $pdfPath,
                    $data['file_name']
                )
            );

            return response()->json([
                'status' => true,
                'message' => 'Statement emailed successfully',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to email loan statement', [
                'loan_id' => $loanId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function generateStatementPdf($request, $loanId)
    {
        if ($request->filled('from_date') || $request->filled('to_date')) {
            $request->validate([
                'from_date' => 'required|date',
                'to_date'   => 'required|date|after_or_equal:from_date',
            ]);
        }

        $loan = LoanAccount::with('client')->findOrFail($loanId);

        $emiQuery = $loan->emis()
            ->whereIn('status', ['paid', 'overdue']);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = $request->from_date;
            $to   = $request->to_date;

            $emiQuery->where(function ($q) use ($from, $to) {
                $q->where(function ($paid) use ($from, $to) {
                    $paid->where('status', 'paid')
                         ->whereDate('paid_date', '>=', $from)
                         ->whereDate('paid_date', '<=', $to);
                })
                ->orWhere(function ($overdue) use ($from, $to) {
                    $overdue->where('status', 'overdue')
                            ->whereDate('due_date', '>=', $from)
                            ->whereDate('due_date', '<=', $to);
                });
            });
        }

        $emis = $emiQuery->orderBy('due_date')->get();

        $body = view('pdf.loan_statement', [
            'emis'   => $emis,
            'loan'   => $loan,
            'client' => $loan->client,
        ])->render();

        $html = view('pdf.dynamic_document', [
            'body' => $body,
            'title' => 'EMI Statement',
            'loan' => $loan,
            'client' => $loan->client,
        ])->render();

        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($html);

        $fileName = "emi_statement_{$loan->id}_" . time() . ".pdf";
        $filePath = "documents/{$fileName}";

        Storage::disk('public')->put($filePath, $mpdf->Output($fileName, 'S'));

        ClientLoanDocument::updateOrCreate(
            [
                'loan_account_id' => $loan->id,
                'document_type'   => 'statement',
            ],
            [
                'client_id'       => $loan->client->id,
                'file_path'       => $filePath,
                'file_name'       => $fileName,
                'document_title'  => 'Statement',
            ]
        );

        return [
            'client'    => $loan->client,
            'file_path' => $filePath,
            'file_name' => $fileName,
        ];
    }

}
