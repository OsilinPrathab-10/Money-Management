@extends('layouts/layoutMaster')

@section('title', 'Staff Management Dashboard')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
        'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
        'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
        'resources/assets/vendor/libs/select2/select2.scss',
        'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
    ])
@endsection

@section('vendor-script')
    @vite([
        'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
        'resources/assets/vendor/libs/select2/select2.js',
        'resources/assets/vendor/libs/flatpickr/flatpickr.js',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-3">
        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Dashboard /</span> HRM Management</h4>
        <div class="nav-align-top">
            <ul class="nav nav-pills gap-2" role="tablist">
                <li class="nav-item">
                    <a href="{{ route('staff-management.directory') }}" 
                       class="nav-link fw-bold px-4 py-2 active bg-primary text-white shadow-sm rounded-pill" style="font-size: 14px;">
                        <i class="ri-team-line me-1"></i> Staff Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('agent-management.index') }}" 
                       class="nav-link fw-bold px-4 py-2 text-muted bg-transparent border border-light rounded-pill" style="font-size: 14px;">
                        <i class="ri-user-star-line me-1"></i> Agent Management
                    </a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '{{ session('success') }}',
                    timer: 2000,
                    showConfirmButton: false,
                    position: 'center'
                });
            });
        </script>
    @endif

    @if(session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '{{ session('error') }}',
                    showConfirmButton: true,
                    position: 'center'
                });
            });
        </script>
    @endif

    @if($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: '<ul class="text-start mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
                    showConfirmButton: true,
                    position: 'center'
                });
            });
        </script>
    @endif

    @php
        $currentTab = request('tab', 'staff');
        $currentSubtab = request('subtab', '');
        $isAttendance = $currentTab === 'attendance';
    @endphp

    <div class="row">
        <div class="col-12">
            <!-- Submenu navigation (replaces tabs) -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="{{ route('staff-management.directory') }}"
                           class="btn btn-sm {{ $currentTab === 'staff' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-team-line me-1"></i> Staff Directory
                        </a>

                        <a href="{{ route('staff-management.directory', ['tab' => 'agent']) }}"
                           class="btn btn-sm {{ $currentTab === 'agent' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-user-star-line me-1"></i> Agent Directory
                        </a>

                        <a href="{{ route('staff-management.attendance.daily', ['date' => request('date')]) }}"
                           class="btn btn-sm {{ $isAttendance ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-calendar-check-line me-1"></i> Attendance
                        </a>

                        <a href="{{ route('staff-management.payroll') }}"
                           class="btn btn-sm {{ $currentTab === 'payroll' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-money-dollar-circle-line me-1"></i> Payroll
                        </a>

                        <a href="{{ route('staff-management.branches') }}"
                           class="btn btn-sm {{ $currentTab === 'branches' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-bank-line me-1"></i> Branches
                        </a>

                        <!-- <a href="{{ route('staff-management.roles') }}"
                           class="btn btn-sm {{ $currentTab === 'roles' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-shield-user-line me-1"></i> Roles List
                        </a> -->

                        <a href="{{ route('staff-management.holidays') }}"
                           class="btn btn-sm {{ $currentTab === 'holidays' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-palanquin-line me-1"></i> Holidays
                        </a>

                        @if($isAttendance)
                            <div class="ms-auto d-flex flex-wrap gap-2">
                                <a href="{{ route('staff-management.attendance.daily', ['date' => request('date')]) }}"
                                   class="btn btn-sm {{ $currentSubtab === 'att-report' ? 'btn-outline-secondary' : 'btn-secondary' }}">
                                    Daily Marking
                                </a>
                                <a href="{{ route('staff-management.attendance.report', ['month' => request('month'), 'year' => request('year')]) }}"
                                   class="btn btn-sm {{ $currentSubtab === 'att-report' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                                    Monthly Grid
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="tab-content border-0 bg-transparent p-0 pt-0">
                    
                    <!-- Staff Directory -->
                    <div class="{{ $currentTab === 'staff' ? '' : 'd-none' }}" id="navs-staff">
                        <div class="card overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="mb-0 text-primary fw-bold">Employee List</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <select class="form-select form-select-sm w-auto" onchange="$('#staffTable').DataTable().page.len(this.value).draw()">
                                        <option value="10" selected>10 per page</option>
                                        <option value="25">25 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                    <input type="text" id="staffSearch" class="form-control form-control-sm" placeholder="Search staff..." onkeyup="searchTable('staffTable', 'staffSearch')" style="width: 200px;">
                                    <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                        <i class="ri-add-line me-1"></i> Add Staff
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover" id="staffTable">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th>SL NO</th>
                                            <th>Staff Member</th>
                                            <th>Branch & Role</th>
                                            <th>Contact</th>
                                            <th>Monthly Salary</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($staffs as $staff)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="d-flex justify-content-start align-items-center">
                                                    <div class="avatar avatar-md me-3">
                                                        @if($staff->profile_photo)
                                                            <img src="{{ asset('storage/' . $staff->profile_photo) }}" alt="Avatar" class="rounded-circle border">
                                                        @else
                                                            <span class="avatar-initial rounded-circle bg-label-primary">{{ strtoupper(substr($staff->name, 0, 1)) }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-dark">{{ $staff->name }}</span>
                                                        <small class="text-muted">ID: #ST{{ str_pad($staff->id, 4, '0', STR_PAD_LEFT) }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="small fw-medium text-dark">{{ $staff->branch->name ?? 'Head Office' }}</span>
                                                    @if($staff->user)
                                                        <span class="badge bg-label-info xsmall" style="font-size: 10px; width: fit-content;">{{ $staff->user->getRoleNames()->first() }}</span>
                                                    @elseif($staff->role)
                                                        <span class="badge bg-label-secondary xsmall" style="font-size: 10px; width: fit-content;">{{ $staff->role }}</span>
                                                    @else
                                                        <span class="text-muted xsmall">No Role</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <small class="d-block fw-medium">{{ $staff->phone }}</small>
                                                <small class="text-muted">{{ $staff->email ?? 'N/A' }}</small>
                                            </td>
                                            <td class="fw-bold text-success">₹{{ number_format($staff->salary_amount, 2) }}</td>
                                            <td>
                                                <span class="badge bg-label-{{ $staff->status == 'active' ? 'success' : 'danger' }} rounded-pill">{{ ucfirst($staff->status) }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-1">
                                                 <button class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Edit" onclick="editStaff({{ json_encode($staff) }}, '{{ $staff->user ? ($staff->user->getRoleNames()->first() ?? '') : '' }}')">
                                                        <i class="ri-edit-2-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-icon btn-label-danger shadow-sm" onclick="deleteStaff({{ $staff->id }})" title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Agent Directory -->
                    <div class="{{ $currentTab === 'agent' ? '' : 'd-none' }}" id="navs-agent">
                        <div class="card overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-label-success py-3">
                                <h5 class="mb-0 text-success fw-bold">Agent Directory</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <select class="form-select form-select-sm w-auto" onchange="$('#agentTable').DataTable().page.len(this.value).draw()">
                                        <option value="10" selected>10 per page</option>
                                        <option value="25">25 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                    <input type="text" id="agentSearch" class="form-control form-control-sm" placeholder="Search agent..." onkeyup="searchTable('agentTable', 'agentSearch')" style="width: 200px;">
                                    <button class="btn btn-success btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addAgentModal">
                                        <i class="ri-add-line me-1"></i> Add Agent
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover" id="agentTable">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th>SL NO</th>
                                            <th>Agent Member</th>
                                            <th>Branch</th>
                                            <th>Contact</th>
                                            <th>Monthly Salary</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($agents as $agent)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="d-flex justify-content-start align-items-center">
                                                    <div class="avatar avatar-md me-3">
                                                        @if($agent->profile_photo)
                                                            <img src="{{ asset('storage/' . $agent->profile_photo) }}" alt="Avatar" class="rounded-circle border">
                                                        @else
                                                            <span class="avatar-initial rounded-circle bg-label-success">{{ strtoupper(substr($agent->name, 0, 1)) }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-bold text-dark">{{ $agent->name }}</span>
                                                        <small class="text-muted">ID: #AG{{ str_pad($agent->id, 4, '0', STR_PAD_LEFT) }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-medium text-dark">{{ $agent->branch->name ?? 'Head Office' }}</span>
                                            </td>
                                            <td>
                                                <small class="d-block fw-medium">{{ $agent->phone }}</small>
                                                <small class="text-muted">{{ $agent->email ?? 'N/A' }}</small>
                                            </td>
                                            <td class="fw-bold text-success">₹{{ number_format($agent->salary_amount, 2) }}</td>
                                            <td>
                                                <span class="badge bg-label-{{ $agent->status == 'active' ? 'success' : 'danger' }} rounded-pill">{{ ucfirst($agent->status) }}</span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Edit" onclick="editStaff({{ json_encode($agent) }}, 'Agent')">
                                                        <i class="ri-edit-2-line"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-icon btn-label-danger shadow-sm" onclick="deleteStaff({{ $agent->id }})" title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Marking & Report -->
                    <div class="{{ $isAttendance ? '' : 'd-none' }}" id="navs-attendance">
                        <div class="nav-align-top">
                            <!-- Subtabs are handled by the submenu navigation above -->
                            <div class="tab-content bg-transparent p-0 shadow-none border-0">
                                <!-- Sub-tab: Daily Marking -->
                                <div class="{{ $currentSubtab === 'att-report' ? 'd-none' : '' }}" id="att-daily">
                                    <div class="card border">
                                        @php $isToday = ($date == date('Y-m-d')); @endphp
                                        <div class="card-header border-bottom d-flex justify-content-between align-items-center bg-light">
                                            <h6 class="mb-0 fw-bold">Attendance for {{ date('d-m-Y', strtotime($date)) }} @if($isToday) <span class="badge bg-label-primary ms-2 small">TODAY</span> @endif</h6>
                                            <div class="d-flex gap-2 align-items-center">
                                                <label class="small fw-bold me-1">Per Page:</label>
                                                <select id="perPageSelect" class="form-select form-select-sm w-auto me-2" onchange="$('#bulkAttTable').DataTable().page.len(this.value).draw()">
                                                    <option value="10">10</option>
                                                    <option value="25" selected>25</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                </select>
                                                <button class="btn btn-xs btn-outline-success" onclick="markAll('present')"><i class="ri-checkbox-circle-line me-1"></i> All Present</button>
                                                <button class="btn btn-xs btn-outline-danger" onclick="markAll('absent')"><i class="ri-close-circle-line me-1"></i> All Absent</button>
                                                <div class="d-flex align-items-center gap-1 ms-2">
                                                    <input type="date" id="attDate" class="form-control form-control-sm w-auto" value="{{ $date }}">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="changeAttDate(document.getElementById('attDate').value)" id="applyDateBtn">Apply</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="bulkAttTable">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAllStaff" onclick="toggleSelectAll(this)"></th>
                                                        <th>Sl No</th>
                                                        <th>Staff Name</th>
                                                        <th class="text-center">Status</th>
                                                        <th>Remarks</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($dailyStaffs as $s)
                                                    @php 
                                                        $att = $dailyAttendances->get($s->id); 
                                                        $st = $att ? $att->status : 'present'; 
                                                        $ec = $att ? $att->edit_count : 0;
                                                        $isLocked = $date < date('Y-m-d');
                                                        $limitReached = $ec >= 2;
                                                        $isDisabled = $isLocked || $limitReached;
                                                    @endphp
                                                    <tr data-staff-id="{{ $s->id }}" class="{{ $isDisabled ? 'table-light' : '' }}">
                                                        <td class="text-center"><input type="checkbox" class="form-check-input staff-checkbox" value="{{ $s->id }}" {{ $isDisabled ? 'disabled' : '' }}></td>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td class="fw-medium text-dark">
                                                            {{ $s->name }}
                                                            @if($limitReached && !$isLocked)
                                                                <small class="d-block text-danger">Edit limit reached (2/2)</small>
                                                            @elseif($att && !$isLocked)
                                                                <small class="d-block text-muted">Edits: {{ $ec }}/2</small>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="d-flex justify-content-center gap-3">
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="radio" name="st_{{ $s->id }}" value="present" id="p_{{ $s->id }}" {{ $st == 'present' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                                                    <label class="form-check-label text-success small" for="p_{{ $s->id }}">P</label>
                                                                </div>
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="radio" name="st_{{ $s->id }}" value="absent" id="a_{{ $s->id }}" {{ $st == 'absent' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                                                    <label class="form-check-label text-danger small" for="a_{{ $s->id }}">A</label>
                                                                </div>
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="radio" name="st_{{ $s->id }}" value="half_day" id="h_{{ $s->id }}" {{ $st == 'half_day' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                                                    <label class="form-check-label text-warning small" for="h_{{ $s->id }}">H</label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><input type="text" class="form-control form-control-sm" id="rem_{{ $s->id }}" value="{{ $att->remarks ?? '' }}" placeholder="Note..." {{ $isDisabled ? 'disabled' : '' }}></td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                                <button type="button" class="btn btn-sm btn-icon btn-label-info shadow-sm" title="View History" onclick="viewHistory({{ $s->id }}, '{{ $s->name }}')"><i class="ri-eye-line"></i></button>
                                                                @if(!$isDisabled)
                                                                    <button type="button" class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Quick Save" onclick="saveAtt({{ $s->id }})"><i class="ri-save-line"></i></button>
                                                                @else
                                                                    <span class="badge bg-label-secondary"><i class="ri-lock-line"></i></span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="card-footer border-top bg-light p-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="ri-error-warning-line me-1"></i> @if($isToday) Marking enabled for today. @else Attendance management for {{ date('d-m-Y', strtotime($date)) }}. @endif</small>
                                                <button type="button" class="btn btn-primary px-4 shadow" onclick="saveBulkAtt()" id="saveAllAttendance" disabled>
                                                    <i class="ri-save-3-line me-1"></i> Save All Attendance
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <!-- Sub-tab: Monthly Report -->
                                <div class="{{ $currentSubtab === 'att-report' ? '' : 'd-none' }}" id="att-report">
                                    <div class="card border">
                                        <div class="card-header border-bottom d-flex justify-content-between align-items-center bg-light">
                                            <h6 class="mb-0 text-dark">Staff Attendance Grid - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h6>
                                            <div class="d-flex gap-2 align-items-center">
                                                <select class="form-select form-select-sm w-auto" onchange="$('#monthlyAttTable').DataTable().page.len(this.value).draw()">
                                                    <option value="10">10 per page</option>
                                                    <option value="25" selected>25 per page</option>
                                                    <option value="50">50 per page</option>
                                                    <option value="100">100 per page</option>
                                                </select>
                                                <select id="repMonth" class="form-select form-select-sm w-auto">@foreach(range(1,12) as $m)<option value="{{sprintf('%02d',$m)}}" {{$month==$m?'selected':''}}>{{date('M', mktime(0,0,0,$m,1))}}</option>@endforeach</select>
                                                <select id="repYear" class="form-select form-select-sm w-auto">@foreach(range(date('Y')-1, date('Y')) as $y)<option value="{{$y}}" {{$year==$y?'selected':''}}>{{$y}}</option>@endforeach</select>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="filterReport()" id="updateViewBtn"><i class="ri-refresh-line me-1"></i> Update View</button>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="ri-download-line me-1"></i> Export
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="exportReport()">CSV format</a></li>
                                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="exportReportPDF()">PDF format</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printReport('{{$month}}', '{{$year}}')"><i class="ri-printer-line"></i></button>
                                            </div>
                                        </div>
                                        <div class="table-responsive" style="max-height: 500px;">
                                            <table class="table table-bordered table-sm m-0" id="monthlyAttTable" style="font-size: 11px;">
                                                <thead class="bg-light sticky-top">
                                                    <tr>
                                                        <th class="text-center" style="width: 50px;">SL NO</th>
                                                        <th class="ps-2" style="min-width: 120px;">Staff Name</th>
                                                        @for($d=1; $d<=$daysInMonth; $d++) <th class="text-center" style="width: 25px;">{{$d}}</th> @endfor
                                                        <th class="text-center text-success fw-bold">P</th>
                                                        <th class="text-center text-danger fw-bold">A</th>
                                                        <th class="text-center text-warning fw-bold">H</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($dailyStaffs as $s)
                                                    @php $staffAtt = $monthlyAttendances->get($s->id) ?? collect(); @endphp
                                                    <tr data-staff-id="{{ $s->id }}">
                                                        <td class="text-center">{{ $loop->iteration }}</td>
                                                        <td class="ps-2 fw-medium text-dark">{{$s->name}}</td>
                                                        @php 
                                                            $indexedAtt = $staffAtt->keyBy(function($a) {
                                                                try {
                                                                    $d = is_string($a->date) ? \Carbon\Carbon::parse($a->date) : $a->date;
                                                                    return $d ? $d->format('Y-m-d') : '';
                                                                } catch (\Exception $e) { return ''; }
                                                            });
                                                        @endphp
                                                        @for($d=1; $d<=$daysInMonth; $d++)
                                                            @php 
                                                                $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                                                                $attRecord = $indexedAtt->get($dateStr);
                                                                $stat = $attRecord->status ?? null;
                                                                $bg = $stat=='present'?'bg-label-success':($stat=='absent'?'bg-label-danger':($stat=='half_day'?'bg-label-warning':''));
                                                            @endphp
                                                            <td class="text-center {{$bg}}" title="{{ $stat ? ucfirst($stat) : 'No Record' }}{{ ($attRecord && $attRecord->remarks) ? ' - ' . $attRecord->remarks : '' }}">
                                                                {{$stat?strtoupper(substr($stat,0,1)):''}}
                                                            </td>
                                                        @endfor
                                                        <td class="text-center fw-bold text-success">{{$staffAtt->where('status','present')->count()}}</td>
                                                        <td class="text-center fw-bold text-danger">{{$staffAtt->where('status','absent')->count()}}</td>
                                                        <td class="text-center fw-bold text-warning">{{$staffAtt->where('status','half_day')->count()}}</td>
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

                    <!-- Payroll -->
                    <div class="{{ $currentTab === 'payroll' ? '' : 'd-none' }}" id="navs-payroll">
                        <div class="card border">
                            <div class="card-header border-bottom d-flex justify-content-between align-items-center bg-label-primary">
                                <h5 class="mb-0 text-dark">Staff Payroll Overview</h5>
                                <a href="{{ route('admin.staff.payroll') }}" class="btn btn-primary btn-sm">Full Payroll Portal <i class="ri-arrow-right-line ms-1"></i></a>
                            </div>
                            <div class="card-body py-5 text-center">
                                <div class="avatar avatar-xl bg-label-success shadow-sm mb-4">
                                    <i class="ri-money-dollar-box-line fs-1"></i>
                                </div>
                                <h5 class="fw-bold">Manage Salaries & Expenses</h5>
                                <p class="text-muted mx-auto" style="max-width: 500px;">Track monthly earnings, calculate deductions for leaves, manage advance payments, and reimburse travel or petrol expenses with itemized payslips.</p>
                                <div class="d-flex justify-content-center gap-3 mt-4">
                                    <div class="p-3 border rounded text-center" style="width: 140px;">
                                        <h4 class="mb-0 text-primary">₹{{ number_format($staffs->sum('salary_amount')) }}</h4>
                                        <small class="text-muted">Est. Liability</small>
                                    </div>
                                    <div class="p-3 border rounded text-center" style="width: 140px;">
                                        <h4 class="mb-0 text-warning">{{ $dailyStaffs->count() }}</h4>
                                        <small class="text-muted">Active Staff</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Branches -->
                    <div class="{{ $currentTab === 'branches' ? '' : 'd-none' }}" id="navs-branches">
                        <div class="card overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="mb-0 text-primary fw-bold">Company Branches</h5>
                                <button class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                                    <i class="ri-add-line me-1"></i> New Branch
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th>Branch Name</th>
                                            <th>Office Location</th>
                                            <th>Employee Strength</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($branches as $branch)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $branch->name }}</td>
                                            <td>{{ $branch->location ?? 'N/A' }}</td>
                                            <td><span class="badge bg-label-info">{{ $branch->staffs()->count() }} Members</span></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-icon text-primary shadow-sm me-1" onclick="editBranch({{ json_encode($branch) }})"><i class="ri-edit-2-line"></i></button>
                                                <form action="{{ route('admin.staff.branches.delete', $branch->id) }}" method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-icon text-danger shadow-sm" onclick="return confirm('Are you sure?')"><i class="ri-delete-bin-line" style="pointer-events: none;"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr><td colspan="4" class="text-center py-4">No branches found. Click 'New Branch' to add.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Roles Summary -->
                    <div class="{{ $currentTab === 'roles' ? '' : 'd-none' }}" id="navs-roles">
                        <div class="row g-4">
                            @foreach($rolesSummary as $role)
                            <div class="col-md-4">
                                <div class="card shadow-sm border h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="avatar bg-label-info p-2 rounded">
                                                <i class="ri-shield-user-line fs-4"></i>
                                            </div>
                                            <span class="badge bg-label-primary px-3">{{ $role->users_count }} Users</span>
                                        </div>
                                        <h6 class="mb-2 fw-bold text-dark">{{ $role->name }}</h6>
                                        <p class="small text-muted mb-0">System access level with predefined permissions for this role.</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-5 text-center p-4 border rounded bg-light">
                            <h6>Need to modify permissions?</h6>
                            <a href="{{ route('role-users') }}" class="btn btn-label-primary btn-sm mt-2 px-4 shadow-sm">Manage Global Roles & Permissions</a>
                        </div>
                    </div>

                    <!-- Holidays -->
                    <div class="{{ $currentTab === 'holidays' ? '' : 'd-none' }}" id="navs-holidays">
                        <div class="card border overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-light">
                                <h5 class="mb-0 text-dark">Office Holidays</h5>
                                <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                                    <i class="ri-add-line me-1"></i> Add Holiday
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Holiday Description</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($holidays as $holiday)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ date('D, d-m-Y', strtotime($holiday->date)) }}</td>
                                            <td>{{ $holiday->name }}</td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-icon btn-label-primary me-1" onclick="editHoliday({{ json_encode($holiday) }})"><i class="ri-edit-2-line"></i></button>
                                                <form action="{{ route('admin.staff.holidays.delete', $holiday->id) }}" method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-icon btn-label-danger" onclick="return confirm('Are you sure?')"><i class="ri-delete-bin-line" style="pointer-events: none;"></i></button>
                                                </form>
                                            </td>
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

<!-- Modal: Add Staff -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-2">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-primary">Add New Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.staff.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
                    @csrf
                    <div class="col-md-6">
                        <div class="p-3 bg-label-primary rounded mb-3">
                            <h6 class="fw-bold mb-2">Assignment Details</h6>
                            <label class="form-label small">Branch Office <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select mb-3 shadow-sm" required>
                                <option value="" selected>Select Branch</option>
                                <option value="0">Main Office</option>
                                @foreach($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                            </select>
                            <label class="form-label small">System Role (Permissions) <span class="text-danger">*</span></label>
                            <select name="role" class="form-select shadow-sm" required>
                                <option value="" selected>Select Role</option>
                                <option value="0">No Login Rights</option>
                                @foreach($roles as $role)
                                    @if($role->name !== 'Agent')
                                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <label class="form-label small fw-bold">Profile Picture</label>
                        <input type="file" name="profile_photo" class="form-control form-control-sm mb-3 shadow-sm" accept="image/*" />
                    </div>
                    <div class="col-md-6 border-start ps-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Personal Information</h6>
                        <div class="mb-3">
                            <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control shadow-sm" required pattern="[A-Za-z\s]+" title="Only alphabets and spaces are allowed" oninput="this.value=this.value.replace(/[^A-Za-z\s]/g,'')" />
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="phone">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" id="phone" class="form-control" name="phone" placeholder="10 Digit Mobile Number" required minlength="10" maxlength="10" pattern="[6-9]\d{9}" title="Please enter a valid 10-digit mobile number starting with 6-9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email Address</label>
                            <input type="email" name="email" class="form-control shadow-sm" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Base Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group input-group-merge shadow-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="salary_amount" class="form-control" required />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 text-end pt-3 border-top">
                        <button type="reset" class="btn btn-label-secondary me-2">Clear</button>
                        <button type="submit" class="btn btn-primary px-5 shadow">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Agent -->
<div class="modal fade" id="addAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-2">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-success">Add New Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.staff.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
                    @csrf
                    <input type="hidden" name="role" value="Agent">
                    <div class="col-md-6">
                        <div class="p-3 bg-label-success rounded mb-3">
                            <h6 class="fw-bold mb-2">Assignment Details</h6>
                            <label class="form-label small">Branch Office <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select mb-3 shadow-sm" required>
                                <option value="" selected>Select Branch</option>
                                <option value="0">Main Office</option>
                                @foreach($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                            </select>
                            <span class="badge bg-success">Role: Agent User</span>
                        </div>
                        <label class="form-label small fw-bold">Profile Picture</label>
                        <input type="file" name="profile_photo" class="form-control form-control-sm mb-3 shadow-sm" accept="image/*" />
                    </div>
                    <div class="col-md-6 border-start ps-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Personal Information</h6>
                        <div class="mb-3">
                            <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control shadow-sm" required pattern="[A-Za-z\s]+" title="Only alphabets and spaces are allowed" oninput="this.value=this.value.replace(/[^A-Za-z\s]/g,'')" />
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="agent_phone">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" id="agent_phone" class="form-control" name="phone" placeholder="10 Digit Mobile Number" required minlength="10" maxlength="10" pattern="[6-9]\d{9}" title="Please enter a valid 10-digit mobile number starting with 6-9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email Address</label>
                            <input type="email" name="email" class="form-control shadow-sm" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Base Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group input-group-merge shadow-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="salary_amount" class="form-control" required />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 text-end pt-3 border-top">
                        <button type="reset" class="btn btn-label-secondary me-2">Clear</button>
                        <button type="submit" class="btn btn-success px-5 shadow">Save Agent Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Branch -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content shadow-lg border-0">
            <form action="{{ route('admin.staff.branches.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-label-primary text-white">
                    <h5 class="modal-title text-dark">Add Branch Office</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control shadow-sm" required placeholder="e.g. City Central">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control shadow-sm" required placeholder="Area/City">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100 shadow">Establish Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Holiday -->
<div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="{{ route('admin.staff.holidays.store') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title">Office Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. New Year">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100 shadow">Add to Calendar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Staff -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content p-2">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-primary">Edit Employee Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editStaffForm" method="POST" enctype="multipart/form-data" class="row g-4">
                    @csrf
                    <div class="col-md-6">
                        <div class="p-3 bg-label-info rounded mb-3">
                            <h6 class="fw-bold mb-2">Assignment & Status</h6>
                            <label class="form-label small">Branch Office <span class="text-danger">*</span></label>
                            <select name="branch_id" id="edit_branch_id" class="form-select mb-3 shadow-sm">
                                <option value="">Select Branch</option>
                                <option value="0">Main Office</option>
                                @foreach($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                            </select>
                            <label class="form-label small">System Role <span class="text-danger">*</span></label>
                            <select name="role" id="edit_role" class="form-select mb-3 shadow-sm">
                                <option value="">Select Role</option>
                                <option value="0">No Login Rights</option>
                                @foreach($roles as $role) <option value="{{ $role->name }}">{{ $role->name }}</option> @endforeach
                            </select>
                            <label class="form-label small"> Employment Status</label>
                            <select name="status" id="edit_status" class="form-select shadow-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <label class="form-label small fw-bold">Update Profile Picture</label>
                        <input type="file" name="profile_photo" class="form-control form-control-sm mb-3 shadow-sm" accept="image/*" />
                    </div>
                    <div class="col-md-6 border-start ps-4">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Personal Information</h6>
                        <div class="mb-3">
                            <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name" class="form-control shadow-sm" required pattern="[A-Za-z\s]+" title="Only alphabets and spaces are allowed" oninput="this.value=this.value.replace(/[^A-Za-z\s]/g,'')" />
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="edit_phone">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" id="edit_phone" class="form-control" name="phone" placeholder="10 Digit Mobile Number" required minlength="10" maxlength="10" pattern="[6-9]\d{9}" title="Please enter a valid 10-digit mobile number starting with 6-9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control shadow-sm" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Base Monthly Salary <span class="text-danger">*</span></label>
                            <div class="input-group input-group-merge shadow-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="salary_amount" id="edit_salary_amount" class="form-control" required />
                            </div>
                        </div>
                    </div>
                    <div class="col-12 text-end pt-3 border-top">
                        <button type="button" class="btn btn-label-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-5 shadow">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Branch -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content shadow-lg border-0">
            <form id="editBranchForm" method="POST">
                @csrf
                <div class="modal-header bg-label-info">
                    <h5 class="modal-title">Edit Branch Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_branch_name" class="form-control shadow-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" id="edit_branch_location" class="form-control shadow-sm" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info w-100 shadow">Update Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Holiday -->
<div class="modal fade" id="editHolidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="editHolidayForm" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title">Edit Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" id="edit_holiday_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_holiday_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100 shadow">Update Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
    /**
     * 1. GLOBAL FUNCTIONS (Defined first for immediate availability)
     */
    window.searchTable = function(tableId, inputId) {
        const input = document.getElementById(inputId);
        const filter = input.value.toUpperCase();
        const table = document.getElementById(tableId);
        const tr = table.getElementsByTagName("tr");
        for (let i = 1; i < tr.length; i++) {
            let found = false;
            const tds = tr[i].getElementsByTagName("td");
            for (let j = 0; j < tds.length; j++) {
                if (tds[j].textContent.toUpperCase().indexOf(filter) > -1) { found = true; break; }
            }
            tr[i].style.display = found ? "" : "none";
        }
    }

    window.changeAttDate = function(val) {
        localStorage.setItem('staffActiveTab', '#navs-attendance');
        localStorage.setItem('staffActiveSubTab', '#att-daily');
        window.location.href = `{{ route('admin.staff.index') }}?date=${val}&tab=attendance&subtab=att-daily&_ts=${Date.now()}#navs-attendance`;
    }

    window.filterReport = function() {
        const m = document.getElementById('repMonth').value;
        const y = document.getElementById('repYear').value;
        localStorage.setItem('staffActiveTab', '#navs-attendance');
        localStorage.setItem('staffActiveSubTab', '#att-report');
        window.location.href = `{{ route('admin.staff.index') }}?month=${m}&year=${y}&tab=attendance&subtab=att-report&_ts=${Date.now()}#navs-attendance`;
    }

    window.exportReport = function() {
        const month = $('#repMonth').val();
        const year = $('#repYear').val();
        window.location.href = `{{route('admin.staff.exportAttendance')}}?month=${month}&year=${year}`;
    }

    window.exportReportPDF = function() {
        const month = $('#repMonth').val();
        const year = $('#repYear').val();
        window.location.href = `/staff/export-attendance-pdf?month=${month}&year=${year}`;
    }

    window.printReport = function(month, year) {
        const url = `{{route('admin.staff.attendance-report')}}?month=${month}&year=${year}&print=true`;
        
        // Use a hidden iframe for seamless printing without visible redirect
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
                window.open(url, '_blank'); // Fallback
            }
        };
    }

    window.toggleSelectAll = function(master) {
        const isChecked = master.checked;
        if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#bulkAttTable')) {
            const table = $('#bulkAttTable').DataTable();
            table.rows().nodes().to$().each(function() {
                $(this).find('.staff-checkbox').prop('checked', isChecked);
            });
        } else {
            document.querySelectorAll('.staff-checkbox').forEach(cb => cb.checked = isChecked);
        }
    }

    window.markAll = function(status) {
        if (!window.attendanceState) return;
        
        Object.keys(window.attendanceState).forEach(sId => {
            window.attendanceState[sId].status = status;
        });

        if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('#bulkAttTable')) {
            const table = $('#bulkAttTable').DataTable();
            table.rows().nodes().to$().each(function() {
                const staffId = $(this).data('staff-id');
                if (staffId) {
                    const radio = $(this).find(`input[name="st_${staffId}"][value="${status}"]`);
                    if (radio.length) {
                        radio.prop('checked', true).trigger('change');
                    }
                }
            });
        } else {
            document.querySelectorAll(`input[name^="st_"][value="${status}"]`).forEach(radio => {
                radio.checked = true;
                $(radio).trigger('change');
            });
        }
        
        document.getElementById('saveAllAttendance').disabled = false;
        
        Swal.fire({
            icon: 'info',
            title: 'Updated',
            text: `All marked as ${status.charAt(0).toUpperCase() + status.slice(1)}. Click Save All to persist.`,
            timer: 1500, showConfirmButton: false, toast: true, position: 'top-end'
        });
    }

    window.saveAtt = function(staffId) {
        const row = document.querySelector(`tr[data-staff-id="${staffId}"]`);
        if (!row) return;
        const radio = row.querySelector(`input[name="st_${staffId}"]:checked`);
        if (!radio) return Swal.fire('Error', 'Please select a status', 'error');
        
        const status = radio.value;
        const remarks = document.getElementById(`rem_${staffId}`).value;

        fetch("{{ route('admin.staff.markAttendance') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ staff_id: staffId, date: "{{ $date }}", status: status, remarks: remarks })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Saved!', text: 'Attendance updated.', timer: 1000, showConfirmButton: false, toast: true, position: 'top-end' });
                const btn = row.querySelector('.btn-label-primary');
                if (btn) {
                    btn.classList.replace('btn-label-primary', 'btn-label-success');
                    btn.innerHTML = '<i class="ri-check-line"></i>';
                }
                updateMonthlyStatusGridCell(staffId, status, remarks, "{{ $date }}");
            } else {
                Swal.fire('Error', data.message || 'Failed to save', 'error');
            }
        });
    }

    function updateMonthlyStatusGridCell(staffId, status, remarks, attDate) {
        const monthlyTableEl = document.getElementById('monthlyAttTable');
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
        const row = monthlyTableEl.querySelector(`tbody tr[data-staff-id="${staffId}"]`);
        if (!row) return;

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

        // If it's a DataTable, update its internal cache so it doesn't revert on sort/paginate
        if (typeof $ !== 'undefined' && $.fn.DataTable && $.fn.DataTable.isDataTable('#monthlyAttTable')) {
            const dt = $('#monthlyAttTable').DataTable();
            dt.row(row).invalidate().draw(false);
        }
    }

    window.saveBulkAtt = function() {
        let toSave = [];
        Object.keys(window.attendanceState).forEach(sId => {
            toSave.push({
                staff_id: sId,
                status: window.attendanceState[sId].status || 'present',
                remarks: window.attendanceState[sId].remarks || ''
            });
        });

        if (toSave.length === 0) return Swal.fire('Info', 'No records found.', 'info');

        Swal.fire({
            title: 'Saving Attendance...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch("{{ route('admin.staff.bulkMarkAttendance') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ date: "{{ $date }}", attendances: toSave })
        })
        .then(async (res) => {
            let data = {};
            try {
                data = await res.json();
            } catch (e) {
                data = {};
            }
            if (!res.ok) {
                throw new Error(data.message || 'Failed to save attendance');
            }
            return data;
        })
        .then(data => {
            Swal.close();
            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Saved!', text: data.message, timer: 1500, showConfirmButton: false, toast: true, position: 'top-end' });
                try {
                    localStorage.setItem('staffActiveTab', '#navs-attendance');
                    localStorage.setItem('staffActiveSubTab', '#att-daily');
                    // Prefer DataTable AJAX reload if available, otherwise reload page
                    if (typeof monthlyAttTable !== 'undefined' && monthlyAttTable && monthlyAttTable.ajax && typeof monthlyAttTable.ajax.reload === 'function') {
                        monthlyAttTable.ajax.reload(null, false);
                    } else {
                        // Stay on Attendance Daily tab after save
                        window.location.reload();
                    }
                } catch (e) {
                    localStorage.setItem('staffActiveTab', '#navs-attendance');
                    localStorage.setItem('staffActiveSubTab', '#att-daily');
                    window.location.reload();
                }
            } else {
                Swal.fire('Error', data.message || 'Failed to save', 'error');
            }
        })
        .catch((error) => {
            Swal.close();
            Swal.fire('Error', error.message || 'Failed to save attendance', 'error');
        });
    }

    window.viewHistory = function(staffId, name) {
        const subTriggerEl = document.querySelector('button[data-bs-target="#att-report"]');
        if (subTriggerEl) {
            subTriggerEl.click();
            Swal.fire({ title: `${name}'s History`, timer: 2000, showConfirmButton: false, position: 'bottom-end', toast: true });
        }
    }

    window.editStaff = function(staff, role) {
        const form = document.getElementById('editStaffForm');
        form.action = `{{ url('staff') }}/${staff.id}/update`;
        document.getElementById('edit_name').value = staff.name || '';
        document.getElementById('edit_phone').value = staff.phone || '';
        document.getElementById('edit_email').value = staff.email || '';
        document.getElementById('edit_salary_amount').value = staff.salary_amount || 0;
        // Set branch: null/undefined means no specific branch selected -> leave empty so Main Office is default fallback
        const branchSelect = document.getElementById('edit_branch_id');
        branchSelect.value = staff.branch_id != null ? staff.branch_id : '';
        // Set role
        const roleSelect = document.getElementById('edit_role');
        roleSelect.value = role || '';
        if (roleSelect.value !== (role || '')) {
            roleSelect.value = ''; // Fallback to empty if value is not in the list
        }
        document.getElementById('edit_status').value = staff.status || 'active';
        // Destroy any existing modal instance to prevent stacking
        const modalEl = document.getElementById('editStaffModal');
        let existingModal = bootstrap.Modal.getInstance(modalEl);
        if (existingModal) existingModal.dispose();
        new bootstrap.Modal(modalEl).show();
    }

    window.editBranch = function(branch) {
        const form = document.getElementById('editBranchForm');
        form.action = `{{ url('staff/branches') }}/${branch.id}/update`;
        document.getElementById('edit_branch_name').value = branch.name;
        document.getElementById('edit_branch_location').value = branch.location;
        new bootstrap.Modal(document.getElementById('editBranchModal')).show();
    }

    window.editHoliday = function(holiday) {
        const form = document.getElementById('editHolidayForm');
        form.action = `{{ url('staff/holidays') }}/${holiday.id}/update`;
        document.getElementById('edit_holiday_date').value = holiday.date;
        document.getElementById('edit_holiday_name').value = holiday.name;
        new bootstrap.Modal(document.getElementById('editHolidayModal')).show();
    }

    window.deleteStaff = function(id) {
        const runDelete = () => {
            const deleteBtn = document.querySelector(`button[onclick="deleteStaff(${id})"]`);
            if (deleteBtn) deleteBtn.disabled = true;

            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch("{{ url('staff') }}/" + id + "/delete", {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message || 'Staff record has been deleted successfully.',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: false,
                        position: 'center'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    if (deleteBtn) deleteBtn.disabled = false;
                    Swal.fire('Error', data.message, 'error');
                }
            }).catch(() => {
                if (deleteBtn) deleteBtn.disabled = false;
                Swal.fire('Error', 'Failed to delete staff.', 'error');
            });
        };

        if (typeof Swal === 'undefined') {
            if (window.confirm('Are you sure you want to delete this staff record?')) runDelete();
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: "This will deactivate the associated user account!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                runDelete();
            }
        });
    }

    /**
     * 2. STATE INITIALIZATION
     */
    window.attendanceState = {
        @foreach($dailyStaffs as $s)
        "{{ $s->id }}": {
            status: "{{ optional($dailyAttendances->get($s->id))->status ?? 'present' }}",
            remarks: {!! json_encode(optional($dailyAttendances->get($s->id))->remarks ?? '') !!}
        },
        @endforeach
    };

    /**
     * 3. GLOBAL EVENT LISTENERS (deferred — jQuery needed)
     */
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $ !== 'undefined') {
            $(document).on('change', '#bulkAttTable input[type="radio"]', function() {
                const staffId = $(this).closest('tr').data('staff-id');
                if (staffId) {
                    if (!window.attendanceState[staffId]) window.attendanceState[staffId] = { remarks: '' };
                    window.attendanceState[staffId].status = $(this).val();
                    document.getElementById('saveAllAttendance').disabled = false;
                }
            });

            $(document).on('input', '#bulkAttTable input[type="text"]', function() {
                const staffId = $(this).closest('tr').data('staff-id');
                if (staffId) {
                    if (!window.attendanceState[staffId]) window.attendanceState[staffId] = {};
                    window.attendanceState[staffId].remarks = $(this).val();
                }
            });
        }
    });

    /**
     * 4. UI INITIALIZATION (DataTable & Tab Persistence)
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Staff Table Init
        var staffTable = $('#staffTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pageLength: 10,
            language: { search: "" },
            columnDefs: [{ orderable: false, targets: [0, 6] }],
            drawCallback: function() { 
                $('.dataTables_filter input').addClass('form-control form-control-sm').attr('placeholder', 'Search staff...'); 
                let info = this.api().page.info();
                this.api().column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                    cell.innerHTML = i + 1 + info.start;
                });
            }
        });

        // Agent Table Init
        var agentTable = $('#agentTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pageLength: 10,
            language: { search: "" },
            columnDefs: [{ orderable: false, targets: [0, 6] }],
            drawCallback: function() { 
                $('.dataTables_filter input').addClass('form-control form-control-sm').attr('placeholder', 'Search agent...'); 
                let info = this.api().page.info();
                this.api().column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                    cell.innerHTML = i + 1 + info.start;
                });
            }
        });

        // Bulk Attendance Table Init
        var bulkAttTable = $('#bulkAttTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: { search: "" },
            columnDefs: [{ orderable: false, targets: [0, 5] }], 
            drawCallback: function() { 
                $('.dataTables_filter input').addClass('form-control form-control-sm').attr('placeholder', 'Search marked staff...');
                $('#perPageSelect').val(this.api().page.len());
                let info = this.api().page.info();
                this.api().column(1, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                    cell.innerHTML = i + 1 + info.start;
                });
            }
        });

        // Monthly Grid Table Init
        var monthlyAttTable = $('#monthlyAttTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: { search: "" },
            ordering: false,
            drawCallback: function() {
                let info = this.api().page.info();
                this.api().column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
                    cell.innerHTML = i + 1 + info.start;
                });
            }
        });

        // Tab Persistence logic
        function activateTab(tabId, subTabId = null) {
            if (!tabId) return;
            const triggerEl = document.querySelector(`button[data-bs-target="${tabId}"]`);
            if (triggerEl) {
                triggerEl.click();
                
                if (tabId === '#navs-attendance' && subTabId) {
                    setTimeout(() => {
                        const subTriggerEl = document.querySelector(`button[data-bs-target="${subTabId}"]`);
                        if (subTriggerEl) subTriggerEl.click();
                    }, 150);
                }
            }
        }

        const hash = window.location.hash;
        const searchParams = new URLSearchParams(window.location.search);
        // URL drives the view now; keep storage logic only for legacy deep links.
        let activeTab = localStorage.getItem('staffActiveTab');
        let activeSubTab = localStorage.getItem('staffActiveSubTab');

        if (searchParams.get('tab') === 'attendance') {
            activeTab = '#navs-attendance';
            if (searchParams.get('subtab') === 'att-report') activeSubTab = '#att-report';
            if (searchParams.get('subtab') === 'att-daily') activeSubTab = '#att-daily';
        } else if (searchParams.get('tab') === 'payroll') {
            activeTab = '#navs-payroll';
            activeSubTab = null;
        } else if (searchParams.get('tab') === 'branches') {
            activeTab = '#navs-branches';
            activeSubTab = null;
        } else if (searchParams.get('tab') === 'roles') {
            activeTab = '#navs-roles';
            activeSubTab = null;
        } else if (searchParams.get('tab') === 'holidays') {
            activeTab = '#navs-holidays';
            activeSubTab = null;
        } else if (searchParams.get('tab') === 'agent') {
            activeTab = '#navs-agent';
            activeSubTab = null;
        } else if (searchParams.get('tab') === 'staff') {
            activeTab = '#navs-staff';
            activeSubTab = null;
        }

        let isInitialLoad = true;

        if (activeTab) {
            activateTab(activeTab, activeSubTab);
        }
        
        // Reset flag after a short delay to allow initial tab activations to complete
        setTimeout(() => { isInitialLoad = false; }, 500);

        // Listen for tab changes and persist them
        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
            btn.addEventListener('shown.bs.tab', e => {
                if (isInitialLoad) return; // Don't persist during initial automated activation

                const target = e.target.getAttribute('data-bs-target');
                if (target.startsWith('#navs-')) {
                    localStorage.setItem('staffActiveTab', target);
                    window.history.replaceState(null, null, target);
                    if (target !== '#navs-attendance') {
                        localStorage.removeItem('staffActiveSubTab');
                    }
                } else if (target.startsWith('#att-')) {
                    localStorage.setItem('staffActiveSubTab', target);
                }
            });
        });

        // Form Validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const phoneInput = this.querySelector('input[name="phone"]');
                if (phoneInput && phoneInput.value.length !== 10) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Mobile number must be 10 digits.' });
                }
            });
        });
    });
</script>
@endsection
