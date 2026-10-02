@extends('layouts/layoutMaster')

@section('title', 'Referral Bonuses')

@section('content')
@php
  // Calculate quick summary metrics
  $allBonuses = \App\Models\ChitReferralBonus::all();
  $totalEarned = $allBonuses->sum('bonus_amount');
  $totalPending = $allBonuses->where('status', 'pending')->sum('bonus_amount');
  $totalPaid = $allBonuses->where('status', 'paid')->sum('bonus_amount');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">Referral Bonuses</h4>
    <p class="text-muted mb-0">Track and manage referral commissions paid to agents for member enrollments.</p>
  </div>
  <div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReferralBonusModal">
      <i class="ri-add-line me-1"></i> Add Referral Bonus
    </button>
  </div>
</div>

<!-- Stats Card -->
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-4">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded bg-label-primary"><i class="ri-money-rupee-circle-line ri-24px"></i></span>
          </div>
          <h4 class="mb-0">₹{{ number_format($totalEarned, 2) }}</h4>
        </div>
        <p class="mb-0 text-muted">Total Referral Bonus Generated</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-4">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded bg-label-warning"><i class="ri-time-line ri-24px"></i></span>
          </div>
          <h4 class="mb-0">₹{{ number_format($totalPending, 2) }}</h4>
        </div>
        <p class="mb-0 text-muted">Pending Payouts</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-4">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded bg-label-success"><i class="ri-checkbox-circle-line ri-24px"></i></span>
          </div>
          <h4 class="mb-0">₹{{ number_format($totalPaid, 2) }}</h4>
        </div>
        <p class="mb-0 text-muted">Paid Bonuses</p>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4 border shadow-none">
  <div class="card-body py-3">
    <form method="GET" action="{{ route('chit.referral-bonuses.index') }}" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Referrer</label>
        <select name="referrer_id" class="form-select form-select-sm select2">
          <option value="">All Referrers</option>
          <optgroup label="Agents">
            @foreach($agents as $agent)
              <option value="agent:{{ $agent->id }}" {{ request('referrer_id') === 'agent:'.$agent->id ? 'selected' : '' }}>
                {{ $agent->agent_name }} ({{ $agent->agent_code }})
              </option>
            @endforeach
          </optgroup>
          <optgroup label="Clients">
            @foreach($clients as $client)
              <option value="client:{{ $client->id }}" {{ request('referrer_id') === 'client:'.$client->id ? 'selected' : '' }}>
                {{ $client->client_name }} ({{ $client->client_phone }})
              </option>
            @endforeach
          </optgroup>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
        </select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="ri-filter-line me-1"></i> Filter</button>
      </div>
      <div class="col-md-2">
        <a href="{{ route('chit.referral-bonuses.index') }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border shadow-none">
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Referrer</th>
          <th>Enrolled Member</th>
          <th>Chit Group</th>
          <th>Bonus Amount</th>
          <th>Configured %</th>
          <th>Status</th>
          <th>Paid Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse($bonuses as $bonus)
          <tr>
            <td>
              <div class="d-flex align-items-center">
                @if($bonus->referrerAgent)
                  <div class="me-2">
                    <span class="badge bg-label-info"><i class="ri-user-star-line me-1"></i>{{ $bonus->referrerAgent->agent_code }}</span>
                  </div>
                  <span class="fw-semibold">{{ $bonus->referrerAgent->agent_name }} (Agent)</span>
                @elseif($bonus->referrerClient)
                  <div class="me-2">
                    <span class="badge bg-label-secondary"><i class="ri-user-line me-1"></i>Client</span>
                  </div>
                  <span class="fw-semibold">{{ $bonus->referrerClient->client_name }} (Client)</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </div>
            </td>
            <td>
              @if($bonus->member && $bonus->member->client)
                <div class="d-flex flex-column">
                  <span class="fw-medium">{{ $bonus->member->client->client_name }}</span>
                  <small class="text-muted">{{ $bonus->member->client->client_phone }}</small>
                </div>
              @else
                <span class="text-muted">N/A</span>
              @endif
            </td>
            <td>
              @if($bonus->member && $bonus->member->group)
                <span class="badge bg-label-primary">{{ $bonus->member->group->group_code }}</span>
              @else
                <span class="text-muted">N/A</span>
              @endif
            </td>
            <td>
              <span class="fw-bold text-success">₹{{ number_format($bonus->bonus_amount, 2) }}</span>
            </td>
            <td>{{ $bonus->calculated_percentage }}%</td>
            <td>
              <span class="badge bg-{{ $bonus->status === 'paid' ? 'success' : 'warning' }}">
                {{ ucfirst($bonus->status) }}
              </span>
            </td>
            <td>
              {{ $bonus->paid_date ? $bonus->paid_date->format('d M Y') : '—' }}
            </td>
            <td>
              @if($bonus->status === 'pending')
                <form action="{{ route('chit.referral-bonuses.pay', $bonus) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-xs btn-success"><i class="ri-check-line me-1"></i> Mark as Paid</button>
                </form>
              @else
                <span class="text-muted"><i class="ri-checkbox-circle-fill text-success me-1"></i> Settled</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="ri-information-line ri-24px mb-2 d-block"></i> No referral bonuses found.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if($bonuses->hasPages())
    <div class="card-footer border-top">
      {{ $bonuses->links() }}
    </div>
  @endif
</div>

