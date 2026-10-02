@php
    use App\Helpers\AppearanceHelper;

    $primaryColor = AppearanceHelper::get('primary_color', '#696cff');
    $companyName = $company['name'] ?? AppearanceHelper::get('title', 'Loan App');
    $companyTagline = $company['subtitle'] ?? AppearanceHelper::get('subtitle', 'Professional Loan Services');

    $loanData = $loan ?? null;
    $clientData = $client
        ?? ($loanData->client ?? null)
        ?? ($loanData?->loanApplication?->client ?? null);

    $clientNameParts = array_filter([
        $clientData?->first_name ?? null,
        $clientData?->last_name ?? null,
    ]);

    $clientName = trim(implode(' ', $clientNameParts));
    if ($clientName === '') {
        $clientName = $clientData?->client_name ?? ($clientData?->name ?? 'Valued Client');
    }

    $applicationNumber = $loanData->application_number
        ?? ($loanData?->loanApplication?->application_number ?? null)
        ?? ($loanData->account_number ?? 'N/A');

    $registeredMobile = $clientData?->client_phone
        ?? $clientData?->phone
        ?? $clientData?->mobile_no
        ?? 'N/A';

    $clientIp = request()->ip() ?? 'N/A';

    $generatedAt = now();
    $consentTimestamp = $generatedAt->format('d-m-Y h:i A');
    $generatedDate = $generatedAt->format('d-m-Y');
    $generatedTime = $generatedAt->format('h:i A');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        body {
            font-family: 'Noto Sans', 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            color: #000;
        }

    header {
        width: 100%;
        margin-bottom: 15px;
    }

    .header-container {
        background: #fff;
        padding: 10px 0;
        width: 100%;
        border-bottom: 2px solid #ddd;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
    }

    .header-left {
        width: 50%;
        text-align: left;
        vertical-align: top;
    }

    .header-right {
        width: 50%;
        text-align: right;
        vertical-align: top;
    }

    .application-number {
        font-size: 11px;
        font-weight: 600;
        color: #333;
        margin: 0 0 4px 0;
        line-height: 1.2;
    }

    .application-date {
        font-size: 12px;
        color: {{ $primaryColor }};
        margin: 0;
        line-height: 1.4;
        font-weight: 600;
    }

    .application-time {
        font-size: 12px;
        color: {{ $primaryColor }};
        margin: 4px 0 0 0;
        line-height: 1.4;
        font-weight: 600;
    }

    .logo-wrapper {
        display: inline-block;
        text-align: right;
    }

    .logo-img {
        max-height: 35px;
        max-width: 120px;
        display: inline-block;
        vertical-align: middle;
    }

    .logo-title {
        font-size: 12px;
        font-weight: 600;
        color: #333;
        margin-top: 4px;
        text-align: right;
    }

    .logo-text {
        font-size: 12px;
        font-weight: 700;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        display: inline-block;
    }

    .document-footer {
        text-align: center;
        font-size: 9px;
        color: #666;
        padding: 8px 0 0 0;
        margin-top: 16px;
        border-top: 1px solid #ddd;
    }

    .footer-content {
        display: block;
        width: 100%;
    }

    .footer-consent {
        margin-top: 4px;
        font-size: 9px;
        font-weight: 600;
        color: #2f2f2f;
    }

    main {
        margin-top: 6px;
    }

    .document-wrapper {
        border: none;
        border-radius: 0;
        padding: 10px 15px 20px;
        background: #ffffff;
        box-shadow: none;
    }

    .document-title {
        text-align: center;
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 8px 0 15px 0;
        color: #222;
    }

    .content-section {
        margin-top: 10px;
    }

    .content-section p {
        margin-bottom: 10px;
    }

    .content-section ul {
        margin: 0 0 10px 18px;
        padding: 0;
    }

    .content-section li {
        margin-bottom: 4px;
    }

    .content-section table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
        font-size: 9px;
        table-layout: auto;
        word-wrap: break-word;
    }

    .content-section table th,
    .content-section table td {
        padding: 6px 8px;
        border: 1px solid #d9dde7;
        text-align: left;
        word-break: break-word;
        mso-number-format: "\@";
    }

    .content-section table thead td,
    .content-section table thead th {
        background-color: #666cff;
        color: #ffffff;
        font-weight: 700;
        text-align: center;
        border: 1px solid #5256cc;
        padding: 8px 6px;
    }

    .content-section table tbody td {
        text-align: center;
        border: 1px solid #ddd;
    }

    .content-section table tfoot td {
        background-color: #f9f9f9;
        font-weight: 700;
        border: 1px solid #ccc;
    }
  </style>
</head>
<body>

  <div class="header-container">
    <table class="header-table">
      <tr>
        <td class="header-left">
          <p class="application-date">Date : {{ $generatedDate }}</p>
          <p class="application-time">Time : {{ $generatedTime }}</p>
        </td>
        <td class="header-right">
          <div class="logo-wrapper">
            @if(!empty($reportBranding['logo'] ?? $logo))
              <img src="{{ $reportBranding['logo'] ?? $logo }}" alt="Logo" class="logo-img" width="120" height="40">
            @else
              <span class="logo-text">{{ $reportBranding['name'] ?? $companyName }}</span>
            @endif
            <div class="logo-title">{{ $reportBranding['name'] ?? $companyName }}</div>
            @if(!empty($reportBranding['address']))
              <div style="max-width: 260px; margin-top: 3px; font-size: 9px; line-height: 1.3; color: #666; text-align: right;">
                {{ $reportBranding['address'] }}
              </div>
            @endif
          </div>
        </td>
      </tr>
    </table>
  </div>

  <div class="document-wrapper">
      @if(!empty($title))
        <div class="document-title">{{ $title }}</div>
      @endif

      @if(!empty($header))
        <div class="content-section">{!! $header !!}</div>
      @endif

      <div class="content-section">
          {!! $body !!}
      </div>
  </div>

</body>
</html>
