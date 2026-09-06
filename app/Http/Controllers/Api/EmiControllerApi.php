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
        $emi = Emi::with('loanAccount', 'loanAccount.client')->findOrFail($id);
        $loan = $emi->loanAccount;
        $client = $loan->client;

        $receiptData = [
            'receipt_number'     => 'RCPT-' . $emi->id,
            'paid_date'          => $emi->paid_date,
            'payment_reference'  => $emi->payment_reference,
            'payment_method'     => $emi->payment_method,

            'application_number' => $loan->application_number,
            'account_number'     => $loan->account_number,
            'disbursed_date'     => $loan->disbursed_at,

            'principal_amount'   => $emi->principal_amount,
            'interest_amount'    => $emi->interest_amount,
            'emi_amount'         => $emi->total_amount,
            'paid_amount'        => $emi->paid_amount,
            'overdue_amount'     => $emi->overdue_amount ?? 0,
            'show_overdue'       => $emi->overdue_amount > 0,
        ];

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

        return response()->json([
            'status' => true,
            'message' => 'Receipt generated successfully',
            'url' => asset('storage/' . $filePath),
        ]);
    }

}
