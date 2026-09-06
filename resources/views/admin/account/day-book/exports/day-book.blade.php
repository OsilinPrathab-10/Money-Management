@php
  $mode = $exportMode ?? 'pdf';
  $rows = $rows ?? [];
  $totals = $totals ?? [];
  $day = $day ?? ($filters['day'] ?? '');
  $status = $filters['status'] ?? ($status ?? '');
@endphp

@if ($mode === 'csv')
<table>
  <tr><th colspan="9">{{ __('Day Book') }} — {{ $day }} ({{ $status }})</th></tr>
  <tr>
    <th>{{ __('Type') }}</th>
    <th>{{ __('Number') }}</th>
    <th>{{ __('Date') }}</th>
    <th>{{ __('Category') }}</th>
    <th>{{ __('Bank') }}</th>
    <th>{{ __('GL') }}</th>
    <th>{{ __('Status') }}</th>
    <th>{{ __('Description') }}</th>
    <th class="num">{{ __('Amount') }}</th>
  </tr>

  @foreach ($rows as $row)
    <tr>
      <td>{{ $row['type'] ?? '—' }}</td>
      <td>{{ $row['number'] ?? '—' }}</td>
      <td>{{ $row['date'] ?? '—' }}</td>
      <td>{{ $row['category'] ?? '—' }}</td>
      <td>{{ $row['bank'] ?? '—' }}</td>
      <td>{{ $row['gl'] ?? '—' }}</td>
      <td>{{ $row['status'] ?? '—' }}</td>
      <td>{{ $row['description'] ?? '' }}</td>
      <td>{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
    </tr>
  @endforeach

  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Revenue') }}</td>
    <td colspan="5"></td>
    <td>{{ __('Total revenue') }}</td>
    <td>{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</td>
  </tr>
  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Expense') }}</td>
    <td colspan="5"></td>
    <td>{{ __('Total expense') }}</td>
    <td>{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</td>
  </tr>
  <tr>
    <td>{{ __('TOTAL') }}</td>
    <td>{{ __('Net') }}</td>
    <td colspan="5"></td>
    <td>{{ __('Revenue - Expense') }}</td>
    <td>{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}</td>
  </tr>
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Day Book') }}</title>
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
  <h2>{{ __('Day Book') }}</h2>
  <p class="muted">{{ __('Day') }}: <strong>{{ $day }}</strong> ({{ $status }})</p>
  <table>
    <thead>
      <tr>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Number') }}</th>
        <th>{{ __('Date') }}</th>
        <th>{{ __('Category') }}</th>
        <th>{{ __('Bank') }}</th>
        <th>{{ __('GL') }}</th>
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
          <td>{{ $row['category'] ?? '—' }}</td>
          <td>{{ $row['bank'] ?? '—' }}</td>
          <td>{{ $row['gl'] ?? '—' }}</td>
          <td>{{ $row['status'] ?? '—' }}</td>
          <td>{{ $row['description'] ?? '' }}</td>
          <td class="num">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="9" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse

      <tr>
        <td><strong>{{ __('TOTAL') }}</strong></td>
        <td>{{ __('Revenue') }}</td>
        <td colspan="5"></td>
        <td>{{ __('Total revenue') }}</td>
        <td class="num"><strong>{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</strong></td>
      </tr>
      <tr>
        <td><strong>{{ __('TOTAL') }}</strong></td>
        <td>{{ __('Expense') }}</td>
        <td colspan="5"></td>
        <td>{{ __('Total expense') }}</td>
        <td class="num"><strong>{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</strong></td>
      </tr>
      <tr>
        <td><strong>{{ __('TOTAL') }}</strong></td>
        <td>{{ __('Net') }}</td>
        <td colspan="5"></td>
        <td>{{ __('Revenue - Expense') }}</td>
        <td class="num"><strong>{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}</strong></td>
      </tr>
    </tbody>
  </table>
</body>
</html>
@endif

