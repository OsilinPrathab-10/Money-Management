@extends('layouts/layoutMaster')

@section('title', 'Agent Management Dashboard')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
        'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
        'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
        'resources/assets/vendor/libs/select2/select2.scss',
        'resources/assets/vendor/libs/animate-css/animate.scss',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
    ])
@endsection

@section('vendor-script')
    @vite([
        'resources/assets/vendor/libs/moment/moment.js',
        'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
        'resources/assets/vendor/libs/select2/select2.js',
        'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('page-style')
<style>
    .container-xxl {
        max-width: 100% !important;
    }
    .nav-tabs-wrapper {
        position: relative;
        overflow: hidden;
    }
    .nav-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
        white-space: nowrap;
        scrollbar-width: none;
        -ms-overflow-style: none;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .nav-tabs::-webkit-scrollbar {
        display: none;
    }
    .nav-tabs .nav-link {
        padding: 0.75rem 1rem !important;
        font-size: 0.9rem;
    }
    .card-datatable.table-responsive {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    .datatables-agents, 
    #bulkAgentAttTable, 
    #monthlyAgentAttTable {
        width: 100% !important;
        min-width: 1000px;
        white-space: nowrap;
    }
</style>
@endsection

@section('page-script')
    @vite([
        'resources/assets/custom-js/agent-management.js',
        'resources/assets/custom-js/agent-collections.js'
    ])
<script>
    /**
     * 1. GLOBAL FUNCTIONS (Defined first for immediate availability)
     */
    window.changeAgentAttDate = function(val) {
        window.location.href = `{{ route('agent-management.submenu.attendance.daily') }}?date=${val}&_ts=${Date.now()}`;
    }

    window.filterAgentReport = function() {
        const m = document.getElementById('repMonthAgent').value;
        const y = document.getElementById('repYearAgent').value;
        window.location.href = `{{ route('agent-management.submenu.attendance.report') }}?month=${m}&year=${y}&_ts=${Date.now()}`;
    }

    window.exportAgentReport = function() {
        const month = $('#repMonthAgent').val();
        const year = $('#repYearAgent').val();
        window.location.href = `{{ route('agent-management.exportAttendance') }}?month=${month}&year=${year}`;
    }

    window.exportAgentReportPDF = function() {
        const month = $('#repMonthAgent').val();
        const year = $('#repYearAgent').val();
        window.location.href = `{{ route('agent-management.exportAttendancePDF') }}?month=${month}&year=${year}`;
    }

    window.toggleSelectAllAgents = function(master) {
        const isChecked = master.checked;
        if ($.fn.DataTable.isDataTable('#bulkAgentAttTable')) {
            const table = $('#bulkAgentAttTable').DataTable();
            table.rows().nodes().to$().each(function() {
                $(this).find('.agent-checkbox').prop('checked', isChecked);
            });
        } else {
            document.querySelectorAll('.agent-checkbox').forEach(cb => cb.checked = isChecked);
        }
    }

    window.markAllAgents = function(status) {
        if (!window.agentAttendanceState) return;
        
        Object.keys(window.agentAttendanceState).forEach(aId => {
            window.agentAttendanceState[aId].status = status;
        });

        if ($.fn.DataTable.isDataTable('#bulkAgentAttTable')) {
            const table = $('#bulkAgentAttTable').DataTable();
            table.rows().nodes().to$().each(function() {
                const agentId = $(this).data('agent-id');
                if (agentId) {
                    const radio = $(this).find(`input[name="st_${agentId}"][value="${status}"]`);
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
        
        Swal.fire({
            icon: 'info',
            title: 'Updated',
            text: `All agents marked as ${status.charAt(0).toUpperCase() + status.slice(1)}. Click Save All to persist.`,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    window.saveSingleAgentAtt = function(agentId) {
        const row = document.querySelector(`tr[data-agent-id="${agentId}"]`);
        if (!row) return;
        const radio = row.querySelector(`input[name="st_${agentId}"]:checked`);
        if (!radio) return Swal.fire('Error', 'Please select a status', 'error');
        
        const status = radio.value;
        const remarks = document.getElementById(`ag_rem_${agentId}`).value;
        const date = "{{ $date }}";

        fetch("{{ route('agent-management.markAttendance') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ agent_id: agentId, date: date, status: status, remarks: remarks })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Saved!', text: 'Attendance updated.', timer: 1000, showConfirmButton: false, toast: true, position: 'top-end' });
                const btn = row.querySelector('.btn-label-primary');
                if (btn) {
                    btn.classList.replace('btn-label-primary', 'btn-label-success');
                    btn.innerHTML = '<i class="ri-check-line"></i>';
                }
            } else {
                Swal.fire('Error', data.message || 'Failed to save', 'error');
            }
        });
    }

    window.saveBulkAgentAtt = function() {
        let toSave = [];
        Object.keys(window.agentAttendanceState).forEach(aId => {
            toSave.push({
                agent_id: aId,
                status: window.agentAttendanceState[aId].status || 'present',
                remarks: window.agentAttendanceState[aId].remarks || ''
            });
        });

        if (toSave.length === 0) return Swal.fire('Info', 'No agents found to save.', 'info');

        Swal.fire({
            title: 'Saving Attendance...',
            text: `Updating ${toSave.length} records`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch("{{ route('agent-management.bulkMarkAttendance') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ date: "{{ $date }}", attendances: toSave })
        })
        .then(async r => {
            const data = await r.json();
            if (!r.ok) throw new Error(data.message || 'Server error');
            return data;
        })
        .then(data => {
            Swal.close();
            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Saved!', text: data.message, timer: 1500, showConfirmButton: false }).then(() => {
                    sessionStorage.setItem('agentActiveTab', '#navs-attendance');
                    sessionStorage.setItem('agentActiveSubTab', '#att-daily');
                    window.location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Failed to save', 'error');
            }
        })
        .catch(err => {
            Swal.close();
            Swal.fire('Error', err.message || 'Network error', 'error');
        });
    }

    window.viewHistory = function(agentId, name) {
        const subTriggerEl = document.querySelector('button[data-bs-target="#att-report"]');
        if (subTriggerEl) {
            new bootstrap.Tab(subTriggerEl).show();
            Swal.fire({ title: `${name}'s History`, text: 'Switched to Monthly Grid context.', timer: 2000, showConfirmButton: false, position: 'bottom-end', toast: true });
        }
    }

    /**
     * 2. STATE INITIALIZATION
     */
    window.agentAttendanceState = {
        @foreach($dailyAgents as $ag)
        "{{ $ag->id }}": {
            status: "{{ optional($dailyAttendances->get($ag->id))->status ?? 'present' }}",
            remarks: {!! json_encode(optional($dailyAttendances->get($ag->id))->remarks ?? '') !!}
        },
        @endforeach
    };

    /**
     * 3. GLOBAL EVENT LISTENERS
     */
    $(document).on('change', '#bulkAgentAttTable input[type="radio"]', function() {
        const agentId = $(this).closest('tr').data('agent-id');
        if (agentId) {
            if (!window.agentAttendanceState[agentId]) window.agentAttendanceState[agentId] = { remarks: '' };
            window.agentAttendanceState[agentId].status = $(this).val();
        }
    });

    $(document).on('input', '#bulkAgentAttTable input[type="text"]', function() {
        const agentId = $(this).closest('tr').data('agent-id');
        if (agentId) {
            if (!window.agentAttendanceState[agentId]) window.agentAttendanceState[agentId] = {};
            window.agentAttendanceState[agentId].remarks = $(this).val();
        }
    });

    /**
     * 4. UI INITIALIZATION (Run after DOM load)
     */
    $(document).ready(function() {
        $('.select2-modal').each(function() {
            $(this).select2({
                dropdownParent: $(this).closest('.modal'),
                placeholder: $(this).data('placeholder'),
                allowClear: true,
                width: '100%'
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTables
        var bulkAgentAttTable = $('#bulkAgentAttTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: { search: "" },
            columnDefs: [{ orderable: false, targets: [0, 5] }], 
            drawCallback: function() { 
                $('.dataTables_filter input').addClass('form-control form-control-sm').attr('placeholder', 'Search marked agents...');
                let info = this.api().page.info();
                this.api().column(1, {search:'applied', order:'applied'}).nodes().each(function(cell, i) {
                    cell.innerHTML = i + 1 + info.start;
                });
            }
        });

        var monthlyAgentAttTable = $('#monthlyAgentAttTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: { search: "" },
            ordering: false,
            drawCallback: function() {
                let info = this.api().page.info();
                this.api().column(0, {search:'applied', order:'applied'}).nodes().each(function(cell, i) {
                    cell.innerHTML = i + 1 + info.start;
                });
            }
        });

        // Tab Persistence
        let activeTab = sessionStorage.getItem('agentActiveTab') || window.location.hash;
        let activeSubTab = sessionStorage.getItem('agentActiveSubTab');
        
        if (activeTab) {
            const triggerEl = document.querySelector(`button[data-bs-target="${activeTab}"]`);
            if (triggerEl) {
                new bootstrap.Tab(triggerEl).show();
                if (activeTab === '#navs-attendance' && activeSubTab) {
                    setTimeout(() => {
                        const subTriggerEl = document.querySelector(`button[data-bs-target="${activeSubTab}"]`);
                        if (subTriggerEl) new bootstrap.Tab(subTriggerEl).show();
                    }, 150);
                }
            }
        }

        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(link => {
            link.addEventListener('shown.bs.tab', e => {
                const target = e.target.getAttribute('data-bs-target');
                if (target.startsWith('#navs-')) {
                    sessionStorage.setItem('agentActiveTab', target);
                    window.history.replaceState(null, null, target);
                } else if (target.startsWith('#att-')) {
                    sessionStorage.setItem('agentActiveSubTab', target);
                }
            });
        });

        // URL Params handling
        const search = window.location.search;
        if (search.includes('month') || search.includes('year') || search.includes('date')) {
            const mainTriggerEl = document.querySelector('button[data-bs-target="#navs-attendance"]');
            if (mainTriggerEl) {
                new bootstrap.Tab(mainTriggerEl).show();
                const subTarget = (search.includes('month') || search.includes('year')) ? '#att-report' : '#att-daily';
                setTimeout(() => {
                    const subTriggerEl = document.querySelector(`button[data-bs-target="${subTarget}"]`);
                    if (subTriggerEl) new bootstrap.Tab(subTriggerEl).show();
                }, 150);
            }
        }
    });
</script>
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-3">
        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Dashboard /</span> HRM Management</h4>
        <div class="nav-align-top">
            <ul class="nav nav-pills gap-2" role="tablist">
                <li class="nav-item">
                    <a href="{{ route('staff-management.directory') }}" 
                       class="nav-link fw-bold px-4 py-2 text-muted bg-transparent border border-light rounded-pill" style="font-size: 14px;">
                        <i class="ri-team-line me-1"></i> Staff Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('agent-management.index') }}" 
                       class="nav-link fw-bold px-4 py-2 active bg-success text-white shadow-sm rounded-pill" style="font-size: 14px;">
                        <i class="ri-user-star-line me-1"></i> Agent Management
                    </a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible shadow-sm border-0 bg-label-success" role="alert">
            <div class="d-flex">
                <i class="ri-checkbox-circle-line me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $currentTab = request('tab', 'agents');
        $currentSubtab = request('subtab', '');
        $isAttendance = $currentTab === 'attendance';
    @endphp

    <div class="row">
        <div class="col-12">
            <!-- Submenu navigation (replaces tabs) -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="{{ route('agent-management.submenu.directory') }}"
                           class="btn btn-sm {{ $currentTab === 'agents' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-team-line me-1"></i> Agent Directory
                        </a>

                        <a href="{{ route('agent-management.submenu.attendance.daily', ['date' => request('date')]) }}"
                           class="btn btn-sm {{ $isAttendance ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-calendar-check-line me-1"></i> Attendance
                        </a>

                        <a href="{{ route('agent-management.submenu.payroll') }}"
                           class="btn btn-sm {{ $currentTab === 'payroll' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-money-dollar-circle-line me-1"></i> Payroll & Expenses
                        </a>

                        <a href="{{ route('agent-management.index', ['tab' => 'locations']) }}"
                           class="btn btn-sm {{ $currentTab === 'locations' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-map-pin-line me-1"></i> Service Areas
                        </a>

                        <!-- <a href="{{ route('agent-management.submenu.roles') }}"
                           class="btn btn-sm {{ $currentTab === 'roles' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-shield-user-line me-1"></i> Roles
                        </a> -->

                        <a href="{{ route('agent-management.submenu.holidays') }}"
                           class="btn btn-sm {{ $currentTab === 'holidays' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="ri-palanquin-line me-1"></i> Holidays
                        </a>

                        @if($isAttendance)
                            <div class="ms-auto d-flex flex-wrap gap-2">
                                <a href="{{ route('agent-management.submenu.attendance.daily', ['date' => request('date')]) }}"
                                   class="btn btn-sm {{ $currentSubtab === 'att-report' ? 'btn-outline-secondary' : 'btn-secondary' }}">
                                    Daily Marking
                                </a>
                                <a href="{{ route('agent-management.submenu.attendance.report', ['month' => request('month'), 'year' => request('year')]) }}"
                                   class="btn btn-sm {{ $currentSubtab === 'att-report' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                                    Monthly Grid
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="tab-content border-0 bg-transparent p-0 pt-0">
                    
                    <!-- Agent Directory -->
                    <div class="{{ $currentTab === 'agents' ? '' : 'd-none' }}" id="navs-agents">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6 col-xl-4">
                                <div class="card shadow-sm border-0">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="mb-0 fw-bold">{{ $totalAgents }}</h4>
                                                <p class="mb-0 text-muted small">Total Registered Agents</p>
                                            </div>
                                            <div class="avatar bg-label-primary p-2 rounded">
                                                <i class="ri-group-line fs-3"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="card shadow-sm border-0">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="mb-0 fw-bold text-success">{{ $activeAgents }}</h4>
                                                <p class="mb-0 text-muted small">Currently Active</p>
                                            </div>
                                            <div class="avatar bg-label-success p-2 rounded">
                                                <i class="ri-user-follow-line fs-3"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-4">
                                <div class="card shadow-sm border-0">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="mb-0 fw-bold text-warning">{{ $inactiveAgents }}</h4>
                                                <p class="mb-0 text-muted small">Inactive Pool</p>
                                            </div>
                                            <div class="avatar bg-label-warning p-2 rounded">
                                                <i class="ri-user-unfollow-line fs-3"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="mb-0 text-primary fw-bold">Agent List</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <select id="agentPerPage" class="form-select form-select-sm w-auto">
                                        <option value="20" selected>20 per page</option>
                                        <option value="25">25 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                    <input type="text" id="agentSearch" class="form-control form-control-sm" placeholder="Search agents..." style="width: 200px;">
                                    <button class="btn btn-primary btn-sm px-3 shadow" data-bs-toggle="modal" data-bs-target="#addAgentModal">
                                        <i class="ri-add-line me-1"></i> Add New Agent
                                    </button>
                                </div>
                            </div>
                            <div class="card-datatable table-responsive">
                                <table class="datatables-agents table table-hover border-top w-100 text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Id</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Mobile</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
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
                                                <select id="perPageSelectAgent" class="form-select form-select-sm w-auto me-2" onchange="$('#bulkAgentAttTable').DataTable().page.len(this.value).draw()">
                                                    <option value="10">10</option>
                                                    <option value="25" selected>25</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                </select>
                                                <button class="btn btn-xs btn-outline-success" onclick="markAllAgents('present')"><i class="ri-checkbox-circle-line me-1"></i> All Present</button>
                                                <button class="btn btn-xs btn-outline-danger" onclick="markAllAgents('absent')"><i class="ri-close-circle-line me-1"></i> All Absent</button>
                                                <div class="d-flex align-items-center gap-1 ms-2">
                                                    <input type="date" id="attDateAgent" class="form-control form-control-sm w-auto" value="{{ $date }}">
                                                    <button class="btn btn-primary btn-sm" onclick="changeAgentAttDate(document.getElementById('attDateAgent').value)" id="applyDateBtnAgent">Apply</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-datatable table-responsive">
                                            <table class="table table-hover text-nowrap" id="bulkAgentAttTable">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAllAgents" onclick="toggleSelectAllAgents(this)"></th>
                                                        <th>Sl No</th>
                                                        <th>Agent Name</th>
                                                        <th class="text-center">Status</th>
                                                        <th>Remarks</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($dailyAgents as $ag)
                                                    @php $att = $dailyAttendances->get($ag->id); $st = $att ? $att->status : 'present'; @endphp
                                                    <tr data-agent-id="{{ $ag->id }}">
                                                        <td class="text-center"><input type="checkbox" class="form-check-input agent-checkbox" value="{{ $ag->id }}"></td>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td class="fw-medium text-dark">
                                                            <div class="d-flex flex-column">
                                                                <span>{{ $ag->agent_name }}</span>
                                                                <small class="text-muted xsmall">{{ $ag->agent_code }}</small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex justify-content-center gap-3">
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="radio" name="st_{{ $ag->id }}" value="present" id="p_{{ $ag->id }}" {{ $st == 'present' ? 'checked' : '' }}>
                                                                    <label class="form-check-label text-success small" for="p_{{ $ag->id }}">P</label>
                                                                </div>
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="radio" name="st_{{ $ag->id }}" value="absent" id="a_{{ $ag->id }}" {{ $st == 'absent' ? 'checked' : '' }}>
                                                                    <label class="form-check-label text-danger small" for="a_{{ $ag->id }}">A</label>
                                                                </div>
                                                                <div class="form-check form-check-inline">
                                                                    <input class="form-check-input" type="radio" name="st_{{ $ag->id }}" value="half_day" id="h_{{ $ag->id }}" {{ $st == 'half_day' ? 'checked' : '' }}>
                                                                    <label class="form-check-label text-warning small" for="h_{{ $ag->id }}">H</label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><input type="text" class="form-control form-control-sm" id="ag_rem_{{ $ag->id }}" value="{{ $att->remarks ?? '' }}" placeholder="Note..."></td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                                <button class="btn btn-sm btn-icon btn-label-info shadow-sm" title="View Profile" onclick="window.location.href='{{ route('agent-management.view', $ag->id) }}'"><i class="ri-eye-line"></i></button>
                                                                <button class="btn btn-sm btn-icon btn-label-primary shadow-sm" title="Quick Save" onclick="saveSingleAgentAtt({{ $ag->id }})"><i class="ri-save-line"></i></button>
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
                                                <button class="btn btn-primary px-4 shadow" onclick="saveBulkAgentAtt()" id="saveAllAgentAttendanceBtn">
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
                                            <h6 class="mb-0 text-dark">Agent Attendance Grid - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h6>
                                            <div class="d-flex gap-2 align-items-center">
                                                <select class="form-select form-select-sm w-auto" onchange="$('#monthlyAgentAttTable').DataTable().page.len(this.value).draw()">
                                                    <option value="10">10 per page</option>
                                                    <option value="25" selected>25 per page</option>
                                                    <option value="50">50 per page</option>
                                                    <option value="100">100 per page</option>
                                                </select>
                                                <select id="repMonthAgent" class="form-select form-select-sm w-auto">@foreach(range(1,12) as $m)<option value="{{sprintf('%02d',$m)}}" {{$month==$m?'selected':''}}>{{date('M', mktime(0,0,0,$m,1))}}</option>@endforeach</select>
                                                <select id="repYearAgent" class="form-select form-select-sm w-auto">@foreach(range(date('Y')-1, date('Y')) as $y)<option value="{{$y}}" {{$year==$y?'selected':''}}>{{$y}}</option>@endforeach</select>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-primary btn-sm" onclick="filterAgentReport()" id="updateAgentViewBtn"><i class="ri-refresh-line me-1"></i> Update View</button>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="ri-download-line me-1"></i> Export
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="exportAgentReport()">CSV format</a></li>
                                                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="exportAgentReportPDF()">PDF format</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-datatable table-responsive" style="max-height: 500px;">
                                            <table class="table table-bordered table-sm m-0 text-nowrap" id="monthlyAgentAttTable" style="font-size: 11px;">
                                                <thead class="bg-light sticky-top">
                                                    <tr>
                                                        <th class="text-center" style="width: 50px;">SL NO</th>
                                                        <th class="ps-2" style="min-width: 120px;">Agent Name</th>
                                                        @for($d=1; $d<=$daysInMonth; $d++) <th class="text-center" style="width: 25px;">{{$d}}</th> @endfor
                                                        <th class="text-center text-success fw-bold">P</th>
                                                        <th class="text-center text-danger fw-bold">A</th>
                                                        <th class="text-center text-warning fw-bold">H</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($dailyAgents as $ag)
                                                    @php 
                                                        $agAtt = $monthlyAttendances->get($ag->id) ?? collect();
                                                        $indexedAtt = $agAtt->keyBy(function($a) {
                                                            try {
                                                                $d = is_string($a->date) ? \Carbon\Carbon::parse($a->date) : $a->date;
                                                                return $d ? $d->format('Y-m-d') : '';
                                                            } catch (\Exception $e) { return ''; }
                                                        });
                                                    @endphp
                                                    <tr>
                                                        <td class="text-center">{{ $loop->iteration }}</td>
                                                        <td class="ps-2 fw-medium text-dark">{{ $ag->agent_name }}</td>
                                                        @for($d=1; $d<=$daysInMonth; $d++)
                                                            @php 
                                                                $dateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
                                                                $statRecord = $indexedAtt->get($dateStr);
                                                                $stat = $statRecord ? $statRecord->status : null;
                                                                $bg = $stat=='present'?'bg-label-success':($stat=='absent'?'bg-label-danger':($stat=='half_day'?'bg-label-warning':''));
                                                            @endphp
                                                            <td class="text-center {{$bg}}">{{$stat?strtoupper(substr($stat,0,1)):''}}</td>
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

                    <!-- Tab 3: Payroll / Commission -->

                    <div class="{{ $currentTab === 'payroll' ? '' : 'd-none' }}" id="navs-payroll">
                        <div class="card border">
                            <div class="card-header border-bottom d-flex justify-content-between align-items-center bg-label-success py-3">
                                <h5 class="mb-0 text-dark fw-bold"><i class="ri-money-rupee-circle-line me-1"></i> Agent Payroll - {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h5>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAgentExpenseModal"><i class="ri-add-line"></i> Expense</button>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addAgentAdvanceModal"><i class="ri-hand-coin-line"></i> Advance</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Agent Member</th>
                                            <th class="text-end">Base Salary</th>
                                            <th class="text-center">Atten. (A/H)</th>
                                            <th class="text-end text-danger">Deduction</th>
                                            <th class="text-end text-warning">Advances</th>
                                            <th class="text-end text-info">Expenses</th>
                                            <th class="text-end fw-bold">Net Salary</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($payrollData as $data)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2 bg-label-primary rounded-circle">
                                                        <span class="avatar-initial">{{ substr($data['agent']->agent_name, 0, 1) }}</span>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark">{{ $data['agent']->agent_name }}</div>
                                                        <small class="text-muted">{{ $data['agent']->agent_code }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">₹{{ number_format($data['base_salary'], 2) }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-label-danger me-1" title="Absents">{{ $data['absents'] }}A</span>
                                                <span class="badge bg-label-warning" title="Half Days">{{ $data['half_days'] }}H</span>
                                            </td>
                                            <td class="text-end text-danger">-₹{{ number_format($data['deduction'], 2) }}</td>
                                            <td class="text-end text-warning">-₹{{ number_format($data['advances'], 2) }}</td>
                                            <td class="text-end text-info">+₹{{ number_format($data['expenses'], 2) }}</td>
                                            <td class="text-end fw-bold text-dark">₹{{ number_format($data['net_salary'], 2) }}</td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View Details">
                                                    <i class="ri-information-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">No payroll data available for this month.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Reflection Sections for Advances and Expenses -->
                        <div class="row mt-4">
                            <!-- Recent Advances -->
                            <div class="col-md-6">
                                <div class="card border h-100">
                                    <div class="card-header border-bottom bg-label-warning py-3">
                                        <h6 class="mb-0 text-dark fw-bold"><i class="ri-hand-coin-line me-1"></i> Recent Advances</h6>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 13px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Agent</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($recentAdvances as $adv)
                                                <tr>
                                                    <td>{{ date('d-m-Y', strtotime($adv->date)) }}</td>
                                                    <td class="fw-medium">{{ $adv->agent->agent_name }}</td>
                                                    <td class="text-end fw-bold text-dark">₹{{ number_format($adv->amount, 2) }}</td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="3" class="text-center py-4 text-muted">No recent advances found.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Recent Expenses -->
                            <div class="col-md-6">
                                <div class="card border h-100">
                                    <div class="card-header border-bottom bg-label-info py-3">
                                        <h6 class="mb-0 text-dark fw-bold"><i class="ri-add-line me-1"></i> Recent Expenses</h6>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0" style="font-size: 13px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Agent</th>
                                                    <th>Category</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($recentExpenses as $exp)
                                                <tr>
                                                    <td>{{ date('d-m-Y', strtotime($exp->date)) }}</td>
                                                    <td class="fw-medium">{{ $exp->agent->agent_name }}</td>
                                                    <td><span class="badge bg-label-secondary xsmall">{{ ucfirst($exp->category) }}</span></td>
                                                    <td class="text-end fw-bold text-dark">₹{{ number_format($exp->amount, 2) }}</td>
                                                </tr>
                                                @empty
                                                <tr><td colspan="4" class="text-center py-4 text-muted">No recent expenses found.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 4: Service Areas (Locations) -->
                    <div class="{{ $currentTab === 'locations' ? '' : 'd-none' }}" id="navs-locations">
                        <div class="card overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                                <h5 class="mb-0 text-primary fw-bold">Serviceable Locations</h5>
                                <a href="{{ route('location-management.index') }}" class="btn btn-xs btn-label-primary">Manage Global Locations <i class="ri-arrow-right-line"></i></a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th>Area Name</th>
                                            <th>City</th>
                                            <th>State</th>
                                            <th class="text-center">Active Agents</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($locations as $loc)
                                        <tr>
                                            <td class="fw-bold text-dark">{{ $loc->name }}</td>
                                            <td>{{ $loc->city }}</td>
                                            <td>{{ $loc->state }}</td>
                                            <td class="text-center"><span class="badge bg-label-info">{{ \App\Models\Agent::where('location_id', $loc->id)->count() }}</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 5: Roles -->
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
                                        <p class="small text-muted mb-0">Permissions for field agents to perform collections and KYC visits.</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                        </div>
                    </div>

                    <!-- Tab 6: Holidays -->
                    <div class="{{ $currentTab === 'holidays' ? '' : 'd-none' }}" id="navs-holidays">
                        <div class="card border overflow-hidden">
                            <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-light">
                                <h5 class="mb-0 text-dark fw-bold">Company Holidays</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Holiday Description</th>
                                            <th>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($holidays as $holiday)
                                        @php 
                                            $hDate = is_object($holiday) ? $holiday->date : $holiday;
                                            $hName = is_object($holiday) ? $holiday->name : 'Holiday';
                                            $hType = is_object($holiday) ? ($holiday->type ?? 'public') : 'public';
                                        @endphp
                                        <tr>
                                            <td class="fw-bold text-dark">{{ date('D, d-m-Y', strtotime($hDate)) }}</td>
                                            <td>{{ $hName }}</td>
                                            <td><span class="badge bg-label-secondary">{{ ucfirst($hType) }}</span></td>
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

<!-- All Previous Modals (Add Agent, Delete, etc.) remain here -->
<div class="modal fade" id="addAgentModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add New Agent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="agentForm">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentName" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentName" name="name" placeholder="Enter agent name" required pattern="[A-Za-z\s]+" title="Only alphabets and spaces are allowed" oninput="this.value=this.value.replace(/[^A-Za-z\\s]/g,'')">
                <div class="invalid-feedback" id="nameError"></div>
              </div>
              
              <div class="col-md-6 mb-3">
                <label for="agentEmail" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="agentEmail" name="email" placeholder="Enter email" required>
                <div class="invalid-feedback" id="emailError"></div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentPhone" class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentPhone" name="phone" placeholder="Enter phone number" maxlength="10" oninput="this.value=this.value.replace(/[^0-9]/g,'')" pattern="[0-9]{10}" inputmode="numeric" required>
                <div class="invalid-feedback" id="phoneError"></div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="agentPincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentPincode" name="pincode" placeholder="Enter pincode" maxlength="6" pattern="[0-9]*" inputmode="numeric" required>
                <div class="invalid-feedback" id="pincodeError"></div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-12 mb-3">
                <label for="agentLocation" class="form-label">Location <span class="text-danger">*</span></label>
              <select class="form-select select2" id="agentLocation" name="location_id" required>
                  <option value="" selected disabled>Select location</option>
                  @foreach($locations as $loc)
                  <option value="{{ $loc->id }}">{{ $loc->name }}, {{ $loc->city }}, {{ $loc->state }}</option>
                  @endforeach
                </select>
                <div class="invalid-feedback" id="locationError"></div>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="agentAddress" class="form-label">Address <span class="text-danger">*</span></label>
              <textarea class="form-control" id="agentAddress" name="address" rows="2" placeholder="Enter address" required></textarea>
              <div class="invalid-feedback" id="addressError"></div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentCity" class="form-label">City <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentCity" name="city" placeholder="Enter city" oninput="this.value=this.value.replace(/[^a-zA-Z\s.]/g,'')" required>
                <div class="invalid-feedback" id="cityError"></div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="agentState" class="form-label">State <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentState" name="state" placeholder="Enter state" oninput="this.value=this.value.replace(/[^a-zA-Z\s.]/g,'')" required>
                <div class="invalid-feedback" id="stateError"></div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentPassword" class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="password" class="form-control" id="agentPassword" name="password" placeholder="Enter password" required>
                  <button class="btn btn-outline-secondary" type="button" data-action="toggle-password" data-target="#agentPassword">
                    <i class="ri-eye-off-line"></i>
                  </button>
                </div>
                <div class="invalid-feedback d-block" id="passwordError"></div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="agentConfirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                  <input type="password" class="form-control" id="agentConfirmPassword" name="confirm_password" placeholder="Confirm password" required>
                  <button class="btn btn-outline-secondary" type="button" data-action="toggle-password" data-target="#agentConfirmPassword">
                    <i class="ri-eye-off-line"></i>
                  </button>
                </div>
                <div class="invalid-feedback d-block" id="confirmPasswordError"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="submitBtn">Save Agent</button>
          </div>
        </form>
      </div>
    </div>
</div>

<!-- Modal: Add Agent Expense -->
<div class="modal fade" id="addAgentExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="{{ route('agent-management.addExpense') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title">Add Agent Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Agent <span class="text-danger">*</span></label>
                        <select name="agent_id" class="form-select" required>
                            <option value="" selected disabled>Select Agent</option>
                            @foreach($dailyAgents as $ag) <option value="{{ $ag->id }}">{{ $ag->agent_name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="travel">Travel</option>
                            <option value="petrol">Petrol</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Submit Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Agent Advance -->
<div class="modal fade" id="addAgentAdvanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="{{ route('agent-management.addAdvance') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title">Issue Advance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Agent <span class="text-danger">*</span></label>
                        <select name="agent_id" class="form-select select2-modal" data-placeholder="Select Agent" required>
                            <option value=""></option>
                            @foreach($dailyAgents as $ag) <option value="{{ $ag->id }}">{{ $ag->agent_name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason/Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning w-100">Record Advance</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('modals')
  <!-- Add Collection Modal -->
  <div class="modal fade" id="addCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Record Manual Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="addCollectionForm">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select Agent <span class="text-danger">*</span></label>
              <select class="form-select select2-modal" name="agent_id" required data-dropdown-parent="#addCollectionModal">
                <option value="">Choose Agent</option>
                @foreach($dailyAgents as $agent)
                  <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Search EMI (Client/Acc No/Phone) <span class="text-danger">*</span></label>
              <select class="form-select select2-ajax" name="emi_id" id="emiSearchSelect" required data-dropdown-parent="#addCollectionModal">
                <option value="">Start typing to search...</option>
              </select>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount" id="collectionAmount" required min="0.01" step="0.01">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="collected_at" value="{{ date('Y-m-d') }}" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Payment Method <span class="text-danger">*</span></label>
              <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="payment_method" id="method_in_hand" value="in_hand" checked>
                  <label class="form-check-label" for="method_in_hand">In Hand</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="payment_method" id="method_direct" value="direct">
                  <label class="form-check-label" for="method_direct">Direct (Bank/UPI)</label>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveCollectionBtn">Save Collection</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Verify Collection Modal -->
  <div class="modal fade" id="verifyCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Verify Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="verifyCollectionForm">
          <div class="modal-body">
            <input type="hidden" id="verifyCollectionId" name="collection_id">
            <div class="mb-3">
              <label class="form-label">Verification Status</label>
              <select class="form-select" name="status" required>
                <option value="">Select Status</option>
                <option value="verified">Approve</option>
                <option value="rejected">Reject</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Add verification remarks..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endpush

@endsection
