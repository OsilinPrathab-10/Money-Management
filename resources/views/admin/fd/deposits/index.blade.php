@extends('layouts/layoutMaster')

@section('title', 'Fixed Deposits')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-0">Fixed Deposits</h4>
        <p class="text-muted mb-0">Manage customer FD accounts, maturity and payouts</p>
    </div>
    <a href="{{ route('fd.deposits.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>New Deposit
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="FD number or customer…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['active','matured','closed','premature_closed','renewed','cancelled'] as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>
                            {{ \Illuminate\Support\Str::headline($st) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="scheme_id" class="form-select form-select-sm">
                    <option value="">All Schemes</option>
                    @foreach($schemes as $scheme)
                        <option value="{{ $scheme->id }}" {{ (string) request('scheme_id') === (string) $scheme->id ? 'selected' : '' }}>
                            {{ $scheme->name }} ({{ $scheme->scheme_code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('fd.deposits.index') }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            All Deposits
            <span class="badge bg-primary ms-2">{{ $deposits->total() }}</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>FD Number</th>
                        <th>Customer</th>
                        <th>Scheme</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Maturity Amt</th>
                        <th>Tenure Progress</th>
                        <th>Auto Renewal</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deposits as $deposit)
                    <tr>
                        <td>
                            <a href="{{ route('fd.deposits.show', $deposit) }}" class="fw-semibold">{{ $deposit->fd_number }}</a>
                        </td>
                        <td>{{ $deposit->client->client_name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-label-secondary">{{ $deposit->scheme->name ?? '—' }}</span>
                        </td>
                        <td class="text-end">₹{{ number_format((float) $deposit->deposit_amount, 2) }}</td>
                        <td class="text-end">₹{{ number_format((float) $deposit->maturity_amount, 2) }}</td>
                        <td>
                            <div style="min-width: 110px;">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold">{{ $deposit->tenure_progress_percentage }}%</span>
                                    <span class="text-muted small">{{ $deposit->remaining_days }}d left</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $deposit->remaining_days <= 7 && $deposit->status === 'active' ? 'bg-warning' : 'bg-primary' }}"
                                         role="progressbar"
                                         style="width: {{ $deposit->tenure_progress_percentage }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input auto-renewal-toggle-list" type="checkbox" role="switch"
                                       id="toggleListAutoRenewal_{{ $deposit->id }}"
                                       data-fd-id="{{ $deposit->id }}"
                                       data-url="{{ route('fd.deposits.toggle-auto-renewal', $deposit) }}"
                                       {{ $deposit->auto_renewal ? 'checked' : '' }}
                                       @if($deposit->status !== 'active') disabled @endif>
                                <label class="form-check-label small ms-1" id="autoRenewalListLabel_{{ $deposit->id }}">
                                    <span class="{{ $deposit->auto_renewal ? 'text-success' : 'text-muted' }}">
                                        {{ $deposit->auto_renewal ? 'On' : 'Off' }}
                                    </span>
                                </label>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $deposit->status_badge }}">{{ $deposit->status_label }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                <a href="{{ route('fd.deposits.show', $deposit) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View">
                                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                                </a>
                                <a href="{{ route('fd.deposits.certificate', $deposit) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" target="_blank" title="Certificate">
                                    <i class="icon-base ri ri-file-paper-2-line icon-22px"></i>
                                </a>
                                @if($deposit->canEdit())
                                <a href="{{ route('fd.deposits.edit', $deposit) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="Edit">
                                    <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                                </a>
                                @endif
                                @if($deposit->canDelete())
                                <form action="{{ route('fd.deposits.destroy', $deposit) }}" method="POST" class="d-inline m-0"
                                      onsubmit="return confirm('Delete this Fixed Deposit? This will reverse the deposit receipt from accounts.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="Delete">
                                        <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">No fixed deposits found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deposits->hasPages())
    <div class="card-footer">{{ $deposits->links() }}</div>
    @endif
</div>

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.auto-renewal-toggle-list').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const url = this.getAttribute('data-url');
            const isChecked = this.checked;
            const fdId = this.getAttribute('data-fd-id');
            const labelEl = document.getElementById('autoRenewalListLabel_' + fdId);
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '{{ csrf_token() }}';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    auto_renewal: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (labelEl) {
                        labelEl.innerHTML = `<span class="${isChecked ? 'text-success' : 'text-muted'}">${isChecked ? 'On' : 'Off'}</span>`;
                    }
                } else {
                    alert(data.message || 'Failed to update auto renewal.');
                    this.checked = !isChecked;
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred while updating auto renewal.');
                this.checked = !isChecked;
            });
        });
    });
});
</script>
@endsection
@endsection
