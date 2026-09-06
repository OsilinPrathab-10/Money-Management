 <?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LoanDocumentTemplate;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'type' => 'repayment_schedule',
                'title' => 'Repayment Schedule',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Schedule generated on {{current_date}}. Please contact support for clarifications.</p>',
                'body' => <<<'HTML'
                    <div style="font-family: Arial, sans-serif; padding: 20px; color: #000;">

                    <table width="100%" border="1" cellspacing="0" cellpadding="8" style="border-collapse: collapse; font-size: 14px; margin-bottom: 20px;">
                        <tr>
                            <td width="30%" style="background-color: #f5f5f5;"><strong>Customer Name</strong></td>
                            <td width="70%">{{client_name}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Agreement No.</strong></td>
                            <td>{{agreement_no}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Agreement Date</strong></td>
                            <td>{{agreement_date}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Frequency</strong></td>
                            <td>{{emi_frequency}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Amount Financed</strong></td>
                            <td>{{amount_financed}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Interest Rate</strong></td>
                            <td>{{interest_rate}}%</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Tenure (Months)</strong></td>
                            <td>{{tenure_months}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Total Installments</strong></td>
                            <td>{{total_installments}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Net Disbursed Amount</strong></td>
                            <td>{{net_disbursed_amount}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #f5f5f5;"><strong>Product</strong></td>
                            <td>{{product_name}}</td>
                        </tr>
                    </table>

                    <p style="font-size: 13px; margin-top: 10px;">
                        <i>*Interest rate is used to calculate the monthly repayment amount.</i>
                    </p>

                    <!-- REPAYMENT TABLE -->
                    <h3 style="margin-top: 25px;">Installment Schedule</h3>

                    <table width="100%" border="1" cellspacing="0" cellpadding="6" style="border-collapse: collapse; font-size: 13px; text-align: center;">
                        <thead style="background:#eaeaea; font-weight: bold;">
                            <tr>
                                <td>S No.</td>
                                <td>Due Date</td>
                                <td>Principal</td>
                                <td>Interest</td>
                                <td>Total EMI</td>
                            </tr>
                        </thead>

                        <tbody>
                            {{#repayments}}
                            <tr>
                                <td>{{sl_no}}</td>
                                <td>{{due_date}}</td>
                                <td>{{principal}}</td>
                                <td>{{interest}}</td>
                                <td>{{installment_amount}}</td>
                            </tr>
                            {{/repayments}}
                        </tbody>

                        <tfoot style="background:#f5f5f5;">
                            <tr>
                                <td colspan="2"><strong>Total</strong></td>
                                <td><strong>{{total_principal}}</strong></td>
                                <td><strong>{{total_interest}}</strong></td>
                                <td><strong>{{total_installment_amount}}</strong></td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- FOOTER -->
                    <p style="margin-top: 20px; font-size: 12px;">
                        <strong>Generated On:</strong> {{generated_on}}<br>
                        <strong>*** END OF REPORT ***</strong><br><br>
                        This is a computer-generated report and does not require signature.
                    </p>
                    </div>

                HTML,
            ],
            [
                'type' => 'statement',
                'title' => 'Loan Statement',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Statement generated on {{current_date}}.</p>',
                'body' => <<<'HTML'
                                            <h3 style="background: #a00000; color: white; padding: 8px;">Loan Statement</h3>
                        <p><strong>Statement Date:</strong> {{current_date}}</p>
                        <p><strong>Loan Account Number:</strong> {{loan_number}}</p>
                        <p><strong>Borrower Name:</strong> {{client_name}}</p>
                        <p>&nbsp;</p>
                        <h3 style="background: #a00000; color: white; padding: 8px;">Loan Summary</h3>
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                        <tbody>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Loan Amount</th>
                        <td style="padding: 8px;">₹{{loan_amount}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Total Payable Amount</th>
                        <td style="padding: 8px;">₹{{total_payable}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Total Paid Amount</th>
                        <td style="padding: 8px;">₹{{total_paid}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Outstanding Amount</th>
                        <td style="padding: 8px;">₹{{outstanding_amount}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Loan Start Date</th>
                        <td style="padding: 8px;">{{loan_start_date}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Loan End Date (Expected)</th>
                        <td style="padding: 8px;">{{loan_end_date}}</td>
                        </tr>
                        </tbody>
                        </table>
                        <p>&nbsp;</p>
                        <h3 style="background: #a00000; color: white; padding: 8px;">EMI Summary</h3>
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                        <tbody>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">EMI Amount</th>
                        <td style="padding: 8px;">₹{{emi_amount}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">Total EMIs</th>
                        <td style="padding: 8px;">{{total_emis}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">EMIs Paid</th>
                        <td style="padding: 8px;">{{emis_paid}}</td>
                        </tr>
                        <tr>
                        <th style="padding: 8px; background: #f2f2f2;">EMIs Remaining</th>
                        <td style="padding: 8px;">{{emis_remaining}} </td>
                        </tr>
                        </tbody>
                        </table>
                        <p>&nbsp;</p>
                        <h3 style="background: #a00000; color: white; padding: 8px;">Transaction Details</h3>
                        <p>The table below reflects all the EMI payments and transactions recorded against this loan.</p>
                        <p>{{transaction_rows}}</p>
                        <p>&nbsp;</p>
                        <p>This statement summarizes all transactions recorded in our system as of the statement date. If you find any discrepancies, please contact our support team within 7 working days.</p>
                        <p>&nbsp;</p>
                        <p>Regards,<br><strong>{{company_name}}</strong><br>Authorized Representative</p>
                HTML,
            ],
            [
                'type' => 'noc',
                'title' => 'No Objection Certificate',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Issued on {{current_date}}</p>',
                'body' => <<<'HTML'
                            <h3 style="background: #a00000; color: white; padding: 8px;">No Objection Certificate (NOC)</h3>
                            <p><strong>Date:</strong> {{current_date}}</p>
                            <p><strong>Loan Account Number:</strong> {{loan_number}}</p>
                            <p><strong>Borrower Name:</strong> {{client_name}}</p>
                            <p>To whom it may concern,</p>
                            <p>This is to certify that the above-mentioned borrower has fully repaid the loan availed from <strong>{{company_name}}</strong>. The loan associated with Loan Account Number <strong>{{loan_number}}</strong> stands <strong>completely closed</strong> as on <strong>{{closure_date}}</strong>.</p>
                            <p>As on the date of issue of this certificate, the borrower has:</p>
                            <ul>
                            <li>No outstanding principal balance</li>
                            <li>No pending EMIs</li>
                            <li>No overdue charges or penalties</li>
                            <li>No further liabilities with respect to this loan</li>
                            </ul>
                            <p><strong>{{company_name}}</strong> hereby confirms that it has <strong>No Objection</strong> to the borrower and releases them from all financial obligations related to this loan account.</p>
                            <p>If any security, hypothecation, or document was collected as part of the loan process, the same is considered released on loan closure. The borrower may use this certificate for any legal or administrative purposes as required.</p>
                            <p>This NOC is issued at the request of the borrower for their record and future reference.</p>
                            <p>&nbsp;</p>
                            <p>Regards,<br><strong>{{company_name}}</strong><br>Authorized Signatory</p>
                          HTML,
            ],
            [
                'type' => 'Foreclosure_letter',
                'title' => 'Foreclosure Letter',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Issued on {{current_date}}</p>',
                'body' => <<<'HTML'
                            <h3 style="background: #a00000; color: white; padding: 8px;">Foreclosure Letter</h3>
                            <p><strong>Date:</strong> {{current_date}}</p>
                            <p><strong>Loan Account Number:</strong> {{loan_number}}</p>
                            <p><strong>Borrower Name:</strong> {{client_name}}</p>
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">Foreclosure Summary</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                            <tbody>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Total Outstanding Before Foreclosure</th>
                            <td style="padding: 8px;">₹{{outstanding_before_foreclosure}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Foreclosure Charges</th>
                            <td style="padding: 8px;">₹{{foreclosure_charges}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Total Amount Paid for Foreclosure</th>
                            <td style="padding: 8px;">₹{{total_foreclosure_amount}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Foreclosure Payment Date</th>
                            <td style="padding: 8px;">{{foreclosure_payment_date}}</td>
                            </tr>
                            </tbody>
                            </table>
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">Confirmation</h3>
                            <p>This letter is to acknowledge that <strong>{{client_name}}</strong> has opted for <strong>foreclosure</strong> of the above-mentioned loan account. We have received the complete foreclosure amount as on <strong>{{foreclosure_payment_date}}</strong>.</p>
                            <p>Upon receipt of the above amount, the loan account is considered <strong>closed</strong> from our end, subject to internal reconciliation. No further EMIs will be presented for payment.</p>
                            <p>The borrower is now free from all financial obligations related to this loan account. Any security or hypothecation created as part of the loan process is considered released on loan closure.</p>
                            <p>A <strong>Loan Closure Certificate</strong> followed by a <strong>No Objection Certificate (NOC)</strong> will be issued separately.</p>
                            <p>&nbsp;</p>
                            <p>For any clarifications, please contact our support team.</p>
                            <p>&nbsp;</p>
                            <p>Regards,<br><strong>{{company_name}}</strong><br>Authorized Signatory</p>
                          HTML,
            ],
            [
                'type' => 'Loan_sanction_letter',
                'title' => 'Loan Sanction Letter',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Issued on {{current_date}}</p>',
                'body' => <<<'HTML'
                                    <h3 style="background: #a00000; color: white; padding: 8px;">Sanction Letter</h3>
                                    <p><strong>Date:</strong> {{current_date}}</p>
                                    <p><strong>Loan Account Number:</strong> {{loan_number}}</p>
                                    <p><strong>Borrower Name:</strong> {{client_name}}</p>
                                    <p>&nbsp;</p>
                                    <h3 style="background: #a00000; color: white; padding: 8px;">Loan Sanction Details</h3>
                                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                                    <tbody>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Loan Type</th>
                                    <td style="padding: 8px;">{{loan_type}}</td>
                                    </tr>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Sanctioned Amount</th>
                                    <td style="padding: 8px;">₹{{sanctioned_amount}}</td>
                                    </tr>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Interest Rate</th>
                                    <td style="padding: 8px;">{{interest_rate}}% per annum</td>
                                    </tr>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Tenure</th>
                                    <td style="padding: 8px;">{{tenure_months}} months</td>
                                    </tr>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">EMI Amount</th>
                                    <td style="padding: 8px;">₹{{emi_amount}}</td>
                                    </tr>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Processing Fee</th>
                                    <td style="padding: 8px;">₹{{processing_fee}}</td>
                                    </tr>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Net Disbursal Amount</th>
                                    <td style="padding: 8px;">₹{{net_disbursal_amount}}</td>
                                    </tr>
                                    </tbody>
                                    </table>
                                    <p>&nbsp;</p>
                                    <h3 style="background: #a00000; color: white; padding: 8px;">Borrower Details</h3>
                                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                                    <tbody>
                                    <tr>
                                    <th style="padding: 8px; background: #f2f2f2;">Name</th>
                                    <td style="padding: 8px;">{{client_name}}</td>
                                    </tr>
                                    </tbody>
                                    </table>
                                    <p>&nbsp;</p>
                                    <h3 style="background: #a00000; color: white; padding: 8px;">Terms &amp; Conditions</h3>
                                    <ol style="font-size: 14px; line-height: 1.5;">
                                    <li>The borrower agrees to repay the loan as per the sanctioned terms and EMI schedule.</li>
                                    <li>The lender reserves the right to revise charges or conditions as per regulatory guidelines.</li>
                                    <li>The borrower shall ensure timely payment of EMIs to avoid penalty and negative credit impact.</li>
                                    <li>Any false information or document discrepancy may result in cancellation of this sanction.</li>
                                    <li>This sanction letter is valid for {{sanction_validity_days}} days from the date of issue.</li>
                                    </ol>
                                    <p>&nbsp;</p>
                                    <p><strong>Confirmation:</strong><br>This sanction has been approved and issued electronically by <strong>{{company_name}}</strong> on {{current_date}}.</p>
                                    <p>&nbsp;</p>
                                    <p>Regards,<br><strong>{{company_name}}</strong><br>Authorized Signatory</p>
                          HTML,
            ],
            [
                'type' => 'loan_closure_certificate',
                'title' => 'Loan Closure Certificate',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Issued on {{current_date}}</p>',
                'body' => <<<'HTML'
                            <h3 style="background: #a00000; color: white; padding: 8px;">Loan Closure Certificate</h3>
                            <p><strong>Date of Issue:</strong> {{current_date}}</p>
                            <p><strong>Loan Account Number:</strong> {{loan_number}}</p>
                            <p><strong>Borrower Name:</strong> {{client_name}}</p>
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">Closure Summary</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                            <tbody>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Loan Amount</th>
                            <td style="padding: 8px;">₹{{loan_amount}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Total Amount Paid</th>
                            <td style="padding: 8px;">₹{{total_amount_paid}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Outstanding Amount</th>
                            <td style="padding: 8px;">₹0.00</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Closure Date</th>
                            <td style="padding: 8px;">{{closure_date}}</td>
                            </tr>
                            </tbody>
                            </table>
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">Confirmation</h3>
                            <p>This is to certify that the loan availed by <strong>{{client_name}}</strong> under the Loan Account Number <strong>{{loan_number}}</strong> has been <strong>fully repaid</strong> as on <strong>{{closure_date}}</strong>.</p>
                            <p>All outstanding dues, penalties, and EMIs associated with this loan have been cleared. There is no remaining balance payable by the borrower.</p>
                            <p>The loan account is now officially marked as <strong>Closed</strong> in our records. No further charges or obligations exist with respect to this loan.</p>
                            <p>If any security, documents, or hypothecation were submitted during the loan process, they are considered released upon closure.</p>
                            <p>A separate <strong>No Objection Certificate (NOC)</strong> will be issued to the borrower as the final confirmation of loan closure.</p>
                            <p>&nbsp;</p>
                            <p>We appreciate your timely repayment and thank you for choosing <strong>{{company_name}}</strong>.</p>
                            <p>&nbsp;</p>
                            <p>Regards,<br><strong>{{company_name}}</strong><br>Authorized Signatory</p>
                          HTML,
            ],
            [
                'type' => 'loan_agreement',
                'title' => 'Loan Agreement',
                'header' => null,
                'footer' => '<p style="text-align:center;font-size:11px;color:#6c757d;">Page 1 of 1 • Generated on {{current_date}}</p>',
                'body' => <<<'HTML'
                            <h3 style="background: #a00000; color: white; padding: 8px; text-align: center;">LOAN AGREEMENT</h3>
                            <p>This Loan Agreement is entered into on <strong>{{agreement_date}}</strong> by and between:</p>
                            
                            <p><strong>LENDER:</strong> <strong>{{company_name}}</strong>, having its principal place of business at {{address_line1}}, {{company_city}}, {{company_state}} - {{company_pincode}} (hereinafter referred to as "the Lender").</p>
                            
                            <p>AND</p>
                            
                            <p><strong>BORROWER:</strong> <strong>{{client_name}}</strong>, residing at {{client_address}} (hereinafter referred to as "the Borrower").</p>
                            
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">1. LOAN DETAILS &amp; TERMS</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                            <tbody>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2; width: 40%;">Loan Account Number</th>
                            <td style="padding: 8px;"><strong>{{loan_number}}</strong></td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Loan Scheme / Product</th>
                            <td style="padding: 8px;">{{product_name}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Loan Principal Amount</th>
                            <td style="padding: 8px;"><strong>₹{{loan_amount}}</strong></td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Rate of Interest</th>
                            <td style="padding: 8px;">{{interest_rate}}% per annum</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Loan Tenure</th>
                            <td style="padding: 8px;">{{tenure}} months</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Monthly EMI Amount</th>
                            <td style="padding: 8px;"><strong>₹{{emi_amount}}</strong></td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Net Disbursed Amount</th>
                            <td style="padding: 8px;">₹{{net_disbursal_amount}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Processing Fee &amp; Charges</th>
                            <td style="padding: 8px;">₹{{total_charges}}</td>
                            </tr>
                            </tbody>
                            </table>
                            
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">2. DISBURSEMENT BANK DETAILS</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                            <tbody>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2; width: 40%;">Bank Name</th>
                            <td style="padding: 8px;">{{bank_name}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Account Number</th>
                            <td style="padding: 8px;">{{account_number}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">IFSC Code</th>
                            <td style="padding: 8px;">{{ifsc_code}}</td>
                            </tr>
                            </tbody>
                            </table>
                            
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">3. NOMINEE / GUARANTOR DETAILS</h3>
                            <table style="width: 100%; border-collapse: collapse; font-size: 14px;" border="1">
                            <tbody>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2; width: 40%;">Guarantor Name</th>
                            <td style="padding: 8px;">{{guarantor_name}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Guarantor Relationship</th>
                            <td style="padding: 8px;">{{guarantor_relationship}}</td>
                            </tr>
                            <tr>
                            <th style="padding: 8px; background: #f2f2f2;">Guarantor Mobile Number</th>
                            <td style="padding: 8px;">{{guarantor_mobile}}</td>
                            </tr>
                            </tbody>
                            </table>
                            
                            <p>&nbsp;</p>
                            <h3 style="background: #a00000; color: white; padding: 8px;">4. UNDERTAKING &amp; TERMS OF REPAYMENT</h3>
                            <ol style="font-size: 14px; line-height: 1.5; margin-left: 20px;">
                            <li>The Borrower promises to pay to the Lender the Principal Amount along with interest in accordance with the EMI schedule.</li>
                            <li>Late payments will attract overdue penalties and charges as defined under the Lender's policies.</li>
                            <li>The Lender reserves the right to declare the entire outstanding loan due and payable immediately upon any default by the Borrower.</li>
                            <li>This agreement shall be governed by the laws of India and subject to the jurisdiction of local courts.</li>
                            </ol>
                            
                            <p>&nbsp;</p>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 40px; font-size: 14px;">
                            <tbody>
                            <tr>
                            <td style="width: 50%; border: none; padding: 10px;">
                            <br><br><br>
                            ___________________________<br>
                            <strong>Signature of Borrower</strong><br>
                            Name: {{client_name}}
                            </td>
                            <td style="width: 50%; border: none; padding: 10px; text-align: right;">
                            <br><br><br>
                            ___________________________<br>
                            <strong>For {{company_name}}</strong><br>
                            Authorized Signatory
                            </td>
                            </tr>
                            </tbody>
                            </table>
                            HTML,
            ]
        ];

        foreach ($templates as $templateData) {
            LoanDocumentTemplate::updateOrCreate(
                ['type' => $templateData['type']],
                $templateData
            );
        }
    }
}
