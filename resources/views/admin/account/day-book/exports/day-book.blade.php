@php
  $mode = $exportMode ?? 'pdf';
  $rows = $rows ?? [];
  $totals = $totals ?? [];
  $day = $day ?? ($filters['day'] ?? '');
  $status = $filters['status'] ?? ($status ?? '');
@endphp

@if ($mode === 'csv')
<table>
  <tr><th colspan="10">{{ __('Day Book') }} — {{ $day }} ({{ $status }})</th></tr>
  <tr>
    <th>{{ __('Type') }}</th>
    <th>{{ __('Number') }}</th>
    <th>{{ __('Date') }}</th>
    <th>{{ __('Module') }}</th>
    <th>{{ __('Entry') }}</th>
    <th>{{ __('Category') }}</th>
    <th>{{ __('Bank') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('Description') }}</th>
    <th class="num">{{ __('Amount') }}</th>
  </tr>

  @foreach ($rows as $row)
    <tr>
      <td>{{ $row['type'] ?? '—' }}</td>
      <td>{{ $row['number'] ?? '—' }}</td>
      <td>{{ $row['date'] ?? '—' }}</td>
      <td>{{ $row['module'] ?? '—' }}</td>
      <td>{{ $row['entry'] ?? '—' }}</td>
      <td>{{ $row['category'] ?? '—' }}</td>
      <td>{{ $row['bank'] ?? '—' }}</td>
      <td>{{ $row['status'] ?? '—' }}</td>
      <td>{{ $row['description'] ?? '' }}</td>
      <td>{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
    </tr>
  @endforeach

  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Revenue') }}</td>
    <td colspan="6"></td>
    <td>{{ __('Total revenue') }}</td>
    <td>{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</td>
  </tr>
  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Expense') }}</td>
    <td colspan="6"></td>
    <td>{{ __('Total expense') }}</td>
    <td>{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</td>
  </tr>
  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Bank In') }}</td>
    <td colspan="6"></td>
    <td>{{ __('Bank credits') }}</td>
    <td>{{ number_format((float) ($totals['bank_credits'] ?? 0), 2) }}</td>
  </tr>
  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Bank Out') }}</td>
    <td colspan="6"></td>
    <td>{{ __('Bank debits') }}</td>
    <td>{{ number_format((float) ($totals['bank_debits'] ?? 0), 2) }}</td>
  </tr>
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Day Book') }}</title>
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
  <h2>{{ __('Day Book') }}</h2>
  <p class="muted">{{ __('Day') }}: <strong>{{ $day }}</strong> ({{ $status }})</p>
  <table>
    <thead>
      <tr>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Number') }}</th>
        <th>{{ __('Date') }}</th>
        <th>{{ __('Module') }}</th>
        <th>{{ __('Entry') }}</th>
        <th>{{ __('Category') }}</th>
        <th>{{ __('Bank') }}</th>
        <th>{{ __('Status') }}</th>
        <th>{{ __('Description') }}</th>
        <th class="num">{{ __('Amount') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          <td>{{ $row['type'] ?? '—' }}</td>
          <td>{{ $row['number'] ?? '—' }}</td>
          <td>{{ $row['date'] ?? '—' }}</td>
          <td>{{ $row['module'] ?? '—' }}</td>
          <td>{{ $row['entry'] ?? '—' }}</td>
          <td>{{ $row['category'] ?? '—' }}</td>
          <td>{{ $row['bank'] ?? '—' }}</td>
          <td>{{ $row['status'] ?? '—' }}</td>
          <td>{{ $row['description'] ?? '' }}</td>
          <td class="num">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="10" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
@endif
