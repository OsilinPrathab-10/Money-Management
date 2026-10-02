@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Master Settings')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Card to Cash Masters & Settings</h4>
    <p class="text-muted mb-0">Manage POS withdrawal gateways, companies, bank masters, and bill payment sources.</p>
  </div>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-6" role="alert">
    <i class="ri-checkbox-circle-fill me-2 fs-5"></i>
    <div>{{ session('success') }}</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-6" role="alert">
    <i class="ri-error-warning-fill me-2 fs-5"></i>
    <div>{{ session('error') }}</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show mb-6" role="alert">
    <div class="d-flex align-items-center mb-2">
      <i class="ri-error-warning-fill me-2 fs-5"></i>
      <strong>Please correct the following errors:</strong>
    </div>
    <ul class="mb-0 ps-4">
      @foreach ($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Master Tabs -->
<div class="nav-align-top mb-6">
  <ul class="nav nav-pills gap-2 mb-4" role="tablist">
    <li class="nav-item">
      <button type="button" class="nav-link active px-4 py-2" role="tab" data-bs-toggle="tab" data-bs-target="#tab-gateways">
        <i class="ri-terminal-box-line me-1 fs-5"></i>
        <span>Withdrawal Gateways & POS</span>
        <span class="badge rounded-pill bg-label-primary ms-2">{{ $gateways->count() }}</span>
      </button>
    </li>
    <li class="nav-item">
      <button type="button" class="nav-link px-4 py-2" role="tab" data-bs-toggle="tab" data-bs-target="#tab-companies">
        <i class="ri-building-4-line me-1 fs-5"></i>
        <span>Companies</span>
        <span class="badge rounded-pill bg-label-primary ms-2">{{ $companies->count() }}</span>
      </button>
    </li>
    <li class="nav-item">
      <button type="button" class="nav-link px-4 py-2" role="tab" data-bs-toggle="tab" data-bs-target="#tab-banks">
        <i class="ri-bank-line me-1 fs-5"></i>
        <span>Bank Names</span>
        <span class="badge rounded-pill bg-label-primary ms-2">{{ $banks->count() }}</span>
      </button>
    </li>
    <li class="nav-item">
      <button type="button" class="nav-link px-4 py-2" role="tab" data-bs-toggle="tab" data-bs-target="#tab-sources">
        <i class="ri-secure-payment-line me-1 fs-5"></i>
        <span>Bill Payment Sources</span>
        <span class="badge rounded-pill bg-label-primary ms-2">{{ $paymentSources->count() }}</span>
      </button>
    </li>
  </ul>

  <div class="tab-content p-0 bg-transparent shadow-none">
    <!-- ===================================================================== -->
    <!-- TAB 1: WITHDRAWAL GATEWAYS & POS MACHINES                             -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade show active" id="tab-gateways" role="tabpanel">
      <div class="card border">
        <div class="card-header d-flex justify-content-between align-items-center py-4">
          <div>
            <h5 class="card-title mb-0">Withdrawal Gateways & POS Machines</h5>
            <small class="text-muted">Configured terminals used for Card Swipe withdrawals with company associations and card fee rates.</small>
          </div>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddGateway">
            <i class="ri-add-line me-1"></i> Add Gateway
          </button>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Gateway / Terminal</th>
                <th>Company</th>
                <th class="text-center">Debit %</th>
                <th class="text-center">Credit %</th>
                <th class="text-center">Prepaid %</th>
                <th class="text-center">Business %</th>
                <th>Wallet Linked</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($gateways as $gw)
                <tr>
                  <td>
                    <strong class="text-heading d-block">{{ $gw->gateway_name }}</strong>
                    <span class="font-monospace text-muted small">{{ $gw->gateway_code }}</span>
                  </td>
                  <td>
                    @if ($gw->company)
                      <span class="badge bg-label-info fw-semibold">
                        <i class="ri-building-line me-1"></i>{{ $gw->company->company_name }}
                      </span>
                    @else
                      <span class="text-muted small">None / General</span>
                    @endif
                  </td>
                  <td class="text-center">
                    <span class="badge bg-label-secondary font-monospace">{{ number_format($gw->debit_percentage, 2) }}%</span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-label-primary font-monospace">{{ number_format($gw->credit_percentage, 2) }}%</span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-label-warning font-monospace">{{ number_format($gw->prepaid_percentage, 2) }}%</span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-label-danger font-monospace">{{ number_format($gw->business_percentage, 2) }}%</span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $gw->wallet_supported ? 'success' : 'secondary' }}">
                      {{ $gw->wallet_supported ? 'Yes' : 'No' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $gw->status === 'active' ? 'success' : 'secondary' }}">
                      {{ ucfirst($gw->status) }}
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                      <button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill waves-effect" data-bs-toggle="modal" data-bs-target="#modalEditGateway{{ $gw->id }}" title="Edit Gateway">
                        <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                      </button>
                      <form action="{{ route('card-cash.settings.withdrawal-gateways.destroy', $gw->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete gateway \'{{ $gw->gateway_name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-icon btn-text-danger btn-sm rounded-pill waves-effect" title="Delete Gateway">
                          <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" class="text-center py-5 text-muted">
                    <i class="ri-terminal-box-line fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No withdrawal gateways registered yet. Click "Add Gateway" to set one up.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 2: COMPANIES MASTER CRUD                                          -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade" id="tab-companies" role="tabpanel">
      <div class="card border">
        <div class="card-header d-flex justify-content-between align-items-center py-4">
          <div>
            <h5 class="card-title mb-0">Companies (Gateway Providers / Merchants)</h5>
            <small class="text-muted">Entities or merchant vendors through which POS swipe gateways are procured.</small>
          </div>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCompany">
            <i class="ri-add-line me-1"></i> Add Company
          </button>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Company Name</th>
                <th>Company Code</th>
                <th>Contact Person</th>
                <th>Phone / Email</th>
                <th class="text-center">Gateways Linked</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($companies as $comp)
                <tr>
                  <td>
                    <strong class="text-heading">{{ $comp->company_name }}</strong>
                    @if ($comp->remarks)
                      <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($comp->remarks, 40) }}</small>
                    @endif
                  </td>
                  <td>
                    <span class="font-monospace small text-primary">{{ $comp->company_code ?: '-' }}</span>
                  </td>
                  <td>
                    <span class="text-heading">{{ $comp->contact_person ?: '-' }}</span>
                  </td>
                  <td>
                    <span class="d-block small text-heading">{{ $comp->phone ?: '-' }}</span>
                    <small class="text-muted">{{ $comp->email ?: '' }}</small>
                  </td>
                  <td class="text-center">
                    <span class="badge rounded-pill bg-label-info font-monospace">{{ $comp->gateways_count }}</span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $comp->status === 'active' ? 'success' : 'secondary' }}">
                      {{ ucfirst($comp->status) }}
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                      <button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill waves-effect" data-bs-toggle="modal" data-bs-target="#modalEditCompany{{ $comp->id }}" title="Edit Company">
                        <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                      </button>
                      <form action="{{ route('card-cash.settings.companies.destroy', $comp->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete company \'{{ $comp->company_name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-icon btn-text-danger btn-sm rounded-pill waves-effect" title="Delete Company">
                          <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="ri-building-4-line fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No companies configured. Click "Add Company" to register gateway providers.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 3: BANK NAMES MASTER CRUD                                         -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade" id="tab-banks" role="tabpanel">
      <div class="card border">
        <div class="card-header d-flex justify-content-between align-items-center py-4">
          <div>
            <h5 class="card-title mb-0">Bank Names Master</h5>
            <small class="text-muted">Master list of banks used for Credit Card CSR, Customer Bank identification, and settlement accounts.</small>
          </div>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddBank">
            <i class="ri-add-line me-1"></i> Add Bank
          </button>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Bank Name</th>
                <th>Bank Code</th>
                <th>IFSC / Branch Prefix</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($banks as $bk)
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar avatar-sm bg-label-primary rounded d-flex align-items-center justify-content-center">
                        <i class="ri-bank-line fs-6"></i>
                      </div>
                      <strong class="text-heading">{{ $bk->bank_name }}</strong>
                    </div>
                  </td>
                  <td>
                    <span class="font-monospace small text-primary">{{ $bk->bank_code ?: '-' }}</span>
                  </td>
                  <td>
                    <span class="font-monospace small text-muted">{{ $bk->ifsc_prefix ?: '-' }}</span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $bk->status === 'active' ? 'success' : 'secondary' }}">
                      {{ ucfirst($bk->status) }}
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                      <button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill waves-effect" data-bs-toggle="modal" data-bs-target="#modalEditBank{{ $bk->id }}" title="Edit Bank">
                        <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                      </button>
                      <form action="{{ route('card-cash.settings.banks.destroy', $bk->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete bank \'{{ $bk->bank_name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-icon btn-text-danger btn-sm rounded-pill waves-effect" title="Delete Bank">
                          <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center py-5 text-muted">
                    <i class="ri-bank-line fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No bank names registered. Click "Add Bank" to add recognized banks.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================================================================== -->
    <!-- TAB 4: BILL PAYMENT SOURCES                                           -->
    <!-- ===================================================================== -->
    <div class="tab-pane fade" id="tab-sources" role="tabpanel">
      <div class="card border">
        <div class="card-header d-flex justify-content-between align-items-center py-4">
          <div>
            <h5 class="card-title mb-0">Bill Payment Sources</h5>
            <small class="text-muted">Accounts or gateways used to pay customer credit card bills.</small>
          </div>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddSource">
            <i class="ri-add-line me-1"></i> Add Source
          </button>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Source Name</th>
                <th>Type</th>
                <th>Account / Ref</th>
                <th>Linked Wallet</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($paymentSources as $src)
                <tr>
                  <td><strong class="text-heading">{{ $src->source_name }}</strong></td>
                  <td><span class="badge bg-label-info text-capitalize">{{ $src->source_type }}</span></td>
                  <td><span class="font-monospace small text-muted">{{ $src->account_number_or_reference ?: '-' }}</span></td>
                  <td>
                    <span class="badge bg-label-secondary">
                      {{ optional($src->wallet)->wallet_name ?? 'None' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $src->status === 'active' ? 'success' : 'secondary' }}">
                      {{ ucfirst($src->status) }}
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-1">
                      <button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill waves-effect" data-bs-toggle="modal" data-bs-target="#modalEditSource{{ $src->id }}" title="Edit Source">
                        <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                      </button>
                      <form action="{{ route('card-cash.settings.payment-sources.destroy', $src->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete payment source \'{{ $src->source_name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-icon btn-text-danger btn-sm rounded-pill waves-effect" title="Delete Source">
                          <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-5 text-muted">
                    <i class="ri-secure-payment-line fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No payment sources configured. Click "Add Source" to add one.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODALS SECTION (PLACED OUTSIDE TABLES TO PREVENT BACKDROP TRAPPING)        -->
