@php
    $viewUrl = $viewUrl ?? null;
    $viewModalTarget = $viewModalTarget ?? null;
    $viewModalClass = $viewModalClass ?? '';
    $viewModalData = $viewModalData ?? [];
    $editUrl = $editUrl ?? null;
    $editModalTarget = $editModalTarget ?? null;
    $editModalClass = $editModalClass ?? '';
    $editModalData = $editModalData ?? [];
    $deleteRoute = $deleteRoute ?? null;
    $approveUrl = $approveUrl ?? null;
    $postUrl = $postUrl ?? null;
    $deleteConfirm = $deleteConfirm ?? null;
    $confirm = $deleteConfirm ?? __('Are you sure you want to delete this?');
@endphp
<div class="d-inline-flex align-items-center flex-wrap gap-1 account-table-actions">
  @if ($viewModalTarget)
    <button
      type="button"
      class="btn btn-sm btn-icon btn-text-secondary rounded-pill {{ $viewModalClass }}"
      title="{{ __('View Details') }}"
      aria-label="{{ __('View') }}"
      data-bs-toggle="modal"
      data-bs-target="{{ $viewModalTarget }}"
      @foreach ($viewModalData as $attr => $value)
        {{ $attr }}="{{ $value }}"
      @endforeach
    >
      <i class="icon-base ri ri-eye-line icon-18px text-info"></i>
    </button>
  @elseif ($viewUrl)
    <a href="{{ $viewUrl }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="{{ __('View') }}" aria-label="{{ __('View') }}">
      <i class="icon-base ri ri-eye-line icon-18px text-info"></i>
    </a>
  @endif
  @if ($editModalTarget)
    <button
      type="button"
      class="btn btn-sm btn-icon btn-text-secondary rounded-pill {{ $editModalClass }}"
      title="{{ __('Edit') }}"
      aria-label="{{ __('Edit') }}"
      data-bs-toggle="modal"
      data-bs-target="{{ $editModalTarget }}"
      @foreach ($editModalData as $attr => $value)
        {{ $attr }}="{{ $value }}"
      @endforeach
    >
      <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
    </button>
  @elseif ($editUrl)
    <a href="{{ $editUrl }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
      <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
    </a>
  @endif
  @if ($approveUrl)
    <form action="{{ $approveUrl }}" method="post" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-sm btn-icon btn-text-secondary rounded-pill border-0 bg-transparent" title="{{ __('Approve') }}" aria-label="{{ __('Approve') }}">
        <i class="icon-base ri ri-checkbox-circle-line icon-18px text-success"></i>
      </button>
    </form>
  @endif
  @if ($postUrl)
    <form action="{{ $postUrl }}" method="post" class="d-inline">
      @csrf
      <button type="submit" class="btn btn-sm btn-icon btn-text-secondary rounded-pill border-0 bg-transparent" title="{{ __('Post') }}" aria-label="{{ __('Post') }}">
        <i class="icon-base ri ri-send-plane-line icon-18px text-primary"></i>
      </button>
    </form>
  @endif
  @if ($deleteRoute)
    @php $formId = 'del-form-' . uniqid(); @endphp
    <form id="{{ $formId }}" action="{{ $deleteRoute }}" method="post" class="d-inline">
      @csrf
      @method('DELETE')
      <button type="button"
              class="btn btn-sm btn-icon btn-text-secondary rounded-pill border-0 bg-transparent swal-delete-btn"
              title="{{ __('Delete') }}"
              aria-label="{{ __('Delete') }}"
              data-form-id="{{ $formId }}"
              data-confirm="{{ $confirm }}">
        <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
      </button>
    </form>
  @endif
</div>
