@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Wallets Management')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Wallets Management</h4>
    <p class="text-muted mb-0">Monitor withdrawal wallet balances (SLP Wallet, Own Wallet), top-ups, and ledger deductions.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddWallet">
      <i class="ri-add-line me-1"></i> Add New Wallet
    </button>
  </div>
</div>

<!-- ============================================== -->
<!-- 1. SYSTEM-WIDE WALLETS KPI SUMMARY             -->
<!-- ============================================== -->
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card bg-primary text-white h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white opacity-75 mb-1">Total Available Balance</h6>
            <h3 class="text-white fw-bold mb-0">₹{{ number_format($totalBalance, 2) }}</h3>
            <small class="text-white opacity-75 mt-1 d-block">Across {{ $wallets->count() }} wallet accounts</small>
          </div>
          <div class="avatar avatar-md bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center">
            <i class="ri-wallet-3-line text-white fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted mb-1">Total Inflow / Top-ups</h6>
            <h3 class="fw-bold mb-0 text-success">+₹{{ number_format($totalCreditsAll, 2) }}</h3>
            <small class="text-muted mt-1 d-block">Lifetime credits received</small>
          </div>
          <div class="avatar avatar-md bg-label-success rounded d-flex align-items-center justify-content-center">
            <i class="ri-arrow-down-circle-line text-success fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted mb-1">Total Outflow / Deductions</h6>
            <h3 class="fw-bold mb-0 text-danger">-₹{{ number_format($totalDebitsAll, 2) }}</h3>
            <small class="text-muted mt-1 d-block">POS swipes & withdrawals</small>
          </div>
          <div class="avatar avatar-md bg-label-danger rounded d-flex align-items-center justify-content-center">
            <i class="ri-arrow-up-circle-line text-danger fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-muted mb-1">Initial Opening Capital</h6>
            <h3 class="fw-bold mb-0 text-heading">₹{{ number_format($totalOpening, 2) }}</h3>
            <small class="text-muted mt-1 d-block">{{ $wallets->where('status', 'active')->count() }} active accounts</small>
          </div>
          <div class="avatar avatar-md bg-label-info rounded d-flex align-items-center justify-content-center">
            <i class="ri-funds-line text-info fs-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- 2. WALLETS ACCOUNTS (LIST-WISE DISPLAY)        -->
