<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\KycDetail;
use App\Models\Client;
use App\Models\Nominee;
use App\Models\EmployeeInformation;
use App\Support\CustomerSettlementLog;

class CheckUserKyc
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — not logged in', [
                'code' => 'USER_NOT_FOUND',
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. User not found.',
                'code' => 'USER_NOT_FOUND'
            ], 401);
        }

        $client = Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();

        if (!$client) {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — client profile not found', [
                'code' => 'CLIENT_NOT_FOUND',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Client profile not found.',
                'code' => 'CLIENT_NOT_FOUND'
            ], 403);
        }

        $kyc = KycDetail::where('client_id', $client->id)->first();
        $nominee = Nominee::where('client_id', $client->id)->first();
        $employee = EmployeeInformation::where('client_id', $client->id)->first();

        if (!$kyc) {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — KYC not found', [
                'code' => 'KYC_NOT_FOUND',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'client_name' => $client->client_name,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'KYC details not found. Please submit your KYC first.',
                'code' => 'KYC_NOT_FOUND'
            ], 403);
        }

        if ($kyc->status === 'pending') {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — KYC pending', [
                'code' => 'KYC_PENDING',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'client_name' => $client->client_name,
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Your KYC is still under verification.',
                'code'    => 'KYC_PENDING',
            ], 403);
        }

        if ($kyc->status === 'rejected') {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — KYC rejected', [
                'code' => 'KYC_REJECTED',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'client_name' => $client->client_name,
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Your KYC has been rejected.',
                'code'    => 'KYC_REJECTED',
            ], 403);
        }

        if (empty($kyc->aadhaar_number)) {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — Aadhaar missing', [
                'code' => 'AADHAAR_MISSING',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Aadhaar number is missing. Please update your Aadhaar details.',
                'code' => 'AADHAAR_MISSING'
            ], 403);
        }

        if (empty($kyc->pan_number)) {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — PAN missing', [
                'code' => 'PAN_MISSING',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'PAN number is missing. Please update your PAN details.',
                'code' => 'PAN_MISSING'
            ], 403);
        }

        if (empty($kyc->account_number) || empty($kyc->ifsc_code)) {
            $this->logSettlementKyc($request, 'APPLY BLOCKED — bank details missing', [
                'code' => 'BANK_DETAILS_MISSING',
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Bank details are incomplete. Please update your bank information.',
                'code' => 'BANK_DETAILS_MISSING'
            ], 403);
        }

        // If KYC is not already verified, check selfie and nominee
        if ($kyc->status !== 'verified') {
            if (empty($kyc->selfie_image)) {
                $this->logSettlementKyc($request, 'APPLY BLOCKED — selfie missing', [
                    'code' => 'SELFIE_IMAGE_MISSING',
                    'login' => $user->name,
                    'phone' => $user->phone,
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Selfie Image is incomplete. Please update your selfie verification.',
                    'code' => 'SELFIE_IMAGE_MISSING'
                ], 403);
            }

            if (!$nominee) {
                $this->logSettlementKyc($request, 'APPLY BLOCKED — nominee not found', [
                    'code' => 'NOMINEE_NOT_FOUND',
                    'login' => $user->name,
                    'phone' => $user->phone,
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Nominee details not found. Please submit your nominee details first.',
                    'code' => 'NOMINEE_NOT_FOUND'
                ], 403);
            }
        }

        return $next($request);
    }

    protected function logSettlementKyc(Request $request, string $event, array $context = []): void
    {
        $path = strtolower($request->path());
        if (! str_contains($path, 'settlement') && ! str_contains($path, 'payout')) {
            return;
        }

        CustomerSettlementLog::write($event, array_merge([
            'path' => $request->path(),
        ], $context));
    }
}
