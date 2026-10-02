@extends('layouts/layoutMaster')

@section('title', __('Edit bank account'))

@section('content')
<div class="account-module">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Edit bank account') }}</h4>
      <p class="text-muted mb-0"><code>{{ $bankaccount->account_number }}</code></p>
    </div>
    <a href="{{ route('account.bank-accounts.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back to list') }}</a>
  </div>

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="card">
    <div class="card-body">
      <form method="post" action="{{ route('account.bank-accounts.update', $bankaccount) }}" enctype="multipart/form-data" class="row g-3">
        @csrf
        @method('PUT')
        <div class="col-md-2">
          <label class="form-label">{{ __('Account #') }}</label>
          <input type="text" name="account_number" value="{{ old('account_number', $bankaccount->account_number) }}" class="form-control @error('account_number') is-invalid @enderror" required>
          @error('account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Name') }}</label>
          <input type="text" name="account_name" value="{{ old('account_name', $bankaccount->account_name) }}" class="form-control @error('account_name') is-invalid @enderror" required>
          @error('account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Bank') }}</label>
          <input type="text" name="bank_name" value="{{ old('bank_name', $bankaccount->bank_name) }}" class="form-control @error('bank_name') is-invalid @enderror" required>
          @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Branch') }}</label>
          <input type="text" name="branch_name" value="{{ old('branch_name', $bankaccount->branch_name) }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Type') }}</label>
          <select name="account_type" class="form-select" required>
            @foreach (['current', 'savings', 'cash', 'other'] as $t)
              <option value="{{ $t }}" @selected(old('account_type', $bankaccount->account_type) === $t)>{{ ucfirst($t) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Opening') }}</label>
          <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $bankaccount->opening_balance) }}" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Current balance') }}</label>
          <input type="number" step="0.01" name="current_balance" value="{{ old('current_balance', $bankaccount->current_balance) }}" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('IBAN') }}</label>
          <input type="text" name="iban" value="{{ old('iban', $bankaccount->iban) }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('SWIFT') }}</label>
          <input type="text" name="swift_code" value="{{ old('swift_code', $bankaccount->swift_code) }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Routing') }}</label>
          <input type="text" name="routing_number" value="{{ old('routing_number', $bankaccount->routing_number) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('IFSC Code') }}</label>
          <input type="text" name="ifsc_code" value="{{ old('ifsc_code', $bankaccount->ifsc_code) }}" class="form-control" placeholder="e.g. SBIN0001234" maxlength="20">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Payment gateway') }}</label>
          <input type="text" name="payment_gateway" value="{{ old('payment_gateway', $bankaccount->payment_gateway) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('UPI ID') }}</label>
          <input type="text" name="upi_id" value="{{ old('upi_id', $bankaccount->upi_id) }}" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('QR Code (for collection)') }}</label>
          <input type="file" name="qr_code" class="form-control" accept="image/*">
          @if($bankaccount->qr_code)
            <div class="mt-2">
              <a href="{{ asset('storage/' . $bankaccount->qr_code) }}" target="_blank" class="btn btn-sm btn-outline-info">
                <i class="ri-eye-line me-1"></i>{{ __('View Current QR') }}
              </a>
            </div>
          @endif
        </div>
        <div class="col-md-2">
          <label class="form-label d-block">{{ __('Active') }}</label>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ba_edit_active" @checked(old('is_active', $bankaccount->is_active))>
            <label class="form-check-label" for="ba_edit_active">{{ __('Yes') }}</label>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
