@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Credit Card Customers')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Credit Card Customers</h4>
    <p class="text-muted mb-0">Directory of customers utilizing card to cash services, saved credit cards, and payment history.</p>
  </div>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCustomer">
    <i class="ri-user-add-line me-1"></i> Add Customer
  </button>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Please correct the following errors:</strong>
    <ul class="mb-0 mt-2 ps-3">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Filter Card -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.customers.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Name, Phone, ID, Email..." value="{{ request('search') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Status</label>
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
          <option value="blocked" {{ request('status') === 'blocked' ? 'selected' : '' }}>Blocked</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="ri-filter-3-line me-1"></i> Filter</button>
        <a href="{{ route('card-cash.customers.index') }}" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Customers Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Registered Customers ({{ $customers->total() }})</h5>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCustomer">
      <i class="ri-user-add-line me-1"></i> Add Customer
    </button>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Customer ID</th>
          <th>Customer Name</th>
          <th>Phone Number</th>
          <th>Saved Cards</th>
          <th>Total Leads</th>
          <th>Total Volume</th>
          <th>Status</th>
          <th>Registered Date</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($customers as $c)
          <tr>
            <td>
              <span class="badge bg-label-secondary font-monospace">{{ $c->customer_number }}</span>
            </td>
            <td>
              <a href="javascript:void(0);" onclick="openCustomerModal({{ $c->id }})" class="fw-bold text-heading">
                {{ $c->customer_name }}
              </a>
            </td>
            <td>
              <a href="https://wa.me/91{{ preg_replace('/[^0-9]/', '', $c->phone_number) }}" target="_blank" class="text-success fw-medium font-monospace">
                <i class="ri-whatsapp-line me-1"></i>{{ $c->phone_number }}
              </a>
            </td>
            <td>
              <span class="badge bg-label-info">
                <i class="ri-bank-card-line me-1"></i>{{ $c->cards->count() }} {{ \Illuminate\Support\Str::plural('Card', $c->cards->count()) }}
              </span>
            </td>
            <td>
              <span class="badge bg-label-primary">{{ $c->leads_count }} leads</span>
            </td>
            <td>
              @php
                $vol = $c->leads->sum('requested_amount');
              @endphp
              <span class="fw-bold text-heading">₹{{ number_format($vol, 2) }}</span>
            </td>
            <td>
              @php
                $statusBadge = match($c->status) {
                  'active' => 'bg-label-success',
                  'blocked' => 'bg-label-danger',
                  default => 'bg-label-secondary'
                };
              @endphp
              <span class="badge {{ $statusBadge }} text-capitalize">{{ $c->status }}</span>
            </td>
            <td>
              <small class="text-muted">{{ $c->created_at ? $c->created_at->format('d M Y') : '-' }}</small>
            </td>
            <td class="text-end">
              <div class="d-inline-flex align-items-center gap-1">
                <a href="{{ route('card-cash.leads.index', ['customer_id' => $c->id, 'open_modal' => 'add_lead']) }}" class="btn btn-xs btn-label-success me-1" title="Create Lead">
                  <i class="ri-add-line me-1"></i> Lead
                </a>
                <button type="button" onclick="openCustomerModal({{ $c->id }})" class="btn btn-icon btn-text-secondary btn-sm rounded-pill waves-effect" title="View Profile & Cards">
                  <i class="icon-base ri ri-eye-line icon-22px"></i>
                </button>
                <button type="button" onclick="openEditModal({{ json_encode($c) }})" class="btn btn-icon btn-text-secondary btn-sm rounded-pill waves-effect" title="Edit Customer">
                  <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                </button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <div class="avatar avatar-lg mb-2 mx-auto">
                <span class="avatar-initial rounded-circle bg-label-secondary"><i class="ri-user-search-line fs-3"></i></span>
              </div>
              <p class="mb-0">No credit card customers registered yet.</p>
              <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalAddCustomer">
                Add Customer
              </button>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($customers->hasPages())
    <div class="card-footer py-3">
      {{ $customers->links() }}
    </div>
  @endif
</div>

<!-- ============================================== -->
<!-- 1. ADD CUSTOMER MODAL                          -->
<!-- ============================================== -->
<div class="modal fade" id="modalAddCustomer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('card-cash.customers.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="ri-user-add-line me-1 text-primary"></i> Add Credit Card Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label required fw-medium">Customer Full Name</label>
              <input type="text" name="customer_name" class="form-control" placeholder="e.g. Ramesh Kumar" required>
            </div>
            <div class="col-12">
              <label class="form-label required fw-medium">Phone Number (10 Digits)</label>
              <input type="tel" name="phone_number" class="form-control font-monospace" 
                placeholder="10-digit mobile number" 
                maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)" required>
              <small class="text-muted">Only numbers allowed, exactly 10 digits.</small>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Email Address (Optional)</label>
              <input type="email" name="email" class="form-control" placeholder="customer@example.com">
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Address (Optional)</label>
              <textarea name="address" class="form-control" rows="2" placeholder="Customer address..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Remarks</label>
              <input type="text" name="remarks" class="form-control" placeholder="Optional notes...">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-check-line me-1"></i> Create Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- 2. EDIT CUSTOMER MODAL                         -->
<!-- ============================================== -->
<div class="modal fade" id="modalEditCustomer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editCustomerForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="ri-pencil-line me-1 text-warning"></i> Edit Customer Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label required fw-medium">Customer Full Name</label>
              <input type="text" id="editCustomerName" name="customer_name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label required fw-medium">Phone Number (10 Digits)</label>
              <input type="tel" id="editCustomerPhone" name="phone_number" class="form-control font-monospace" 
                maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)" required>
              <small class="text-muted">Only numbers allowed, exactly 10 digits.</small>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Email Address</label>
              <input type="email" id="editCustomerEmail" name="email" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Status</label>
              <select id="editCustomerStatus" name="status" class="form-select" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="blocked">Blocked</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Address</label>
              <textarea id="editCustomerAddress" name="address" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium">Remarks</label>
              <input type="text" id="editCustomerRemarks" name="remarks" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ri-check-line me-1"></i> Update Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- 3. VIEW CUSTOMER & SAVED CARDS MODAL           -->
<!-- ============================================== -->
<div class="modal fade" id="modalViewCustomer" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="viewCustomerNameTitle">Customer Profile</h5>
          <small class="text-muted font-monospace" id="viewCustomerNumberSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge" id="viewCustomerStatusBadge"></span>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body" id="viewCustomerModalBody">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <a href="#" id="viewCustomerNewLeadBtn" class="btn btn-primary">
          <i class="ri-add-line me-1"></i> Create Lead for this Customer &rarr;
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('open_modal') && urlParams.get('open_modal') === 'add_customer') {
    new bootstrap.Modal(document.getElementById('modalAddCustomer')).show();
  }
  if (urlParams.has('view_customer')) {
    openCustomerModal(urlParams.get('view_customer'));
  }
});

function openEditModal(customer) {
  const form = document.getElementById('editCustomerForm');
  form.action = '{{ url("card-to-cash/customers") }}/' + customer.id;

  document.getElementById('editCustomerName').value = customer.customer_name || '';
  document.getElementById('editCustomerPhone').value = customer.phone_number || '';
  document.getElementById('editCustomerEmail').value = customer.email || '';
  document.getElementById('editCustomerAddress').value = customer.address || '';
  document.getElementById('editCustomerStatus').value = customer.status || 'active';
  document.getElementById('editCustomerRemarks').value = customer.remarks || '';

  new bootstrap.Modal(document.getElementById('modalEditCustomer')).show();
}

function openCustomerModal(customerId) {
  const modalEl = document.getElementById('modalViewCustomer');
  const viewModal = new bootstrap.Modal(modalEl);
  viewModal.show();

  const titleEl = document.getElementById('viewCustomerNameTitle');
  const subtitleEl = document.getElementById('viewCustomerNumberSubtitle');
  const badgeEl = document.getElementById('viewCustomerStatusBadge');
  const bodyEl = document.getElementById('viewCustomerModalBody');
  const newLeadBtn = document.getElementById('viewCustomerNewLeadBtn');

  newLeadBtn.href = '{{ route("card-cash.leads.index") }}?customer_id=' + customerId + '&open_modal=add_lead';

  fetch('{{ url("card-to-cash/customers") }}/' + customerId, {
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
  })
  .then(res => res.json())
  .then(res => {
    if (res.status && res.customer) {
      const cust = res.customer;
      const cards = res.cards || [];
      const leads = res.leads || [];

      titleEl.textContent = cust.customer_name;
      subtitleEl.textContent = 'Customer ID: ' + cust.customer_number;
      badgeEl.className = 'badge bg-label-' + (cust.status === 'active' ? 'success' : 'danger');
      badgeEl.textContent = cust.status.toUpperCase();

      let cardsHtml = '';
      if (cards.length === 0) {
        cardsHtml = `<p class="text-muted small mb-0">No credit cards registered yet.</p>`;
      } else {
        cardsHtml = '<div class="row g-3">';
        cards.forEach(card => {
          cardsHtml += `
            <div class="col-md-6">
              <div class="p-3 border rounded bg-white">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="badge bg-label-primary">${card.csr_bank_name}</span>
                  ${card.card_network ? `<span class="badge bg-label-secondary text-uppercase">${card.card_network}</span>` : ''}
                </div>
                <h6 class="mb-1 fw-bold text-heading">${card.card_name}</h6>
                <p class="mb-1 font-monospace fw-bold text-primary">${card.card_number ? ('•••• •••• •••• ' + card.card_number.slice(-4)) : 'N/A'}</p>
                ${card.card_holder_phone ? `<p class="mb-0 text-muted small"><i class="ri-phone-line me-1"></i>Holder: <span class="font-monospace fw-medium">${card.card_holder_phone}</span></p>` : ''}
              </div>
            </div>`;
        });
        cardsHtml += '</div>';
      }

      let html = `
        <div class="row g-4">
          <!-- Profile Card -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded-3 h-100">
              <h6 class="fw-bold mb-3 text-dark"><i class="ri-user-line me-1 text-primary"></i> Contact & Address</h6>
              <p class="mb-2"><strong>Phone:</strong> <a href="tel:${cust.phone_number}" class="font-monospace">${cust.phone_number}</a></p>
              <p class="mb-2"><strong>Email:</strong> ${cust.email || 'Not provided'}</p>
              <p class="mb-2"><strong>Address:</strong> ${cust.address || 'Not provided'}</p>
              <p class="mb-0"><strong>Remarks:</strong> ${cust.remarks || 'None'}</p>
            </div>
          </div>

          <!-- Quick Lifetime Stats -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded-3 h-100">
              <h6 class="fw-bold mb-3 text-dark"><i class="ri-bar-chart-line me-1 text-info"></i> Lifetime Stats</h6>
              <p class="mb-2"><strong>Total Leads:</strong> <span class="badge bg-label-primary">${leads.length} leads</span></p>
              <p class="mb-2"><strong>Total Registered Cards:</strong> <span class="badge bg-label-info">${cards.length} cards</span></p>
              <p class="mb-0"><strong>Member Since:</strong> ${new Date(cust.created_at).toLocaleDateString()}</p>
            </div>
          </div>

          <!-- Saved Credit Cards -->
          <div class="col-12">
            <div class="p-3 bg-light rounded-3">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="ri-bank-card-line me-1 text-primary"></i> Saved Credit Cards (${cards.length})</h6>
                <button type="button" class="btn btn-xs btn-primary" onclick="document.getElementById('addCardInlineForm').style.display = document.getElementById('addCardInlineForm').style.display === 'none' ? 'block' : 'none'">
                  <i class="ri-add-line me-1"></i> Add Card
                </button>
              </div>

              <!-- Inline Add Card Form -->
              <div id="addCardInlineForm" class="p-3 border rounded bg-white mb-3" style="display: none;">
                <form action="{{ url('card-to-cash/customers') }}/${cust.id}/cards" method="POST">
                  <input type="hidden" name="_token" value="{{ csrf_token() }}">
                  <h6 class="fw-bold small mb-2 text-primary">Register New Card for ${cust.customer_name}</h6>
                  <div class="row g-2">
                    <div class="col-md-3">
                      <input type="text" name="card_name" class="form-control form-control-sm" placeholder="Card Name (e.g. HDFC Regalia)" required>
                    </div>
                    <div class="col-md-3">
                      <input type="text" name="csr_bank_name" class="form-control form-control-sm" placeholder="Bank Name (e.g. HDFC Bank)" required>
                    </div>
                    <div class="col-md-3">
                      <input type="text" name="card_number" class="form-control form-control-sm font-monospace" placeholder="Last 4 digits" maxlength="19" pattern="[0-9]{4}" inputmode="numeric" oninput="this.value = this.value.replace(/\\D/g, '').slice(-4)" required autocomplete="off">
                    </div>
                    <div class="col-md-3">
                      <input type="tel" name="card_holder_phone" class="form-control form-control-sm font-monospace" placeholder="Holder Mobile (Optional)" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" oninput="this.value = this.value.replace(/\\D/g, '').slice(0, 10)">
                    </div>
                    <div class="col-12 text-end pt-1">
                      <button type="submit" class="btn btn-xs btn-success"><i class="ri-check-line me-1"></i> Save Card</button>
                    </div>
                  </div>
                </form>
              </div>

              ${cardsHtml}
            </div>
          </div>
        </div>`;

      bodyEl.innerHTML = html;
    }
  })
  .catch(err => {
    bodyEl.innerHTML = `<div class="alert alert-danger mb-0">Error loading customer details. Please try again.</div>`;
  });
}
</script>
@endsection
