@extends('layouts/layoutMaster')
@section('title', 'Contact enquiry — ' . $enquiry->reference)

@section('content')
<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('admin.website.enquiries.index', ['type' => 'contact']) }}" class="btn btn-sm btn-outline-secondary">
      <i class="ri-arrow-left-line me-1"></i> Back to submissions
    </a>
    <span class="badge bg-label-info fs-6">{{ $enquiry->subject ?? 'Website enquiry' }}</span>
    <span class="badge bg-primary font-monospace">{{ $enquiry->reference }}</span>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible mb-4">
      <i class="ri-checkbox-circle-line me-1"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ri-user-3-line me-1 text-primary"></i> {{ $enquiry->name }}</h5>
          <span class="badge bg-label-primary text-uppercase">{{ ucwords(str_replace('_', ' ', $enquiry->status)) }}</span>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <span class="text-muted small d-block">Mobile</span>
              <a href="tel:{{ $enquiry->phone }}" class="fw-bold text-dark text-decoration-none">
                <i class="ri-phone-line text-success me-1"></i>{{ $enquiry->phone }}
              </a>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Email</span>
              @if($enquiry->email)
                <a href="mailto:{{ $enquiry->email }}" class="fw-bold text-dark text-decoration-none">{{ $enquiry->email }}</a>
              @else
                <span class="text-muted">—</span>
              @endif
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Inquiry topic</span>
              <strong>{{ $enquiry->subject ?? '—' }}</strong>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Submitted</span>
              {{ $enquiry->created_at->format('d M Y, h:i A') }}
            </div>
            <div class="col-12">
              <span class="text-muted small d-block">Message</span>
              <div class="p-3 bg-light rounded mt-1" style="white-space: pre-wrap;">{{ $enquiry->message }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-bold">Admin actions</h6></div>
        <div class="card-body">
          <a href="tel:{{ $enquiry->phone }}" class="btn btn-success w-100 mb-2">
            <i class="ri-phone-fill me-1"></i> Call {{ $enquiry->phone }}
          </a>
          <a href="https://wa.me/91{{ $enquiry->phone }}?text=Hello%20{{ urlencode($enquiry->name) }},%20this%20is%20{{ urlencode($enquiry->subject ?? 'your enquiry') }}%20with%20Star%20Finance."
            target="_blank" class="btn btn-outline-success w-100 mb-3">
            <i class="ri-whatsapp-line me-1"></i> WhatsApp
          </a>

          @if($enquiry->client)
            <div class="alert alert-info small py-2 mb-3">
              Linked client:
              <a href="{{ route('client-view-account', $enquiry->client) }}" class="fw-bold">{{ $enquiry->client->client_name }}</a>
              @if($enquiry->client->customer_id)
                <span class="text-muted">({{ $enquiry->client->customer_id }})</span>
              @endif
            </div>
          @else
            <button type="button" class="btn btn-warning w-100 mb-3" data-bs-toggle="modal" data-bs-target="#convertContactModal">
              <i class="ri-user-add-line me-1"></i> Convert to client
            </button>
          @endif

          @if($enquiry->called_at)
            <div class="p-2 bg-label-success rounded text-center small mb-3">
              Called by {{ $enquiry->handler?->name ?? 'Staff' }} on {{ $enquiry->called_at->format('d M Y, h:i A') }}
            </div>
          @endif

          <form method="POST" action="{{ route('admin.website.enquiries.update', $enquiry->id) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
              <label class="form-label fw-bold small">STATUS</label>
              <select name="status" class="form-select">
                @foreach(['new','in_progress','closed'] as $s)
                  <option value="{{ $s }}" @selected($enquiry->status === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold small">INTERNAL NOTES</label>
              <textarea name="admin_notes" class="form-control" rows="4">{{ $enquiry->admin_notes }}</textarea>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="mark_called" value="1" id="mc" @checked($enquiry->called_at)>
              <label class="form-check-label small" for="mc">Mark as called now</label>
            </div>
            <button class="btn btn-primary w-100" type="submit"><i class="ri-save-line me-1"></i> Save</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

@unless($enquiry->client)
{{-- Convert Contact Enquiry to Client Profile Modal --}}
<div class="modal fade" id="convertContactModal" tabindex="-1" aria-labelledby="convertContactModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-label-warning py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-warning bg-opacity-25 p-2 text-warning d-flex align-items-center justify-content-center" style="width:38px;height:38px">
            <i class="ri-user-add-line fs-5"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-dark mb-0" id="convertContactModalLabel">Create or Link Client Profile</h5>
            <small class="text-muted">Convert contact enquiry to client account</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted mb-3">
          Create or link a client profile for this contact?
        </p>

        {{-- Contact Details Card --}}
        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="avatar avatar-md bg-white text-primary rounded-circle shadow-xs d-flex align-items-center justify-content-center fw-bold fs-5" style="width:42px;height:42px">
              {{ strtoupper(substr($enquiry->name, 0, 1)) }}
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">{{ $enquiry->name }}</h6>
              <span class="text-muted small font-monospace">{{ $enquiry->reference }}</span>
            </div>
          </div>
          <hr class="my-2 border-secondary border-opacity-10">
          <div class="row g-2 small">
            <div class="col-6">
              <span class="text-muted d-block">Phone:</span>
              <strong class="text-dark">{{ $enquiry->phone }}</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Email:</span>
              <strong class="text-dark text-break">{{ $enquiry->email ?: 'N/A' }}</strong>
            </div>
            @if($enquiry->subject)
              <div class="col-12 mt-1">
                <span class="text-muted d-block">Subject:</span>
                <span class="badge bg-label-primary">{{ $enquiry->subject }}</span>
              </div>
            @endif
          </div>
        </div>

        <div class="alert alert-primary d-flex align-items-start gap-2 py-2 px-3 mb-0 small">
          <i class="ri-information-line fs-5 text-primary flex-shrink-0 mt-1"></i>
          <div>
            <strong>Smart Client Matching:</strong>
            If an existing client matches this phone or email, the enquiry will link directly to that profile. Otherwise, a new client account will be created with portal credentials (mobile number as initial password).
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light px-4 py-3 border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> Cancel
        </button>
        <form method="POST" action="{{ route('admin.website.enquiries.convert-client', $enquiry->id) }}" class="m-0">
          @csrf
          <button type="submit" class="btn btn-warning px-4">
            <i class="ri-user-add-line me-1"></i> Yes, Create / Link Client
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endunless
@endsection