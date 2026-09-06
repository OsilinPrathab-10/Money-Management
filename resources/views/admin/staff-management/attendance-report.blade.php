@extends('layouts/layoutMaster')

@section('title', 'Staff Attendance Report')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('staff-management.attendance.report', ['month' => $month, 'year' => $year]) }}" class="btn btn-icon btn-outline-primary shadow-sm"><i class="ri-arrow-left-line"></i></a>
            <h4 class="fw-bold py-3 mb-0"><span class="text-muted fw-light">Staff Management /</span> Attendance Report</h4>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select id="reportMonth" class="form-select" style="width: 150px;">
                @foreach(range(1, 12) as $m)
                    <option value="{{ sprintf('%02d', $m) }}" {{ $month == sprintf('%02d', $m) ? 'selected' : '' }}>
                        {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                    </option>
                @endforeach
            </select>
            <select id="reportYear" class="form-select" style="width: 120px;">
                @foreach(range(date('Y')-1, date('Y')+1) as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" onclick="filterReport()">Filter</button>
            <a href="{{ route('admin.staff.exportAttendance', ['month' => $month, 'year' => $year]) }}" class="btn btn-label-success">Export CSV</a>
            <button class="btn btn-label-secondary" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Monthly Status Grid - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-bordered table-sm">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 40px;">SL NO</th>
                        <th style="min-width: 150px;">Staff Member</th>
                        @for($d=1; $d<=$daysInMonth; $d++)
                            <th class="text-center" style="width: 25px;">{{ $d }}</th>
                        @endfor
                        <th class="text-center bg-label-success">P</th>
                        <th class="text-center bg-label-danger">A</th>
                        <th class="text-center bg-label-warning">H</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffs as $staff)
                    @php
                        $staffAtt = $attendances->get($staff->id) ?? collect();
                        $pCount = $staffAtt->where('status', 'present')->count();
                        $aCount = $staffAtt->where('status', 'absent')->count();
                        $hCount = $staffAtt->where('status', 'half_day')->count();
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="small fw-bold">{{ $staff->name }}</td>
                        @for($d=1; $d<=$daysInMonth; $d++)
                            @php
                                $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                                $status = $staffAtt->first(fn($a) => \Carbon\Carbon::parse($a->date)->format('Y-m-d') == $dateStr)->status ?? null;
                                $color = '';
                                if($status == 'present') $color = 'bg-success text-white';
                                elseif($status == 'absent') $color = 'bg-danger text-white';
                                elseif($status == 'half_day') $color = 'bg-warning text-white';
                            @endphp
                            <td class="text-center p-0 {{ $color }} no-print-bg" style="font-size: 10px; height: 30px;">
                                @if($status == 'present') P
                                @elseif($status == 'absent') A
                                @elseif($status == 'half_day') H
                                @endif
                            </td>
                        @endfor
                        <td class="text-center fw-bold">{{ $pCount }}</td>
                        <td class="text-center fw-bold">{{ $aCount }}</td>
                        <td class="text-center fw-bold">{{ $hCount }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Legend: <span class="badge bg-success">P: Present</span> 
            <span class="badge bg-danger ms-2">A: Absent</span> 
            <span class="badge bg-warning ms-2">H: Half Day</span>
        </div>
    </div>
</div>

<style>
@media print {
    @page { size: landscape; margin: 0.5cm; }
    .no-print { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; font-size: 9px !important; }
    .table th, .table td { padding: 2px !important; border: 1px solid #ddd !important; }
    .bg-success { background-color: #71dd37 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-danger { background-color: #ff3e1d !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bg-warning { background-color: #ffab00 !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    /* Ensure Sl. No column is narrow and centered in exports */
    .table th:first-child, .table td:first-child { text-align: center !important; padding: 5px !important; width: 40px !important; }
}
</style>
@endsection

@section('page-script')
<script>
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print')) {
            setTimeout(function() {
                window.print();
            }, 800);
        }
    };

    // Close window after print dialog is closed (only if it was opened for printing and not in an iframe)
    window.addEventListener('afterprint', (event) => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print') && window.self === window.top) {
            window.close();
        }
    });

    function filterReport() {
        const month = document.getElementById('reportMonth').value;
        const year = document.getElementById('reportYear').value;
        window.location.href = `{{ route('admin.staff.attendance-report') }}?month=${month}&year=${year}&_ts=${Date.now()}`;
    }
</script>
@endsection
