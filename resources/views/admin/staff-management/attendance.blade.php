@extends('layouts/layoutMaster')

@section('title', 'Staff Attendance')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('page-script')
    @vite([
        'resources/assets/vendor/libs/flatpickr/flatpickr.js',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
<script>
    /**
     * 1. GLOBAL FUNCTIONS
     */
    window.changePerPage = function(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', val);
        window.location.href = url.toString();
    }

    window.saveSingleAttendance = function(staffId) {
        const row = document.querySelector(`tr[data-staff-id="${staffId}"]`);
        const statusInput = row.querySelector(`input[name="status_${staffId}"]:checked`);
        const remarksInput = row.querySelector(`#remarks_${staffId}`);
        const btnSave = row.querySelector('.save-attendance');

        if (!statusInput) return Swal.fire('Warning', 'Please select a status', 'warning');
        
        const status = statusInput.value;
        const remarks = remarksInput ? remarksInput.value : '';
        const date = "{{ $date }}";

        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch("{{ route('admin.staff.markAttendance') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ staff_id: staffId, date, status, remarks })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire({ icon: 'success', title: 'Saved!', timer: 1000, showConfirmButton: false, toast: true, position: 'top-end' });
                btnSave.classList.replace('btn-label-primary', 'btn-label-success');
                btnSave.innerHTML = '<i class="ri-check-line me-1"></i> Saved';
                setTimeout(() => {
                    btnSave.classList.replace('btn-label-success', 'btn-label-primary');
                    btnSave.innerHTML = '<i class="ri-save-line me-1"></i> Save';
                    btnSave.disabled = false;
                }, 1500);
            } else {
                Swal.fire('Error', data.message || 'Failed to save', 'error');
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="ri-save-line me-1"></i> Save';
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Network error', 'error');
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="ri-save-line me-1"></i> Save';
        });
    }

    window.saveAllAttendance = function() {
        const date = "{{ $date }}";
        const attendances = [];
        
        document.querySelectorAll('tr[data-staff-id]').forEach(row => {
            const staffId = row.dataset.staffId;
            const statusInput = row.querySelector(`input[name="status_${staffId}"]:checked`);
            const remarksInput = row.querySelector(`#remarks_${staffId}`);
            
            // Only add if not disabled (to respect locking/edit limits)
            if (statusInput && !statusInput.disabled) {
                attendances.push({
                    staff_id: staffId,
                    status: statusInput.value,
                    remarks: remarksInput ? remarksInput.value : ''
                });
            }
        });

        if (attendances.length === 0) return Swal.fire('Info', 'No editable records to save', 'info');

        const btn = document.getElementById('saveAllAttendance');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        fetch("{{ route('admin.staff.bulkMarkAttendance') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ date, attendances })
        })
        .then(async response => {
            let data = {};
            try {
                data = await response.json();
            } catch (e) {
                data = {};
            }
            if (!response.ok) {
                throw new Error(data.message || 'Failed to save attendance');
            }
            return data;
        })
        .then(data => {
            if(data.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Saved!', 
                    text: data.message, 
                    timer: 1500, 
                    showConfirmButton: false 
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(err => {
            Swal.fire('Error', err.message || 'Network error', 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    }

    window.markAll = function(status) {
        document.querySelectorAll(`.status-radio[value="${status}"]`).forEach(radio => {
            if (!radio.disabled) {
                radio.checked = true;
            }
        });
        Swal.fire({
            icon: 'info',
            title: 'Updated',
            text: `All editable staff marked as ${status.charAt(0).toUpperCase() + status.slice(1)} (Click Save All to persist)`,
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    /**
     * 2. UI INITIALIZATION
     */
    document.addEventListener('DOMContentLoaded', function() {
        const flatpickrDate = document.querySelector('#attendanceDate');
        if (flatpickrDate) {
            flatpickrDate.flatpickr({
                altInput: true,
                altFormat: 'F j, Y',
                dateFormat: 'Y-m-d'
            });
        }

        document.getElementById('updateViewBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            const dateStr = flatpickrDate.value;
            // Use navigation but prevent accidental form submission reloads
            window.location.href = "{{ route('admin.staff.attendance') }}?date=" + dateStr + "&per_page=" + document.getElementById('perPage').value;
        });
    });
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold py-3 mb-0"><span class="text-muted fw-light">Staff Management /</span> Attendance</h4>
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center">
                <label class="me-2 mb-0 text-nowrap small fw-bold">Per Page:</label>
                <select id="perPage" class="form-select form-select-sm" style="width: 70px;" onchange="window.changePerPage(this.value)">
                    <option value="20" {{ request('per_page' 20)== 20 ? 'selected' : '' }}>20</option>
                    <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                </select>
            </div>
            <div class="d-flex align-items-center gap-1">
                <label class="me-2 mb-0 text-nowrap small fw-bold">Select Date:</label>
                <input type="text" id="attendanceDate" class="form-control form-control-sm" style="width: 130px;" value="{{ $date }}" />
                <button class="btn btn-primary btn-sm" id="updateViewBtn">Update View</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Attendance for {{ date('d-m-Y', strtotime($date)) }}</h5>
                @if($date < date('Y-m-d'))
                    <span class="badge bg-label-danger mt-1"><i class="ri-lock-line me-1"></i> Historical Date Locked</span>
                @endif
            </div>
            <div class="d-flex gap-2">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="window.markAll('present')">
                        <i class="ri-check-double-line me-1"></i> All Present
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.markAll('absent')">
                        <i class="ri-close-circle-line me-1"></i> All Absent
                    </button>
                </div>
                <button type="button" class="btn btn-success btn-sm" id="saveAllAttendance" onclick="window.saveAllAttendance()">
                    <i class="ri-save-line me-1"></i> Save All Attendance
                </button>
            </div>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;">Sl No</th>
                        <th>Staff Name</th>
                        <th class="text-center">Status</th>
                        <th>Remarks</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($staffs as $staff)
                    @php
                        $attendance = $attendances->get($staff->id);
                        $status = $attendance ? $attendance->status : 'present';
                        $editCount = $attendance ? $attendance->edit_count : 0;
                        $isLocked = $date < date('Y-m-d');
                        $limitReached = $editCount >= 3;
                        $isDisabled = $isLocked || $limitReached;
                    @endphp
                    <tr data-staff-id="{{ $staff->id }}" class="{{ $isDisabled ? 'table-light' : '' }}">
                        <td>{{ ($staffs->firstItem()) ? ($staffs->firstItem() + $loop->index) : $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>
                                    <span class="fw-semibold text-dark">{{ $staff->name }}</span>
                                    @if($limitReached && !$isLocked)
                                        <small class="d-block text-danger">Edit limit reached (3/3)</small>
                                    @elseif($attendance && !$isLocked)
                                        <small class="d-block text-muted">Edits: {{ $editCount }}/3</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input status-radio" type="radio" name="status_{{ $staff->id }}" id="present_{{ $staff->id }}" value="present" {{ $status == 'present' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                    <label class="form-check-label text-success" for="present_{{ $staff->id }}">Present</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input status-radio" type="radio" name="status_{{ $staff->id }}" id="absent_{{ $staff->id }}" value="absent" {{ $status == 'absent' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                    <label class="form-check-label text-danger" for="absent_{{ $staff->id }}">Absent</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input status-radio" type="radio" name="status_{{ $staff->id }}" id="half_day_{{ $staff->id }}" value="half_day" {{ $status == 'half_day' ? 'checked' : '' }} {{ $isDisabled ? 'disabled' : '' }}>
                                    <label class="form-check-label text-warning" for="half_day_{{ $staff->id }}">Half</label>
                                </div>
                            </div>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" id="remarks_{{ $staff->id }}" value="{{ $attendance->remarks ?? '' }}" placeholder="Add note..." {{ $isDisabled ? 'disabled' : '' }}>
                        </td>
                        <td class="text-center">
                            @if($isDisabled)
                                <span class="badge bg-label-secondary"><i class="ri-lock-line me-1"></i> Locked</span>
                            @else
                                <button type="button" class="btn btn-sm btn-label-primary save-attendance" onclick="window.saveSingleAttendance({{ $staff->id }})">
                                    <i class="ri-save-line me-1"></i> Save
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No staff found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($staffs->hasPages())
        <div class="card-footer d-flex justify-content-center border-top">
            {{ $staffs->appends(request()->all())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
