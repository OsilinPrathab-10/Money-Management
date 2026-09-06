@extends('layouts/layoutMaster')

@section('title', 'Collection Details')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
  @if(session('success'))
    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="alert alert-success alert-dismissible" role="alert">
          <strong>Success!</strong> {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      </div>
    </div>
  @endif

  <div class="row g-6 mb-6">
    <div class="col-12">
      <a href="{{ route('agent-collections') }}" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> Back to Collections
      </a>
    </div>
  </div>

  <div class="row g-6">
    <div class="col-xl-8">
      <div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
          <h5 class="mb-0">Collection Information</h5>
          <span class="badge @if($collection->status === 'verified') bg-success @elseif($collection->status === 'rejected') bg-danger @else bg-warning @endif">
            {{ ucfirst(str_replace('_', ' ', $collection->status)) }}
          </span>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Collection ID</h6>
              <p class="mb-0 fw-medium">#{{ $collection->id }}</p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">EMI ID</h6>
              <p class="mb-0 fw-medium">#{{ $collection->emi_id }}</p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Amount</h6>
              <p class="mb-0 fw-medium text-success">₹{{ number_format($collection->amount, 2) }}</p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Collection Date</h6>
              <p class="mb-0">{{ $collection->collected_at ? $collection->collected_at->format('d-m-Y h:i A') : 'N/A' }}</p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Payment Method</h6>
              @php
                $methodColor = 'secondary';
                $methodLower = strtolower($collection->payment_method ?? '');
                if ($methodLower === 'in_hand') $methodColor = 'primary';
                  elseif ($methodLower === 'cash') $methodColor = 'success';
                elseif ($methodLower === 'upi') $methodColor = 'info';
                elseif ($methodLower === 'bank_transfer') $methodColor = 'warning';
              @endphp
              <span class="badge bg-label-{{ $methodColor }}">{{ ucfirst(str_replace('_', ' ', $collection->payment_method)) }}</span>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Payment Type</h6>
              <span class="badge @if($collection->payment_type === 'overdue') bg-label-danger @else bg-label-warning @endif">{{ ucfirst($collection->payment_type) }}</span>
            </div>
            @if($collection->payment_reference && in_array($collection->payment_method, ['direct', 'payment_link']))
            <div class="col-md-12">
              <h6 class="text-muted mb-1">Payment Reference</h6>
              <p class="mb-0">
                <code class="text-primary">{{ $collection->payment_reference }}</code>
                <small class="text-muted ms-2">(Razorpay Transaction ID)</small>
              </p>
            </div>
            @endif
            @if($collection->verified_by)
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Verified By</h6>
              <p class="mb-0">{{ $collection->verifiedBy->name ?? 'N/A' }}</p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Verified At</h6>
              <p class="mb-0">{{ $collection->verified_at ? $collection->verified_at->format('d-m-Y h:i A') : 'N/A' }}</p>
            </div>
            @endif
            @if($collection->remarks)
            <div class="col-12">
              <h6 class="text-muted mb-1">Remarks</h6>
              <p class="mb-0">{{ $collection->remarks }}</p>
            </div>
            @endif
          </div>
        </div>
      </div>

      @if(in_array($collection->payment_method, ['direct', 'payment_link']) && $collection->status === 'completed')
      <div class="card mb-6">
        <div class="card-header">
          <h5 class="mb-0"><i class="ri-secure-payment-line me-2"></i>Online Payment Details</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Payment Gateway</h6>
              <div class="d-flex align-items-center">
                <span class="badge bg-label-primary me-2">Razorpay</span>
                <small class="text-muted">Verified Payment</small>
              </div>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Transaction ID</h6>
              <p class="mb-0"><code class="text-success">{{ $collection->payment_reference ?? 'N/A' }}</code></p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Payment Method</h6>
              <span class="badge bg-label-success">
                {{ $collection->payment_method === 'payment_link' ? 'Payment Link' : 'Direct Payment' }}
              </span>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Payment Status</h6>
              <span class="badge bg-success">
                <i class="ri-checkbox-circle-line me-1"></i>Completed
              </span>
            </div>
            <div class="col-12">
              <div class="alert alert-success mb-0" role="alert">
                <i class="ri-information-line me-2"></i>
                <strong>Payment Verified:</strong> This payment was automatically verified through Razorpay webhook and the EMI has been updated accordingly.
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif

      @if($collection->proof_image_path)
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0"><i class="ri-image-line me-2"></i>Payment Proof</h5>
        </div>
        <div class="card-body text-center">
          <a href="{{ asset('storage/' . $collection->proof_image_path) }}" target="_blank">
            <img src="{{ asset('storage/' . $collection->proof_image_path) }}" class="img-fluid rounded shadow-sm" style="max-height: 400px; cursor: pointer;" alt="Payment Proof">
          </a>
          <p class="text-muted mt-2 mb-0"><small>Click image to view full size</small></p>
        </div>
      </div>
      @endif
    </div>

    <div class="col-xl-4">
      <div class="card mb-6">
        <div class="card-header"><h5 class="mb-0">Collector Information</h5></div>
        <div class="card-body">
          <h6 class="mb-0">{{ $collection->agent->agent_name ?? ($collection->verifiedBy ? 'Admin: ' . $collection->verifiedBy->name : 'Admin') }}</h6>
          <small class="text-muted">{{ $collection->agent->agent_phone ?? '' }}</small>
        </div>
      </div>

      @if(!auth()->user()->hasRole('Agent') && in_array($collection->status, ['pending', 'in_progress']))
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Verification Actions</h5></div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
              <i class="ri-check-line me-1"></i> Approve Collection
            </button>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
              <i class="ri-close-line me-1"></i> Reject Collection
            </button>
          </div>
        </div>
      </div>
      @endif

      @if(!auth()->user()->hasRole('Agent') && $collection->status === 'rejected')
      <div class="card border-danger">
        <div class="card-header bg-label-danger"><h5 class="mb-0 text-danger"><i class="ri-refresh-line me-1"></i>Rejected — Admin Actions</h5></div>
        <div class="card-body">
          <p class="text-muted small mb-3">This collection was rejected. You can re-process and repay it directly.</p>
          <button type="button" class="btn btn-warning w-100" id="repayBtn">
            <i class="ri-money-dollar-circle-line me-1"></i> Repay &amp; Verify
          </button>
        </div>
      </div>
      @endif
    </div>
  </div>

  <!-- Approve Modal -->
  <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Approve Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('agent-collections.verify', $collection->id) }}">
          @csrf
          <input type="hidden" name="status" value="verified">
          <div class="modal-body">
            <p>Are you sure you want to approve this collection?</p>
            <div class="mb-3">
              <label class="form-label">Remarks (Optional)</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Add approval remarks..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Confirm Approval</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Reject Modal -->
  <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Reject Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('agent-collections.verify', $collection->id) }}">
          @csrf
          <input type="hidden" name="status" value="rejected">
          <div class="modal-body">
            <p>Are you sure you want to reject this collection?</p>
            <div class="mb-3">
              <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Confirm Rejection</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Repay Modal -->
  <div class="modal fade" id="repayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ri-money-dollar-circle-line me-2 text-warning"></i>Repay &amp; Verify Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning"><i class="ri-information-line me-2"></i>This will re-process the rejected collection of <strong>₹{{ number_format($collection->amount, 2) }}</strong> and mark the EMI as paid.</div>
          <p>Are you sure you want to repay and verify this rejected collection?</p>
          <div class="mb-3">
            <label class="form-label">Remarks (Optional)</label>
            <textarea class="form-control" id="repayRemarks" rows="3" placeholder="Add repayment remarks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-warning" id="confirmRepayBtn"><i class="ri-check-line me-1"></i>Yes, Repay &amp; Verify</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Repay handler
    const repayBtn = document.getElementById('repayBtn');
    if (repayBtn) {
      repayBtn.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('repayModal'));
        modal.show();
      });
    }

    const confirmRepayBtn = document.getElementById('confirmRepayBtn');
    if (confirmRepayBtn) {
      confirmRepayBtn.addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

        const remarks = document.getElementById('repayRemarks')?.value || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch('{{ route("agent-collections.repay", $collection->id) }}', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ remarks: remarks })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            Swal.fire({ title: 'Success!', text: data.message, icon: 'success', confirmButtonText: 'OK' })
              .then(() => location.reload());
          } else {
            Swal.fire({ title: 'Error', text: data.message, icon: 'error' });
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-check-line me-1"></i>Yes, Repay & Verify';
          }
        })
        .catch(() => {
          Swal.fire({ title: 'Error', text: 'An error occurred. Please try again.', icon: 'error' });
          btn.disabled = false;
          btn.innerHTML = '<i class="ri-check-line me-1"></i>Yes, Repay & Verify';
        });
      });
    }
  });
  </script>
@endsection