<!-- ========================================================================= -->

<!-- MODAL: ADD WITHDRAWAL GATEWAY -->
<div class="modal fade" id="modalAddGateway" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form action="{{ route('card-cash.settings.withdrawal-gateways.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add Withdrawal Gateway / POS Machine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Gateway Name <span class="text-danger">*</span></label>
              <input type="text" name="gateway_name" class="form-control" placeholder="e.g. PineLabs POS, Paytm Terminal" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Gateway Code <span class="text-danger">*</span></label>
              <input type="text" name="gateway_code" class="form-control font-monospace text-uppercase" placeholder="e.g. PINELABS_01" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Company Provider</label>
            <select name="company_id" class="form-select">
              <option value="">-- No Company (General) --</option>
              @foreach ($companies as $c)
                <option value="{{ $c->id }}">{{ $c->company_name }} {{ $c->company_code ? "({$c->company_code})" : '' }}</option>
              @endforeach
            </select>
            <div class="form-text small">Select the registered company or vendor providing this POS machine.</div>
          </div>

          <div class="divider my-3">
            <div class="divider-text text-muted small fw-medium">Card Swipe Fee Rates (%)</div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3 col-6">
              <label class="form-label">Debit Card %</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" name="debit_percentage" class="form-control" placeholder="0.00" value="0.00">
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Credit Card %</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" name="credit_percentage" class="form-control" placeholder="0.00" value="0.00">
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Prepaid Card %</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" name="prepaid_percentage" class="form-control" placeholder="0.00" value="0.00">
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label">Business Card %</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" name="business_percentage" class="form-control" placeholder="0.00" value="0.00">
                <span class="input-group-text">%</span>
              </div>
            </div>
          </div>

          <div class="mb-3 form-check form-switch">
            <input type="checkbox" name="wallet_supported" value="1" class="form-check-input" id="new_wallet_supported" checked>
            <label class="form-check-label fw-medium" for="new_wallet_supported">Supports Wallet Balance Deductions</label>
          </div>

          <div class="mb-2">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Terminal ID, merchant ID, or notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Gateway</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODALS: EDIT WITHDRAWAL GATEWAYS -->
