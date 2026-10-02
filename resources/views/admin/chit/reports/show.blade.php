@extends('layouts/layoutMaster')

@section('title', $definition['title'] . ' - Chit Reports')

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <a href="{{ route('chit.reports.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i></a>
      <h4 class="mb-0">{{ $definition['title'] }}</h4>
    </div>
    <p class="text-muted mb-0">{{ $definition['description'] }}</p>
  </div>
  <div class="dropdown">
    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="ri-download-2-line me-1"></i> Export
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li>
        <a class="dropdown-item"
          href="{{ route('chit.reports.export', array_merge(['report' => $report], request()->except('format'), ['format' => 'xlsx'])) }}">
          <i class="ri-file-excel-2-line me-2 text-success"></i> Excel (.xlsx)
        </a>
      </li>
      <li>
        <a class="dropdown-item"
          href="{{ route('chit.reports.export', array_merge(['report' => $report], request()->except('format'), ['format' => 'csv'])) }}">
          <i class="ri-file-text-line me-2 text-primary"></i> CSV (.csv)
        </a>
      </li>
      <li>
        <a class="dropdown-item"
          href="{{ route('chit.reports.export', array_merge(['report' => $report], request()->except('format'), ['format' => 'pdf'])) }}">
          <i class="ri-file-pdf-2-line me-2 text-danger"></i> PDF (.pdf)
        </a>
      </li>
    </ul>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-5">
  @foreach ($reports as $key => $item)
    <a href="{{ route('chit.reports.show', $key) }}"
      class="btn btn-sm {{ $key === $report ? 'btn-primary' : 'btn-outline-secondary' }}">
      <i class="ri {{ $item['icon'] }} me-1"></i>{{ $item['title'] }}
    </a>
  @endforeach
</div>

<div class="card mb-5">
  <div class="card-header">
    <h5 class="card-title mb-0">Filters</h5>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('chit.reports.show', $report) }}" class="row g-4">
      <div class="col-md-4 col-xl-3">
        <label class="form-label" for="reportSearch">Search</label>
        <input type="search" class="form-control" id="reportSearch" name="search" value="{{ request('search') }}"
          placeholder="Name, phone, code or reference">
      </div>

      <div class="col-md-4 col-xl-3">
        <label class="form-label" for="reportScheme">Scheme</label>
        <select class="form-select" id="reportScheme" name="scheme_id">
          <option value="">All schemes</option>
          @foreach ($schemes as $scheme)
            <option value="{{ $scheme->id }}" @selected((string) request('scheme_id') === (string) $scheme->id)>
              {{ $scheme->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-4 col-xl-3">
        <label class="form-label" for="reportGroup">Group</label>
        <select class="form-select" id="reportGroup" name="group_id">
          <option value="">All groups</option>
          @foreach ($groups as $group)
            <option value="{{ $group->id }}" @selected((string) request('group_id') === (string) $group->id)>
              {{ $group->group_code }}
            </option>
          @endforeach
        </select>
      </div>

      @if (count($statusOptions))
        <div class="col-md-4 col-xl-3">
          <label class="form-label" for="reportStatus">Status</label>
          <select class="form-select" id="reportStatus" name="status">
            <option value="">All statuses</option>
            @foreach ($statusOptions as $status)
              <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
            @endforeach
          </select>
        </div>
      @endif

      @if (in_array($report, ['collections', 'settlements'], true))
        <div class="col-md-4 col-xl-3">
          <label class="form-label" for="reportPaymentMode">Payment Mode</label>
          <select class="form-select" id="reportPaymentMode" name="payment_mode">
            <option value="">All payment modes</option>
            @foreach ($paymentModes as $mode)
              <option value="{{ $mode }}" @selected(request('payment_mode') === $mode)>{{ \Illuminate\Support\Str::headline($mode) }}</option>
            @endforeach
          </select>
        </div>
      @endif

      @if ($report === 'collections')
        <div class="col-md-4 col-xl-3">
          <label class="form-label" for="reportCollector">Collector</label>
          <select class="form-select" id="reportCollector" name="collector_id">
            <option value="">All collectors</option>
            @foreach ($collectors as $collector)
              <option value="{{ $collector->id }}" @selected((string) request('collector_id') === (string) $collector->id)>
                {{ $collector->name }}
              </option>
            @endforeach
          </select>
        </div>
      @endif

      <div class="col-12">
        @include('partials.date-range-filter', [
          'fromId' => 'reportDateFrom',
          'toId' => 'reportDateTo',
          'presetId' => 'chitReportDatePreset',
          'fromName' => 'date_from',
          'toName' => 'date_to',
          'fromValue' => request('date_from'),
          'toValue' => request('date_to'),
          'presetValue' => request('date_preset', 'all'),
          'autoSubmit' => true,
          'size' => 'sm',
          'compact' => false,
        ])
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line me-1"></i> Apply Filters</button>
        <a href="{{ route('chit.reports.show', $report) }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-4 mb-5">
  @foreach ($summary as $item)
    <div class="col-sm-6 col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ $item['label'] }}</small>
          <h4 class="mb-0 mt-1">{{ $item['value'] }}</h4>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="card-title mb-0">{{ $definition['title'] }}</h5>
    <span class="badge bg-label-primary">{{ number_format($records->total()) }} records</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          @foreach ($columns as $label)
            <th>{{ $label }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse ($tableRows as $row)
          <tr>
            @foreach (array_keys($columns) as $key)
              <td class="text-nowrap">{{ filled($row[$key] ?? null) ? $row[$key] : '—' }}</td>
            @endforeach
          </tr>
        @empty
          <tr>
            <td colspan="{{ count($columns) }}" class="text-center py-5">
              <i class="ri-file-search-line text-muted d-block mb-2" style="font-size: 2.5rem"></i>
              <h6 class="text-muted">No records match the selected filters.</h6>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($records->hasPages())
    <div class="card-footer">
      {{ $records->links() }}
    </div>
  @endif
</div>
@endsection
