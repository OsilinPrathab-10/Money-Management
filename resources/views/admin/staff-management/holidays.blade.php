@extends('layouts/layoutMaster')

@section('title', 'Holiday Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Staff Management /</span> Holidays</h4>

    <div class="row">
        <!-- Add Holiday Form -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add New Holiday</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.staff.holidays.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Diwali" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="government">Government Holiday</option>
                                <option value="company">Company Holiday</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Save Holiday</button>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info" role="alert">
                <h6 class="alert-heading fw-bold mb-1">Information</h6>
                <span>Government holidays are automatically excluded from salary deductions for all staff members.</span>
            </div>
        </div>

        <!-- Holidays List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">Upcoming / Recent Holidays</h5>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($holidays as $holiday)
                            <tr>
                                <td><span class="fw-bold">{{ $holiday->date->format('d-m-Y') }}</span></td>
                                <td>{{ $holiday->name }}</td>
                                <td><span class="badge bg-label-info">{{ ucfirst($holiday->type) }}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-icon text-primary me-1" onclick="editHolidayStandalone({{ json_encode($holiday) }})">
                                        <i class="ri-edit-2-line"></i>
                                    </button>
                                    <form action="{{ route('admin.staff.holidays.delete', $holiday->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon text-danger" onclick="return confirm('Are you sure you want to delete this holiday?')">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">No holidays recorded</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

<!-- Modal: Edit Holiday -->
<div class="modal fade" id="editHolidayStandaloneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="editHolidayStandaloneForm" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title">Edit Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" id="edit_holiday_date_standalone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_holiday_name_standalone" class="form-control" required placeholder="e.g. Diwali">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="edit_holiday_type_standalone" class="form-select">
                            <option value="government">Government Holiday</option>
                            <option value="company">Company Holiday</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Update Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('page-script')
<script>
    function editHolidayStandalone(holiday) {
        const form = document.getElementById('editHolidayStandaloneForm');
        form.action = `{{ url('staff/holidays') }}/${holiday.id}/update`;
        document.getElementById('edit_holiday_date_standalone').value = holiday.date;
        document.getElementById('edit_holiday_name_standalone').value = holiday.name;
        document.getElementById('edit_holiday_type_standalone').value = holiday.type || 'government';
        const modal = new bootstrap.Modal(document.getElementById('editHolidayStandaloneModal'));
        modal.show();
    }
</script>
@endsection