<!-- ============================================== -->
<div class="card mb-6">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center pb-3 gap-2">
    <div>
      <h5 class="card-title mb-0 fw-bold">
        <i class="ri-list-check-2 me-1 text-primary"></i> Wallets Accounts Directory
      </h5>
      <small class="text-muted">List-wise overview of withdrawal wallets, capital allocations, balances, and lifetime flow.</small>
    </div>
    <span class="badge bg-label-primary px-3 py-2">{{ $wallets->count() }} Accounts Configured</span>
  </div>

  <div class="table-responsive text-nowrap">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Wallet Account</th>
          <th>Code</th>
          <th>Type</th>
          <th>Opening Balance</th>
          <th>Total Inflow (+)</th>
          <th>Total Outflow (-)</th>
          <th>Current Available Balance</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($wallets as $w)
          <tr class="{{ $selectedWallet && $selectedWallet->id === $w->id ? 'table-active border-start border-4 border-primary' : '' }}">
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm bg-label-primary rounded d-flex align-items-center justify-content-center">
                  <i class="ri-wallet-3-fill text-primary"></i>
                </div>
                <div>
                  <a href="{{ route('card-cash.wallets.index', ['wallet_id' => $w->id]) }}#account-ledger" class="fw-bold text-heading text-decoration-none">
                    {{ $w->wallet_name }}
                  </a>
                  @if ($selectedWallet && $selectedWallet->id === $w->id)
                    <span class="badge bg-primary ms-1 text-white" style="font-size: 0.65rem;">Viewing Ledger</span>
                  @endif
                  @if ($w->remarks)
                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($w->remarks, 30) }}</small>
                  @endif
                </div>
              </div>
            </td>
            <td>
              <span class="badge bg-label-secondary font-monospace">{{ $w->wallet_code }}</span>
            </td>
            <td>
              <span class="badge bg-label-info text-uppercase font-monospace" style="font-size: 0.75rem;">
                {{ $w->wallet_type }}
              </span>
            </td>
            <td>
              <span class="fw-medium text-heading">₹{{ number_format($w->opening_balance, 2) }}</span>
            </td>
            <td>
              <span class="text-success fw-semibold">
                +₹{{ number_format($w->total_credits, 2) }}
              </span>
            </td>
            <td>
              <span class="text-danger fw-semibold">
                -₹{{ number_format($w->total_debits, 2) }}
              </span>
            </td>
            <td>
              <span class="badge bg-label-{{ $w->current_balance < 10000 ? 'warning' : 'primary' }} fs-6 fw-bold px-3 py-2">
                ₹{{ number_format($w->current_balance, 2) }}
              </span>
            </td>
            <td>
              <span class="badge bg-label-{{ $w->status === 'active' ? 'success' : 'secondary' }}">
                {{ ucfirst($w->status) }}
              </span>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a href="{{ route('card-cash.wallets.index', ['wallet_id' => $w->id]) }}#account-ledger" 
                   class="btn btn-sm btn-{{ $selectedWallet && $selectedWallet->id === $w->id ? 'primary' : 'outline-primary' }}"
                   title="View Inside Account Ledger">
                  <i class="ri-book-open-line me-1"></i> Ledger
                </a>
                <button type="button" class="btn btn-sm btn-outline-success" 
                        data-bs-toggle="modal" data-bs-target="#modalTopupWallet{{ $w->id }}"
                        title="Top-up Balance">
                  <i class="ri-add-line"></i> Top-up
                </button>
                <div class="dropdown d-inline-block">
                  <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill" type="button" data-bs-toggle="dropdown">
                    <i class="ri-more-2-fill"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="{{ route('card-cash.wallets.index', ['wallet_id' => $w->id]) }}#account-ledger">
                        <i class="ri-history-line me-2 text-primary"></i> View Account Ledger
                      </a>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalTopupWallet{{ $w->id }}">
                        <i class="ri-add-circle-line me-2 text-success"></i> Add Balance (Top-up)
                      </button>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalAdjustWallet{{ $w->id }}">
                        <i class="ri-equalizer-line me-2 text-warning"></i> Adjust Balance
                      </button>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEditWallet{{ $w->id }}">
                        <i class="ri-pencil-line me-2 text-info"></i> Edit Details
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <i class="ri-wallet-3-line fs-1 d-block mb-2 text-secondary"></i>
              No wallets configured yet. Click <strong>"Add New Wallet"</strong> to set up an SLP or Own Wallet.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================== -->
