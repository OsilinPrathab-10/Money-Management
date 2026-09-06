{{-- Shared Accounting page header (title + subtitle + icon) --}}
@php
  $title = $title ?? '';
  $subtitle = $subtitle ?? null;
  $icon = $icon ?? 'ri-book-2-line';
@endphp
<div class="account-page-hero card border-0 shadow-sm mb-4 overflow-hidden">
  <div class="card-body py-4 px-4 px-md-5">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
      <div class="d-flex gap-3 align-items-start">
        <div class="account-page-hero-icon flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center">
          <i class="icon-base ri {{ $icon }} ri-24px text-primary"></i>
        </div>
        <div>
          <h4 class="mb-1 fw-semibold">{{ $title }}</h4>
          @if ($subtitle)
            <p class="text-muted mb-0 small">{{ $subtitle }}</p>
          @endif
        </div>
      </div>
      @isset($toolbar)
        <div class="d-flex flex-wrap align-items-center gap-2">{!! $toolbar !!}</div>
      @endisset
    </div>
  </div>
</div>
