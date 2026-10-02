{{-- Shared Accounting page header — matches Client Ledgers Management style --}}
@include('admin.account.shared.styles')
@php
  $title = $title ?? '';
  $subtitle = $subtitle ?? null;
  $breadcrumb = $breadcrumb ?? $title;
  $icon = $icon ?? null; // kept for BC; unused in flat header
@endphp
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="mb-0 fw-bold text-primary">{{ $title }}</h4>
    @if ($subtitle)
      <p class="text-muted mb-0 small mt-1">{{ $subtitle }}</p>
    @endif
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    @isset($toolbar)
      {!! $toolbar !!}
    @endisset
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('account.index') }}">{{ __('Accounting') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb }}</li>
      </ol>
    </nav>
  </div>
</div>
