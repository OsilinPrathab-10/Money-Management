@extends('layouts/layoutMaster')

@section('title', __('Account') . ' — ' . $chartofaccount->account_name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1"><code>{{ $chartofaccount->account_code }}</code> — {{ $chartofaccount->account_name }}</h4>
      <p class="text-muted mb-0">{{ $chartofaccount->description ?? __('No description') }}</p>
    </div>
    <a href="{{ route('account.chart-of-accounts.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back to list') }}</a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Balances') }}</strong></div>
        <div class="card-body">
          <p class="mb-1"><span class="text-muted">{{ __('Normal balance') }}:</span> <strong>{{ $chartofaccount->normal_balance }}</strong></p>
          <p class="mb-1"><span class="text-muted">{{ __('Opening') }}:</span> ₹{{ number_format((float) $chartofaccount->opening_balance, 2) }}</p>
          <p class="mb-1"><span class="text-muted">{{ __('Stored current') }}:</span> ₹{{ number_format((float) $chartofaccount->current_balance, 2) }}</p>
          <hr>
          <p class="mb-1 text-muted small">{{ __('From journal lines') }}</p>
          <p class="mb-0"><span class="text-muted">{{ __('Debits') }}:</span> ₹{{ number_format((float) $totalDebits, 2) }}</p>
          <p class="mb-0"><span class="text-muted">{{ __('Credits') }}:</span> ₹{{ number_format((float) $totalCredits, 2) }}</p>
          <p class="mb-0 mt-2"><span class="text-muted">{{ __('Calculated balance') }}:</span>
            <strong>₹{{ number_format((float) $calculatedBalance, 2) }}</strong>
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Details') }}</strong></div>
        <div class="card-body">
          <p class="mb-1"><span class="text-muted">{{ __('Account type') }}:</span> {{ $chartofaccount->account_type?->name ?? '—' }}</p>
          <p class="mb-1"><span class="text-muted">{{ __('Parent') }}:</span> {{ $chartofaccount->parent_account?->account_name ?? '—' }}</p>
          <p class="mb-0"><span class="text-muted">{{ __('System account') }}:</span> {{ $chartofaccount->is_system_account ? __('Yes') : __('No') }}</p>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>{{ __('Recent journal lines') }}</strong></div>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Journal') }}</th>
            <th class="text-end">{{ __('Debit') }}</th>
            <th class="text-end">{{ __('Credit') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($history as $line)
            <tr>
              <td>{{ $line->created_at?->format('Y-m-d H:i') }}</td>
              <td>{{ $line->journalEntry?->journal_number ?? ('#' . $line->journal_entry_id) }}</td>
              <td class="text-end">₹{{ number_format((float) $line->debit_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $line->credit_amount, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No journal activity for this account yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($history->hasPages())
      <div class="card-footer">{{ $history->links() }}</div>
    @endif
  </div>
</div>
@endsection
