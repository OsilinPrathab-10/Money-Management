<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Agent;
use Illuminate\Support\Facades\Hash;
use App\Models\UserDevice;
use App\Models\UserLiveLocation;

class AuthControllerApi extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'        => 'required|email',
            'password'     => 'required|string|min:6',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'device_name'  => 'required|string',
            'device_model' => 'required|string',
            'device_id'    => 'required|string',
            'device_token' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $agent = Agent::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$agent) {
            return response()->json([
                'status' => false,
                'message' => 'Agent account not found or inactive'
            ], 403);
        }

        if (!$user->hasRole('Agent')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized role'
            ], 403);
        }

        UserDevice::updateOrCreate(
            [
                'user_id'   => $user->id,
                'device_id' => $request->device_id,
            ],
            [
                'user_type'   => 'Agent',
                'device_name'   => $request->device_name,
                'device_model'  => $request->device_model,
                'device_token'  => $request->device_token,
                'latitude'      => $request->latitude,
                'longitude'     => $request->longitude,
                'ip_address'    => $request->ip(),
                'login_at' => now(),
            ]
        );

        // delete token only for same device
        $user->tokens()
            ->where('name', $request->device_id)
            ->delete();

        // create new token
        $token = $agent->createToken('agent-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->agent_name,
                    'email' => $user->email,
                    'phone' => $agent->agent_phone,
                    'code' => $agent->agent_code,
                    'status' => $agent->status
                ],
                'role' => $user->getRoleNames()->first()
            ]
        ]);
    }

    public function sendForgetPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $otp = random_int(100000, 999999);

        Cache::put('forget_password_otp_'.$user->id, $otp, now()->addMinutes(5));

        Mail::raw("Your OTP for password reset is: {$otp}", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Password Reset OTP');
        });

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email'
        ]);
    }

    public function verifyForgetPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        $cachedOtp = Cache::get('forget_password_otp_'.$user->id);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired OTP'
            ], 400);
        }

        // OTP verified, return success but don't change password yet
        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully'
        ]);
    }

    public function changePasswordAfterOtp(Request $request)
    {
        $request->validate([
            'email'        => 'required|email',
            'new_password' => 'required|string|min:6|confirmed' // new_password + new_password_confirmation
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if OTP was verified (optional)
        if (!Cache::has('forget_password_otp_'.$user->id)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP not verified or expired'
            ], 400);
        }

        $user->password = bcrypt($request->new_password);
        $user->save();

        // Remove OTP after password change
        Cache::forget('forget_password_otp_'.$user->id);

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully'
        ]);
    }

    public function logout(Request $request)
    {
        // Implement logout logic here
    }

    public function refresh(Request $request)
    {
        // Implement token refresh logic here
    }

    public function me(Request $request)
    {
        // Implement logic to return authenticated agent's details here
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = auth()->user(); // authenticated user

        \App\Models\UserLiveLocation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'recorded_at' => now(),
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Location updated successfully',
        ]);
    }
}
