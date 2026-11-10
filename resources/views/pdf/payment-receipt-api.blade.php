<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receiptData['receipt_number'] }}</title>

    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
        }

        .receipt-header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #7100e2;
            margin-bottom: 14px;
        }

        .title {
            font-size: 16px;
            font-weight: 800;
            color: #7100e2;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .receipt-no {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .meta-table td {
            padding: 5px 8px;
            font-size: 10.5px;
            vertical-align: top;
        }

        .meta-label {
            font-weight: 700;
            color: #475569;
            width: 38%;
        }

        .meta-value {
            font-weight: 700;
            color: #0f172a;
        }

        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
        }

        .kpi-table td {
            width: 20%;
            padding: 8px 6px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            vertical-align: middle;
        }

        .kpi-table td:last-child {
            border-right: none;
        }

        .kpi-table td.active {
            background: #f5f3ff;
            border-top: 2px solid #7100e2;
            border-bottom: 2px solid #7100e2;
        }

        .kpi-lbl {
            font-size: 8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .kpi-val {
            font-size: 11.5px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        .kpi-val-active {
            font-size: 13px;
            font-weight: 900;
            color: #7100e2;
            margin-top: 2px;
        }

        .kpi-sub {
            font-size: 7.5px;
            color: #94a3b8;
            margin-top: 1px;
            font-weight: 600;
        }

        .kpi-sub-active {
            font-size: 7.5px;
            color: #7100e2;
            margin-top: 1px;
            font-weight: 700;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            border: 1px solid #cbd5e1;
        }

        .payment-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .payment-table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            font-size: 10.5px;
        }

        .right {
            text-align: right;
        }

        .grand-total {
            background-color: #f8fafc;
            font-weight: 800;
            border-top: 2px solid #7100e2;
            font-size: 11.5px;
        }

        .grand-total-amount {
            color: #7100e2;
            font-size: 12px;
            font-weight: 800;
        }

        .footer-note {
            margin-top: 18px;
            text-align: center;
            font-size: 9.5px;
            color: #64748b;
        }
    </style>
</head>
<body>

@php
    if (!function_exists('numberToIndianWords')) {
        function numberToIndianWords($number) {
            $number = round((float) $number, 2);
            if ($number <= 0) return 'Zero Rupees Only';

            $decimal = round(($number - floor($number)) * 100);
            $num = (int) floor($number);

            $words = [
                0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
                6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
                11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
                16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
                20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
                60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
            ];

            $convertBelowThousand = function($n) use ($words) {
                $str = '';
                if ($n >= 100) {
                    $str .= $words[(int) ($n / 100)] . ' Hundred ';
                    $n %= 100;
                }
                if ($n > 0) {
                    if ($n < 20) {
                        $str .= $words[$n] . ' ';
                    } else {
                        $str .= $words[(int) ($n / 10) * 10] . ' ';
                        if ($n % 10 > 0) {
                            $str .= $words[$n % 10] . ' ';
                        }
                    }
                }
                return trim($str);
            };

            $crore = (int) ($num / 10000000);
            $num %= 10000000;
            $lakh = (int) ($num / 100000);
            $num %= 100000;
            $thousand = (int) ($num / 1000);
            $num %= 1000;
            $hundreds = $num;

            $res = '';
            if ($crore > 0) $res .= $convertBelowThousand($crore) . ' Crore ';
            if ($lakh > 0) $res .= $convertBelowThousand($lakh) . ' Lakh ';
            if ($thousand > 0) $res .= $convertBelowThousand($thousand) . ' Thousand ';
            if ($hundreds > 0) $res .= $convertBelowThousand($hundreds) . ' ';

            $res = trim($res);
            $res = $res ? $res . ' Rupees' : '';

            if ($decimal > 0) {
                $paise = $convertBelowThousand($decimal);
                $res .= ($res ? ' and ' : '') . $paise . ' Paise';
            }

            return $res ? $res . ' Only' : 'Zero Rupees Only';
        }
    }

    $module = strtolower($receiptData['module'] ?? 'loan');
    $title = trim(preg_replace('/\bBULK\s+/i', '', $receiptData['receipt_title'] ?? 'LOAN PAYMENT RECEIPT'));
    $currentPayment = (float) ($receiptData['current_payment'] ?? $receiptData['paid_amount'] ?? 0);
    $dueAmount = (float) ($receiptData['due_amount'] ?? $receiptData['total_due_amount'] ?? $currentPayment);
    $previouslyPaid = (float) ($receiptData['previously_paid'] ?? $receiptData['previous_paid_amount'] ?? 0);
    $todayPayment = (float) ($receiptData['today_payment'] ?? $currentPayment);
    $totalPaidToDate = (float) ($receiptData['total_paid_amount'] ?? round($previouslyPaid + $currentPayment, 2));
    $remainingDue = (float) ($receiptData['outstanding_amount'] ?? max(0.0, round($dueAmount - $totalPaidToDate, 2)));
    $isPartial = !empty($receiptData['is_partial']) || $remainingDue > 0.01;
@endphp

<div class="receipt-header">
    <div class="title">{{ $title }}</div>
    <div class="receipt-no">{{ $receiptData['receipt_number'] }}</div>
</div>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 6px;">
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Receipt No:</td>
                    <td class="meta-value">{{ $receiptData['receipt_number'] }}</td>
                </tr>
                @if(!empty($receiptData['client_name']))
                <tr>
                    <td class="meta-label">Customer Name:</td>
                    <td class="meta-value">{{ $receiptData['client_name'] }}</td>
                </tr>
                @endif
                <tr>
                    <td class="meta-label">Payment Date:</td>
                    <td class="meta-value">{{ $receiptData['paid_date_only'] ?? $receiptData['paid_date'] }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Transaction Ref:</td>
                    <td class="meta-value">{{ $receiptData['payment_reference'] ?: 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Payment Mode:</td>
                    <td class="meta-value" style="text-transform: uppercase;">{{ $receiptData['payment_method'] ?? 'CASH' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 50%; vertical-align: top; padding-left: 6px;">
            <table class="meta-table">
                <tr>
                    <td class="meta-label">{{ $receiptData['account_label'] ?? 'Account No' }}:</td>
                    <td class="meta-value">{{ $receiptData['account_number'] ?? $receiptData['application_number'] ?? 'N/A' }}</td>
                </tr>
                @if(!empty($receiptData['loan_product']))
                <tr>
                    <td class="meta-label">Product / Scheme:</td>
                    <td class="meta-value">{{ $receiptData['loan_product'] }}</td>
                </tr>
                @endif
                @if(!empty($receiptData['instalment_label']))
                <tr>
                    <td class="meta-label">{{ $receiptData['split_item_label'] ?? 'Installment' }}:</td>
                    <td class="meta-value">{{ $receiptData['instalment_label'] }}</td>
                </tr>
                @endif
                @if(!empty($receiptData['disbursed_date']) && $receiptData['disbursed_date'] !== 'N/A')
                <tr>
                    <td class="meta-label">{{ $receiptData['start_date_label'] ?? 'Start Date' }}:</td>
                    <td class="meta-value">{{ $receiptData['disbursed_date'] }}</td>
                </tr>
                @endif
                <tr>
                    <td class="meta-label">Payment Status:</td>
                    <td class="meta-value" style="color: #15803d;">{{ $receiptData['status_label'] ?? 'Paid' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Financial Summary KPI Strip -->
<table class="kpi-table">
    <tr>
        <td>
            <div class="kpi-lbl">Due Amount</div>
            <div class="kpi-val">₹{{ number_format($dueAmount, 2) }}</div>
            <div class="kpi-sub">Total Due</div>
        </td>
        <td>
            <div class="kpi-lbl">Previously Paid</div>
            <div class="kpi-val" style="color: #64748b;">₹{{ number_format($previouslyPaid, 2) }}</div>
            <div class="kpi-sub">Prior Payments</div>
        </td>
        <td class="active">
            <div class="kpi-lbl" style="color: #7100e2;">Current Payment</div>
            <div class="kpi-val-active">₹{{ number_format($currentPayment, 2) }}</div>
            <div class="kpi-sub-active">Received Now</div>
        </td>
        <td>
            <div class="kpi-lbl">Total Paid</div>
            <div class="kpi-val" style="color: #15803d;">₹{{ number_format($totalPaidToDate, 2) }}</div>
            <div class="kpi-sub">Cumulative</div>
        </td>
        <td>
            <div class="kpi-lbl">Remaining Due</div>
            <div class="kpi-val" style="color: {{ $remainingDue > 0.01 ? '#b91c1c' : '#15803d' }};">
                {{ $remainingDue > 0.01 ? '₹' . number_format($remainingDue, 2) : 'NIL (₹0.00)' }}
            </div>
            <div class="kpi-sub">{{ $remainingDue > 0.01 ? 'Pending' : 'Cleared' }}</div>
        </td>
    </tr>
</table>
@if($todayPayment > 0 && abs($todayPayment - $currentPayment) > 0.01)
    <div style="margin-top: -6px; margin-bottom: 10px; padding: 4px 8px; background: #f1f5f9; border: 1px dashed #cbd5e1; font-size: 9px; color: #475569;">
        &#9432; <strong>Today's Total Collections Reference:</strong> ₹{{ number_format($todayPayment, 2) }} across today's receipts.
    </div>
@elseif($todayPayment > 0)
    <div style="margin-top: -6px; margin-bottom: 10px; padding: 4px 8px; background: #f1f5f9; border: 1px dashed #cbd5e1; font-size: 9px; color: #475569;">
        &#10003; <strong>Today's Payment Reference:</strong> ₹{{ number_format($todayPayment, 2) }}
    </div>
@endif

<table class="payment-table">
    <thead>
        <tr>
            <th>Particulars / Description</th>
            <th class="right">Amount (₹)</th>
        </tr>
    </thead>
    <tbody>
        @if($module === 'fd')
            <tr>
                <td>Deposit / Transaction Amount ({{ $receiptData['instalment_label'] ?? 'FD' }})</td>
                <td class="right">{{ number_format($receiptData['principal_amount'] ?? $currentPayment, 2) }}</td>
            </tr>
            @if(!empty($receiptData['interest_amount']) && (float)$receiptData['interest_amount'] > 0)
            <tr>
                <td>Interest Credited / Paid</td>
                <td class="right">{{ number_format((float)$receiptData['interest_amount'], 2) }}</td>
            </tr>
            @endif
        @else
            <tr>
                <td>Principal Portion Paid</td>
                <td class="right">{{ number_format((float)($receiptData['principal_amount'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Interest Portion Paid</td>
                <td class="right">{{ number_format((float)($receiptData['interest_amount'] ?? 0), 2) }}</td>
            </tr>
        @endif

        @if(!empty($receiptData['show_overdue']) && (float)($receiptData['overdue_amount'] ?? 0) > 0)
        <tr>
            <td style="color: #b91c1c;">Late Payment Penalty / Overdue Fee</td>
            <td class="right" style="color: #b91c1c;">{{ number_format((float)$receiptData['overdue_amount'], 2) }}</td>
        </tr>
        @endif

        <tr class="grand-total">
            <td><strong>Current Payment Received</strong></td>
            <td class="right grand-total-amount"><strong>₹{{ number_format($currentPayment, 2) }}</strong></td>
        </tr>

        <tr style="font-size: 10px; background: #f8fafc; color: #475569;">
            <td>Due Amount (Total Scheduled Installment)</td>
            <td class="right">₹{{ number_format($dueAmount, 2) }}</td>
        </tr>

        @if($previouslyPaid > 0)
        <tr style="font-size: 10px; color: #64748b;">
            <td>Previously Paid Amount</td>
            <td class="right">₹{{ number_format($previouslyPaid, 2) }}</td>
        </tr>
        @endif

        <tr style="font-size: 10px; background: #f0fdf4; color: #15803d; font-weight: bold;">
            <td>Total Paid to Date (Previous + Current)</td>
            <td class="right">₹{{ number_format($totalPaidToDate, 2) }}</td>
        </tr>

        @if($remainingDue > 0.01)
        <tr style="background: #fef2f2; color: #b91c1c; font-weight: bold;">
            <td>Remaining Balance Due</td>
            <td class="right">₹{{ number_format($remainingDue, 2) }}</td>
        </tr>
        @else
        <tr style="background: #f0fdf4; color: #15803d; font-size: 10px;">
            <td>&#10003; Installment Status</td>
            <td class="right">Fully Paid (₹0.00 Balance)</td>
        </tr>
        @endif
    </tbody>
</table>

<div style="margin-top: 10px; padding: 6px 10px; background: #f8fafc; border: 1px dashed #cbd5e1; font-size: 10px;">
    <strong>Amount in Words:</strong> <span style="color: #7100e2; font-style: italic;">{{ numberToIndianWords($currentPayment) }}</span>
</div>

<p class="footer-note">
    This is a computer-generated digital receipt. No signature is required.
</p>

</body>
</html>
