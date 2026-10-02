<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $title }}</title>
  <style>
    @page { size: A4 landscape; margin: 10mm 8mm 12mm 8mm; }
    body { font-family: DejaVu Sans, sans-serif; color: #2f3349; font-size: 8px; margin: 0; }
    h1 { margin: 0 0 2px; font-size: 14px; }
    .meta { color: #6d6f85; margin-bottom: 8px; font-size: 8px; }
    .filters { padding: 5px 7px; margin-bottom: 8px; background: #f4f5fa; border: 1px solid #e5e7eb; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td {
      padding: 4px 5px;
      border: 1px solid #d9dbe5;
      vertical-align: top;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    th { background: #666cff; color: #fff; font-weight: bold; text-align: center; }
    td { text-align: left; }
    td.num, th.num { text-align: right; }
    td.center, th.center { text-align: center; }
    tr:nth-child(even) td { background: #f8f8fb; }
    .empty { text-align: center; padding: 18px; color: #6d6f85; }
  </style>
</head>
<body>
  @include('admin.reports.partials.download-branding')

  <h1>{{ $title }}</h1>
  <div class="meta">
    Generated: {{ $generatedAt->format('d M Y, h:i A') }}
    · Records: {{ number_format(count($rows)) }}
  </div>

  @php
    $filterBits = [];
    foreach ($filters ?? [] as $key => $value) {
      if (! filled($value) || ! is_scalar($value) || in_array($key, ['_token', 'format', 'page', '_export'], true)) {
        continue;
      }
      $filterBits[] = \Illuminate\Support\Str::headline($key) . ': ' . $value;
    }
  @endphp
  @if ($filterBits)
    <div class="filters">
      <strong>Applied filters:</strong>
      {{ implode(' · ', $filterBits) }}
    </div>
  @endif

  <table>
    @if (!empty($headers))
      <colgroup>
        @foreach ($headers as $header)
          <col style="width: {{ number_format(100 / max(count($headers), 1), 2) }}%;">
        @endforeach
      </colgroup>
    @endif
    <thead>
      <tr>
        @foreach ($headers as $header)
          @php
            $class = '';
            $lower = strtolower((string) $header);
            if (in_array($header, ['S.No', 'Sl. No', 'Sl No', 'ID'], true)) $class = 'center';
            if (str_contains($lower, 'amount') || str_contains($lower, 'principal') || str_contains($lower, 'interest') || str_contains($lower, 'balance')) $class = 'num';
          @endphp
          <th class="{{ $class }}">{{ $header }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          @foreach ($headers as $index => $header)
            @php
              $class = '';
              $lower = strtolower((string) $header);
              if (in_array($header, ['S.No', 'Sl. No', 'Sl No', 'ID'], true)) $class = 'center';
              if (str_contains($lower, 'amount') || str_contains($lower, 'principal') || str_contains($lower, 'interest') || str_contains($lower, 'balance')) $class = 'num';
              $cell = is_array($row) ? ($row[$index] ?? $row[$header] ?? '') : '';
            @endphp
            <td class="{{ $class }}">{{ filled($cell) || $cell === 0 || $cell === '0' ? $cell : '—' }}</td>
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
