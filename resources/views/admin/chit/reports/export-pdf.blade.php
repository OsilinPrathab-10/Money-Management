<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $definition['title'] }}</title>
  <style>
    @page {
      size: A4 landscape;
      margin: 10mm;
    }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #2f3349;
      font-size: 9px;
      margin: 0;
    }
    h1 { margin: 0 0 4px; font-size: 16px; }
    .meta { color: #6d6f85; margin-bottom: 10px; }
    .filters {
      padding: 6px 8px;
      margin-bottom: 10px;
      background: #f4f5fa;
      border: 1px solid #e5e7eb;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: auto;
      word-wrap: break-word;
    }
    th, td {
      padding: 5px 7px;
      border: 1px solid #d9dbe5;
      text-align: left;
      vertical-align: top;
      word-break: break-word;
      mso-number-format: "\@";
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
  @include('admin.reports.partials.download-branding')

  <h1>{{ $definition['title'] }}</h1>
  <div class="meta">
    {{ $definition['description'] }}<br>
    Generated: {{ $generatedAt->format('d M Y, h:i A') }} · Records: {{ number_format($rows->count()) }}
  </div>

  @if (count($filters))
    <div class="filters">
      <strong>Applied filters:</strong>
      @foreach ($filters as $key => $value)
        @if (filled($value))
          {{ \Illuminate\Support\Str::headline($key) }}: {{ $value }}{{ !$loop->last ? ' · ' : '' }}
        @endif
      @endforeach
    </div>
  @endif

  <table>
    <thead>
      <tr>
        @foreach ($columns as $label)
          <th>{{ $label }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          @foreach (array_keys($columns) as $key)
            <td>{{ filled($row[$key] ?? null) ? $row[$key] : '—' }}</td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td class="empty" colspan="{{ count($columns) }}">No records match the selected filters.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
