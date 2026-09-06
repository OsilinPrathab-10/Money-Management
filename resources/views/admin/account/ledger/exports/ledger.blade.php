@php
  $mode = $exportMode ?? 'pdf';
  $rows = $rows ?? [];
  $totals = $totals ?? [];
  $fromDate = $fromDate ?? ($filters['from_date'] ?? '');
  $toDate = $toDate ?? ($filters['to_date'] ?? '');
  $status = $filters['status'] ?? ($status ?? '');
@endphp

@if ($mode === 'csv')
<table>
  <tr><th colspan="7">{{ __('Ledger') }} — {{ $fromDate }} → {{ $toDate }} ({{ $status }})</th></tr>
  <tr>
    <th>{{ __('Date') }}</th>
    <th>{{ __('Journal') }}</th>
    <th>{{ __('Account') }}</th>
    <th class="num">{{ __('Debit') }} (₹)</th>
    <th class="num">{{ __('Credit') }} (₹)</th>
    <th>{{ __('Description') }}</th>
    <th>{{ __('Status') }}</th>
  </tr>

  @foreach ($rows as $row)
    <tr>
      <td>{{ $row['date'] ?? '—' }}</td>
      <td>{{ $row['journal'] ?? '—' }}</td>
      <td>{{ $row['account'] ?? '—' }}</td>
      <td>{{ number_format((float) ($row['debit'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['credit'] ?? 0), 2) }}</td>
      <td>{{ $row['description'] ?? '' }}</td>
      <td>{{ $row['status'] ?? '—' }}</td>
    </tr>
  @endforeach

  <tr>
    <td></td>
    <td>{{ __('TOTAL') }}</td>
    <td></td>
    <td>{{ number_format((float) ($totals['total_debit'] ?? 0), 2) }}</td>
    <td>{{ number_format((float) ($totals['total_credit'] ?? 0), 2) }}</td>
    <td>{{ __('Totals') }}</td>
    <td></td>
  </tr>
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Ledger') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h2 { font-size: 16px; margin-bottom: 8px; }
    .muted { color: #666; margin-bottom: 12px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 16px; }
    th, td { border: 1px solid #999; padding: 6px 8px; }
    th { background: #f3f4f6; }
    .num { text-align: right; }
  </style>
</head>
<body>
  <h2>{{ __('Ledger') }}</h2>
  <p class="muted">{{ __('Period') }}: <strong>{{ $fromDate }}</strong> → <strong>{{ $toDate }}</strong> ({{ $status }})</p>
  <table>
    <thead>
      <tr>
        <th>{{ __('Date') }}</th>
        <th>{{ __('Journal') }}</th>
        <th>{{ __('Account') }}</th>
        <th class="num">{{ __('Debit') }} (₹)</th>
        <th class="num">{{ __('Credit') }} (₹)</th>
        <th>{{ __('Description') }}</th>
        <th>{{ __('Status') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          <td>{{ $row['date'] ?? '—' }}</td>
          <td>{{ $row['journal'] ?? '—' }}</td>
          <td>{{ $row['account'] ?? '—' }}</td>
          <td class="num">{{ number_format((float) ($row['debit'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['credit'] ?? 0), 2) }}</td>
          <td>{{ $row['description'] ?? '' }}</td>
          <td>{{ $row['status'] ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse

      <tr>
        <td></td>
        <td><strong>{{ __('TOTAL') }}</strong></td>
        <td></td>
        <td class="num"><strong>{{ number_format((float) ($totals['total_debit'] ?? 0), 2) }}</strong></td>
        <td class="num"><strong>{{ number_format((float) ($totals['total_credit'] ?? 0), 2) }}</strong></td>
        <td>{{ __('Totals') }}</td>
        <td></td>
      </tr>
    </tbody>
  </table>
</body>
</html>
@endif

