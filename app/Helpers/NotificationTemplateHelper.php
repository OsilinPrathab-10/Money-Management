<?php

namespace App\Helpers;

use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Log;

class NotificationTemplateHelper
{
    /**
     * Parse notification templates for SMS and WhatsApp redirects
     * 
     * @param array $data
     * @return array
     */
    public static function getRepaymentMessages(array $data): array
    {
        $clientName = $data['client_name'] ?? 'Client';
        $mobileNo = $data['mobile_no'] ?? '';
        $accountNo = $data['account_no'] ?? '';
        
        $amountPaidVal = floatval($data['amount_paid'] ?? 0);
        $amountPaid = number_format($amountPaidVal, 2, '.', ',');
        
        $remainingBalanceVal = floatval($data['remaining_balance'] ?? 0);
        $remainingBalance = number_format($remainingBalanceVal, 2, '.', ',');
        
        $emiBalanceVal = floatval($data['emi_balance'] ?? 0);
        $emiBalance = number_format($emiBalanceVal, 2, '.', ',');
        
        $loanMode = $data['loan_mode'] ?? '';
        $paymentType = $data['payment_type'] ?? '';
        $isPartial = filter_var($data['is_partial'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isKandhuvatti = ($loanMode === 'interest_only');

        // Construct Public Link
        $publicLink = '';
        if (!empty($data['application_number'])) {
            $publicToken = base64_encode($data['application_number']);
            $publicLink = url("/view-schedule/{$publicToken}");
        }

        // 1. Build Fallback Messages (the previous templates)
        $fallbackSms = '';
        if ($isKandhuvatti) {
            if ($paymentType === 'principal') {
                $fallbackSms = "Dear {$clientName},\nYour Principal payment of ₹{$amountPaid} towards Shanmuga Finance Open Loan Account {$accountNo} has been received successfully.\nRemaining Principal Balance: ₹{$remainingBalance}.\nThank you!";
            } else {
                if ($isPartial) {
                    $fallbackSms = "Dear {$clientName},\nYour Partial Interest payment of ₹{$amountPaid} towards Shanmuga Finance Open Loan Account {$accountNo} has been received successfully.\nBalance Interest to pay: ₹{$emiBalance}.\nRemaining Principal Balance: ₹{$remainingBalance}.\nThank you!";
                } else {
                    $fallbackSms = "Dear {$clientName},\nYour Interest payment of ₹{$amountPaid} towards Shanmuga Finance Open Loan Account {$accountNo} has been received successfully.\nRemaining Principal Balance: ₹{$remainingBalance}.\nThank you!";
                }
            }
        } else {
            if ($isPartial) {
                $fallbackSms = "Dear {$clientName},\nYour Partial EMI payment of ₹{$amountPaid} towards Shanmuga Finance Loan Account {$accountNo} has been received successfully.\nBalance EMI to pay: ₹{$emiBalance}.\nOutstanding Balance: ₹{$remainingBalance}.\nThank you!";
            } else {
                $fallbackSms = "Dear {$clientName},\nYour EMI payment of ₹{$amountPaid} towards Shanmuga Finance Loan Account {$accountNo} has been received successfully.\nOutstanding Balance: ₹{$remainingBalance}.\nThank you!";
            }
        }

        $fallbackWa = $fallbackSms;
        if (!empty($publicLink)) {
            $fallbackWa .= "\n\nPlease check your EMI Schedule here: {$publicLink}";
        }

        // 2. Fetch templates from database
        $smsTemplate = SmsTemplate::where('identifier', 'payment_received_sms')->where('status', true)->first();
        $whatsappTemplate = SmsTemplate::where('identifier', 'payment_received_whatsapp')->where('status', true)->first();

        // 3. Format placeholders
        $variables = [
            'client_name' => $clientName,
            'amount_paid' => $amountPaid,
            'remaining_balance' => $remainingBalance,
            'account_no' => $accountNo,
            'emi_balance' => $emiBalance,
            'payment_type' => $paymentType === 'principal' ? 'Principal' : ($paymentType === 'interest' ? 'Interest' : 'EMI'),
            'public_link' => $publicLink,
        ];

        $smsMessage = $smsTemplate ? $smsTemplate->sms_body : $fallbackSms;
        $whatsappMessage = $whatsappTemplate ? $whatsappTemplate->sms_body : $fallbackWa;

        foreach ($variables as $key => $val) {
            $smsMessage = str_replace(["[[{$key}]]", "{{{$key}}}"], $val, $smsMessage);
            $whatsappMessage = str_replace(["[[{$key}]]", "{{{$key}}}"], $val, $whatsappMessage);
        }

        return [
            'sms_message' => $smsMessage,
            'whatsapp_message' => $whatsappMessage,
        ];
    }
}
