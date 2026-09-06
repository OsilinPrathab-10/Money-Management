@php
  $mode = $exportMode ?? 'pdf';
  $pageTitle = $pageTitle ?? __('Report');
  $columns = $columns ?? [];
  $rows = $rows ?? [];
  $subtitle = $subtitle ?? null;
@endphp

@if ($mode === 'csv')
<table>
  <tr>
    <th colspan="{{ count($columns) }}">{{ $pageTitle }}{{ $subtitle ? ' — ' . $subtitle : '' }}</th>
  </tr>
  <tr>
    @foreach ($columns as $col)
      <th>{{ $col['label'] ?? '' }}</th>
    @endforeach
  </tr>

  @foreach ($rows as $row)
    <tr>
      @foreach ($columns as $col)
        @php
          $key = $col['key'] ?? null;
          $class = $col['class'] ?? '';
          $val = $key !== null ? ($row[$key] ?? '') : '';
        @endphp
        <td class="{{ $class }}">{{ $val }}</td>
      @endforeach
    </tr>
  @endforeach
</table>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <title>{{ $pageTitle }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h2 { font-size: 16px; margin-bottom: 8px; }
    .muted { color: #666; margin-bottom: 12px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 16px; }
    th, td { border: 1px solid #999; padding: 6px 8px; }
    th { background: #f3f4f6; }
    .text-end { text-align: right; }
  </style>
</head>
<body>
  <h2>{{ $pageTitle }}</h2>
  @if ($subtitle)
    <p class="muted">{{ $subtitle }}</p>
  @endif
  <table>
    <thead>
      <tr>
        @foreach ($columns as $col)
          <th>{{ $col['label'] ?? '' }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          @foreach ($columns as $col)
            @php
              $key = $col['key'] ?? null;
              $class = $col['class'] ?? '';
              $val = $key !== null ? ($row[$key] ?? '') : '';
            @endphp
            <td class="{{ $class }}">{{ $val }}</td>
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($columns) }}" style="text-align:center;">{{ __('No data') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
@endif

