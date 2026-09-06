@php
  $mode = $exportMode ?? 'pdf';
  $collectedItems = $data['tax_collected']['items'] ?? collect();
  $paidItems = $data['tax_paid']['items'] ?? collect();
  $from = $filters['from_date'] ?? ($data['from_date'] ?? '');
  $to = $filters['to_date'] ?? ($data['to_date'] ?? '');
@endphp
@if ($mode === 'csv')
<table>
  <tr><th colspan="2">{{ __('Tax summary') }} {{ $from }} → {{ $to }}</th></tr>
  <tr><th colspan="2">{{ __('Tax collected (sales)') }}</th></tr>
  <tr><th>{{ __('Tax') }}</th><th>{{ __('Amount') }}</th></tr>
  @foreach ($collectedItems as $row)
    @php
      $name = is_array($row) ? ($row['tax_name'] ?? '—') : ($row->tax_name ?? '—');
      $amt = is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0);
    @endphp
    <tr><td>{{ $name }}</td><td>{{ number_format((float) $amt, 2) }}</td></tr>
  @endforeach
  <tr><td>{{ __('Total collected') }}</td><td>{{ number_format((float) ($data['tax_collected']['total'] ?? 0), 2) }}</td></tr>
  <tr><th colspan="2">{{ __('Tax paid (purchases)') }}</th></tr>
  <tr><th>{{ __('Tax') }}</th><th>{{ __('Amount') }}</th></tr>
  @foreach ($paidItems as $row)
    @php
      $name = is_array($row) ? ($row['tax_name'] ?? '—') : ($row->tax_name ?? '—');
      $amt = is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0);
    @endphp
    <tr><td>{{ $name }}</td><td>{{ number_format((float) $amt, 2) }}</td></tr>
  @endforeach
  <tr><td>{{ __('Total paid') }}</td><td>{{ number_format((float) ($data['tax_paid']['total'] ?? 0), 2) }}</td></tr>
  <tr><td>{{ __('Net tax liability') }}</td><td>{{ number_format((float) ($data['net_tax_liability'] ?? 0), 2) }}</td></tr>
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ __('Tax summary') }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h2 { font-size: 16px; }
    .muted { color: #666; margin-bottom: 12px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 16px; }
    th, td { border: 1px solid #999; padding: 6px 8px; }
    th { background: #f3f4f6; }
    .num { text-align: right; }
  </style>
</head>
<body>
  <h2>{{ __('Tax summary') }}</h2>
  <p class="muted">{{ __('Period') }}: {{ $from }} → {{ $to }}</p>
  <h3 style="font-size: 12px;">{{ __('Tax collected (sales)') }}</h3>
  <table>
    <thead><tr><th>{{ __('Tax') }}</th><th class="num">{{ __('Amount') }} (₹)</th></tr></thead>
    <tbody>
      @forelse ($collectedItems as $row)
        @php
          $name = is_array($row) ? ($row['tax_name'] ?? '—') : ($row->tax_name ?? '—');
          $amt = is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0);
        @endphp
        <tr><td>{{ $name }}</td><td class="num">{{ number_format((float) $amt, 2) }}</td></tr>
      @empty
        <tr><td colspan="2">{{ __('No data') }}</td></tr>
      @endforelse
    </tbody>
  </table>
  <p><strong>{{ __('Total collected') }}:</strong> ₹{{ number_format((float) ($data['tax_collected']['total'] ?? 0), 2) }}</p>

  <h3 style="font-size: 12px;">{{ __('Tax paid (purchases)') }}</h3>
  <table>
    <thead><tr><th>{{ __('Tax') }}</th><th class="num">{{ __('Amount') }} (₹)</th></tr></thead>
    <tbody>
      @forelse ($paidItems as $row)
        @php
          $name = is_array($row) ? ($row['tax_name'] ?? '—') : ($row->tax_name ?? '—');
          $amt = is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0);
        @endphp
        <tr><td>{{ $name }}</td><td class="num">{{ number_format((float) $amt, 2) }}</td></tr>
      @empty
        <tr><td colspan="2">{{ __('No data') }}</td></tr>
      @endforelse
    </tbody>
  </table>
  <p><strong>{{ __('Total paid') }}:</strong> ₹{{ number_format((float) ($data['tax_paid']['total'] ?? 0), 2) }}</p>
  <p><strong>{{ __('Net tax liability') }}:</strong> ₹{{ number_format((float) ($data['net_tax_liability'] ?? 0), 2) }}</p>
</body>
</html>
@endif
