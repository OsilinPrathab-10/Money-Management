@extends('layouts/layoutMaster')

@section('title', 'FD Schemes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-0">Fixed Deposit Schemes</h4>
        <p class="text-muted mb-0">Define interest rates, tenure rules, and payout defaults</p>
    </div>
    <a href="{{ route('fd.schemes.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>New Scheme
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
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Name or code…" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('fd.schemes.index') }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            All Schemes
            <span class="badge bg-primary ms-2">{{ $schemes->total() }}</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Rate</th>
                        <th>Tenure</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schemes as $scheme)
                    <tr>
                        <td><code>{{ $scheme->scheme_code }}</code></td>
                        <td>
                            <a href="{{ route('fd.schemes.show', $scheme) }}" class="fw-semibold">{{ $scheme->name }}</a>
                        </td>
                        <td>
                            <span class="badge bg-label-info">{{ $scheme->deposit_type_label }}</span>
                        </td>
                        <td>{{ number_format((float) $scheme->interest_rate, 2) }}%</td>
                        <td>
                            {{ $scheme->min_tenure }}–{{ $scheme->max_tenure }}
                            {{ ucfirst($scheme->tenure_type) }}
                        </td>
                        <td>
                            <span class="badge bg-{{ $scheme->status_badge }}">{{ ucfirst($scheme->status) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                <a href="{{ route('fd.schemes.show', $scheme) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View">
                                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                                </a>
                                <a href="{{ route('fd.schemes.edit', $scheme) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="Edit">
                                    <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                                </a>
                                <form action="{{ route('fd.schemes.destroy', $scheme) }}" method="POST" class="d-inline m-0"
                                      onsubmit="return confirm('Delete this scheme?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="Delete">
                                        <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No schemes created yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($schemes->hasPages())
    <div class="card-footer">{{ $schemes->links() }}</div>
    @endif
</div>
@endsection
