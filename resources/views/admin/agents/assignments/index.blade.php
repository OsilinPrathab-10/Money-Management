@extends('layouts/layoutMaster')

@section('title', 'Agent Assignments')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
<script>
  const baseUrl = "{{ url('/') }}/";
  
  @if(!$isAgent)
  $(document).ready(function() {
    // Initialize DataTables
    const dt_assignments = $('#assignmentsTable').DataTable({
      ajax: {
        url: baseUrl + 'app/agents/assignments/list',
        data: function(d) {
          d.agent_id = $('#filterAgent').val();
          d.status = $('#filterStatus').val();
        }
      },
      columns: [
        { data: 'id' },
        { data: 'client_name', render: function(data, type, row) {
            return `<div class="d-flex justify-content-start align-items-center">
                <div class="d-flex flex-column">
                    <span class="fw-medium text-heading text-truncate">${data}</span>
                    <small class="text-muted text-truncate">Acc: ${row.account_number}</small>
                </div>
            </div>`;
        }},
        { data: 'agent_name', render: function(data, type, row) {
            if (!data || data === 'N/A') return '<span class="text-muted">Not Assigned</span>';
            return `<div class="d-flex justify-content-start align-items-center">
                <div class="avatar-wrapper me-2">
                    <div class="avatar avatar-xs">
                        <span class="avatar-initial rounded-circle bg-label-info">${data.charAt(0)}</span>
                    </div>
                </div>
                <div class="d-flex flex-column">
                    <span class="fw-medium text-heading text-truncate">${data}</span>
                    <small class="text-muted text-truncate">${row.agent_code}</small>
                </div>
            </div>`;
        }},
        { data: 'emi_number', render: function(data, type, row) {
            return `EMI #${data}<br><small class="text-success fw-bold">₹${parseFloat(row.amount).toFixed(2)}</small>`;
        }},
        { data: 'assigned_at' },
        { data: 'status', render: function(data) {
            const badges = {
              assigned: '<span class="badge bg-label-primary">Assigned</span>',
              visited: '<span class="badge bg-label-info">Visited</span>',
              completed: '<span class="badge bg-label-success">Completed</span>',
              resolved: '<span class="badge bg-label-success">Completed</span>',
              reassigned: '<span class="badge bg-label-warning">Reassigned</span>',
              cancelled: '<span class="badge bg-label-danger">Cancelled</span>'
            };
            return badges[data] || `<span class="badge bg-label-secondary">${data}</span>`;
        }},
        { data: 'remarks' },
        { data: 'id', render: function(data, type, row) {
            if (row.status === 'completed' || row.status === 'resolved') {
                return '<span class="text-success small"><i class="ri-checkbox-circle-line me-1"></i>Collected</span>';
            }
            // For agents, show a "Collect" button
            return `<a href="${baseUrl}app/agents/agent-collections?emi_id=${row.real_emi_id}" class="btn btn-sm btn-primary">
                <i class="ri-hand-coin-line me-1"></i> Collect
            </a>`;
        }}
      ],
      order: [[4, 'desc']],
      dom: '<"row mx-1"<"col-sm-12 col-md-3" l><"col-sm-12 col-md-9"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-md-end justify-content-center flex-wrap me-1"<"me-3"f>>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      displayLength: 20,
      lengthMenu: [20, 25, 50, 75, 100],
      responsive: true
    });

    // Refresh table on filter change
    $('#filterAgent, #filterStatus').on('change', function() {
      dt_assignments.ajax.reload();
    });

    // Initialize Select2 for Assign Agent Modal
    $('.select2-assign').select2({
        dropdownParent: $('#assignAgentModal'),
        width: '100%'
    });

    $('#emiAssignSelect').select2({
        ajax: {
            url: "{{ route('agent-collections.search-emis') }}",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { 
                    q: params.term,
                    agent_id: $('#assignAgentModal select[name="agent_id"]').val(),
                    action: 'assign'
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        placeholder: 'Search for client, account number or phone number',
        minimumInputLength: 0,
        width: '100%',
        dropdownParent: $('#assignAgentModal')
    });

    // Clear EMI selection if agent changes
    $(document).on('change', '#assignAgentModal select[name="agent_id"]', function() {
        $('.select2-ajax-assign').val(null).trigger('change');
    });

    // Handle Assignment Form Submission
    $('#assignAgentForm').on('submit', function(e) {
      e.preventDefault();
      
      const form = $(this);
      const submitBtn = $('#saveAssignBtn');
      
      submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Assigning...');
      
      $.ajax({
        url: baseUrl + 'app/agents/agent-collections/assign',
        method: 'POST',
        data: form.serialize(),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          if (response.success) {
            $('#assignAgentModal').modal('hide');
            dt_assignments.ajax.reload();
            form[0].reset();
            $('.select2-assign, .select2-ajax-assign').val(null).trigger('change');
            
            Swal.fire({
              icon: 'success',
              title: 'Assigned!',
              text: response.message,
              customClass: { confirmButton: 'btn btn-success' }
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.message,
              customClass: { confirmButton: 'btn btn-primary' }
            });
          }
        },
        error: function(xhr) {
          const msg = xhr.responseJSON?.message || 'An error occurred while assigning the loan.';
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: msg,
            customClass: { confirmButton: 'btn btn-primary' }
          });
        },
        complete: function() {
          submitBtn.prop('disabled', false).html('Assign EMI');
        }
      });
    });
  });
  @endif
