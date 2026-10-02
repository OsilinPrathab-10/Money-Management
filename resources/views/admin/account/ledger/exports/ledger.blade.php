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
  <tr><th colspan="10">{{ __('Operational Ledger') }} — {{ $fromDate }} → {{ $toDate }} ({{ $status }})</th></tr>
  <tr>
    <th>{{ __('Date') }}</th>
    <th>{{ __('Module') }}</th>
    <th>{{ __('Entry') }}</th>
    <th>{{ __('Bank') }}</th>
    <th>{{ __('Ref') }}</th>
    <th>{{ __('Source') }}</th>
    <th class="num">{{ __('Debit') }} (₹)</th>
    <th class="num">{{ __('Credit') }} (₹)</th>
    <th>{{ __('Description') }}</th>
    <th>{{ __('Status') }}</th>
  </tr>

  @foreach ($rows as $row)
    <tr>
      <td>{{ $row['date'] ?? '—' }}</td>
      <td>{{ $row['module'] ?? '—' }}</td>
      <td>{{ $row['entry'] ?? '—' }}</td>
      <td>{{ $row['bank'] ?? '—' }}</td>
      <td>{{ $row['ref'] ?? '—' }}</td>
      <td>{{ $row['source'] ?? '—' }}</td>
      <td>{{ number_format((float) ($row['debit'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['credit'] ?? 0), 2) }}</td>
      <td>{{ $row['description'] ?? '' }}</td>
      <td>{{ $row['status'] ?? '—' }}</td>
    </tr>
  @endforeach

  <tr>
    <td></td>
    <td></td>
    <td></td>
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
  <title>{{ __('Operational Ledger') }}</title>
  <style>
    @page { size: A4 landscape; margin: 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; margin: 0; }
    h2 { font-size: 16px; margin-bottom: 8px; }
    .muted { color: #666; margin-bottom: 12px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 16px; table-layout: auto; word-wrap: break-word; }
    th, td { border: 1px solid #999; padding: 6px 8px; font-size: 9px; vertical-align: top; word-break: break-word; mso-number-format: "\@"; }
    th { background: #666cff; color: #ffffff; font-weight: bold; }
    .num { text-align: right; }
  </style>
</head>
<body>
  @include('admin.reports.partials.download-branding')
  <h2>{{ __('Operational Ledger') }}</h2>
  <p class="muted">{{ __('Period') }}: <strong>{{ $fromDate }}</strong> → <strong>{{ $toDate }}</strong> ({{ $status }})</p>
  <table>
    <thead>
      <tr>
        <th>{{ __('Date') }}</th>
        <th>{{ __('Module') }}</th>
        <th>{{ __('Entry') }}</th>
        <th>{{ __('Bank') }}</th>
        <th>{{ __('Ref') }}</th>
        <th>{{ __('Source') }}</th>
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
          <td>{{ $row['module'] ?? '—' }}</td>
          <td>{{ $row['entry'] ?? '—' }}</td>
          <td>{{ $row['bank'] ?? '—' }}</td>
          <td>{{ $row['ref'] ?? '—' }}</td>
          <td>{{ $row['source'] ?? '—' }}</td>
          <td class="num">{{ number_format((float) ($row['debit'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['credit'] ?? 0), 2) }}</td>
          <td>{{ $row['description'] ?? '' }}</td>
          <td>{{ $row['status'] ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="10" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse

      <tr>
        <td></td>
        <td></td>
        <td></td>
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
