<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SmsTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        SmsTemplate::updateOrCreate(
            ['identifier' => 'payment_received_sms'],
            [
                'name' => 'Payment Received SMS (Redirect)',
                'sms_body' => "Dear [[client_name]],\nYour [[payment_type]] payment of ₹[[amount_paid]] towards Shanmuga Finance Loan Account [[account_no]] has been received successfully.\nOutstanding Balance: ₹[[remaining_balance]].\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you!",
                'template_id' => 'DLT_PAY_RECV_SMS_ID',
                'status' => true
            ]
        );

        SmsTemplate::updateOrCreate(
            ['identifier' => 'payment_received_whatsapp'],
            [
                'name' => 'Payment Received WhatsApp (Redirect)',
                'sms_body' => "Dear [[client_name]],\nYour [[payment_type]] payment of ₹[[amount_paid]] towards Shanmuga Finance Loan Account [[account_no]] has been received successfully.\nOutstanding Balance: ₹[[remaining_balance]].\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you!",
                'template_id' => 'DLT_PAY_RECV_WA_ID',
                'status' => true
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SmsTemplate::whereIn('identifier', ['payment_received_sms', 'payment_received_whatsapp'])->delete();
    }
};
