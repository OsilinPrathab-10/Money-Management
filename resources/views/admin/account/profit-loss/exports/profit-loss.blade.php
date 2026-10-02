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