@foreach ($gateways as $gw)
  <div class="modal fade" id="modalEditGateway{{ $gw->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form action="{{ route('card-cash.settings.withdrawal-gateways.update', $gw->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="modal-header">
            <h5 class="modal-title">Edit Gateway: {{ $gw->gateway_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label">Gateway Name <span class="text-danger">*</span></label>
                <input type="text" name="gateway_name" class="form-control" value="{{ $gw->gateway_name }}" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Gateway Code</label>
                <input type="text" class="form-control font-monospace text-uppercase" value="{{ $gw->gateway_code }}" readonly disabled>
              </div>
              <div class="col-md-4">
                <label class="form-label">Company Provider</label>
                <select name="company_id" class="form-select">
                  <option value="">-- No Company (General) --</option>
                  @foreach ($companies as $c)
                    <option value="{{ $c->id }}" {{ $gw->company_id == $c->id ? 'selected' : '' }}>
                      {{ $c->company_name }} {{ $c->company_code ? "({$c->company_code})" : '' }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="divider my-3">
              <div class="divider-text text-muted small fw-medium">Card Swipe Fee Rates (%)</div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-3 col-6">
                <label class="form-label">Debit Card %</label>
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" name="debit_percentage" class="form-control" value="{{ $gw->debit_percentage }}">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <label class="form-label">Credit Card %</label>
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" name="credit_percentage" class="form-control" value="{{ $gw->credit_percentage }}">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <label class="form-label">Prepaid Card %</label>
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" name="prepaid_percentage" class="form-control" value="{{ $gw->prepaid_percentage }}">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <label class="form-label">Business Card %</label>
                <div class="input-group">
                  <input type="number" step="0.01" min="0" max="100" name="business_percentage" class="form-control" value="{{ $gw->business_percentage }}">
                  <span class="input-group-text">%</span>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-select" required>
                  <option value="active" {{ $gw->status === 'active' ? 'selected' : '' }}>Active</option>
                  <option value="inactive" {{ $gw->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
              </div>
              <div class="col-md-6 d-flex align-items-center pt-4">
                <div class="form-check form-switch">
                  <input type="checkbox" name="wallet_supported" value="1" class="form-check-input" id="gw_w_{{ $gw->id }}" {{ $gw->wallet_supported ? 'checked' : '' }}>
                  <label class="form-check-label fw-medium" for="gw_w_{{ $gw->id }}">Supports Wallet Deduction</label>
                </div>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Remarks / Machine Details</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $gw->remarks }}</textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endforeach

<!-- MODAL: ADD COMPANY -->
<div class="modal fade" id="modalAddCompany" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('card-cash.settings.companies.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add Company Provider</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Company Name <span class="text-danger">*</span></label>
            <input type="text" name="company_name" class="form-control" placeholder="e.g. Pine Labs Private Limited" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Company Code</label>
            <input type="text" name="company_code" class="form-control font-monospace text-uppercase" placeholder="e.g. PINELABS">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" class="form-control" placeholder="Key contact name">
            </div>
            <div class="col-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" placeholder="e.g. 9876543210">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="vendor@example.com">
          </div>
          <div class="mb-2">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Merchant agreements, vendor notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Company</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODALS: EDIT COMPANIES -->
@foreach ($companies as $comp)
  <div class="modal fade" id="modalEditCompany{{ $comp->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('card-cash.settings.companies.update', $comp->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="modal-header">
            <h5 class="modal-title">Edit Company: {{ $comp->company_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Company Name <span class="text-danger">*</span></label>
              <input type="text" name="company_name" class="form-control" value="{{ $comp->company_name }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Company Code</label>
              <input type="text" name="company_code" class="form-control font-monospace text-uppercase" value="{{ $comp->company_code }}">
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label">Contact Person</label>
                <input type="text" name="contact_person" class="form-control" value="{{ $comp->contact_person }}">
              </div>
              <div class="col-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ $comp->phone }}">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="{{ $comp->email }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Status <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="active" {{ $comp->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $comp->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $comp->remarks }}</textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endforeach

<!-- MODAL: ADD BANK NAME -->
<div class="modal fade" id="modalAddBank" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('card-cash.settings.banks.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add Recognized Bank</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
            <input type="text" name="bank_name" class="form-control" placeholder="e.g. Standard Chartered Bank" required>
          </div>
          <div class="row g-3 mb-2">
            <div class="col-6">
              <label class="form-label">Bank Code / Short Name</label>
              <input type="text" name="bank_code" class="form-control font-monospace text-uppercase" placeholder="e.g. SCB">
            </div>
            <div class="col-6">
              <label class="form-label">IFSC Prefix</label>
              <input type="text" name="ifsc_prefix" class="form-control font-monospace text-uppercase" placeholder="e.g. SCBL">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Bank</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODALS: EDIT BANKS -->
@foreach ($banks as $bk)
  <div class="modal fade" id="modalEditBank{{ $bk->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('card-cash.settings.banks.update', $bk->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="modal-header">
            <h5 class="modal-title">Edit Bank: {{ $bk->bank_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Bank Name <span class="text-danger">*</span></label>
              <input type="text" name="bank_name" class="form-control" value="{{ $bk->bank_name }}" required>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label">Bank Code / Short Name</label>
                <input type="text" name="bank_code" class="form-control font-monospace text-uppercase" value="{{ $bk->bank_code }}">
              </div>
              <div class="col-6">
                <label class="form-label">IFSC Prefix</label>
                <input type="text" name="ifsc_prefix" class="form-control font-monospace text-uppercase" value="{{ $bk->ifsc_prefix }}">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label">Status <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="active" {{ $bk->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $bk->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endforeach

<!-- MODAL: ADD PAYMENT SOURCE -->
<div class="modal fade" id="modalAddSource" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('card-cash.settings.payment-sources.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add Payment Source</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Source Name <span class="text-danger">*</span></label>
            <input type="text" name="source_name" class="form-control" placeholder="e.g. HDFC Current A/C, BillDesk Direct" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Type <span class="text-danger">*</span></label>
            <select name="source_type" class="form-select" required>
              <option value="gateway">Gateway</option>
              <option value="account" selected>Bank Account</option>
              <option value="wallet">Wallet</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Account / Reference Number</label>
            <input type="text" name="account_number_or_reference" class="form-control" placeholder="A/C number or reference identifier">
          </div>
          <div class="mb-3">
            <label class="form-label">Linked Wallet (Optional)</label>
            <select name="wallet_id" class="form-select">
              <option value="">None</option>
              @foreach ($wallets as $w)
                <option value="{{ $w->id }}">{{ $w->wallet_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Notes on this source..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Source</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODALS: EDIT PAYMENT SOURCES -->
@foreach ($paymentSources as $src)
  <div class="modal fade" id="modalEditSource{{ $src->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('card-cash.settings.payment-sources.update', $src->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="modal-header">
            <h5 class="modal-title">Edit Payment Source: {{ $src->source_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Source Name <span class="text-danger">*</span></label>
              <input type="text" name="source_name" class="form-control" value="{{ $src->source_name }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Type <span class="text-danger">*</span></label>
              <select name="source_type" class="form-select" required>
                <option value="gateway" {{ $src->source_type === 'gateway' ? 'selected' : '' }}>Gateway</option>
                <option value="account" {{ $src->source_type === 'account' ? 'selected' : '' }}>Bank Account</option>
                <option value="wallet" {{ $src->source_type === 'wallet' ? 'selected' : '' }}>Wallet</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Account / Reference ID</label>
              <input type="text" name="account_number_or_reference" class="form-control" value="{{ $src->account_number_or_reference }}">
            </div>
            <div class="mb-3">
              <label class="form-label">Linked Wallet (Optional)</label>
              <select name="wallet_id" class="form-select">
                <option value="">None</option>
                @foreach ($wallets as $w)
                  <option value="{{ $w->id }}" {{ $src->wallet_id == $w->id ? 'selected' : '' }}>{{ $w->wallet_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Status <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="active" {{ $src->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $src->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $src->remarks }}</textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endforeach

@endsection