<!-- 3. INSIDE THE WALLET ACCOUNT: LEDGER & TXS     -->
<!-- ============================================== -->
@if ($selectedWallet)
<div class="card border border-2 border-primary shadow-sm mb-6" id="account-ledger">
  <!-- Account Header -->
  <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="avatar avatar-md bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center">
        <i class="ri-bank-card-2-line text-white fs-3"></i>
      </div>
      <div>
        <h5 class="mb-0 fw-bold text-white">Inside Account: {{ $selectedWallet->wallet_name }}</h5>
        <div class="d-flex align-items-center gap-2 mt-1">
          <span class="badge bg-white text-primary font-monospace">{{ $selectedWallet->wallet_code }}</span>
          <span class="badge bg-white bg-opacity-25 text-white text-uppercase font-monospace">{{ $selectedWallet->wallet_type }}</span>
          <span class="badge bg-white bg-opacity-25 text-white">{{ strtoupper($selectedWallet->status) }}</span>
        </div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-light text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalTopupWallet{{ $selectedWallet->id }}">
        <i class="ri-add-line me-1"></i> Top-up Balance
      </button>
      <button type="button" class="btn btn-sm btn-outline-light text-white" data-bs-toggle="modal" data-bs-target="#modalAdjustWallet{{ $selectedWallet->id }}">
        <i class="ri-equalizer-line me-1"></i> Adjust Balance
      </button>
      <button type="button" class="btn btn-sm btn-outline-light text-white" data-bs-toggle="modal" data-bs-target="#modalEditWallet{{ $selectedWallet->id }}">
        <i class="ri-pencil-line me-1"></i> Edit
      </button>
    </div>
  </div>

  <!-- Wallet Switcher Navigation Pills -->
  <div class="bg-light px-4 py-2 border-bottom">
    <div class="d-flex align-items-center flex-wrap gap-2">
      <span class="small fw-bold text-muted text-uppercase me-2">Switch Wallet Account:</span>
      @foreach ($wallets as $w)
        <a href="{{ route('card-cash.wallets.index', ['wallet_id' => $w->id]) }}#account-ledger" 
           class="btn btn-sm {{ $selectedWallet->id === $w->id ? 'btn-primary shadow-sm' : 'btn-outline-secondary bg-white' }} rounded-pill px-3 py-1">
          <i class="ri-wallet-3-line me-1"></i>
          {{ $w->wallet_name }}
          <span class="badge {{ $selectedWallet->id === $w->id ? 'bg-white text-primary' : 'bg-label-primary' }} ms-1">
            ₹{{ number_format($w->current_balance, 2) }}
          </span>
        </a>
      @endforeach
    </div>
  </div>

  <!-- Account Specific Stats Ribbon -->
  <div class="card-body border-bottom bg-white py-3">
    <div class="row g-3 text-center">
      <div class="col-6 col-md-3 border-end">
        <span class="text-muted small d-block">Available Account Balance</span>
        <h4 class="fw-bold text-primary mb-0 mt-1">₹{{ number_format($selectedWallet->current_balance, 2) }}</h4>
      </div>
      <div class="col-6 col-md-3 border-end">
        <span class="text-muted small d-block">Account Opening Capital</span>
        <h4 class="fw-semibold text-heading mb-0 mt-1">₹{{ number_format($selectedWallet->opening_balance, 2) }}</h4>
      </div>
      <div class="col-6 col-md-3 border-end">
        <span class="text-muted small d-block">Total Credits / Inflow</span>
        <h4 class="fw-bold text-success mb-0 mt-1">+₹{{ number_format($selectedWalletCredits, 2) }}</h4>
      </div>
      <div class="col-6 col-md-3">
        <span class="text-muted small d-block">Total Debits / Deductions</span>
        <h4 class="fw-bold text-danger mb-0 mt-1">-₹{{ number_format($selectedWalletDebits, 2) }}</h4>
      </div>
    </div>
  </div>

  <!-- Filter Toolbar for Account Ledger Transactions -->
  <div class="card-body bg-light border-bottom py-3">
    <form action="{{ route('card-cash.wallets.index') }}#account-ledger" method="GET" class="row g-3 align-items-end">
      <input type="hidden" name="wallet_id" value="{{ $selectedWallet->id }}">
      
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search Description / Ref</label>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search reference, swipe, notes..." value="{{ request('search') }}">
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-medium">Transaction Type</label>
        <select name="tx_type" class="form-select form-select-sm">
          <option value="">All Transactions</option>
          <option value="credit" {{ request('tx_type') === 'credit' ? 'selected' : '' }}>Credits (+ Top-ups)</option>
          <option value="debit" {{ request('tx_type') === 'debit' ? 'selected' : '' }}>Debits (- Deductions)</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-medium">From Date</label>
        <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}">
      </div>

      <div class="col-md-2">
        <label class="form-label small fw-medium">To Date</label>
        <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}">
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
          <i class="ri-filter-3-line me-1"></i> Filter
        </button>
        <a href="{{ route('card-cash.wallets.index', ['wallet_id' => $selectedWallet->id]) }}#account-ledger" class="btn btn-sm btn-outline-secondary">
          Reset
        </a>
      </div>
    </form>
  </div>

  <!-- Account Transactions & Ledger Table -->
  <div class="table-responsive text-nowrap">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Date & Time</th>
          <th>Type</th>
          <th>Reference / Nature</th>
          <th>Description / Particulars</th>
          <th class="text-end">Credit (+)</th>
          <th class="text-end">Debit (-)</th>
          <th class="text-end">Balance Trail (Before → After)</th>
          <th>Logged By</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($transactions as $tx)
          @php $isCredit = $tx->transaction_type === 'credit'; @endphp
          <tr>
            <td>
              <span class="fw-medium text-heading">
                {{ $tx->transaction_date ? $tx->transaction_date->format('d M Y') : '-' }}
              </span>
              <br>
              <small class="text-muted">
                {{ $tx->transaction_date ? $tx->transaction_date->format('h:i:s A') : '' }}
              </small>
            </td>
            <td>
              <span class="badge bg-label-{{ $isCredit ? 'success' : 'danger' }} text-uppercase fw-bold px-2 py-1">
                <i class="ri-{{ $isCredit ? 'arrow-down-circle-line' : 'arrow-up-circle-line' }} me-1"></i>
                {{ $tx->transaction_type }}
              </span>
            </td>
            <td>
              <span class="badge bg-label-secondary font-monospace">
                {{ strtoupper(str_replace('_', ' ', $tx->reference_type ?? 'DIRECT')) }}
              </span>
              @if ($tx->reference_id)
                <small class="text-muted font-monospace d-block mt-1">Ref ID: #{{ $tx->reference_id }}</small>
              @endif
            </td>
            <td>
              <span class="text-heading fw-medium">{{ $tx->description }}</span>
            </td>
            <td class="text-end">
              @if ($isCredit)
                <span class="text-success fw-bold fs-6">+₹{{ number_format($tx->amount, 2) }}</span>
              @else
                <span class="text-muted opacity-50">-</span>
              @endif
            </td>
            <td class="text-end">
              @if (!$isCredit)
                <span class="text-danger fw-bold fs-6">-₹{{ number_format($tx->amount, 2) }}</span>
              @else
                <span class="text-muted opacity-50">-</span>
              @endif
            </td>
            <td class="text-end font-monospace">
              <span class="text-muted small">₹{{ number_format($tx->balance_before, 2) }}</span>
              <i class="ri-arrow-right-line mx-1 text-secondary"></i>
              <span class="fw-bold text-heading">₹{{ number_format($tx->balance_after, 2) }}</span>
            </td>
            <td>
              <small class="text-heading">{{ $tx->creator?->name ?? 'System' }}</small>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="ri-file-list-3-line fs-1 d-block mb-2 text-secondary"></i>
              No transactions found for <strong>{{ $selectedWallet->wallet_name }}</strong> matching criteria.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  @if ($transactions instanceof \Illuminate\Pagination\LengthAwarePaginator && $transactions->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center py-3">
      <small class="text-muted">
        Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} ledger entries
      </small>
      <div>
        {{ $transactions->links() }}
      </div>
    </div>
  @endif
