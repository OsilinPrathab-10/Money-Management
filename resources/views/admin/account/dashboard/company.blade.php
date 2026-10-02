@extends('layouts/layoutMaster')

@section('title', __('Account Dashboard'))

@section('content')
@php
  $cash = $cashBook ?? [];
  $loanRows = $loanByClient ?? collect();
  $chitRows = $chitByClient ?? collect();
  $fdRows = $fdByClient ?? collect();
  $loanTot = $loanTotals ?? ['credit' => 0, 'debit' => 0, 'clients' => 0];
  $chitTot = $chitTotals ?? ['credit' => 0, 'debit' => 0, 'clients' => 0];
  $fdTot = $fdTotals ?? ['credit' => 0, 'debit' => 0, 'clients' => 0];
  $perPage = $perPage ?? 25;
  $perPageOptions = $perPageOptions ?? [25, 75, 100, 250, 500];
  $activeTab = request('tab', 'loan');
  if (! in_array($activeTab, ['loan', 'chit', 'fd'], true)) {
      $activeTab = 'loan';
  }
@endphp
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Accounting Dashboard Management'),
    'subtitle' => __('Bank, cash, loan, chit & fixed deposit money flow'),
    'toolbar' => '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal"><i class="ri-bank-line me-1"></i>' . e(__('Add Bank')) . '</button>
      <a href="' . e(route('account.bank-accounts.index')) . '" class="btn btn-sm btn-outline-primary"><i class="ri-bank-line me-1"></i>' . e(__('Bank Accounts')) . '</a>
      <a href="' . e(route('account.bank-transactions.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="ri-exchange-line me-1"></i>' . e(__('Transactions')) . '</a>
      <a href="' . e(route('account.day-book.index')) . '" class="btn btn-sm btn-outline-warning"><i class="ri-book-3-line me-1"></i>' . e(__('Day Book')) . '</a>',
  ])

  <div class="row g-4 mb-5">
    <div class="col-sm-6 col-xl-3">
      <div class="card card-border-shadow-info h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-info p-2"><i class="ri-hand-coin-line ri-24px"></i></span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">₹{{ number_format((float) ($cash['cash_balance'] ?? 0), 2) }}</h4>
              <p class="mb-0 small text-muted">{{ __('Cash in Hand') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card card-border-shadow-primary h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-primary p-2"><i class="ri-bank-line ri-24px"></i></span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">₹{{ number_format((float) ($cash['bank_balance'] ?? 0), 2) }}</h4>
              <p class="mb-0 small text-muted">{{ __('Bank balances') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card card-border-shadow-success h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-success p-2"><i class="ri-wallet-3-line ri-24px"></i></span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">₹{{ number_format((float) ($cash['total_liquidity'] ?? 0), 2) }}</h4>
              <p class="mb-0 small text-muted">{{ __('Total liquidity') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card card-border-shadow-warning h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-warning p-2"><i class="ri-safe-2-line ri-24px"></i></span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">{{ number_format($cash['accounts_count'] ?? 0) }}</h4>
              <p class="mb-0 small text-muted">{{ __('Active accounts') }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header border-bottom pb-0">
      <ul class="nav nav-tabs card-header-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button
            type="button"
            class="nav-link {{ $activeTab === 'loan' ? 'active' : '' }}"
            id="tab-loan-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-loan"
            role="tab"
            aria-controls="tab-loan"
            aria-selected="{{ $activeTab === 'loan' ? 'true' : 'false' }}"
            onclick="if (window.history && history.replaceState) { const u = new URL(window.location.href); u.searchParams.set('tab','loan'); history.replaceState({}, '', u); }"
          >
            <i class="ri-bank-card-line me-1"></i>{{ __('Loan') }}
            <span class="badge bg-label-primary ms-1">{{ number_format($loanTot['clients'] ?? 0) }}</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            type="button"
            class="nav-link {{ $activeTab === 'chit' ? 'active' : '' }}"
            id="tab-chit-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-chit"
            role="tab"
            aria-controls="tab-chit"
            aria-selected="{{ $activeTab === 'chit' ? 'true' : 'false' }}"
            onclick="if (window.history && history.replaceState) { const u = new URL(window.location.href); u.searchParams.set('tab','chit'); history.replaceState({}, '', u); }"
          >
            <i class="ri-group-line me-1"></i>{{ __('Chit') }}
            <span class="badge bg-label-success ms-1">{{ number_format($chitTot['clients'] ?? 0) }}</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button
            type="button"
            class="nav-link {{ $activeTab === 'fd' ? 'active' : '' }}"
            id="tab-fd-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-fd"
            role="tab"
            aria-controls="tab-fd"
            aria-selected="{{ $activeTab === 'fd' ? 'true' : 'false' }}"
            onclick="if (window.history && history.replaceState) { const u = new URL(window.location.href); u.searchParams.set('tab','fd'); history.replaceState({}, '', u); }"
          >
            <i class="ri-safe-2-line me-1"></i>{{ __('Fixed Deposit') }}
            <span class="badge bg-label-warning ms-1">{{ number_format($fdTot['clients'] ?? 0) }}</span>
          </button>
        </li>
      </ul>
    </div>

        <div class="card-body">
      <div class="tab-content">
        {{-- Loan tab --}}
        <div class="tab-pane fade {{ $activeTab === 'loan' ? 'show active' : '' }}" id="tab-loan" role="tabpanel" aria-labelledby="tab-loan-btn">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
              <h6 class="mb-0">{{ __('Client-wise loan credit & debit') }}</h6>
              <small class="text-muted">{{ __('Credit = collections · Debit = disbursements') }}</small>
            </div>
            <a href="{{ route('account.loan-accounts.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View all loans') }}</a>
        </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <span class="d-block text-muted small">{{ __('Total credit') }}</span>
                <h4 class="mb-0 acc-credit">₹{{ number_format((float) ($loanTot['credit'] ?? 0), 2) }}</h4>
      </div>
    </div>
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <span class="d-block text-muted small">{{ __('Total debit') }}</span>
                <h4 class="mb-0 acc-debit">₹{{ number_format((float) ($loanTot['debit'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
          <div class="table-responsive">
            <table class="account-datatable table table-hover">
              <thead>
                <tr>
                  <th>{{ __('Client') }}</th>
                  <th class="text-end acc-credit">{{ __('Credit') }}</th>
                  <th class="text-end acc-debit">{{ __('Debit') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($loanRows as $row)
                  <tr>
                    <td><span class="acc-name">{{ $row->client_name }}</span></td>
                    <td class="text-end acc-credit {{ ((float) $row->credit) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) $row->credit, 2) }}</td>
                    <td class="text-end acc-debit {{ ((float) $row->debit) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) $row->debit, 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No loan credit / debit yet') }}</td></tr>
                @endforelse
              </tbody>
              @if(method_exists($loanRows, 'isNotEmpty') ? $loanRows->isNotEmpty() : $loanRows->count())
                <tfoot>
                  <tr class="fw-semibold">
                    <td>{{ __('Total (all clients)') }}</td>
                    <td class="text-end acc-credit">₹{{ number_format((float) ($loanTot['credit'] ?? 0), 2) }}</td>
                    <td class="text-end acc-debit">₹{{ number_format((float) ($loanTot['debit'] ?? 0), 2) }}</td>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
          @include('admin.account.shared.flow-pagination', [
            'tab' => 'loan',
            'paginator' => $loanRows,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
          ])
  </div>

        {{-- Chit tab --}}
        <div class="tab-pane fade {{ $activeTab === 'chit' ? 'show active' : '' }}" id="tab-chit" role="tabpanel" aria-labelledby="tab-chit-btn">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
              <h6 class="mb-0">{{ __('Client-wise chit credit & debit') }}</h6>
              <small class="text-muted">{{ __('Credit = installments · Debit = payouts') }}</small>
      </div>
            <a href="{{ route('account.chit-accounts.index') }}" class="btn btn-sm btn-outline-success">{{ __('View all chits') }}</a>
      </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <span class="d-block text-muted small">{{ __('Total credit') }}</span>
                <h4 class="mb-0 acc-credit">₹{{ number_format((float) ($chitTot['credit'] ?? 0), 2) }}</h4>
      </div>
    </div>
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <span class="d-block text-muted small">{{ __('Total debit') }}</span>
                <h4 class="mb-0 acc-debit">₹{{ number_format((float) ($chitTot['debit'] ?? 0), 2) }}</h4>
      </div>
    </div>
  </div>
          <div class="table-responsive">
            <table class="account-datatable table table-hover">
              <thead>
                <tr>
                  <th>{{ __('Client') }}</th>
                  <th class="text-end acc-credit">{{ __('Credit') }}</th>
                  <th class="text-end acc-debit">{{ __('Debit') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($chitRows as $row)
                  <tr>
                    <td><span class="acc-name">{{ $row->client_name }}</span></td>
                    <td class="text-end acc-credit {{ ((float) $row->credit) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) $row->credit, 2) }}</td>
                    <td class="text-end acc-debit {{ ((float) $row->debit) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) $row->debit, 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No chit credit / debit yet') }}</td></tr>
                @endforelse
              </tbody>
              @if(method_exists($chitRows, 'isNotEmpty') ? $chitRows->isNotEmpty() : $chitRows->count())
                <tfoot>
                  <tr class="fw-semibold">
                    <td>{{ __('Total (all clients)') }}</td>
                    <td class="text-end acc-credit">₹{{ number_format((float) ($chitTot['credit'] ?? 0), 2) }}</td>
                    <td class="text-end acc-debit">₹{{ number_format((float) ($chitTot['debit'] ?? 0), 2) }}</td>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
          @include('admin.account.shared.flow-pagination', [
            'tab' => 'chit',
            'paginator' => $chitRows,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
          ])
        </div>

        {{-- FD tab --}}
        <div class="tab-pane fade {{ $activeTab === 'fd' ? 'show active' : '' }}" id="tab-fd" role="tabpanel" aria-labelledby="tab-fd-btn">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
              <h6 class="mb-0">{{ __('Client-wise FD credit & debit') }}</h6>
              <small class="text-muted">{{ __('Credit = deposits · Debit = closures / payouts') }}</small>
            </div>
            <a href="{{ route('account.fd-accounts.index') }}" class="btn btn-sm btn-outline-warning">{{ __('View all FDs') }}</a>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <span class="d-block text-muted small">{{ __('Total credit') }}</span>
                <h4 class="mb-0 acc-credit">₹{{ number_format((float) ($fdTot['credit'] ?? 0), 2) }}</h4>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="border rounded p-3 h-100">
                <span class="d-block text-muted small">{{ __('Total debit') }}</span>
                <h4 class="mb-0 acc-debit">₹{{ number_format((float) ($fdTot['debit'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
          <div class="table-responsive">
            <table class="account-datatable table table-hover">
              <thead>
                <tr>
                  <th>{{ __('Client') }}</th>
                  <th class="text-end acc-credit">{{ __('Credit') }}</th>
                  <th class="text-end acc-debit">{{ __('Debit') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse($fdRows as $row)
                  <tr>
                    <td><span class="acc-name">{{ $row->client_name }}</span></td>
                    <td class="text-end acc-credit {{ ((float) $row->credit) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) $row->credit, 2) }}</td>
                    <td class="text-end acc-debit {{ ((float) $row->debit) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) $row->debit, 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No FD credit / debit yet') }}</td></tr>
                @endforelse
              </tbody>
              @if(method_exists($fdRows, 'isNotEmpty') ? $fdRows->isNotEmpty() : $fdRows->count())
                <tfoot>
                  <tr class="fw-semibold">
                    <td>{{ __('Total (all clients)') }}</td>
                    <td class="text-end acc-credit">₹{{ number_format((float) ($fdTot['credit'] ?? 0), 2) }}</td>
                    <td class="text-end acc-debit">₹{{ number_format((float) ($fdTot['debit'] ?? 0), 2) }}</td>
                  </tr>
                </tfoot>
              @endif
            </table>
          </div>
          @include('admin.account.shared.flow-pagination', [
            'tab' => 'fd',
            'paginator' => $fdRows,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
          ])
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
