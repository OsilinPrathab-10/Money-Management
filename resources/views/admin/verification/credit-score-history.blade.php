@extends('layouts/layoutMaster')

@section('title', __('Credit score / CIBIL history'))

@section('content')
@php
  $scoreTone = function ($score) {
      if ($score === null || $score === '') {
          return 'secondary';
      }
      $s = (int) $score;
      if ($s >= 750) {
          return 'success';
      }
      if ($s >= 700) {
          return 'info';
      }
      if ($s >= 650) {
          return 'warning';
      }
      return 'danger';
  };
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm bg-label-primary bg-opacity-10">
        <div class="card-body py-4 px-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <h4 class="mb-1">{{ __('Credit score / CIBIL') }}</h4>
            <p class="text-muted mb-0">{{ __('Fetch bureau scores, export PDF, send by email or WhatsApp (Gallabox).') }}</p>
          </div>
          @unless(auth()->check() && auth()->user()->hasRole('CreditVerifier'))
          <a href="{{ route('setup-configuration-api-configuration') }}" class="btn btn-primary">
            <i class="icon-base ri ri-settings-3-line me-1"></i>{{ __('CIBIL API settings') }}
          </a>
          @endunless
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <span class="avatar avatar-sm rounded bg-label-primary me-2"><i class="icon-base ri ri-file-list-3-line"></i></span>
            <span class="text-muted small text-uppercase">{{ __('Total checks') }}</span>
          </div>
          <h3 class="mb-0">{{ number_format($stats['total_checks'] ?? 0) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <span class="avatar avatar-sm rounded bg-label-success me-2"><i class="icon-base ri ri-checkbox-circle-line"></i></span>
            <span class="text-muted small text-uppercase">{{ __('Successful') }}</span>
          </div>
          <h3 class="mb-0">{{ number_format($stats['success_checks'] ?? 0) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <span class="avatar avatar-sm rounded bg-label-info me-2"><i class="icon-base ri ri-bar-chart-box-line"></i></span>
            <span class="text-muted small text-uppercase">{{ __('Latest score') }}</span>
          </div>
          <h3 class="mb-0">
            @if (($stats['last_score'] ?? null) !== null)
              <span class="badge bg-{{ $scoreTone($stats['last_score']) }} fs-5">{{ $stats['last_score'] }}</span>
            @else
              <span class="text-muted">—</span>
            @endif
          </h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <span class="avatar avatar-sm rounded bg-label-{{ $cibilConfigured ? 'success' : 'warning' }} me-2"><i class="icon-base ri ri-plug-line"></i></span>
            <span class="text-muted small text-uppercase">{{ __('API') }}</span>
          </div>
          <p class="mb-0 fw-semibold">{{ $cibilConfigured ? __('Connected') : __('Demo / offline') }}</p>
        </div>
      </div>
    </div>
  </div>

  @if (! $cibilConfigured)
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
      <i class="icon-base ri ri-alert-line icon-22px mt-1"></i>
      <div>
        <strong>{{ __('Demo mode') }}:</strong>
        {{ __('CIBIL API is not enabled in API Configuration. Scores shown are simulated. Add your partner base URL and credentials under Setup → API Configuration → CIBIL.') }}
      </div>
    </div>
  @endif

  <!-- Hidden New Credit Check Card removed to use Modal -->

  <div class="card border-0 shadow-sm">
    <div class="card-header border-bottom-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-0">{{ __('History') }}</h5>
        <small class="text-muted">{{ __('Recent pulls with export and messaging actions.') }}</small>
      </div>
      <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNewCreditCheck">
          <i class="icon-base ri ri-add-line me-1"></i>{{ __('New Credit Check') }}
        </button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Client') }}</th>
            <th>{{ __('Score') }}</th>
            <th>{{ __('Rating') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($histories as $h)
            <tr>
              <td class="text-nowrap"><span class="text-muted small">{{ $h->created_at->format('Y-m-d') }}</span><br>{{ $h->created_at->format('H:i') }}</td>
              <td class="fw-medium">{{ $h->applicant_name }}</td>
              <td>{{ $h->client?->client_name ?? '—' }}</td>
              <td>
                @if ($h->score !== null)
                  <span class="badge bg-{{ $scoreTone($h->score) }} fs-6">{{ $h->score }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>{{ $h->rating ?? '—' }}</td>
              <td>
                <span class="badge bg-label-{{ $h->status === 'success' ? 'success' : ($h->status === 'demo' ? 'warning' : 'secondary') }}">{{ $h->status }}</span>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-view-cibil" title="{{ __('View details') }}"
                    data-id="{{ $h->id }}">
                    <i class="icon-base ri ri-eye-line icon-18px text-body"></i>
                  </button>
                  <a href="{{ route('verification-credit-score-pdf', $h) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="{{ __('PDF') }}">
                    <i class="icon-base ri ri-file-pdf-line icon-18px text-danger"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-mail" title="{{ __('Email PDF') }}"
                    data-id="{{ $h->id }}" data-email="{{ $h->client?->client_email ?? '' }}">
                    <i class="icon-base ri ri-mail-line icon-18px text-primary"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-wa" title="{{ __('WhatsApp') }}"
                    data-id="{{ $h->id }}" data-phone="{{ $h->phone ?? $h->client?->client_phone ?? '' }}">
                    <i class="icon-base ri ri-whatsapp-line icon-18px text-success"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-delete-cibil text-danger" title="{{ __('Delete') }}"
                    data-id="{{ $h->id }}">
                    <i class="icon-base ri ri-delete-bin-line icon-18px"></i>
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No credit checks yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($histories->hasPages())
      <div class="card-footer">{{ $histories->links() }}</div>
    @endif
  </div>
</div>

{{-- Email modal --}}
<div class="modal fade" id="mailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Send report by email') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mailHistoryId" value="">
        <label class="form-label">{{ __('Email') }}</label>
        <input type="email" class="form-control" id="mailEmail" required placeholder="name@example.com">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-primary" id="btnSendMail">{{ __('Send') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- View CIBIL details modal --}}
<div class="modal fade" id="viewCibilModal" tabindex="-1" aria-labelledby="viewCibilModalLabel">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewCibilModalLabel">{{ __('CIBIL / credit check details') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body" id="viewCibilBody">
        <p class="text-muted mb-0">{{ __('Loading…') }}</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- WhatsApp modal --}}
<div class="modal fade" id="waModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Send summary on WhatsApp') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="waHistoryId" value="">
        <label class="form-label">{{ __('Mobile (10 digits)') }}</label>
        <input type="text" class="form-control" id="waPhone" required placeholder="9876543210">
        <small class="text-muted">{{ __('Uses Gallabox / channel from API Configuration → WhatsApp & .env') }}</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-success" id="btnSendWa">{{ __('Send') }}</button>
      </div>
    </div>
  </div>
</div>

{{-- New Credit Check modal --}}
<div class="modal fade" id="modalNewCreditCheck" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('New credit check') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="creditFetchForm" class="row g-3">
          @csrf
          <div class="col-md-12">
            <label class="form-label">{{ __('Select Client (Optional - Auto-fills fields)') }}</label>
            <select name="client_id" id="client_id" class="form-select">
              <option value="">{{ __('— Manual entry —') }}</option>
              @foreach ($clients as $c)
                <option value="{{ $c->id }}" data-phone="{{ $c->client_phone }}" data-name="{{ $c->client_name }}" data-email="{{ $c->client_email }}">{{ $c->client_name }} @if($c->client_phone)({{ $c->client_phone }})@endif</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Applicant name') }} <span class="text-danger">*</span></label>
            <input type="text" name="applicant_name" id="applicant_name" class="form-control" required placeholder="{{ __('Full Name') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Phone') }}</label>
            <input type="text" name="phone" id="phone" class="form-control" placeholder="{{ __('10-digit mobile') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Email') }}</label>
            <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('PAN') }}</label>
            <input type="text" name="pan_number" id="pan_number" class="form-control text-uppercase" maxlength="10" placeholder="ABCDE1234F">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Aadhaar') }}</label>
            <input type="text" name="aadhar_number" id="aadhar_number" class="form-control" maxlength="12" inputmode="numeric" placeholder="12 Digit Aadhaar">
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('Date of birth') }}</label>
            <input type="date" name="date_of_birth" id="date_of_birth" class="form-control">
          </div>
          <div class="col-12 mt-4 text-center">
            <button type="submit" class="btn btn-primary btn-lg px-5" id="btnFetch">
              <i class="icon-base ri ri-search-line me-1"></i>{{ __('Verify & Fetch Report') }}
            </button>
          </div>
        </form>
        <div id="fetchAlert" class="mt-3 d-none"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
  const historyBase = @json(url('/verification/credit-score-history'));
  const fetchForm = document.getElementById('creditFetchForm');
  const clientSel = document.getElementById('client_id');
  const phoneIn = document.getElementById('phone');
  const nameIn = document.getElementById('applicant_name');

  clientSel?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) {
        // Clear manual fields if manual entry is selected
        nameIn.value = '';
        phoneIn.value = '';
        document.getElementById('email').value = '';
        return;
    }
    if (opt.dataset.phone) phoneIn.value = opt.dataset.phone.replace(/\D/g, '').slice(-10);
    if (opt.dataset.name) nameIn.value = opt.dataset.name;
    if (opt.dataset.email) document.getElementById('email').value = opt.dataset.email;
  });

  async function parseJsonResponse(res) {
    const text = await res.text();
    try {
      return text ? JSON.parse(text) : {};
    } catch (e) {
      throw new Error(text.slice(0, 200) || ('HTTP ' + res.status));
    }
  }

  fetchForm?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnFetch');
    const alertEl = document.getElementById('fetchAlert');
    btn.disabled = true;
    alertEl.classList.add('d-none');
    const fd = new FormData(fetchForm);
    try {
      const res = await fetch(@json(route('verification-credit-score-fetch')), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });
      const data = await parseJsonResponse(res);
      if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
      alertEl.className = 'mt-3 alert alert-' + (data.is_demo ? 'warning' : 'success');
      alertEl.textContent = data.message || 'OK';
      alertEl.classList.remove('d-none');
      setTimeout(() => location.reload(), 1400);
    } catch (err) {
      alertEl.className = 'mt-3 alert alert-danger';
      alertEl.textContent = err.message || 'Error';
      alertEl.classList.remove('d-none');
    } finally {
      btn.disabled = false;
    }
  });

  const mailModal = new bootstrap.Modal(document.getElementById('mailModal'));
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-mail');
    if (btn) {
      document.getElementById('mailHistoryId').value = btn.dataset.id;
      document.getElementById('mailEmail').value = btn.dataset.email || '';
      mailModal.show();
    }
  });
  document.getElementById('btnSendMail')?.addEventListener('click', async function () {
    const id = document.getElementById('mailHistoryId').value;
    const email = document.getElementById('mailEmail').value;
    try {
      const res = await fetch(`${historyBase}/${id}/mail`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ email })
      });
      const data = await parseJsonResponse(res);
      if (!res.ok) throw new Error(data.message || 'Failed');
      alert(data.message || 'Sent');
      if (data.success) mailModal.hide();
    } catch (e) {
      alert(e.message || 'Error');
    }
  });

  const waModal = new bootstrap.Modal(document.getElementById('waModal'));
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-wa');
    if (btn) {
      document.getElementById('waHistoryId').value = btn.dataset.id;
      document.getElementById('waPhone').value = (btn.dataset.phone || '').replace(/\D/g, '').slice(-10);
      waModal.show();
    }
  });
  document.getElementById('btnSendWa')?.addEventListener('click', async function () {
    const id = document.getElementById('waHistoryId').value;
    const phone = document.getElementById('waPhone').value;
    try {
      const res = await fetch(`${historyBase}/${id}/whatsapp`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ phone })
      });
      const data = await parseJsonResponse(res);
      if (!res.ok) throw new Error(data.message || 'Failed');
      alert(data.message || 'Sent');
      if (data.success) waModal.hide();
    } catch (e) {
      alert(e.message || 'Error');
    }
  });

  const viewCibilModal = new bootstrap.Modal(document.getElementById('viewCibilModal'));
  const viewCibilBody = document.getElementById('viewCibilBody');

  function escHtml(s) {
    if (s === null || s === undefined) return '—';
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  document.querySelectorAll('.btn-view-cibil').forEach(btn => btn.addEventListener('click', async function () {
    const id = this.dataset.id;
    viewCibilBody.innerHTML = '<p class="text-muted mb-0">{{ __('Loading…') }}</p>';
    viewCibilModal.show();
    try {
      const res = await fetch(`${historyBase}/${id}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await parseJsonResponse(res);
      if (!res.ok || !data.success || !data.history) throw new Error(data.message || 'Failed to load');
      const h = data.history;
      const reportJson = h.report_json != null ? JSON.stringify(h.report_json, null, 2) : null;
      
      let scoreClass = 'bg-label-secondary';
      const s = parseInt(h.score);
      if (s >= 750) scoreClass = 'bg-label-success';
      else if (s >= 700) scoreClass = 'bg-label-info';
      else if (s >= 650) scoreClass = 'bg-label-warning';
      else if (s > 0) scoreClass = 'bg-label-danger';

      viewCibilBody.innerHTML = `
        <div class="row">
          <!-- Left Column: Key Stats -->
          <div class="col-md-4 text-center border-end">
            <div class="p-4 mb-4 rounded ${scoreClass}">
              <h1 class="display-3 fw-bold mb-0 text-dark">${escHtml(h.score || 'N/A')}</h1>
              <div class="h5 fw-semibold mb-0 text-dark">${escHtml(h.rating || 'No Rating')}</div>
            </div>
            <div class="d-grid gap-2 mb-4">
              <a href="{{ url('/verification/credit-score-history') }}/${h.id}/pdf" class="btn btn-outline-danger">
                <i class="ri-file-pdf-line me-1"></i> Download PDF
              </a>
              <button type="button" class="btn btn-outline-primary btn-mail" data-id="${h.id}" data-email="${escHtml(h.email || '')}">
                <i class="ri-mail-line me-1"></i> Email Report
              </button>
            </div>
            <div class="text-start small">
              <div class="mb-2"><strong>Status:</strong> <span class="badge bg-label-primary">${escHtml(h.status)}</span></div>
              <div class="mb-2"><strong>Checked:</strong> <div class="text-muted">${escHtml(h.created_at)}</div></div>
              <div><strong>By:</strong> <div class="text-muted">${escHtml(h.created_by_name || 'System')}</div></div>
            </div>
          </div>
          
          <!-- Right Column: Details -->
          <div class="col-md-8 px-md-5">
            <h6 class="text-uppercase fw-semibold mb-3 border-bottom pb-2">Applicant Information</h6>
            <div class="row mb-3">
              <div class="col-sm-5 text-muted">Full Name:</div>
              <div class="col-sm-7 fw-medium">${escHtml(h.applicant_name)}</div>
            </div>
            <div class="row mb-3">
              <div class="col-sm-5 text-muted">Aadhaar:</div>
              <div class="col-sm-7">${escHtml(h.aadhar_number)}</div>
            </div>
            <div class="row mb-3">
              <div class="col-sm-5 text-muted">PAN:</div>
              <div class="col-sm-7 text-uppercase">${escHtml(h.pan_number)}</div>
            </div>
            <div class="row mb-3">
              <div class="col-sm-5 text-muted">Email:</div>
              <div class="col-sm-7">${escHtml(h.email)}</div>
            </div>
            <div class="row mb-3">
              <div class="col-sm-5 text-muted">Phone:</div>
              <div class="col-sm-7">${escHtml(h.phone)}</div>
            </div>
            <div class="row mb-3">
              <div class="col-sm-5 text-muted">DOB:</div>
              <div class="col-sm-7">${escHtml(h.date_of_birth)}</div>
            </div>

            <h6 class="text-uppercase fw-semibold mt-5 mb-3 border-bottom pb-2">Bureau Data (RAW)</h6>
            ${reportJson ? `
              <pre class="bg-dark text-white p-3 rounded small mb-0" style="max-height: 250px; overflow:auto;">${escHtml(reportJson)}</pre>
            ` : '<p class="text-muted italic">No raw report data available.</p>'}
            
            ${h.error_message ? `
              <div class="alert alert-danger mt-3 small p-2">
                <strong>Error:</strong> ${escHtml(h.error_message)}
              </div>
            ` : ''}
          </div>
        </div>`;
    } catch (e) {
      viewCibilBody.innerHTML = '<p class="text-danger mb-0">' + escHtml(e.message) + '</p>';
    }
  }));

  document.querySelectorAll('.btn-delete-cibil').forEach(btn => btn.addEventListener('click', async function () {
    const id = this.dataset.id;
    if (!confirm(@json(__('Delete this credit check record? This cannot be undone.')))) return;
    try {
      const res = await fetch(`${historyBase}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await parseJsonResponse(res);
      if (!res.ok) throw new Error(data.message || 'Delete failed');
      alert(data.message || '{{ __('Deleted') }}');
      location.reload();
    } catch (e) {
      alert(e.message || 'Error');
    }
  }));
});
</script>
@endsection