</div>
@endif

<!-- ============================================== -->
<!-- MODAL: ADD WALLET                              -->
<!-- ============================================== -->
<div class="modal fade" id="modalAddWallet" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('card-cash.wallets.store') }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Create New Wallet Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-medium">Wallet Name <span class="text-danger">*</span></label>
            <input type="text" name="wallet_name" class="form-control" placeholder="e.g. SLP Primary Wallet" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Unique Code <span class="text-danger">*</span></label>
            <input type="text" name="wallet_code" class="form-control font-monospace text-uppercase" placeholder="e.g. SLP_WALLET_2" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Wallet Type <span class="text-danger">*</span></label>
            <input type="text" name="wallet_type" class="form-control" placeholder="e.g. internal / vendor / third_party" value="internal" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Opening Balance (₹) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="0.00" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Remarks / Description</label>
            <textarea name="remarks" class="form-control" rows="2" placeholder="Notes on this wallet..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Wallet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================== -->
<!-- PER-WALLET MODALS (TOPUP, ADJUST, EDIT)        -->
<!-- ============================================== -->
@foreach ($wallets as $wallet)
  <!-- Modal Topup -->
  <div class="modal fade" id="modalTopupWallet{{ $wallet->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('card-cash.wallets.add-balance', $wallet->id) }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Top-up Wallet: {{ $wallet->wallet_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info py-2 small mb-3">
              <i class="ri-information-line me-1"></i> Current Balance: <strong>₹{{ number_format($wallet->current_balance, 2) }}</strong>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">Top-up Amount (₹) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="1" name="amount" class="form-control form-control-lg" placeholder="e.g. 50000" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">Description / Source <span class="text-danger">*</span></label>
              <input type="text" name="description" class="form-control" placeholder="e.g. Bank settlement, Owner capital top-up" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Credit to Wallet</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Adjust Balance -->
  <div class="modal fade" id="modalAdjustWallet{{ $wallet->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('card-cash.wallets.adjust-balance', $wallet->id) }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Adjust Balance: {{ $wallet->wallet_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning py-2 small mb-3">
              <i class="ri-alert-line me-1"></i> Current Balance: <strong>₹{{ number_format($wallet->current_balance, 2) }}</strong>. Setting a new balance will create an adjustment ledger record.
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">New Target Balance (₹) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="current_balance" class="form-control form-control-lg" value="{{ $wallet->current_balance }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">Reason for Adjustment <span class="text-danger">*</span></label>
              <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Physical reconciliation with bank account" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning">Apply Adjustment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Edit Wallet -->
  <div class="modal fade" id="modalEditWallet{{ $wallet->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form action="{{ route('card-cash.wallets.update', $wallet->id) }}" method="POST">
          @csrf
          @method('PUT')
          <div class="modal-header">
            <h5 class="modal-title fw-bold">Edit Wallet: {{ $wallet->wallet_name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label fw-medium">Wallet Name <span class="text-danger">*</span></label>
              <input type="text" name="wallet_name" class="form-control" value="{{ $wallet->wallet_name }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">Wallet Type <span class="text-danger">*</span></label>
              <input type="text" name="wallet_type" class="form-control" value="{{ $wallet->wallet_type }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="active" {{ $wallet->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $wallet->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-medium">Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $wallet->remarks }}</textarea>
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

<!-- ============================================== -->
<!-- MODAL: AJAX QUICK LEDGER VIEWER                -->
<!-- ============================================== -->
<div class="modal fade" id="modalWalletLedger" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="walletLedgerTitle">Wallet Ledger</h5>
          <small class="text-muted font-monospace" id="walletLedgerSubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="walletLedgerModalBody">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('view_wallet_modal')) {
    openWalletLedgerModal(urlParams.get('view_wallet_modal'));
  }
});

