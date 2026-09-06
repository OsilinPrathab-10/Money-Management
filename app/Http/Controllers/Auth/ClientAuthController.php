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
        return view('authentications.auth-login', ['defaultTab' => 'customer']);
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
        
        // If not found in users table, check clients table
        if (!$user) {
            $client = Client::where('mobile_no', $phone)->first();
            if ($client && $client->user_id) {
                $user = User::find($client->user_id);
            }
        }

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
        Log::info("Client Login Attempt", ['login' => $request->login]);

        $request->validate([
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $login = $request->login;
        $password = $request->password;

        // Find user by Email or Phone
        $user = User::where('email', $login)
                    ->orWhere('phone', $login)
                    ->first();
        
        Log::info("User lookup result", ['found' => $user ? 'Yes' : 'No', 'user_id' => $user->id ?? 'N/A']);

        if (!$user) {
            // Check clients table as fallback
            $client = Client::where('client_email', $login)
                            ->orWhere('client_phone', $login)
                            ->first();
            
            if ($client) {
                // If client exists but user doesn't, or user_id is missing, find/create user
                if ($client->user_id) {
                    $user = User::find($client->user_id);
                }
                
                if (!$user) {
                    // Create user for the client
                    $user = User::firstOrCreate(
                        ['phone' => $client->client_phone],
                        [
                            'name' => $client->client_name,
                            'email' => $client->client_email,
                            'password' => Hash::make($client->client_phone),
                        ]
                    );
                    $client->user_id = $user->id;
                    $client->save();
                }
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        // The user wants: "client_phone --> password"
        // So we check if the entered password matches the hashed password OR the literal phone number.
        $passwordMatches = Hash::check($password, $user->password) || (trim($password) === trim($user->phone));

        if (!$passwordMatches) {
            Log::warning("Login Failed: Password mismatch", ['login' => $login]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.'
            ], 401);
        }

        Log::info("Login Success: Authenticating user", ['user_id' => $user->id]);

        // Login user
        Auth::login($user);

        // Ensure user has 'Client' role
        if (method_exists($user, 'hasRole') && !$user->hasRole('Client')) {
            $user->assignRole('Client');
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => route('client.dashboard')
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
        return redirect()->route('client.login');
    }
}
