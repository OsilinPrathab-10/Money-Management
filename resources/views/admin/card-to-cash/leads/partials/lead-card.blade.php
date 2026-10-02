{{-- One Card to Cash lead rendered as a tile for the card view. --}}
<div class="col-12 col-md-6 col-xl-4">
  <div class="card h-100 border shadow-none lead-tile">
    <div class="card-header d-flex justify-content-between align-items-start gap-2 pb-3">
      <div class="min-w-0">
        <a href="javascript:void(0);" onclick="openLeadModal({{ $lead->id }})" class="fw-bold text-primary d-block text-truncate">
          {{ $lead->lead_number }}
        </a>
        <small class="text-muted">{{ $lead->lead_date ? $lead->lead_date->format('d M Y, h:i A') : '-' }}</small>
      </div>
      <div class="d-flex align-items-center gap-1 flex-shrink-0">
        <span class="badge {{ $lead->status_badge }}">{{ $lead->status_label }}</span>
        @include('admin.card-to-cash.leads.partials.lead-actions', ['lead' => $lead])
      </div>
    </div>

    <div class="card-body pt-0">
      {{-- Customer --}}
      <div class="d-flex align-items-center mb-3">
        <div class="avatar avatar-sm me-2 flex-shrink-0">
          <span class="avatar-initial rounded-circle bg-label-primary"><i class="ri-user-line"></i></span>
        </div>
        <div class="min-w-0">
          <span class="fw-medium text-heading d-block text-truncate">{{ optional($lead->customer)->customer_name ?? 'N/A' }}</span>
          <small class="text-muted font-monospace">{{ $lead->phone_number }}</small>
          @if (optional($lead->customer)->customer_number)
            <small class="text-muted"> &middot; {{ $lead->customer->customer_number }}</small>
          @endif
        </div>
      </div>

      {{-- Card & bank --}}
      <div class="p-3 bg-light rounded-3 mb-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
          <span class="fw-medium text-truncate">{{ $lead->card_name }}</span>
          @if ($lead->transaction_type === 'bill_payment')
            <span class="badge bg-label-primary flex-shrink-0"><i class="ri-bank-card-line me-1"></i> Bill Payment</span>
          @else
            <span class="badge bg-label-info flex-shrink-0"><i class="ri-swap-box-line me-1"></i> Swipe</span>
          @endif
        </div>
        <small class="text-muted d-block">{{ $lead->csr_bank_name }}</small>
        @if ($lead->masked_card_number)
          <span class="font-monospace small text-primary d-block mt-1">{{ $lead->masked_card_number }}</span>
        @endif
        @if ($lead->card_holder_phone)
          <small class="text-muted d-block mt-1" title="Cardholder Mobile">
            <i class="ri-phone-line me-1"></i>Holder: <span class="font-monospace">{{ $lead->card_holder_phone }}</span>
          </small>
        @endif
      </div>

      {{-- Amount & staff --}}
      <div class="d-flex justify-content-between align-items-end gap-2">
        <div class="min-w-0">
          <small class="text-muted d-block">Requested Amount</small>
          <span class="fw-bold fs-5 text-heading">₹{{ number_format($lead->requested_amount, 2) }}</span>
          @if ($lead->due_date)
            <small class="text-muted d-block mt-1">Due: {{ $lead->due_date->format('d M Y') }}</small>
          @endif
        </div>
        <div class="text-end min-w-0">
          <small class="text-muted d-block">Assigned</small>
          <small class="text-truncate d-block">{{ optional($lead->assignedStaff)->name ?? 'Unassigned' }}</small>
        </div>
      </div>
    </div>

    <div class="card-footer pt-0 border-0">
      <a href="{{ route('card-cash.processing.process', $lead->id) }}" class="btn btn-sm btn-label-primary w-100">
        <i class="ri-settings-4-line me-1"></i> Process Lead
      </a>
    </div>
  </div>
</div>
