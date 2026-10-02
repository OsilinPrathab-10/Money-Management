<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Agent;
use App\Models\UserDevice;
use App\Models\UserOtp;
use App\Models\SmsOtpLog;
use App\Utils\SMSUtility;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LoginControllerApi extends Controller
{
    public function sendOtp(Request $request)
    {
        $appType = strtolower($request->header('X-App-Type', 'unknown'));

        if (!in_array($appType, ['client', 'agent'])) {
            return response()->json(['status' => false, 'message' => 'Invalid X-App-Type'], 400);
        }

        $request->validate([
            'phone' => 'required|numeric|digits:10'
        ]);

        $phone = $request->phone;

        if ($appType === 'client') {
            $userModel = Client::class;
            $phoneField = 'client_phone';
        } else {
            $userModel = Agent::class;
            $phoneField = 'agent_phone';
        }

        // Check if phone exists
        $entity = $userModel::withTrashed()
            ->where($phoneField, $phone)
            ->first();

        if ($entity && $entity->trashed()) {
            $entity->restore();
        }
        
        // If not exists, create new
        if (!$entity) {
            try {
                $entity = $userModel::create([
                    $phoneField => $phone,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle duplicate entry - fetch existing record
                if ($e->getCode() == 23000) {
                    $entity = $userModel::where($phoneField, $phone)->first();
                    if (!$entity) {
                        return response()->json(['status' => false, 'message' => 'Unable to process request'], 500);
                    }
                } else {
                    throw $e;
                }
            }
        }

        $user = $entity->user; // fetch corresponding user

        $otpCode = random_int(100000, 999999);
        if ($user) {
            UserOtp::create([
                'user_id' => $user->id,
                'user_type' => $appType,
                'otp_code' => $otpCode,
                'expires_at' => now()->addSeconds(30),
            ]);
        }

        $logMeta = [
            'purpose' => SmsOtpLog::PURPOSE_LOGIN,
            'mobile' => $phone,
            'user_id' => $user?->id,
            'client_id' => $appType === 'client' ? $entity->id : null,
        ];

        if ($phone === '9876543210') {
            SmsOtpLog::record($logMeta + [
                'status' => SmsOtpLog::STATUS_TEST,
                'provider' => 'test',
                'provider_message' => 'OTP sent successfully (Test Mode)',
            ]);

            return response()->json(['status' => true, 'message' => 'OTP sent successfully (Test Mode)']);
        }

        $result = SMSUtility::otp($phone, $otpCode);
        $ok = is_array($result) ? (bool) ($result['status'] ?? false) : (bool) $result;

        SmsOtpLog::record($logMeta + [
            'status' => $ok ? SmsOtpLog::STATUS_SENT : SmsOtpLog::STATUS_FAILED,
            'provider' => 'msg91',
            'provider_message' => is_array($result) ? ($result['message'] ?? null) : null,
        ]);

        return response()->json($result);
    }

    public function verifyOtp(Request $request)
    {
        $appType = strtolower($request->header('X-App-Type', 'unknown'));
        Log::info('OTP verification request received', [
            'phone' => $request->phone,
            'app_type' => $appType,
            'ip' => $request->ip(),
            'device_id' => $request->device_id,
        ]);

        if (!in_array($appType, ['client', 'agent'])) {
            Log::warning('Invalid X-App-Type header', ['app_type' => $appType]);
            return response()->json(['status' => false, 'message' => 'Invalid X-App-Type'], 400);
        }

        $request->validate([
            'phone' => 'required|numeric|digits:10',
            'otp' => 'required|numeric|digits:6',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'device_id' => 'required|string|max:255',
            'device_model' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
        ]);

        $userModel = $appType === 'client' ? Client::class : Agent::class;
        $entity = $userModel::where('client_phone', $request->phone)->first();

        if (!$entity) {
            Log::warning('User not found for OTP verification', ['phone' => $request->phone, 'app_type' => $appType]);
            return response()->json(['status' => false, 'message' => ucfirst($appType) . ' not found'], 404);
        }

        $user = $entity->user;

        if ($request->phone === '9876543210' && $request->otp === '123456') {
            $otpRecord = (object)['user_id' => $user->id]; // Fake record for bypass
        } else {
            $otpRecord = UserOtp::where('user_id', $user->id)
                ->where('otp_code', $request->otp)
                ->where('expires_at', '>=', now())
                ->latest()
                ->first();
        }

        if (!$otpRecord) {
            Log::warning('Invalid or expired OTP attempt', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'otp' => $request->otp,
            ]);
            return response()->json(['status' => false, 'message' => 'Invalid or expired OTP'], 422);
        }

        // Delete only tokens from the same device (per-device token management)
        // This allows multiple devices to stay logged in simultaneously
        $existingDevice = UserDevice::where('user_id', $user->id)
            ->where('device_id', $request->device_id)
            ->first();

        if ($existingDevice) {
            // Find and delete tokens created during this device's previous session
            // We identify tokens by creation time matching the device's login time
            $user->tokens()
                ->where('created_at', '>=', $existingDevice->login_at)
                ->where('created_at', '<=', $existingDevice->logout_at ?? now())
                ->delete();
            
            Log::info('Deleted previous tokens for device', [
                'user_id' => $user->id,
                'device_id' => $request->device_id,
            ]);
        }

        if ($request->filled('device_token')) {
            UserDevice::where('device_token', $request->device_token)->delete();
        }

        $device = UserDevice::updateOrCreate(
            [
                'user_id'   => $user->id,
                'device_id' => $request->device_id,
            ],
            [
                'user_type'     => $appType,
                'device_model'  => $request->device_model,
                'device_name'   => $request->device_name,
                'latitude'      => $request->latitude,
                'longitude'     => $request->longitude,
                'ip_address'    => $request->ip(),
                'device_token'  => $request->device_token ?? null,
                'login_at'      => now(),
                'logout_at'     => null,
            ]
        );

        Log::info('OTP verified successfully and device updated', [
            'user_id' => $user->id,
            'device_id' => $device->device_id,
            'phone' => $user->phone,
            'app_type' => $appType,
        ]);

        // Create new token for this device
        $token = $user->createToken('user-token', ['*'], null)->plainTextToken;

        if ($request->filled('mpin') && $appType === 'client' && $entity) {
            $mpinVal = (string) $request->input('mpin');
            $entity->mpin = $mpinVal;
            $entity->mpin_hash = \Illuminate\Support\Facades\Hash::make($mpinVal);
            $entity->mpin_set_at = now();
            $entity->save();
        }

        $mpinStatus = $appType === 'client' && $entity ? (bool) ($entity->mpin_hash ?? false) : false;

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully',
            'token' => $token,
            'mpin' => $mpinStatus,
            'mpin_set' => $mpinStatus,
            'mpin_status' => $mpinStatus,
            'user' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $appType,
                'mpin' => $mpinStatus,
                'mpin_set' => $mpinStatus,
                'mpin_status' => $mpinStatus,
            ]
        ]);
    }

    public function resendOtp(Request $request)
    {
        $appType = strtolower($request->header('X-App-Type', 'unknown'));

        if (!in_array($appType, ['client', 'agent'])) {
            return response()->json(['status' => false, 'message' => 'Invalid X-App-Type'], 400);
        }

        $request->validate([
            'phone' => 'required|numeric|digits:10',
            'template_id' => 'nullable|string|max:255',
        ]);

        $phone = $request->phone;
        $template_id = $request->template_id;

        $userModel = $appType === 'client' ? Client::class : Agent::class;

        $entity = $userModel::where('client_phone', $phone)->first();

        if (!$entity) {
            return response()->json([
                'status' => false,
                'message' => 'User not found for this phone number',
            ], 404);
        }

        $user = $entity->user;

        // Delete old OTPs
        UserOtp::where('user_id', $user->id)
            ->where('user_type', $appType)
            ->delete();

        // Generate and save new OTP
        $otpCode = random_int(100000, 999999);

        UserOtp::create([
            'user_id' => $user->id,
            'user_type' => $appType,
            'otp_code' => $otpCode,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Send new OTP
        $result = SMSUtility::otp($phone, $otpCode);
        $ok = is_array($result) ? (bool) ($result['status'] ?? $phone === '9876543210') : (bool) $result;

        SmsOtpLog::record([
            'purpose' => SmsOtpLog::PURPOSE_LOGIN,
            'mobile' => $phone,
            'user_id' => $user?->id,
            'client_id' => $appType === 'client' ? $entity->id : null,
            'status' => $phone === '9876543210'
                ? SmsOtpLog::STATUS_TEST
                : ($ok ? SmsOtpLog::STATUS_SENT : SmsOtpLog::STATUS_FAILED),
            'provider' => $phone === '9876543210' ? 'test' : 'msg91',
            'provider_message' => $phone === '9876543210'
                ? 'OTP resent successfully (Test Mode)'
                : (is_array($result) ? ($result['message'] ?? 'OTP resent successfully') : 'OTP resent successfully'),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'OTP resent successfully',
            'expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'user_id' => $user->id,
            'type' => $appType,
        ]);
    }

    public function registerDeviceToken(Request $request)
    {
        $token = $request->input('fcm_token')
            ?: $request->input('device_token')
            ?: $request->input('firebase_token')
            ?: $request->input('push_token')
            ?: $request->input('fcmToken')
            ?: $request->input('deviceToken')
            ?: $request->header('X-FCM-Token')
            ?: $request->input('token');

        if (empty($token)) {
            return response()->json([
                'status' => false,
                'message' => 'The fcm_token or device_token field is required.',
            ], 422);
        }

        if (! PushNotificationService::isLikelyFcmToken((string) $token)) {
            return response()->json([
                'status' => false,
                'message' => 'That value is not an FCM device token. Send the Firebase token from the app, not the API bearer token.',
            ], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $deviceId = $request->input('device_id')
            ?: ($request->header('X-Device-Id') ?: ('device-' . $user->id));
        $appType = strtolower($request->header('X-App-Type', 'client'));

        // Remove token from stale devices if re-registered by another user
        UserDevice::where('device_token', $token)->where('user_id', '!=', $user->id)->delete();

        $device = UserDevice::updateOrCreate(
            [
                'user_id'   => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'user_type'    => $appType,
                'device_name'  => $request->input('device_name', 'Mobile App'),
                'device_model' => $request->input('device_model', 'Mobile'),
                'device_token' => $token,
                'latitude'     => $request->input('latitude'),
                'longitude'    => $request->input('longitude'),
                'ip_address'   => $request->ip(),
                'login_at'     => now(),
                'logout_at'    => null,
            ]
        );

        // Update direct fcm_token columns on user and client
        $user->fcm_token = $token;
        $user->save();

        if ($user->client) {
            $user->client->fcm_token = $token;
            $user->client->save();
        }

        return response()->json([
            'status'  => true,
            'message' => 'FCM device token saved successfully',
            'data'    => [
                'user_id'      => $user->id,
                'client_id'    => $user->client?->id,
                'fcm_token'    => $token,
                'device_id'    => $device->device_id,
                'device_model' => $device->device_model,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user && $request->user()->currentAccessToken()) {
            $token = $request->user()->currentAccessToken();

            UserDevice::where('user_id', $user->id)
                ->latest('login_at')
                ->limit(1)
                ->update([
                    'logout_at' => Carbon::now(),
                ]);

            $token->delete();

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully.',
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Unable to logout. Token not found or invalid.',
        ], 401);
    }

}
