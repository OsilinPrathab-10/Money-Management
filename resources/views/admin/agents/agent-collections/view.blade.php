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
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
      <a href="{{ route('agent-collections') }}" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> {{ auth()->user()->hasRole('Agent') ? 'Back to My Collections' : 'Back to Agent Collections' }}
      </a>
      <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="badge @if($collection->status === 'verified') bg-success @elseif($collection->status === 'rejected') bg-danger @else bg-warning text-dark @endif fs-6 px-3 py-2">
          <i class="@if($collection->status === 'verified') ri-checkbox-circle-line @elseif($collection->status === 'rejected') ri-close-circle-line @else ri-time-line @endif me-1"></i>
          {{ ucfirst(str_replace('_', ' ', $collection->status)) }}
        </span>
        @if(!empty($isMultiEmi))
        <span class="badge bg-info fs-6 px-3 py-2">
          <i class="ri-stack-line me-1"></i> Bulk Payment
        </span>
        @endif
      </div>
    </div>
  </div>

  <div class="row g-6">
    <div class="col-xl-8">
      <div class="card mb-6">
        <div class="card-header border-bottom">
          <h5 class="mb-0">Collection Information</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <h6 class="text-muted mb-1">Collection ID</h6>
              <p class="mb-0 fw-medium">#{{ $collection->id }}</p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">{{ !empty($isMultiEmi) ? 'EMIs' : 'EMI ID' }}</h6>
              <p class="mb-0 fw-medium">
                @php
                  $emiIdChunks = collect($emiNumbers ?? [])->filter()->values()->chunk(5);
                @endphp
                @if ($emiIdChunks->isNotEmpty())
                  @foreach ($emiIdChunks as $emiIdChunk)
                    <span class="d-block">EMI #{{ $emiIdChunk->implode(', #') }}</span>
                  @endforeach
                @else
                  {{ $emiSplitLabel ?? ('#' . $collection->emi_id) }}
                @endif
              </p>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-1">{{ !empty($isMultiEmi) ? 'Collected Amount (Bulk)' : 'Amount' }}</h6>
              <p class="mb-0 fw-medium text-success">₹{{ number_format($bulkTotalAmount ?? $collection->amount, 2) }}</p>
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
            @if(!empty($isMultiEmi) && isset($relatedCollections) && count($relatedCollections))
            <div class="col-12">
              <h6 class="text-muted mb-2">EMI Split</h6>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead>
                    <tr>
                      <th>EMI</th>
                      <th class="text-end">Amount</th>
                      <th>Type</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($relatedCollections as $related)
                    <tr>
                      <td>{{ $related->emi?->instalment_number ? ('EMI #' . $related->emi->instalment_number) : ('#' . $related->emi_id) }}</td>
                      <td class="text-end">₹{{ number_format($related->amount, 2) }}</td>
                      <td>{{ ucfirst($related->payment_type ?? 'full') }}</td>
                      <td>{{ ucfirst(str_replace('_', ' ', $related->status)) }}</td>
                    </tr>
                    @endforeach
                  </tbody>
                  <tfoot>
                    <tr>
                      <th>Total</th>
                      <th class="text-end">₹{{ number_format($bulkTotalAmount ?? $relatedCollections->sum('amount'), 2) }}</th>
                      <th colspan="2"></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
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
          <h6 class="mb-0">{{ $collection->getCollectedByLabel() }}</h6>
          <small class="text-muted">{{ $collection->agent?->agent_phone ?? '' }}</small>
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
        <form method="POST" action="{{ route('agent-collections.verify-one') }}" id="approveCollectionForm">
          @csrf
          <input type="hidden" name="collection_id" value="{{ $collection->id }}">
          <input type="hidden" name="status" value="verified">
          <div class="modal-body">
            <p>Are you sure you want to approve this collection?</p>
            @if(in_array(strtolower($collection->payment_method ?? ''), ['upi', 'bank_transfer']))
              <div class="mb-3">
                <label class="form-label" for="internal_bank_account_id">Credit Bank Account <span class="text-danger">*</span></label>
                <select class="form-select" name="internal_bank_account_id" id="internal_bank_account_id" required>
                  <option value="" disabled {{ $collection->bank_account_id ? '' : 'selected' }}>-- Select Internal Bank Account --</option>
                  @foreach($bankAccounts as $bank)
                    <option value="{{ $bank->id }}"
                            @selected((int) $collection->bank_account_id === (int) $bank->id)
                            data-bank-name="{{ $bank->bank_name }}"
                            data-account-name="{{ $bank->account_name }}"
                            data-account-number="{{ $bank->account_number }}"
                            data-branch-name="{{ $bank->branch_name }}"
                            data-account-type="{{ $bank->account_type }}"
                            data-ifsc="{{ $bank->effective_ifsc }}"
                            data-upi-id="{{ $bank->upi_id }}"
                            data-qr-code="{{ $bank->qr_code ? asset('storage/' . $bank->qr_code) : '' }}">
                      {{ $bank->account_name }} (₹{{ number_format($bank->current_balance, 2) }})
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3 d-none" id="verifyBankDetailsCard">
                <div class="card bg-lighter border shadow-none">
                  <div class="card-body p-3">
                    <div class="row g-3 align-items-start">
                      <div class="col-md-7">
                        <h6 class="mb-2 fw-semibold text-heading" id="qrBankName">Bank Name</h6>
                        <div id="verifyBankTransferContent" class="small text-dark"></div>
                      </div>
                      <div class="col-md-5 text-center" id="qrCodeDisplayContainer">
                        <p class="mb-1 small text-muted">UPI / GPay QR</p>
                        <p class="mb-2 small">UPI ID: <span class="fw-bold text-dark" id="qrUpiId">N/A</span></p>
                        <div id="qrCodeImageWrapper"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            @endif
            <div class="mb-3">
              <label class="form-label">Remarks (Optional)</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Add approval remarks..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success" id="confirmApproveBtn">Confirm Approval</button>
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
        <form method="POST" action="{{ route('agent-collections.verify-one') }}" id="rejectCollectionForm">
          @csrf
          <input type="hidden" name="collection_id" value="{{ $collection->id }}">
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
    // Combined bank details + QR in verify/approve modal
    const internalBankSelect = document.getElementById('internal_bank_account_id');
    const detailsCard = document.getElementById('verifyBankDetailsCard');
    const detailsContent = document.getElementById('verifyBankTransferContent');
    const qrContainer = document.getElementById('qrCodeDisplayContainer');

    function renderVerifyBankDetails(option) {
      if (!option || !option.value || !detailsCard) {
        if (detailsCard) detailsCard.classList.add('d-none');
        return;
      }

      const bankName = option.getAttribute('data-bank-name') || 'N/A';
      const accountName = option.getAttribute('data-account-name') || 'N/A';
      const accountNumber = option.getAttribute('data-account-number') || 'N/A';
      const branchName = option.getAttribute('data-branch-name') || 'N/A';
      const ifsc = option.getAttribute('data-ifsc') || '';
      const upiId = option.getAttribute('data-upi-id') || 'N/A';
      const qrCodeUrl = option.getAttribute('data-qr-code') || '';

      document.getElementById('qrBankName').textContent = bankName;
      document.getElementById('qrUpiId').textContent = upiId;

      let html = `
        <p class="mb-1"><strong>Bank:</strong> ${bankName}</p>
        <p class="mb-1"><strong>Account Name:</strong> ${accountName}</p>
        <p class="mb-1"><strong>Account Number:</strong> ${accountNumber}</p>
        <p class="mb-1"><strong>Branch:</strong> ${branchName}</p>
      `;
      if (ifsc) {
        html += `<p class="mb-1"><strong>IFSC:</strong> ${ifsc}</p>`;
      }
      if (upiId && upiId !== 'N/A') {
        html += `<p class="mb-1"><strong>UPI ID:</strong> ${upiId}</p>`;
      }
      html += '<p class="text-muted small mb-0 mt-2">Collection will be credited to this bank account.</p>';
      if (detailsContent) {
        detailsContent.innerHTML = html;
      }

      const wrapper = document.getElementById('qrCodeImageWrapper');
      wrapper.innerHTML = '';
      if (qrCodeUrl) {
        wrapper.innerHTML = `<img src="${qrCodeUrl}" alt="QR Code" class="img-fluid my-1" style="max-height: 220px; border: 1px solid #eee; padding: 8px; border-radius: 8px;">`;
      } else {
        wrapper.innerHTML = `<div class="alert alert-warning py-2 mb-0 mt-1 small">No QR Code image uploaded for this bank account.</div>`;
      }

      detailsCard.classList.remove('d-none');
      if (qrContainer) {
        qrContainer.classList.remove('d-none');
      }
    }

    if (internalBankSelect) {
      internalBankSelect.addEventListener('change', function() {
        renderVerifyBankDetails(this.options[this.selectedIndex]);
      });
      if (internalBankSelect.value) {
        renderVerifyBankDetails(internalBankSelect.options[internalBankSelect.selectedIndex]);
      }
    }

    const approveForm = document.getElementById('approveCollectionForm');
    if (approveForm) {
      approveForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('confirmApproveBtn');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Approving...';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(approveForm.action, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new FormData(approveForm)
        })
        .then(r => r.json().catch(() => ({ success: false, message: 'Approve failed. Please try again.' })))
        .then(data => {
          const modalEl = document.getElementById('approveModal');
          const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
          if (modal) modal.hide();

          if (data.success) {
            Swal.fire({ title: 'Approved', text: data.message || 'Collection verified successfully', icon: 'success' })
              .then(() => location.reload());
          } else {
            Swal.fire({ title: 'Error', text: data.message || 'Verification failed', icon: 'error' });
            btn.disabled = false;
            btn.innerHTML = 'Confirm Approval';
          }
        })
        .catch(() => {
          Swal.fire({ title: 'Error', text: 'An error occurred. Please try again.', icon: 'error' });
          btn.disabled = false;
          btn.innerHTML = 'Confirm Approval';
        });
      });
    }

    const rejectForm = document.getElementById('rejectCollectionForm');
    if (rejectForm) {
      rejectForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = rejectForm.querySelector('button[type="submit"]');
        if (btn && btn.disabled) return;
        if (btn) {
          btn.disabled = true;
          btn.dataset.originalHtml = btn.innerHTML;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting...';
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(rejectForm.action, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new FormData(rejectForm)
        })
        .then(r => r.json().catch(() => ({ success: false, message: 'Reject failed. Please try again.' })))
        .then(data => {
          const modalEl = document.getElementById('rejectModal');
          const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
          if (modal) modal.hide();

          if (data.success) {
            Swal.fire({ title: 'Rejected', text: data.message || 'Collection rejected successfully', icon: 'success' })
              .then(() => location.reload());
          } else {
            Swal.fire({ title: 'Error', text: data.message || 'Rejection failed', icon: 'error' });
            if (btn) {
              btn.disabled = false;
              btn.innerHTML = btn.dataset.originalHtml || 'Confirm Rejection';
            }
          }
        })
        .catch(() => {
          Swal.fire({ title: 'Error', text: 'An error occurred. Please try again.', icon: 'error' });
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml || 'Confirm Rejection';
          }
        });
      });
    }

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
