<?php

namespace App\Services\CardCash;

use App\Models\CardCash\CardCashActivityLog;
use App\Models\CardCash\CardCashBillPayment;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashReturn;
use App\Services\GallaboxService;
use Illuminate\Support\Facades\Log;

class CardCashNotificationService
{
    /**
     * Build WhatsApp message text
     */
    public function buildWhatsAppMessage(CardCashLead $lead, ?CardCashBillPayment $payment = null, ?CardCashReturn $return = null): string
    {
        $customer = $lead->customer;
        $customerName = $customer ? $customer->customer_name : 'Customer';
        $leadNumber = $lead->lead_number;
        $display = $lead->settlementDisplay($return);
        $cardLine = "{$lead->card_name} ({$lead->csr_bank_name})";
        if ($lead->card_number) {
            $cardLine .= " [{$lead->masked_card_number}]";
        }

        $lines = [
            "Hello *{$customerName}*,",
            "",
            "Your Card to Cash request has been updated:",
            "📋 *Lead Ref:* {$leadNumber}",
            "",
            "*Settlement Details*",
            "• *Name:* {$display['name']}",
            "• *Bank Name:* {$display['bank_name']}",
            "• *Card Number:* {$display['card_number']}",
            "• *Due Date:* {$display['due_date']}",
            "• *Amount:* {$display['amount']}",
            "• *Settlement Details:* {$display['settlement_details']}",
            "",
            "💳 *Card:* {$cardLine}",
            "📌 *Type:* " . ($lead->transaction_type === 'bill_payment' ? 'Credit Card Bill Payment' : 'Card Swipe'),
            "⚡ *Status:* " . $lead->status_label,
        ];

        if ($payment) {
            $lines[] = "";
            $lines[] = "✅ *Payment Details:*";
            $lines[] = "• Amount Paid: ₹" . number_format($payment->amount, 2);
            if ($payment->transaction_reference) {
                $lines[] = "• UTR/Ref: {$payment->transaction_reference}";
            }
            $lines[] = "• Date: " . $payment->payment_date->format('d M Y, h:i A');
        }

        if ($return) {
            $lines[] = "";
            if ($return->payment_reference) {
                $lines[] = "• Reference / UTR: {$return->payment_reference}";
            }
        }

        $lines[] = "";
        $lines[] = "Thank you for choosing our services!";

        return implode("\n", $lines);
    }

    /**
     * Generate direct wa.me click-to-chat URL
     */
    public function generateWhatsAppLink(string $phone, string $message): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91' . $cleanPhone;
        }

        return 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($message);
    }

    /**
     * Trigger WhatsApp notification via API if available and log
     */
    public function notifyLead(CardCashLead $lead, ?int $userId = null): array
    {
        $message = $this->buildWhatsAppMessage($lead, $lead->billPayment, $lead->returnSettlement);
        $link = $this->generateWhatsAppLink($lead->phone_number, $message);

        // Attempt automated API delivery via Gallabox if configured
        $apiSent = false;
        try {
            $gallabox = app(GallaboxService::class);
            if (method_exists($gallabox, 'sendTextMessage')) {
                $recipientName = optional($lead->customer)->customer_name ?? 'Customer';
                $res = $gallabox->sendTextMessage($lead->phone_number, $recipientName, $message);
                $apiSent = !empty($res['success']);
            }
        } catch (\Throwable $e) {
            Log::info("Card to Cash WhatsApp automated API skipped: " . $e->getMessage());
        }

        // Log to activity timeline
        CardCashActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => $userId,
            'action' => 'whatsapp_sent',
            'old_status' => $lead->status,
            'new_status' => $lead->status,
            'description' => "Customer WhatsApp notification generated" . ($apiSent ? " and sent via API" : ""),
            'metadata' => [
                'phone' => $lead->phone_number,
                'link' => $link,
                'api_sent' => $apiSent,
            ],
        ]);

        return [
            'message' => $message,
            'whatsapp_link' => $link,
            'api_sent' => $apiSent,
        ];
    }
}
