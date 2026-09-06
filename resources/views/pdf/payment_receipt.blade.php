<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - {{ $receiptData['receipt_number'] }}</title>
    
    @php
        use App\Helpers\SettingsHelper;

        $adminFavicon = SettingsHelper::get('admin_favicon');
        $primaryColor = SettingsHelper::get('primary_color', '#00BFFF');
    @endphp
    
    <!-- Favicon -->
    @if($adminFavicon)
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . $adminFavicon) }}" />
        <link rel="shortcut icon" href="{{ asset('storage/' . $adminFavicon) }}" />
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
        <link rel="shortcut icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    @endif

    <style>
        :root {
            --primary-color: {{ $primaryColor }};
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: white;
            padding: 0;
        }

        .receipt-container {
            width: 210mm;
            margin: 0 auto;
            background: white;
            border: 2px solid var(--primary-color);
            position: relative;
        }

        /* Top Blue Bar */
        .top-bar {
            height: 15px;
            background: var(--primary-color);
            width: 100%;
        }

        /* Header Section */
        .header {
            padding: 15px 20px 10px 20px;
            border-bottom: 1px solid #ddd;
        }

        .header-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .company-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .company-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: 0.5px;
        }

        .company-tagline {
            font-size: 10px;
            color: #555;
            letter-spacing: 0.3px;
        }

        .details-box {
            margin: 0 20px 15px 20px;
            border: 1px solid #cfd3d9;
            background: #f5f8fb;
            padding: 12px 16px;
            font-size: 10px;
        }

        .details-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-box tr + tr td {
            padding-top: 4px;
        }

        .details-box td {
            padding: 2px 6px;
            vertical-align: top;
        }

        .details-box td.label {
            font-weight: 600;
            color: #3d4146;
            width: 40%;
        }

        .details-box td.value {
            color: #000;
        }

        .receipt-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: var(--primary-color);
            letter-spacing: 2px;
            margin: 15px 0;
        }

        /* Table Section */
        .table-section {
            padding: 0 20px;
            margin-bottom: 20px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            font-size: 10px;
        }

        .payment-table th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
        }

        .payment-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }

        .amount-cell {
            text-align: right;
        }

        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }

        /* Footer Section */
        .footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            margin-top: 20px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .digital-signature {
            text-align: right;
            font-size: 10px;
            color: #333;
            line-height: 1.5;
        }

        .digital-signature .label {
            color: var(--primary-color);
            font-weight: 600;
            letter-spacing: 0.4px;
            display: block;
            margin-bottom: 4px;
        }

        .generated-line {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 2px;
            font-style: italic;
        }

        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .print-button:hover {
            background: var(--primary-color);
            filter: brightness(0.92);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        /* Print Styles */
        @media print {
            @page {
                size: A4;
                margin: 10mm 12mm 12mm 12mm;
            }

            body {
                padding: 0;
                background: white;
            }

            .receipt-container {
                border: 2px solid var(--primary-color);
                box-shadow: none;
                max-width: none;
                margin: 0;
                width: 100%;
            }

            .print-button {
                display: none;
            }

            .no-print {
                display: none !important;
            }

            .print-footer-mask {
                display: block;
                position: fixed;
                left: -2cm;
                right: -2cm;
                bottom: -2cm;
                height: 3cm;
                background: #fff;
            }

            .print-header-mask {
                display: block;
                position: fixed;
                left: -2cm;
                right: -2cm;
                top: -2cm;
                height: 3cm;
                background: #fff;
            }
        }

        .print-footer-mask {
            display: none;
        }

        .print-header-mask {
            display: none;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-paid {
            background-color: #e8fadf;
            color: #71dd37;
            border: 1px solid #71dd37;
        }

        .status-partial {
            background-color: #e7e7ff;
            color: #696cff;
            border: 1px solid #696cff;
        }

        .status-pending {
            background-color: #fff2e2;
            color: #ffab00;
            border: 1px solid #ffab00;
        }

        .status-overdue {
            background-color: #ffe5e5;
            color: #ff3e1d;
            border: 1px solid #ff3e1d;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button class="print-button no-print" onclick="window.print()">
        Print Receipt
    </button>

    @php
        $brandLogo = $adminLogo
            ? asset('storage/' . $adminLogo)
            : asset('assets/img/branding/logo.png');
        $brandTitle = $adminTitle ?? config('variables.templateName', 'Loan App');
        $brandSubtitle = $adminSubtitle ?? config('variables.templateSuffix', 'Loan Management System');
    @endphp

    <div class="receipt-container">
        <!-- Top Blue Bar -->
        <div class="top-bar"></div>

        <!-- Header -->
        <div class="header">
            <div class="header-row">
                <div class="logo-section">
                    <img src="{{ $brandLogo }}" alt="{{ $brandTitle }} Logo" class="logo">
                    <div class="company-info">
                        <div class="company-name">{{ $brandTitle }}</div>
                        <div class="company-tagline">{{ $brandSubtitle }}</div>
                    </div>
                </div>
            </div>
            
            <div class="receipt-title">STATEMENT / PAYMENT RECEIPT</div>
        </div>

        <div class="details-box">
            <table>
                <tr>
                    <td class="label">Receipt No:</td>
                    <td class="value">{{ $receiptData['receipt_number'] }}</td>
                </tr>
                <tr>
                    <td class="label">Payment Date &amp; Time:</td>
                    <td class="value">{{ $receiptData['paid_date'] }}</td>
                </tr>
                <tr>
                    <td class="label">Transaction ID:</td>
                    <td class="value">{{ $receiptData['payment_reference'] }}</td>
                </tr>
                <tr>
                    <td class="label">Mode of Payment:</td>
                    <td class="value">{{ $receiptData['payment_method'] }}</td>
                </tr>
                <tr>
                    <td class="label">Loan ID:</td>
                    <td class="value">{{ $receiptData['application_number'] }}</td>
                </tr>
                <tr>
                    <td class="label">Disbursement Date:</td>
                    <td class="value">{{ $receiptData['disbursed_date'] }}</td>
                </tr>
                @if(isset($receiptData['status']))
                <tr>
                    <td class="label">Payment Status:</td>
                    <td class="value">
                        <span class="status-badge status-{{ strtolower($receiptData['status']) }}">
                            {{ $receiptData['status_label'] ?? ucfirst($receiptData['status']) }}
                        </span>
                    </td>
                </tr>
                @endif
            </table>
        </div>

        <!-- Payment Table -->
        <div class="table-section">
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount-cell">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Principal Amount (Paid)</td>
                        <td class="amount-cell">₹ {{ number_format($receiptData['principal_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Interest Payment</td>
                        <td class="amount-cell">₹ {{ number_format($receiptData['interest_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Adjustment of Fees</td>
                        <td class="amount-cell">Nil</td>
                    </tr>
                    <tr>
                        <td>Thanking You</td>
                        <td class="amount-cell"></td>
                    </tr>
                    @if(!empty($receiptData['show_overdue']) && $receiptData['show_overdue'])
                    <tr>
                        <td>Overdue Amount</td>
                        <td class="amount-cell">₹ {{ number_format($receiptData['overdue_amount'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td><strong>Total Amount</strong></td>
                        <td class="amount-cell"><strong>₹ {{ number_format($receiptData['total_amount_display'] ?? $receiptData['emi_amount'], 2) }}</strong></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Total Amount Paid</strong></td>
                        <td class="amount-cell"><strong>₹ {{ number_format($receiptData['paid_amount'], 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div></div>
                <div class="digital-signature">
                    <span class="label">Digitally Signed</span>
                    <span>{{ now()->format('d-m-Y h:i A') }}</span>
                </div>
            </div>
            <div class="generated-line">
                Receipt generated automatically on {{ now()->format('d-m-Y h:i A') }}.
            </div>
        </div>
    </div>

    <div class="print-footer-mask"></div>

    <script>
        // Auto-trigger print dialog when page loads
        window.addEventListener('load', function() {
            // Small delay to ensure page is fully rendered
            setTimeout(function() {
                window.print();
            }, 500);
        });

        // Handle print button click
        function printReceipt() {
            window.print();
        }

        // Close window after printing (optional)
        window.addEventListener('afterprint', function() {
            // Uncomment the line below if you want to close the window after printing
            // window.close();
        });
    </script>
</body>
</html>