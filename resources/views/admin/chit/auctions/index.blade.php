@extends('layouts/layoutMaster')
@section('title', isset($mode) && $mode === 'show' ? 'Auction — Month '.$auction->month_number : (isset($mode) && $mode === 'create' ? 'Schedule Auction' : 'Auction Winners'))

@section('content')
@if(isset($mode) && $mode === 'create')
{{-- CREATE AUCTION FORM VIEW --}}
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5><i class="ri-auction-line me-2 text-warning"></i>New Auction</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.auctions.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Group *</label>
                            <select name="group_id" class="form-select select2" required>
                                <option value="">Select active group...</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->group_code }} — ₹{{ number_format($g->chit_value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Month Number *</label>
                            <input type="number" name="month_number" class="form-control" min="1" value="{{ old('month_number') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Auction Date *</label>
                            <input type="date" name="auction_date" class="form-control" value="{{ old('auction_date', today()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Time</label>
                            <input type="time" name="auction_time" class="form-control" value="{{ old('auction_time', '10:00') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Min Bid (₹)</label>
                            <input type="number" name="min_bid" class="form-control" value="{{ old('min_bid') }}" step="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Duration (Minutes) *</label>
                            <input type="number" name="duration_minutes" class="form-control" value="{{ old('duration_minutes', 30) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bid Type *</label>
                            <select name="bid_type" class="form-select" required>
                                <option value="open">Open Bid (Real-time lowest shown)</option>
                                <option value="closed">Closed Bid (Hides other bids)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Location</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="e.g., Head Office">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="ri-calendar-event-line me-1"></i>Schedule</button>
                            <a href="{{ route('chit.auctions.index') }}" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@elseif(isset($mode) && $mode === 'show')
{{-- SHOW AUCTION DETAILS VIEW --}}
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0">Auction Info</h5>
                <span class="badge bg-{{ $auction->status_badge }}">{{ ucfirst($auction->status) }}</span>
            </div>
            <div class="card-body p-0">
                @php $rows = [
                    ['Group', $auction->group?->group_code ?? '—'],
                    ['Month', 'Month '.$auction->month_number],
                    ['Date', Carbon\Carbon::parse($auction->auction_date)->format('d M Y')],
                    ['Chit Value', '₹'.number_format($auction->group?->chit_value ?? 0)],
                    ['Min Bid', $auction->min_bid ? '₹'.number_format($auction->min_bid) : '—'],
                    ['Winning Bid', $auction->winning_bid ? '₹'.number_format($auction->winning_bid) : '—'],
                    ['Discount', $auction->discount ? '₹'.number_format($auction->discount) : '—'],
                    ['Winner', $auction->winner?->client->client_name ?? '—'],
                ]; @endphp
                @foreach($rows as [$k,$v])
                <div class="d-flex justify-content-between px-4 py-2 border-bottom">
                    <span class="text-muted" style="font-size:.82rem;">{{ $k }}</span>
                    <span style="font-size:.85rem;font-weight:500;">{{ $v }}</span>
                </div>
                @endforeach
            </div>
            <div class="card-footer">
                @if($auction->status === 'scheduled')
                <form method="POST" action="{{ route('chit.auctions.declare-winner', $auction) }}" class="w-100">
                    @csrf
                    <div class="alert alert-info py-2 mb-2" style="font-size:.82rem;">
                        <i class="ri-information-line me-1"></i>Lowest bid will automatically win.
                    </div>
                    <button type="submit" class="btn btn-warning w-100"
                        onclick="return confirm('Declare winner based on lowest bid?')">
                        <i class="ri-award-line me-1"></i>Declare Winner
                    </button>
                </form>
                @elseif($auction->status === 'completed')
                <div class="text-center">
                    @if($auction->dividend)
                        <a href="{{ route('chit.dividends.show',$auction->dividend) }}" class="btn btn-sm btn-outline-success me-2">
                            <i class="ri-percent-line me-1"></i>View Dividend
                        </a>
                    @endif
                    @if($auction->payout)
                        <a href="{{ route('chit.payouts.show',$auction->payout) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ri-money-rupee-circle-line me-1"></i>View Payout
                        </a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Add Bid -->
        @if($auction->status === 'scheduled')
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="ri-add-circle-line me-2 text-primary"></i>Add Bid</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('chit.auctions.bid', $auction) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Member</label>
                        <select name="member_id" class="form-select select2" required>
                            <option value="">Select eligible member...</option>
                            @foreach($eligibleMembers as $m)
                                <option value="{{ $m->id }}"># {{ $m->member_number }} — {{ $m->client->client_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Bid Amount (₹)</label>
                        <input type="number" name="bid_amount" class="form-control" step="100"
                            min="{{ $auction->min_bid ?? 1 }}" max="{{ $auction->max_bid ?? ($auction->group?->chit_value ?? 0) }}" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="ri-price-tag-3-line me-1"></i>Place Bid</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <!-- Bids List -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="ri-list-check-2 me-2 text-warning"></i>All Bids ({{ $auction->bids->count() }})</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr><th>Member</th><th>Client</th><th>Bid Amount</th><th>Winner</th></tr>
                        </thead>
                        <tbody>
                            @forelse($auction->bids->sortBy('bid_amount') as $bid)
                            <tr class="{{ $bid->is_winner ? 'table-success' : '' }}">
                                <td># {{ $bid->member->member_number }}</td>
                                <td>{{ $bid->member->client->client_name }}</td>
                                <td class="fw-bold">₹{{ number_format($bid->bid_amount) }}</td>
                                <td>
                                    @if($bid->is_winner)
                                        <span class="badge bg-success"><i class="ri-award-line me-1"></i>Winner</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">No bids placed yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@else
{{-- DEFAULT INDEX: Auction Winners + Auctions --}}
@php $activeTab = $tab ?? request('tab', 'winners'); @endphp
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1">Auction Winners</h4>
        <p class="text-muted mb-0">Settled members appear here as winners after payout is released</p>
    </div>
    <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#schedule_auction"><i class="ri-add-line me-1"></i>Schedule Auction</a>
</div>

<ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'winners' ? 'active' : '' }}" href="{{ route('chit.auctions.index', array_merge(request()->except(['tab', 'auctions_page', 'winners_page']), ['tab' => 'winners'])) }}">
            <i class="ri-award-line me-1"></i>Winners
            <span class="badge bg-label-success ms-1">{{ isset($winners) ? $winners->total() : 0 }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $activeTab === 'auctions' ? 'active' : '' }}" href="{{ route('chit.auctions.index', array_merge(request()->except(['tab', 'auctions_page', 'winners_page']), ['tab' => 'auctions'])) }}">
            <i class="ri-auction-line me-1"></i>Auctions
            <span class="badge bg-label-primary ms-1">{{ isset($auctions) ? $auctions->total() : 0 }}</span>
        </a>
    </li>
