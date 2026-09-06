{{-- $exportRoute: route name, $query: array of query params (e.g. as_of_date) --}}
@php
  $q = $query ?? [];
@endphp
<div class="btn-group">
  <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">{{ __('Export') }}</button>
  <ul class="dropdown-menu dropdown-menu-end">
    @if (!isset($disablePdf) || !$disablePdf)
      <li><a class="dropdown-item" href="{{ route($exportRoute, array_merge($q, ['format' => 'pdf'])) }}">PDF</a></li>
    @endif
    <li><a class="dropdown-item" href="{{ route($exportRoute, array_merge($q, ['format' => 'xlsx'])) }}">Excel</a></li>
    <li><a class="dropdown-item" href="{{ route($exportRoute, array_merge($q, ['format' => 'csv'])) }}">CSV</a></li>
  </ul>
</div>
