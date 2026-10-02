{{-- Row/card action menu for a Card to Cash lead. Shared by the list and card views. --}}
<div class="dropdown">
  <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="ri-more-2-line"></i>
  </button>
  <div class="dropdown-menu dropdown-menu-end">
    <a class="dropdown-item text-primary" href="{{ route('card-cash.processing.process', $lead->id) }}">
      <i class="ri-settings-4-line me-2"></i> Process Lead
    </a>
    <a class="dropdown-item" href="javascript:void(0);" onclick="openLeadModal({{ $lead->id }})">
      <i class="ri-eye-line me-2"></i> View Details
    </a>
    <a class="dropdown-item text-secondary" href="javascript:void(0);" onclick="openEditLeadModal({{ $lead->id }}, '{{ $lead->lead_number }}', '{{ $lead->requested_amount }}', '{{ addslashes($lead->card_name) }}', '{{ addslashes($lead->csr_bank_name) }}', '{{ addslashes($lead->remarks ?? '') }}', '{{ addslashes($lead->card_holder_phone ?? '') }}', '{{ addslashes($lead->last_four ?? '') }}', '{{ $lead->due_date?->format('Y-m-d') }}')">
      <i class="ri-edit-line me-2"></i> Edit Amount / Details
    </a>
    <div class="dropdown-divider"></div>
    <form action="{{ route('card-cash.leads.destroy', $lead->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this lead?');">
      @csrf
      @method('DELETE')
      <button type="submit" class="dropdown-item text-danger">
        <i class="ri-delete-bin-7-line me-2"></i> Delete
      </button>
    </form>
  </div>
</div>
