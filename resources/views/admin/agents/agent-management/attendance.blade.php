@extends('layouts/layoutMaster')

@section('title', 'Agent Attendance')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/flatpickr/flatpickr.scss'])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold py-3 mb-0"><span class="text-muted fw-light">Agents /</span> Attendance</h4>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="nav-align-top mb-4">
                <ul class="nav nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-daily" aria-controls="navs-daily" aria-selected="true">
                            <i class="ri-calendar-check-line me-1"></i> Daily Marking
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-report" aria-controls="navs-report" aria-selected="false">
                            <i class="ri-file-list-3-line me-1"></i> Attendance Report
                        </button>
                    </li>
                </ul>
                <div class="tab-content border-0 bg-transparent p-0 pt-4">
                    
                    <!-- Tab 1: Daily Marking -->
                    <div class="tab-pane fade show active" id="navs-daily" role="tabpanel">
                        <div class="card border">
                            <div class="card-header border-bottom d-flex justify-content-between align-items-center bg-light">
                                <div class="d-flex align-items-center gap-2">
                                    <div>
                                        <h6 class="mb-0 text-dark fw-bold">Attendance for {{ date('d-m-Y', strtotime($date)) }}</h6>
                                        @if($date < date('Y-m-d'))
                                            <span class="badge bg-label-danger mt-1"><i class="ri-lock-line me-1"></i> Locked</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-success" id="markAllPresent">
                                                <i class="ri-check-double-line me-1"></i> All Present
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" id="markAllAbsent">
                                                <i class="ri-close-circle-line me-1"></i> All Absent
                                            </button>
                                        </div>
                                        <button type="button" class="btn btn-success btn-sm shadow-sm" id="saveAllAttendance" disabled>
                                            <i class="ri-save-line me-1"></i> Save All
                                        </button>
                                        <div class="d-flex align-items-center gap-1 ms-2">
                                            <label class="small fw-medium me-1">Date:</label>
                                            <input type="text" id="attendanceDate" class="form-control form-control-sm" style="width: 130px;" value="{{ $date }}" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Agent Name</th>
                                            <th class="text-center">Status</th>
                                            <th>Remarks</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">
                                        @forelse($dailyAgents as $agent)
                                        @php
                                            $attendance = $dailyAttendances->get($agent->id);
                                            $status = $attendance ? $attendance->status : 'present';
                                            $editCount = $attendance ? $attendance->edit_count : 0;
                                            $isLocked = $date < date('Y-m-d');
                                            $limitReached = $editCount >= 2;
                                            $isDisabled = $isLocked || $limitReached;
                                        @endphp
                                        <tr data-agent-id="{{ $agent->id }}" class="{{ $isDisabled ? 'table-light' : '' }}">
                                            <td class="ps-4">
                                                <span class="fw-medium text-dark">{{ $agent->agent_name }}</span>
                                                <small class="d-block text-muted">{{ $agent->agent_code }}</small>
                                                @if($limitReached && !$isLocked)
                                                    <small class="text-danger">Edit limit reached (2/2)</small>
                                                @elseif($attendance && !$isLocked)
                                                    <small class="text-muted">Edits: {{ $editCount }}/2</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-3">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input status-radio" type="radio" name="status_{{ $agent->id }}" id="present_{{ $agent->id }}" value="present" {{ $status == 'present' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                                        <label class="form-check-label text-success" for="present_{{ $agent->id }}">Present</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input status-radio" type="radio" name="status_{{ $agent->id }}" id="absent_{{ $agent->id }}" value="absent" {{ $status == 'absent' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                                        <label class="form-check-label text-danger" for="absent_{{ $agent->id }}">Absent</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input status-radio" type="radio" name="status_{{ $agent->id }}" id="half_day_{{ $agent->id }}" value="half_day" {{ $status == 'half_day' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                                        <label class="form-check-label text-warning" for="half_day_{{ $agent->id }}">Half</label>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm remarks-input" id="remarks_{{ $agent->id }}" value="{{ $attendance->remarks ?? '' }}" placeholder="Note..." {{ $isDisabled ? 'disabled' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                @if($isDisabled)
                                                    <span class="badge bg-label-secondary"><i class="ri-lock-line me-1"></i> Locked</span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-dark save-attendance" data-agent-id="{{ $agent->id }}">
                                                        <i class="ri-save-line me-1"></i> Save
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No agents found</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Attendance Report -->
                    <div class="tab-pane fade" id="navs-report" role="tabpanel">
                        <div class="card border">
                            <div class="card-header border-bottom d-flex justify-content-between align-items-center bg-light">
                                <h6 class="mb-0 text-dark fw-bold">Monthly Status Grid - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h6>
                                <div class="d-flex gap-2">
                                    <select id="repMonth" class="form-select form-select-sm w-auto">@foreach(range(1,12) as $m)<option value="{{sprintf('%02d',$m)}}" {{$month==$m?'selected':''}}>{{date('M', mktime(0,0,0,$m,1))}}</option>@endforeach</select>
                                    <select id="repYear" class="form-select form-select-sm w-auto">@foreach(range(date('Y')-1, date('Y')) as $y)<option value="{{$y}}" {{$year==$y?'selected':''}}>{{$y}}</option>@endforeach</select>
                                    <button type="button" class="btn btn-dark btn-sm" onclick="filterReport()">Filter Report</button>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Export">
                                            <i class="ri-download-2-line"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('agent-management.exportAttendance') }}?month={{$month}}&year={{$year}}"><i class="ri-file-excel-2-line me-2 text-success"></i> Excel / CSV</a></li>
                                            <li><a class="dropdown-item" href="{{ route('agent-management.exportAttendancePDF') }}?month={{$month}}&year={{$year}}"><i class="ri-file-pdf-line me-2 text-danger"></i> PDF Document</a></li>
                                        </ul>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printReport('{{$month}}', '{{$year}}')"><i class="ri-printer-line"></i></button>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 500px;">
                                <table class="table table-bordered table-sm m-0" style="font-size: 11px;">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-2" style="min-width: 150px;">Agent</th>
                                            <th class="text-center">Action</th>
                                            @for($d=1; $d<=$daysInMonth; $d++) <th class="text-center" style="width: 25px;">{{$d}}</th> @endfor
                                            <th class="text-center text-success fw-bold">P</th>
                                            <th class="text-center text-danger fw-bold">A</th>
                                            <th class="text-center text-warning fw-bold">H</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dailyAgents as $ag)
                                        @php $agAtt = $monthlyAttendances->get($ag->id) ?? collect(); @endphp
                                        <tr data-agent-id="{{ $ag->id }}">
                                            <td class="ps-2 fw-medium text-dark">{{$ag->agent_name}}</td>
                                            <td class="text-center">
                                                <a href="{{ route('agent-management.view', $ag->id) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View Profile">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            </td>
                                            @php 
                                                $indexedAtt = $agAtt->keyBy(function($a) {
                                                    $d = is_string($a->date) ? \Carbon\Carbon::parse($a->date) : $a->date;
                                                    return $d ? $d->format('Y-m-d') : '';
                                                });
                                            @endphp
                                            @for($d=1; $d<=$daysInMonth; $d++)
                                                @php 
                                                    $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                                                    $stat = $indexedAtt->get($dateStr)->status ?? null;
                                                    $bg = $stat=='present'?'bg-label-success':($stat=='absent'?'bg-label-danger':($stat=='half_day'?'bg-label-warning':''));
                                                @endphp
                                                <td class="text-center {{$bg}}" title="{{ $stat ? ucfirst($stat) : 'No Record' }}">{{$stat?strtoupper(substr($stat,0,1)):''}}</td>
                                            @endfor
                                            <td class="text-center fw-bold text-success">{{$agAtt->where('status','present')->count()}}</td>
                                            <td class="text-center fw-bold text-danger">{{$agAtt->where('status','absent')->count()}}</td>
                                            <td class="text-center fw-bold text-warning">{{$agAtt->where('status','half_day')->count()}}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page-script')
@vite(['resources/assets/vendor/libs/flatpickr/flatpickr.js'])
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function updateMonthlyStatusGridCell(agentId, status, remarks, attDate) {
        const monthlyTableEl = document.querySelector('#navs-report table');
        if (!monthlyTableEl || !attDate) return;

        // Parse date reliably
        const dateParts = attDate.split('-');
        if (dateParts.length !== 3) return;
        const year = parseInt(dateParts[0]);
        const month = parseInt(dateParts[1]);
        const dayOfMonth = parseInt(dateParts[2]);

        const selectedMonth = Number(document.getElementById('repMonth')?.value || 0);
        const selectedYear = Number(document.getElementById('repYear')?.value || 0);
        
        if (selectedMonth !== month || selectedYear !== year) return;

        // Find the row in the monthly table specifically
        const row = monthlyTableEl.querySelector(`tbody tr[data-agent-id="${agentId}"]`);
        if (!row) return;

        // Column mapping: Agent (0), Action (1), Day 1 (2), ...
        const dayCell = row.cells[dayOfMonth + 1];
        if (!dayCell) return;

        dayCell.classList.remove('bg-label-success', 'bg-label-danger', 'bg-label-warning');
        let badgeClass = '';
        let shortCode = '';
        if (status === 'present') {
            badgeClass = 'bg-label-success';
            shortCode = 'P';
        } else if (status === 'absent') {
            badgeClass = 'bg-label-danger';
            shortCode = 'A';
        } else if (status === 'half_day') {
            badgeClass = 'bg-label-warning';
            shortCode = 'H';
        }

        if (badgeClass) dayCell.classList.add(badgeClass);
        dayCell.textContent = shortCode;
        dayCell.title = `${status ? status.charAt(0).toUpperCase() + status.slice(1) : 'No Record'}${remarks ? ' - ' + remarks : ''}`;

        // Update counts (P, A, H are the last 3 cells)
        const totalCells = row.cells.length;
        const pCell = row.cells[totalCells - 3];
        const aCell = row.cells[totalCells - 2];
        const hCell = row.cells[totalCells - 1];

        // Recalculate counts from the current row state
        let pCount = 0, aCount = 0, hCount = 0;
        for (let i = 2; i < totalCells - 3; i++) {
            const val = row.cells[i].textContent.trim();
            if (val === 'P') pCount++;
            else if (val === 'A') aCount++;
            else if (val === 'H') hCount++;
        }

        if (pCell) pCell.textContent = pCount;
        if (aCell) aCell.textContent = aCount;
        if (hCell) hCell.textContent = hCount;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Enable Save All on any change
        $(document).on('change', '.status-radio', function() {
            document.getElementById('saveAllAttendance').disabled = false;
        });

        $(document).on('input', '.remarks-input', function() {
            document.getElementById('saveAllAttendance').disabled = false;
        });

        // Tab Persistence
        const activeTab = sessionStorage.getItem('agentAttendanceActiveTab');
        if (activeTab) {
            const triggerEl = document.querySelector(`button[data-bs-target="${activeTab}"]`);
            if (triggerEl) {
                new bootstrap.Tab(triggerEl).show();
            }
        }

        const tabLinks = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabLinks.forEach(link => {
            link.addEventListener('shown.bs.tab', function(e) {
                sessionStorage.setItem('agentAttendanceActiveTab', e.target.getAttribute('data-bs-target'));
            });
        });

        // If URL has month/year params, show report tab
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('month') || urlParams.has('year')) {
            const reportTab = document.querySelector('button[data-bs-target="#navs-report"]');
            if (reportTab) new bootstrap.Tab(reportTab).show();
        }

        // Init flatpickr for daily date
        const flatpickrDate = document.querySelector('#attendanceDate');
        if (flatpickrDate) {
            flatpickrDate.flatpickr({
                altInput: true,
                altFormat: 'F j, Y',
                dateFormat: 'Y-m-d',
                onChange: function(selectedDates, dateStr) {
                    window.location.href = "{{ route('agent-management.attendance') }}?date=" + dateStr;
                }
            });
        }

        // Save Single Attendance
        document.querySelectorAll('.save-attendance').forEach(btn => {
            btn.addEventListener('click', function() {
                const agentId = this.dataset.agentId;
                const statusInput = document.querySelector(`input[name="status_${agentId}"]:checked`);
                if (!statusInput) return;
                
                const status = statusInput.value;
                const remarks = document.querySelector(`#remarks_${agentId}`).value;
                const date = "{{ $date }}";
                const btnSave = this;

                btnSave.disabled = true;
                btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch("{{ route('agent-management.markAttendance') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ agent_id: agentId, date, status, remarks })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved!',
                            text: 'Attendance saved',
                            timer: 1500,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                        btnSave.classList.replace('btn-dark', 'btn-success');
                        btnSave.innerHTML = '<i class="ri-check-line"></i>';
                        btnSave.disabled = false;
                        
                        updateMonthlyStatusGridCell(agentId, status, remarks, date);

                        // Revert button after 2 seconds
                        setTimeout(() => {
                            btnSave.classList.replace('btn-success', 'btn-dark');
                            btnSave.innerHTML = '<i class="ri-save-line me-1"></i> Save';
                        }, 2000);
                    }
                })
                .catch(err => {
                    console.error('Save Error:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: err.message || 'Failed to save attendance.'
                    });
                    btnSave.disabled = false;
                    btnSave.innerHTML = '<i class="ri-save-line me-1"></i> Save';
                });
            });
        });

        // Save All Attendance
        const btnSaveAll = document.getElementById('saveAllAttendance');
        if (btnSaveAll) {
            btnSaveAll.addEventListener('click', function() {
                const date = "{{ $date }}";
                const attendances = [];
                
                document.querySelectorAll('tr[data-agent-id]').forEach(row => {
                    const agentId = row.dataset.agentId;
                    if (!agentId) return; // Skip report rows if they are present in DOM
                    
                    const statusInput = row.querySelector(`input[name="status_${agentId}"]:checked`);
                    const remarksInput = row.querySelector(`#remarks_${agentId}`);
                    
                    if (statusInput && !statusInput.disabled) {
                        attendances.push({
                            agent_id: agentId,
                            status: statusInput.value,
                            remarks: remarksInput ? remarksInput.value : ''
                        });
                    }
                });

                if (attendances.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No data',
                        text: 'No attendance records found to save.'
                    });
                    return;
                }

                btnSaveAll.disabled = true;
                const originalText = btnSaveAll.innerHTML;
                btnSaveAll.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

                fetch("{{ route('agent-management.bulkMarkAttendance') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ date, attendances })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Bulk Saved!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Failed to save attendance');
                    }
                })
                .catch(err => {
                    console.error('Bulk Save Error:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: err.message || 'Failed to save attendance. Please try again.'
                    });
                    btnSaveAll.disabled = false;
                    btnSaveAll.innerHTML = originalText;
                });
            });
        }

        // Mark All Present
        const btnAllPresent = document.getElementById('markAllPresent');
        if (btnAllPresent) {
            btnAllPresent.addEventListener('click', function() {
                document.querySelectorAll('.status-radio[value="present"]').forEach(radio => {
                    if (!radio.disabled) radio.checked = true;
                });
                document.getElementById('saveAllAttendance').disabled = false;
                Swal.fire({
                    icon: 'info',
                    title: 'Updated',
                    text: 'All agents marked as Present (Click Save All to persist)',
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        }

        // Mark All Absent
        const btnAllAbsent = document.getElementById('markAllAbsent');
        if (btnAllAbsent) {
            btnAllAbsent.addEventListener('click', function() {
                document.querySelectorAll('.status-radio[value="absent"]').forEach(radio => {
                    if (!radio.disabled) radio.checked = true;
                });
                document.getElementById('saveAllAttendance').disabled = false;
                Swal.fire({
                    icon: 'info',
                    title: 'Updated',
                    text: 'All agents marked as Absent (Click Save All to persist)',
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            });
        }
    });

    function filterReport() {
        const m = document.getElementById('repMonth').value;
        const y = document.getElementById('repYear').value;
        window.location.href = `{{ route('agent-management.attendance') }}?month=${m}&year=${y}`;
    }

    function printReport(month, year) {
        const url = `{{route('agent-management.printAttendance')}}?month=${month}&year=${year}&print=true`;
        
        let printIframe = document.getElementById('print_iframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'print_iframe';
            printIframe.style.position = 'fixed';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            printIframe.style.width = '0';
            printIframe.style.height = '0';
            printIframe.style.border = '0';
            document.body.appendChild(printIframe);
        }
        
        Swal.fire({
            title: 'Preparing Print...',
            html: 'Generating attendance report...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        printIframe.src = url;
        printIframe.onload = function() {
            Swal.close();
            try {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            } catch (e) {
                console.error("Print failed", e);
                window.open(url, '_blank');
            }
        };
    }
</script>
@endsection
