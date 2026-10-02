{{-- Clear settlement fields: Name, Bank Name, Card Number, Due Date, Amount, Settlement Details. --}}
@php
  $rows = $lead->settlementDisplay($return ?? $lead->returnSettlement ?? null);
@endphp
<div class="{{ $wrapperClass ?? 'p-3 bg-light rounded-3' }}">
  @if (!empty($title))
    <h6 class="fw-bold {{ $titleClass ?? 'text-dark' }} mb-3">
      <i class="ri-file-list-3-line me-1"></i> {{ $title }}
    </h6>
  @endif
  <div class="row g-3">
    <div class="col-sm-6 col-lg-4">
      <span class="text-muted small d-block">Name</span>
      <p class="fw-semibold text-heading mb-0">{{ $rows['name'] }}</p>
    </div>
    <div class="col-sm-6 col-lg-4">
      <span class="text-muted small d-block">Bank Name</span>
      <p class="fw-semibold text-heading mb-0">{{ $rows['bank_name'] }}</p>
    </div>
    <div class="col-sm-6 col-lg-4">
      <span class="text-muted small d-block">Card Number</span>
      <p class="fw-semibold font-monospace text-heading mb-0">{{ $rows['card_number'] }}</p>
    </div>
    <div class="col-sm-6 col-lg-4">
      <span class="text-muted small d-block">Due Date</span>
      <p class="fw-semibold text-heading mb-0">{{ $rows['due_date'] }}</p>
    </div>
    <div class="col-sm-6 col-lg-4">
      <span class="text-muted small d-block">Amount</span>
      <p class="fw-bold text-success fs-6 mb-0">{{ $rows['amount'] }}</p>
    </div>
    <div class="col-sm-6 col-lg-4">
      <span class="text-muted small d-block">Settlement Details</span>
      <p class="fw-semibold text-primary mb-0">{{ $rows['settlement_details'] }}</p>
    </div>
  </div>
</div>
