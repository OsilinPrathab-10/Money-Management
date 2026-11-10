@extends('layouts/layoutMaster')

@section('title', __('CIBIL Report'))

@php
  $scoreColor = function ($score) {
      if ($score === null) return 'secondary';
      $s = (int) $score;
      if ($s >= 750) return 'success';
      if ($s >= 700) return 'info';
      if ($s >= 650) return 'warning';
      return 'danger';
  };
  $ringPct = function ($score) {
      if ($score === null) return 0;
      return max(0, min(100, ((int) $score - 300) / 6));
  };
@endphp

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h4 class="mb-1">{{ __('CIBIL Report') }}</h4>
      <p class="text-muted mb-0">
        API Hub <code>{{ $config['path'] }}</code>
        · {{ $config['mock'] ? __('Test mode (mock)') : __('Live bureau') }}
      </p>
    </div>
    <ul class="nav nav-pills">
      <li class="nav-item">
        <a class="nav-link @if($tab === 'new') active @endif" href="{{ route('cibil-report.index') }}">{{ __('New report') }}</a>
      </li>
      <li class="nav-item">
        <a class="nav-link @if($tab === 'history') active @endif" href="{{ route('cibil-report.index', ['tab' => 'history']) }}">{{ __('History') }}</a>
      </li>
    </ul>
  </div>

  <div class="row g-3 mb-4">
    @foreach([
      ['label' => __('Total reports'), 'value' => $stats['total']],
      ['label' => __('This month'), 'value' => $stats['this_month']],
      ['label' => __('Linked to client'), 'value' => $stats['linked']],
      ['label' => __('PDFs saved'), 'value' => $stats['pdfs_saved']],
      ['label' => __('Avg. score'), 'value' => $stats['avg_score'] ?: '—'],
    ] as $card)
      <div class="col-6 col-lg">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body py-3">
            <small class="text-muted text-uppercase">{{ $card['label'] }}</small>
            <h4 class="mb-0 mt-1">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</h4>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  @if($tab === 'new')
    <div class="card">
      <div class="card-header"><h5 class="mb-0">{{ __('Fetch bureau report') }}</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('cibil-report.store') }}" class="row g-3">
          @csrf
          <div class="col-md-6">
            <label class="form-label">{{ __('Client (optional)') }}</label>
            <select name="client_id" id="cibilClientSelect" class="form-select">
              <option value="">{{ __('— Manual entry —') }}</option>
              @foreach($clients as $c)
                <option value="{{ $c['id'] }}" data-name="{{ $c['name'] }}" data-phone="{{ $c['phone'] }}" data-pan="{{ $c['pan'] }}" data-gender="{{ $c['gender'] }}"
                  @selected(request('client_id') == $c['id'])>{{ $c['name'] }} @if($c['pan']) ({{ $c['pan'] }}) @endif</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('PAN') }} *</label>
            <input type="text" name="pan" id="cibilPan" class="form-control text-uppercase" maxlength="10" value="{{ old('pan') }}" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Full name (as on PAN)') }} *</label>
            <input type="text" name="name" id="cibilName" class="form-control" value="{{ old('name') }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Mobile') }} *</label>
            <input type="text" name="mobile" id="cibilMobile" class="form-control" maxlength="10" value="{{ old('mobile') }}" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Gender') }} *</label>
            <select name="gender" id="cibilGender" class="form-select" required>
              <option value="male" @selected(old('gender') === 'male')>{{ __('Male') }}</option>
              <option value="female" @selected(old('gender') === 'female')>{{ __('Female') }}</option>
              <option value="other" @selected(old('gender', 'other') === 'other')>{{ __('Other') }}</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="consent" id="cibilConsent" value="1" required @checked(old('consent'))>
              <label class="form-check-label" for="cibilConsent">{{ __('Customer has consented to the bureau pull.') }}</label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="ri-search-eye-line me-1"></i>{{ __('Pull CIBIL report') }}
            </button>
          </div>
        </form>
      </div>
    </div>
    <script>
      document.getElementById('cibilClientSelect')?.addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        if (!opt || !opt.value) return;
        if (opt.dataset.name) document.getElementById('cibilName').value = opt.dataset.name;
        if (opt.dataset.phone) document.getElementById('cibilMobile').value = (opt.dataset.phone || '').replace(/\D/g,'').slice(-10);
        if (opt.dataset.pan) document.getElementById('cibilPan').value = opt.dataset.pan;
        if (opt.dataset.gender) {
          const g = (opt.dataset.gender || '').toLowerCase();
          if (['male','female','other'].includes(g)) document.getElementById('cibilGender').value = g;
        }
      });
      document.getElementById('cibilClientSelect')?.dispatchEvent(new Event('change'));
    </script>
  @else
    <div class="card mb-3">
      <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="tab" value="history">
          <div class="col-md-3"><input type="search" name="search" class="form-control" placeholder="{{ __('Search name, PAN, mobile') }}" value="{{ request('search') }}"></div>
          <div class="col-md-2">
            <select name="status" class="form-select">
              <option value="">{{ __('All status') }}</option>
              <option value="success" @selected(request('status')==='success')>{{ __('Success') }}</option>
              <option value="failed" @selected(request('status')==='failed')>{{ __('Failed') }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select name="linked" class="form-select">
              <option value="">{{ __('All links') }}</option>
              <option value="linked" @selected(request('linked')==='linked')>{{ __('Linked') }}</option>
              <option value="unlinked" @selected(request('linked')==='unlinked')>{{ __('Unlinked') }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select name="date_preset" class="form-select">
              <option value="">{{ __('Any date') }}</option>
              <option value="today" @selected(request('date_preset')==='today')>{{ __('Today') }}</option>
              <option value="this_week" @selected(request('date_preset')==='this_week')>{{ __('This week') }}</option>
              <option value="this_month" @selected(request('date_preset')==='this_month')>{{ __('This month') }}</option>
              <option value="custom" @selected(request('date_preset')==='custom')>{{ __('Custom') }}</option>
            </select>
          </div>
          <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">{{ __('Filter') }}</button>
            <a href="{{ route('cibil-report.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>{{ __('Applicant') }}</th>
              <th>{{ __('Score') }}</th>
              <th>{{ __('Client') }}</th>
              <th>{{ __('PDF') }}</th>
              <th>{{ __('Requested') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($histories as $row)
              <tr>
                <td>
                  <div class="fw-semibold">{{ $row->applicant_name }}</div>
                  <small class="text-muted">{{ $row->pan_number }} · {{ $row->phone }}</small>
                  @if($row->is_mock)<span class="badge bg-label-warning ms-1">Test</span>@endif
                  @if($row->status === 'failed')<span class="badge bg-label-danger ms-1">Failed</span>@endif
                </td>
                <td>
                  @if($row->score)
                    <span class="badge bg-{{ $scoreColor($row->score) }}">{{ $row->score }}</span>
                  @else — @endif
                </td>
                <td>
                  @if($row->client)
                    <a href="{{ route('client-view-cibil', $row->client_id) }}">{{ $row->client->client_name }}</a>
                  @else
                    <span class="text-muted">{{ __('Not linked') }}</span>
                  @endif
                </td>
                <td>
                  @if($row->pdf_status === 'saved')
                    <span class="badge bg-label-success">{{ __('Saved') }}</span>
                  @elseif($row->is_mock)
                    <span class="text-muted">{{ __('Test') }}</span>
                  @else
                    <span class="text-muted">{{ $row->pdf_status ?? '—' }}</span>
                  @endif
                </td>
                <td>
                  <small>{{ $row->created_at?->format('d M Y H:i') }}</small>
                  @if($row->creator)<br><small class="text-muted">{{ $row->creator->name }}</small>@endif
                </td>
                <td class="text-end text-nowrap">
                  <a href="{{ route('cibil-report.show', $row->id) }}" class="btn btn-sm btn-icon btn-text-secondary"><i class="ri-eye-line"></i></a>
                  @if($row->pdf_status === 'saved')
                    <a href="{{ route('cibil-report.pdf', $row->id) }}" class="btn btn-sm btn-icon btn-text-secondary"><i class="ri-download-line"></i></a>
                  @endif
                  <form action="{{ route('cibil-report.destroy', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Delete this report?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger"><i class="ri-delete-bin-line"></i></button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No reports yet.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($histories->hasPages())
        <div class="card-footer">{{ $histories->links() }}</div>
      @endif
    </div>
  @endif
</div>
@endsection
