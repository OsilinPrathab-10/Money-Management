@extends('layouts/layoutMaster')

@section('title', __('Year-End Closing'))

@section('content')
<div class="account-module">

  {{-- Page header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
      <i class="ri-calendar-check-line text-primary me-2"></i>{{ __('Year-End Closing') }}
    </h4>
    <a href="{{ route('account.reports.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm">
      <i class="ri-arrow-left-line me-1"></i> {{ __('Back to Reports') }}
    </a>
  </div>

  {{-- Flash messages --}}
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4" role="alert">
      <i class="ri-checkbox-circle-line me-2"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4" role="alert">
      <i class="ri-error-warning-line me-2"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Year selector --}}
  <div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('account.year-end-closing.index') }}" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold text-muted small text-uppercase">{{ __('Financial Year') }}</label>
          <select name="year" class="form-select" onchange="this.form.submit()">
            @foreach ($availableYears as $y)
              <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-8">
          <p class="text-muted small mb-0 pt-1">
            <i class="ri-information-line me-1"></i>
            {{ __('Showing accumulated revenue and expense balances for the period') }}
            <strong>{{ $preview['fy_start'] }}</strong> {{ __('to') }}
            <strong>{{ $preview['fy_end'] }}</strong>.
            {{ __('Closing this year transfers the net profit to the Retained Earnings (Capital) account.') }}
          </p>
        </div>
      </form>
    </div>
  </div>

  {{-- Already closed notice --}}
  @if ($preview['already_closed'])
    <div class="alert alert-warning shadow-sm border-0 rounded-3 mb-4">
      <i class="ri-lock-line me-2"></i>
      <strong>{{ __('FY :year has already been closed.', ['year' => $year]) }}</strong>
      {{ __('A closing journal was posted for this financial year. No further action is needed.') }}
    </div>
  @endif

  <div class="row g-4 mb-4">
    {{-- Summary cards --}}
    <div class="col-sm-4">
      <div class="card border-0 shadow-sm rounded-4 h-100 text-center">
        <div class="card-body py-4">
          <p class="text-muted small text-uppercase fw-semibold mb-1">{{ __('Total Revenue') }}</p>
          <h3 class="text-success mb-0">₹ {{ number_format($preview['total_revenue'], 2) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border-0 shadow-sm rounded-4 h-100 text-center">
        <div class="card-body py-4">
          <p class="text-muted small text-uppercase fw-semibold mb-1">{{ __('Total Expenses') }}</p>
          <h3 class="text-danger mb-0">₹ {{ number_format($preview['total_expense'], 2) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card border-0 shadow-sm rounded-4 h-100 text-center">
        <div class="card-body py-4">
          <p class="text-muted small text-uppercase fw-semibold mb-1">{{ __('Net Profit / (Loss)') }}</p>
          <h3 class="mb-0 {{ $preview['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
            ₹ {{ number_format(abs($preview['net_profit']), 2) }}
            @if ($preview['net_profit'] < 0)
              <small class="fs-6">({{ __('Loss') }})</small>
            @endif
          </h3>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    {{-- Revenue accounts table --}}
    <div class="col-md-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
          <h6 class="fw-bold mb-0">
            <i class="ri-funds-line text-success me-2"></i>{{ __('Revenue Accounts (FY :year)', ['year' => $year]) }}
          </h6>
        </div>
        <div class="card-body px-4 pb-4">
          @if (count($preview['revenue_accounts']) > 0)
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Account') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($preview['revenue_accounts'] as $acct)
                    <tr>
                      <td class="text-muted small">{{ $acct['account_code'] }}</td>
                      <td>{{ $acct['account_name'] }}</td>
                      <td class="text-end text-success fw-semibold">₹ {{ number_format($acct['fy_balance'], 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="2" class="fw-bold">{{ __('Total') }}</td>
                    <td class="text-end fw-bold text-success">₹ {{ number_format($preview['total_revenue'], 2) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          @else
            <p class="text-muted text-center py-4 mb-0">{{ __('No revenue transactions found for FY :year.', ['year' => $year]) }}</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Expense accounts table --}}
    <div class="col-md-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0 px-4">
          <h6 class="fw-bold mb-0">
            <i class="ri-arrow-down-circle-line text-danger me-2"></i>{{ __('Expense Accounts (FY :year)', ['year' => $year]) }}
          </h6>
        </div>
        <div class="card-body px-4 pb-4">
          @if (count($preview['expense_accounts']) > 0)
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Account') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($preview['expense_accounts'] as $acct)
                    <tr>
                      <td class="text-muted small">{{ $acct['account_code'] }}</td>
                      <td>{{ $acct['account_name'] }}</td>
                      <td class="text-end text-danger fw-semibold">₹ {{ number_format($acct['fy_balance'], 2) }}</td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="2" class="fw-bold">{{ __('Total') }}</td>
                    <td class="text-end fw-bold text-danger">₹ {{ number_format($preview['total_expense'], 2) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          @else
            <p class="text-muted text-center py-4 mb-0">{{ __('No expense transactions found for FY :year.', ['year' => $year]) }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Closing journal entry explanation --}}
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body px-4 py-4">
      <h6 class="fw-bold mb-3">
        <i class="ri-file-list-3-line text-primary me-2"></i>{{ __('Journal Entry That Will Be Posted') }}
      </h6>
      @if (abs($preview['net_profit']) < 0.01)
        <p class="text-muted mb-0">{{ __('Net profit is zero — no closing journal is needed for FY :year.', ['year' => $year]) }}</p>
      @else
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Account') }}</th>
                <th class="text-end">{{ __('Debit') }}</th>
                <th class="text-end">{{ __('Credit') }}</th>
              </tr>
            </thead>
            <tbody>
              @if ($preview['net_profit'] > 0)
                @foreach ($preview['revenue_accounts'] as $acct)
                  <tr>
                    <td>{{ $acct['account_code'] }} — {{ $acct['account_name'] }}</td>
                    <td class="text-end">₹ {{ number_format($acct['fy_balance'], 2) }}</td>
                    <td class="text-end text-muted">—</td>
                  </tr>
                @endforeach
                @foreach ($preview['expense_accounts'] as $acct)
                  <tr>
                    <td>{{ $acct['account_code'] }} — {{ $acct['account_name'] }}</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end">₹ {{ number_format($acct['fy_balance'], 2) }}</td>
                  </tr>
                @endforeach
                <tr class="table-success fw-bold">
                  <td>3200 — {{ __('Retained Earnings (Capital)') }}</td>
                  <td class="text-end text-muted">—</td>
                  <td class="text-end">₹ {{ number_format($preview['net_profit'], 2) }}</td>
                </tr>
              @else
                <tr class="table-danger fw-bold">
                  <td>3200 — {{ __('Retained Earnings (Capital)') }}</td>
                  <td class="text-end">₹ {{ number_format(abs($preview['net_profit']), 2) }}</td>
                  <td class="text-end text-muted">—</td>
                </tr>
                @foreach ($preview['expense_accounts'] as $acct)
                  <tr>
                    <td>{{ $acct['account_code'] }} — {{ $acct['account_name'] }}</td>
                    <td class="text-end text-muted">—</td>
                    <td class="text-end">₹ {{ number_format($acct['fy_balance'], 2) }}</td>
                  </tr>
                @endforeach
                @foreach ($preview['revenue_accounts'] as $acct)
                  <tr>
                    <td>{{ $acct['account_code'] }} — {{ $acct['account_name'] }}</td>
                    <td class="text-end">₹ {{ number_format($acct['fy_balance'], 2) }}</td>
                    <td class="text-end text-muted">—</td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- Confirm & close button --}}
  @if (!$preview['already_closed'] && abs($preview['net_profit']) >= 0.01)
    <div class="card border-0 shadow-sm rounded-4 border-warning">
      <div class="card-body px-4 py-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <h6 class="fw-bold mb-1">
            <i class="ri-alarm-warning-line text-warning me-2"></i>{{ __('Confirm Year-End Close for FY :year', ['year' => $year]) }}
          </h6>
          <p class="text-muted small mb-0">
            {{ __('This action is irreversible. It will post a balanced journal entry dated :date and update all GL account balances accordingly.', ['date' => $preview['fy_end']]) }}
          </p>
        </div>
        <form method="POST" action="{{ route('account.year-end-closing.close') }}"
              onsubmit="return confirm('{{ __('Are you sure you want to close FY :year? This cannot be undone.', ['year' => $year]) }}')">
          @csrf
          <input type="hidden" name="year" value="{{ $year }}">
          <button type="submit" class="btn btn-danger rounded-pill px-4">
            <i class="ri-lock-2-line me-1"></i> {{ __('Close FY :year & Transfer to Capital', ['year' => $year]) }}
          </button>
        </form>
      </div>
    </div>
  @endif

</div>
@endsection
