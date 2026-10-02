<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $title }}</title>
  <style>
    @page { margin: 22px; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #2f3349;
      font-size: 9px;
    }
    h1 { margin: 0 0 4px; font-size: 18px; }
    .meta { color: #6d6f85; margin-bottom: 12px; }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      padding: 5px;
      border: 1px solid #d9dbe5;
      text-align: left;
      vertical-align: top;
    }
    th {
      background: #666cff;
      color: #fff;
      font-weight: bold;
    }
    tr:nth-child(even) td { background: #f8f8fb; }
    .empty { text-align: center; padding: 20px; color: #6d6f85; }
  </style>
</head>
<body>
  @if(isset($reportBranding))
  <div style="margin-bottom:12px;">
    <strong>{{ $reportBranding['name'] ?? config('app.name') }}</strong><br>
    <span style="color:#6d6f85;">{{ $reportBranding['address'] ?? '' }}</span>
  </div>
  @endif

  <h1>{{ $title }}</h1>
  <div class="meta">
    Generated: {{ now()->format('d M Y, h:i A') }} · Records: {{ number_format(count($rows)) }}
  </div>

  <table>
    <thead>
      <tr>
        @foreach($headers as $header)
          <th>{{ $header }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
        <tr>
          @foreach($row as $cell)
            <td>{{ filled($cell) || $cell === 0 || $cell === '0' ? $cell : '—' }}</td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td class="empty" colspan="{{ max(count($headers), 1) }}">No records match the selected filters.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
