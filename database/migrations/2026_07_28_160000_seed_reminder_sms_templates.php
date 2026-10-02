<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SmsTemplate;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'identifier' => 'chit_overdue_reminder_sms',
                'name' => 'Chit Overdue Reminder SMS',
                'sms_body' => "Dear [[client_name]], your Chit installment for Group [[group_name]] ([[period_label]]) of Rs.[[amount_due]] was due on [[due_date]]. Please pay at your earliest convenience to avoid additional penalty charges.\n\nPlease check your Chit Schedule here: [[public_link]]\n\nThank you, [[company_slogan]].",
                'template_id' => 'DLT_CHIT_OVERDUE_SMS_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'chit_overdue_reminder_whatsapp',
                'name' => 'Chit Overdue Reminder WhatsApp',
                'sms_body' => "Dear *[[client_name]]*, your Chit installment for *Group [[group_name]]* ([[period_label]]) of *Rs.[[amount_due]]* was due on *[[due_date]]*. Please pay at your earliest convenience to avoid additional penalty charges.\n\nPlease check your Chit Schedule here: [[public_link]]\n\nThank you, [[company_slogan]]. For queries: [[company_phone]].",
                'template_id' => 'DLT_CHIT_OVERDUE_WA_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'chit_pending_reminder_sms',
                'name' => 'Chit Pending Reminder SMS',
                'sms_body' => "Dear [[client_name]], your upcoming Chit installment for Group [[group_name]] ([[period_label]]) of Rs.[[amount_due]] is due on [[due_date]]. Kindly arrange payment on or before the due date.\n\nPlease check your Chit Schedule here: [[public_link]]\n\nThank you, [[company_slogan]].",
                'template_id' => 'DLT_CHIT_PENDING_SMS_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'chit_pending_reminder_whatsapp',
                'name' => 'Chit Pending Reminder WhatsApp',
                'sms_body' => "Dear *[[client_name]]*, your upcoming Chit installment for *Group [[group_name]]* ([[period_label]]) of *Rs.[[amount_due]]* is due on *[[due_date]]*. Kindly arrange payment on or before the due date.\n\nPlease check your Chit Schedule here: [[public_link]]\n\nThank you, [[company_slogan]]. For queries: [[company_phone]].",
                'template_id' => 'DLT_CHIT_PENDING_WA_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'loan_overdue_reminder_sms',
                'name' => 'Loan Overdue Reminder SMS',
                'sms_body' => "Dear [[client_name]], your EMI payment of Rs.[[amount_due]] for Loan Account No: [[account_no]] ([[period_label]]) was due on [[due_date]]. Please clear your overdue EMI at the earliest to avoid penalty.\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you, [[company_slogan]].",
                'template_id' => 'DLT_LOAN_OVERDUE_SMS_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'loan_overdue_reminder_whatsapp',
                'name' => 'Loan Overdue Reminder WhatsApp',
                'sms_body' => "Dear *[[client_name]]*, your EMI payment of *Rs.[[amount_due]]* for *Loan Account No: [[account_no]]* ([[period_label]]) was due on *[[due_date]]*. Please clear your overdue EMI at the earliest to avoid penalty and credit impact.\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you, [[company_slogan]]. For queries: [[company_phone]].",
                'template_id' => 'DLT_LOAN_OVERDUE_WA_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'loan_pending_reminder_sms',
                'name' => 'Loan Pending Reminder SMS',
                'sms_body' => "Dear [[client_name]], your upcoming EMI payment of Rs.[[amount_due]] for Loan Account No: [[account_no]] ([[period_label]]) is due on [[due_date]]. Kindly arrange payment on or before the due date.\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you, [[company_slogan]].",
                'template_id' => 'DLT_LOAN_PENDING_SMS_ID',
                'status' => 1,
            ],
            [
                'identifier' => 'loan_pending_reminder_whatsapp',
                'name' => 'Loan Pending Reminder WhatsApp',
                'sms_body' => "Dear *[[client_name]]*, your upcoming EMI payment of *Rs.[[amount_due]]* for *Loan Account No: [[account_no]]* ([[period_label]]) is due on *[[due_date]]*. Kindly arrange payment on or before the due date.\n\nPlease check your EMI Schedule here: [[public_link]]\n\nThank you, [[company_slogan]]. For queries: [[company_phone]].",
                'template_id' => 'DLT_LOAN_PENDING_WA_ID',
                'status' => 1,
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::updateOrCreate(
                ['identifier' => $template['identifier']],
                $template
            );
        }
    }

    public function down(): void
    {
        SmsTemplate::whereIn('identifier', [
            'chit_overdue_reminder_sms',
            'chit_overdue_reminder_whatsapp',
            'chit_pending_reminder_sms',
            'chit_pending_reminder_whatsapp',
            'loan_overdue_reminder_sms',
            'loan_overdue_reminder_whatsapp',
            'loan_pending_reminder_sms',
            'loan_pending_reminder_whatsapp',
        ])->delete();
    }
};
