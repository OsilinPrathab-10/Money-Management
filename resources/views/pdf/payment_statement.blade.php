<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Statement - {{ $statementData['statement_number'] }}</title>
    
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
            font-size: 10px;
            line-height: 1.3;
            color: #000;
            background: white;
            padding: 0;
        }

        .statement-container {
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

        .statement-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: var(--primary-color);
            letter-spacing: 2px;
            margin: 15px 0;
        }

        .details-box {
            margin: 0 20px 15px 20px;
            border: 1px solid #cfd3d9;
            background: #f5f8fb;
            padding: 12px 16px;
            font-size: 9px;
        }

        .details-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-box td {
            padding: 3px 6px;
            vertical-align: top;
        }

        .details-box td.label {
            font-weight: 600;
            color: #3d4146;
            width: 25%;
        }

        .details-box td.value {
            color: #000;
            width: 25%;
        }

        /* Table Section */
        .table-section {
            padding: 0 20px;
            margin-bottom: 20px;
        }

        .statement-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            font-size: 9px;
        }

        .statement-table th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
        }

        .statement-table td {
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
            font-size: 9px;
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
            font-size: 8px;
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

            .statement-container {
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
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
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
        Print Statement
    </button>

    @php
        $brandLogo = SettingsHelper::get('admin_logo')
            ? asset('storage/' . SettingsHelper::get('admin_logo'))
            : asset('assets/img/branding/logo.png');
        $brandTitle = SettingsHelper::get('admin_title') ?? config('variables.templateName', 'Loan App');
        $brandSubtitle = SettingsHelper::get('admin_subtitle') ?? config('variables.templateSuffix', 'Loan Management System');
    @endphp

    <div class="statement-container">
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
            
            <div class="statement-title">LOAN STATEMENT OF LEDGER</div>
        </div>

        <div class="details-box">
            <table>
                <tr>
                    <td class="label">Statement No:</td>
                    <td class="value">{{ $statementData['statement_number'] }}</td>
                    <td class="label">Total Loan Amount:</td>
                    <td class="value" style="font-weight: bold; color: var(--primary-color);">₹ {{ number_format($statementData['loan_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Client Name:</td>
                    <td class="value">{{ $statementData['client_name'] }}</td>
                    <td class="label">Total Principal Paid:</td>
                    <td class="value" style="color: #696cff;">₹ {{ number_format($statementData['principal_paid'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Loan Account No:</td>
                    <td class="value">{{ $statementData['application_number'] }}</td>
                    <td class="label">Total Interest Paid:</td>
                    <td class="value" style="color: #71dd37;">₹ {{ number_format($statementData['interest_paid'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Loan Scheme:</td>
                    <td class="value">{{ $statementData['loan_product'] }}</td>
                    <td class="label">Principal Balance:</td>
                    <td class="value" style="font-weight: bold; color: #ff3e1d;">₹ {{ number_format($statementData['outstanding_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Interest Rate:</td>
                    <td class="value">{{ $statementData['interest_rate'] }}% p.a.</td>
                    <td class="label">Total Collected:</td>
                    <td class="value" style="font-weight: bold; color: #71dd37;">₹ {{ number_format($statementData['paid_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Disbursed Date:</td>
                    <td class="value">{{ $statementData['disbursed_date'] }}</td>
                    <td class="label">Loan Status:</td>
                    <td class="value" style="font-weight: bold; text-transform: uppercase;">{{ $statementData['status'] }}</td>
                </tr>
            </table>
        </div>

        <!-- Ledger Table -->
        <div class="table-section">
            <table class="statement-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">Cycle</th>
                        <th style="width: 12%;">Due Date</th>
                        <th class="amount-cell" style="width: 14%;">Principal Due</th>
                        <th class="amount-cell" style="width: 14%;">Interest Due</th>
                        <th class="amount-cell" style="width: 10%;">Penalty</th>
                        <th class="amount-cell" style="width: 14%;">Total Due</th>
                        <th class="amount-cell" style="width: 14%;">Amount Paid</th>
                        <th style="width: 14%; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statementData['emis'] as $emi)
                        <tr>
                            <td style="text-align: center;">#{{ $emi->instalment_number }}</td>
                            <td>{{ $emi->due_date ? $emi->due_date->format('d-m-Y') : 'N/A' }}</td>
                            <td class="amount-cell">₹ {{ number_format($emi->principal_amount, 2) }}</td>
                            <td class="amount-cell">₹ {{ number_format($emi->interest_amount, 2) }}</td>
                            <td class="amount-cell">₹ {{ number_format($emi->penalty_amount ?? 0, 2) }}</td>
                            <td class="amount-cell" style="font-weight: bold;">₹ {{ number_format($emi->total_due, 2) }}</td>
                            <td class="amount-cell" style="font-weight: bold; color: #71dd37;">₹ {{ number_format($emi->paid_amount, 2) }}</td>
                            <td style="text-align: center;">
                                <span class="status-badge status-{{ strtolower($emi->status) }}">
                                    {{ $emi->status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div></div>
                <div class="digital-signature">
                    <span class="label">Digitally Signed Statement</span>
                    <span>{{ now()->format('d-m-Y h:i A') }}</span>
                </div>
            </div>
            <div class="generated-line">
                Statement generated automatically on {{ now()->format('d-m-Y h:i A') }}.
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger print dialog when page loads
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
