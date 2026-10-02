<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Emi;
use App\Http\Resources\EmiResource;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;

class EmiControllerApi extends Controller
{
    public function emiHistory(Request $request)
    {
        $user = Auth::user();
        $client = $user->client;

        $loanAccountId = $request->loan_account_id;

        $query = Emi::whereHas('loanAccount', function ($q) use ($client) {
            $q->where('client_id', $client->id);
        })->with('loanAccount');

        if ($loanAccountId) {
            $query->where('loan_account_id', $loanAccountId);
        }

        $emis = $query->orderBy('instalment_number')->get();

        return response()->json([
            'status' => true,
            'message' => $loanAccountId
                            ? 'EMIs for selected loan account fetched successfully'
                            : 'All EMIs for user fetched successfully',
            'emis' => EmiResource::collection($emis),
        ]);
    }

    public function generateEmiReceipt($id)
    {
        $decodedId = \App\Support\HashId::decode((string) $id);
        $realId = $decodedId ?: (is_numeric($id) ? (int) $id : null);

        if (! $realId) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid EMI Receipt ID',
            ], 404);
        }

        $emi = Emi::with('loanAccount', 'loanAccount.client', 'collections.emi')->findOrFail($realId);
        $loan = $emi->loanAccount;
        $client = optional($loan)->client;

        $receiptData = app(\App\Http\Controllers\EmiController::class)->buildReceiptPayload($emi);
        $receiptData['account_number'] = $receiptData['account_number']
            ?? optional($loan)->account_number
            ?? optional($loan)->customer_loan_account_number;
        $receiptData['disbursed_date'] = $receiptData['disbursed_date'] ?? optional($loan)->disbursed_at;

        $body = view('pdf.payment-receipt-api', compact('receiptData', 'client', 'loan'))->render();

        $html = view('pdf.dynamic_document', [
            'title'  => 'Payment Receipt',
            'body'   => $body,
            'client' => $client,
            'loan'   => $loan,
        ])->render();

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

        $fileName = 'receipt-' . $receiptData['receipt_number'] . '.pdf';
        $filePath = "receipts/" . $fileName;

        Storage::disk('public')->put($filePath, $mpdf->Output('', 'S'));

        $encodedEmiId = \App\Support\HashId::encode($emi->id);
        $encodedAccountId = $loan ? \App\Support\HashId::encode($loan->id) : null;

        return response()->json([
            'status' => true,
            'message' => 'Receipt generated successfully',
            'url' => asset('storage/' . $filePath),
            'receipt_url' => url('/emi/receipt/' . ($encodedEmiId ?: $emi->id)),
            'receipt_view_url' => url('/emi/receipt/' . ($encodedEmiId ?: $emi->id)),
            'admin_receipt_url' => url('/emi/receipts/view/' . ($encodedEmiId ?: $emi->id)),
            'statement_url' => $encodedAccountId ? url('/loan/statement/' . $encodedAccountId) : null,
            'statement_view_url' => $encodedAccountId ? url('/loan/statement/' . $encodedAccountId) : null,
        ]);
    }

}
