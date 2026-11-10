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

        $companySlogan = \App\Models\CompanyDetail::first()->company_slogan ?? \App\Models\CompanyDetail::first()->company_name ?? 'Codepluse Gen PVT Ltd';

        // 1. Build Fallback Messages (the previous templates)
        $fallbackSms = '';
        if ($isKandhuvatti) {
            if ($paymentType === 'principal') {
                $fallbackSms = "Dear {$clientName},\nYour Principal payment of ₹{$amountPaid} towards {$companySlogan} Open Loan Account {$accountNo} has been received successfully.\nRemaining Principal Balance: ₹{$remainingBalance}.\nThank you!";
            } else {
                if ($isPartial) {
                    $fallbackSms = "Dear {$clientName},\nYour Partial Interest payment of ₹{$amountPaid} towards {$companySlogan} Open Loan Account {$accountNo} has been received successfully.\nBalance Interest to pay: ₹{$emiBalance}.\nRemaining Principal Balance: ₹{$remainingBalance}.\nThank you!";
                } else {
                    $fallbackSms = "Dear {$clientName},\nYour Interest payment of ₹{$amountPaid} towards {$companySlogan} Open Loan Account {$accountNo} has been received successfully.\nRemaining Principal Balance: ₹{$remainingBalance}.\nThank you!";
                }
            }
        } else {
            if ($isPartial) {
                $fallbackSms = "Dear {$clientName},\nYour Partial EMI payment of ₹{$amountPaid} towards {$companySlogan} Loan Account {$accountNo} has been received successfully.\nBalance EMI to pay: ₹{$emiBalance}.\nOutstanding Balance: ₹{$remainingBalance}.\nThank you!";
            } else {
                $fallbackSms = "Dear {$clientName},\nYour EMI payment of ₹{$amountPaid} towards {$companySlogan} Loan Account {$accountNo} has been received successfully.\nOutstanding Balance: ₹{$remainingBalance}.\nThank you!";
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
            'company_slogan' => $companySlogan,
            'company_name' => \App\Models\CompanyDetail::first()->company_name ?? 'Codepluse Gen PVT Ltd',
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
            'company_slogan' => $companySlogan,
        ];
    }

    /**
     * Parse notification templates for Loan Top-Up SMS and WhatsApp redirects
     */
    public static function getTopupMessages(array $data): array
    {
        $clientName = $data['client_name'] ?? 'Client';
        $mobileNo = $data['mobile_no'] ?? '';
        $accountNo = $data['account_no'] ?? '';
        $topupAmount = number_format(floatval($data['topup_amount'] ?? 0), 2, '.', ',');
        $newEmiAmount = number_format(floatval($data['new_emi_amount'] ?? 0), 2, '.', ',');
        $nextDueDate = !empty($data['next_due_date']) ? \Carbon\Carbon::parse($data['next_due_date'])->format('d-m-Y') : 'Upcoming Cycle';
        $remainingBalance = number_format(floatval($data['remaining_balance'] ?? 0), 2, '.', ',');
        $frequency = ucfirst($data['frequency'] ?? 'Monthly');

        $publicLink = '';
        if (!empty($data['application_number'])) {
            $publicToken = base64_encode($data['application_number']);
            $publicLink = url("/view-schedule/{$publicToken}");
        }

        $companySlogan = \App\Models\CompanyDetail::first()->company_slogan ?? \App\Models\CompanyDetail::first()->company_name ?? 'Codepluse Gen PVT Ltd';

        $fallbackSms = "Dear {$clientName},\nYour Loan Account {$accountNo} has been topped up with ₹{$topupAmount} successfully.\nRevised {$frequency} EMI: ₹{$newEmiAmount}\nNext EMI Due Date: {$nextDueDate}\nOutstanding Balance: ₹{$remainingBalance}.\nThank you!\n- {$companySlogan}";

        $fallbackWa = $fallbackSms;
        if (!empty($publicLink)) {
            $fallbackWa .= "\n\nPlease view your revised schedule here: {$publicLink}";
        }

        $smsTemplate = SmsTemplate::where('identifier', 'loan_topup_sms')->where('status', true)->first();
        $whatsappTemplate = SmsTemplate::where('identifier', 'loan_topup_whatsapp')->where('status', true)->first();

        $variables = [
            'client_name' => $clientName,
            'account_no' => $accountNo,
            'topup_amount' => $topupAmount,
            'new_emi_amount' => $newEmiAmount,
            'next_due_date' => $nextDueDate,
            'remaining_balance' => $remainingBalance,
            'frequency' => $frequency,
            'public_link' => $publicLink,
            'company_slogan' => $companySlogan,
            'company_name' => \App\Models\CompanyDetail::first()->company_name ?? 'Codepluse Gen PVT Ltd',
        ];

        $smsMessage = $smsTemplate ? $smsTemplate->sms_body : $fallbackSms;
        $whatsappMessage = $whatsappTemplate ? $whatsappTemplate->sms_body : $fallbackWa;

        foreach ($variables as $key => $val) {
            $smsMessage = str_replace(["[[{$key}]]", "{{{$key}}}"], $val, $smsMessage);
            $whatsappMessage = str_replace(["[[{$key}]]", "{{{$key}}}"], $val, $whatsappMessage);
        }

        $whatsappLink = '';
        if (!empty($mobileNo)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $mobileNo);
            if (strlen($cleanPhone) === 10) {
                $cleanPhone = '91' . $cleanPhone;
            }
            $whatsappLink = 'https://api.whatsapp.com/send?phone=' . $cleanPhone . '&text=' . urlencode($whatsappMessage);
        }

        return [
            'sms_message' => $smsMessage,
            'whatsapp_message' => $whatsappMessage,
            'whatsapp_link' => $whatsappLink,
            'company_slogan' => $companySlogan,
        ];
    }


    /**
     * Parse notification templates for Loan Overdue Reminder SMS and WhatsApp redirects
     */
    public static function getLoanOverdueReminderMessages(array $data): array
    {
        $clientName = $data['client_name'] ?? 'Client';
        $accountNo = $data['account_no'] ?? '';
        $periodLabel = $data['period_label'] ?? ('EMI ' . ($data['emi_number'] ?? ''));
        $amountDueVal = floatval($data['amount_due'] ?? $data['balance'] ?? 0);
        $amountDue = number_format($amountDueVal, 2, '.', ',');
        $dueDate = $data['due_date'] ?? '';

        $companyName = \App\Models\CompanyDetail::first()->company_name ?? 'Codepluse Gen';
        $companySlogan = \App\Models\CompanyDetail::first()->company_slogan ?? $companyName;
        $companyPhone = \App\Models\CompanyDetail::first()->company_mobile ?? '';

        $publicLink = $data['public_link'] ?? '';

        $fallbackSms = "Dear {$clientName}, your EMI payment of Rs.{$amountDue} for Loan Account No: {$accountNo} ({$periodLabel}) was due on {$dueDate}. Please clear your overdue EMI at the earliest to avoid penalty." . (!empty($publicLink) ? "\n\nPlease check your EMI Schedule here: {$publicLink}" : '') . "\n\nThank you, {$companySlogan}.";
        $fallbackWa = "Dear *{$clientName}*, your EMI payment of *Rs.{$amountDue}* for *Loan Account No: {$accountNo}* ({$periodLabel}) was due on *{$dueDate}*. Please clear your overdue EMI at the earliest to avoid penalty and credit score impact." . (!empty($publicLink) ? "\n\nPlease check your EMI Schedule here: {$publicLink}" : '') . "\n\nThank you, {$companySlogan}. For queries: {$companyPhone}.";

        $smsTemplate = SmsTemplate::where('identifier', 'loan_overdue_reminder_sms')->where('status', true)->first();
        $whatsappTemplate = SmsTemplate::where('identifier', 'loan_overdue_reminder_whatsapp')->where('status', true)->first();

        $variables = [
            'client_name' => $clientName,
            'amount_due' => $amountDue,
            'account_no' => $accountNo,
            'period_label' => $periodLabel,
            'due_date' => $dueDate,
            'public_link' => $publicLink,
            'company_slogan' => $companySlogan,
            'company_name' => $companyName,
            'company_phone' => $companyPhone,
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
            'company_slogan' => $companySlogan,
            'public_link' => $publicLink,
        ];
    }

    /**
     * Parse notification templates for Loan Pending Reminder SMS and WhatsApp redirects
     */
    public static function getLoanPendingReminderMessages(array $data): array
    {
        $clientName = $data['client_name'] ?? 'Client';
        $accountNo = $data['account_no'] ?? '';
        $periodLabel = $data['period_label'] ?? ('EMI ' . ($data['emi_number'] ?? ''));
        $amountDueVal = floatval($data['amount_due'] ?? $data['balance'] ?? 0);
        $amountDue = number_format($amountDueVal, 2, '.', ',');
        $dueDate = $data['due_date'] ?? '';

        $companyName = \App\Models\CompanyDetail::first()->company_name ?? 'Codepluse Gen';
        $companySlogan = \App\Models\CompanyDetail::first()->company_slogan ?? $companyName;
        $companyPhone = \App\Models\CompanyDetail::first()->company_mobile ?? '';

        $publicLink = $data['public_link'] ?? '';

        $fallbackSms = "Dear {$clientName}, your upcoming EMI payment of Rs.{$amountDue} for Loan Account No: {$accountNo} ({$periodLabel}) is due on {$dueDate}. Kindly arrange payment on or before the due date." . (!empty($publicLink) ? "\n\nPlease check your EMI Schedule here: {$publicLink}" : '') . "\n\nThank you, {$companySlogan}.";
        $fallbackWa = "Dear *{$clientName}*, your upcoming EMI payment of *Rs.{$amountDue}* for *Loan Account No: {$accountNo}* ({$periodLabel}) is due on *{$dueDate}*. Kindly arrange payment on or before the due date." . (!empty($publicLink) ? "\n\nPlease check your EMI Schedule here: {$publicLink}" : '') . "\n\nThank you, {$companySlogan}. For queries: {$companyPhone}.";

        $smsTemplate = SmsTemplate::where('identifier', 'loan_pending_reminder_sms')->where('status', true)->first();
        $whatsappTemplate = SmsTemplate::where('identifier', 'loan_pending_reminder_whatsapp')->where('status', true)->first();

        $variables = [
            'client_name' => $clientName,
            'amount_due' => $amountDue,
            'account_no' => $accountNo,
            'period_label' => $periodLabel,
            'due_date' => $dueDate,
            'public_link' => $publicLink,
            'company_slogan' => $companySlogan,
            'company_name' => $companyName,
            'company_phone' => $companyPhone,
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
            'company_slogan' => $companySlogan,
            'public_link' => $publicLink,
        ];
    }
}
