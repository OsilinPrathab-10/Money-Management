<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserLiveLocation;
use App\Services\MenuAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthControllerApi extends Controller
{
    /**
     * Agent mobile login — same credentials as web login (email + password).
     * Optional device fields for FCM / device tracking.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'device_token' => 'nullable|string|max:2048',
            'fcm_token' => 'nullable|string|max:2048',
        ]);

        $this->ensureIsNotRateLimited($request);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($this->throttleKey($request));

            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (! $user->hasRole('Agent')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized role. Agent access only.',
            ], 403);
        }

        $agent = Agent::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $agent) {
            return response()->json([
                'status' => false,
                'message' => 'Agent account not found or inactive',
            ], 403);
        }

        RateLimiter::clear($this->throttleKey($request));

        $deviceId = $request->input('device_id') ?: ('agent-app-' . $agent->id);
        $fcmToken = trim((string) ($request->input('fcm_token') ?: $request->input('device_token') ?: ''));

        if ($fcmToken !== '') {
            UserDevice::where('device_token', $fcmToken)
                ->where('user_id', '!=', $user->id)
                ->update(['device_token' => null]);
        }

        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'user_type' => 'Agent',
                'device_name' => $request->input('device_name', 'Agent App'),
                'device_model' => $request->input('device_model', 'Mobile'),
                'device_token' => $fcmToken !== '' ? $fcmToken : $request->input('device_token'),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'ip_address' => $request->ip(),
                'login_at' => now(),
                'logout_at' => null,
            ]
        );

        if ($fcmToken !== '') {
            $user->fcm_token = $fcmToken;
            $user->save();
        }

        // Revoke previous token for this device, then issue a new one
        $agent->tokens()->where('name', $deviceId)->delete();
        $token = $agent->createToken($deviceId)->plainTextToken;

        $firstMenuUrl = null;
        try {
            $firstMenuUrl = app(MenuAccessService::class)->firstUrlForUser($user);
        } catch (\Throwable $e) {
            // Menu service optional for mobile
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'agent' => [
                    'id' => $agent->id,
                    'user_id' => $user->id,
                    'name' => $agent->agent_name,
                    'email' => $user->email,
                    'phone' => $agent->agent_phone,
                    'code' => $agent->agent_code,
                    'status' => $agent->status,
                    'location_id' => $agent->location_id,
                ],
                'role' => $user->getRoleNames()->first(),
                'permissions' => method_exists($user, 'getAllPermissions')
                    ? $user->getAllPermissions()->pluck('name')->values()
                    : [],
                'default_menu_url' => $firstMenuUrl,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $agent = $request->user();

        if ($agent instanceof Agent) {
            UserDevice::where('user_id', $agent->user_id)
                ->where('user_type', 'Agent')
                ->update([
                    'logout_at' => now(),
                    'device_token' => null,
                ]);

            if ($agent->user) {
                $remaining = UserDevice::where('user_id', $agent->user_id)
                    ->whereNull('logout_at')
                    ->whereNotNull('device_token')
                    ->value('device_token');
                $agent->user->fcm_token = $remaining ?: null;
                $agent->user->save();
            }

            $token = $agent->currentAccessToken();
            if ($token) {
                $token->delete();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * POST /api/agent/fcm-token
     * Body: { fcm_token | device_token, device_id?, device_name?, device_model? }
     */
    public function registerFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'nullable|string|max:2048',
            'device_token' => 'nullable|string|max:2048',
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
        ]);

        $token = trim((string) ($request->input('fcm_token') ?: $request->input('device_token') ?: ''));
        if ($token === '') {
            return response()->json([
                'status' => false,
                'message' => 'The fcm_token or device_token field is required.',
            ], 422);
        }

        $agent = $request->user();
        if (! $agent instanceof Agent) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $user = $agent->user;
        if (! $user) {
            return response()->json(['status' => false, 'message' => 'Agent user not found.'], 404);
        }

        $deviceId = $request->input('device_id') ?: ('agent-app-' . $agent->id);

        UserDevice::where('device_token', $token)
            ->where('user_id', '!=', $user->id)
            ->update(['device_token' => null]);

        $device = UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'user_type' => 'Agent',
                'device_name' => $request->input('device_name', 'Agent App'),
                'device_model' => $request->input('device_model', 'Mobile'),
                'device_token' => $token,
                'ip_address' => $request->ip(),
                'login_at' => now(),
                'logout_at' => null,
            ]
        );

        $user->fcm_token = $token;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'FCM device token saved successfully',
            'data' => [
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'fcm_token' => $token,
                'device_id' => $device->device_id,
            ],
        ]);
    }

    public function sendForgetPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->hasRole('Agent')) {
            return response()->json([
                'status' => false,
                'message' => 'Agent user not found',
            ], 404);
        }

        $otp = random_int(100000, 999999);
        Cache::put('forget_password_otp_' . $user->id, $otp, now()->addMinutes(5));

        Mail::raw("Your OTP for password reset is: {$otp}", function ($message) use ($user) {
            $message->to($user->email)->subject('Password Reset OTP');
        });

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email',
        ]);
    }

    public function verifyForgetPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        $cachedOtp = Cache::get('forget_password_otp_' . $user->id);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully',
        ]);
    }

    public function changePasswordAfterOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        if (! Cache::has('forget_password_otp_' . $user->id)) {
            return response()->json([
                'status' => false,
                'message' => 'OTP not verified or expired',
            ], 400);
        }

        $user->password = bcrypt($request->new_password);
        $user->save();
        Cache::forget('forget_password_otp_' . $user->id);

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    public function refresh(Request $request)
    {
        $agent = $request->user();

        if (! $agent instanceof Agent) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $oldToken = $agent->currentAccessToken();
        $tokenName = $oldToken?->name ?: ('agent-app-' . $agent->id);

        if ($oldToken) {
            $oldToken->delete();
        }

        $token = $agent->createToken($tokenName)->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function me(Request $request)
    {
        $agent = $request->user();

        if (! $agent instanceof Agent) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $agent->loadMissing(['user', 'location']);
        $user = $agent->user;

        return response()->json([
            'status' => true,
            'message' => 'Agent profile fetched successfully',
            'data' => [
                'agent' => [
                    'id' => $agent->id,
                    'user_id' => $agent->user_id,
                    'name' => $agent->agent_name,
                    'email' => $user?->email,
                    'phone' => $agent->agent_phone,
                    'code' => $agent->agent_code,
                    'status' => $agent->status,
                    'address' => $agent->address,
                    'city' => $agent->city,
                    'state' => $agent->state,
                    'pincode' => $agent->pincode,
                    'location' => $agent->location ? [
                        'id' => $agent->location->id,
                        'name' => $agent->location->name,
                    ] : null,
                ],
                'role' => $user?->getRoleNames()->first(),
            ],
        ]);
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $agent = $request->user();
        $userId = $agent instanceof Agent ? $agent->user_id : ($agent->id ?? null);

        if (! $userId) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to resolve agent user',
            ], 422);
        }

        UserLiveLocation::updateOrCreate(
            ['user_id' => $userId],
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

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')) . '|agent-api|' . $request->ip());
    }
}
