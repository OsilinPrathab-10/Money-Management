<div style="width: 100%;">
    <h3 style="text-align: center; margin-bottom: 20px;">Agent Attendance Report - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h3>
    
    <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
        <thead>
            <tr style="background-color: #f8f9fa;">
                <th style="border: 1px solid #dee2e6; padding: 5px;">Agent Member</th>
                @for($d=1; $d<=$daysInMonth; $d++)
                    <th style="border: 1px solid #dee2e6; padding: 2px; text-align: center;">{{ $d }}</th>
                @endfor
                <th style="border: 1px solid #dee2e6; padding: 5px; text-align: center;">P</th>
                <th style="border: 1px solid #dee2e6; padding: 5px; text-align: center;">A</th>
                <th style="border: 1px solid #dee2e6; padding: 5px; text-align: center;">H</th>
            </tr>
        </thead>
        <tbody>
            @foreach($agents as $agent)
                @php
                    $agentAtt = $attendances->get($agent->id) ?? collect();
                    $indexedAtt = $agentAtt->keyBy(function($att) {
                        return \Carbon\Carbon::parse($att->date)->format('Y-m-d');
                    });
                    $pCount = 0; $aCount = 0; $hCount = 0;
                @endphp
                <tr>
                    <td style="border: 1px solid #dee2e6; padding: 5px; font-weight: bold;">{{ $agent->agent_name }}</td>
                    @for($d=1; $d<=$daysInMonth; $d++)
                        @php
                            $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                            $statRecord = $indexedAtt->get($dateStr);
                            $stat = $statRecord ? $statRecord->status : '-';
                            
                            $display = '-';
                            $color = '#000';
                            if($stat == 'present') { $display = 'P'; $pCount++; $color = 'green'; }
                            elseif($stat == 'absent') { $display = 'A'; $aCount++; $color = 'red'; }
                            elseif($stat == 'half_day') { $display = 'H'; $hCount++; $color = 'orange'; }
                        @endphp
                        <td style="border: 1px solid #dee2e6; padding: 2px; text-align: center; color: {{ $color }};">{{ $display }}</td>
                    @endfor
                    <td style="border: 1px solid #dee2e6; padding: 5px; text-align: center; font-weight: bold; background-color: #e8f5e9;">{{ $pCount }}</td>
                    <td style="border: 1px solid #dee2e6; padding: 5px; text-align: center; font-weight: bold; background-color: #ffebee;">{{ $aCount }}</td>
                    <td style="border: 1px solid #dee2e6; padding: 5px; text-align: center; font-weight: bold; background-color: #fff3e0;">{{ $hCount }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="margin-top: 20px; font-size: 11px;">
        <p><strong>Legend:</strong> P = Present, A = Absent, H = Half Day, - = No Record</p>
    </div>
</div>
