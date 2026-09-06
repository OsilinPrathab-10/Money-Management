<?php

namespace App\Utils;

use App\Models\SmsTemplate;
use App\Utils\SMSHelper;
use Illuminate\Support\Facades\Log;

class SMSUtility
{
    /**
     * Generic method to send SMS using templates
     */
    public static function send($phone, $identifier, array $variables = [])
    {
        // 1. Fetch active template
        $template = SmsTemplate::where('identifier', $identifier)
            ->where('status', true)
            ->first();

        if (!$template) {
            $message = "SMS template [{$identifier}] not found or inactive";
            Log::error($message);
            return ['status' => false, 'message' => $message];
        }

        // 2. Validate template_id
        if (empty($template->template_id)) {
            $message = "SMS template_id is missing for template [{$identifier}]";
            Log::error($message);
            return ['status' => false, 'message' => $message];
        }

        // 3. Prepare SMS body
        $body = $template->sms_body;
        $allVariables = array_merge([
            'site_name' => config('app.name', 'Shanmuga Finance'),
        ], $variables);

        foreach ($allVariables as $key => $value) {
            $body = str_replace('[[' . $key . ']]', $value, $body);
        }

        // 4. Send SMS via helper
        $sent = SMSHelper::sendSMS($phone, config('app.name'), $body, $template->template_id);

        if (!$sent['success']) {
            $message = "Failed to send SMS to {$phone}: " . ($sent['message'] ?? 'Unknown error');
            Log::error($message);
            return ['status' => false, 'message' => $message];
        }

        return ['status' => true, 'message' => "SMS [{$identifier}] sent successfully"];
    }

    /**
     * Send OTP SMS
     */
    public static function otp($phone, $otp)
    {
        return self::send($phone, 'otp', ['code' => $otp]);
    }

    /**
     * Send Loan Submitted SMS
     */
    public static function loanSubmitted($phone, $name, $appNo, $amount)
    {
        return self::send($phone, 'loan_submitted', [
            'name' => $name,
            'app_no' => $appNo,
            'amount' => number_format($amount, 0)
        ]);
    }

    /**
     * Send Loan Approved/Converted SMS
     */
    public static function loanApproved($phone, $name, $appNo, $amount)
    {
        return self::send($phone, 'loan_approved', [
            'name' => $name,
            'app_no' => $appNo,
            'amount' => number_format($amount, 0)
        ]);
    }

    /**
     * Send Loan Activated/Disbursed SMS
     */
    public static function loanActivated($phone, $name, $accNo, $disbursedAmount)
    {
        return self::send($phone, 'loan_activated', [
            'name' => $name,
            'acc_no' => $accNo,
            'disbursed_amount' => number_format($disbursedAmount, 0)
        ]);
    }

    /**
     * Send EMI Reminder SMS
     */
    public static function emiReminder($phone, $name, $accNo, $amount, $dueDate)
    {
        return self::send($phone, 'emi_reminder', [
            'name' => $name,
            'acc_no' => $accNo,
            'amount' => number_format($amount, 0),
            'date' => $dueDate
        ]);
    }

}
