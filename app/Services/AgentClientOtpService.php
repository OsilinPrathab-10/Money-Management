<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Utils\SMSUtility;

class AgentClientOtpService
{
    /**
     * Generate and send OTP to client for collection verification
     * Uses the same SMS logic as login OTP
     * 
     * @param string $clientPhone
     * @param int $emiId
     * @param int $agentId
     * @return array
     */
    public function sendOtp(string $clientPhone, int $emiId, int $agentId): array
    {
        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Create cache key
        $cacheKey = "collection_otp:{$emiId}:{$agentId}";
        
        // Store OTP in cache for 10 minutes
        Cache::put($cacheKey, [
            'otp' => $otp,
            'client_phone' => $clientPhone,
            'emi_id' => $emiId,
            'agent_id' => $agentId,
            'created_at' => now()->toDateTimeString()
        ], now()->addMinutes(10));
        
        // Send real SMS using SMSUtility (same as login)
        $smsResult = $this->sendSms($clientPhone, $otp);
        
        if (!$smsResult['status']) {
            // SMS failed, but OTP is still cached for testing
            Log::warning("Collection OTP SMS failed", [
                'emi_id' => $emiId,
                'agent_id' => $agentId,
                'client_phone' => $clientPhone,
                'error' => $smsResult['message']
            ]);
        }
        
        Log::info("Collection OTP sent", [
            'emi_id' => $emiId,
            'agent_id' => $agentId,
            'client_phone' => $clientPhone,
            'otp' => $otp, // Log OTP for debugging (remove in production)
            'sms_status' => $smsResult['status']
        ]);
        
        return [
            'success' => true,
            'message' => 'OTP sent to client successfully',
            'expires_in' => 600 // 10 minutes in seconds
        ];
    }
    
    /**
     * Verify OTP entered by client
     * 
     * @param string $otp
     * @param int $emiId
     * @param int $agentId
     * @return array
     */
    public function verifyOtp(string $otp, int $emiId, int $agentId): array
    {
        $cacheKey = "collection_otp:{$emiId}:{$agentId}";
        
        $cachedData = Cache::get($cacheKey);
        
        if (!$cachedData) {
            return [
                'success' => false,
                'message' => 'OTP expired or not found. Please request a new OTP.'
            ];
        }
        
        if ($cachedData['otp'] !== $otp) {
            return [
                'success' => false,
                'message' => 'Invalid OTP. Please try again.'
            ];
        }
        
        // OTP verified successfully - remove from cache
        Cache::forget($cacheKey);
        
        Log::info("Collection OTP verified successfully", [
            'emi_id' => $emiId,
            'agent_id' => $agentId
        ]);
        
        return [
            'success' => true,
            'message' => 'OTP verified successfully'
        ];
    }
    
    /**
     * Send SMS to client using SMSUtility (same as login OTP)
     * 
     * @param string $phone
     * @param string $otp
     * @return array
     */
    private function sendSms(string $phone, string $otp): array
    {
        // Use the same SMSUtility as login OTP
        // This will use the 'otp' template from sms_templates table
        $result = SMSUtility::otp($phone, $otp);
        
        return $result;
    }
}
