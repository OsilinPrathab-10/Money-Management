@php
  $mode = $exportMode ?? 'pdf';
  $asOf = $filters['as_of_date'] ?? ($data['as_of_date'] ?? date('Y-m-d'));
  $isCustomer = ($balanceType ?? '') === 'customer';
@endphp
@if ($mode === 'csv')
<table>
  <tr><th colspan="2">{{ $pageTitle }} — {{ __('As of') }} {{ $asOf }}</th></tr>
  <tr><th>{{ __('Name') }}</th><th>{{ __('Balance') }}</th></tr>
  @if ($isCustomer)
    @foreach (($data['customers'] ?? []) as $row)
      <tr><td>{{ $row['customer_name'] ?? '—' }}</td><td>{{ number_format((float) ($row['balance'] ?? 0), 2) }}</td></tr>
    @endforeach
  @else
    @foreach (($data['vendors'] ?? []) as $row)
      <tr><td>{{ $row['vendor_name'] ?? '—' }}</td><td>{{ number_format((float) ($row['balance'] ?? 0), 2) }}</td></tr>
    @endforeach
  @endif
  <tr><td>{{ __('Total') }}</td><td>{{ number_format((float) ($data['total_balance'] ?? 0), 2) }}</td></tr>
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ $pageTitle }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h2 { font-size: 16px; }
    .muted { color: #666; margin-bottom: 12px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #999; padding: 6px 8px; }
    th { background: #f3f4f6; }
    .num { text-align: right; }
  </style>
</head>
<body>
  <h2>{{ $pageTitle }}</h2>
  <p class="muted">{{ __('As of') }}: <strong>{{ $asOf }}</strong></p>
  <table>
    <thead><tr><th>{{ __('Name') }}</th><th class="num">{{ __('Balance') }} (₹)</th></tr></thead>
    <tbody>
      @if ($isCustomer)
        @forelse (($data['customers'] ?? []) as $row)
          <tr>
            <td>{{ $row['customer_name'] ?? '—' }}</td>
            <td class="num">{{ number_format((float) ($row['balance'] ?? 0), 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="2" style="text-align:center;">{{ __('No data') }}</td></tr>
        @endforelse
      @else
        @forelse (($data['vendors'] ?? []) as $row)
          <tr>
            <td>{{ $row['vendor_name'] ?? '—' }}</td>
            <td class="num">{{ number_format((float) ($row['balance'] ?? 0), 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="2" style="text-align:center;">{{ __('No data') }}</td></tr>
        @endforelse
      @endif
    </tbody>
  </table>
  <p><strong>{{ __('Total') }}:</strong> ₹{{ number_format((float) ($data['total_balance'] ?? 0), 2) }}</p>
</body>
</html>
@endif
