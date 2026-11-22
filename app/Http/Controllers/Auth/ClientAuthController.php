<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use App\Utils\SMSUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ClientAuthController extends Controller
{
    /**
     * Show the client login form.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('Client')) {
                return redirect()->route('client.dashboard');
            }
            // If they are staff, they can still access the client login if they want, 
            // but usually we redirect to their respective dashboard.
            return redirect('/dashboard'); 
        }
        return view('client.auth.star-login');
    }

    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect()->route('client.dashboard');
        }

        return view('client.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'client_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/', 'unique:users,phone'],
            'consent' => 'accepted',
        ]);

        $phone = $data['phone'];

        $user = User::create([
            'name' => $data['client_name'],
            'email' => $data['email'],
            'phone' => $phone,
            'password' => Hash::make($phone),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('Client');
        }

        $client = Client::create([
            'user_id' => $user->id,
            'client_name' => $data['client_name'],
            'client_email' => $data['email'],
            'client_phone' => $phone,
            'status' => 'active',
            'accepted_terms' => true,
            'accepted_privacy' => true,
        ]);

        Client::assignCustomerId($client);

        Auth::login($user);

        return redirect()
            ->route('client.dashboard')
            ->with('info', __('Welcome! Your login is your email and your temporary password is your mobile number. Please change your password in Profile.'));
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('Client'), 403);

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phoneOk = trim($data['current_password']) === trim((string) $user->phone);
        if (! Hash::check($data['current_password'], $user->password) && ! $phoneOk) {
            return back()->withErrors(['current_password' => __('Current password is incorrect.')]);
        }

        $user->password = Hash::make($data['password']);
        $user->must_change_password = false;
        $user->save();

        return back()->with('success', __('Password updated successfully.'));
    }

    /**
     * Send OTP to the provided phone number.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|digits:10',
        ]);

        $phone = $request->phone;
        Log::info("OTP Request started for phone: $phone");
        
        // Find user by phone in users table
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            Log::warning("OTP Request: Phone number $phone not registered.");
            return response()->json([
                'success' => false,
                'message' => 'Phone number not registered.'
            ], 404);
        }

        Log::info("User found for phone $phone: ID " . $user->id);

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);
        
        // Store OTP in cache for 5 minutes
        Cache::put('client_otp_' . $phone, $otp, now()->addMinutes(5));

        // For development/testing purposes, log the OTP
        Log::info("OTP for $phone: $otp");

        // Send via SMS
        Log::info("Attempting to send SMS OTP to $phone with code $otp");
        $response = SMSUtility::otp($phone, $otp);
        Log::info("SMSUtility response for $phone: " . json_encode($response));
        
        if (!$response['status']) {
            Log::error("SMS OTP failed for $phone: " . $response['message']);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP via SMS. Please try again later.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully to your registered mobile number.',
            'test_otp' => config('app.debug') ? $otp : null 
        ]);
    }

    /**
     * Verify the OTP and log the user in.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|digits:10',
            'otp' => 'required|digits:6',
        ]);

        $cachedOtp = Cache::get('client_otp_' . $request->phone);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.'
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Clear OTP
        Cache::forget('client_otp_' . $request->phone);

        // Login user
        Auth::login($user);

        // Ensure user has 'Client' role
        if (!$user->hasRole('Client')) {
            $user->assignRole('Client');
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => route('client.dashboard')
        ]);
    }

    /**
     * Handle client login request (Email login, Phone password).
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $auth = app(AuthenticatedSessionController::class);
        $user = $auth->resolveUserForLogin($request->input('login'));

        if (! $user || ! $auth->passwordMatches($user, $request->input('password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if (method_exists($user, 'hasRole') && ! $user->hasRole('Client') && ! $user->hasAnyRole(['Admin', 'Staff', 'Agent', 'CreditVerifier'])) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not enabled for portal access.',
            ], 403);
        }

        $redirect = $user->hasRole('Client')
            ? ($user->must_change_password ? route('client.profile') : route('client.dashboard'))
            : route('dashboard');

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => $redirect,
        ]);
    }

    /**
     * Log the client out.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
