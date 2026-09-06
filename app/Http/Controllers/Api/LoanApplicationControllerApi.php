<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanAccount;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\LoanApplicationResource;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\LoanHistoryResource;
use App\Models\LoanApplicationDetail;
use Illuminate\Support\Facades\Log;
use App\Models\LoanConfiguration;

class LoanApplicationControllerApi extends Controller
{
    public function applyForLoan(Request $request)
    {
        $user = Auth::user();
        $client = $user->client;

        $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'extra_data' => 'nullable',
            'vehicle_image' => 'nullable|image|max:5120',
            'business_proofs.*' => 'nullable|mimes:pdf|max:5120', // each PDF max 5MB
        ]);

        $loanProduct = LoanProduct::findOrFail($request->loan_product_id);

        DB::beginTransaction();

        try {
            // 1. Create the loan application
            $application = LoanApplication::create([
                'client_id' => $client->id,
                'loan_product_id' => $loanProduct->id,
                'loan_name' => $loanProduct->loan_name,
                'loan_code' => $loanProduct->loan_code,
                'loan_amount_min' => $loanProduct->loan_amount_min,
                'loan_amount_max' => $loanProduct->loan_amount_max,
                'interest_rate' => $loanProduct->interest_rate,
                'tenure' => $loanProduct->tenure,
            ]);

            // 2. Prepare loan application details
            $detailsData = [
                'loan_application_id' => $application->id,
                'details' => $request->extra_data ?? [],
            ];

            // 3. Handle vehicle image upload
            if ($request->hasFile('vehicle_image')) {
                $path = $request->file('vehicle_image')->store('vehicle_images', 'public');
                $detailsData['vehicle_image'] = $path;
            }

            // 4. Handle business proof PDFs (if any)
            if ($request->hasFile('business_proofs')) {
                $businessProofPaths = [];
                foreach ($request->file('business_proofs') as $file) {
                    $businessProofPaths[] = $file->store('business_proofs', 'public');
                }
                $detailsData['business_proofs'] = $businessProofPaths;
            }

            // 5. Create LoanApplicationDetail if there's any data
            if (!empty($detailsData['details']) || isset($detailsData['vehicle_image']) || isset($detailsData['business_proofs'])) {
                LoanApplicationDetail::create($detailsData);
            }

            DB::commit();

            // Fire event
            event(new \App\Events\NewLoanApplicationEvent($application));

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted successfully.',
                'application_id' => $application->id,
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error("Loan Application Failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Loan application failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listApplications(Request $request, $id = null)
    {
        $user = Auth::user();
        $client = $user->client;

        if ($id) {
            $application = LoanApplication::with(['product', 'loanAccount', 'client'])
                ->where('client_id', $client->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Loan Application detail fetched successfully.',
                'data' => new LoanApplicationResource($application),
            ], 200);
        }

        $applications = LoanApplication::with('product')
            ->where('client_id', $client->id)
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Loan Applications fetched successfully.',
            'data' => LoanApplicationResource::collection($applications),
        ], 200);
    }

    public function proceedApplication(Request $request)
    {
        $user = Auth::user();
        $client = $user->client;

        $validated = $request->validate([
            'application_id' => 'required|exists:loan_applications,id',
            'loan_amount' => 'required|numeric|min:0',
            'tenure' => 'required|integer|min:1',
            'emi_day' => 'required|integer|min:1|max:28',
            'payment_gateway' => 'required|in:razor-pay,cash-free,pay-U',
            'payment_method' => 'required|in:e-nach,manual',
            'verification_video' => 'required|file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:51200',
            'loan_agreement_pdf' => 'required|file|mimetypes:application/pdf|max:10240',
        ]);

        DB::transaction(function () use ($request, $client) {

            $application = LoanApplication::where('id', $request->application_id)
                ->where('client_id', $client->id)
                ->firstOrFail();

            $updateData = [
                'loan_amount' => $request->loan_amount,
                'tenure' => $request->tenure,
                'emi_day' => $request->emi_day,
                'payment_method' => $request->payment_method,
                'payment_gateway' => $request->payment_gateway,
                'status' => 'in_progress',
            ];

            if ($request->hasFile('verification_video')) {

                // temporarily increasing memory for base64 encoding
                ini_set('memory_limit', '512M');

                $file = $request->file('verification_video');
                $fileContents = file_get_contents($file->getRealPath());
                $base64Video = 'data:' . $file->getMimeType() . ';base64,' . base64_encode($fileContents);

                $updateData['loan_code_video'] = $base64Video;

                Log::info('Processing verification video', [
                    'application_id' => $application->id,
                    'size_bytes' => strlen($base64Video)
                ]);
            }

            if ($request->hasFile('loan_agreement_pdf')) {

                $pdf = $request->file('loan_agreement_pdf');

                $path = $pdf->store('loan_documents', 'public');

                $updateData['loan_agreement_pdf'] = $path;
            }

            $application->update($updateData);
        });

        return response()->json([
            'status' => true,
            'message' => 'Loan application proceeded to next step',
        ], 200);
    }


    public function loanHistory()
    {
        $user = Auth::user();
        $client = $user->client;

        $activeLoans = LoanAccount::with(['emis.loanAccount.loanApplication', 'loanApplication.product'])
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        $closedLoans = LoanAccount::with(['emis.loanAccount.loanApplication', 'loanApplication.product'])
            ->where('client_id', $client->id)
            ->where('status', 'closed')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Loan history fetched successfully',
            'active_loans' => LoanHistoryResource::collection($activeLoans),
            'closed_loans' => LoanHistoryResource::collection($closedLoans),
        ]);
    }

    public function loanDetail($account_id)
    {
        $loan = LoanAccount::with(['client', 'emis.loanAccount.loanApplication', 'loanApplication.product'])
                    ->where('id', $account_id)
                    ->first();

        $makePayments = LoanConfiguration::where('is_active', true)
                        ->whereIn('type', ['foreclosure', 'prepayment', 'partial_payment'])
                        ->get()
                        ->keyBy('type');

        if (!$loan) {
            return response()->json([
                'success' => false,
                'message' => 'Loan not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LoanHistoryResource($loan),
            'make_payments' => $makePayments,
        ]);
    }

}
