@extends('layouts/layoutMaster')
@section('title', 'Website Enquiries')

@section('content')
@php
  $type = $type ?? 'all';
  $statusOptions = match ($type) {
    'application' => ['new','under_review','documents_pending','verification','approved','rejected','disbursed','closed'],
    'contact' => ['new','in_progress','closed'],
    default => ['new','in_progress','closed','under_review','documents_pending','verification','approved','rejected','disbursed'],
  };
@endphp
<div class="container-xxl py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">Website Enquiries</h4>
      <p class="text-muted small mb-0">Contact form messages, online loan applications, and actions in one inbox.</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible mb-4">
      <i class="ri-checkbox-circle-line me-1"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item">
      <a class="nav-link {{ $type === 'all' ? 'active' : '' }}" href="{{ route('admin.website.enquiries.index', request()->except('page', 'type') + ['type' => 'all']) }}">All submissions</a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $type === 'contact' ? 'active' : '' }}" href="{{ route('admin.website.enquiries.index', request()->except('page', 'type') + ['type' => 'contact']) }}">Contact messages</a>
    </li>
    <li class="nav-item">
      <a class="nav-link {{ $type === 'application' ? 'active' : '' }}" href="{{ route('admin.website.enquiries.index', request()->except('page', 'type') + ['type' => 'application']) }}">Loan applications</a>
    </li>
  </ul>

  <form class="row g-2 mb-3" method="GET">
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="col-md-3">
      <input name="search" class="form-control" placeholder="Ref, name, phone, email…" value="{{ $search ?? request('search') }}">
    </div>
    @if($type === 'contact' || $type === 'all')
      <div class="col-md-3">
        <select name="subject" class="form-select">
          <option value="">All inquiry topics</option>
          @foreach($subjectOptions ?? [] as $subj)
            <option value="{{ $subj }}" @selected(($subject ?? request('subject')) === $subj)>{{ $subj }}</option>
          @endforeach
        </select>
      </div>
    @endif
    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">All status</option>
        @foreach($statusOptions as $s)
          <option value="{{ $s }}" @selected(($status ?? request('status')) === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" type="submit"><i class="ri-filter-3-line me-1"></i> Filter</button>
      <a href="{{ route('admin.website.enquiries.index', ['type' => $type]) }}" class="btn btn-label-secondary">Reset</a>
    </div>
  </form>

  <div class="card table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Type</th>
          <th>Ref</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Topic / Product</th>
          <th>KYC &amp; Bank Details</th>
          <th>Status</th>
          <th>Client</th>
          <th>Date</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @if($type === 'contact' && isset($enquiries))
          @forelse($enquiries as $e)
            <tr>
              <td><span class="badge bg-label-info">Contact</span></td>
              <td class="font-monospace small">{{ $e->reference }}</td>
              <td>{{ $e->name }}</td>
              <td><a href="tel:{{ $e->phone }}">{{ $e->phone }}</a></td>
              <td><span class="badge bg-label-secondary">{{ $e->subject ?? '—' }}</span></td>
              <td><span class="text-muted small">—</span></td>
              <td><span class="badge bg-label-primary">{{ ucwords(str_replace('_', ' ', $e->status)) }}</span></td>
              <td>
                @if($e->client)
                  <a href="{{ route('client-view-account', $e->client) }}" class="small">View client</a>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td>{{ $e->created_at->format('d M Y') }}</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.website.enquiries.show', $e->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                @unless($e->client)
                  <button type="button" class="btn btn-sm btn-warning btn-convert-modal-trigger"
                    data-action="{{ route('admin.website.enquiries.convert-client', $e->id) }}"
                    data-name="{{ $e->name }}"
                    data-ref="{{ $e->reference }}"
                    data-phone="{{ $e->phone }}"
                    data-email="{{ $e->email }}">
                    Convert
                  </button>
                @endunless
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted py-4">No contact submissions yet.</td></tr>
          @endforelse
        @elseif($type === 'application' && isset($leads))
          @forelse($leads as $lead)
            @php
              $fd = $lead->form_data ?? [];
              $pan = $lead->pan_number ?? ($fd['pan_number'] ?? null);
              $aadhaar = $fd['aadhaar_number'] ?? null;
              $bank = $fd['bank_name'] ?? null;
              $accNo = $fd['bank_account_number'] ?? null;
              $ifsc = $fd['bank_ifsc'] ?? null;
              $nominee = $fd['nominee_name'] ?? null;
            @endphp
            <tr>
              <td><span class="badge bg-label-success">Application</span></td>
              <td class="font-monospace small">{{ $lead->application_ref }}</td>
              <td>
                <strong class="text-dark">{{ $lead->applicant_name }}</strong>
                @if($lead->requested_amount)
                  <div class="small text-muted">₹{{ number_format($lead->requested_amount) }} · {{ $lead->requested_tenure_months }}M</div>
                @endif
              </td>
              <td><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></td>
              <td>{{ $lead->product?->loan_name ?? '—' }}</td>
              <td>
                <div class="d-flex flex-column gap-1" style="min-width:180px">
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    @if($pan)
                      <span class="badge bg-label-dark font-monospace" title="PAN Card" style="font-size:.72rem">
                        <i class="ri-id-card-line me-1"></i>{{ $pan }}
                      </span>
                    @endif
                    @if($aadhaar)
                      <span class="badge bg-label-info font-monospace" title="Aadhaar Number" style="font-size:.72rem">
                        <i class="ri-fingerprint-line me-1"></i>•••• {{ substr($aadhaar, -4) }}
                      </span>
                    @endif
                  </div>
                  @if($bank)
                    <div class="small text-truncate" style="max-width:210px" title="{{ $bank }} - {{ $accNo }}">
                      <i class="ri-bank-line text-success me-1"></i><strong class="text-dark">{{ $bank }}</strong>
                      @if($accNo)
                        <span class="text-muted font-monospace">({{ substr($accNo, 0, 2) }}•••{{ substr($accNo, -4) }})</span>
                      @endif
                    </div>
                  @endif
                  @if($nominee)
                    <div class="text-muted" style="font-size:.74rem">
                      <i class="ri-user-heart-line text-warning me-1"></i>Nominee: <strong>{{ $nominee }}</strong>
                    </div>
                  @endif
                  @if(!$pan && !$aadhaar && !$bank)
                    <span class="text-muted small">Pending details</span>
                  @endif
                </div>
              </td>
              <td><span class="badge bg-label-primary">{{ ucwords(str_replace('_', ' ', $lead->status)) }}</span></td>
              <td>
                @if($lead->client)
                  <a href="{{ route('client-view-account', $lead->client) }}" class="small">View client</a>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td>{{ $lead->created_at->format('d M Y') }}</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.website.leads.show', $lead->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                @unless($lead->client)
                  <button type="button" class="btn btn-sm btn-warning btn-convert-modal-trigger"
                    data-action="{{ route('admin.website.leads.convert-client', $lead->id) }}"
                    data-name="{{ $lead->applicant_name }}"
                    data-ref="{{ $lead->application_ref }}"
                    data-phone="{{ $lead->phone }}"
                    data-email="{{ $lead->email }}"
                    data-pan="{{ $pan }}"
                    data-aadhaar="{{ $aadhaar }}"
                    data-bank="{{ $bank }}"
                    data-acc="{{ $accNo }}"
                    data-ifsc="{{ $ifsc }}"
                    data-nominee="{{ $nominee }}">
                    Convert
                  </button>
                @endunless
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted py-4">No loan applications yet.</td></tr>
          @endforelse
        @elseif($type === 'all' && isset($submissions))
          @forelse($submissions as $row)
            @php
              $isApp = $row->kind === 'application';
              $pan = $row->pan_number ?? null;
              $aadhaar = $row->aadhaar_number ?? null;
              $bank = $row->bank_name ?? null;
              $accNo = $row->bank_account_number ?? null;
              $ifsc = $row->bank_ifsc ?? null;
              $nominee = $row->nominee_name ?? null;
            @endphp
            <tr>
              <td>
                @if($row->kind === 'contact')
                  <span class="badge bg-label-info">Contact</span>
                @else
                  <span class="badge bg-label-success">Application</span>
                @endif
              </td>
              <td class="font-monospace small">{{ $row->ref }}</td>
              <td>
                <strong class="text-dark">{{ $row->name }}</strong>
                @if($isApp && !empty($row->requested_amount))
                  <div class="small text-muted">₹{{ number_format($row->requested_amount) }} · {{ $row->requested_tenure_months }}M</div>
                @endif
              </td>
              <td><a href="tel:{{ $row->phone }}">{{ $row->phone }}</a></td>
              <td>{{ $row->category }}</td>
              <td>
                @if($isApp)
                  <div class="d-flex flex-column gap-1" style="min-width:180px">
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                      @if($pan)
                        <span class="badge bg-label-dark font-monospace" title="PAN Card" style="font-size:.72rem">
                          <i class="ri-id-card-line me-1"></i>{{ $pan }}
                        </span>
                      @endif
                      @if($aadhaar)
                        <span class="badge bg-label-info font-monospace" title="Aadhaar Number" style="font-size:.72rem">
                          <i class="ri-fingerprint-line me-1"></i>•••• {{ substr($aadhaar, -4) }}
                        </span>
                      @endif
                    </div>
                    @if($bank)
                      <div class="small text-truncate" style="max-width:210px" title="{{ $bank }} - {{ $accNo }}">
                        <i class="ri-bank-line text-success me-1"></i><strong class="text-dark">{{ $bank }}</strong>
                        @if($accNo)
                          <span class="text-muted font-monospace">({{ substr($accNo, 0, 2) }}•••{{ substr($accNo, -4) }})</span>
                        @endif
                      </div>
                    @endif
                    @if($nominee)
                      <div class="text-muted" style="font-size:.74rem">
                        <i class="ri-user-heart-line text-warning me-1"></i>Nominee: <strong>{{ $nominee }}</strong>
                      </div>
                    @endif
                    @if(!$pan && !$aadhaar && !$bank)
                      <span class="text-muted small">Pending details</span>
                    @endif
                  </div>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td><span class="badge bg-label-primary">{{ ucwords(str_replace('_', ' ', $row->status)) }}</span></td>
              <td>
                @if($row->client)
                  <a href="{{ route('client-view-account', $row->client) }}" class="small">View client</a>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td>{{ $row->created_at->format('d M Y') }}</td>
              <td class="text-end text-nowrap">
                <a href="{{ $row->show_url }}" class="btn btn-sm btn-outline-primary">View</a>
                @unless($row->client)
                  <button type="button" class="btn btn-sm btn-warning btn-convert-modal-trigger"
                    data-action="{{ $row->convert_url }}"
                    data-name="{{ $row->name }}"
                    data-ref="{{ $row->ref }}"
                    data-phone="{{ $row->phone }}"
                    data-email="{{ $row->email ?? '' }}"
                    data-pan="{{ $pan }}"
                    data-aadhaar="{{ $aadhaar }}"
                    data-bank="{{ $bank }}"
                    data-acc="{{ $accNo }}"
                    data-ifsc="{{ $ifsc }}"
                    data-nominee="{{ $nominee }}">
                    Convert
                  </button>
                @endunless
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted py-4">No website submissions yet.</td></tr>
          @endforelse
        @endif
      </tbody>
    </table>
    <div class="p-3">
      @if($type === 'contact' && isset($enquiries))
        {{ $enquiries->links() }}
      @elseif($type === 'application' && isset($leads))
        {{ $leads->links() }}
      @elseif($type === 'all' && isset($submissions))
        {{ $submissions->links() }}
      @endif
    </div>
  </div>
</div>

{{-- Global Convert Confirmation Modal --}}
<div class="modal fade" id="tableConvertModal" tabindex="-1" aria-labelledby="tableConvertModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-label-warning py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-warning bg-opacity-25 p-2 text-warning d-flex align-items-center justify-content-center" style="width:36px;height:36px">
            <i class="ri-user-add-line fs-5"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-dark mb-0" id="tableConvertModalLabel">Create or Link Client Profile</h5>
            <small class="text-muted">Convert submission to client account</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted mb-3">
          Create or link a client profile for <strong class="text-dark" id="modalTargetName"></strong> (<span class="font-monospace small" id="modalTargetRef"></span>)?
        </p>

        {{-- Applicant Details Card --}}
        <div class="card bg-light border-0 rounded-3 p-3 mb-3" id="modalKycDetailsBox">
          <div class="row g-2 small">
            <div class="col-6">
              <span class="text-muted d-block">Phone:</span>
              <strong class="text-dark" id="modalTargetPhone">—</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Email:</span>
              <strong class="text-dark text-break" id="modalTargetEmail">—</strong>
            </div>
            <div class="col-6" id="modalPanCol">
              <span class="text-muted d-block">PAN Card:</span>
              <span class="badge bg-label-dark font-monospace" id="modalTargetPan">—</span>
            </div>
            <div class="col-6" id="modalAadhaarCol">
              <span class="text-muted d-block">Aadhaar:</span>
              <span class="badge bg-label-info font-monospace" id="modalTargetAadhaar">—</span>
            </div>
            <div class="col-12" id="modalBankCol">
              <span class="text-muted d-block">Bank Account:</span>
              <strong class="text-dark" id="modalTargetBank">—</strong>
              <span class="text-muted font-monospace small" id="modalTargetAcc"></span>
              <span class="text-muted small" id="modalTargetIfsc"></span>
            </div>
            <div class="col-12" id="modalNomineeCol">
              <span class="text-muted d-block">Nominee:</span>
              <strong class="text-dark" id="modalTargetNominee">—</strong>
            </div>
          </div>
        </div>

        <div class="alert alert-primary d-flex align-items-start gap-2 py-2 px-3 mb-0 small">
          <i class="ri-information-line fs-5 text-primary flex-shrink-0 mt-1"></i>
          <div>
            <strong>Smart Client Matching:</strong>
            If an existing client profile matches this phone or email, the application will automatically link to that profile and update its KYC/Bank records. Otherwise, a new client account will be created with portal credentials (mobile number as initial password).
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light px-4 py-3 border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> Cancel
        </button>
        <form id="tableConvertForm" method="POST" action="" class="m-0">
          @csrf
          <button type="submit" class="btn btn-warning px-4">
            <i class="ri-user-add-line me-1"></i> Yes, Create / Link Client
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const modalEl = document.getElementById('tableConvertModal');
  if (!modalEl) return;
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('tableConvertForm');
  const nameEl = document.getElementById('modalTargetName');
  const refEl = document.getElementById('modalTargetRef');
  const phoneEl = document.getElementById('modalTargetPhone');
  const emailEl = document.getElementById('modalTargetEmail');
  const panEl = document.getElementById('modalTargetPan');
  const panCol = document.getElementById('modalPanCol');
  const aadhaarEl = document.getElementById('modalTargetAadhaar');
  const aadhaarCol = document.getElementById('modalAadhaarCol');
  const bankEl = document.getElementById('modalTargetBank');
  const accEl = document.getElementById('modalTargetAcc');
  const ifscEl = document.getElementById('modalTargetIfsc');
  const bankCol = document.getElementById('modalBankCol');
  const nomineeEl = document.getElementById('modalTargetNominee');
  const nomineeCol = document.getElementById('modalNomineeCol');

  document.querySelectorAll('.btn-convert-modal-trigger').forEach(btn => {
    btn.addEventListener('click', function() {
      form.action = this.getAttribute('data-action');
      nameEl.textContent = this.getAttribute('data-name') || 'Applicant';
      refEl.textContent = this.getAttribute('data-ref') || '';
      phoneEl.textContent = this.getAttribute('data-phone') || '—';
      emailEl.textContent = this.getAttribute('data-email') || '—';

      const pan = this.getAttribute('data-pan');
      if (pan) {
        panEl.textContent = pan;
        panCol.style.display = '';
      } else {
        panCol.style.display = 'none';
      }

      const aadhaar = this.getAttribute('data-aadhaar');
      if (aadhaar) {
        aadhaarEl.textContent = '•••• ' + aadhaar.slice(-4);
        aadhaarCol.style.display = '';
      } else {
        aadhaarCol.style.display = 'none';
      }

      const bank = this.getAttribute('data-bank');
      const acc = this.getAttribute('data-acc');
      const ifsc = this.getAttribute('data-ifsc');
      if (bank || acc) {
        bankEl.textContent = bank || 'Bank';
        accEl.textContent = acc ? ('(A/C: ••••' + acc.slice(-4) + ')') : '';
        ifscEl.textContent = ifsc ? ('· IFSC: ' + ifsc) : '';
        bankCol.style.display = '';
      } else {
        bankCol.style.display = 'none';
      }

      const nominee = this.getAttribute('data-nominee');
      if (nominee) {
        nomineeEl.textContent = nominee;
        nomineeCol.style.display = '';
      } else {
        nomineeCol.style.display = 'none';
      }

      modal.show();
    });
  });
});
</script>
@endpush