@extends('layouts/layoutMaster')

@section('title', 'Customer Wallets')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-0">Customer Wallets</h4>
        <p class="text-muted mb-0">Wallet balances credited from Fixed Deposit payouts</p>
    </div>
    <a href="{{ route('fd.dashboard') }}" class="btn btn-label-secondary">
        <i class="ri-arrow-left-line me-1"></i>Dashboard
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
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Customer name or phone…" value="{{ request('search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Search</button>
                <a href="{{ route('fd.wallets.index') }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            Wallets
            <span class="badge bg-primary ms-2">{{ $wallets->total() }}</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wallets as $wallet)
                    <tr>
                        <td>
                            <a href="{{ route('fd.wallets.show', $wallet) }}" class="fw-semibold">
                                {{ $wallet->client->client_name ?? '—' }}
                            </a>
                        </td>
                        <td>{{ $wallet->client->client_phone ?? '—' }}</td>
                        <td class="text-end fw-bold text-primary">₹{{ number_format((float) $wallet->balance, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ ($wallet->status ?? 'active') === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($wallet->status ?? 'active') }}
                            </span>
                        </td>
                        <td>{{ optional($wallet->updated_at)->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <div class="d-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                <a href="{{ route('fd.wallets.show', $wallet) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View">
                                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No wallets found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($wallets->hasPages())
    <div class="card-footer">{{ $wallets->links() }}</div>
    @endif
</div>
@endsection
