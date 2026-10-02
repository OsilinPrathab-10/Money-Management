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
            ['identifier' => 'chit_payment_received_sms'],
            [
                'name' => 'Chit Payment Received SMS (Redirect)',
                'sms_body' => "Dear [[client_name]],\nYour Chit Payment of ₹[[amount_paid]] towards Chit Group [[group_name]] (Month [[month_number]]) has been received successfully.\nRemaining Balance: ₹[[remaining_balance]].\n\nThank you!",
                'template_id' => 'DLT_CHIT_PAY_RECV_SMS_ID',
                'status' => true
            ]
        );

        SmsTemplate::updateOrCreate(
            ['identifier' => 'chit_payment_received_whatsapp'],
            [
                'name' => 'Chit Payment Received WhatsApp (Redirect)',
                'sms_body' => "Dear [[client_name]],\nYour Chit Payment of ₹[[amount_paid]] towards Chit Group [[group_name]] (Month [[month_number]]) has been received successfully.\nRemaining Balance: ₹[[remaining_balance]].\n\nThank you!",
                'template_id' => 'DLT_CHIT_PAY_RECV_WA_ID',
                'status' => true
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SmsTemplate::whereIn('identifier', ['chit_payment_received_sms', 'chit_payment_received_whatsapp'])->delete();
    }
};
