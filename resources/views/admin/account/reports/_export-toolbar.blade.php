{{-- $exportRoute: route name, $query: array of query params (e.g. as_of_date) --}}
@php
  $q = $query ?? [];
@endphp
<div class="btn-group">
  <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle d-inline-flex align-items-center gap-1" data-bs-toggle="dropdown">
    <i class="ri-download-2-line me-1"></i> {{ __('Export') }}
  </button>
  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
    @if (!isset($disablePdf) || !$disablePdf)
      <li>
        <a class="dropdown-item d-flex align-items-center py-2" href="{{ route($exportRoute, array_merge($q, ['format' => 'pdf'])) }}">
          <i class="ri-file-pdf-line me-2 text-danger ri-18px"></i> {{ __('PDF Format (.pdf)') }}
        </a>
      </li>
    @endif
    <li>
      <a class="dropdown-item d-flex align-items-center py-2" href="{{ route($exportRoute, array_merge($q, ['format' => 'xlsx'])) }}">
        <i class="ri-file-excel-2-line me-2 text-success ri-18px"></i> {{ __('Excel Format (.xlsx)') }}
      </a>
    </li>
    <li>
      <a class="dropdown-item d-flex align-items-center py-2" href="{{ route($exportRoute, array_merge($q, ['format' => 'csv'])) }}">
        <i class="ri-file-text-line me-2 text-info ri-18px"></i> {{ __('CSV Format (.csv)') }}
      </a>
    </li>
  </ul>
</div>
