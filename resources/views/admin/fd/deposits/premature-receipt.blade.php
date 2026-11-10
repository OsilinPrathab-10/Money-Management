<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premature Closure Receipt — {{ $deposit->fd_number }}</title>
    
    @php
        use App\Helpers\SettingsHelper;

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

        $brandTitle = SettingsHelper::get('admin_title') ?: config('variables.templateName', 'Codepluse');
        $brandSubtitle = SettingsHelper::get('admin_subtitle') ?: config('variables.templateSuffix', 'Society & Finance');
        $footerText = SettingsHelper::get('footer_text') ?: 'Thank you for your business.';

        $payableAmount = (float) ($deposit->closure_amount ?? ($txn->amount ?? 0));
        $closureDate = optional($deposit->closure_date)->format('d-m-Y') ?? optional($txn->created_at ?? null)->format('d-m-Y') ?? now()->format('d-m-Y');
        $closureTime = optional($txn->created_at ?? null)->format('h:i A') ?? now()->format('h:i A');
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

        .top-accent-bar {
            height: 6px;
            background: {{ $primaryColor }};
            width: 100%;
        }

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
            color: {{ $primaryColor }};
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
            background: #b91c1c;
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

        .text-right { text-align: right; }

        .amount-cell {
            text-align: right;
            font-weight: 700;
            font-family: 'DejaVu Sans Mono', monospace, Arial;
            color: #0f172a;
        }

        .grand-total-row td {
            background-color: #fef2f2 !important;
            border-top: 2px solid #b91c1c !important;
            font-size: 12px;
            font-weight: 800;
        }

        .grand-total-label {
            color: #b91c1c;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grand-total-amount {
            color: #b91c1c;
            font-size: 13px;
            font-weight: 800;
        }

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
            color: #b91c1c;
            font-style: italic;
        }

        .footer-section {
            padding: 12px 20px 16px 20px;
            border-top: 1.5px solid #e2e8f0;
            background: #ffffff;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
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
            color: {{ $primaryColor }};
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 10px;
            display: block;
            margin-bottom: 2px;
        }

        .bottom-audit-bar {
            text-align: center;
            font-size: 8.5px;
            color: #94a3b8;
            padding: 6px 20px;
            border-top: 1px solid #f1f5f9;
            background: #fafafa;
        }

        @media screen {
            body {
                background: #f1f5f9;
                padding: 24px 15px 48px;
            }

            .screen-toolbar {
                max-width: 800px;
                margin: 0 auto 14px auto;
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
                background: #fee2e2;
                color: #b91c1c;
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
                text-decoration: none;
            }

            .btn-print {
                background: {{ $primaryColor }};
                color: #ffffff;
            }

            .btn-back {
                background: #e2e8f0;
                color: #334155;
            }

            .receipt-container {
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            }
        }

        @media print {
            body { padding: 0 !important; background: #ffffff !important; }
            .screen-toolbar { display: none !important; }
            .receipt-container { border: 1px solid #94a3b8 !important; box-shadow: none !important; border-radius: 0 !important; }
        }
    </style>
</head>
<body>

    <!-- Screen Preview Toolbar -->
    <div class="screen-toolbar">
        <div class="toolbar-inner">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="toolbar-badge">Premature Withdrawal</span>
                <span class="toolbar-id">{{ $deposit->fd_number }}</span>
            </div>
            <div class="toolbar-right">
                <button type="button" onclick="window.print()" class="btn-action btn-print">
                    Print Receipt
                </button>
                <a href="{{ route('fd.deposits.show', $deposit) }}" class="btn-action btn-back">
                    Back to FD
                </a>
            </div>
        </div>
    </div>

    <!-- Main Receipt Document Container -->
    <div class="receipt-container">
        <div class="top-accent-bar" style="background: #b91c1c;"></div>

        <!-- Header Table -->
        <table class="header-table">
            <tr>
                <td style="width: 58%;">
                    <table style="border-collapse: collapse; border: none;">
                        <tr>
                            @if(!empty($brandLogo))
                            <td style="padding: 0 12px 0 0; vertical-align: middle;">
                                <img src="{{ $brandLogo }}" alt="{{ $brandTitle }}" class="logo-img" onerror="this.style.display='none'">
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
                    <div class="receipt-title-badge">PREMATURE WITHDRAWAL</div>
                    <div class="receipt-no-display">{{ $deposit->fd_number }}</div>
                    <div class="receipt-date-sub">
                        Closure Date: {{ $closureDate }} · {{ $closureTime }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Overview Details Grid -->
        <div class="details-wrapper">
            <table class="details-table">
                <tr>
                    <td class="details-col-left">
                        <table class="meta-list-table">
                            <tr>
                                <td class="meta-label">FD Number:</td>
                                <td class="meta-value">{{ $deposit->fd_number }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Customer Name:</td>
                                <td class="meta-value">{{ $deposit->client->client_name ?? '—' }}</td>
                            </tr>
                            @if(!empty($deposit->client->client_phone))
                            <tr>
                                <td class="meta-label">Contact No:</td>
                                <td class="meta-value">{{ $deposit->client->client_phone }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="meta-label">Scheme / Plan:</td>
                                <td class="meta-value">{{ $deposit->scheme->name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Original Principal:</td>
                                <td class="meta-value">₹{{ number_format((float) $deposit->deposit_amount, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="details-col-divider"></td>
                    <td class="details-col-right">
                        <table class="meta-list-table">
                            <tr>
                                <td class="meta-label">Closure Date:</td>
                                <td class="meta-value">{{ $closureDate }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Payment Mode:</td>
                                <td class="meta-value" style="text-transform: uppercase;">{{ $deposit->closure_payment_mode ?: '—' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Payout Option:</td>
                                <td class="meta-value">{{ $deposit->payout_option_label }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Reference ID:</td>
                                <td class="meta-value">{{ $deposit->closure_transaction_ref ?: ($txn->description ?? '—') }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Status:</td>
                                <td class="meta-value">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 9px; font-weight: 800; background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;">
                                        {{ $deposit->status_label }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Particulars Table Section -->
        <div class="table-section">
            <div class="section-header-title">Closure Particulars & Payout Settlement</div>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Particulars / Description</th>
                        <th style="width: 25%;">Component</th>
                        <th style="width: 25%;" class="text-right">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div style="font-weight: 700;">Deposit Principal Amount</div>
                            <div style="font-size: 9.5px; color: #64748b;">Original fixed deposit amount</div>
                        </td>
                        <td>Principal</td>
                        <td class="amount-cell">₹{{ number_format((float) $deposit->deposit_amount, 2) }}</td>
                    </tr>
                    @if(!empty($txn->interest_amount) && (float)$txn->interest_amount > 0)
                    <tr>
                        <td>
                            <div style="font-weight: 700;">Premature Interest Accrued / Payable</div>
                            <div style="font-size: 9.5px; color: #64748b;">Interest earned till early withdrawal date</div>
                        </td>
                        <td>Interest</td>
                        <td class="amount-cell" style="color: #15803d;">+ ₹{{ number_format((float) $txn->interest_amount, 2) }}</td>
                    </tr>
                    @endif
                    @if(!empty($txn->penalty_amount) && (float)$txn->penalty_amount > 0)
                    <tr>
                        <td style="color: #b91c1c;">
                            <div style="font-weight: 700;">Premature Withdrawal Penalty / Fee</div>
                            <div style="font-size: 9.5px; color: #b91c1c;">Penalty deducted for breaking deposit before tenure</div>
                        </td>
                        <td style="color: #b91c1c;">Penalty</td>
                        <td class="amount-cell" style="color: #b91c1c;">- ₹{{ number_format((float) $txn->penalty_amount, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total-row">
                        <td colspan="2" class="text-right grand-total-label">Net Payable / Paid Amount</td>
                        <td class="amount-cell grand-total-amount">₹{{ number_format($payableAmount, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Amount in Words -->
        <div class="words-box">
            <table class="words-table">
                <tr>
                    <td class="words-label">Amount in Words:</td>
                    <td class="words-value">{{ numberToIndianWords($payableAmount) }}</td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <table class="footer-table">
                <tr>
                    <td style="width: 58%;">
                        <div class="footer-terms">
                            <strong>{{ $footerText }}</strong><br>
                            This is a computer-generated premature closure receipt. No physical signature is required.
                        </div>
                    </td>
                    <td style="width: 42%; text-align: right;">
                        <div class="digital-seal-box">
                            <span class="seal-verified-text">&#10004; Digitally Verified</span>
                            <span style="font-size: 9px; color: #64748b; font-family: monospace;">{{ now()->format('d-m-Y h:i A') }} IST</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bottom-audit-bar">
            FD Number: {{ $deposit->fd_number }} · Processed on {{ now()->format('d-m-Y h:i:s A') }} IST
        </div>
    </div>
</body>
</html>
