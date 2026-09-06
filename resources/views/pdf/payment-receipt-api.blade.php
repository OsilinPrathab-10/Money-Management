<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $receiptData['receipt_number'] }}</title>

    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            border: 1px solid #333;
            padding: 6px;
        }

        .right {
            text-align: right;
        }

        .label {
            font-weight: bold;
            width: 40%;
        }
    </style>
</head>
<body>

<div class="title">PAYMENT RECEIPT</div>

<table>
    <tr>
        <td class="label">Receipt No</td>
        <td>{{ $receiptData['receipt_number'] }}</td>
    </tr>
    <tr>
        <td class="label">Payment Date</td>
        <td>{{ $receiptData['paid_date'] }}</td>
    </tr>
    <tr>
        <td class="label">Transaction ID</td>
        <td>{{ $receiptData['payment_reference'] }}</td>
    </tr>
    <tr>
        <td class="label">Loan ID</td>
        <td>{{ $receiptData['account_number'] }}</td>
    </tr>
    <tr>
        <td class="label">Disbursement Date</td>
        <td>{{ $receiptData['disbursed_date'] }}</td>
    </tr>
</table>

<div class="section-title">Payment Details</div>

<table>
    <tr>
        <th>Description</th>
        <th class="right">Amount (₹)</th>
    </tr>
    <tr>
        <td>Principal Paid</td>
        <td class="right">{{ number_format($receiptData['principal_amount'], 2) }}</td>
    </tr>
    <tr>
        <td>Interest Paid</td>
        <td class="right">{{ number_format($receiptData['interest_amount'], 2) }}</td>
    </tr>

    @if(!empty($receiptData['show_overdue']) && $receiptData['show_overdue'])
    <tr>
        <td>Overdue Amount</td>
        <td class="right">{{ number_format($receiptData['overdue_amount'], 2) }}</td>
    </tr>
    @endif

    <tr>
        <td><strong>Total EMI Amount</strong></td>
        <td class="right"><strong>{{ number_format($receiptData['emi_amount'], 2) }}</strong></td>
    </tr>
    <tr>
        <td><strong>Total Paid</strong></td>
        <td class="right"><strong>{{ number_format($receiptData['paid_amount'], 2) }}</strong></td>
    </tr>
</table>

<p style="margin-top: 15px; text-align: center; font-size: 10px;">
    This is a computer-generated receipt. No signature required.
</p>

</body>
</html>
