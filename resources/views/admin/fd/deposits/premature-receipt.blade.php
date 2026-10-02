<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Premature Receipt — {{ $deposit->fd_number }}</title>
    <style>
        @page { margin: 16mm; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #2f3349;
            margin: 0;
            padding: 20px;
        }
        .receipt {
            border: 1px solid #d9dbe5;
            max-width: 720px;
            margin: 0 auto;
            padding: 24px;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 18px;
            color: #ea5455;
        }
        .meta { color: #6d6f85; font-size: 12px; margin-bottom: 16px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            text-align: left;
            font-size: 13px;
        }
        th { background: #f4f5fa; width: 40%; color: #6d6f85; font-weight: 600; }
        .highlight {
            background: #fff5f5;
            border: 1px solid #f8c9c9;
            padding: 12px;
            margin: 16px 0;
            text-align: center;
        }
        .highlight .lbl { font-size: 12px; color: #6d6f85; }
        .highlight .val { font-size: 22px; font-weight: bold; color: #ea5455; margin-top: 4px; }
        .print-bar { text-align: center; margin-bottom: 14px; }
        .print-bar button, .print-bar a {
            display: inline-block;
            padding: 8px 14px;
            margin: 0 4px;
            border: 1px solid #ea5455;
            background: #ea5455;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .print-bar a.secondary { background: #fff; color: #ea5455; }
        @media print {
            .print-bar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button type="button" onclick="window.print()">Print Receipt</button>
        <a class="secondary" href="{{ route('fd.deposits.show', $deposit) }}">Back to FD</a>
    </div>

    <div class="receipt">
        <h1>Premature Withdrawal Receipt</h1>
        <div class="meta">
            Generated: {{ now()->format('d M Y, h:i A') }} · FD: {{ $deposit->fd_number }}
        </div>

        <div class="highlight">
            <div class="lbl">Amount Paid / Payable</div>
            <div class="val">
                ₹{{ number_format((float) ($deposit->closure_amount ?? ($txn->amount ?? 0)), 2) }}
            </div>
        </div>

        <table>
            <tr>
                <th>FD Number</th>
                <td>{{ $deposit->fd_number }}</td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>{{ $deposit->client->client_name ?? '—' }}</td>
            </tr>
            <tr>
                <th>Scheme</th>
                <td>{{ $deposit->scheme->name ?? '—' }}</td>
            </tr>
            <tr>
                <th>Original Principal</th>
                <td>₹{{ number_format((float) $deposit->deposit_amount, 2) }}</td>
            </tr>
            <tr>
                <th>Interest (Original)</th>
                <td>₹{{ number_format((float) $deposit->interest_amount, 2) }}</td>
            </tr>
            <tr>
                <th>Closure / Withdrawal Date</th>
                <td>{{ optional($deposit->closure_date)->format('d M Y') ?? optional($txn->created_at ?? null)->format('d M Y') ?? '—' }}</td>
            </tr>
            <tr>
                <th>Penalty</th>
                <td>₹{{ number_format((float) ($txn->penalty_amount ?? 0), 2) }}</td>
            </tr>
            <tr>
                <th>Interest Credited (Txn)</th>
                <td>₹{{ number_format((float) ($txn->interest_amount ?? 0), 2) }}</td>
            </tr>
            <tr>
                <th>Payout Option</th>
                <td>{{ $deposit->payout_option_label }}</td>
            </tr>
            <tr>
                <th>Payment Mode</th>
                <td>{{ $deposit->closure_payment_mode ?: '—' }}</td>
            </tr>
            <tr>
                <th>Reference</th>
                <td>{{ $deposit->closure_transaction_ref ?: ($txn->description ?? '—') }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $deposit->status_label }}</td>
            </tr>
        </table>

        <p style="margin-top:24px;font-size:12px;color:#6d6f85;text-align:center;">
            This is a computer-generated receipt for premature closure of the Fixed Deposit.
        </p>
    </div>
</body>
</html>
