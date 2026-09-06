@extends('layouts/layoutMaster')

@section('title', __('Account Dashboard'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">{{ __('Accounting dashboard') }}</h4>
      <p class="text-muted mb-0">{{ __('Overview from integrated ERP-style accounting module') }}</p>
    </div>
  </div>

 <div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card bg-lighter border-0 shadow-none">
      <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="fw-medium me-1 text-primary"><i class="ri-flashlight-line me-1"></i> {{ __('Quick Actions') }}:</span>
          
          <!-- 1. Blue -->
          <button type="button" class="btn btn-sm btn-label-primary" data-bs-toggle="modal" data-bs-target="#addBankModal">
            <i class="ri-bank-line me-1"></i> {{ __('Add Bank') }}
          </button>

          <!-- 2. Orange/Yellow -->
          <button type="button" class="btn btn-sm btn-label-warning" data-bs-toggle="modal" data-bs-target="#addAccountTypeModal">
            <i class="ri-shapes-line me-1"></i> {{ __('Add Type') }}
          </button>

          <!-- 3. Cyan/Sky Blue -->
          <a href="{{ route('account.chart-of-accounts.index') }}" class="btn btn-sm btn-label-info">
            <i class="ri-node-tree me-1"></i> {{ __('View Chart of Accounts') }}
          </a>

          <!-- 4. Green -->
          <button type="button" class="btn btn-sm btn-label-success" data-bs-toggle="modal" data-bs-target="#addRevenueDraftModal">
            <i class="ri-add-circle-line me-1"></i> {{ __('Add New Revenue') }}
          </button>

          <!-- 5. Purple (Custom Style) -->
          <button type="button" class="btn btn-sm btn-label-secondary" style="color: #6610f2; background-color: #ebe3ff; border-color: transparent;" data-bs-toggle="modal" data-bs-target="#addRevenueCategoryModal">
            <i class="ri-bookmark-2-line me-1"></i> {{ __('Add Revenue Category') }}
          </button>

          <!-- 6. Red -->
          <button type="button" class="btn btn-sm btn-label-danger" data-bs-toggle="modal" data-bs-target="#addExpenseDraftModal">
            <i class="ri-add-circle-line me-1"></i> {{ __('Add New Expense') }}
          </button>

          <!-- 7. Pink/Rose (Custom Style) -->
          <button type="button" class="btn btn-sm btn-label-secondary" style="color: #e83e8c; background-color: #ffe5f0; border-color: transparent;" data-bs-toggle="modal" data-bs-target="#addExpenseCategoryModal">
            <i class="ri-bookmark-2-line me-1"></i> {{ __('Add Expense Category') }}
          </button>

          <!-- 8. Dark/Slate -->
          <button type="button" class="btn btn-sm btn-label-dark" data-bs-toggle="modal" data-bs-target="#addChartOfAccountModal">
            <i class="ri-add-line me-1"></i> {{ __('Add New GL Account') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>


  <div class="row g-4 mb-2">
    <div class="col-12">
      <h5 class="mb-2">{{ __('Loan portfolio (linked)') }}</h5>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-primary">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Loan accounts (total)') }}</span>
          <h3 class="mb-0">{{ number_format(($loanPortfolio ?? [])['loan_accounts_total'] ?? 0) }}</h3>
          <a href="{{ route('account.loan-accounts.index') }}" class="small">{{ __('View list') }} →</a>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Active loan accounts') }}</span>
          <h3 class="mb-0">{{ number_format(($loanPortfolio ?? [])['loan_accounts_active'] ?? 0) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Pending EMIs') }}</span>
          <h3 class="mb-0">{{ number_format(($loanPortfolio ?? [])['emis_pending'] ?? 0) }}</h3>
          <a href="{{ route('account.emis.index', ['status' => 'pending']) }}" class="small">{{ __('View list') }} →</a>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Overdue EMIs') }}</span>
          <h3 class="mb-0 text-danger">{{ number_format(($loanPortfolio ?? [])['emis_overdue'] ?? 0) }}</h3>
          <a href="{{ route('account.emis.index') }}" class="small">{{ __('All EMIs') }} →</a>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Clients (ERP customers)') }}</span>
          <h3 class="mb-0">{{ number_format($stats['total_clients'] ?? 0) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Vendors') }}</span>
          <h3 class="mb-0">{{ number_format($stats['total_vendors'] ?? 0) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Total revenue') }}</span>
          <h3 class="mb-0">₹{{ number_format((float) ($stats['total_revenue'] ?? 0), 2) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card">
        <div class="card-body">
          <span class="d-block mb-1 text-muted">{{ __('Total expense') }}</span>
          <h3 class="mb-0">₹{{ number_format((float) ($stats['total_expense'] ?? 0), 2) }}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4 border-info">
    <div class="card-header"><strong>{{ __('Posted (GL)') }}</strong></div>
    <div class="card-body row g-3">
      <div class="col-md-4">
        <span class="text-muted d-block">{{ __('Posted revenue') }}</span>
        <h5 class="mb-0">₹{{ number_format((float) ($stats['total_revenue_posted'] ?? 0), 2) }}</h5>
      </div>
      <div class="col-md-4">
        <span class="text-muted d-block">{{ __('Posted expense') }}</span>
        <h5 class="mb-0">₹{{ number_format((float) ($stats['total_expense_posted'] ?? 0), 2) }}</h5>
      </div>
      <div class="col-md-4">
        <span class="text-muted d-block">{{ __('Net (posted only)') }}</span>
        <h5 class="mb-0 {{ ($stats['net_profit_posted'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
          ₹{{ number_format((float) ($stats['net_profit_posted'] ?? 0), 2) }}
        </h5>
      </div>
    </div>
    <div class="card-footer small text-muted">{{ __('Totals above include draft + approved + posted; posted row shows only items with status Posted.') }}</div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Customer payments (total)') }}</strong></div>
        <div class="card-body">
          <h4 class="mb-0">₹{{ number_format((float) ($stats['total_customer_payment'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Vendor payments (total)') }}</strong></div>
        <div class="card-body">
          <h4 class="mb-0">₹{{ number_format((float) ($stats['total_vendor_payment'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><strong>{{ __('Net profit (revenue − expense)') }}</strong></div>
    <div class="card-body">
      <h3 class="mb-0 {{ ($stats['net_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
        ₹{{ number_format((float) ($stats['net_profit'] ?? 0), 2) }}
      </h3>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Recent revenues') }}</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>#</th><th>{{ __('Title') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
              <tbody>
                @forelse($recentRevenues as $r)
                  <tr>
                    <td>{{ $r['id'] }}</td>
                    <td>{{ $r['title'] }}</td>
                    <td class="text-end">₹{{ number_format((float) $r['amount'], 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No data yet') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Recent expenses') }}</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>#</th><th>{{ __('Title') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
              <tbody>
                @forelse($recentExpenses as $e)
                  <tr>
                    <td>{{ $e['id'] }}</td>
                    <td>{{ $e['title'] }}</td>
                    <td class="text-end">₹{{ number_format((float) $e['amount'], 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No data yet') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