function openWalletLedgerModal(walletId) {
  const modalEl = document.getElementById('modalWalletLedger');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  const titleEl = document.getElementById('walletLedgerTitle');
  const subtitleEl = document.getElementById('walletLedgerSubtitle');
  const bodyEl = document.getElementById('walletLedgerModalBody');

  fetch('{{ url("card-to-cash/wallets") }}/' + walletId, {
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
  })
  .then(res => res.json())
  .then(res => {
    if (res.status && res.wallet) {
      const wallet = res.wallet;
      const txs = res.transactions || [];

      titleEl.textContent = wallet.wallet_name + ' — Quick Ledger';
      subtitleEl.textContent = 'Code: ' + wallet.wallet_code + ' | Type: ' + wallet.wallet_type.toUpperCase();

      let txRows = '';
      if (txs.length === 0) {
        txRows = '<tr><td colspan="6" class="text-center py-4 text-muted">No transactions recorded in this wallet yet.</td></tr>';
      } else {
        txs.forEach(tx => {
          const isCredit = tx.transaction_type === 'credit';
          txRows += `
            <tr>
              <td><small class="text-muted">${tx.transaction_date ? new Date(tx.transaction_date).toLocaleString() : '-'}</small></td>
              <td><span class="badge bg-label-${isCredit ? 'success' : 'danger'} text-uppercase">${tx.transaction_type}</span></td>
              <td><span class="badge bg-label-secondary font-monospace">${tx.reference_type}</span></td>
              <td><span class="small">${tx.description}</span></td>
              <td><span class="fw-bold fs-6 ${isCredit ? 'text-success' : 'text-danger'}">${isCredit ? '+' : '-'}₹${parseFloat(tx.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span></td>
              <td><span class="fw-semibold">₹${parseFloat(tx.balance_after).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span></td>
            </tr>`;
        });
      }

      bodyEl.innerHTML = `
        <div class="row g-3 mb-4">
          <div class="col-sm-4">
            <div class="p-3 bg-primary text-white rounded">
              <span class="opacity-75 small d-block">Current Available Balance</span>
              <h4 class="text-white fw-bold mb-0">₹${parseFloat(wallet.current_balance).toLocaleString('en-IN', {minimumFractionDigits: 2})}</h4>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="p-3 bg-light rounded">
              <span class="text-muted small d-block">Total Credits</span>
              <h4 class="text-success fw-bold mb-0">+₹${parseFloat(res.total_credits || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</h4>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="p-3 bg-light rounded">
              <span class="text-muted small d-block">Total Debits</span>
              <h4 class="text-danger fw-bold mb-0">-₹${parseFloat(res.total_debits || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}</h4>
            </div>
          </div>
        </div>

        <div class="table-responsive text-nowrap">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Date & Time</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Balance After</th>
              </tr>
            </thead>
            <tbody>${txRows}</tbody>
          </table>
        </div>`;
    }
  })
  .catch(err => {
    bodyEl.innerHTML = `<div class="alert alert-danger mb-0">Error loading wallet ledger. Please try again.</div>`;
  });
}
</script>
@endsection
