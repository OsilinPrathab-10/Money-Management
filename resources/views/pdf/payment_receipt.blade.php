<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $receiptData['receipt_title'] ?? 'Payment Receipt' }} - {{ $receiptData['receipt_number'] }}</title>

    @php
        use App\Helpers\SettingsHelper;

        if (!function_exists('numberToIndianWords')) {
            function numberToIndianWords($number)
            {
                $number = round((float) $number, 2);
                if ($number <= 0)
                    return 'Zero Rupees Only';

                $decimal = round(($number - floor($number)) * 100);
                $num = (int) floor($number);

                $words = [
                    0 => '',
                    1 => 'One',
                    2 => 'Two',
                    3 => 'Three',
                    4 => 'Four',
                    5 => 'Five',
                    6 => 'Six',
                    7 => 'Seven',
                    8 => 'Eight',
                    9 => 'Nine',
                    10 => 'Ten',
                    11 => 'Eleven',
                    12 => 'Twelve',
                    13 => 'Thirteen',
                    14 => 'Fourteen',
                    15 => 'Fifteen',
                    16 => 'Sixteen',
                    17 => 'Seventeen',
                    18 => 'Eighteen',
                    19 => 'Nineteen',
                    20 => 'Twenty',
                    30 => 'Thirty',
                    40 => 'Forty',
                    50 => 'Fifty',
                    60 => 'Sixty',
                    70 => 'Seventy',
                    80 => 'Eighty',
                    90 => 'Ninety'
                ];

                $convertBelowThousand = function ($n) use ($words) {
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
                if ($crore > 0) {
                    $res .= $convertBelowThousand($crore) . ' Crore ';
                }
                if ($lakh > 0) {
                    $res .= $convertBelowThousand($lakh) . ' Lakh ';
                }
                if ($thousand > 0) {
                    $res .= $convertBelowThousand($thousand) . ' Thousand ';
                }
                if ($hundreds > 0) {
                    $res .= $convertBelowThousand($hundreds) . ' ';
                }

                $res = trim($res);
                $res = $res ? $res . ' Rupees' : '';

                if ($decimal > 0) {
                    $paise = $convertBelowThousand($decimal);
                    $res .= ($res ? ' and ' : '') . $paise . ' Paise';
                }

                return $res ? $res . ' Only' : 'Zero Rupees Only';
            }
        }

        $adminFavicon = SettingsHelper::get('admin_favicon');
        $primaryColor = SettingsHelper::get('primary_color', '#7100e2');

        $settingsLogo = SettingsHelper::get('admin_logo');
        $brandLogo = '';
        if ($settingsLogo && file_exists(public_path('storage/' . $settingsLogo))) {
            $brandLogo = asset('storage/' . $settingsLogo);
        } elseif (file_exists(public_path('assets/img/branding/logo.png'))) {
            $brandLogo = asset('assets/img/branding/logo.png');
        }

        $brandTitle = SettingsHelper::get('admin_title') ?: config('variables.templateName', 'osilinprathab');
        $brandSubtitle = SettingsHelper::get('admin_subtitle') ?: config('variables.templateSuffix', 'Co-operative Society Bank');
        $footerText = SettingsHelper::get('footer_text') ?: 'Thank you for your payment.';

        $module = strtolower($receiptData['module'] ?? '');
        if (!$module) {
            if (!empty($receiptData['is_combined'])) {
                $module = 'combined';
            } elseif (!empty($receiptData['items'][0]['type'])) {
                $module = strtolower($receiptData['items'][0]['type']);
            } else {
                $module = 'loan';
            }
        }

        $rawReceiptTitle = $receiptData['receipt_title'] ?? null;
        if (!$rawReceiptTitle) {
            $rawReceiptTitle = match ($module) {
                'fd' => 'FIXED DEPOSIT RECEIPT',
                default => (!empty($receiptData['is_partial']) ? 'LOAN PARTIAL PAYMENT RECEIPT' : 'LOAN PAYMENT RECEIPT'),
            };
        }
        $displayReceiptTitle = trim(preg_replace('/\bBULK\s+/i', '', $rawReceiptTitle));

        $statusKey = strtolower($receiptData['status'] ?? 'paid');
        $paidDateOnly = $receiptData['paid_date_only'] ?? (!empty($receiptData['paid_date']) ? explode(' ', $receiptData['paid_date'])[0] : 'N/A');
        $displayTime = $receiptData['paid_time'] ?? '';
        if (!$displayTime && !empty($receiptData['paid_date']) && str_contains($receiptData['paid_date'], ' ')) {
            $displayTime = trim(substr($receiptData['paid_date'], 10));
        }

        $items = $receiptData['items'] ?? [];
        $isBulk = !empty($receiptData['is_bulk']) || count($items) > 1;

        $currentPayment = (float) ($receiptData['current_payment'] ?? $receiptData['paid_amount'] ?? 0);
        $dueAmount = (float) ($receiptData['due_amount'] ?? $receiptData['total_due_amount'] ?? $currentPayment);
        $previouslyPaid = (float) ($receiptData['previously_paid'] ?? $receiptData['previous_paid_amount'] ?? 0);
        $todayPayment = (float) ($receiptData['today_payment'] ?? $currentPayment);
        $totalPaidToDate = (float) ($receiptData['total_paid_amount'] ?? round($previouslyPaid + $currentPayment, 2));
        $remainingDue = (float) ($receiptData['outstanding_amount'] ?? max(0.0, round($dueAmount - $totalPaidToDate, 2)));
        $isPartial = !empty($receiptData['is_partial']) || $remainingDue > 0.01;
        $totalPaid = $currentPayment;
    @endphp

    @if($adminFavicon)
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . $adminFavicon) }}" />
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    @endif

    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        :root {
            --primary-color:
                {{ $primaryColor }}
            ;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #1e293b;
            background: #ffffff;
            padding: 12px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .receipt-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        /* Top Accent Header */
        .top-accent-bar {
            height: 6px;
            background:
                {{ $primaryColor }}
            ;
            width: 100%;
        }

        /* Header Section */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #e2e8f0;
            background: #ffffff;
        }

        .header-table td {
            padding: 16px 20px;
            vertical-align: middle;
        }

        .logo-img {
            max-height: 48px;
            max-width: 150px;
            object-fit: contain;
            display: block;
        }

        .company-name {
            font-size: 18px;
            font-weight: 800;
            color:
                {{ $primaryColor }}
            ;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .company-subtitle {
            font-size: 10.5px;
            color: #64748b;
            font-weight: 600;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-title-cell {
            text-align: right;
            vertical-align: middle;
        }

        .receipt-title-badge {
            display: inline-block;
            background:
                {{ $primaryColor }}
            ;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 5px 14px;
            border-radius: 20px;
        }

        .receipt-no-display {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'DejaVu Sans Mono', monospace, Arial;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }

        .receipt-date-sub {
            font-size: 9.5px;
            color: #64748b;
            margin-top: 3px;
            font-weight: 500;
        }

        /* Overview Details Box */
        .details-wrapper {
            padding: 14px 20px;
            background: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table td {
            vertical-align: top;
            padding: 0;
        }

        .details-col-left {
            width: 49%;
            padding-right: 14px !important;
        }

        .details-col-divider {
            width: 2%;
            border-left: 1px dashed #cbd5e1;
        }

        .details-col-right {
            width: 49%;
            padding-left: 14px !important;
        }

        .meta-list-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-list-table td {
            padding: 3.5px 0;
            font-size: 10.5px;
            vertical-align: top;
        }

        .meta-list-table td.meta-label {
            font-weight: 600;
            color: #475569;
            width: 42%;
        }

        .meta-list-table td.meta-value {
            font-weight: 700;
            color: #0f172a;
            width: 58%;
        }

        /* Status & Type Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-paid,
        .status-verified,
        .status-approved,
        .status-successful {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .status-partial {
            background-color: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #d8b4fe;
        }

        .status-pending,
        .status-in_progress {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .status-overdue,
        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .module-tag {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .module-tag-loan {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        .module-tag-fd {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        /* Financial Summary Strip */
        .summary-kpi-wrapper {
            margin: 14px 20px 0 20px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
        }

        .summary-kpi-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-kpi-table td.kpi-cell {
            width: 20%;
            padding: 9px 8px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            vertical-align: middle;
        }

        .summary-kpi-table td.kpi-cell:last-child {
            border-right: none;
        }

        .summary-kpi-table td.kpi-cell-active {
            background: #f5f3ff !important;
            border-top: 2px solid
                {{ $primaryColor }}
            ;
            border-bottom: 2px solid
                {{ $primaryColor }}
            ;
        }

        .kpi-label {
            font-size: 8.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .kpi-value {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'DejaVu Sans Mono', monospace, Arial;
        }

        .kpi-sub {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 2px;
            font-weight: 600;
        }

        .kpi-label-active {
            font-size: 8.5px;
            font-weight: 800;
            color:
                {{ $primaryColor }}
            ;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .kpi-value-active {
            font-size: 14.5px;
            font-weight: 900;
            color:
                {{ $primaryColor }}
            ;
            font-family: 'DejaVu Sans Mono', monospace, Arial;
        }

        .kpi-sub-active {
            font-size: 8px;
            color:
                {{ $primaryColor }}
            ;
            margin-top: 2px;
            font-weight: 700;
        }

        .kpi-today-note {
            padding: 5px 12px;
            background: #f1f5f9;
            border-top: 1px dashed #cbd5e1;
            font-size: 9px;
            color: #475569;
        }

        /* Particulars Table Section */
        .table-section {
            padding: 16px 20px 12px 20px;
        }

        .section-header-title {
            font-size: 11px;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
        }

        .payment-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .payment-table td {
            padding: 6.5px 10px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }

        .payment-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .amount-cell {
            text-align: right;
            font-weight: 700;
            font-family: 'DejaVu Sans Mono', monospace, Arial;
            color: #0f172a;
        }

        .total-highlight-row td {
            background-color: #f1f5f9 !important;
            font-weight: 800;
            border-top: 2px solid #cbd5e1;
            font-size: 11px;
        }

        .grand-total-row td {
            background-color: #f8fafc !important;
            border-top: 2px solid
                {{ $primaryColor }}
                !important;
            font-size: 12px;
            font-weight: 800;
        }

        .grand-total-label {
            color:
                {{ $primaryColor }}
            ;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grand-total-amount {
            color:
                {{ $primaryColor }}
            ;
            font-size: 13px;
            font-weight: 800;
        }

        .partial-alert-row td {
            background-color: #fef2f2 !important;
            color: #b91c1c;
            border-top: 1px solid #fecaca;
        }

        /* Amount in Words Box */
        .words-box {
            margin: 0 20px 14px 20px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
        }

        .words-table {
            width: 100%;
            border-collapse: collapse;
        }

        .words-table td {
            vertical-align: middle;
        }

        .words-label {
            font-size: 9.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 120px;
        }

        .words-value {
            font-size: 11px;
            font-weight: 700;
            color:
                {{ $primaryColor }}
            ;
            font-style: italic;
        }

        /* Footer Section */
        .footer-section {
            padding: 12px 20px 16px 20px;
            border-top: 1.5px solid #e2e8f0;
            background: #ffffff;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            vertical-align: middle;
        }

        .footer-terms {
            font-size: 9.5px;
            color: #64748b;
            line-height: 1.4;
        }

        .digital-seal-box {
            text-align: right;
            font-size: 9.5px;
            color: #334155;
            line-height: 1.4;
        }

        .seal-verified-text {
            color:
                {{ $primaryColor }}
            ;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 10px;
            display: block;
            margin-bottom: 2px;
        }

        .seal-time-text {
            font-size: 9px;
            color: #64748b;
            font-family: 'DejaVu Sans Mono', monospace, Arial;
        }

        .bottom-audit-bar {
            text-align: center;
            font-size: 8.5px;
            color: #94a3b8;
            padding: 6px 20px;
            border-top: 1px solid #f1f5f9;
            background: #fafafa;
        }

        /* Screen Preview Toolbar and Modal Embedding */
        @media screen {
            body {
                background: #f1f5f9;
                padding: 24px 15px 48px;
            }

            body.in-iframe {
                background: #ffffff !important;
                padding: 8px 10px !important;
            }

            body.in-iframe .screen-toolbar {
                display: none !important;
            }

            body.in-iframe .receipt-container {
                box-shadow: none !important;
                border: 1px solid #cbd5e1;
            }

            .screen-toolbar {
                max-width: 800px;
                margin: 0 auto 14px auto;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 10px 16px;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            }

            .toolbar-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }

            .toolbar-left {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .toolbar-badge {
                display: inline-block;
                background: #ede9fe;
                color: #5b21b6;
                font-size: 11px;
                font-weight: 700;
                padding: 3px 8px;
                border-radius: 4px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .toolbar-id {
                font-weight: 800;
                color: #0f172a;
                font-size: 13px;
                font-family: monospace;
            }

            .toolbar-right {
                display: flex;
                gap: 8px;
            }

            .btn-action {
                border: none;
                cursor: pointer;
                font-weight: 600;
                font-size: 12px;
                padding: 7px 16px;
                border-radius: 6px;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .btn-print {
                background:
                    {{ $primaryColor }}
                ;
                color: #ffffff;
            }

            .btn-print:hover {
                opacity: 0.92;
                transform: translateY(-1px);
            }

            .btn-close-win {
                background: #e2e8f0;
                color: #334155;
            }

            .btn-close-win:hover {
                background: #cbd5e1;
            }

            .receipt-container {
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            }
        }

        /* Print Optimization */
        @media print {
            body {
                padding: 0 !important;
                background: #ffffff !important;
            }

            .no-print,
            .screen-toolbar {
                display: none !important;
            }

            .receipt-container {
                border: 1px solid #94a3b8 !important;
                box-shadow: none !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>

<body>

    <!-- Screen Preview Toolbar (Hidden in Print and in Iframe Modal) -->
    <div class="no-print screen-toolbar" id="screenToolbar">
        <div class="toolbar-inner">
            <div class="toolbar-left">
                <span class="toolbar-badge">{{ $displayReceiptTitle }}</span>
                <span class="toolbar-id">{{ $receiptData['receipt_number'] }}</span>
            </div>
            <div class="toolbar-right">
                <button type="button" onclick="window.print()" class="btn-action btn-print">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print Receipt
                </button>
                <button type="button" onclick="window.close()" class="btn-action btn-close-win">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Main Receipt Document Container -->
    <div class="receipt-container">
        <!-- Top Primary Color Bar -->
        <div class="top-accent-bar"></div>

        <!-- Header Table -->
        <table class="header-table">
            <tr>
                <td style="width: 58%;">
                    <table style="border-collapse: collapse; border: none;">
                        <tr>
                            @if(!empty($brandLogo))
                                <td style="padding: 0 12px 0 0; vertical-align: middle;">
                                    <img src="{{ $brandLogo }}" alt="{{ $brandTitle }}" class="logo-img"
                                        onerror="this.style.display='none'">
                                </td>
                            @endif
                            <td style="padding: 0; vertical-align: middle;">
                                <div class="company-name">{{ $brandTitle }}</div>
                                <div class="company-subtitle">{{ $brandSubtitle }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="header-title-cell" style="width: 42%;">
                    <div class="receipt-title-badge">{{ $displayReceiptTitle }}</div>
                    <div class="receipt-no-display">{{ $receiptData['receipt_number'] }}</div>
                    <div class="receipt-date-sub">
                        Issued: {{ $paidDateOnly }} @if(!empty($displayTime)) · {{ $displayTime }} IST @endif
                    </div>
                </td>
            </tr>
        </table>

        <!-- Overview Details Grid Section -->
        <div class="details-wrapper">
            <table class="details-table">
                <tr>
                    <!-- Left Column: Payment Details -->
                    <td class="details-col-left">
                        <table class="meta-list-table">
                            <tr>
                                <td class="meta-label">Receipt No:</td>
                                <td class="meta-value">{{ $receiptData['receipt_number'] }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Customer Name:</td>
                                <td class="meta-value">{{ $receiptData['client_name'] ?? 'N/A' }}</td>
                            </tr>
                            @if(!empty($receiptData['customer_id']))
                                <tr>
                                    <td class="meta-label">Customer ID:</td>
                                    <td class="meta-value">{{ $receiptData['customer_id'] }}</td>
                                </tr>
                            @endif
                            @if(!empty($receiptData['client_phone']))
                                <tr>
                                    <td class="meta-label">Contact No:</td>
                                    <td class="meta-value">{{ $receiptData['client_phone'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="meta-label">Payment Date:</td>
                                <td class="meta-value">{{ $paidDateOnly }}</td>
                            </tr>
                            @if(!empty($displayTime))
                                <tr>
                                    <td class="meta-label">Payment Time:</td>
                                    <td class="meta-value">
                                        <span
                                            style="font-family: 'DejaVu Sans Mono', monospace; font-size: 11px;">{{ $displayTime }}</span>
                                        <span
                                            style="font-size: 8px; font-weight: 700; color: #64748b; background: #e2e8f0; padding: 1px 4px; border-radius: 3px; margin-left: 2px;">IST</span>
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td class="meta-label">Transaction Ref:</td>
                                <td class="meta-value">{{ $receiptData['payment_reference'] ?: 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Payment Mode:</td>
                                <td class="meta-value" style="text-transform: uppercase;">
                                    {{ $receiptData['payment_method'] ?? 'CASH' }}</td>
                            </tr>
                        </table>
                    </td>

                    <!-- Center Divider -->
                    <td class="details-col-divider"></td>

                    <!-- Right Column: Account / Product Details -->
                    <td class="details-col-right">
                        <table class="meta-list-table">
                            <tr>
                                <td class="meta-label">Module Type:</td>
                                <td class="meta-value">
                                    <span class="module-tag module-tag-{{ $module }}">
                                        {{ $module === 'fd' ? 'Fixed Deposit' : 'Loan' }}
                                    </span>
                                </td>
                            </tr>
                            @if(!empty($receiptData['loan_product']) && $receiptData['loan_product'] !== 'N/A')
                                <tr>
                                    <td class="meta-label">{{ $module === 'fd' ? 'Scheme Plan:' : 'Loan Product:' }}</td>
                                    <td class="meta-value">{{ $receiptData['loan_product'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="meta-label">
                                    {{ $receiptData['account_label'] ?? ($module === 'fd' ? 'FD Number' : 'Loan ID') }}:
                                </td>
                                <td class="meta-value">
                                    {{ $receiptData['account_number'] ?? $receiptData['application_number'] ?? 'N/A' }}
                                </td>
                            </tr>
                            @if(!empty($receiptData['application_number']) && !empty($receiptData['account_number']) && $receiptData['application_number'] !== $receiptData['account_number'])
                                <tr>
                                    <td class="meta-label">Application Ref:</td>
                                    <td class="meta-value">{{ $receiptData['application_number'] }}</td>
                                </tr>
                            @endif
                            @if(!empty($receiptData['instalment_label']) && $receiptData['instalment_label'] !== 'N/A')
                                <tr>
                                    <td class="meta-label">
                                        {{ $receiptData['split_item_label'] ?? ($module === 'fd' ? 'Txn Type' : 'EMI') }}:
                                    </td>
                                    <td class="meta-value">{{ $receiptData['instalment_label'] }}</td>
                                </tr>
                            @endif
                            @if(!empty($receiptData['disbursed_date']) && $receiptData['disbursed_date'] !== 'N/A')
                                <tr>
                                    <td class="meta-label">
                                        {{ $receiptData['start_date_label'] ?? ($module === 'fd' ? 'Deposit Date' : 'Disbursed Date') }}:
                                    </td>
                                    <td class="meta-value">{{ $receiptData['disbursed_date'] }}</td>
                                </tr>
                            @endif
                            @if(!empty($receiptData['collector_name']))
                                <tr>
                                    <td class="meta-label">Collected By:</td>
                                    <td class="meta-value">{{ $receiptData['collector_name'] }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="meta-label">Payment Status:</td>
                                <td class="meta-value">
                                    <span class="status-badge status-{{ $statusKey }}">
                                        {{ $receiptData['status_label'] ?? ucfirst($statusKey) }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Financial Summary KPI Strip -->
        <div class="summary-kpi-wrapper">
            <table class="summary-kpi-table">
                <tr>
                    <td class="kpi-cell">
                        <div class="kpi-label">Due Amount</div>
                        <div class="kpi-value">₹{{ number_format($dueAmount, 2) }}</div>
                        <div class="kpi-sub">Total Scheduled</div>
                    </td>
                    <td class="kpi-cell">
                        <div class="kpi-label">Previously Paid</div>
                        <div class="kpi-value" style="color: #64748b;">₹{{ number_format($previouslyPaid, 2) }}</div>
                        <div class="kpi-sub">Prior Payments</div>
                    </td>
                    <td class="kpi-cell kpi-cell-active">
                        <div class="kpi-label-active">Current Payment</div>
                        <div class="kpi-value-active">₹{{ number_format($currentPayment, 2) }}</div>
                        <div class="kpi-sub-active">Received Now</div>
                    </td>
                    <td class="kpi-cell">
                        <div class="kpi-label">Total Paid</div>
                        <div class="kpi-value" style="color: #15803d;">₹{{ number_format($totalPaidToDate, 2) }}</div>
                        <div class="kpi-sub">Cumulative Paid</div>
                    </td>
                    <td class="kpi-cell">
                        <div class="kpi-label">Remaining Balance</div>
                        <div class="kpi-value" style="color: {{ $remainingDue > 0.01 ? '#b91c1c' : '#15803d' }};">
                            {{ $remainingDue > 0.01 ? '₹' . number_format($remainingDue, 2) : 'NIL (₹0.00)' }}
                        </div>
                        <div class="kpi-sub">{{ $remainingDue > 0.01 ? 'Pending Due' : 'Fully Cleared' }}</div>
                    </td>
                </tr>
            </table>
            @if($todayPayment > 0 && abs($todayPayment - $currentPayment) > 0.01)
                <div class="kpi-today-note" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">&#9432; <strong>Today's Total Collections
                            Reference:</strong> ₹{{ number_format($todayPayment, 2) }} across today's receipts.</div>
                    <div style="display: table-cell; text-align: right;"><strong>Current Payment:</strong>
                        ₹{{ number_format($currentPayment, 2) }}</div>
                </div>
            @elseif($todayPayment > 0)
                <div class="kpi-today-note" style="display: table; width: 100%;">
                    <div style="display: table-cell; text-align: left;">&#10003; <strong>Today's Payment Reference:</strong>
                        ₹{{ number_format($todayPayment, 2) }}</div>
                    <div style="display: table-cell; text-align: right;"><strong>Payment Status:</strong>
                        {{ $receiptData['status_label'] ?? ucfirst($statusKey) }}</div>
                </div>
            @endif
        </div>

        <!-- Particulars / Breakdown Table Section -->
        <div class="table-section">
            <div class="section-header-title">Payment Particulars & Breakdown</div>

            @if($isBulk && count($items) > 0)
                {{-- Bulk / Multiple Items Breakdown --}}
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;" class="text-center">#</th>
                            <th style="width: 20%;">Type / Account Ref</th>
                            <th style="width: 25%;">Customer / Details</th>
                            <th style="width: 18%;">Installment / Item</th>
                            <th style="width: 16%;" class="text-right">Due Amount (₹)</th>
                            <th style="width: 16%;" class="text-right">Current Paid (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $subtotalDue = 0;
                        $subtotalPaid = 0; @endphp
                        @foreach($items as $index => $item)
                            @php
                                $itemType = strtolower($item['type'] ?? 'loan');
                                $due = (float) ($item['due_amount'] ?? $item['paid_amount'] ?? 0);
                                $paid = (float) ($item['paid_amount'] ?? 0);
                                $subtotalDue += $due;
                                $subtotalPaid += $paid;
                            @endphp
                            <tr>
                                <td class="text-center font-weight-bold">{{ $index + 1 }}</td>
                                <td>
                                    <span class="module-tag module-tag-{{ $itemType }}">{{ strtoupper($itemType) }}</span>
                                    <div style="font-weight: 700; margin-top: 2px;">
                                        {{ $item['account_number'] ?? $item['group_code'] ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $item['client_name'] ?? ($receiptData['client_name'] ?? 'N/A') }}</td>
                                <td><span
                                        style="font-weight: 600;">{{ $item['instalment_no'] ?? $item['item_label'] ?? 'N/A' }}</span>
                                </td>
                                <td class="amount-cell">₹{{ number_format($due, 2) }}</td>
                                <td class="amount-cell" style="color: #15803d;">₹{{ number_format($paid, 2) }}</td>
                            </tr>
                        @endforeach

                        @if(!empty($receiptData['show_overdue']) && (float) ($receiptData['overdue_amount'] ?? 0) > 0)
                            <tr>
                                <td colspan="5" class="text-right font-weight-bold" style="color: #b91c1c;">Late Fee / Overdue
                                    Penalty Charges</td>
                                <td class="amount-cell" style="color: #b91c1c;">
                                    ₹{{ number_format((float) $receiptData['overdue_amount'], 2) }}</td>
                            </tr>
                        @endif

                        <tr class="grand-total-row">
                            <td colspan="5" class="text-right grand-total-label">Current Payment Received</td>
                            <td class="amount-cell grand-total-amount">₹{{ number_format($currentPayment, 2) }}</td>
                        </tr>

                        <tr class="total-highlight-row" style="font-size: 10px;">
                            <td colspan="5" class="text-right" style="color: #475569; font-weight: 600;">Total Scheduled Due
                            </td>
                            <td class="amount-cell" style="color: #475569;">
                                ₹{{ number_format(max($subtotalDue, $dueAmount), 2) }}</td>
                        </tr>
                        @if($previouslyPaid > 0)
                            <tr style="font-size: 10px;">
                                <td colspan="5" class="text-right" style="color: #64748b; font-weight: 600;">Previously Paid
                                    Amount</td>
                                <td class="amount-cell" style="color: #64748b;">₹{{ number_format($previouslyPaid, 2) }}</td>
                            </tr>
                        @endif
                        <tr style="font-size: 10px; background-color: #f8fafc;">
                            <td colspan="5" class="text-right" style="color: #15803d; font-weight: 700;">Total Paid to Date
                                (Previous + Current)</td>
                            <td class="amount-cell" style="color: #15803d;">₹{{ number_format($totalPaidToDate, 2) }}</td>
                        </tr>
                        @if($remainingDue > 0.01)
                            <tr class="partial-alert-row">
                                <td colspan="5" class="text-right font-weight-bold">Remaining Balance Due</td>
                                <td class="amount-cell" style="color: #b91c1c;">₹{{ number_format($remainingDue, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

            @elseif($module === 'fd')
                {{-- Fixed Deposit Particulars --}}
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Particulars / Description</th>
                            <th style="width: 25%;">Transaction Reference</th>
                            <th style="width: 25%;" class="text-right">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight: 700;">Fixed Deposit Transaction</div>
                                <div style="font-size: 9.5px; color: #64748b;">Scheme:
                                    {{ $receiptData['loan_product'] ?? 'Fixed Deposit Scheme' }}</div>
                            </td>
                            <td>{{ $receiptData['instalment_label'] ?? 'Deposit' }}</td>
                            <td class="amount-cell">
                                ₹{{ number_format($receiptData['principal_amount'] ?? $currentPayment, 2) }}</td>
                        </tr>

                        @if(!empty($receiptData['interest_amount']) && (float) $receiptData['interest_amount'] > 0)
                            <tr>
                                <td>Interest Credited / Payout</td>
                                <td>Interest Component</td>
                                <td class="amount-cell">₹{{ number_format((float) $receiptData['interest_amount'], 2) }}</td>
                            </tr>
                        @endif

                        @if(!empty($receiptData['show_overdue']) && (float) ($receiptData['overdue_amount'] ?? 0) > 0)
                            <tr>
                                <td style="color: #b91c1c; font-weight: 600;">Premature Penalty / Deductions</td>
                                <td style="color: #b91c1c;">Closure Deduction</td>
                                <td class="amount-cell" style="color: #b91c1c;">-
                                    ₹{{ number_format((float) $receiptData['overdue_amount'], 2) }}</td>
                            </tr>
                        @endif

                        <tr class="grand-total-row">
                            <td colspan="2" class="text-right grand-total-label">Current Payment Received</td>
                            <td class="amount-cell grand-total-amount">₹{{ number_format($currentPayment, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

            @else
                {{-- Standard Loan Payment Particulars --}}
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Particulars / Description</th>
                            <th style="width: 25%;">Allocation Component</th>
                            <th style="width: 25%;" class="text-right">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight: 700;">Principal Portion Paid</div>
                                <div style="font-size: 9.5px; color: #64748b;">Allocation towards principal reduction</div>
                            </td>
                            <td>Principal</td>
                            <td class="amount-cell">₹{{ number_format((float) ($receiptData['principal_amount'] ?? 0), 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style="font-weight: 700;">Interest Portion Paid</div>
                                <div style="font-size: 9.5px; color: #64748b;">Scheduled finance interest charge</div>
                            </td>
                            <td>Interest</td>
                            <td class="amount-cell">₹{{ number_format((float) ($receiptData['interest_amount'] ?? 0), 2) }}
                            </td>
                        </tr>

                        @if(!empty($receiptData['show_overdue']) && (float) ($receiptData['overdue_amount'] ?? 0) > 0)
                            <tr>
                                <td style="color: #b91c1c;">
                                    <div style="font-weight: 700;">Late Payment Penalty / Overdue Fee</div>
                                    <div style="font-size: 9.5px; color: #b91c1c;">Default / late payment assessment</div>
                                </td>
                                <td style="color: #b91c1c;">Penalty</td>
                                <td class="amount-cell" style="color: #b91c1c;">
                                    ₹{{ number_format((float) $receiptData['overdue_amount'], 2) }}</td>
                            </tr>
                        @endif

                        <tr class="grand-total-row">
                            <td colspan="2" class="text-right grand-total-label">Current Payment Received</td>
                            <td class="amount-cell grand-total-amount">₹{{ number_format($currentPayment, 2) }}</td>
                        </tr>

                        <tr class="total-highlight-row" style="font-size: 10px;">
                            <td colspan="2" class="text-right" style="color: #475569; font-weight: 600;">Due Amount (Total
                                Scheduled Installment)</td>
                            <td class="amount-cell" style="color: #475569;">₹{{ number_format($dueAmount, 2) }}</td>
                        </tr>
                        @if($previouslyPaid > 0)
                            <tr style="font-size: 10px;">
                                <td colspan="2" class="text-right" style="color: #64748b; font-weight: 600;">Previously Paid
                                    Amount</td>
                                <td class="amount-cell" style="color: #64748b;">₹{{ number_format($previouslyPaid, 2) }}</td>
                            </tr>
                        @endif
                        <tr style="font-size: 10px; background-color: #f8fafc;">
                            <td colspan="2" class="text-right" style="color: #15803d; font-weight: 700;">Total Paid to Date
                                (Previous + Current)</td>
                            <td class="amount-cell" style="color: #15803d;">₹{{ number_format($totalPaidToDate, 2) }}</td>
                        </tr>

                        @if($remainingDue > 0.01)
                            <tr class="partial-alert-row">
                                <td colspan="2" class="text-right font-weight-bold">Remaining Balance Due on Installment</td>
                                <td class="amount-cell" style="color: #b91c1c;">₹{{ number_format($remainingDue, 2) }}</td>
                            </tr>
                        @else
                            <tr style="background-color: #f0fdf4; color: #15803d; font-size: 10px;">
                                <td colspan="2" class="text-right font-weight-bold">&#10003; Installment Status</td>
                                <td class="amount-cell" style="color: #15803d;">Fully Paid (₹0.00 Balance)</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Amount in Words Container -->
        <div class="words-box">
            <table class="words-table">
                <tr>
                    <td class="words-label">Amount in Words:</td>
                    <td class="words-value">{{ numberToIndianWords($currentPayment) }}</td>
                </tr>
            </table>
        </div>

        <!-- Footer Section -->
        <div class="footer-section">
            <table class="footer-table">
                <tr>
                    <td style="width: 58%;">
                        <div class="footer-terms">
                            <strong>{{ $footerText }}</strong><br>
                            This is a computer-generated digital receipt. No signature is required.<br>
                            For queries, please contact our customer care or branch office.
                        </div>
                    </td>
                    <td style="width: 42%; text-align: right;">
                        <div class="digital-seal-box">
                            <span class="seal-verified-text">&#10004; Digitally Verified</span>
                            <span class="seal-time-text">Verified on {{ now()->format('d-m-Y h:i A') }} IST</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Audit Footprint -->
        <div class="bottom-audit-bar">
            Document Ref: {{ $receiptData['receipt_number'] }} · Generated on {{ now()->format('d-m-Y h:i:s A') }} IST
        </div>
    </div>

    <!-- Automatically detect iframe embedding -->
    <script>
        try {
            if (window.self !== window.top) {
                document.body.classList.add('in-iframe');
            }
        } catch (e) {
            // cross-origin protection
        }
    </script>
</body>

</html>