<!-- Add Referral Bonus Modal -->
<div class="modal fade" id="addReferralBonusModal" tabindex="-1" aria-labelledby="addReferralBonusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addReferralBonusModalLabel">
          <i class="ri-add-line me-1 text-primary"></i> Add Referral Bonus Manually
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="{{ route('chit.referral-bonuses.store') }}" id="manualReferralForm">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            {{-- Scheme --}}
            <div class="col-12">
              <label class="form-label fw-semibold">Chit Scheme <span class="text-danger">*</span></label>
              <select id="modalSchemeSelect" class="form-select" required>
                <option value="">Select Scheme…</option>
              </select>
            </div>

            {{-- Group --}}
            <div class="col-12">
              <label class="form-label fw-semibold">Chit Group <span class="text-danger">*</span></label>
              <select id="modalGroupSelect" class="form-select" required disabled>
                <option value="">Select Group…</option>
              </select>
            </div>

            {{-- Member --}}
            <div class="col-12">
              <label class="form-label fw-semibold">Group Member <span class="text-danger">*</span></label>
              <select name="group_member_id" id="modalMemberSelect" class="form-select" required disabled>
                <option value="">Select Member…</option>
              </select>
            </div>

            {{-- Referrer --}}
            <div class="col-12">
              <label class="form-label fw-semibold">Referrer <span class="text-danger">*</span></label>
              <select name="referrer_id" class="form-select" required>
                <option value="">Select Referrer…</option>
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

            {{-- Percentage --}}
            <div class="col-md-6">
              <label class="form-label fw-semibold">Percentage (%) <span class="text-danger">*</span></label>
              <input type="number" name="calculated_percentage" id="modalPercentageInput" class="form-control" step="0.1" min="0" max="100" required>
            </div>

            {{-- Bonus Amount --}}
            <div class="col-md-6">
              <label class="form-label fw-semibold">Bonus Amount (₹) <span class="text-danger">*</span></label>
              <input type="number" name="bonus_amount" id="modalBonusAmountInput" class="form-control" step="0.01" min="0" required>
            </div>

            {{-- Status --}}
            <div class="col-12">
              <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let optionsData = { schemes: [], groups: [], global_percent: 10, calculation_base: 'group_commission' };

    const modal = document.getElementById('addReferralBonusModal');
    const schemeSelect = document.getElementById('modalSchemeSelect');
    const groupSelect = document.getElementById('modalGroupSelect');
    const memberSelect = document.getElementById('modalMemberSelect');
    const pctInput = document.getElementById('modalPercentageInput');
    const amtInput = document.getElementById('modalBonusAmountInput');

    if (modal) {
        // Fetch dynamic options
        fetch("{{ route('chit.referral-bonuses.options') }}")
            .then(res => res.json())
            .then(data => {
                optionsData = data;
                
                // Populate Schemes
                schemeSelect.innerHTML = '<option value="">Select Scheme…</option>';
                optionsData.schemes.forEach(s => {
                    const option = document.createElement('option');
                    option.value = s.id;
                    option.textContent = `${s.name} (₹${parseFloat(s.chit_value).toLocaleString('en-IN')})`;
                    schemeSelect.appendChild(option);
                });
            });

        // Filter groups based on scheme selection
        schemeSelect.addEventListener('change', function() {
            const schemeId = this.value;
            groupSelect.innerHTML = '<option value="">Select Group…</option>';
            groupSelect.disabled = !schemeId;
            
            memberSelect.innerHTML = '<option value="">Select Member…</option>';
            memberSelect.disabled = true;

            if (schemeId) {
                const filteredGroups = optionsData.groups.filter(g => g.scheme_id == schemeId);
                filteredGroups.forEach(g => {
                    const option = document.createElement('option');
                    option.value = g.id;
                    option.textContent = `${g.group_code} (${g.members.length} members)`;
                    groupSelect.appendChild(option);
                });
            }
            updateCalculation();
        });

        // Filter members based on group selection
        groupSelect.addEventListener('change', function() {
            const groupId = this.value;
            memberSelect.innerHTML = '<option value="">Select Member…</option>';
            memberSelect.disabled = !groupId;

            if (groupId) {
                const group = optionsData.groups.find(g => g.id == groupId);
                if (group && group.members) {
                    group.members.forEach(m => {
                        const option = document.createElement('option');
                        option.value = m.id;
                        option.textContent = `Member #${m.member_number}: ${m.client ? m.client.client_name : 'NA'}`;
                        memberSelect.appendChild(option);
                    });
                }

                // Auto-fill percentage: check group, then scheme, then global
                let pct = optionsData.global_percent;
                const scheme = optionsData.schemes.find(s => s.id == group.scheme_id);
                if (group.referral_commission_pct !== null && group.referral_commission_pct !== undefined) {
                    pct = parseFloat(group.referral_commission_pct);
                } else if (scheme && scheme.referral_commission_pct !== null && scheme.referral_commission_pct !== undefined) {
                    pct = parseFloat(scheme.referral_commission_pct);
                }
                pctInput.value = pct;
            }
            updateCalculation();
        });

        pctInput.addEventListener('input', updateCalculation);

        function updateCalculation() {
            const groupId = groupSelect.value;
            const pct = parseFloat(pctInput.value) || 0;

            if (!groupId) {
                amtInput.value = '';
                return;
            }

            const group = optionsData.groups.find(g => g.id == groupId);
            if (!group) {
                amtInput.value = '';
                return;
            }

            const chitValue = parseFloat(group.chit_value) || 0;
            // Always calculate referral bonus percentage directly from the scheme amount (chit_value)
            const baseAmount = chitValue;

            const bonus = (baseAmount * pct) / 100;
            amtInput.value = bonus.toFixed(2);
        }
    }
});
</script>
@endsection
