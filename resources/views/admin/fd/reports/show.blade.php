@extends('layouts/layoutMaster')

@section('title', $title . ' - FD Reports')

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <a href="{{ route('fd.reports.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i></a>
      <h4 class="mb-0">{{ $title }}</h4>
    </div>
    <p class="text-muted mb-0">Filter, review and export Fixed Deposit report data.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <div class="dropdown">
      <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="ri-download-2-line me-1"></i> Export
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li>
          <a class="dropdown-item"
            href="{{ route('fd.reports.export', array_merge(['report' => $report], request()->except('format'), ['format' => 'pdf'])) }}">
            <i class="ri-file-pdf-2-line me-2 text-danger"></i> PDF
          </a>
        </li>
        <li>
          <a class="dropdown-item"
            href="{{ route('fd.reports.export', array_merge(['report' => $report], request()->except('format'), ['format' => 'xlsx'])) }}">
            <i class="ri-file-excel-2-line me-2 text-success"></i> Excel (.xlsx)
          </a>
        </li>
        <li>
          <a class="dropdown-item"
            href="{{ route('fd.reports.export', array_merge(['report' => $report], request()->except('format'), ['format' => 'csv'])) }}">
            <i class="ri-file-text-line me-2 text-primary"></i> CSV
          </a>
        </li>
      </ul>
    </div>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
      <i class="ri-printer-line me-1"></i> Print
    </button>
  </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">
  {{ session('success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">
  {{ session('error') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card mb-5">
  <div class="card-header">
    <h5 class="card-title mb-0">Filters</h5>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('fd.reports.show', $report) }}" class="row g-4">
      <div class="col-12">
        @include('partials.date-range-filter', [
          'fromId' => 'from_date',
          'toId' => 'to_date',
          'presetId' => 'fdReportDatePreset',
          'fromName' => 'from_date',
          'toName' => 'to_date',
          'fromValue' => request('from_date'),
          'toValue' => request('to_date'),
          'presetValue' => request('date_preset', 'all'),
          'autoSubmit' => true,
          'size' => 'sm',
          'compact' => false,
        ])
      </div>
      <div class="col-md-4 col-xl-3">
        <label class="form-label" for="scheme_id">Scheme</label>
        <select class="form-select" id="scheme_id" name="scheme_id">
          <option value="">All schemes</option>
          @foreach($schemes as $scheme)
            <option value="{{ $scheme->id }}" @selected((string) request('scheme_id', $schemeId ?? '') === (string) $scheme->id)>
              {{ $scheme->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4 col-xl-3">
        <label class="form-label" for="client_id">Customer</label>
        <select class="form-select" id="client_id" name="client_id">
          <option value="">All customers</option>
          @foreach($clients as $client)
            <option value="{{ $client->id }}" @selected((string) request('client_id', $clientId ?? '') === (string) $client->id)>
              {{ $client->client_name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4 col-xl-3">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">All statuses</option>
          @foreach(['active','matured','closed','premature_closed','renewed','cancelled'] as $st)
            <option value="{{ $st }}" @selected(request('status', $status ?? '') === $st)>
              {{ \Illuminate\Support\Str::headline($st) }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="ri-filter-3-line me-1"></i> Apply Filters</button>
        <a href="{{ route('fd.reports.show', $report) }}" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

@if(!empty($summary))
<div class="row g-4 mb-5">
  @foreach($summary as $key => $value)
    <div class="col-sm-6 col-lg-4">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ \Illuminate\Support\Str::headline($key) }}</small>
          <h4 class="mb-0 mt-1">
            @if(is_numeric($value))
              ₹{{ number_format((float) $value, 2) }}
            @else
              {{ $value }}
            @endif
          </h4>
        </div>
      </div>
    </div>
  @endforeach
</div>
@endif

<div class="card" id="reportPrintArea">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="card-title mb-0">{{ $title }}</h5>
    <span class="badge bg-label-primary">{{ number_format(count($rows)) }} rows</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          @foreach($headers as $header)
            <th>{{ $header }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          <tr>
            @foreach($row as $cell)
              <td class="text-nowrap">{{ filled($cell) || $cell === 0 || $cell === '0' ? $cell : '—' }}</td>
            @endforeach
          </tr>
        @empty
          <tr>
            <td colspan="{{ max(count($headers), 1) }}" class="text-center py-5">
              <i class="ri-file-search-line text-muted d-block mb-2" style="font-size: 2.5rem"></i>
              <h6 class="text-muted">No records match the selected filters.</h6>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
