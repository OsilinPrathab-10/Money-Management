<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settlement Consent &amp; Promissory Note — {{ $memberName }}</title>
  <style>
    @page { margin: 18mm 16mm; }
    * { box-sizing: border-box; }
    body {
      font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
      color: #2f3349;
      font-size: 11px;
      line-height: 1.45;
      margin: 0;
      padding: {{ ($mode ?? 'pdf') === 'print' ? '16px' : '0' }};
      background: #fff;
    }
    .toolbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: flex-end;
      padding: 10px 12px;
      margin: -16px -16px 16px;
      background: #f4f5fa;
      border-bottom: 1px solid #d9dbe5;
    }
    .toolbar a, .toolbar button {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 6px;
      border: 1px solid transparent;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      font-family: inherit;
    }
    .btn-print { background: #696cff; color: #fff; }
    .btn-pdf { background: #28c76f; color: #fff; }
    .btn-back { background: #fff; color: #2f3349; border-color: #d9dbe5; }
    .doc { max-width: 820px; margin: 0 auto; }
    h1 {
      margin: 0 0 4px;
      font-size: 16px;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .subtitle {
      text-align: center;
      color: #6d6f85;
      font-size: 10px;
      margin-bottom: 14px;
    }
    .meta-row {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
    }
    .meta-row td {
      padding: 4px 0;
      font-size: 11px;
      width: 50%;
    }
    h2 {
      margin: 14px 0 8px;
      font-size: 12px;
      border-bottom: 1px solid #d9dbe5;
      padding-bottom: 4px;
      color: #696cff;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    .grid {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8px;
    }
    .grid td {
      padding: 5px 6px;
      vertical-align: top;
      border: 1px solid #e5e7eb;
      font-size: 10.5px;
    }
    .grid td.label {
      width: 34%;
      background: #f8f8fc;
      color: #6d6f85;
      font-weight: bold;
    }
    .amount-box {
      border: 1px solid #c7c9d9;
      background: #f8f8fc;
      padding: 10px 12px;
      margin: 10px 0;
    }
    .amount-box p { margin: 4px 0; }
    .amount-box strong { color: #2f3349; }
    ol { margin: 6px 0 6px 18px; padding: 0; }
    ol li { margin-bottom: 4px; }
    p { margin: 0 0 8px; text-align: justify; }
    .sign-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    .sign-table td {
      width: 50%;
      vertical-align: top;
      padding: 8px 10px 8px 0;
      font-size: 10.5px;
    }
    .sign-line {
      margin-top: 28px;
      border-top: 1px solid #2f3349;
      width: 85%;
      padding-top: 4px;
    }
    .witness-box {
      border: 1px solid #e5e7eb;
      padding: 8px 10px;
      margin-bottom: 8px;
      min-height: 78px;
    }
    .witness-box .title {
      font-weight: bold;
      margin-bottom: 6px;
      color: #696cff;
    }
    .footer-note {
      margin-top: 14px;
      font-size: 9px;
      color: #6d6f85;
      text-align: center;
    }
    @media print {
      .toolbar { display: none !important; }
      body { padding: 0; }
      a { color: inherit; text-decoration: none; }
    }
  </style>
</head>
<body>
@if(($mode ?? 'pdf') === 'print')
  <div class="toolbar">
    <a class="btn-back" href="{{ $backUrl ?? url()->previous() }}">← Back</a>
    <a class="btn-pdf" href="{{ $pdfUrl }}">Download PDF</a>
    <button type="button" class="btn-print" onclick="window.print()">Print</button>
  </div>
@endif

<div class="doc">
  @include('admin.reports.partials.download-branding', ['reportBranding' => $reportBranding])

  <h1>Chit Fund Settlement Consent and Promissory Note</h1>
  <div class="subtitle">Full and final settlement acknowledgement for chit membership</div>

  <table class="meta-row">
    <tr>
      <td><strong>Date:</strong> {{ $noteDate }}</td>
      <td><strong>Place:</strong> {{ $place }}</td>
    </tr>
    <tr>
      <td><strong>Payout Ref:</strong> {{ $payoutCode }}</td>
      <td><strong>Settlement Month:</strong> {{ $monthLabel }}</td>
    </tr>
  </table>

  <h2>Member details</h2>
  <table class="grid">
    <tr>
      <td class="label">Member name</td>
      <td>{{ $memberName }}</td>
    </tr>
    <tr>
      <td class="label">Customer ID</td>
      <td>{{ $customerId }}</td>
    </tr>
    <tr>
      <td class="label">Chit group name / number</td>
      <td>{{ $groupLabel }}</td>
    </tr>
    <tr>
      <td class="label">Chit value</td>
      <td>₹{{ number_format((float) $chitValue, 2) }}</td>
    </tr>
    <tr>
      <td class="label">Ticket / Member number</td>
      <td>{{ $ticketNumber }}</td>
    </tr>
    <tr>
      <td class="label">Address</td>
      <td>{{ $memberAddress }}</td>
    </tr>
  </table>

  <h2>Settlement details</h2>
  <p>
    I, <strong>{{ $memberName }}</strong>, am a member of the above-mentioned chit group conducted by
    <strong>{{ $companyName }}</strong>.
  </p>
  <p>
    I hereby confirm that the chit account has been settled based on the mutual agreement between myself and the company.
    The settlement has been explained to me in detail, including the calculation of installments paid, dividends,
    foreman commission, auction adjustment, penalties (if any), interest adjustment (if any), and the final settlement amount.
  </p>

  <div class="amount-box">
    <p>The final settlement amount agreed between both parties is:</p>
    <p><strong>Settlement amount:</strong> ₹{{ number_format((float) $settlementAmount, 2) }}</p>
    <p><strong>Amount received by me:</strong> ₹{{ number_format((float) $amountReceived, 2) }}</p>
    <p><strong>Outstanding amount (if any):</strong> ₹{{ number_format((float) $outstandingAmount, 2) }}</p>
  </div>

  <p>
    I acknowledge that I have understood the settlement calculation and voluntarily accept the above settlement amount
    as full and final settlement of my chit account.
  </p>

  <h2>Consent declaration</h2>
  <p>I hereby declare and agree that:</p>
  <ol>
    <li>I have received the settlement amount mentioned above to my full satisfaction.</li>
    <li>I have no objection to the settlement calculation prepared by the company.</li>
    <li>I shall not make any future claim, demand, dispute, or legal action regarding this chit account after this settlement.</li>
    <li>I voluntarily execute this settlement consent without any force, coercion, or undue influence.</li>
    <li>This settlement shall be treated as full and final settlement of the above chit membership.</li>
  </ol>

  <h2>Promissory undertaking</h2>
  <p>
    In case any amount remains payable by me to the company after settlement, I hereby unconditionally promise to pay
    the outstanding amount of
    <strong>₹{{ number_format((float) $outstandingAmount, 2) }} (Rupees {{ $outstandingAmountInWords }} only)</strong>
    to <strong>{{ $companyName }}</strong> on demand or within the period agreed by the company.
  </p>
  <p>
    I agree that in the event of default, the company shall have the right to recover the outstanding amount along with
    applicable interest, legal expenses, and other recovery charges as permitted under the applicable laws and the chit agreement.
  </p>
  <p>
    I further undertake that this promissory note shall remain valid until the outstanding amount is fully discharged.
  </p>

  <h2>Declaration</h2>
  <p>
    I confirm that I have read and understood the contents of this Settlement Consent and Promissory Note.
    The contents have been explained to me in my preferred language, and I am signing this document voluntarily.
  </p>

  <table class="sign-table">
    <tr>
      <td>
        <div class="sign-line">Member signature</div>
        <div style="margin-top:8px;"><strong>Member name:</strong> {{ $memberName }}</div>
        <div><strong>Mobile number:</strong> {{ $memberMobile }}</div>
        <div><strong>Aadhaar / PAN:</strong> {{ $idProof }}</div>
      </td>
      <td>
        <div class="sign-line">Authorized signatory</div>
        <div style="margin-top:8px;"><strong>For {{ $companyName }}</strong></div>
        <div><strong>Name:</strong> {{ $authorizedName }}</div>
        <div><strong>Designation:</strong> {{ $authorizedDesignation }}</div>
        <div>Signature &amp; seal</div>
      </td>
    </tr>
  </table>

  <h2>Witnesses</h2>
  <table class="sign-table">
    <tr>
      <td>
        <div class="witness-box">
          <div class="title">Witness 1</div>
          <div>Name: _______________________</div>
          <div style="margin-top:16px;">Signature: _______________________</div>
          <div style="margin-top:16px;">Address: _______________________</div>
        </div>
      </td>
      <td>
        <div class="witness-box">
          <div class="title">Witness 2</div>
          <div>Name: _______________________</div>
          <div style="margin-top:16px;">Signature: _______________________</div>
          <div style="margin-top:16px;">Address: _______________________</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="footer-note">
    Generated on {{ $generatedAt }} · {{ $companyName }}
    @if(!empty($reportBranding['slogan'])) — {{ $reportBranding['slogan'] }} @endif
  </div>
</div>
</body>
</html>
