@extends('layouts/layoutMaster')

@section('title', 'Chit Account — ' . $member->account_number)

@section('content')

@php
  $primaryClient = $member->client;
  $collectClientKey = $primaryClient?->getRouteKey();
  $ownershipShares = $member->is_shared
      ? $member->resolveOwnershipShares()
      : collect();
@endphp

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
  <div>
    <h4 class="mb-1">Chit Account — {{ $member->account_number }}</h4>
    <p class="mb-0 fs-5 fw-semibold text-heading">
      <i class="ri-user-3-line text-primary me-1"></i>{{ $primaryClient->client_name ?? 'N/A' }}
    </p>
    @if($primaryClient?->client_phone)
    <small class="text-muted">{{ $primaryClient->client_phone }}</small>
    @endif
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('chit.accounts.index') }}" class="btn btn-outline-secondary">
      <i class="ri-arrow-left-line me-1"></i> Back To Chit Accounts
    </a>
    @if($member->group)
    <a href="{{ route('chit.groups.show', $member->group) }}" class="btn btn-outline-primary">
      <i class="ri-pages-line me-1"></i> View Group
    </a>
    @endif
    @if($member->client_id)
    <a href="{{ route('client-view-account', $member->client_id) }}" class="btn btn-outline-info">
      <i class="ri-user-3-line me-1"></i> View Client
    </a>
    @endif
    @if($member->canBeTransferred($member->group))
    <a href="{{ route('chit.members.transfer.create', $member) }}" class="btn btn-outline-warning">
      <i class="ri-exchange-line me-1"></i> Transfer Member
    </a>
    @endif
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Chit Value</small>
        <h5 class="mb-0 mt-1">₹{{ number_format((float) ($member->group->chit_value ?? 0), 2) }}</h5>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Total Paid</small>
        <h5 class="mb-0 mt-1 text-success">₹{{ number_format($stats['total_paid'], 2) }}</h5>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Outstanding</small>
        <h5 class="mb-0 mt-1 text-danger">₹{{ number_format($stats['outstanding'], 2) }}</h5>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Installments</small>
        <h5 class="mb-0 mt-1">{{ $stats['paid_count'] }} paid / {{ $installments->count() }} total</h5>
        @if($stats['overdue_count'] > 0)
        <small class="text-danger">{{ $stats['overdue_count'] }} overdue</small>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">Account Summary</h5>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <small class="text-muted d-block">Client Name</small>
        <span class="fw-bold fs-5 text-heading d-inline-flex align-items-center gap-1">
          <i class="ri-user-3-line text-primary"></i>
          {{ $primaryClient->client_name ?? '—' }}
        </span>
        @if($primaryClient?->client_phone)
        <small class="text-muted d-block mt-1">{{ $primaryClient->client_phone }}</small>
        @endif
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Account Number</small>
        <span class="fw-semibold">{{ $member->account_number }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Group</small>
        <span class="fw-semibold">{{ $member->group->group_code ?? '—' }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Scheme</small>
        <span class="fw-semibold">{{ $member->group->scheme->name ?? '—' }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Member No.</small>
        <span class="fw-semibold">#{{ $member->member_number }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Joined Date</small>
        <span class="fw-semibold">{{ optional($member->joined_date)->format('d M Y') ?? '—' }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Status</small>
        <span class="badge bg-{{ $member->status_badge }}">{{ ucfirst($member->status) }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Chit Need Month</small>
        <span class="fw-semibold text-primary">{{ $member->chit_need_formatted }}</span>
      </div>
      <div class="col-md-4">
        <small class="text-muted d-block">Won Auction</small>
        <span class="fw-semibold">{{ $member->has_won_auction ? 'Yes' : 'No' }}</span>
      </div>
      @if($member->is_shared)
      <div class="col-12">
        <small class="text-muted d-block">Shared Ownership</small>
        <span class="fw-semibold">{{ $member->owners_display }}</span>
      </div>
      @endif
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h5 class="mb-0">Installment Schedule</h5>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      @if($member->is_shared && $ownershipShares->count() > 1)
        <div class="dropdown">
          <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ri-money-dollar-circle-line me-1"></i> Collect Payment
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            @foreach($ownershipShares as $share)
              @php
                $shareClient = $share->client;
                $shareKey = $shareClient?->getRouteKey();
                $pct = rtrim(rtrim(number_format((float) $share->ownership_percentage, 2, '.', ''), '0'), '.');
              @endphp
              @if($shareKey)
              <li>
                <a class="dropdown-item" href="{{ route('chit.installments.client', $shareKey) }}">
                  <i class="ri-user-3-line me-1"></i>
                  {{ $shareClient->client_name ?? ('Client #' . $share->client_id) }}
                  <small class="text-muted">({{ $pct }}%)</small>
                </a>
              </li>
              @endif
            @endforeach
          </ul>
        </div>
      @elseif($collectClientKey)
        <a href="{{ route('chit.installments.client', $collectClientKey) }}" class="btn btn-sm btn-primary">
          <i class="ri-money-dollar-circle-line me-1"></i> Collect Payment
        </a>
      @endif
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Month</th>
            <th>Due Date</th>
            <th class="text-end">Amount</th>
            <th class="text-end">Penalty</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
            <th>Paid Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse($installments as $inst)
          @php
            $currentPaid = (float)($inst->paid_amount ?? 0);
            $inProgressSum = $inst->collections ? $inst->collections->where('status', 'in_progress')->sum('amount') : 0;
            $displayPaid = $currentPaid + $inProgressSum;
            $displayRemaining = max(0, (float)$inst->balance - $inProgressSum);
          @endphp
          <tr>
            <td>{{ $inst->month_number }}</td>
            <td>{{ optional($inst->due_date)->format('d M Y') }}</td>
            <td class="text-end">{{ $inst->amount_display }}</td>
            <td class="text-end">₹{{ number_format((float) $inst->penalty_amount, 2) }}</td>
            <td class="text-end">
              @if($displayPaid > 0.01)
                <div class="d-flex flex-column align-items-end text-end font-monospace" style="font-size: 0.85rem; gap: 2px;">
                  <span class="fw-bold text-dark">
                    Total: ₹{{ number_format($currentPaid, 2) }}
                    @if($inProgressSum > 0.01)
                      <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressSum, 2) }})</span>
                    @endif
                  </span>
                </div>
              @else
                -
              @endif
            </td>
            <td class="text-end fw-semibold">₹{{ number_format($displayRemaining, 2) }}</td>
            <td>
              @if($inst->status === 'paid')
                <span class="badge bg-label-success">Paid</span>
              @elseif($inProgressSum > 0)
                @if($displayRemaining <= 0.01)
                  <span class="badge bg-label-warning text-warning" title="Awaiting Admin Verification">Paid (Unverified)</span>
                @else
                  <span class="badge bg-label-info text-info" title="Awaiting Admin Verification">Partial (Unverified)</span>
                @endif
              @else
                <span class="badge bg-{{ $inst->status_badge }}">{{ ucfirst($inst->status) }}</span>
              @endif
            </td>
            <td>{{ optional($inst->paid_date)->format('d M Y') ?? '—' }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-center py-4 text-muted">No installments generated yet</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection
