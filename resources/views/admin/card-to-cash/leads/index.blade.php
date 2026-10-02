@extends('layouts/layoutMaster')

@section('title', 'Card to Cash Leads')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Card to Cash Leads</h4>
    <p class="text-muted mb-0">View, search, filter, and track all incoming credit card customer leads.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddLead">
      <i class="ri-add-line me-1"></i> Add Lead
    </button>
    <a href="{{ route('card-cash.processing.index') }}" class="btn btn-label-warning">
      <i class="ri-loader-4-line me-1"></i> Processing Queue
    </a>
  </div>
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

<!-- Filters Card -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.leads.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Lead #, Customer, Phone, Card..." value="{{ request('search') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Transaction Type</label>
        <select name="transaction_type" class="form-select">
          <option value="">All Types</option>
          <option value="bill_payment" {{ request('transaction_type') === 'bill_payment' ? 'selected' : '' }}>Bill Payment</option>
          <option value="swipe" {{ request('transaction_type') === 'swipe' ? 'selected' : '' }}>Swipe</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Status</label>
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
          <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
          <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
          <option value="payment_processing" {{ request('status') === 'payment_processing' ? 'selected' : '' }}>Payment Processing</option>
          <option value="payment_success" {{ request('status') === 'payment_success' ? 'selected' : '' }}>Payment Success</option>
          <option value="return_pending" {{ request('status') === 'return_pending' ? 'selected' : '' }}>Return Pending</option>
          <option value="return_processed" {{ request('status') === 'return_processed' ? 'selected' : '' }}>Return Processed</option>
          <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
          <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
          <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">From Date</label>
        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">To Date</label>
        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
      </div>
      <div class="col-md-1 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100" title="Apply Filter">
          <i class="ri-filter-3-line"></i>
        </button>
        <a href="{{ route('card-cash.leads.index') }}" class="btn btn-outline-secondary" title="Reset Filter">
          <i class="ri-refresh-line"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Leads Card -->
<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="card-title mb-0">All Leads ({{ $leads->total() }})</h5>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="btn-group btn-group-sm" role="group" aria-label="Leads view mode">
        <button type="button" class="btn btn-outline-primary" data-leads-view="list">
          <i class="ri-list-check-2 me-1"></i> List
        </button>
        <button type="button" class="btn btn-outline-primary" data-leads-view="card">
          <i class="ri-layout-grid-line me-1"></i> Cards
        </button>
      </div>
      <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddLead">
        <i class="ri-add-line me-1"></i> Add Lead
      </button>
    </div>
  </div>

  <!-- Card (tile) view -->
  <div id="leadsCardView" class="card-body d-none">
    @if ($leads->count())
      <div class="row g-4">
        @foreach ($leads as $lead)
          @include('admin.card-to-cash.leads.partials.lead-card', ['lead' => $lead])
        @endforeach
      </div>
    @else
      <div class="text-center py-5 text-muted">
        <div class="avatar avatar-lg mb-2 mx-auto">
          <span class="avatar-initial rounded-circle bg-label-secondary"><i class="ri-file-search-line fs-3"></i></span>
        </div>
        <p class="mb-0">No Card to Cash leads found.</p>
        <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalAddLead">
          <i class="ri-add-line me-1"></i> Create Lead
        </button>
      </div>
    @endif
  </div>

  <!-- List (table) view -->
  <div id="leadsListView" class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Lead Date</th>
          <th>Customer</th>
          <th>Phone</th>
          <th>Card & Bank</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Assigned Staff</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse ($leads as $lead)
          <tr>
            <td>
              <a href="javascript:void(0);" onclick="openLeadModal({{ $lead->id }})" class="fw-bold text-primary">
                {{ $lead->lead_number }}
              </a>
            </td>
            <td>
              <span class="text-muted">{{ $lead->lead_date ? $lead->lead_date->format('d M Y, h:i A') : '-' }}</span>
            </td>
            <td>
              <span class="fw-medium text-heading">{{ optional($lead->customer)->customer_name ?? 'N/A' }}</span>
              <br><small class="text-muted">{{ optional($lead->customer)->customer_number ?? '' }}</small>
            </td>
            <td>
              <span class="font-monospace">{{ $lead->phone_number }}</span>
            </td>
            <td>
              <span class="fw-medium">{{ $lead->card_name }}</span>
              <br><small class="text-muted">{{ $lead->csr_bank_name }}</small>
              @if ($lead->masked_card_number)
                <br><span class="font-monospace small text-primary">{{ $lead->masked_card_number }}</span>
              @endif
              @if ($lead->card_holder_phone)
                <br><small class="text-muted" title="Cardholder Mobile"><i class="ri-phone-line me-1"></i>Holder: <span class="font-monospace">{{ $lead->card_holder_phone }}</span></small>
              @endif
            </td>
            <td>
              @if ($lead->transaction_type === 'bill_payment')
                <span class="badge bg-label-primary"><i class="ri-bank-card-line me-1"></i> Bill Payment</span>
              @else
                <span class="badge bg-label-info"><i class="ri-swap-box-line me-1"></i> Swipe</span>
              @endif
            </td>
            <td>
              <span class="fw-bold">₹{{ number_format($lead->requested_amount, 2) }}</span>
            </td>
            <td>
              <span class="text-muted">{{ $lead->due_date ? $lead->due_date->format('d M Y') : '—' }}</span>
            </td>
            <td>
              <span class="badge {{ $lead->status_badge }}">{{ $lead->status_label }}</span>
            </td>
            <td>
              <small class="text-muted">{{ optional($lead->assignedStaff)->name ?? 'Unassigned' }}</small>
            </td>
            <td class="text-end">
              @include('admin.card-to-cash.leads.partials.lead-actions', ['lead' => $lead])
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="11" class="text-center py-5 text-muted">
              <div class="avatar avatar-lg mb-2 mx-auto">
                <span class="avatar-initial rounded-circle bg-label-secondary"><i class="ri-file-search-line fs-3"></i></span>
              </div>
              <p class="mb-0">No Card to Cash leads found.</p>
              <button type="button" class="btn btn-sm btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalAddLead">
                <i class="ri-add-line me-1"></i> Create Lead
              </button>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($leads->hasPages())
    <div class="card-footer py-3">
      {{ $leads->links() }}
    </div>
  @endif
</div>

<!-- ============================================== -->
<!-- 1. ADD LEAD MODAL                              -->
<!-- ============================================== -->
<div class="modal fade" id="modalAddLead" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('card-cash.leads.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="ri-add-circle-line me-1 text-primary"></i> Initiate New Card to Cash Lead</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-4">
            <!-- Left Side: Customer & Card Details -->
            <div class="col-lg-8">
              <!-- Customer Details -->
              <div class="p-3 bg-light rounded-3 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="ri-user-line me-1 text-primary"></i> 1. Customer Information</h6>
                
                <div class="mb-3">
                  <label class="form-label fw-medium">Select Existing Customer (Optional)</label>
                  <select id="modalCustomerSelect" name="credit_card_customer_id" class="form-select">
                    <option value="">-- New Customer (Enter details below) --</option>
                    @foreach ($customers as $cust)
                      <option value="{{ $cust->id }}" 
                        data-name="{{ $cust->customer_name }}"
                        data-phone="{{ $cust->phone_number }}"
                        data-email="{{ $cust->email }}"
                        data-address="{{ $cust->address }}"
                        {{ (old('credit_card_customer_id', ($selectedCustomer ?? null)?->id) == $cust->id) ? 'selected' : '' }}>
                        {{ $cust->customer_name }} ({{ $cust->phone_number }}) - [{{ $cust->customer_number }}]
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label required fw-medium">Customer Name</label>
                    <input type="text" id="modalCustName" name="customer_name" class="form-control" 
                      placeholder="Customer full name" 
                      value="{{ old('customer_name', ($selectedCustomer ?? null)?->customer_name) }}" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label required fw-medium">Phone Number (10 Digits)</label>
                    <input type="tel" id="modalCustPhone" name="phone_number" class="form-control font-monospace" 
                      placeholder="10-digit mobile number" 
                      maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                      oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)"
                      value="{{ old('phone_number', ($selectedCustomer ?? null)?->phone_number) }}" required>
                    <small class="text-muted">Only numbers allowed, exactly 10 digits.</small>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-medium">Email Address (Optional)</label>
                    <input type="email" id="modalCustEmail" name="email" class="form-control" 
                      placeholder="customer@example.com" 
                      value="{{ old('email', ($selectedCustomer ?? null)?->email) }}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-medium">Address (Optional)</label>
                    <input type="text" id="modalCustAddress" name="address" class="form-control" 
                      placeholder="City / Location" 
                      value="{{ old('address', ($selectedCustomer ?? null)?->address) }}">
                  </div>
                </div>
              </div>

              <!-- Credit Card Details -->
              <div class="p-3 bg-light rounded-3 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="ri-bank-card-line me-1 text-info"></i> 2. Credit Card Details (Multiple Cards Supported)</h6>
                
                <div class="mb-3" id="modalSavedCardContainer" style="{{ ($selectedCustomer && $selectedCustomer->cards->count() > 0) ? '' : 'display: none;' }}">
                  <label class="form-label fw-medium">Select Saved Card for Customer</label>
                  <select name="customer_card_id" id="modalCustomerCardSelect" class="form-select">
                    <option value="">-- Enter New Card Details Below --</option>
                    @if ($selectedCustomer)
                      @foreach ($selectedCustomer->cards as $card)
                        <option value="{{ $card->id }}" 
                          data-name="{{ $card->card_name }}"
                          data-bank="{{ $card->csr_bank_name }}"
                          data-number="{{ $card->last_four }}"
                          data-holder-phone="{{ $card->card_holder_phone }}">
                          {{ $card->card_name }} ({{ $card->masked_card_number }}) - {{ $card->csr_bank_name }}{{ $card->card_holder_phone ? ' (Holder: ' . $card->card_holder_phone . ')' : '' }}
                        </option>
                      @endforeach
                    @endif
                  </select>
                </div>

                <div class="row g-3">
                  <div class="col-md-3">
                    <label class="form-label required fw-medium">Card Name</label>
                    <input type="text" id="modalCardName" name="card_name" class="form-control" 
                      placeholder="e.g. HDFC Regalia Gold" 
                      value="{{ old('card_name') }}" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label required fw-medium">CSR Bank Name</label>
                    <input type="text" id="modalCardBank" name="csr_bank_name" class="form-control" 
                      placeholder="e.g. HDFC Bank, SBI" 
                      value="{{ old('csr_bank_name') }}" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label required fw-medium">Last 4 Digits of Card</label>
                    <input type="text" id="modalCardNumber" name="card_number" class="form-control font-monospace" 
                      placeholder="1234" 
                      maxlength="19" pattern="[0-9]{4}" inputmode="numeric"
                      oninput="this.value = this.value.replace(/\D/g, '').slice(-4)"
                      value="{{ old('card_number') }}" required autocomplete="off">
                    <small class="text-muted">Enter the last 4 digits only.</small>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label fw-medium">Cardholder Mobile <span class="badge bg-label-secondary ms-1 fw-normal">Optional</span></label>
                    <input type="tel" id="modalCardHolderPhone" name="card_holder_phone" class="form-control font-monospace" 
                      placeholder="10-digit mobile number" 
                      maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                      oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)"
                      value="{{ old('card_holder_phone') }}">
                    <small class="text-muted">OTP / card mobile if different.</small>
                  </div>
                </div>
              </div>

              <!-- Transaction Type & Amount -->
              <div class="p-3 bg-light rounded-3">
                <h6 class="fw-bold text-dark mb-3"><i class="ri-exchange-dollar-line me-1 text-success"></i> 3. Transaction Details</h6>
                
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label required fw-medium">Transaction Type</label>
                    <div class="d-flex gap-4 mt-2">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="transaction_type" id="modalTypeBillPayment" value="bill_payment" {{ old('transaction_type', 'bill_payment') === 'bill_payment' ? 'checked' : '' }} required>
                        <label class="form-check-label fw-medium" for="modalTypeBillPayment">
                          <i class="ri-bank-card-line text-primary me-1"></i> Bill Payment
                        </label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="transaction_type" id="modalTypeSwipe" value="swipe" {{ old('transaction_type') === 'swipe' ? 'checked' : '' }} required>
                        <label class="form-check-label fw-medium" for="modalTypeSwipe">
                          <i class="ri-swap-box-line text-info me-1"></i> Card Swipe
                        </label>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label required fw-medium">Requested Amount (₹)</label>
                    <div class="input-group">
                      <span class="input-group-text">₹</span>
                      <input type="number" step="any" min="1" name="requested_amount" class="form-control fw-bold fs-5" 
                        placeholder="50000" 
                        value="{{ old('requested_amount') }}" onwheel="this.blur()" required>
                    </div>
                    <small class="text-muted">Enter exact amount requested.</small>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-medium">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                    <small class="text-muted">Card bill / settlement due date.</small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Side: Staff Assignment & Remarks -->
            <div class="col-lg-4">
              <div class="p-3 bg-light rounded-3 h-100 d-flex flex-column justify-content-between">
                <div>
                  <h6 class="fw-bold text-dark mb-3"><i class="ri-shield-user-line me-1 text-secondary"></i> Assignment & Remarks</h6>

                  <div class="mb-3">
                    <label class="form-label fw-medium">Lead Date & Time</label>
                    <input type="text" class="form-control bg-white" value="{{ now()->format('d M Y, h:i A') }}" readonly>
                    <small class="text-muted">System generated current timestamp.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-medium">Assign Staff</label>
                    <select name="assigned_user_id" class="form-select">
                      <option value="{{ auth()->id() }}">Assign to Me ({{ auth()->user()->name }})</option>
                      @foreach ($staffUsers as $staff)
                        @if ($staff->id !== auth()->id())
                          <option value="{{ $staff->id }}" {{ old('assigned_user_id') == $staff->id ? 'selected' : '' }}>
                            {{ $staff->name }}
                          </option>
                        @endif
                      @endforeach
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-medium">Internal Remarks</label>
                    <textarea name="remarks" class="form-control" rows="4" placeholder="Optional notes for processing staff...">{{ old('remarks') }}</textarea>
                  </div>
                </div>

                <div class="pt-3 border-top mt-4">
                  <button type="submit" class="btn btn-primary btn-lg w-100 mb-2">
                    <i class="ri-check-line me-1"></i> Create Lead & Process
                  </button>
                  <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- 2. VIEW LEAD DETAILS MODAL                     -->
<!-- ============================================== -->
<div class="modal fade" id="modalViewLead" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="viewLeadNumberTitle">Lead Details</h5>
          <small class="text-muted" id="viewLeadDateSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge" id="viewLeadStatusBadge"></span>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body" id="viewLeadModalBody">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <a href="#" id="viewLeadProcessBtn" class="btn btn-primary">
          <i class="ri-settings-4-line me-1"></i> Open in Processing Hub &rarr;
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- 3. EDIT LEAD MODAL                             -->
<!-- ============================================== -->
<div class="modal fade" id="modalEditLead" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editLeadForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="ri-edit-circle-line me-1 text-primary"></i> Edit Lead <span id="editLeadNumberLabel"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required fw-medium">Requested Amount (₹)</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" step="any" min="1" name="requested_amount" id="editLeadAmount" 
                class="form-control fw-bold fs-5" onwheel="this.blur()" required>
            </div>
            <small class="text-muted">Enter exact amount requested.</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Due Date</label>
            <input type="date" name="due_date" id="editLeadDueDate" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label required fw-medium">Credit Card Name</label>
            <input type="text" name="card_name" id="editLeadCardName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label required fw-medium">CSR Bank Name</label>
            <input type="text" name="csr_bank_name" id="editLeadBankName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label required fw-medium">Last 4 Digits of Card</label>
            <input type="text" name="card_number" id="editLeadCardNumber" class="form-control font-monospace"
              placeholder="1234" maxlength="19" pattern="[0-9]{4}" inputmode="numeric"
              oninput="this.value = this.value.replace(/\D/g, '').slice(-4)" required autocomplete="off">
            <small class="text-muted">Enter the last 4 digits only.</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Cardholder Mobile <span class="badge bg-label-secondary ms-1 fw-normal">Optional</span></label>
            <input type="tel" name="card_holder_phone" id="editLeadCardHolderPhone" class="form-control font-monospace" 
              placeholder="10-digit mobile number" maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
              oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)">
            <small class="text-muted">Mobile registered with this card for OTP / verification.</small>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Internal Remarks</label>
            <textarea name="remarks" id="editLeadRemarks" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="ri-check-line me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
// 0. List / Card view toggle. The chosen mode is remembered per browser, and
// ?view=card|list in the URL wins so links can point straight at a mode.
document.addEventListener('DOMContentLoaded', function() {
  const STORAGE_KEY = 'cardCashLeadsViewMode';
  const listView = document.getElementById('leadsListView');
  const cardView = document.getElementById('leadsCardView');
  const toggles = document.querySelectorAll('[data-leads-view]');

  if (!listView || !cardView) return;

  function applyView(mode) {
    const showCards = mode === 'card';
    cardView.classList.toggle('d-none', !showCards);
    listView.classList.toggle('d-none', showCards);
    toggles.forEach(function (btn) {
      btn.classList.toggle('active', btn.dataset.leadsView === (showCards ? 'card' : 'list'));
    });
  }

  let stored = null;
  try {
    stored = localStorage.getItem(STORAGE_KEY);
  } catch (e) {
    // Private browsing / storage disabled - fall back to the default view.
  }

  const requested = new URLSearchParams(window.location.search).get('view') || stored;
  applyView(requested === 'card' ? 'card' : 'list');

  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const mode = btn.dataset.leadsView;
      try {
        localStorage.setItem(STORAGE_KEY, mode);
      } catch (e) {
        // Ignore - the toggle still works for this page view.
      }
      applyView(mode);
    });
  });
});

document.addEventListener('DOMContentLoaded', function() {
  // 1. Add Lead Modal Script
  const modalCustSelect = document.getElementById('modalCustomerSelect');
  const modalCustName = document.getElementById('modalCustName');
  const modalCustPhone = document.getElementById('modalCustPhone');
  const modalCustEmail = document.getElementById('modalCustEmail');
  const modalCustAddress = document.getElementById('modalCustAddress');
  const modalSavedCardContainer = document.getElementById('modalSavedCardContainer');
  const modalCustomerCardSelect = document.getElementById('modalCustomerCardSelect');
  const modalCardName = document.getElementById('modalCardName');
  const modalCardBank = document.getElementById('modalCardBank');
  const modalCardNumber = document.getElementById('modalCardNumber');
  const modalCardHolderPhone = document.getElementById('modalCardHolderPhone');

  function loadModalCards(customerId) {
    if (!customerId) {
      if (modalSavedCardContainer) modalSavedCardContainer.style.display = 'none';
      if (modalCustomerCardSelect) modalCustomerCardSelect.innerHTML = '<option value="">-- Enter New Card Details Below --</option>';
      return;
    }

    fetch('{{ url("card-to-cash/leads/customer-cards") }}/' + customerId)
      .then(res => res.json())
      .then(data => {
        if (data.status && data.cards && data.cards.length > 0) {
          let options = '<option value="">-- Enter New Card Details Below --</option>';
          data.cards.forEach(card => {
            const holderSuffix = card.card_holder_phone ? ` (Holder: ${card.card_holder_phone})` : '';
            options += `<option value="${card.id}" 
              data-name="${card.card_name}" 
              data-bank="${card.csr_bank_name}" 
              data-number="${card.card_number || ''}"
              data-holder-phone="${card.card_holder_phone || ''}">
              ${card.card_name} (${card.masked_card_number}) - ${card.csr_bank_name}${holderSuffix}
            </option>`;
          });
          if (modalCustomerCardSelect) {
            modalCustomerCardSelect.innerHTML = options;
            modalSavedCardContainer.style.display = 'block';
          }
        } else {
          if (modalCustomerCardSelect) modalCustomerCardSelect.innerHTML = '<option value="">-- Enter New Card Details Below --</option>';
          if (modalSavedCardContainer) modalSavedCardContainer.style.display = 'none';
        }
      })
      .catch(err => console.error('Error fetching cards:', err));
  }

  if (modalCustSelect) {
    modalCustSelect.addEventListener('change', function() {
      const selected = this.options[this.selectedIndex];
      if (this.value) {
        if (modalCustName) modalCustName.value = selected.dataset.name || '';
        if (modalCustPhone) modalCustPhone.value = selected.dataset.phone || '';
        if (modalCustEmail) modalCustEmail.value = selected.dataset.email || '';
        if (modalCustAddress) modalCustAddress.value = selected.dataset.address || '';
        loadModalCards(this.value);
      } else {
        if (modalCustName) modalCustName.value = '';
        if (modalCustPhone) modalCustPhone.value = '';
        if (modalCustEmail) modalCustEmail.value = '';
        if (modalCustAddress) modalCustAddress.value = '';
        loadModalCards(null);
      }
    });
  }

  if (modalCustomerCardSelect) {
    modalCustomerCardSelect.addEventListener('change', function() {
      const selected = this.options[this.selectedIndex];
      if (this.value) {
        if (modalCardName) modalCardName.value = selected.dataset.name || '';
        if (modalCardBank) modalCardBank.value = selected.dataset.bank || '';
        if (modalCardNumber) modalCardNumber.value = (selected.dataset.number || '').replace(/\D/g, '').slice(-4);
        if (modalCardHolderPhone) modalCardHolderPhone.value = selected.dataset.holderPhone || '';
      } else {
        if (modalCardName) modalCardName.value = '';
        if (modalCardBank) modalCardBank.value = '';
        if (modalCardNumber) modalCardNumber.value = '';
        if (modalCardHolderPhone) modalCardHolderPhone.value = '';
      }
    });
  }

  // 2. Open Add Lead Modal if requested via URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('open_modal') || urlParams.has('customer_id')) {
    const addModal = new bootstrap.Modal(document.getElementById('modalAddLead'));
    addModal.show();
    if (urlParams.has('customer_id') && modalCustSelect) {
      modalCustSelect.value = urlParams.get('customer_id');
      modalCustSelect.dispatchEvent(new Event('change'));
    }
  }

  if (urlParams.has('view_lead')) {
    openLeadModal(urlParams.get('view_lead'));
  }
});

// 3. View Lead Details Modal Loader
function openLeadModal(leadId) {
  const modalEl = document.getElementById('modalViewLead');
  const viewModal = new bootstrap.Modal(modalEl);
  viewModal.show();

  const titleEl = document.getElementById('viewLeadNumberTitle');
  const dateEl = document.getElementById('viewLeadDateSubtitle');
  const badgeEl = document.getElementById('viewLeadStatusBadge');
  const bodyEl = document.getElementById('viewLeadModalBody');
  const processBtn = document.getElementById('viewLeadProcessBtn');

  processBtn.href = '{{ url("card-to-cash/processing") }}/' + leadId;

  fetch('{{ url("card-to-cash/leads") }}/' + leadId, {
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
  })
  .then(res => res.json())
  .then(res => {
    if (res.status && res.lead) {
      const lead = res.lead;
      const cust = res.customer;
      const card = res.customer_card;
      const bill = res.bill_payment;
      const swipe = res.swipe_transaction;
      const ret = res.return_settlement;

      titleEl.textContent = lead.lead_number;
      dateEl.textContent = 'Created on ' + (lead.lead_date ? new Date(lead.lead_date).toLocaleString() : '-');
      badgeEl.className = 'badge bg-label-' + (lead.status === 'completed' ? 'success' : (lead.status === 'rejected' ? 'danger' : 'primary'));
      badgeEl.textContent = lead.status.toUpperCase();

      let html = `
        <div class="row g-4">
          <!-- Customer & Card Info -->
          <div class="col-md-6">
            <div class="p-3 bg-light rounded-3 h-100">
              <h6 class="fw-bold mb-2 text-dark"><i class="ri-user-line me-1 text-primary"></i> Customer Details</h6>
              <p class="mb-1 fw-bold text-heading fs-6">${cust ? cust.customer_name : 'N/A'}</p>
              <p class="mb-1 font-monospace small"><i class="ri-phone-line me-1 text-muted"></i>${lead.phone_number}</p>
              <p class="mb-0 text-muted small"><i class="ri-mail-line me-1"></i>${cust && cust.email ? cust.email : 'No email'}</p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 bg-light rounded-3 h-100">
              <h6 class="fw-bold mb-2 text-dark"><i class="ri-bank-card-line me-1 text-info"></i> Credit Card Details</h6>
              <p class="mb-1 fw-bold text-primary">${lead.card_name}</p>
              <p class="mb-1 font-monospace fw-bold">${lead.card_number ? ('•••• •••• •••• ' + lead.card_number.slice(-4)) : 'N/A'}</p>
              <p class="mb-0 text-muted small"><i class="ri-building-line me-1"></i>Bank: ${lead.csr_bank_name}</p>
              ${lead.card_holder_phone ? `<p class="mb-0 text-muted small mt-1"><i class="ri-phone-line me-1"></i>Cardholder Mobile: <span class="font-monospace fw-bold text-dark">${lead.card_holder_phone}</span></p>` : ''}
            </div>
          </div>

          <!-- Transaction Summary -->
          <div class="col-12">
            <div class="row g-3">
              <div class="col-sm-4">
                <div class="p-3 border rounded text-center">
                  <span class="text-muted small d-block">Transaction Type</span>
                  <span class="badge bg-label-primary text-uppercase mt-1">${lead.transaction_type}</span>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="p-3 border rounded text-center">
                  <span class="text-muted small d-block">Requested Amount</span>
                  <h5 class="fw-bold text-dark mb-0 mt-1">₹${parseFloat(lead.requested_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</h5>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="p-3 border rounded text-center">
                  <span class="text-muted small d-block">Assigned Staff</span>
                  <span class="fw-medium text-heading d-block mt-1">${res.assigned_staff ? res.assigned_staff.name : 'Unassigned'}</span>
                </div>
              </div>
            </div>
          </div>`;

      if (ret) {
        const settle = res.settlement_display || {};
        html += `
          <div class="col-12">
            <div class="p-3 bg-label-success bg-opacity-10 border border-success rounded-3">
              <h6 class="fw-bold text-success mb-3"><i class="ri-check-double-line me-1"></i> Settlement Details</h6>
              <div class="row g-3">
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Name</span><p class="fw-semibold mb-0">${settle.name || (cust ? cust.customer_name : 'N/A')}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Bank Name</span><p class="fw-semibold mb-0">${settle.bank_name || lead.csr_bank_name || '—'}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Card Number</span><p class="fw-semibold font-monospace mb-0">${settle.card_number || lead.masked_card_number || '—'}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Due Date</span><p class="fw-semibold mb-0">${settle.due_date || '—'}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Amount</span><p class="fw-bold text-success mb-0">${settle.amount || ('₹' + parseFloat(lead.requested_amount).toLocaleString('en-IN', {minimumFractionDigits: 2}))}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Settlement Details</span><p class="fw-semibold text-primary mb-0">${settle.settlement_details || ret.settlement_summary || '—'}</p></div>
              </div>
            </div>
          </div>`;
      } else {
        const settle = res.settlement_display || {};
        html += `
          <div class="col-12">
            <div class="p-3 border rounded-3">
              <h6 class="fw-bold text-dark mb-3"><i class="ri-file-list-3-line me-1"></i> Settlement Details</h6>
              <div class="row g-3">
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Name</span><p class="fw-semibold mb-0">${settle.name || (cust ? cust.customer_name : 'N/A')}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Bank Name</span><p class="fw-semibold mb-0">${settle.bank_name || lead.csr_bank_name || '—'}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Card Number</span><p class="fw-semibold font-monospace mb-0">${settle.card_number || lead.masked_card_number || '—'}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Due Date</span><p class="fw-semibold mb-0">${settle.due_date || '—'}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Amount</span><p class="fw-bold text-success mb-0">${settle.amount || ('₹' + parseFloat(lead.requested_amount).toLocaleString('en-IN', {minimumFractionDigits: 2}))}</p></div>
                <div class="col-sm-6 col-lg-4"><span class="text-muted small d-block">Settlement Details</span><p class="fw-semibold text-muted mb-0">${settle.settlement_details || 'Pending settlement'}</p></div>
              </div>
            </div>
          </div>`;
      }

      html += `</div>`;
      bodyEl.innerHTML = html;
    }
  })
  .catch(err => {
    bodyEl.innerHTML = `<div class="alert alert-danger mb-0">Error loading lead details. Please try again.</div>`;
  });
}

// 4. Edit Lead Details Modal Opener
function openEditLeadModal(leadId, leadNumber, amount, cardName, bankName, remarks, holderPhone, lastFour, dueDate) {
  const form = document.getElementById('editLeadForm');
  if (!form) return;
  form.action = '{{ url("card-to-cash/leads") }}/' + leadId;
  document.getElementById('editLeadNumberLabel').textContent = '#' + leadNumber;
  document.getElementById('editLeadAmount').value = parseFloat(amount) || '';
  document.getElementById('editLeadCardName').value = cardName || '';
  document.getElementById('editLeadBankName').value = bankName || '';
  document.getElementById('editLeadRemarks').value = remarks || '';
  const holderInput = document.getElementById('editLeadCardHolderPhone');
  if (holderInput) holderInput.value = holderPhone || '';
  const lastFourInput = document.getElementById('editLeadCardNumber');
  if (lastFourInput) lastFourInput.value = lastFour || '';
  const dueDateInput = document.getElementById('editLeadDueDate');
  if (dueDateInput) dueDateInput.value = dueDate || '';
  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditLead'));
  modal.show();
}

// 5. Prevent mouse wheel from inadvertently changing values in number inputs
document.addEventListener('wheel', function(e) {
  if (document.activeElement && document.activeElement.type === 'number') {
    document.activeElement.blur();
  }
}, { passive: false });
</script>
@endsection
