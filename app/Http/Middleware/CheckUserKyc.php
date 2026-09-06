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
        $client = Client::where('user_id', $user->id)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. User not found.',
                'code' => 'USER_NOT_FOUND'
            ], 401);
        }

        $kyc = KycDetail::where('client_id', $client->id)->first();
        $nominee = Nominee::where('client_id', $client->id)->first();
        $employee = EmployeeInformation::where('client_id', $client->id)->first();

        if (!$kyc) {
            return response()->json([
                'status' => false,
                'message' => 'KYC details not found. Please submit your KYC first.',
                'code' => 'KYC_NOT_FOUND'
            ], 403);
        }

        if (empty($kyc->aadhaar_number)) {
            return response()->json([
                'status' => false,
                'message' => 'Aadhaar number is missing. Please update your Aadhaar details.',
                'code' => 'AADHAAR_MISSING'
            ], 403);
        }

        if (empty($kyc->pan_number)) {
            return response()->json([
                'status' => false,
                'message' => 'PAN number is missing. Please update your PAN details.',
                'code' => 'PAN_MISSING'
            ], 403);
        }

        if (empty($kyc->account_number) || empty($kyc->ifsc_code)) {
            return response()->json([
                'status' => false,
                'message' => 'Bank details are incomplete. Please update your bank information.',
                'code' => 'BANK_DETAILS_MISSING'
            ], 403);
        }

        if (empty($kyc->selfie_image) || empty($kyc->selfie_image)) {
            return response()->json([
                'status' => false,
                'message' => 'Selfie Image are incomplete. Please update your selfie verification.',
                'code' => 'SELFIE_IMAGE_MISSING'
            ], 403);
        }

        if ($kyc->status === 'pending') {
            return response()->json([
                'status'  => false,
                'message' => 'Your KYC is still under verification.',
                'code'    => 'KYC_PENDING',
            ], 403);
        }

        if ($kyc->status === 'rejected') {
            return response()->json([
                'status'  => false,
                'message' => 'Your KYC has been rejected.',
                'code'    => 'KYC_REJECTED',
            ], 403);
        }

        if (!$nominee) {
            return response()->json([
                'status' => false,
                'message' => 'Nominee details not found. Please submit your nominee details first.',
                'code' => 'NOMINEE_NOT_FOUND'
            ], 403);
        }

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee Information details not found. Please submit your Employee details first.',
                'code' => 'EMPLOYEE_INFORMATION_NOT_FOUND'
            ], 403);
        }

        return $next($request);
    }
}