</ul>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            <div class="col-md-4">
                <select name="group_id" class="form-select form-select-sm select2">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" {{ (string) request('group_id') === (string) $g->id ? 'selected' : '' }}>{{ $g->group_code }} — {{ $g->scheme->name ?? '—' }} (₹{{ number_format($g->chit_value, 0) }})</option>
                    @endforeach
                </select>
            </div>
            @if($activeTab === 'auctions')
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['scheduled','open','completed','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Search winner / code / group">
            </div>
            @endif
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('chit.auctions.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-light ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

@if($activeTab === 'winners')
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Payout Code</th>
                        <th>Winner</th>
                        <th>Group</th>
                        <th>Month</th>
                        <th>Settlement Amount</th>
                        <th>Paid On</th>
                        <th>Documents</th>
                        <th>Client</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($winners ?? collect()) as $winner)
                    @php
                        $client = $winner->winner?->client;
                    @endphp
                    <tr>
                        <td><code>{{ $winner->payout_code }}</code></td>
                        <td>
                            <div class="fw-semibold">{{ $client->client_name ?? ($winner->winner?->owners_display ?? '—') }}</div>
                            <small class="text-muted">Member #{{ $winner->winner?->member_number ?? '—' }}</small>
                        </td>
                        <td><strong>{{ $winner->group?->group_code ?? '—' }}</strong></td>
                        <td><span class="badge bg-label-warning">Month {{ $winner->month_number }}</span></td>
                        <td class="fw-semibold text-success">₹{{ number_format((float) $winner->payout_amount, 2) }}</td>
                        <td>{{ $winner->paid_date ? \Carbon\Carbon::parse($winner->paid_date)->format('d M Y') : '—' }}</td>
                        <td>
                            @if($winner->settlement_document || $winner->other_document || $winner->collateral_document)
                                <div class="d-flex flex-wrap gap-1">
                                    @if($winner->settlement_document)
                                        <a href="{{ asset('storage/' . $winner->settlement_document) }}" target="_blank" class="btn btn-xs btn-outline-primary" title="Settlement Deed"><i class="ri-file-text-line"></i></a>
                                    @endif
                                    @if($winner->collateral_document)
                                        <a href="{{ asset('storage/' . $winner->collateral_document) }}" target="_blank" class="btn btn-xs btn-outline-warning" title="Collateral"><i class="ri-shield-check-line"></i></a>
                                    @endif
                                    @if($winner->other_document)
                                        <a href="{{ asset('storage/' . $winner->other_document) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Other Document"><i class="ri-folder-shield-2-line"></i></a>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($client)
                                <a href="{{ route('client-view-chits', $client->id) }}" class="btn btn-sm btn-label-primary" title="View Client Chits">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-5 text-muted">No settlement winners yet. Release a settlement to list the client here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(isset($winners) && $winners->hasPages())
        <div class="card-footer">{{ $winners->links() }}</div>
    @endif
</div>
@else
<div class="card ajax-table-container">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Group</th><th>Month</th><th>Date</th><th>Min Bid</th><th>Winner</th><th>Winning Bid</th><th>Discount</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($auctions as $auction)
                    <tr>
                        <td><strong>{{ $auction->group?->group_code ?? '—' }}</strong></td>
                        <td>Month {{ $auction->month_number }}</td>
                        <td>{{ Carbon\Carbon::parse($auction->auction_date)->format('d M Y') }}</td>
                        <td>{{ $auction->min_bid ? '₹'.number_format($auction->min_bid) : '—' }}</td>
                        <td>{{ $auction->winner?->client->client_name ?? '—' }}</td>
                        <td>{{ $auction->winning_bid ? '₹'.number_format($auction->winning_bid) : '—' }}</td>
                        <td class="text-success">{{ $auction->discount ? '₹'.number_format($auction->discount) : '—' }}</td>
                        <td><span class="badge bg-{{ $auction->status_badge }}">{{ ucfirst($auction->status) }}</span></td>
                        <td><a href="{{ route('chit.auctions.show',$auction) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a></td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-5 text-muted">No auctions scheduled</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($auctions->hasPages())<div class="card-footer">{{ $auctions->links() }}</div>@endif
</div>
@endif

<!-- Schedule Auction Modal -->
<div class="modal fade" id="schedule_auction" tabindex="-1" aria-labelledby="schedule_auction" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule New Auction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start">
                <form id="scheduleAuctionForm" action="{{ route('chit.auctions.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Chit Group <span class="text-danger">*</span></label>
                                <select name="group_id" class="form-select select2" required>
                                    <option value="">Select Group</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->group_code }} ({{ $group->scheme->name ?? '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-6 col-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Month Number <span class="text-danger">*</span></label>
                                <input type="number" name="month_number" class="form-control" placeholder="e.g. 1" min="1" required>
                            </div>
                        </div>
                        <div class="col-lg-6 col-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Min Bid Amount (₹)</label>
                                <input type="number" name="min_bid" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                        <div class="col-lg-6 col-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Auction Date <span class="text-danger">*</span></label>
                                <input type="date" name="auction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-lg-6 col-12">
                            <div class="form-group mb-3">
                                <label class="form-label">Auction Time</label>
                                <input type="time" name="auction_time" class="form-control" value="10:00">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="form-label">Location / Venue</label>
                                <input type="text" name="location" class="form-control" placeholder="Branch Office / Online">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('scheduleAuctionForm').submit()">Schedule Auction</button>
            </div>
        </div>
    </div>
</div>
<!-- /Schedule Auction Modal -->
@endif
@endsection
