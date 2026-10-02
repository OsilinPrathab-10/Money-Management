<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FD Certificate — {{ $deposit->fd_number }}</title>
    <style>
        @page { margin: 15mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #2f3349;
            margin: 0;
            padding: 24px;
            background: #f4f5fa;
        }
        .print-bar {
            text-align: center;
            margin-bottom: 16px;
        }
        .print-bar button, .print-bar a {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 4px;
            border: 1px solid #666cff;
            background: #666cff;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .print-bar a.secondary { background: #fff; color: #666cff; }
        .certificate {
            border: 3px double #666cff;
            background: #fff;
            padding: 0;
            max-width: 820px;
            margin: 0 auto;
            overflow: hidden;
        }
        .cert-top-band {
            background: linear-gradient(135deg, #666cff 0%, #5a5fd4 100%);
            color: #fff;
            padding: 20px 28px;
            text-align: center;
        }
        .cert-top-band .company-logo {
            max-height: 56px;
            max-width: 140px;
            margin-bottom: 10px;
            filter: brightness(0) invert(1);
        }
        .cert-top-band .company-name {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: .5px;
        }
        .cert-top-band .company-slogan {
            font-size: 11px;
            opacity: .9;
            font-style: italic;
            margin-bottom: 10px;
        }
        .company-details {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px 18px;
            font-size: 10px;
            opacity: .95;
            line-height: 1.5;
        }
        .company-details span::before {
            content: '•';
            margin-right: 6px;
            opacity: .6;
        }
        .company-details span:first-child::before { content: ''; margin: 0; }
        .cert-body {
            padding: 24px 32px 28px;
        }
        .cert-title-row {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #e7e7ef;
        }
        .cert-title-row h2 {
            margin: 0 0 6px;
            font-size: 17px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #666cff;
        }
        .cert-title-row .subtitle {
            color: #6d6f85;
            font-size: 12px;
        }
        .fd-number-box {
            text-align: center;
            background: #f8f8fb;
            border: 1px dashed #666cff;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .fd-number-box .lbl {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6d6f85;
        }
        .fd-number-box .num {
            font-size: 20px;
            font-weight: bold;
            color: #666cff;
            margin-top: 4px;
        }
        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        table.info th {
            background: #666cff;
            color: #fff;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 8px 10px;
            text-align: left;
        }
        table.info td {
            padding: 8px 10px;
            border-bottom: 1px solid #ececf2;
            font-size: 12px;
            vertical-align: top;
        }
        table.info tr:nth-child(even) td { background: #fafafc; }
        table.info td.label {
            color: #6d6f85;
            width: 36%;
            font-weight: 500;
        }
        table.info td.value { font-weight: 600; }
        .amounts {
            display: flex;
            gap: 10px;
            margin: 18px 0;
        }
        .amount-box {
            flex: 1;
            border: 1px solid #e5e7eb;
            background: #f8f8fb;
            padding: 12px 8px;
            text-align: center;
            border-radius: 6px;
        }
        .amount-box .lbl {
            font-size: 10px;
            color: #6d6f85;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .amount-box .val {
            font-size: 15px;
            font-weight: bold;
            margin-top: 4px;
            color: #2f3349;
        }
        .amount-box.maturity {
            background: rgba(102, 108, 255, .08);
            border-color: #666cff;
        }
        .amount-box.maturity .val { color: #666cff; }
        .terms {
            font-size: 10px;
            color: #6d6f85;
            line-height: 1.55;
            padding: 12px 14px;
            background: #f8f8fb;
            border-radius: 6px;
            margin-top: 16px;
        }
        .footer {
            margin-top: 32px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 11px;
        }
        .sign {
            text-align: center;
            flex: 1;
        }
        .sign .line {
            border-top: 1px solid #2f3349;
            margin-top: 52px;
            padding-top: 6px;
            font-weight: 600;
        }
        .sign .sub {
            font-size: 10px;
            color: #6d6f85;
            margin-top: 2px;
        }
        .cert-footer-band {
            background: #f8f8fb;
            border-top: 1px solid #e7e7ef;
            padding: 12px 28px;
            text-align: center;
            font-size: 10px;
            color: #6d6f85;
        }
        .reg-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px 16px;
            margin-top: 8px;
            font-size: 10px;
        }
        .reg-badges span {
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 3px 10px;
            border-radius: 20px;
        }
        @media print {
            .print-bar { display: none !important; }
            body { padding: 0; background: #fff; }
        }
        @media (max-width: 600px) {
            .amounts { flex-direction: column; }
            .cert-body { padding: 16px; }
        }
    </style>
</head>
<body>
    @php
        $companyName = $reportBranding['name'] ?? ($company?->company_name ?? config('app.name'));
        $address = $reportBranding['address'] ?? collect([
            $company?->address_line1,
            $company?->address_line2,
            $company?->city,
            $company?->state,
            $company?->pincode,
            $company?->country,
        ])->filter()->implode(', ');
    @endphp

    <div class="print-bar">
        <button type="button" onclick="window.print()">Print Certificate</button>
        <a class="secondary" href="{{ route('fd.deposits.show', $deposit) }}">Back to FD</a>
    </div>

    <div class="certificate">
        {{-- Company header --}}
        <div class="cert-top-band">
            @if(!empty($reportBranding['logo']))
                <img src="{{ $reportBranding['logo'] }}" alt="{{ $companyName }}" class="company-logo">
            @endif
            <h1 class="company-name">{{ $companyName }}</h1>
            @if(!empty($company?->company_slogan))
                <div class="company-slogan">{{ $company->company_slogan }}</div>
            @endif
            <div class="company-details">
                @if($address)
                    <span>{{ $address }}</span>
                @endif
                @if(!empty($company?->company_mobile))
                    <span>Phone: {{ $company->company_mobile }}@if($company->alternate_mobile) / {{ $company->alternate_mobile }}@endif</span>
                @endif
                @if(!empty($company?->company_email))
                    <span>Email: {{ $company->company_email }}</span>
                @endif
                @if(!empty($company?->website_url))
                    <span>Web: {{ $company->website_url }}</span>
                @endif
            </div>
        </div>

        <div class="cert-body">
            <div class="cert-title-row">
                <h2>Fixed Deposit Certificate</h2>
                <div class="subtitle">This is to certify that the following Fixed Deposit is held with {{ $companyName }}</div>
            </div>

            <div class="fd-number-box">
                <div class="lbl">Certificate / FD Number</div>
                <div class="num">{{ $deposit->fd_number }}</div>
            </div>

            <table class="info">
                <thead>
                    <tr>
                        <th colspan="2">Depositor & Scheme Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="label">Depositor Name</td>
                        <td class="value">{{ $deposit->client->client_name ?? '—' }}</td>
                    </tr>
                    @if(!empty($deposit->client?->client_phone))
                    <tr>
                        <td class="label">Mobile Number</td>
                        <td class="value">{{ $deposit->client->client_phone }}</td>
                    </tr>
                    @endif
                    @if(!empty($deposit->client?->address))
                    <tr>
                        <td class="label">Address</td>
                        <td class="value">{{ $deposit->client->address }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="label">Scheme</td>
                        <td class="value">{{ $deposit->scheme->name ?? '—' }} ({{ $deposit->scheme->scheme_code ?? '' }})</td>
                    </tr>
                    <tr>
                        <td class="label">Deposit Date</td>
                        <td class="value">{{ optional($deposit->deposit_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Start Date</td>
                        <td class="value">{{ optional($deposit->start_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Maturity Date</td>
                        <td class="value">{{ optional($deposit->maturity_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Tenure</td>
                        <td class="value">{{ $deposit->tenure }} {{ ucfirst($deposit->tenure_type) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Interest Rate</td>
                        <td class="value">{{ number_format((float) $deposit->interest_rate, 2) }}% p.a. ({{ $deposit->interest_type_label }})</td>
                    </tr>
                    <tr>
                        <td class="label">Compounding</td>
                        <td class="value">{{ ucfirst(str_replace('_', ' ', (string) $deposit->interest_frequency)) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Nominee</td>
                        <td class="value">
                            {{ $deposit->nominee_name ?: '—' }}
                            @if($deposit->nominee_relation) ({{ $deposit->nominee_relation }}) @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Payout Option</td>
                        <td class="value">{{ $deposit->payout_option_label }}</td>
                    </tr>
                    <tr>
                        <td class="label">Status</td>
                        <td class="value">{{ $deposit->status_label }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="amounts">
                <div class="amount-box">
                    <div class="lbl">Principal Amount</div>
                    <div class="val">₹{{ number_format((float) $deposit->deposit_amount, 2) }}</div>
                </div>
                <div class="amount-box">
                    <div class="lbl">Interest Amount</div>
                    <div class="val">₹{{ number_format((float) $deposit->interest_amount, 2) }}</div>
                </div>
                <div class="amount-box maturity">
                    <div class="lbl">Maturity Amount</div>
                    <div class="val">₹{{ number_format((float) $deposit->maturity_amount, 2) }}</div>
                </div>
            </div>

            <div class="terms">
                This certificate is issued subject to the terms and conditions of the Fixed Deposit scheme.
                Interest is calculated as per the scheme rules at the time of booking.
                Premature withdrawal, if permitted, shall be governed by the applicable penalty policy.
                This document must be presented along with valid identity proof for any claim or settlement.
            </div>

            <div class="footer">
                <div class="sign">
                    <div class="line">Authorized Signatory</div>
                    <div class="sub">{{ $companyName }}</div>
                </div>
                <div class="sign">
                    <div class="line">Depositor / Nominee</div>
                    <div class="sub">{{ $deposit->client->client_name ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="cert-footer-band">
            <div>
                Issued on <strong>{{ now()->format('d M Y, h:i A') }}</strong>
                @if($deposit->creator) · Issued by <strong>{{ $deposit->creator->name }}</strong> @endif
            </div>
            @if($company && ($company->gst_number || $company->pan_number || $company->cin_number))
            <div class="reg-badges">
                @if($company->gst_number)<span>GST: {{ $company->gst_number }}</span>@endif
                @if($company->pan_number)<span>PAN: {{ $company->pan_number }}</span>@endif
                @if($company->cin_number)<span>CIN: {{ $company->cin_number }}</span>@endif
            </div>
            @endif
            @if(!empty($company?->support_email) || !empty($company?->support_mobile))
            <div style="margin-top:6px;">
                Support:
                @if($company->support_email) {{ $company->support_email }} @endif
                @if($company->support_mobile) · {{ $company->support_mobile }} @endif
            </div>
            @endif
            @if(!empty($company?->working_hours))
            <div style="margin-top:4px;">Working Hours: {{ $company->working_hours }}</div>
            @endif
        </div>
    </div>
</body>
</html>
