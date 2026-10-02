<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - {{ $receiptData['receipt_number'] }}</title>
    
    @php
        use App\Helpers\SettingsHelper;

        $adminFavicon = SettingsHelper::get('admin_favicon');
        $primaryColor = SettingsHelper::get('primary_color', '#7100e2');
    @endphp
    
    @if($adminFavicon)
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . $adminFavicon) }}" />
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    @endif

    <style>
        @page {
            size: A4;
            margin: 0;
        }

        :root {
            --primary-color: {{ $primaryColor }};
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #1e293b;
            background: #ffffff;
            padding: 15px;
        }

        .receipt-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid {{ $primaryColor }};
            border-radius: 8px;
            overflow: hidden;
        }

        /* Top Accent Bar */
        .top-bar {
            height: 8px;
            background: {{ $primaryColor }};
            width: 100%;
        }

        /* Header Section */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            padding: 16px 20px 12px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .header-table td {
            vertical-align: middle;
        }

        .logo-img {
            max-height: 48px;
            max-width: 160px;
            object-fit: contain;
        }

        .company-name {
            font-size: 20px;
            font-weight: 800;
            color: {{ $primaryColor }};
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .company-subtitle {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }

        .header-title-cell {
            text-align: right;
        }

        .receipt-title-badge {
            display: inline-block;
            background: {{ $primaryColor }};
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 16px;
            border-radius: 20px;
        }

        .receipt-no-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
            font-weight: 600;
        }

        /* Title banner when not in badge */
        .banner-title {
            text-align: center;
            font-size: 14px;
            font-weight: 800;
            color: {{ $primaryColor }};
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 12px 20px 8px 20px;
        }

        /* Metadata Details Grid / Box */
        .details-container {
            margin: 12px 20px 16px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table td {
            padding: 4px 6px;
            font-size: 10.5px;
            vertical-align: top;
        }

        .details-table td.label {
            font-weight: 600;
            color: #475569;
            width: 38%;
        }

        .details-table td.value {
            font-weight: 700;
            color: #0f172a;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-verified, .status-paid, .status-approved, .status-successful {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .status-partial {
            background-color: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #d8b4fe;
        }

        .status-pending, .status-in_progress {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .status-overdue, .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        /* Type Tag */
        .type-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .type-tag-loan {
            background: #e0f2fe;
            color: #0369a1;
        }

        .type-tag-chit {
            background: #ecfdf5;
            color: #047857;
        }

        /* Table Section */
        .table-section {
            padding: 0 20px;
            margin-bottom: 16px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
        }

        .payment-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .payment-table td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }

        .payment-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .amount-cell {
            text-align: right;
            font-weight: 600;
            font-family: 'DejaVu Sans', sans-serif;
        }

        .total-row td {
            background-color: #f1f5f9 !important;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
            font-size: 11px;
        }

        /* Footer Section */
        .footer {
            padding: 12px 20px 16px 20px;
            border-top: 1px solid #e2e8f0;
            margin-top: 10px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            vertical-align: bottom;
        }

        .digital-signature {
            text-align: right;
            font-size: 9.5px;
            color: #475569;
            line-height: 1.4;
        }

        .digital-signature .sig-label {
            color: {{ $primaryColor }};
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }

        .generated-line {
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            margin-top: 10px;
            font-style: italic;
        }

        /* Screen Preview Toolbar and Container */
        @media screen {
            body {
                background: #f1f5f9;
                padding: 24px 15px 48px;
            }
            .screen-toolbar {
                max-width: 800px;
                margin: 0 auto 16px auto;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 10px 16px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            }
            .toolbar-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }
            .toolbar-badge {
                display: inline-block;
                background: #ede9fe;
                color: #5b21b6;
                font-size: 11px;
                font-weight: 700;
                padding: 3px 8px;
                border-radius: 4px;
                margin-right: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .toolbar-id {
                font-weight: 700;
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
                background: var(--primary-color);
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
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
            }
        }

        /* Print Hide */
        @media print {
            body {
                padding: 0;
                background: #ffffff;
            }
            .receipt-container {
                border: none;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    @php
        $brandLogo = !empty($adminLogo)
            ? asset('storage/' . $adminLogo)
            : asset('assets/img/branding/logo.png');
        $brandTitle = $adminTitle ?? config('variables.templateName', 'Codepluse');
        $brandSubtitle = $adminSubtitle ?? config('variables.templateSuffix', 'Chitfund & Finance');
        $statusKey = strtolower($receiptData['status'] ?? 'verified');
    @endphp

    <!-- Screen Preview Toolbar (Hidden in Print) -->
    <div class="no-print screen-toolbar">
        <div class="toolbar-inner">
            <div class="toolbar-left">
                <span class="toolbar-badge">Official Receipt</span>
                <span class="toolbar-id">{{ $receiptData['receipt_number'] }}</span>
            </div>
            <div class="toolbar-right">
                <button type="button" onclick="window.print()" class="btn-action btn-print">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print Receipt
                </button>
                <button type="button" onclick="window.close()" class="btn-action btn-close-win">
                    Close
                </button>
            </div>
        </div>
    </div>

    <div class="receipt-container">
        <!-- Top Accent Bar -->
        <div class="top-bar"></div>

        <!-- Header Table -->
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="{{ $brandLogo }}" alt="{{ $brandTitle }}" class="logo-img" onerror="this.style.display='none'">
                        <div>
                            <div class="company-name">{{ $brandTitle }}</div>
                            <div class="company-subtitle">{{ $brandSubtitle }}</div>
                        </div>
                    </div>
                </td>
                <td class="header-title-cell" style="width: 40%;">
                    <div class="receipt-title-badge">PAYMENT RECEIPT</div>
                    <div class="receipt-no-sub">{{ $receiptData['receipt_number'] }}</div>
                </td>
            </tr>
        </table>

        <!-- Receipt Banner Title -->
        <div class="banner-title">{{ $receiptData['receipt_title'] ?? 'PAYMENT RECEIPT' }}</div>

        <!-- Details Grid Container -->
        <div class="details-container">
            <table class="details-table">
                <tr>
                    <td style="width: 50%; padding-right: 12px;">
                        <table class="details-table">
                            <tr>
                                <td class="label">Receipt No:</td>
                                <td class="value">{{ $receiptData['receipt_number'] }}</td>
                            </tr>
                            @if(!empty($receiptData['client_name']))
                            <tr>
                                <td class="label">Customer Name:</td>
                                <td class="value">{{ $receiptData['client_name'] }}</td>
                            </tr>
                            @endif
                            @php
                                $paidDateOnly = $receiptData['paid_date_only'] ?? (!empty($receiptData['paid_date']) ? explode(' ', $receiptData['paid_date'])[0] : 'N/A');
                                $displayTime = $receiptData['paid_time'] ?? '';
                                if (!$displayTime && !empty($receiptData['paid_date']) && str_contains($receiptData['paid_date'], ' ')) {
                                    $displayTime = trim(substr($receiptData['paid_date'], 10));
                                }
                            @endphp
                            <tr>
                                <td class="label">Payment Date:</td>
                                <td class="value">{{ $paidDateOnly }}</td>
                            </tr>
                            @if(!empty($displayTime))
                            <tr>
                                <td class="label">Payment Time:</td>
                                <td class="value">
                                    <span style="font-family: monospace; font-size: 11px; font-weight: 700; color: {{ $primaryColor }};">{{ $displayTime }}</span>
                                    <span style="font-size: 8.5px; font-weight: 600; color: #64748b; background: #e2e8f0; padding: 1px 4px; border-radius: 3px; margin-left: 2px;">IST</span>
                                </td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label">Transaction ID:</td>
                                <td class="value">{{ $receiptData['payment_reference'] }}</td>
                            </tr>
                            <tr>
                                <td class="label">Mode of Payment:</td>
                                <td class="value" style="text-transform: uppercase;">{{ $receiptData['payment_method'] }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; padding-left: 12px; border-left: 1px solid #e2e8f0;">
                        <table class="details-table">
                            <tr>
                                <td class="label">{{ $receiptData['account_label'] ?? 'Account / Ref' }}:</td>
                                <td class="value">{{ $receiptData['application_number'] }}</td>
                            </tr>
                            <tr>
                                <td class="label">{{ $receiptData['start_date_label'] ?? 'Start Date' }}:</td>
                                <td class="value">{{ $receiptData['disbursed_date'] }}</td>
                            </tr>
                            @php $splitLabel = $receiptData['split_item_label'] ?? 'EMI'; @endphp
                            @if(!empty($receiptData['instalment_label']))
                            <tr>
                                <td class="label">{{ $splitLabel }}:</td>
                                <td class="value">{{ $receiptData['instalment_label'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($receiptData['collector_name']))
                            <tr>
                                <td class="label">Collected By:</td>
                                <td class="value">{{ $receiptData['collector_name'] }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label">Payment Status:</td>
                                <td class="value">
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

        <!-- Payment Table Section -->
        <div class="table-section">
            @if(!empty($receiptData['items']) && count($receiptData['items']) > 1)
            <table class="payment-table">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">#</th>
                        <th style="width: 22%;">Type / Acc Ref</th>
                        <th style="width: 25%;">Customer / Item</th>
                        <th style="width: 18%;">Installment / EMI</th>
                        <th style="width: 15%;" class="amount-cell">Total Due</th>
                        <th style="width: 15%;" class="amount-cell">Amount Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receiptData['items'] as $index => $item)
                    @php $itemType = strtolower($item['type'] ?? 'loan'); @endphp
                    <tr>
                        <td style="text-align: center; font-weight: 600;">{{ $index + 1 }}</td>
                        <td>
                            <span class="type-tag type-tag-{{ $itemType }}">{{ strtoupper($itemType) }}</span>
                            <div style="font-weight: 700; margin-top: 2px;">{{ $item['account_number'] }}</div>
                        </td>
                        <td>{{ $item['client_name'] }}</td>
                        <td style="font-weight: 600;">{{ $item['instalment_no'] }}</td>
                        <td class="amount-cell">Rs. {{ number_format($item['due_amount'], 2) }}</td>
                        <td class="amount-cell">Rs. {{ number_format($item['paid_amount'], 2) }}</td>
                    </tr>
                    @endforeach
                    @if(!empty($receiptData['show_overdue']) && $receiptData['show_overdue'])
                    <tr>
                        <td colspan="5" style="text-align: right; font-weight: 700; color: #b91c1c;">Penalty / Overdue Charges</td>
                        <td class="amount-cell" style="color: #b91c1c; font-weight: 700;">Rs. {{ number_format($receiptData['overdue_amount'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td colspan="5" style="text-align: right;">Total Payable Amount</td>
                        <td class="amount-cell">Rs. {{ number_format($receiptData['total_amount_display'] ?? $receiptData['emi_amount'], 2) }}</td>
                    </tr>
                    <tr class="total-row" style="background-color: #e2e8f0 !important;">
                        <td colspan="5" style="text-align: right; color: {{ $primaryColor }};">Total Amount Paid</td>
                        <td class="amount-cell" style="color: {{ $primaryColor }}; font-size: 12px;">Rs. {{ number_format($receiptData['paid_amount'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
            @else
            <table class="payment-table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Description</th>
                        <th style="width: 30%;" class="amount-cell">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @if(($receiptData['module'] ?? '') === 'chit')
                    <tr>
                        <td style="font-weight: 600;">Installment Amount (Paid)</td>
                        <td class="amount-cell">Rs. {{ number_format($receiptData['principal_amount'] ?? $receiptData['paid_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Dividend / Discount Applied</td>
                        <td class="amount-cell">Nil</td>
                    </tr>
                    @else
                    <tr>
                        <td style="font-weight: 600;">Principal Amount (Paid)</td>
                        <td class="amount-cell">Rs. {{ number_format($receiptData['principal_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Interest / Charges Paid</td>
                        <td class="amount-cell">Rs. {{ number_format($receiptData['interest_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Adjustment of Fees</td>
                        <td class="amount-cell">Nil</td>
                    </tr>
                    @endif
                    @if(!empty($receiptData['show_overdue']) && $receiptData['show_overdue'])
                    <tr>
                        <td style="color: #b91c1c; font-weight: 600;">Penalty / Overdue Amount</td>
                        <td class="amount-cell" style="color: #b91c1c; font-weight: 600;">Rs. {{ number_format($receiptData['overdue_amount'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td style="text-align: right;">Total Amount Payable</td>
                        <td class="amount-cell">Rs. {{ number_format($receiptData['total_amount_display'] ?? $receiptData['emi_amount'], 2) }}</td>
                    </tr>
                    <tr class="total-row" style="background-color: #e2e8f0 !important;">
                        <td style="text-align: right; color: {{ $primaryColor }};">Total Amount Paid</td>
                        <td class="amount-cell" style="color: {{ $primaryColor }}; font-size: 12px;">Rs. {{ number_format($receiptData['paid_amount'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
            @endif
        </div>

        <!-- Footer Section -->
        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td style="width: 60%;">
                        <div style="font-size: 9px; color: #64748b;">
                            Thank you for your payment.<br>
                            This is a system generated document. No physical signature is required.
                        </div>
                    </td>
                    <td style="width: 40%;">
                        <div class="digital-signature">
                            <span class="sig-label">&#10004; Digitally Verified</span>
                            <span>{{ now()->format('d-m-Y h:i A') }}</span>
                        </div>
                    </td>
                </tr>
            </table>
            <div class="generated-line">
                Receipt generated automatically on {{ now()->format('d-m-Y h:i A') }}.
            </div>
        </div>
    </div>

</body>
</html>