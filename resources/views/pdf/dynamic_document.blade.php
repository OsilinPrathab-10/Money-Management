@php
    use App\Helpers\AppearanceHelper;

    $primaryColor = AppearanceHelper::get('primary_color', '#696cff');
    $companyName = $company['name'] ?? AppearanceHelper::get('title', 'Loan App');
    $companyTagline = $company['subtitle'] ?? AppearanceHelper::get('subtitle', 'Professional Loan Services');

    $loanData = $loan ?? null;
    $clientData = $client
        ?? ($loanData->client ?? null)
        ?? ($loanData->loanApplication->client ?? null);

    $clientNameParts = array_filter([
        $clientData->first_name ?? null,
        $clientData->last_name ?? null,
    ]);

    $clientName = trim(implode(' ', $clientNameParts));
    if ($clientName === '') {
        $clientName = $clientData->client_name ?? ($clientData->name ?? 'Valued Client');
    }

    $applicationNumber = $loanData->application_number
        ?? ($loanData->loanApplication->application_number ?? null)
        ?? ($loanData->account_number ?? 'N/A');

    $registeredMobile = $clientData->phone ?? 'N/A';

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
        @page {
            margin: 100px 50px 80px 50px;
        }
        
        body {
            font-family: 'Noto Sans', 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #000;
        }

    header {
        width: 100%;
        margin-bottom: 20px;
    }

    .header-container {
        background: #fff;
        padding: 15px 0;
        width: 100%;
        border-bottom: 2px solid #ddd;
    }

    .header-left {
        float: left;
        width: 50%;
        text-align: left;
    }

    .header-right {
        float: right;
        width: 50%;
        text-align: right;
    }

    .application-number {
        font-size: 12px;
        font-weight: 600;
        color: #333;
        margin: 0 0 4px 0;
        line-height: 1.2;
    }

    .application-date {
        font-size: 13px;
        color: {{ $primaryColor }};
        margin: 0;
        line-height: 1.4;
        font-weight: 600;
    }

    .application-time {
        font-size: 13px;
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
        font-size: 14px;
        font-weight: 700;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        display: inline-block;
    }

    .logo-text {
        font-size: 12px;
        font-weight: 700;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        display: inline-block;
    }

    footer {
        position: fixed;
        bottom: -40px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 10px;
        color: #666;
        padding: 10px 0;
    }

    .footer-content {
        display: block;
        width: 100%;
    }

    .footer-consent {
        margin-top: 4px;
        font-size: 10px;
        font-weight: 600;
        color: #2f2f2f;
    }

    main {
        margin-top: 6px;
    }

    .document-wrapper {
        border: none;
        border-radius: 0;
        padding: 20px 32px 36px;
        background: #ffffff;
        box-shadow: none;
    }

    .document-title {
        text-align: center;
        font-size: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 12px 0 20px 0;
        color: #222;
    }

    .content-section {
        margin-top: 10px;
    }

    .content-section p {
        margin-bottom: 12px;
    }

    .content-section ul {
        margin: 0 0 12px 18px;
        padding: 0;
    }

    .content-section li {
        margin-bottom: 6px;
    }

    .content-section table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        font-size: 12px;
    }

    .content-section table th,
    .content-section table td {
        padding: 8px 10px;
        border: 1px solid #d9dde7;
        text-align: left;
    }

    .content-section table thead td,
    .content-section table thead th {
        background-color: #f5f5f5;
        font-weight: 700;
        text-align: center;
        border: 1px solid #ccc;
        padding: 10px 8px;
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

  <header>
    <div class="header-container">
      <div class="header-left">
        <p class="application-date">Date : {{ $generatedDate }}</p>
        <p class="application-time">Time : {{ $generatedTime }}</p>
      </div>
      <div class="header-right">
        <div class="logo-wrapper">
          @if(!empty($logo))
            <img src="{{ $logo }}" alt="Logo" class="logo-img" onerror="this.style.display='none';">
          @else
            <span class="logo-text">{{ $companyName }}</span>
          @endif
          <div class="logo-title">{{ $companyName }}</div>
        </div>
      </div>
    </div>
  </header>

  <main>
      <div class="document-wrapper">
          @if(!empty($title))
            <div class="document-title">{{ $title }}</div>
          @endif

          <div class="content-section">
              {!! $body !!}
          </div>
      </div>
  </main>

</body>
</html>
