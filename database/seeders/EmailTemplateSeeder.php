<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Don't truncate - use updateOrCreate to handle existing templates

        $templates = [
            [
                'name' => 'OTP Verification Email',
                'identifier' => 'otp_email',
                'subject' => 'OTP Verification - {{app_name}}',
                'email_body' => '<h2>Hello {{client_name}},</h2>
                  <p>Use the following One-Time Password (OTP) to verify your email address:</p>
                  <div style="font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 3px; margin: 20px 0; text-align: center; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
                    {{otp}}
                  </div>
                  <p>This OTP is valid for <strong>{{expiry_minutes}} minutes</strong>.</p>
                  <p>If you did not request this verification, please ignore this email.</p>
                  <p style="margin-top: 30px; color: #6c757d; font-size: 14px;">
                    <strong>Security Tip:</strong> Never share your OTP with anyone. {{app_name}} will never ask for your OTP via phone or email.
                  </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'Loan Documents Email',
                'identifier' => 'loan_documents',
                'subject' => 'Your Loan Documents - {{application_number}}',
                'email_body' => '<!-- Loan Disbursed -->
                  <table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; background:#ffffff; color:#222;">
                    <tr>
                      <td align="center" style="padding:20px 12px;">
                        <table width="600" style="border-collapse:collapse;">
                          <tr>
                            <td style="padding:16px 0; text-align:center;">
                              <h2 style="color: #0b2540; margin: 0;">{{company_name}}</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; background:#0b2540; color:#ffffff; border-radius:6px;">
                              <h2 style="margin:0; font-size:18px;">Your loan has been disbursed</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:20px; background:#f8f9fb; border:1px solid #efefef;">
                              <p style="margin:0 0 12px 0; font-size:14px; color:#222;">
                                Dear <strong>{{client_name}}</strong>,
                              </p>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#333;">
                                Thank you for choosing <strong>{{company_name}}</strong>. Your <strong>{{loan_type}}</strong> has been successfully disbursed.
                              </p>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#333;">
                                Please find the attached documents for your records:
                              </p>

                              <ul style="margin:0 0 16px 20px; color:#333; font-size:14px;">
                                <li>Loan Agreement</li>
                                <li>Loan Sanction Letter</li>
                                <li>Repayment Schedule</li>
                              </ul>

                              <p style="margin:0; font-size:13px; color:#555;">
                                If you have any questions, contact us at <a href="mailto:{{support_email}}" style="color:#0b54a6;">{{support_email}}</a>.
                              </p>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; text-align:left;">
                              <p style="margin:0; font-size:14px; color:#222;">Sincerely,</p>
                              <p style="margin:6px 0 0 0; font-weight:700; color:#0b2540;">Team {{company_name}}</p>
                              <p style="margin:6px 0 0 0; font-size:12px; color:#777;">{{company_website}}</p>
                            </td>
                          </tr>

                        </table>
                      </td>
                    </tr>
                  </table>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'Loan Statement Email',
                'identifier' => 'loan_statement',
                'subject' => 'Your Loan Statement - {{application_number}}',
                'email_body' => '<p>Dear {{client_name}},</p>

                        <p>
                        Thank you for banking with {{company_name}}.  
                        Your <strong>Loan Statement</strong> for the loan account <strong>{{loan_account_no}}</strong> is attached with this email.
                        </p>

                        <p>
                        The statement contains a detailed summary of:
                        </p>

                        <ul>
                          <li>Total principal and interest</li>
                          <li>EMIs paid and pending</li>
                          <li>Penalties, if applicable</li>
                          <li>Outstanding balance</li>
                        </ul>

                        <p>
                        If you have any questions or need clarification, please contact us at  
                        <a href="mailto:{{support_email}}">{{support_email}}</a>.
                        </p>

                        <p>
                        Regards,<br>
                        <strong>Team {{company_name}}</strong>
                        </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
              'name' => 'Loan Repayment Email',
              'identifier' => 'loan_repayment',
              'subject' => 'Your Loan Repayment - {{application_number}}',
              'email_body' => '<p>Dear {{client_name}},</p>

                                 <p>
                                  Thank you for your recent loan repayment with {{company_name}}.  
                                  Your <strong>Repayment Receipt</strong> for the transaction dated 
                                  <strong>{{payment_date}}</strong> is attached with this email.
                                 </p>

                                  <p>
                                  The receipt includes details of:
                                  </p>

                                  <ul>
                                    <li>Amount paid</li>
                                    <li>Transaction reference</li>
                                    <li>Updated outstanding balance</li>
                                  </ul>

                                  <p>
                                  If you have any questions regarding this payment, feel free to reach us at  
                                  <a href="mailto:{{support_email}}">{{support_email}}</a>.
                                  </p>

                                  <p>
                                  We appreciate your timely payment and value your association with us.
                                  </p>

                                  <p>
                                  Regards,<br>
                                  <strong>Team {{company_name}}</strong>
                                  </p>',
              'image_path' => null,
              'status' => true,
            ],
            [
                'name' => 'Loan Closed Email',
                'identifier' => 'loan_closed',
                'subject' => 'Loan Account Closed - {{loan_account_no}}',
                'email_body' => '<p>Dear {{client_name}},</p>

                  <p>
                  We are pleased to inform you that your loan account <strong>{{loan_account_no}}</strong> with 
                  <strong>{{company_name}}</strong> has been successfully closed.
                  </p>

                  <p>
                  Please find the following documents attached for your reference and records:
                  </p>

                  <ul>
                    <li><strong>Loan Closure Certificate</strong></li>
                    <li><strong>No Objection Certificate (NOC)</strong></li>
                  </ul>

                  <p>
                  These documents confirm that all outstanding dues have been cleared and the loan has been fully settled.
                  </p>

                  <p>
                  If you require any further assistance, feel free to contact us at  
                  <a href="mailto:{{support_email}}">{{support_email}}</a>.
                  </p>

                  <p>
                  Thank you for choosing {{company_name}}.
                  </p>

                  <p>
                  Regards,<br>
                  <strong>Team {{company_name}}</strong>
                  </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'Loan Foreclosed Email',
                'identifier' => 'loan_foreclosed',
                'subject' => 'Loan Foreclosure Confirmation - {{loan_account_no}}',
                'email_body' => '<p>Dear {{client_name}},</p>

                  <p>
                  We would like to inform you that your loan account <strong>{{loan_account_no}}</strong> has been 
                  successfully foreclosed as per your request.
                  </p>

                  <p>
                  Please find the following documents attached for your records:
                  </p>

                  <ul>
                    <li><strong>Foreclosure Letter</strong></li>
                    <li><strong>No Objection Certificate (NOC)</strong></li>
                    <li><strong>Loan Closure Letter</strong></li>
                  </ul>

                  <p>
                  These documents confirm that all outstanding dues have been settled and the loan has been closed permanently.
                  </p>

                  <p>
                  If you require any further assistance or clarification, please contact us at  
                  <a href="mailto:{{support_email}}">{{support_email}}</a>.
                  </p>

                  <p>
                  Thank you for choosing {{company_name}}.
                  </p>

                  <p>
                  Regards,<br>
                  <strong>Team {{company_name}}</strong>
                  </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'Loan Statement Email',
                'identifier' => 'loan_statement',
                'subject' => 'Your Loan Statement - {{application_number}}',
                'email_body' => '<p>Dear {{client_name}},</p>

                  <p>
                  Please find your loan statement for account <strong>{{loan_account_no}}</strong> attached with this email.
                  </p>

                  <p>
                  The statement contains details of all your EMI payments and outstanding balance.
                  </p>

                  <p>
                  If you have any questions or need clarification, please contact us at  
                  <a href="mailto:{{support_email}}">{{support_email}}</a>.
                  </p>

                  <p>
                  Thank you for choosing {{company_name}}.
                  </p>

                  <p>
                  Regards,<br>
                  <strong>Team {{company_name}}</strong>
                  </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'Partial Payment Confirmation',
                'identifier' => 'partial_payment_confirmation',
                'subject' => 'Partial Payment Received - {{account_number}}',
                'email_body' => '<h2 style="color: #667eea; margin-bottom: 20px;">Partial Payment Confirmation</h2>
                  
                  <p>Dear <strong>{{client_name}}</strong>,</p>
                  
                  <p>Your partial payment has been successfully processed for your loan account.</p>
                  
                  <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #333; margin-top: 0;">Payment Details</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                      <tr>
                        <td style="padding: 8px 0; color: #666;">Loan Account:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{{account_number}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #666;">EMI Number:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{{emi_number}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #666;">Payment Amount:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">₹{{payment_amount}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #666;">Payment Date:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{{payment_date}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #666;">Payment Method:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{{payment_method}}</td>
                      </tr>
                    </table>
                  </div>
                  
                  <div style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
                    <h3 style="color: #856404; margin-top: 0;">EMI Status</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                      <tr>
                        <td style="padding: 5px 0; color: #856404;">Total EMI Amount:</td>
                        <td style="padding: 5px 0; font-weight: bold; color: #856404;">₹{{total_emi_amount}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 5px 0; color: #856404;">Amount Paid:</td>
                        <td style="padding: 5px 0; font-weight: bold; color: #856404;">₹{{total_paid_amount}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 5px 0; color: #856404;">Balance Remaining:</td>
                        <td style="padding: 5px 0; font-weight: bold; color: #dc3545;">₹{{balance_remaining}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 5px 0; color: #856404;">Status:</td>
                        <td style="padding: 5px 0; font-weight: bold; color: #856404;">{{emi_status}}</td>
                      </tr>
                    </table>
                  </div>
                  
                  <p style="background-color: #e7f3ff; padding: 12px; border-radius: 6px; color: #004085;">
                    <strong>Note:</strong> The remaining balance of ₹{{balance_remaining}} will be added to your next EMI installment.
                  </p>
                  
                  <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <h3 style="color: #333;">Next EMI Details</h3>
                    <p style="margin: 5px 0;">Due Date: <strong>{{next_emi_due_date}}</strong></p>
                    <p style="margin: 5px 0;">Amount: <strong>₹{{next_emi_amount}}</strong></p>
                  </div>
                  
                  <p style="margin-top: 30px;">
                    If you have any questions, please contact us at <a href="mailto:{{support_email}}" style="color: #667eea;">{{support_email}}</a>.
                  </p>
                  
                  <p>
                    Thank you for your payment!<br>
                    <strong>Team {{company_name}}</strong>
                  </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'Prepayment Confirmation',
                'identifier' => 'prepayment_confirmation',
                'subject' => 'Prepayment Processed - {{account_number}}',
                'email_body' => '<h2 style="color: #667eea; margin-bottom: 20px;">Prepayment Confirmation</h2>
                  
                  <p>Dear <strong>{{client_name}}</strong>,</p>
                  
                  <p>Your prepayment has been successfully processed. Your loan tenure has been reduced!</p>
                  
                  <div style="background-color: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
                    <h3 style="color: #155724; margin-top: 0;">Prepayment Details</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                      <tr>
                        <td style="padding: 8px 0; color: #155724;">Loan Account:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{{account_number}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #155724;">Prepayment Amount:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">₹{{prepayment_amount}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #155724;">Prepayment Charge:</td>
                        <td style="padding: 8px 0; font-weight: bold;">₹{{prepayment_charge}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #155724;">Total Paid:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">₹{{total_paid}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #155724;">Payment Date:</td>
                        <td style="padding: 8px 0; font-weight: bold;">{{payment_date}}</td>
                      </tr>
                    </table>
                  </div>
                  
                  <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #333; margin-top: 0;">Updated Loan Details</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                      <tr>
                        <td style="padding: 8px 0; color: #666;">Previous Outstanding:</td>
                        <td style="padding: 8px 0; font-weight: bold;">₹{{previous_outstanding}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #666;">New Outstanding:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">₹{{new_outstanding}}</td>
                      </tr>
                      <tr style="border-top: 1px solid #dee2e6;">
                        <td style="padding: 8px 0; color: #666; padding-top: 15px;">Previous Tenure:</td>
                        <td style="padding: 8px 0; font-weight: bold; padding-top: 15px;">{{previous_tenure}} months</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #666;">New Tenure:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">{{new_tenure}} months</td>
                      </tr>
                      <tr style="border-top: 1px solid #dee2e6;">
                        <td style="padding: 8px 0; color: #666; padding-top: 15px;">EMI Amount:</td>
                        <td style="padding: 8px 0; font-weight: bold; padding-top: 15px;">₹{{emi_amount}} <span style="color: #28a745; font-size: 12px;">(unchanged)</span></td>
                      </tr>
                    </table>
                  </div>
                  
                  <div style="background-color: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #004085; margin-top: 0;">💰 Your Savings</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                      <tr>
                        <td style="padding: 8px 0; color: #004085;">Principal Reduced:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">₹{{principal_reduced}}</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #004085;">Tenure Reduced:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">{{tenure_reduced}} months</td>
                      </tr>
                      <tr>
                        <td style="padding: 8px 0; color: #004085;">Estimated Interest Saved:</td>
                        <td style="padding: 8px 0; font-weight: bold; color: #28a745;">₹{{interest_saved}}</td>
                      </tr>
                    </table>
                  </div>
                  
                  <p style="background-color: #fff3cd; padding: 12px; border-radius: 6px; color: #856404;">
                    <strong>Important:</strong> Your EMI schedule has been regenerated. Please check your loan account for the updated repayment schedule.
                  </p>
                  
                  <p style="margin-top: 30px;">
                    If you have any questions, please contact us at <a href="mailto:{{support_email}}" style="color: #667eea;">{{support_email}}</a>.
                  </p>
                  
                  <p>
                    Thank you for your prepayment!<br>
                    <strong>Team {{company_name}}</strong>
                  </p>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'EMI Before Due Reminder',
                'identifier' => 'emi_before_due',
                'subject' => 'Reminder: EMI Payment Due Soon - {{loan_account_no}}',
                'email_body' => '<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; background:#ffffff; color:#222;">
                    <tr>
                      <td align="center" style="padding:20px 12px;">
                        <table width="600" style="border-collapse:collapse;">
                          <tr>
                            <td style="padding:16px 0;">
                              <img src="{{company_logo_url}}" alt="{{company_name}}" style="max-height:48px;">
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; background:#0b2540; color:#ffffff; border-radius:6px;">
                              <h2 style="margin:0; font-size:18px;">📅 EMI Payment Reminder</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:20px; background:#f8f9fb; border:1px solid #efefef;">
                              <p style="margin:0 0 12px 0; font-size:14px; color:#222;">
                                Dear <strong>{{client_name}}</strong>,
                              </p>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#333;">
                                This is a friendly reminder that your EMI payment is due soon for your loan account <strong>{{loan_account_no}}</strong>.
                              </p>

                              <div style="background:#e7f3ff; padding:15px; border-radius:6px; margin:15px 0;">
                                <h3 style="margin:0 0 10px 0; font-size:16px; color:#004085;">EMI Details</h3>
                                <table style="width:100%; font-size:14px;">
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">EMI Number:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{emi_number}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">EMI Amount:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#28a745;">₹{{emi_amount}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Due Date:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545;">{{due_date}}</td>
                                  </tr>
                                </table>
                              </div>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#333;">
                                Please ensure timely payment to avoid any late payment charges.
                              </p>

                              <p style="margin:0; font-size:13px; color:#555;">
                                For any queries, contact us at <a href="mailto:{{support_email}}" style="color:#0b54a6;">{{support_email}}</a> or call {{support_mobile}}.
                              </p>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; text-align:left;">
                              <p style="margin:0; font-size:14px; color:#222;">Best Regards,</p>
                              <p style="margin:6px 0 0 0; font-weight:700; color:#0b2540;">Team {{company_name}}</p>
                            </td>
                          </tr>

                        </table>
                      </td>
                    </tr>
                  </table>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'EMI Due Today Reminder',
                'identifier' => 'emi_due_today',
                'subject' => 'URGENT: EMI Payment Due Today - {{loan_account_no}}',
                'email_body' => '<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; background:#ffffff; color:#222;">
                    <tr>
                      <td align="center" style="padding:20px 12px;">
                        <table width="600" style="border-collapse:collapse;">
                          <tr>
                            <td style="padding:16px 0;">
                              <img src="{{company_logo_url}}" alt="{{company_name}}" style="max-height:48px;">
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; background:#dc3545; color:#ffffff; border-radius:6px;">
                              <h2 style="margin:0; font-size:18px;">⚠️ EMI Payment Due TODAY</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:20px; background:#fff3cd; border:1px solid #ffc107;">
                              <p style="margin:0 0 12px 0; font-size:14px; color:#222;">
                                Dear <strong>{{client_name}}</strong>,
                              </p>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#856404; font-weight:bold;">
                                Your EMI payment is due TODAY for loan account <strong>{{loan_account_no}}</strong>.
                              </p>

                              <div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:2px solid #dc3545;">
                                <h3 style="margin:0 0 10px 0; font-size:16px; color:#dc3545;">Payment Details</h3>
                                <table style="width:100%; font-size:14px;">
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">EMI Number:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{emi_number}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Amount Due:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545; font-size:16px;">₹{{emi_amount}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Due Date:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{due_date}} (TODAY)</td>
                                  </tr>
                                </table>
                              </div>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#856404;">
                                <strong>Action Required:</strong> Please make the payment immediately to avoid late payment penalties.
                              </p>

                              <p style="margin:0; font-size:13px; color:#555;">
                                For immediate assistance, contact us at <a href="mailto:{{support_email}}" style="color:#dc3545;">{{support_email}}</a> or call {{support_mobile}}.
                              </p>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; text-align:left;">
                              <p style="margin:0; font-size:14px; color:#222;">Best Regards,</p>
                              <p style="margin:6px 0 0 0; font-weight:700; color:#0b2540;">Team {{company_name}}</p>
                            </td>
                          </tr>

                        </table>
                      </td>
                    </tr>
                  </table>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'EMI Overdue Notice',
                'identifier' => 'emi_overdue',
                'subject' => 'OVERDUE: EMI Payment Pending - {{loan_account_no}}',
                'email_body' => '<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; background:#ffffff; color:#222;">
                    <tr>
                      <td align="center" style="padding:20px 12px;">
                        <table width="600" style="border-collapse:collapse;">
                          <tr>
                            <td style="padding:16px 0;">
                              <img src="{{company_logo_url}}" alt="{{company_name}}" style="max-height:48px;">
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; background:#dc3545; color:#ffffff; border-radius:6px;">
                              <h2 style="margin:0; font-size:18px;">🚨 EMI Payment OVERDUE</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:20px; background:#f8d7da; border:2px solid #dc3545;">
                              <p style="margin:0 0 12px 0; font-size:14px; color:#222;">
                                Dear <strong>{{client_name}}</strong>,
                              </p>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#721c24; font-weight:bold;">
                                Your EMI payment for loan account <strong>{{loan_account_no}}</strong> is now OVERDUE.
                              </p>

                              <div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
                                <h3 style="margin:0 0 10px 0; font-size:16px; color:#dc3545;">Overdue Payment Details</h3>
                                <table style="width:100%; font-size:14px;">
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">EMI Number:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{emi_number}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">EMI Amount:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545; font-size:16px;">₹{{emi_amount}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Due Date:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{due_date}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Days Overdue:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545;">{{dpd}} days</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Penalty Charges:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545;">₹{{penalty_amount}}</td>
                                  </tr>
                                  <tr style="border-top:2px solid #dc3545;">
                                    <td style="padding:8px 0; color:#000; font-weight:bold;">Total Amount Due:</td>
                                    <td style="padding:8px 0; font-weight:bold; color:#dc3545; font-size:18px;">₹{{pending_amount}}</td>
                                  </tr>
                                </table>
                              </div>

                              <div style="background:#fff3cd; padding:12px; border-radius:6px; border-left:4px solid #ffc107; margin:15px 0;">
                                <p style="margin:0; font-size:13px; color:#856404;">
                                  <strong>⚠️ Important Notice:</strong> Continued non-payment may affect your credit score and may result in additional penalties.
                                </p>
                              </div>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#721c24; font-weight:bold;">
                                Please make the payment immediately to avoid further penalties and legal action.
                              </p>

                              <p style="margin:0; font-size:13px; color:#555;">
                                For payment assistance, contact us urgently at <a href="mailto:{{support_email}}" style="color:#dc3545;">{{support_email}}</a> or call {{support_mobile}}.
                              </p>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; text-align:left;">
                              <p style="margin:0; font-size:14px; color:#222;">Regards,</p>
                              <p style="margin:6px 0 0 0; font-weight:700; color:#0b2540;">Team {{company_name}}</p>
                            </td>
                          </tr>

                        </table>
                      </td>
                    </tr>
                  </table>',
                'image_path' => null,
                'status' => true,
            ],
            [
                'name' => 'EMI Urgent Overdue Notice',
                'identifier' => 'emi_urgent_overdue',
                'subject' => 'FINAL NOTICE: Urgent EMI Payment Required - {{loan_account_no}}',
                'email_body' => '<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; background:#ffffff; color:#222;">
                    <tr>
                      <td align="center" style="padding:20px 12px;">
                        <table width="600" style="border-collapse:collapse; border:3px solid #dc3545;">
                          <tr>
                            <td style="padding:16px 0; text-align:center; background:#dc3545;">
                              <h2 style="color: #ffffff; margin: 0;">{{company_name}}</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; background:#721c24; color:#ffffff; text-align:center;">
                              <h2 style="margin:0; font-size:20px;">🚨 FINAL NOTICE - URGENT ACTION REQUIRED 🚨</h2>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:20px; background:#f8d7da;">
                              <p style="margin:0 0 12px 0; font-size:14px; color:#222;">
                                Dear <strong>{{client_name}}</strong>,
                              </p>

                              <div style="background:#721c24; color:#ffffff; padding:15px; border-radius:6px; margin:15px 0; text-align:center;">
                                <p style="margin:0; font-size:16px; font-weight:bold;">
                                  FINAL NOTICE: Your EMI payment is seriously overdue!
                                </p>
                              </div>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#721c24; font-weight:bold;">
                                Despite multiple reminders, your EMI payment for loan account <strong>{{loan_account_no}}</strong> remains unpaid.
                              </p>

                              <div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:3px solid #dc3545;">
                                <h3 style="margin:0 0 10px 0; font-size:16px; color:#dc3545; text-align:center;">CRITICAL PAYMENT DETAILS</h3>
                                <table style="width:100%; font-size:14px;">
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">EMI Number:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{emi_number}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Original EMI Amount:</td>
                                    <td style="padding:5px 0; font-weight:bold;">₹{{emi_amount}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Due Date:</td>
                                    <td style="padding:5px 0; font-weight:bold;">{{due_date}}</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Days Overdue:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545; font-size:16px;">{{dpd}} DAYS</td>
                                  </tr>
                                  <tr>
                                    <td style="padding:5px 0; color:#666;">Accumulated Penalties:</td>
                                    <td style="padding:5px 0; font-weight:bold; color:#dc3545;">₹{{penalty_amount}}</td>
                                  </tr>
                                  <tr style="border-top:3px solid #dc3545; background:#fff3cd;">
                                    <td style="padding:10px 0; color:#000; font-weight:bold; font-size:15px;">TOTAL AMOUNT DUE NOW:</td>
                                    <td style="padding:10px 0; font-weight:bold; color:#dc3545; font-size:20px;">₹{{pending_amount}}</td>
                                  </tr>
                                </table>
                              </div>

                              <div style="background:#fff3cd; padding:15px; border-radius:6px; border:2px solid #ffc107; margin:15px 0;">
                                <h4 style="margin:0 0 8px 0; color:#856404;">⚠️ CONSEQUENCES OF NON-PAYMENT:</h4>
                                <ul style="margin:5px 0; padding-left:20px; color:#856404; font-size:13px;">
                                  <li>Severe impact on your credit score (CIBIL)</li>
                                  <li>Additional late payment penalties</li>
                                  <li>Legal action and recovery proceedings</li>
                                  <li>Loan account may be classified as NPA (Non-Performing Asset)</li>
                                </ul>
                              </div>

                              <div style="background:#dc3545; color:#ffffff; padding:15px; border-radius:6px; margin:15px 0; text-align:center;">
                                <p style="margin:0; font-size:15px; font-weight:bold;">
                                  ⏰ PAY IMMEDIATELY TO AVOID LEGAL ACTION ⏰
                                </p>
                              </div>

                              <p style="margin:0 0 12px 0; font-size:14px; color:#721c24; font-weight:bold;">
                                If payment is not received within 48 hours, we will be forced to initiate recovery proceedings.
                              </p>

                              <p style="margin:0; font-size:13px; color:#555;">
                                <strong>Contact us IMMEDIATELY:</strong><br>
                                Email: <a href="mailto:{{support_email}}" style="color:#dc3545; font-weight:bold;">{{support_email}}</a><br>
                                Phone: <strong>{{support_mobile}}</strong>
                              </p>
                            </td>
                          </tr>

                          <tr>
                            <td style="padding:18px; text-align:left; background:#f8f9fa;">
                              <p style="margin:0; font-size:14px; color:#222;">Regards,</p>
                              <p style="margin:6px 0 0 0; font-weight:700; color:#0b2540;">Collections Department<br>{{company_name}}</p>
                            </td>
                          </tr>

                        </table>
                      </td>
                    </tr>
                  </table>',
                'image_path' => null,
                'status' => true,
            ]
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['identifier' => $template['identifier']], // Match on identifier
                $template // Update with all template data
            );
        }

        $this->command->info('Email templates seeded successfully!');
    }
}
