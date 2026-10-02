@extends('layouts/layoutMaster')
@section('title', isset($mode) && $mode === 'create' ? 'Enroll Member' : (isset($mode) && $mode === 'edit' ? 'Edit Membership' : 'Group Enrollments'))

@section('page-script')
@vite(['resources/assets/custom-js/chit-shared-membership.js', 'resources/assets/custom-js/chit-need-month.js'])
@endsection

@section('content')
@if(isset($mode) && $mode === 'create')
{{-- ENROLL FORM VIEW --}}
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="icon-base ri ri-user-add-line me-2 text-primary"></i>Enroll Member in Group</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.members.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Chit Group *</label>
                            <select name="group_id" class="form-select select2" required>
                                <option value="">Select forming group...</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}"
                                            data-chit-value="{{ $g->chit_value }}"
                                            data-installment="{{ $g->getInstallmentAmountForMonth(1) }}"
                                            data-payout="{{ $g->resolvePayoutAmountForMonth(max(2, (int) ($g->current_month ?? 0) + 1)) }}"
                                            data-start-date="{{ optional($g->start_date)->format('Y-m-d') }}"
                                            data-total-months="{{ $g->total_months }}"
                                            {{ old('group_id',$group?->id)==$g->id?'selected':'' }}>
                                        {{ $g->group_code }} — {{ $g->seatsFillLabel() }} seats (₹{{ number_format($g->chit_value) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            @include('admin.chit.members.partials.shared-membership-fields', [
                                'clients' => $clients,
                                'group' => $group ?? null,
                                'prefix' => 'create',
                            ])
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold">Agent</label>
                            <select name="agent_id" class="form-select select2">
                                <option value="">Select Agent...</option>
                                @foreach($agents as $a)
                                    <option value="{{ $a->id }}" {{ old('agent_id') == $a->id ? 'selected' : '' }}>
                                        Agent: {{ $a->agent_name }} ({{ $a->agent_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold">Referred By</label>
                            <select name="referred_by" class="form-select select2">
                                <option value="">Select Referrer...</option>
                                <optgroup label="Agents">
                                    @foreach($agents as $a)
                                        <option value="agent:{{ $a->id }}" {{ old('referred_by') == 'agent:'.$a->id ? 'selected' : '' }}>
                                            Agent: {{ $a->agent_name }} ({{ $a->agent_code }})
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Clients">
                                    @foreach($clients as $c)
                                        <option value="client:{{ $c->id }}" {{ old('referred_by') == 'client:'.$c->id ? 'selected' : '' }}>
                                            Client: {{ $c->client_name }} ({{ $c->client_phone }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                        <input type="hidden" name="joined_date" value="{{ old('joined_date', date('Y-m-d')) }}">
                        @if($clients->isEmpty())
                        <div class="col-12">
                            <div class="alert alert-warning py-2">
                                <i class="ri-alert-line me-2"></i>
                                No KYC-verified clients found. <a href="{{ route('client-add') }}">Add clients</a> first.
                            </div>
                        </div>
                        @endif
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="icon-base ri ri-user-add-line me-1"></i>Enroll</button>
                            <a href="{{ route('chit.members.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'edit')
{{-- EDIT MEMBERSHIP --}}
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="icon-base ri ri-pencil-line me-2 text-primary"></i>
                    Edit Membership — {{ $member->group->group_code ?? '' }} #{{ $member->member_number }}
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.members.update', $member) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.chit.members.partials.shared-membership-fields', [
                        'clients' => $clients,
                        'member' => $member,
                        'group' => $member->group,
                        'prefix' => 'edit',
                    ])
                    <div class="row g-3 mt-1">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold">Agent</label>
                            <select name="agent_id" class="form-select select2">
                                <option value="">Select Agent...</option>
                                @foreach($agents as $a)
                                    <option value="{{ $a->id }}" {{ old('agent_id', $member->client?->assigned_to) == $a->id ? 'selected' : '' }}>
                                        Agent: {{ $a->agent_name }} ({{ $a->agent_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold">Referred By</label>
                            <select name="referred_by" class="form-select select2">
                                <option value="">Select Referrer...</option>
                                <optgroup label="Agents">
                                    @foreach($agents as $a)
                                        <option value="agent:{{ $a->id }}" {{ old('referred_by', $member->referred_by_agent_id ? 'agent:'.$member->referred_by_agent_id : '') == 'agent:'.$a->id ? 'selected' : '' }}>
                                            Agent: {{ $a->agent_name }} ({{ $a->agent_code }})
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Clients">
                                    @foreach($clients as $c)
                                        <option value="client:{{ $c->id }}" {{ old('referred_by', $member->referred_by_client_id ? 'client:'.$member->referred_by_client_id : '') == 'client:'.$c->id ? 'selected' : '' }}>
                                            Client: {{ $c->client_name }} ({{ $c->client_phone }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Membership</button>
                            <a href="{{ route('chit.groups.show', $member->group) }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@else
{{-- ENROLLMENTS INDEX LIST — aligned with Loan Accounts UI --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-1">Group Enrollments</h4>
        <p class="text-muted mb-0">Manage group member enrollments and approvals</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('chit.families.index') }}" class="btn btn-label-primary">
            <i class="icon-base ri ri-parent-line me-1"></i>Family Members
        </a>
        <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enroll_member">
            <i class="icon-base ri ri-user-add-line me-1"></i>Enroll Member
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <h5 class="mb-0">
            All Members
            <span class="badge bg-label-primary ms-2">{{ $members->total() }}</span>
        </h5>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-3 ajax-filter-form">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Group:</label>
                <select name="group_id" class="form-select form-select-sm select2" style="min-width: 180px;">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" {{ request('group_id')==$g->id?'selected':'' }}>
                            {{ $g->group_code }} — {{ $g->scheme->name ?? '—' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Status:</label>
                <select name="status" class="form-select form-select-sm no-search" style="width: 140px;">
                    <option value="">All Statuses</option>
                    @foreach(['applied','approved','active','defaulted','completed'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Search:</label>
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 150px;"
                       placeholder="Name / phone / group…" value="{{ request('search') }}">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-filter-3-line me-1"></i>Filter
                </button>
                <a href="{{ route('chit.members.index') }}" class="btn btn-sm btn-label-secondary">
                    <i class="icon-base ri ri-refresh-line me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
    <div class="card-datatable table-responsive ajax-table-container">
        <table class="table text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Client / Owners</th>
                    <th>Group</th>
                    <th>Scheme</th>
                    <th>Assigned Agent</th>
                    <th>Status</th>
                    <th>Settlement Amount</th>
                    <th>Chit Need Month</th>
                    <th>Won Auction</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($members as $member)
                @php
                    $settleInfo = $member->settlement_status_info;
                @endphp
                <tr>
                    <td class="fw-medium">{{ $member->display_member_number }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar avatar-sm">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    {{ strtoupper(substr($member->client->client_name ?? 'NA', 0, 2)) }}
                                </span>
                            </div>
                            <div>
                                @if($member->is_shared)
                                    <div class="fw-semibold text-heading">{{ $member->owners_display }}</div>
                                    <span class="badge bg-label-primary" style="font-size:.65rem;">Shared</span>
                                @else
                                    <div class="fw-semibold text-heading">{{ $member->client->client_name ?? '—' }}</div>
                                    <small class="text-muted">{{ $member->client->client_phone ?? '' }}</small>
                                @endif
                                <span class="badge bg-label-info ms-1" style="font-size:.65rem;">{{ rtrim(rtrim(number_format($member->effective_share_percentage, 2), '0'), '.') }}% Share</span>
                                @if(($member->collection_frequency ?? 'monthly') !== 'monthly')
                                    <span class="badge bg-label-warning ms-1" style="font-size:.65rem;">{{ $member->collection_frequency_label }}</span>
                                @else
                                    <span class="badge bg-label-secondary ms-1" style="font-size:.65rem;">Monthly</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($member->group)
                            <a href="{{ route('chit.groups.show', $member->group) }}" class="fw-semibold text-primary text-decoration-none">
                                {{ $member->group->group_code }}
                            </a>
                        @else
                            <span class="text-muted">Group removed</span>
                        @endif
                    </td>
                    <td>{{ $member->group?->scheme?->name ?? '—' }}</td>
                    <td>
                        @if($member->assigned_agent_name !== '—')
                            <span class="badge bg-label-info">
                                <i class="icon-base ri ri-user-star-line me-1"></i>{{ $member->assigned_agent_name }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-label-{{ $member->status_badge }}">{{ ucfirst($member->status) }}</span>
                    </td>
                    <td class="fw-semibold text-success">₹{{ number_format($settleInfo['amount'], 2) }}</td>
                    <td>
                        @if($member->chit_need_formatted !== '—')
                            <span class="text-success fw-semibold">
                                <i class="icon-base ri ri-calendar-event-line me-1"></i>{{ $member->chit_need_formatted }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($member->has_won_auction)
                            <span class="badge bg-label-success">
                                <i class="icon-base ri ri-checkbox-circle-line me-1"></i>Yes
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            @if($member->client_id)
                            @php
                                $mPublicToken = \App\Support\HashId::encode($member->client_id);
                                $mPublicLink = route('public.view-chit-schedule', $mPublicToken);
                            @endphp
                            <a href="{{ $mPublicLink }}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Public chit schedule">
                                <i class="icon-base ri ri-share-line icon-22px"></i>
                            </a>
                            @endif
                            @if($member->group)
                                @if(in_array($settleInfo['status'], ['eligible', 'pending']))
                                <a href="{{ route('chit.settlements.confirm', [$member->group_id, $member->id]) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Chit Settlement">
                                    <i class="icon-base ri ri-money-rupee-circle-line icon-22px"></i>
                                </a>
                                @endif
                                <a href="{{ route('chit.members.edit', $member) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Edit">
                                    <i class="icon-base ri ri-pencil-line icon-22px"></i>
                                </a>
                                @php
                                    $canTransferMember = $member->canBeTransferred($member->group);
                                    $transferBlockedReason = $member->transferBlockReason($member->group);
                                @endphp
                                @if(!in_array($member->status, \App\Models\GroupMember::INACTIVE_STATUSES, true))
                                    @if($canTransferMember)
                                        <a href="{{ route('chit.members.transfer.create', $member) }}" class="btn btn-sm btn-icon btn-text-info rounded-pill" title="Transfer Member">
                                            <i class="icon-base ri ri-exchange-line icon-22px"></i>
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" disabled title="{{ $transferBlockedReason }}">
                                            <i class="icon-base ri ri-exchange-line icon-22px"></i>
                                        </button>
                                    @endif
                                @endif
                                @if($member->status === 'applied')
                                <form method="POST" action="{{ route('chit.members.approve', $member) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-success rounded-pill" title="Approve">
                                        <i class="icon-base ri ri-checkbox-circle-line icon-22px"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('chit.members.reject', $member) }}" class="d-inline" onsubmit="return confirm('Reject this member?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Reject">
                                        <i class="icon-base ri ri-close-circle-line icon-22px"></i>
                                    </button>
                                </form>
                                @endif
                                @php
                                    $canDeleteMember = $member->canDeleteFromGroup();
                                    $memberPaidSettlement = $member->group->relationLoaded('payouts')
                                        ? $member->group->payouts->where('winner_member_id', $member->id)->where('status', 'paid')->isNotEmpty()
                                        : \App\Models\Payout::where('winner_member_id', $member->id)->where('status', 'paid')->exists();
                                    $canCancelMember = ! $canDeleteMember
                                        && in_array($member->status, ['active', 'approved', 'defaulted', 'frozen'], true)
                                        && ! $member->has_won_auction
                                        && ! $memberPaidSettlement;
                                @endphp
                                @if($canDeleteMember)
                                <form method="POST" action="{{ route('chit.members.destroy', $member) }}" class="d-inline" onsubmit="return confirm('Permanently delete this client from the group? Available until the first month installment is paid.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Delete Client (until first month paid)">
                                        <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                    </button>
                                </form>
                                @elseif($canCancelMember)
                                <form method="POST" action="{{ route('chit.members.cancel', $member) }}" class="d-inline" onsubmit="return confirm('Cancel this client\'s chit? Settlement = paid months − foreman commission (apply separately).');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-icon btn-text-warning rounded-pill" title="Cancel Chit">
                                        <i class="icon-base ri ri-prohibited-line icon-22px"></i>
                                    </button>
                                </form>
                                @endif
                            @else
                                <span class="text-muted small">Orphan record</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="icon-base ri ri-user-unfollow-line icon-32px d-block mb-2"></i>
                        No members found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($members->hasPages())
        <div class="card-footer">{{ $members->links() }}</div>
    @endif
</div>

<!-- Enroll Member Modal -->
<div class="modal fade" id="enroll_member" tabindex="-1" aria-labelledby="enroll_member" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="icon-base ri ri-user-add-line me-1 text-primary"></i>Enroll New Member
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="enrollMemberForm" action="{{ route('chit.members.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Chit Group <span class="text-danger">*</span></label>
                        <select name="group_id" class="form-select select2" required>
                            <option value="">Select Group</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" data-chit-value="{{ $group->chit_value }}"
                                    data-installment="{{ $group->getInstallmentAmountForMonth(1) }}"
                                    data-payout="{{ $group->resolvePayoutAmountForMonth(max(2, (int) ($group->current_month ?? 0) + 1)) }}"
                                    data-start-date="{{ optional($group->start_date)->format('Y-m-d') }}"
                                    data-total-months="{{ $group->total_months }}">
                                    {{ $group->group_code }} ({{ $group->scheme->name ?? '' }}) — ₹{{ number_format($group->chit_value) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @include('admin.chit.members.partials.shared-membership-fields', [
                        'clients' => $clients,
                        'prefix' => 'modal',
                    ])
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold">Agent</label>
                            <select name="agent_id" class="form-select select2">
                                <option value="">Select Agent...</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-12">
                            <label class="form-label fw-semibold">Referred By</label>
                            <select name="referred_by" class="form-select select2">
                                <option value="">Select Referrer...</option>
                                <optgroup label="Agents">
                                    @foreach($agents as $agent)
                                        <option value="agent:{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Clients">
                                    @foreach($clients as $client)
                                        <option value="client:{{ $client->id }}">{{ $client->client_name }} ({{ $client->client_phone }})</option>
                                    @endforeach
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="joined_date" value="{{ date('Y-m-d') }}">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="enrollMemberForm" class="btn btn-primary">
                    <i class="icon-base ri ri-user-add-line me-1"></i>Enroll
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
