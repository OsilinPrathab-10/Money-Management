@extends('layouts/layoutMaster')

@section('title', $expense->expense_number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ $expense->expense_number }}</h4>
      <p class="text-muted mb-0">{{ __('Expense detail') }}</p>
    </div>
    <a href="{{ route('account.expenses.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back') }}</a>
  </div>

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card mb-4">
    <div class="card-body row g-3">
      <div class="col-md-3"><span class="text-muted">{{ __('Date') }}</span><br><strong>{{ $expense->expense_date?->format('Y-m-d') }}</strong></div>
      <div class="col-md-3"><span class="text-muted">{{ __('Amount') }}</span><br><strong>₹{{ number_format((float) $expense->amount, 2) }}</strong></div>
      <div class="col-md-3"><span class="text-muted">{{ __('Status') }}</span><br><span class="badge bg-label-info">{{ $expense->status }}</span></div>
      <div class="col-md-3"><span class="text-muted">{{ __('Reference') }}</span><br>{{ $expense->reference_number ?? '—' }}</div>
      <div class="col-md-6"><span class="text-muted">{{ __('Category') }}</span><br>{{ $expense->category?->category_name ?? '—' }}</div>
      <div class="col-md-6"><span class="text-muted">{{ __('Bank') }}</span><br>{{ $expense->bankAccount?->account_name ?? '—' }}</div>
      <div class="col-md-6"><span class="text-muted">{{ __('Expense GL') }}</span><br>{{ $expense->chartOfAccount?->account_code }} — {{ $expense->chartOfAccount?->account_name }}</div>
      <div class="col-12"><span class="text-muted">{{ __('Description') }}</span><br>{{ $expense->description ?? '—' }}</div>
    </div>
  </div>

  @if ($expense->status === 'draft')
    <div class="card">
      <div class="card-header"><strong>{{ __('Edit draft') }}</strong></div>
      <div class="card-body">
        <form method="post" action="{{ route('account.expenses.update', $expense) }}" class="row g-3">
          @csrf
          @method('PUT')
          <div class="col-md-2">
            <label class="form-label">{{ __('Date') }}</label>
            <input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ __('Category') }}</label>
            <select name="category_id" class="form-select" required>
              @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected($expense->category_id == $c->id)>{{ $c->category_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ __('Bank') }}</label>
            <select name="bank_account_id" class="form-select" required>
              @foreach ($bankAccounts as $b)
                <option value="{{ $b->id }}" @selected($expense->bank_account_id == $b->id)>{{ $b->account_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('GL') }}</label>
            <select name="chart_of_account_id" class="form-select" required>
              @foreach ($chartOfAccounts as $g)
                <option value="{{ $g->id }}" @selected($expense->chart_of_account_id == $g->id)>{{ $g->account_code }} — {{ $g->account_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ __('Amount') }}</label>
            <input type="number" step="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Reference') }}</label>
            <input type="text" name="reference_number" value="{{ old('reference_number', $expense->reference_number) }}" class="form-control">
          </div>
          <div class="col-md-8">
            <label class="form-label">{{ __('Description') }}</label>
            <input type="text" name="description" value="{{ old('description', $expense->description) }}" class="form-control">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  <div class="d-flex gap-2 flex-wrap">
    @if ($expense->status === 'draft')
      <form action="{{ route('account.expenses.approve', $expense) }}" method="post">@csrf<button type="submit" class="btn btn-primary">{{ __('Approve') }}</button></form>
      <form action="{{ route('account.expenses.destroy', $expense) }}" method="post" onsubmit="return confirm(@json(__('Delete?')));">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger">{{ __('Delete') }}</button></form>
    @elseif ($expense->status === 'approved')
      <form action="{{ route('account.expenses.post', $expense) }}" method="post">@csrf<button type="submit" class="btn btn-success">{{ __('Post to GL') }}</button></form>
    @endif
  </div>
</div>
@endsection