</script>
@endsection

@section('content')
<h4 class="mb-4">Agent Assignments</h4>

@if($isAgent)
  <!-- My Assignments (Agent View) -->
  <div class="card border-warning mb-4" style="border-width:2px">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-label-warning py-3">
      <div>
        <h5 class="card-title mb-0 text-warning"><i class="ri-task-line me-2"></i>My Assignments</h5>
        <small class="text-muted">EMIs assigned to you by Admin — tap Collect to submit a collection</small>
      </div>
      <span class="badge bg-warning text-dark">{{ $myAssignments->count() }} Pending</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Client</th>
            <th>Account No.</th>
            <th>EMI #</th>
            <th>Due Date</th>
            <th>Pending Amount</th>
            <th>Assigned On</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($myAssignments as $i => $assignment)
          @php
            $emi    = $assignment->emi;
            $client = $emi?->loanAccount?->client;
            $loan   = $emi?->loanAccount;
            $pending = $emi ? max(0, (float)($emi->pending_amount ?? $emi->total_amount)) : 0;
          @endphp
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>
              <span class="fw-semibold">{{ $client?->client_name ?? 'N/A' }}</span><br>
              <small class="text-muted">{{ $client?->client_phone ?? '' }}</small>
            </td>
            <td><span class="badge bg-label-secondary">{{ $loan?->account_number ?? 'N/A' }}</span></td>
            <td><span class="badge bg-label-primary">EMI #{{ $emi?->instalment_number ?? '?' }}</span></td>
            <td>
              @if($emi?->due_date)
                @php $due = \Carbon\Carbon::parse($emi->due_date); @endphp
                <span class="{{ $due->isPast() ? 'text-danger fw-bold' : '' }}">
                  {{ $due->format('d-m-Y') }}
                </span>
                @if($due->isPast())
                  <span class="badge bg-label-danger ms-1">Overdue</span>
                @endif
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
            <td class="fw-bold text-danger">₹{{ number_format($pending, 2) }}</td>
            <td><small class="text-muted">{{ $assignment->assigned_at?->format('d-m-Y') ?? '-' }}</small></td>
            <td>
              <a href="{{ url('app/agents/agent-collections?emi_id=' . ($emi?->id ?? '')) }}"
                class="btn btn-sm btn-warning">
                <i class="ri-hand-coin-line me-1"></i> Collect
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center py-4 text-muted">
              <i class="ri-information-line me-1"></i> No pending assignments found.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@else
  <!-- Admin View: Datatable -->
  <div class="card mb-4">
    <div class="card-header border-bottom d-flex flex-wrap justify-content-between align-items-center">
      <h5 class="card-title mb-0">Assignments Filter</h5>
      <!-- @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignAgentModal">
        <i class="ri-user-add-line me-1"></i> Assign Agent for Collection
      </button>
      @endif -->
    </div>
    <div class="card-body mt-3">
      <div class="row g-3">
        @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
        <div class="col-md-4">
          <label class="form-label">Filter by Agent</label>
          <select id="filterAgent" class="form-select">
            <option value="">All Agents</option>
            @foreach($agents as $agent)
              <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
            @endforeach
          </select>
        </div>
        @endif
        <div class="col-md-4">
          <label class="form-label">Filter by Status</label>
          <select id="filterStatus" class="form-select">
            <option value="">All Statuses</option>
            <option value="assigned">Assigned</option>
            <option value="visited">Visited</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-datatable table-responsive">
      <table class="datatables-users table" id="assignmentsTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Client & Loan</th>
            <th>Agent</th>
            <th>EMI Info</th>
            <th>Assigned At</th>
            <th>Status</th>
            <th>Remarks</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
@endif

<!-- Assign Agent Modal -->
<div class="modal fade" id="assignAgentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Agent for Collection</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="assignAgentForm">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required">Select Agent</label>
            <select name="agent_id" class="form-select select2-assign" required>
              <option value="">Choose Agent</option>
              @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label required">Search EMI</label>
            <select name="emi_id" class="form-select select2-ajax-assign" id="emiAssignSelect" required>
              <option value="">Search by client name, account number...</option>
            </select>
            <small class="text-muted">Search client name to find their pending EMIs.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Remarks (Optional)</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Instructions for the agent..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveAssignBtn">Assign EMI</button>
        </div>
      </form>
    </div>
  </div>
</div>

  
@endsection
