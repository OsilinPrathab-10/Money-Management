@php
  $mode = $exportMode ?? 'pdf';
  $rows = $rows ?? [];
  $fromDate = $fromDate ?? ($filters['from_date'] ?? '');
  $toDate = $toDate ?? ($filters['to_date'] ?? '');
  $statusMode = $filters['status_mode'] ?? ($statusMode ?? '');
@endphp

@if ($mode === 'csv')
<table>
  <tr><th colspan="2">{{ __('Profit & Loss') }} — {{ $fromDate }} → {{ $toDate }} ({{ $statusMode }})</th></tr>
  <tr>
    <th>{{ __('Metric') }}</th>
    <th class="num">{{ __('Amount') }} (₹)</th>
  </tr>
  @foreach ($rows as $row)
    <tr>
      <td>{{ $row['metric'] ?? '—' }}</td>
      <td>{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
    </tr>
  @endforeach
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Profit & Loss') }}</title>
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
  <h2>{{ __('Profit & Loss') }}</h2>
  <p class="muted">{{ __('Period') }}: <strong>{{ $fromDate }}</strong> → <strong>{{ $toDate }}</strong> ({{ $statusMode }})</p>
  <table>
    <thead>
      <tr>
        <th>{{ __('Metric') }}</th>
        <th class="num">{{ __('Amount') }} (₹)</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          <td>{{ $row['metric'] ?? '—' }}</td>
          <td class="num">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="2" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
@endif

