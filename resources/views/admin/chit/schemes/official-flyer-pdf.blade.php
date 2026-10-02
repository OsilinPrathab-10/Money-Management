<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Official Scheme Flyer — {{ $scheme['name'] ?? 'Chit Scheme' }}</title>
  <style>
    @page { margin: 22px; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #2f3349;
      font-size: 10px;
    }
    h1 { margin: 0 0 2px; font-size: 16px; }
    .badge {
      display: inline-block;
      background: #666cff;
      color: #fff;
      font-size: 8px;
      font-weight: bold;
      letter-spacing: 0.6px;
      padding: 3px 8px;
      text-transform: uppercase;
    }
    .meta { color: #6d6f85; font-size: 9px; margin: 6px 0 12px; }
    .summary {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
      background: #f4f5fa;
      border: 1px solid #e5e7eb;
    }
    .summary td {
      padding: 6px 8px;
      border: none;
      font-size: 9px;
      width: 25%;
    }
    .summary .label { color: #6d6f85; }
    .summary .value { font-weight: bold; }
    table.schedule {
      width: 100%;
      border-collapse: collapse;
    }
    table.schedule th,
    table.schedule td {
      padding: 6px 7px;
      border: 1px solid #d9dbe5;
      text-align: left;
    }
    table.schedule th {
      background: #1e2f5c;
      color: #fff;
      font-weight: bold;
      font-size: 9px;
    }
    table.schedule td.month { text-align: center; width: 14%; }
    table.schedule td.amount { text-align: right; }
    table.schedule tr.foreman td { background: #e8f9ef; }
    table.schedule tfoot td {
      background: #f4f5fa;
      font-weight: bold;
    }
    .note { margin-top: 12px; font-size: 8px; color: #6d6f85; }
  </style>
</head>
<body>
  @php
    $freq = $scheme['installment_frequency'] ?? 'monthly';
    $periodLabel = match ($freq) {
      'weekly' => 'Week',
      'daily' => 'Day',
      default => 'Month',
    };
  @endphp
  @include('admin.reports.partials.download-branding')

  <div class="badge">Official Scheme Flyer</div>
  <h1>{{ $scheme['name'] ?? 'Chit Scheme' }}</h1>
  <div class="meta">
    @if (!empty($scheme['scheme_code']))
      Code {{ $scheme['scheme_code'] }} ·
    @endif
    Generated {{ $generatedAt->format('d M Y, h:i A') }} ·
    {{ count($rows) }} {{ \Illuminate\Support\Str::plural(strtolower($periodLabel), count($rows)) }}
  </div>

  <table class="summary">
    <tr>
      <td class="label">Chit Value</td>
      <td class="value">
        {{ isset($scheme['chit_value']) ? 'Rs. ' . number_format((float) $scheme['chit_value'], 2) : '—' }}
      </td>
      <td class="label">Members</td>
      <td class="value">{{ $scheme['total_members'] ?? '—' }}</td>
    </tr>
    <tr>
      <td class="label">Duration</td>
      <td class="value">
        {{ $scheme['duration_months'] }} ×
        {{ ucfirst($scheme['installment_frequency'] ?? 'monthly') }}
      </td>
      <td class="label">Commission</td>
      <td class="value">
        {{ isset($scheme['commission_pct']) ? rtrim(rtrim(number_format((float) $scheme['commission_pct'], 2), '0'), '.') . '%' : '—' }}
      </td>
    </tr>
    <tr>
      <td class="label">Installment</td>
      <td class="value">
        {{ isset($scheme['installment_amount']) && $scheme['installment_amount'] !== null
            ? 'Rs. ' . number_format((float) $scheme['installment_amount'], 2)
            : '—' }}
      </td>
      <td class="label">Foreman {{ $periodLabel }}</td>
      <td class="value">{{ $scheme['foreman_commission_month'] ?? '—' }}</td>
    </tr>
  </table>

  <table class="schedule">
    <thead>
      <tr>
        <th style="width: 14%;">No. ({{ $periodLabel }})</th>
        <th>Installment Amount (Rs.)</th>
        <th>Payout / Disbursement</th>
      </tr>
    </thead>
    <tbody>
      @php $totalInstallment = 0; $totalPayout = 0; @endphp
      @foreach ($rows as $row)
        @php
          $installment = $row['installment'];
          $payout      = $row['payout'];
          $payoutNumeric = is_numeric(str_replace([',', 'Rs.', '₹', ' '], '', (string) $payout))
              ? (float) str_replace([',', 'Rs.', '₹', ' '], '', (string) $payout)
              : null;

          $totalInstallment += $installment ?? 0;
          $totalPayout      += $payoutNumeric ?? 0;
        @endphp
        <tr class="{{ $row['is_foreman'] ? 'foreman' : '' }}">
          <td class="month">
            {{ $row['month'] }}
            @if ($row['is_foreman'])
              <div style="font-size: 7px; color: #0f9d58;">Foreman</div>
            @endif
          </td>
          <td class="{{ $installment === null ? 'blank' : 'amount' }}">
            {{ $installment !== null ? number_format($installment, 2) : '—' }}
          </td>
          <td class="{{ ($payout === null || trim((string) $payout) === '') ? 'blank' : ($payoutNumeric !== null ? 'amount' : '') }}">
            {{ $payoutNumeric !== null ? number_format($payoutNumeric, 2) : ($payout ?: '—') }}
          </td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td class="month">Total</td>
        <td class="amount">{{ number_format($totalInstallment, 2) }}</td>
        <td class="amount">{{ $totalPayout > 0 ? number_format($totalPayout, 2) : '—' }}</td>
      </tr>
    </tfoot>
  </table>

  <div class="note">
    This is the official scheme flyer / payout schedule. Foreman commission month is highlighted.
  </div>
</body>
</html>
