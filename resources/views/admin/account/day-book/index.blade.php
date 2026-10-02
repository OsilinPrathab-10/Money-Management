@extends('layouts/layoutMaster')

@section('title', __('Day Book'))

@section('content')
@php
  use App\Services\Account\AccountingTags;
@endphp
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Day Book Management'),
    'subtitle' => __('Daily tagged movements for Chit, Loan & FD (auto-updates from collections, fees, payouts).'),
  ])

  <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
      @php
        $exportQuery = array_filter([
          'day' => $day ?? null,
          'status' => $status ?? null,
          'bank_account_id' => $bankAccountId ?? null,
          'revenue_category_id' => $revenueCategoryId ?? null,
          'expense_category_id' => $expenseCategoryId ?? null,
          'module_tag' => ($moduleTag ?? 'all') !== 'all' ? $moduleTag : null,
          'entry_tag' => $entryTag ?? null,
          'search' => $search ?? null,
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.day-book.export',
        'query' => $exportQuery,
      ])
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Revenue') }}</span>
          <h4 class="mb-0">₹{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Expense') }}</span>
          <h4 class="mb-0">₹{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-xl-2">
      <div class="card h-100 border-info">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Net profit') }}</span>
          <h4 class="mb-0 {{ ((float) ($totals['net_profit'] ?? 0)) >= 0 ? 'text-success' : 'text-danger' }}">
            ₹{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}
          </h4>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Bank/Cash In') }}</span>
          <h4 class="mb-0 acc-credit">₹{{ number_format((float) ($totals['bank_credits'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Bank/Cash Out') }}</span>
          <h4 class="mb-0 acc-debit">₹{{ number_format((float) ($totals['bank_debits'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4 col-xl-2">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Cash book net') }}</span>
          <h4 class="mb-0 {{ ((float) ($totals['bank_net'] ?? 0)) >= 0 ? 'text-success' : 'text-danger' }}">
            ₹{{ number_format((float) ($totals['bank_net'] ?? 0), 2) }}
          </h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-2 align-items-center">
        <div class="col-md-2">
          <input type="date" name="day" value="{{ $day }}" class="form-control" title="{{ __('Select Day') }}">
        </div>
        <div class="col-md-2">
          <select name="module_tag" class="form-select">
            <option value="all">{{ __('All modules') }}</option>
            @foreach ($moduleOptions ?? [] as $m)
              <option value="{{ $m }}" @selected(($moduleTag ?? 'all') === $m)>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="entry_tag" class="form-select">
            <option value="">{{ __('All entry tags') }}</option>
            @foreach ($entryOptions ?? [] as $e)
              <option value="{{ $e }}" @selected(($entryTag ?? null) === $e)>{{ AccountingTags::entryLabel($e) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="all">{{ __('All Status') }}</option>
            @foreach (['posted' => __('Posted'), 'approved' => __('Approved'), 'draft' => __('Draft')] as $key => $label)
              <option value="{{ $key }}" @selected(($status ?? 'posted') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="bank_account_id" class="form-select">
            <option value="">{{ __('All Banks') }}</option>
            @foreach ($bankAccounts ?? [] as $b)
              <option value="{{ $b->id }}" @selected(($bankAccountId ?? null) == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search...') }}">
          </div>
        </div>
        <div class="col-md-12 d-flex justify-content-end gap-2 mt-2">
          <input type="hidden" name="revenue_category_id" value="{{ $revenueCategoryId }}">
          <input type="hidden" name="expense_category_id" value="{{ $expenseCategoryId }}">
          <button type="submit" class="btn btn-primary px-4">{{ __('Filter') }}</button>
          <a href="{{ route('account.day-book.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Revenues') }}</strong></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('Number') }}</th>
                  <th>{{ __('Tags') }}</th>
                  <th>{{ __('Category') }}</th>
                  <th>{{ __('Bank') }}</th>
                  <th class="text-end">{{ __('Amount') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($revenues ?? [] as $r)
                  <tr>
                    <td><code>{{ $r->revenue_number }}</code></td>
                    <td>
                      @if ($r->module_tag)
                        <span class="badge {{ AccountingTags::moduleBadgeClass($r->module_tag) }}">{{ $r->module_tag }}</span>
                      @endif
                      @if ($r->entry_tag)
                        <span class="badge bg-label-secondary">{{ AccountingTags::entryLabel($r->entry_tag) }}</span>
                      @endif
                    </td>
                    <td>
                      <div>{{ $r->category?->category_name ?? '—' }}</div>
                      <small class="text-muted">{{ \Illuminate\Support\Str::limit($r->description, 60) }}</small>
                    </td>
                    <td><span class="acc-name">{{ $r->bankAccount?->account_name ?? '—' }}</span></td>
                    <td class="text-end acc-credit">₹{{ number_format((float) ($r->amount ?? 0), 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No revenue found for this day.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Expenses') }}</strong></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('Number') }}</th>
                  <th>{{ __('Tags') }}</th>
                  <th>{{ __('Category') }}</th>
                  <th>{{ __('Bank') }}</th>
                  <th class="text-end">{{ __('Amount') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($expenses ?? [] as $e)
                  <tr>
                    <td><code>{{ $e->expense_number }}</code></td>
                    <td>
                      @if ($e->module_tag)
                        <span class="badge {{ AccountingTags::moduleBadgeClass($e->module_tag) }}">{{ $e->module_tag }}</span>
                      @endif
                      @if ($e->entry_tag)
                        <span class="badge bg-label-secondary">{{ AccountingTags::entryLabel($e->entry_tag) }}</span>
                      @endif
                    </td>
                    <td>
                      <div>{{ $e->category?->category_name ?? '—' }}</div>
                      <small class="text-muted">{{ \Illuminate\Support\Str::limit($e->description, 60) }}</small>
                    </td>
                    <td><span class="acc-name">{{ $e->bankAccount?->account_name ?? '—' }}</span></td>
                    <td class="text-end acc-debit">₹{{ number_format((float) ($e->amount ?? 0), 2) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No expense found for this day.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>{{ __('Bank & Cash Movements') }}</strong>
      <small class="text-muted">{{ __('Chit / Loan / FD cashbook (tagged)') }}</small>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>{{ __('Ref') }}</th>
              <th>{{ __('Tags') }}</th>
              <th>{{ __('Account') }}</th>
              <th>{{ __('Description') }}</th>
              <th>{{ __('Type') }}</th>
              <th class="text-end">{{ __('Amount') }}</th>
              <th class="text-end">{{ __('Balance') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($bankTransactions ?? [] as $tx)
              <tr>
                <td><code>{{ $tx->reference_number ?: '—' }}</code></td>
                <td>
                  @if ($tx->module_tag)
                    <span class="badge {{ AccountingTags::moduleBadgeClass($tx->module_tag) }}">{{ $tx->module_tag }}</span>
                  @endif
                  @if ($tx->entry_tag)
                    <span class="badge bg-label-secondary">{{ AccountingTags::entryLabel($tx->entry_tag) }}</span>
                  @endif
                </td>
                <td><span class="acc-name">{{ $tx->bankAccount?->account_name ?? '—' }}</span></td>
                <td>{{ $tx->description ?: '—' }}</td>
                <td>
                  <span class="badge bg-label-{{ $tx->transaction_type === 'credit' ? 'success' : 'danger' }}">
                    {{ strtoupper($tx->transaction_type) }}
                  </span>
                </td>
                <td class="text-end {{ $tx->transaction_type === 'credit' ? 'acc-credit' : 'acc-debit' }}">
                  ₹{{ number_format((float) $tx->amount, 2) }}
                </td>
                <td class="text-end">₹{{ number_format((float) $tx->running_balance, 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-4">{{ __('No bank/cash movements for this day.') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>{{ __('Dividend pool (memo)') }}</strong>
      <small class="text-muted">{{ __('Group dividend movements — does not change bank balance') }}</small>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>{{ __('Tags') }}</th>
              <th>{{ __('Group') }}</th>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Remarks') }}</th>
              <th class="text-end">{{ __('Amount') }}</th>
              <th class="text-end">{{ __('Balance after') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($dividends ?? [] as $d)
              <tr>
                <td>
                  <span class="badge {{ AccountingTags::moduleBadgeClass('CHIT') }}">CHIT</span>
                  <span class="badge bg-label-secondary">{{ AccountingTags::entryLabel('DIVIDEND') }}</span>
                </td>
                <td>{{ $d->group?->group_code ?? ('GRP-' . $d->group_id) }}</td>
                <td><span class="badge bg-label-{{ $d->entry_type_badge }}">{{ $d->entry_type_label }}</span></td>
                <td>{{ $d->remarks ?: '—' }}</td>
                <td class="text-end">₹{{ number_format((float) $d->amount, 2) }}</td>
                <td class="text-end">₹{{ number_format((float) $d->balance_after, 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">{{ __('No dividend pool entries for this day.') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
