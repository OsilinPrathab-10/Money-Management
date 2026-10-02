@php
  $tab = $tab ?? 'loan';
  $paginator = $paginator ?? null;
  $perPage = $perPage ?? 25;
  $perPageOptions = $perPageOptions ?? [25, 75, 100, 250, 500];
@endphp
@if ($paginator)
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <form method="get" action="{{ route('account.index') }}" class="d-flex align-items-center gap-2">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <label class="mb-0 small text-muted">{{ __('Show') }}</label>
      <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
        @foreach ($perPageOptions as $n)
          <option value="{{ $n }}" @selected((int) $perPage === (int) $n)>{{ $n }}</option>
        @endforeach
      </select>
      <span class="small text-muted">{{ __('entries') }}</span>
    </form>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <small class="text-muted">
        {{ __('Showing') }}
        {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}
        {{ __('of') }}
        {{ number_format($paginator->total()) }}
      </small>
      @if ($paginator->hasPages())
        <div>{{ $paginator->onEachSide(1)->links() }}</div>
      @endif
    </div>
  </div>
@endif
