@extends('layouts/layoutMaster')
@section('title', isset($mode) && $mode === 'show'
    ? 'Dividend — Month ' . $dividend->month_number
    : (isset($mode) && $mode === 'pool'
        ? 'Dividend Pool — ' . ($group->group_code ?? '')
        : 'Dividend Management'))

@section('content')
@if(isset($mode) && $mode === 'show')
{{-- SHOW AUCTION DIVIDEND DETAILS --}}
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Dividend Info</h5>
                <span class="badge bg-{{ $dividend->status == 'distributed' ? 'success' : 'warning' }}">{{ ucfirst($dividend->status) }}</span>
            </div>
            <div class="card-body p-0">
                @php $rows = [
                    ['Group', $dividend->group?->group_code ?? '—'],
                    ['Month', 'Month ' . $dividend->month_number],
                    ['Chit Value', '₹' . number_format($dividend->chit_value)],
                    ['Discount', '₹' . number_format($dividend->discount)],
                    ['Commission', '₹' . number_format($dividend->commission_amount)],
                    ['Net Dividend', '₹' . number_format($dividend->net_dividend)],
                    ['Per Member', '₹' . number_format($dividend->per_member_dividend)],
                ]; @endphp
                @foreach($rows as [$k, $v])
                <div class="d-flex justify-content-between px-4 py-2 border-bottom">
                    <span class="text-muted">{{ $k }}</span>
                    <span class="fw-bold">{{ $v }}</span>
                </div>
                @endforeach
            </div>
            <div class="card-footer">
                @if($dividend->status != 'distributed')
                <form action="{{ route('chit.dividends.distribute', $dividend) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Distribute to all members?')">
                        <i class="ri-send-plane-line me-1"></i>Distribute Now
                    </button>
                </form>
                @endif
                <a href="{{ route('chit.dividends.index', ['view' => 'auction']) }}" class="btn btn-label-secondary w-100 mt-2">Back to List</a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Member Distributions</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Member #</th>
                                <th>Client</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dividend->distributions as $dist)
                            <tr>
                                <td>{{ $dist->member->member_number }}</td>
                                <td>{{ $dist->member->client->client_name }}</td>
                                <td>₹{{ number_format($dist->amount) }}</td>
                                <td><span class="badge bg-{{ $dist->status == 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($dist->status) }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'pool')
{{-- GROUP DIVIDEND POOL LEDGER --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Dividend Pool — {{ $group->group_code }}</h4>
        <p class="text-muted mb-0">{{ $group->scheme->name ?? '—' }} · Chit value ₹{{ number_format($group->chit_value, 0) }} · {{ $group->total_members }} Members</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-label-primary"><i class="ri-group-line me-1"></i>View Group</a>
        <a href="{{ route('chit.dividends.index') }}" class="btn btn-label-secondary"><i class="ri-arrow-left-line me-1"></i>Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2_4 col-sm-6">
        <div class="card border-success border-opacity-25 h-100">
            <div class="card-body py-3">
                <small class="text-muted d-block">Current Pool Balance</small>
                <h4 class="mb-0 text-success fw-bold">₹{{ number_format($group->dividend_pool_balance ?? 0, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2_4 col-sm-6">
        <div class="card border-primary border-opacity-25 h-100">
            <div class="card-body py-3">
                <small class="text-muted d-block">Total Dividend Amount Sum</small>
                <h4 class="mb-0 text-primary fw-bold">₹{{ number_format($totalDividendSum ?? 0, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2_4 col-sm-6">
        <div class="card border-info border-opacity-25 h-100">
            <div class="card-body py-3">
                <small class="text-muted d-block">Total Payout Amount Sum</small>
                <h4 class="mb-0 text-info fw-bold">₹{{ number_format($totalPayoutSum ?? $allPayoutSum ?? 0, 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2_4 col-sm-6">
        <div class="card h-100">
            <div class="card-body py-3">
                <small class="text-muted d-block">Total Surplus Credited</small>
                <h4 class="mb-0 text-success">₹{{ number_format($group->dividendPoolEntries()->where('entry_type', 'credit')->sum('amount'), 2) }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2_4 col-sm-6">
        <div class="card h-100">
            <div class="card-body py-3">
                <small class="text-muted d-block">Total Excess Drawn</small>
                <h4 class="mb-0 text-warning">₹{{ number_format($group->dividendPoolEntries()->where('entry_type', 'debit')->sum('amount'), 2) }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="ri-information-line me-1"></i>
    <strong>Flow:</strong> If monthly payout is below chit value (e.g. ₹1,00,000 − ₹85,000 = ₹15,000), surplus goes to this pool.
    If a later month payout is above chit value (e.g. ₹1,20,000), the extra ₹20,000 is drawn from this pool.
</div>

{{-- Monthly Dividend & Payout Breakdown Table --}}
<div class="card mb-4 shadow-xs">
    <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">
            <i class="ri-calendar-schedule-line me-2 text-primary"></i>All Monthly Dividend &amp; Payout Breakdown
            <span class="badge bg-primary ms-2">{{ ($monthlyBreakdown ?? collect())->count() }} Months</span>
        </h5>
        <div class="text-muted small">
            Chit Value: <strong>₹{{ number_format($group->chit_value, 2) }}</strong> &bull; Total Members: <strong>{{ $group->total_members }}</strong>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">Month #</th>
                        <th>Period / Calendar Month</th>
                        <th class="text-end">Monthly Installment</th>
                        <th class="text-end">Per Member Dividend</th>
                        <th class="text-end">Total Group Dividend Sum</th>
                        <th class="text-end">Payout Amount Sum</th>
                        <th>Winner Member / Client</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($monthlyBreakdown ?? collect()) as $mRow)
                    <tr>
                        <td class="text-center fw-bold">Month {{ $mRow['month_number'] }}</td>
                        <td><span class="fw-medium text-dark">{{ $mRow['month_label'] }}</span></td>
                        <td class="text-end">₹{{ number_format($mRow['installment_amount'], 2) }}</td>
                        <td class="text-end text-warning fw-semibold">
                            {{ $mRow['per_member_dividend'] > 0 ? '₹' . number_format($mRow['per_member_dividend'], 2) : '—' }}
                        </td>
                        <td class="text-end text-primary fw-bold">
                            {{ $mRow['net_dividend'] > 0 ? '₹' . number_format($mRow['net_dividend'], 2) : '—' }}
                        </td>
                        <td class="text-end text-info fw-bold">
                            ₹{{ number_format($mRow['payout_amount'], 2) }}
                        </td>
                        <td>
                            @if($mRow['winner_name'])
                                <div class="fw-semibold text-dark"><i class="ri-user-star-line text-warning me-1"></i>{{ $mRow['winner_name'] }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($mRow['payout'])
                                <span class="badge bg-{{ $mRow['payout']->status_badge }}">{{ $mRow['status'] }}</span>
                            @elseif($mRow['auction'])
                                <span class="badge bg-info text-white">Auction Done</span>
                            @elseif($group->isForemanCommissionMonth($mRow['month_number']))
                                <span class="badge bg-secondary">Foreman Month</span>
                            @else
                                <span class="badge bg-light text-muted">Scheduled</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-5 text-muted">No monthly schedule data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Pool Movements Ledger Card --}}
<div class="card shadow-xs">
    <div class="card-header border-bottom py-3"><h5 class="mb-0"><i class="ri-history-line me-2 text-success"></i>Dividend Pool Movements Ledger</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Month</th>
                        <th>Type</th>
                        <th>Chit Value</th>
                        <th>Payout</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th>Member / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $entry->month_number ? 'Month '.$entry->month_number : '—' }}</td>
                        <td><span class="badge bg-label-{{ $entry->entry_type_badge }}">{{ $entry->entry_type_label }}</span></td>
                        <td>₹{{ number_format($entry->chit_value ?? 0, 2) }}</td>
                        <td>₹{{ number_format($entry->payout_amount ?? 0, 2) }}</td>
                        <td class="fw-semibold {{ $entry->entry_type === 'credit' ? 'text-success' : 'text-warning' }}">
                            {{ $entry->entry_type === 'credit' ? '+' : '−' }}₹{{ number_format($entry->amount, 2) }}
                        </td>
                        <td>₹{{ number_format($entry->balance_after, 2) }}</td>
                        <td>
                            @if($entry->payout?->winner?->client)
                                <div class="fw-medium">{{ $entry->payout->winner->client->client_name }}</div>
                            @endif
                            <small class="text-muted">{{ $entry->remarks }}</small>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-5 text-muted">No pool movements yet. Surplus posts when a settlement payout below chit value is paid.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($entries->hasPages())
        <div class="card-footer">{{ $entries->links() }}</div>
    @endif
</div>

@else
{{-- INDEX: Group dividend pool (default) + auction dividends tab --}}
@php $activeView = $view ?? request('view', 'pool'); @endphp

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Dividend Management</h4>
        <p class="text-muted mb-0">Group surplus pool, dividends sum, payout sum, and auction distributions</p>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $activeView === 'pool' ? 'active' : '' }}" href="{{ route('chit.dividends.index', ['view' => 'pool'] + request()->except('view', 'page', 'status')) }}">
            <i class="ri-wallet-3-line me-1"></i>Group Dividend Pool
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeView === 'auction' ? 'active' : '' }}" href="{{ route('chit.dividends.index', ['view' => 'auction'] + request()->except('view', 'page', 'has_balance')) }}">
            <i class="ri-auction-line me-1"></i>Auction Dividends
        </a>
    </li>
</ul>

@if($activeView === 'pool')
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-3">
            <small class="text-muted">Total Pool Balance</small>
            <h4 class="mb-0 text-success fw-bold">₹{{ number_format($poolTotals['balance'] ?? 0, 2) }}</h4>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-3">
            <small class="text-muted">Total Dividend Sum</small>
            <h4 class="mb-0 text-primary fw-bold">₹{{ number_format($poolTotals['total_dividends'] ?? 0, 2) }}</h4>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-3">
            <small class="text-muted">Total Payout Sum</small>
            <h4 class="mb-0 text-info fw-bold">₹{{ number_format($poolTotals['total_payouts'] ?? 0, 2) }}</h4>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body py-3">
            <small class="text-muted">Total Surplus In</small>
            <h4 class="mb-0 text-success">₹{{ number_format($poolTotals['credits'] ?? 0, 2) }}</h4>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end ajax-filter-form">
            <input type="hidden" name="view" value="pool">
            <div class="col-md-4">
                <select name="group_id" class="form-select form-select-sm select2">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected((string) request('group_id') === (string) $g->id)>
                            {{ $g->group_code }} — {{ $g->scheme->name ?? '—' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="has_balance" class="form-select form-select-sm">
                    <option value="">All balances</option>
                    <option value="1" @selected(request('has_balance') === '1')>With pool balance</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('chit.dividends.index', ['view' => 'pool']) }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card ajax-table-container">
    <div class="card-header"><h5 class="mb-0">Group-wise Dividend Pool <span class="badge bg-primary ms-2">{{ $poolGroups->total() }}</span></h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Group</th>
                        <th>Scheme</th>
                        <th>Chit Value</th>
                        <th class="text-end">Dividend Sum</th>
                        <th class="text-end">Payout Sum</th>
                        <th class="text-end">Surplus In</th>
                        <th class="text-end">Excess Drawn</th>
                        <th class="text-end">Pool Balance</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($poolGroups as $g)
                    <tr>
                        <td>
                            <a href="{{ route('chit.groups.show', $g) }}" class="fw-semibold text-primary text-decoration-none">{{ $g->group_code }}</a>
                            <div><span class="badge bg-label-{{ $g->status_badge }}">{{ ucfirst($g->status) }}</span></div>
                        </td>
                        <td>{{ $g->scheme->name ?? '—' }}</td>
                        <td>₹{{ number_format($g->chit_value, 0) }}</td>
                        <td class="text-end text-primary fw-semibold">₹{{ number_format($g->calculated_total_dividend_sum ?? $g->total_dividend_sum ?? 0, 2) }}</td>
                        <td class="text-end text-info fw-semibold">₹{{ number_format($g->total_payout_sum ?? 0, 2) }}</td>
                        <td class="text-end text-success">₹{{ number_format($g->pool_credit_total ?? 0, 2) }}</td>
                        <td class="text-end text-warning">₹{{ number_format($g->pool_debit_total ?? 0, 2) }}</td>
                        <td class="text-end fw-bold text-success">₹{{ number_format($g->dividend_pool_balance ?? 0, 2) }}</td>
                        <td class="text-center">
                            <a href="{{ route('chit.dividends.pool', $g) }}" class="btn btn-sm btn-label-primary" title="View group pool & monthly dividends">
                                <i class="ri-eye-line me-1"></i>View Pool
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-5 text-muted">No groups found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($poolGroups->hasPages())
        <div class="card-footer">{{ $poolGroups->links() }}</div>
    @endif
</div>

@else
{{-- Auction discount dividends (legacy) --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end ajax-filter-form">
            <input type="hidden" name="view" value="auction">
            <div class="col-md-4">
                <select name="group_id" class="form-select form-select-sm select2">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)<option value="{{ $g->id }}" {{ request('group_id')==$g->id?'selected':'' }}>{{ $g->group_code }} — {{ $g->scheme->name ?? '—' }} (₹{{ number_format($g->chit_value, 0) }})</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['pending','processed','distributed'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('chit.dividends.index', ['view' => 'auction']) }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card ajax-table-container">
    <div class="card-header"><h5 class="mb-0">Auction Dividends <span class="badge bg-primary ms-2">{{ $dividends->total() }}</span></h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Group</th><th>Month</th><th>Chit Value</th><th>Discount</th><th>Commission</th><th>Net Dividend</th><th>Per Member</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($dividends as $div)
                    <tr>
                        <td>{{ $div->group?->group_code ?? '—' }}</td>
                        <td>Month {{ $div->month_number }}</td>
                        <td>₹{{ number_format($div->chit_value) }}</td>
                        <td class="text-success">₹{{ number_format($div->discount) }}</td>
                        <td class="text-warning">₹{{ number_format($div->commission_amount) }}</td>
                        <td class="fw-bold">₹{{ number_format($div->net_dividend) }}</td>
                        <td>₹{{ number_format($div->per_member_dividend) }}</td>
                        <td><span class="badge {{ $div->status==='distributed'?'bg-success':($div->status==='processed'?'bg-info':'bg-warning text-dark') }}">{{ ucfirst($div->status) }}</span></td>
                        <td>
                            <a href="{{ route('chit.dividends.show',$div) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                            @if($div->status !== 'distributed')
                            <form method="POST" action="{{ route('chit.dividends.distribute',$div) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" onclick="return confirm('Distribute dividends to all members?')">
                                    <i class="ri-send-plane-line"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-5 text-muted">No auction dividend records</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($dividends->hasPages())<div class="card-footer">{{ $dividends->links() }}</div>@endif
</div>
@endif
@endif
@endsection
