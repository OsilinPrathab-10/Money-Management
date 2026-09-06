<?php

namespace App\Utils;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSHelper
{
    public static function sendSMS($to, $from, $text, $template_id)
    {
        try {
            $userName = env('SMS_API_USER');
            $password = env('SMS_API_PASSWORD');
            $sender   = env('SMS_API_SENDER');
            $channel  = env('SMS_API_CHANNEL', 'Trans');
            $DCS      = env('SMS_API_DCS', 0);
            $flashsms = env('SMS_API_FLASH', 0);
            $url      = env('SMS_API_BASE_URL', 'http://137.59.55.14/api/mt/SendSMS');

            // Prefix with 91 if it's a 10-digit number (common for Indian gateways)
            if (strlen($to) == 10) {
                $to = '91' . $to;
            }

            $postData = [
                'user'          => $userName,
                'password'      => $password,
                'senderid'      => $sender,
                'channel'       => $channel,
                'DCS'           => $DCS,
                'flashsms'      => $flashsms,
                'number'        => $to,
                'text'          => $text,
                'DLTTemplateId' => $template_id,
            ];

            $logMsg = date('Y-m-d H:i:s') . " - SMS API Request: $url - PostData: " . json_encode($postData) . "\n";
            file_put_contents(public_path('sms_log.txt'), $logMsg, FILE_APPEND);

            $response = Http::withOptions([
                'verify' => false,
            ])->timeout(10)->connectTimeout(5)->get($url . '?' . http_build_query($postData));

            $result = $response->body();
            $logMsg = date('Y-m-d H:i:s') . " - SMS API Response: " . $result . "\n";
            file_put_contents(public_path('sms_log.txt'), $logMsg, FILE_APPEND);
            
            $decoded = json_decode($result, true);
            
            // Check if successful - either JSON success or non-empty body with common success indicators
            $isSuccess = false;
            if ($response->successful()) {
                if ($decoded && (isset($decoded['status']) && strtolower($decoded['status']) == 'success' || isset($decoded['responseCode']) && $decoded['responseCode'] == '200')) {
                    $isSuccess = true;
                } elseif (stripos($result, 'Message') !== false || stripos($result, 'JobID') !== false || stripos($result, 'Success') !== false) {
                    $isSuccess = true;
                } elseif ($decoded === null && !empty($result)) {
                    // If not JSON but we have a result and HTTP was 200, consider it a success for now
                    $isSuccess = true;
                }
            }

            return [
                'success'  => $isSuccess,
                'message'  => $isSuccess ? 'SMS sent successfully' : 'SMS delivery failed: ' . $result,
                'response' => $decoded ?? $result,
                'phone'    => $to
            ];
        } catch (Exception $e) {
            Log::error("SMS Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
