<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Attendance Report - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #000; text-transform: uppercase; }
        .header p { margin: 5px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 4px 2px; text-align: center; overflow: hidden; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .name-col { text-align: left; padding-left: 5px; width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sl-col { width: 30px; }
        .day-col { width: 22px; }
        .stat-col { width: 30px; font-weight: bold; }
        .present { color: #28a745; }
        .absent { color: #dc3545; background-color: #ffeaea; }
        .half-day { color: #ffc107; background-color: #fff9e6; }
        .summary { margin-top: 20px; display: flex; justify-content: flex-end; gap: 20px; font-size: 12px; }
        .summary-item { border: 1px solid #ccc; padding: 5px 15px; border-radius: 4px; background: #f9f9f9; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 10px; }
            table { font-size: 9px; }
            @page { margin: 0.5cm; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 20px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">Print Report</button>
    </div>

    <div class="header">
        <h2>Agent Attendance Report</h2>
        <p>Month: <strong>{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</strong></p>
        <p>Generated on: {{ date('d-m-Y h:i A') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="sl-col">SL</th>
                <th class="name-col">Agent Name</th>
                @for($d=1; $d<=$daysInMonth; $d++)
                    <th class="day-col">{{ $d }}</th>
                @endfor
                <th class="stat-col">P</th>
                <th class="stat-col">A</th>
                <th class="stat-col">H</th>
            </tr>
        </thead>
        <tbody>
            @foreach($agents as $index => $agent)
            @php 
                $agentAtt = $attendances->get($agent->id) ?? collect();
                $indexedAtt = $agentAtt->keyBy(function($a) {
                    $d = is_string($a->date) ? \Carbon\Carbon::parse($a->date) : $a->date;
                    return $d ? $d->format('Y-m-d') : '';
                });
                $p = 0; $a = 0; $h = 0;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="name-col">{{ $agent->agent_name }}</td>
                @for($d=1; $d<=$daysInMonth; $d++)
                    @php 
                        $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                        $status = $indexedAtt->get($dateStr)->status ?? null;
                        $class = $status == 'present' ? 'present' : ($status == 'absent' ? 'absent' : ($status == 'half_day' ? 'half-day' : ''));
                        $char = $status ? strtoupper(substr($status, 0, 1)) : '-';
                        if($status == 'present') $p++;
                        elseif($status == 'absent') $a++;
                        elseif($status == 'half_day') $h++;
                    @endphp
                    <td class="{{ $class }}">{{ $char }}</td>
                @endfor
                <td style="background: #e8f5e9; font-weight: bold;">{{ $p }}</td>
                <td style="background: #ffebee; font-weight: bold;">{{ $a }}</td>
                <td style="background: #fff8e1; font-weight: bold;">{{ $h }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-item">Total Agents: <strong>{{ count($agents) }}</strong></div>
        <div class="summary-item">P: Present | A: Absent | H: Half Day</div>
    </div>

    <script>
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('print')) {
                setTimeout(function() {
                    window.print();
                }, 800);
            }
            
            // Auto-close if it was opened just for printing in a new window (not iframe)
            window.onafterprint = function() {
                if (urlParams.has('print') && window.self === window.top) {
                    window.close();
                }
            };
        };
    </script>
</body>
</html>
