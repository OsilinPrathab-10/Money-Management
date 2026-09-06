@php
  $mode = $exportMode ?? 'pdf';
  $a = $data['aging_summary'] ?? [];
  $customers = $data['customers'] ?? [];
  $asOf = $filters['as_of_date'] ?? ($data['as_of_date'] ?? date('Y-m-d'));
@endphp
@if ($mode === 'csv')
<table>
  <tr><th colspan="2">{{ __('Invoice aging') }} — {{ __('As of') }} {{ $asOf }}</th></tr>
  <tr><th>{{ __('Bucket') }}</th><th>{{ __('Amount') }}</th></tr>
  <tr><td>{{ __('Current') }}</td><td>{{ number_format((float) ($a['current'] ?? 0), 2) }}</td></tr>
  <tr><td>1–30</td><td>{{ number_format((float) ($a['1_30_days'] ?? 0), 2) }}</td></tr>
  <tr><td>31–60</td><td>{{ number_format((float) ($a['31_60_days'] ?? 0), 2) }}</td></tr>
  <tr><td>61–90</td><td>{{ number_format((float) ($a['61_90_days'] ?? 0), 2) }}</td></tr>
  <tr><td>90+</td><td>{{ number_format((float) ($a['over_90_days'] ?? 0), 2) }}</td></tr>
  <tr><td>{{ __('Total') }}</td><td>{{ number_format((float) ($a['total'] ?? 0), 2) }}</td></tr>
  <tr><th colspan="2">{{ __('By customer') }}</th></tr>
  <tr>
    <th>{{ __('Customer') }}</th>
    <th>{{ __('Current') }}</th>
    <th>1–30</th>
    <th>31–60</th>
    <th>61–90</th>
    <th>90+</th>
    <th>{{ __('Total') }}</th>
  </tr>
  @foreach ($customers as $row)
    <tr>
      <td>{{ $row['customer_name'] ?? '—' }}</td>
      <td>{{ number_format((float) ($row['current'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['1_30_days'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['31_60_days'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['61_90_days'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['over_90_days'] ?? 0), 2) }}</td>
      <td>{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
    </tr>
  @endforeach
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Invoice aging') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h2 { font-size: 16px; margin-bottom: 8px; }
    .muted { color: #666; font-size: 10px; margin-bottom: 16px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
    th { background: #f3f4f6; }
    .num { text-align: right; }
  </style>
</head>
<body>
  <h2>{{ __('Invoice aging') }}</h2>
  <p class="muted">{{ __('As of') }}: <strong>{{ $asOf }}</strong></p>

  <table>
    <thead>
      <tr><th>{{ __('Bucket') }}</th><th class="num">{{ __('Amount') }} (₹)</th></tr>
    </thead>
    <tbody>
      <tr><td>{{ __('Current') }}</td><td class="num">{{ number_format((float) ($a['current'] ?? 0), 2) }}</td></tr>
      <tr><td>1–30</td><td class="num">{{ number_format((float) ($a['1_30_days'] ?? 0), 2) }}</td></tr>
      <tr><td>31–60</td><td class="num">{{ number_format((float) ($a['31_60_days'] ?? 0), 2) }}</td></tr>
      <tr><td>61–90</td><td class="num">{{ number_format((float) ($a['61_90_days'] ?? 0), 2) }}</td></tr>
      <tr><td>90+</td><td class="num">{{ number_format((float) ($a['over_90_days'] ?? 0), 2) }}</td></tr>
      <tr><td><strong>{{ __('Total') }}</strong></td><td class="num"><strong>{{ number_format((float) ($a['total'] ?? 0), 2) }}</strong></td></tr>
    </tbody>
  </table>

  <h3 style="font-size: 13px;">{{ __('By customer') }}</h3>
  <table>
    <thead>
      <tr>
        <th>{{ __('Customer') }}</th>
        <th class="num">{{ __('Current') }}</th>
        <th class="num">1–30</th>
        <th class="num">31–60</th>
        <th class="num">61–90</th>
        <th class="num">90+</th>
        <th class="num">{{ __('Total') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($customers as $row)
        <tr>
          <td>{{ $row['customer_name'] ?? '—' }}</td>
          <td class="num">{{ number_format((float) ($row['current'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['1_30_days'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['31_60_days'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['61_90_days'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['over_90_days'] ?? 0), 2) }}</td>
          <td class="num">{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
@endif
