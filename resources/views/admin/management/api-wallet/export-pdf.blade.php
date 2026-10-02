<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $title }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h3 { margin: 0 0 6px; }
    .meta { color: #666; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
    th { background: #f3f3f3; }
  </style>
</head>
<body>
  <h3>{{ $title }}</h3>
  <div class="meta">Generated: {{ $generatedAt->format('d M Y, h:i A') }} · Records: {{ number_format($rows->count()) }}</div>
  <table>
    <thead>
      <tr>
        @if($rows->isNotEmpty())
          @foreach(array_keys($rows->first()) as $col)
            <th>{{ $col }}</th>
          @endforeach
        @else
          <th>No data</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
        <tr>
          @foreach($row as $cell)
            <td>{{ $cell ?? '—' }}</td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td>No records for the selected filters.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
