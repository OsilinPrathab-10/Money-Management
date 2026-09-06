<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\DB;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'identifier' => 'otp',
                'name' => 'OTP Verification',
                'sms_body' => 'Your OTP for [[site_name]] is [[code]]. Please do not share this with anyone.',
                'template_id' => 'DLT_OTP_ID',
                'status' => 1
            ],
            [
                'identifier' => 'loan_submitted',
                'name' => 'Loan Submitted Notification',
                'sms_body' => 'Dear [[name]], your loan application #[[app_no]] for INR [[amount]] has been submitted to [[site_name]]. We will review it shortly.',
                'template_id' => 'DLT_LOAN_SUBMIT_ID',
                'status' => 1
            ],
            [
                'identifier' => 'loan_approved',
                'name' => 'Loan Approved Notification',
                'sms_body' => 'Congratulations [[name]]! Your loan application #[[app_no]] for INR [[amount]] has been APPROVED. Our team will contact you for disbursement.',
                'template_id' => 'DLT_LOAN_APPROVE_ID',
                'status' => 1
            ],
            [
                'identifier' => 'loan_activated',
                'name' => 'Loan Activated Notification',
                'sms_body' => 'Dear [[name]], your loan account #[[acc_no]] is now ACTIVE. Disbursed Amount: INR [[disbursed_amount]]. Repay timely to maintain score.',
                'template_id' => 'DLT_LOAN_ACTIVATE_ID',
                'status' => 1
            ],
            [
                'identifier' => 'emi_reminder',
                'name' => 'EMI Reminder Notification',
                'sms_body' => 'Reminder: Your EMI of INR [[amount]] for loan account #[[acc_no]] is due on [[date]]. Please pay by due date to avoid penalty.',
                'template_id' => 'DLT_EMI_REMIND_ID',
                'status' => 1
            ],
            [
                'identifier' => 'payment_received_sms',
                'name' => 'Payment Received SMS (Redirect)',
                'sms_body' => "Dear [[client_name]],\nYour [[payment_type]] payment of ₹[[amount_paid]] towards Shanmuga Finance Loan Account [[account_no]] has been received successfully.\nOutstanding Balance: ₹[[remaining_balance]].\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you!",
                'template_id' => 'DLT_PAY_RECV_SMS_ID',
                'status' => 1
            ],
            [
                'identifier' => 'payment_received_whatsapp',
                'name' => 'Payment Received WhatsApp (Redirect)',
                'sms_body' => "Dear [[client_name]],\nYour [[payment_type]] payment of ₹[[amount_paid]] towards Shanmuga Finance Loan Account [[account_no]] has been received successfully.\nOutstanding Balance: ₹[[remaining_balance]].\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you!",
                'template_id' => 'DLT_PAY_RECV_WA_ID',
                'status' => 1
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::updateOrCreate(
                ['identifier' => $template['identifier']],
                [
                    'name' => $template['name'],
                    'sms_body' => $template['sms_body'],
                    'template_id' => $template['template_id'],
                    'status' => $template['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
