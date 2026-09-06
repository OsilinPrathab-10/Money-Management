@extends('layouts/layoutMaster')

@section('title', 'EMI Details - ' . $loanAccount->account_number)

@section('vendor-style')
@vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/client-view-loans.js'])
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
    const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');
    const isAdminOrStaff = {{ (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff')) ? 'true' : 'false' }};

    document.addEventListener('click', function(e) {
        const link = e.target.closest('.view-history-link');
        if (link) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const historyModalElement = document.getElementById('paymentHistoryModal');
            if (!historyModalElement) return;
            
            // Use existing instance or create new one robustly
            let historyModal = null;
            if (typeof bootstrap !== 'undefined') {
                historyModal = bootstrap.Modal.getOrCreateInstance(historyModalElement);
            } else {
                console.error('Bootstrap is not defined');
                return;
            }

            const historyEmiNumber = document.getElementById('historyEmiNumber');
            const historyEmiStatus = document.getElementById('historyEmiStatus');
            const historyTableBody = document.getElementById('historyTableBody');
            
            const emiId = link.dataset.emiId;
            const emiNumber = link.dataset.emiNumber;
            
            if (historyEmiNumber) historyEmiNumber.textContent = emiNumber;
            if (historyEmiStatus) historyEmiStatus.innerHTML = ''; // Clear previous status
            
            if (historyTableBody) {
                const colCount = isAdminOrStaff ? 7 : 6;
                historyTableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></td></tr>`;
                
                // Show modal immediately on first click without delay
                historyModal.show();

                fetch(`${baseUrl}client/emi/${emiId}/history`)
                    .then(response => {
                        if (!response.ok) {
                            if (response.status === 404) throw new Error('History endpoint not found. Please check routes.');
                            return response.json().then(err => { throw err; });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Update status badge
                            if (historyEmiStatus) {
                                const statusColors = {
                                    'paid': 'success',
                                    'pending': 'warning',
                                    'overdue': 'danger',
                                    'partial': 'info'
                                };
                                const color = statusColors[data.status] || 'secondary';
                                const statusLabel = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                                historyEmiStatus.innerHTML = `<span class="badge bg-label-${color}">${statusLabel}</span>`;
                            }

                            historyTableBody.innerHTML = '';
                            if (data.collections.length === 0) {
                                historyTableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-3 text-muted">No history found</td></tr>`;
                                return;
                            }
                            data.collections.forEach(c => {
                                const tr = document.createElement('tr');
                                // Build status badge based on collection status
                                let statusBadge;
                                if (c.status === 'in_progress') {
                                    statusBadge = `<span class="badge" style="background-color:#ff8c00;color:#fff;" title="Awaiting Admin Verification">Paid (Unverified)</span>`;
                                } else if (c.status === 'paid' || c.status === 'verified') {
                                    statusBadge = `<span class="badge bg-label-success">Paid</span>`;
                                } else if (c.status === 'rejected') {
                                    statusBadge = `<span class="badge bg-label-danger">Rejected</span>`;
                                } else {
                                    const label = c.status ? c.status.charAt(0).toUpperCase() + c.status.slice(1) : '-';
                                    statusBadge = `<span class="badge bg-label-secondary">${label}</span>`;
                                }

                                let actionsCell = '';
                                if (isAdminOrStaff) {
                                    if (c.status === 'in_progress') {
                                        actionsCell = `
                                            <td class="px-4 py-3 text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-xs btn-success btn-verify-collection" data-id="${c.id}" data-amount="${c.amount}" title="Verify Payment">
                                                        <i class="ri-check-line"></i> Verify
                                                    </button>
                                                    <button class="btn btn-xs btn-danger btn-reject-collection" data-id="${c.id}" data-amount="${c.amount}" title="Reject Payment">
                                                        <i class="ri-close-line"></i> Reject
                                                    </button>
                                                </div>
                                            </td>
                                        `;
                                    } else {
                                        actionsCell = `<td class="px-4 py-3 text-center text-muted small">-</td>`;
                                    }
                                }

                                // Construct payment breakdown HSL/monospace details
                                let breakdownHTML = '';
                                const rawPrincipal = parseFloat(c.raw_principal_paid || 0);
                                const rawInterest = parseFloat(c.raw_interest_paid || 0);
                                const isKandhuvatti = (c.is_kandhuvatti === true || c.is_kandhuvatti === 'true');
                                
                                if (rawPrincipal > 0.01) {
                                    if (isKandhuvatti) {
                                        breakdownHTML += `<div class="text-success small fw-bold" style="font-size:0.75rem;"><i class="ri-checkbox-circle-line me-1"></i>Principal Paid: ₹${c.principal_paid}</div>`;
                                    } else {
                                        breakdownHTML += `<div class="text-success small" style="font-size:0.75rem;">P. Paid: ₹${c.principal_paid}</div>`;
                                    }
                                }
                                if (rawInterest > 0.01) {
                                    breakdownHTML += `<div class="text-info small" style="font-size:0.75rem;">I. Paid: ₹${c.interest_paid}</div>`;
                                }
                                
                                if (isKandhuvatti) {
                                    if (rawInterest > 0.01) {
                                        breakdownHTML += `<div class="fw-bold text-dark border-top pt-1 mt-1">Total: ₹${c.interest_paid}</div>`;
                                    }
                                } else {
                                    breakdownHTML += `<div class="fw-bold text-dark border-top pt-1 mt-1">Total: ₹${c.amount}</div>`;
                                }

                                tr.innerHTML = `
                                    <td class="px-4 py-3 text-nowrap">${c.date}</td>
                                    <td class="text-end px-4 py-3">
                                        <div class="d-flex flex-column align-items-end text-end font-monospace">
                                            ${breakdownHTML}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium">${c.agent}</span>
                                            <small class="text-muted">${c.role}</small>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><span class="badge bg-label-info">${c.method}</span></td>
                                    <td class="px-4 py-3 text-muted small">${c.reference}</td>
                                    <td class="px-4 py-3">${statusBadge}</td>
                                    ${actionsCell}
                                `;
                                historyTableBody.appendChild(tr);
                            });
                        } else {
                            historyTableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-3 text-danger">Error: ${data.message || 'Failed to load history'}</td></tr>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching history:', error);
                        historyTableBody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-3 text-danger">${error.message || 'Failed to load history due to server error'}</td></tr>`;
                    });
            }
        }

        const verifyBtn = e.target.closest('.btn-verify-collection');
        if (verifyBtn) {
            e.preventDefault();
            if (verifyBtn.disabled) return;
            const originalBtnContent = verifyBtn.innerHTML;
            const collectionId = verifyBtn.dataset.id;
            const amount = verifyBtn.dataset.amount;

            // Get history modal instance to hide it and avoid focus trap issues with SweetAlert
            const historyModalElement = document.getElementById('paymentHistoryModal');
            const historyModal = historyModalElement ? bootstrap.Modal.getOrCreateInstance(historyModalElement) : null;
            if (historyModal) {
                historyModal.hide();
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Verify Payment Collection',
                    text: 'Are you sure you want to verify this payment collection of ₹' + amount + '?',
                    icon: 'question',
                    input: 'text',
                    inputPlaceholder: 'Enter verification remarks (optional)',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Verify',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-success me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function (result) {
                    if (result.isConfirmed) {
                        const remarks = result.value || '';
                        verifyBtn.disabled = true;
                        verifyBtn.innerHTML = `<i class="ri-loader-2-line ri-spin"></i> Verifying...`;
                        Swal.showLoading();
                        fetch(baseUrl + 'app/agents/agent-collections/' + collectionId + '/verify', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                status: 'verified',
                                remarks: remarks
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Verified!',
                                    text: data.message || 'Payment has been successfully verified.',
                                    customClass: {
                                        confirmButton: 'btn btn-success'
                                    }
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                verifyBtn.disabled = false;
                                verifyBtn.innerHTML = originalBtnContent;
                                if (historyModal) {
                                    historyModal.show();
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to verify payment.',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        })
                        .catch(err => {
                            verifyBtn.disabled = false;
                            verifyBtn.innerHTML = originalBtnContent;
                            if (historyModal) {
                                historyModal.show();
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while verifying the payment.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                }
                            });
                        });
                    } else {
                        if (historyModal) {
                            historyModal.show();
                        }
                    }
                });
            }
        }

        const rejectBtn = e.target.closest('.btn-reject-collection');
        if (rejectBtn) {
            e.preventDefault();
            if (rejectBtn.disabled) return;
            const originalBtnContent = rejectBtn.innerHTML;
            
            const collectionId = rejectBtn.dataset.id;
            const amount = rejectBtn.dataset.amount;

            // Get history modal instance to hide it and avoid focus trap issues with SweetAlert
            const historyModalElement = document.getElementById('paymentHistoryModal');
            const historyModal = historyModalElement ? bootstrap.Modal.getOrCreateInstance(historyModalElement) : null;
            if (historyModal) {
                historyModal.hide();
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Reject Payment Collection',
                    text: 'Are you sure you want to reject this payment collection of ₹' + amount + '?',
                    icon: 'warning',
                    input: 'text',
                    inputPlaceholder: 'Enter rejection remarks (optional)',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Reject',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-danger me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function (result) {
                    if (result.isConfirmed) {
                        const remarks = result.value || '';
                        rejectBtn.disabled = true;
                        rejectBtn.innerHTML = `<i class="ri-loader-2-line ri-spin"></i> Rejecting...`;
                        Swal.showLoading();
                        fetch(baseUrl + 'app/agents/agent-collections/' + collectionId + '/verify', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                status: 'rejected',
                                remarks: remarks
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Rejected!',
                                    text: data.message || 'Payment has been successfully rejected.',
                                    customClass: {
                                        confirmButton: 'btn btn-success'
                                    }
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                rejectBtn.disabled = false;
                                rejectBtn.innerHTML = originalBtnContent;
                                if (historyModal) {
                                    historyModal.show();
                                }
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to reject payment.',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        })
                        .catch(err => {
                            rejectBtn.disabled = false;
                            rejectBtn.innerHTML = originalBtnContent;
                            if (historyModal) {
                                historyModal.show();
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while rejecting the payment.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                }
                            });
                        });
                    } else {
                        if (historyModal) {
                            historyModal.show();
                        }
                    }
                });
            }
        }

        const replayBtn = e.target.closest('.btn-replay-pay');
        if (replayBtn) {
            e.preventDefault();
            const collectionId = replayBtn.dataset.id;
            const rejectedAmount = parseFloat(replayBtn.dataset.amount);
            const emiPending = parseFloat(replayBtn.dataset.emiPending);
            const emiNo = replayBtn.dataset.emiNo;

            // The reprocessable amount is the lesser of rejected amount and EMI pending
            const reprocessAmount = Math.min(rejectedAmount, emiPending);
            const formattedReprocess = reprocessAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            const formattedRejected = rejectedAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            let confirmHtml = '';
            if (reprocessAmount < rejectedAmount) {
                confirmHtml = '<div class="text-start"><p class="mb-2">Only <strong>₹' + formattedReprocess + '</strong> can be reprocessed for EMI #' + emiNo + '.</p>' +
                    '<p class="text-muted small mb-0">Original rejected amount was ₹' + formattedRejected + ', but the remaining EMI balance is ₹' + formattedReprocess + '.</p></div>';
            } else {
                confirmHtml = 'Are you sure you want to re-process and verify this rejected collection of <strong>₹' + formattedReprocess + '</strong> for EMI #' + emiNo + '?';
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirm Re-Pay Payment',
                    html: confirmHtml,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Re-Pay ₹' + formattedReprocess,
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-warning me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function (result) {
                    if (result.value || result.isConfirmed) {
                        Swal.showLoading();
                        fetch(baseUrl + 'app/agents/agent-collections/' + collectionId + '/repay', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    customClass: {
                                        confirmButton: 'btn btn-success'
                                    }
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message,
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        })
                        .catch(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while re-paying the payment.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                }
                            });
                        });
                    }
                });
            } else {
                if (confirm('Re-process ₹' + formattedReprocess + ' for EMI #' + emiNo + '?')) {
                    fetch(baseUrl + 'app/agents/agent-collections/' + collectionId + '/repay', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({})
                    })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            location.reload();
                        }
                    })
                    .catch(() => {
                        alert('An error occurred.');
                    });
                }
            }
        }
    });
});
</script>
@endsection

@section('page-style')
<style>
.nav-tabs .nav-link.active {
    background-color: #696cff !important;
    color: white !important;
    border-color: #696cff #696cff #fff !important;
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    color: #6c757d;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
    color: #696cff;
}

.card-header-tabs {
    margin-bottom: -1px;
}

.card-header .nav-tabs {
    margin-bottom: 0 !important;
}

.card-header + .tab-content {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

#emi-schedule .table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

#emi-schedule .table {
    width: max-content !important;
    min-width: 100% !important;
    white-space: nowrap !important;
    margin-bottom: 0 !important;
}

.card-body .tab-content {
    padding: 0 !important;
}

#emi-schedule .pagination {
    margin-bottom: 0;
}
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
  <div>
    <h4 class="mb-1">EMI Details - {{ $loanAccount->account_number }}</h4>
    <p class="text-muted mb-0">{{ $client->first_name }} {{ $client->last_name }}</p>
  </div>
  <div class="d-flex gap-2 w-100 w-sm-auto">
    <a href="{{ route('client-view-account', $client->id) }}" class="btn btn-sm btn-outline-primary flex-grow-1 w-sm-auto d-inline-flex align-items-center justify-content-center">
      <i class="icon-base ri ri-user-3-line me-1"></i>
      <span>Back to Client</span>
    </a>
    <a href="{{ route('client-view-loans', $client->id) }}" class="btn btn-sm btn-outline-secondary flex-grow-1 w-sm-auto d-inline-flex align-items-center justify-content-center">
      <i class="icon-base ri ri-arrow-left-line me-1"></i>
      <span>Back to Loans</span>
    </a>
  </div>
</div>

<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

@if($loanAccount->status === 'closed')
  <div class="alert alert-success d-flex align-items-center p-4 mb-6 shadow-sm border-0" role="alert" style="border-left: 5px solid #71dd37 !important; background-color: #e8fadf;">
    <i class="ri-checkbox-circle-fill ri-32px text-success me-3"></i>
    <div>
      <h5 class="alert-heading text-success mb-1 fw-bold">🎉 Congratulations!</h5>
      <p class="mb-0 text-dark">This loan account has been fully paid and successfully closed. All principal and interest obligations have been settled.</p>
    </div>
  </div>
@endif

<!-- Loan Summary Card -->
<div class="card mb-6">
  <div class="card-header">
    <h5 class="mb-0">Loan Summary</h5>
  </div>
  <div class="card-body">
    <div class="row g-4">  
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Account Number</small>
          <h6 class="mb-0">{{ $loanAccount->account_number }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Client Name</small>
          <h5 class="mb-0 text-info">{{ $loanAccount->client->client_name }}</h5>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          @php
            $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
          @endphp
          <small class="text-muted text-uppercase mb-1">{{ $isKandhuvatti ? 'Total Loan Amount' : 'Loan Amount' }}</small>
          <h6 class="mb-0 text-primary">₹{{ number_format($loanAccount->loan_amount, 2) }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Interest Rate</small>
          <h6 class="mb-0">{{ $loanAccount->interest_rate }}% p.a.</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Tenure</small>
          @php
            $unit = $loanAccount->loanApplication->term_unit ?: optional($loanAccount->loanApplication->product)->term_unit ?: 'months';
            $unitLabel = in_array(strtolower($unit), ['days', 'day', 'daily']) ? 'Days' : (in_array(strtolower($unit), ['weeks', 'week', 'weekly']) ? 'Weeks' : 'Months');
          @endphp
          <h6 class="mb-0">{{ $loanAccount->tenure }} {{ $unitLabel }}</h6>
        </div>
      </div>
      @if($isKandhuvatti)
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Loan Structure</small>
            <h6 class="mb-0">Open Loan</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Total Interest Paid</small>
            <h6 class="mb-0 text-success">₹{{ number_format($interestPaid, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Total Principal Paid</small>
            <h6 class="mb-0 text-info">₹{{ number_format($principalPaid, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Remaining Principal Balance</small>
            <h6 class="mb-0 text-danger">₹{{ number_format($loanAccount->outstanding_amount, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Total Amount Collected</small>
            <h6 class="mb-0 text-success fw-bold">₹{{ number_format($loanAccount->paid_amount, 2) }}</h6>
          </div>
        </div>
      @else
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Total Payable</small>
            <h6 class="mb-0">₹{{ number_format($loanAccount->total_payable, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Principal Outstanding</small>
            <h6 class="mb-0 text-danger">₹{{ number_format($loanAccount->outstanding_amount, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Principal Paid</small>
            <h6 class="mb-0 text-info">₹{{ number_format($principalPaid, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Interest Paid</small>
            <h6 class="mb-0 text-success">₹{{ number_format($interestPaid, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Total Paid</small>
            <h6 class="mb-0 text-success fw-bold">₹{{ number_format($loanAccount->paid_amount, 2) }}</h6>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <div class="d-flex flex-column">
            <small class="text-muted text-uppercase mb-1">Next EMI Due Date</small>
            @php
              $firstUnpaid = $loanAccount->emis->whereIn('status', ['pending', 'overdue', 'partial'])->sortBy('instalment_number')->first();
              $nextEmiDueDate = $loanAccount->status === 'closed' ? 'Closed' : ($firstUnpaid ? $firstUnpaid->due_date->format('d-m-Y') : 'N/A');
            @endphp
            <h6 class="mb-0 text-{{ $nextEmiDueDate === 'Closed' ? 'success' : ($nextEmiDueDate === 'N/A' ? 'muted' : 'warning') }}">{{ $nextEmiDueDate }}</h6>
          </div>
        </div>
      @endif
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Status</small>
          <span class="badge bg-label-{{ $loanAccount->status === 'active' ? 'success' : ($loanAccount->status === 'closed' ? 'info' : 'danger') }}" style="width: fit-content">
            {{ ucfirst($loanAccount->status) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tabs Card -->
<div class="card">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="emi-schedule-tab" data-bs-toggle="tab" data-bs-target="#emi-schedule" type="button" role="tab" aria-controls="emi-schedule" aria-selected="true">
          <i class="icon-base ri ri-calendar-line me-1"></i>
          {{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'EMI Schedules' }}
        </button>
      </li>
      <!-- <li class="nav-item" role="presentation">
        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
          <i class="icon-base ri ri-file-text-line me-1"></i>
          Documents
        </button>
      </li> -->
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="emi-schedule" role="tabpanel" aria-labelledby="emi-schedule-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">{{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'EMI Schedule' }}</h6>
          <div class="text-muted d-flex align-items-center gap-3">
            @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
              <span class="badge bg-label-danger fw-bold fs-6">Remaining Principal Balance: ₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
            @endif
            <small>{{ $isKandhuvatti ? 'Total Cycles' : 'Total EMIs' }}: {{ $emis->total() }}</small>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th class="text-center">{{ $isKandhuvatti ? 'Cycle' : 'EMI No.' }}</th>
                <th>Due Date</th>
                @if(!$isKandhuvatti)
                  <th class="text-end">Opening Balance</th>
                @endif
                <th class="text-end">{{ $isKandhuvatti ? 'Principal Due' : 'Principal' }}</th>
                <th class="text-end">{{ $isKandhuvatti ? 'Interest Due' : 'Interest' }}</th>
                <th class="text-end">{{ $isKandhuvatti ? 'Total Due' : 'Total EMI' }}</th>
                @if(!$isKandhuvatti)
                  <th class="text-end">Closing Balance</th>
                @endif
                <th class="text-end">Penalty</th>
                <th class="text-end">Grand Total</th>
                <th class="text-end">Paid Amount</th>
                <th class="text-end">Remaining</th>
                <th>Paid Date</th>
                <th class="text-center">Status</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              @php
                $firstUnpaid = $loanAccount->emis->where('status', '!=', 'paid')->sortBy('instalment_number')->first();
                $firstUnpaidId = $firstUnpaid ? $firstUnpaid->id : null;
                
                $latestPaidInstalment = $loanAccount->emis
                  ->filter(fn($e) => (float)$e->paid_amount > 0.001 || in_array($e->status, ['paid', 'partial']))
                  ->max('instalment_number');

                $penaltyConfig = \App\Models\LoanConfiguration::getPenaltyConfig();
                $isPenaltyActive = $penaltyConfig && $penaltyConfig->is_active;
              @endphp
              @forelse($emis as $emi)
                @php
                   // Calculate dynamic penalty if not saved to DB but applicable
                   $appliedPenalty = (float)($emi->penalty_amount ?? 0);
                   $dynamicPenalty = 0;
                   
                   if ($isPenaltyActive && $appliedPenalty <= 0 && $emi->status !== 'paid') {
                       // Resolve penalty settings: use global if set, otherwise fallback to loan account settings
                       $penaltyAmount = ($penaltyConfig->charge_value > 0) 
                           ? $penaltyConfig->charge_value 
                           : ($loanAccount->penalty ?? 0);

                       $graceDays = ($penaltyConfig->eligibility_days !== null)
                           ? $penaltyConfig->eligibility_days
                           : ($loanAccount->grace_period_days ?? 0);

                       if ($penaltyAmount > 0) {
                           $dueDate = \Carbon\Carbon::parse($emi->due_date);
                           $penaltyStartDate = $dueDate->copy()->addDays($graceDays);
                           if (\Carbon\Carbon::today()->gt($penaltyStartDate)) {
                               $dynamicPenalty = $penaltyAmount;
                           }
                       }
                   }
                   $totalPenaltyToShow = $appliedPenalty > 0 ? $appliedPenalty : $dynamicPenalty;
                @endphp
                <tr>
                <td class="text-center fw-medium">{{ $isKandhuvatti ? 'Cycle #' : '#' }}{{ $emi->instalment_number }}</td>
                <td>
                  <div class="d-flex flex-column">
                    <span>{{ $emi->due_date ? $emi->due_date->format('d-m-Y') : '-' }}</span>
                    @if($emi->status === 'overdue')
                      <span class="badge bg-label-danger mt-1" style="width: fit-content">Overdue</span>
                    @endif
                  </div>
                </td>
                @if(!$isKandhuvatti)
                  <td class="text-end">₹{{ number_format($emi->opening_balance ?? 0, 2) }}</td>
                @endif
                <td class="text-end">
                  @php
                    $displayPrincipal = $emi->principal_amount;
                  @endphp
                  ₹{{ number_format($displayPrincipal, 2) }}
                </td>
                <td class="text-end">
                  ₹{{ number_format($emi->interest_amount, 2) }}
                </td>
                <td class="text-end">
                  <strong>₹{{ number_format($isKandhuvatti ? $emi->interest_amount : $emi->total_amount, 2) }}</strong>
                </td>
                @if(!$isKandhuvatti)
                  <td class="text-end">₹{{ number_format($emi->closing_balance ?? 0, 2) }}</td>
                @endif
                <td class="text-end text-danger">
                  {{ $totalPenaltyToShow > 0 ? '₹' . number_format($totalPenaltyToShow, 2) : '-' }}
                </td>
                <td class="text-end fw-bold">
                  ₹{{ number_format(($isKandhuvatti ? $emi->interest_amount : $emi->total_amount) + $totalPenaltyToShow, 2) }}
                </td>
                <td class="text-end">
                    @php
                        if ($isKandhuvatti) {
                            $principalPaid = (float)($emi->principal_amount ?? 0);
                            $interestPaid = max(0.00, (float)($emi->paid_amount ?? 0) - $principalPaid);
                            
                            $currentPaid = (float)($emi->paid_amount ?? 0);
                            $currentRemaining = (float)($emi->pending_amount ?? 0) + ($appliedPenalty <= 0 ? $dynamicPenalty : 0);
                            $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                            
                            $inProgressPrincipalPaid = $emi->collections ? $emi->collections->where('status', 'in_progress')->where('payment_type', 'principal')->sum('amount') : 0;
                            $inProgressInterestPaid = $emi->collections ? $emi->collections->where('status', 'in_progress')->where('payment_type', '!=', 'principal')->sum('amount') : 0;
                            if ($inProgressSum > 0 && $inProgressPrincipalPaid == 0 && $inProgressInterestPaid == 0) {
                                $inProgressInterestPaid = $inProgressSum;
                            }
 
                            $displayPaid = $currentPaid + $inProgressSum;
                            $displayRemaining = max(0, $currentRemaining - $inProgressSum);
                            
                            $totalInterestPaid = $interestPaid + $inProgressInterestPaid;
                            $totalPrincipalPaid = $principalPaid + $inProgressPrincipalPaid;
                        } else {
                            $currentPaid = (float)($emi->paid_amount ?? 0);
                            $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                            $displayPaid = $currentPaid + $inProgressSum;
                            $currentRemaining = max(0, ($emi->total_amount + $totalPenaltyToShow) - $currentPaid);
                            $displayRemaining = max(0, $currentRemaining - $inProgressSum);
 
                            $interestPart = (float)($emi->interest_amount ?? 0);
                            $interestPaid = min($currentPaid, $interestPart);
                            $principalPaid = max(0.00, $currentPaid - $interestPart);
 
                            $remainingInterestPart = max(0.00, $interestPart - $interestPaid);
                            $inProgressInterestPaid = min((float)$inProgressSum, $remainingInterestPart);
                            $inProgressPrincipalPaid = max(0.00, (float)$inProgressSum - $remainingInterestPart);
 
                            $totalInterestPaid = $interestPaid + $inProgressInterestPaid;
                            $totalPrincipalPaid = $principalPaid + $inProgressPrincipalPaid;
                        }
                    @endphp
                   <div class="d-flex flex-column align-items-end">
                     @if($displayPaid > 0.01)
                       <div class="d-flex flex-column align-items-end text-end font-monospace w-100" style="font-size: 0.85rem; gap: 2px;">
                         @if($totalPrincipalPaid > 0.01)
                           <span class="text-success small fw-medium">
                             P. Paid: ₹{{ number_format($principalPaid, 2) }}
                             @if($inProgressPrincipalPaid > 0.01)
                               <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressPrincipalPaid, 2) }})</span>
                             @endif
                           </span>
                         @endif
                         @if($totalInterestPaid > 0.01)
                           <span class="text-info small fw-medium">
                             I. Paid: ₹{{ number_format($interestPaid, 2) }}
                             @if($inProgressInterestPaid > 0.01)
                               <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressInterestPaid, 2) }})</span>
                             @endif
                           </span>
                         @endif
                         <span class="fw-bold text-dark border-top pt-1 mt-1">
                           Total: ₹{{ number_format($currentPaid, 2) }}
                           @if($inProgressSum > 0.01)
                             <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressSum, 2) }})</span>
                           @endif
                         </span>
                       </div>
                     @else
                       <span class="fw-bold">-</span>
                     @endif
                     @if($emi->collections && $emi->collections->count() > 0)
                       <a href="javascript:void(0)" class="text-muted small view-history-link mt-1" 
                          data-emi-id="{{ $emi->getRouteKey() }}" 
                          data-emi-number="{{ $emi->instalment_number }}"
                          data-collections="{{ e(json_encode($emi->collections->sortByDesc('collected_at')->map(function($c) { 
                              return [
                                'date' => $c->collected_at ? $c->collected_at->format('d-m-Y') : $c->created_at->format('d-m-Y'),
                                'amount' => number_format($c->amount, 2),
                                'method' => ucfirst($c->payment_method),
                                'reference' => $c->payment_reference ?: 'N/A',
                                'type' => ucfirst($c->payment_type)
                              ];
                          }))) }}">
                         <i class="ri-history-line me-1"></i>History
                       </a>
                     @endif
                   </div>
                 </td>
                 <td class="text-end">
                     @if($displayRemaining <= 0.01)
                       <span class="text-success fw-bold">-</span>
                     @else
                       <span class="text-danger fw-bold">
                         ₹{{ number_format($displayRemaining, 2) }}
                         @if($inProgressSum > 0)
                           <br><small class="text-muted text-decoration-line-through" style="font-size: 0.75rem;">₹{{ number_format($currentRemaining, 2) }}</small>
                         @endif
                       </span>
                     @endif
                 </td>
                <td>
                  {{ $emi->paid_date ? $emi->paid_date->format('d-m-Y') : '-' }}
                </td>
                <td class="text-center">
                  @if($emi->status === 'paid')
                    <span class="badge bg-label-success">Paid</span>
                  @elseif($inProgressSum > 0)
                    @if($displayRemaining <= 0.01)
                      <span class="badge bg-label-warning text-warning" title="Awaiting Admin Verification">Paid (Unverified)</span>
                    @else
                      <span class="badge bg-label-info text-info" title="Awaiting Admin Verification">Partial (Unverified)</span>
                    @endif
                  @elseif($emi->status === 'partial')
                    <span class="badge bg-label-info">Partial</span>
                  @elseif($emi->status === 'overdue')
                    <span class="badge bg-label-danger">Overdue</span>
                  @else
                    <span class="badge bg-label-warning">Pending</span>
                  @endif
                </td>
                <td class="text-center">
                  <div class="d-flex justify-content-center gap-2">
                    @if(in_array($emi->status, ['pending', 'overdue', 'partial']))
                      @php
                        $paidAmount = $emi->paid_amount ?? 0;
                        $remainingAmount = max(0, ($emi->total_amount + ($emi->penalty_amount ?? 0)) - $paidAmount);
                        
                        // New Sequential Lock Logic:
                        // 1. Only the GLOBAL first unpaid EMI can be paid.
                        // 2. If it's Pending, it's unlocked if it's NOT the first-ever EMI (because previous is paid) 
                        //    OR if it's within the 3-day window.
                        // 3. If it's Overdue or Partial, it's always unlocked.
                        
                        $isFirstUnpaidGlobal = (isset($firstUnpaidInstalment) && $emi->instalment_number == $firstUnpaidInstalment);
                        $canPay = $isFirstUnpaidGlobal || in_array($emi->status, ['partial', 'overdue']);

                        $hasRejectedCollection = $emi->collections && $emi->collections->where('status', 'rejected')->isNotEmpty();
                        $rejectedCollection = $hasRejectedCollection ? $emi->collections->where('status', 'rejected')->sortByDesc('collected_at')->first() : null;
                        $isAdminOrStaff = auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff');
                        $isAgent = auth()->user()->hasRole('Agent');
                        $canRePay = $isAdminOrStaff || ($isAgent && $rejectedCollection && auth()->user()->agent && $rejectedCollection->agent_id == auth()->user()->agent->id);
                      @endphp

                      @if($hasRejectedCollection)
                        @if($canRePay)
                          @php
                            // Calculate EMI pending: total due minus verified payments
                            $verifiedPaidForEmi = $emi->collections ? $emi->collections->where('status', 'verified')->sum('amount') : 0;
                            $emiTotalDue = (float)$emi->total_amount + (float)$emi->penalty_amount;
                            $emiPendingForRepay = max(0, $emiTotalDue - $verifiedPaidForEmi);
                          @endphp
                          <button type="button" class="btn btn-sm btn-warning btn-replay-pay" 
                                  data-id="{{ $rejectedCollection->getRouteKey() }}"
                                  data-amount="{{ $rejectedCollection->amount }}"
                                  data-emi-pending="{{ $emiPendingForRepay }}"
                                  data-emi-no="{{ $emi->instalment_number }}">
                            <i class="ri-refresh-line"></i> Re-Pay ₹{{ number_format(min($rejectedCollection->amount, $emiPendingForRepay), 2) }}                          </button>
                        @else
                          <button type="button" class="btn btn-sm btn-secondary" disabled title="Payment was rejected. Only the collecting Agent or Admin can repay.">
                            <i class="ri-lock-line"></i> Locked
                          </button>
                        @endif
                      @elseif($inProgressSum > 0 && $displayRemaining <= 0.01)
                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Payment submitted, awaiting admin verification.">
                          <i class="ri-lock-line"></i> Locked
                        </button>
                      @elseif($canPay)
                        <button type="button" class="btn btn-sm btn-primary pay-emi-btn" 
                                data-emi-id="{{ $emi->getRouteKey() }}"
                                data-emi-number="{{ $emi->instalment_number }}"
                                data-total-amount="{{ $emi->total_amount }}"
                                data-interest-amount="{{ $emi->interest_amount }}"
                                data-principal-amount="{{ $emi->principal_amount ?? 0 }}"
                                data-paid-amount="{{ $displayPaid }}"
                                data-remaining-amount="{{ $displayRemaining }}"
                                data-penalty-amount="{{ $totalPenaltyToShow ?? 0 }}"
                                data-is-kandhuvatti="{{ $isKandhuvatti ? 'true' : 'false' }}">
                          <i class="ri-money-dollar-circle-line"></i> Pay
                        </button>
 
                        @if($partialPaymentConfig && $partialPaymentConfig->is_active)
                          <button type="button" class="btn btn-sm btn-info partial-payment-btn" 
                                  data-emi-id="{{ $emi->getRouteKey() }}"
                                  data-emi-number="{{ $emi->instalment_number }}"
                                  data-total-amount="{{ $emi->total_amount }}"
                                  data-interest-amount="{{ $emi->interest_amount }}"
                                  data-paid-amount="{{ $displayPaid }}"
                                  data-principal-amount="{{ $emi->principal_amount ?? 0 }}"
                                  data-previous-balance="{{ $emi->previous_balance ?? 0 }}"
                                  data-penalty-amount="{{ $totalPenaltyToShow ?? 0 }}"
                                  data-min-percentage="{{ $partialPaymentConfig && $partialPaymentConfig->is_active ? ($partialPaymentConfig->minimum_partial_percentage ?? 10) : 0 }}"
                                  data-partial-timing="{{ $partialPaymentConfig->partial_payment_timing ?? 'anytime' }}"
                                  data-penalty-method="{{ $partialPaymentConfig->penalty_calculation_method ?? 'emi_amount' }}"
                                  data-is-kandhuvatti="{{ $isKandhuvatti ? 'true' : 'false' }}"
                                  data-outstanding-principal="{{ $loanAccount->outstanding_amount }}">
                            <i class="ri-percent-line"></i>
                          </button>
                        @endif
                      @else
                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Please pay the previous EMI first or wait until 3 days before the due date.">
                          <i class="ri-time-line"></i> Locked
                        </button>
                      @endif
                    @endif

                    @if($emi->status === 'paid' || $emi->status === 'partial')
                      <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="ri-printer-line me-1"></i> Print
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                          <li>
                            <a href="{{ route('print-receipt', $emi->getRouteKey()) }}" target="_blank" class="dropdown-item py-2">
                              🧾 Payment Receipt
                            </a>
                          </li>
                          <li>
                            <a href="{{ route('print-statement', $emi->getRouteKey()) }}" target="_blank" class="dropdown-item py-2">
                              📊 Account Statement
                            </a>
                          </li>
                        </ul>
                      </div>
                    @endif
                    
                    @if(auth()->user()->hasRole('Admin') && in_array($emi->status, ['paid', 'partial', 'overdue']) && (float)$emi->paid_amount > 0.001 && $emi->instalment_number == $latestPaidInstalment)
                      <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-undo-payment rounded-pill" 
                              data-emi-id="{{ $emi->getRouteKey() }}" 
                              data-instalment="{{ $emi->instalment_number }}"
                              title="Undo Payment">
                        <i class="icon-base ri ri-history-line icon-20px"></i>
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="{{ $isKandhuvatti ? 12 : 14 }}" class="text-center text-muted py-4">
                  No EMI records found
                </td>
              </tr>
              @endforelse
            </tbody>
            @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
            <tfoot class="table-light">
              <tr>
                <td colspan="12" class="text-end fw-bold py-3 text-danger">
                  Unallocated Principal: ₹{{ number_format($loanAccount->outstanding_amount, 2) }} (Carry Forward)
                </td>
              </tr>
            </tfoot>
            @endif
          </table>

        
        <!-- Pagination -->
        <div class="d-flex justify-content-end mt-2">
          <div>
            {{ $emis->links('pagination::bootstrap-5') }}
          </div>
        </div>
      </div>

      <!-- Documents Tab -->
      <!-- <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Loan Documents</h6>
        </div>
        <div class="table-responsive">
          <table class="table table-hover border">
            <thead class="table-light">
              <tr>
                <th style="width: 80px;">S.No</th>
                <th>Document Name</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @php
                $docs = [
                  ['name' => 'Loan Sanction Letter', 'type' => 'sanction_letter'],
                  ['name' => 'Repayment Schedule', 'type' => 'repayment_schedule'],
                  ['name' => 'Loan Agreement', 'type' => 'loan_agreement'],
                  ['name' => 'Loan Statement', 'type' => 'loan_statement'],
                  ['name' => 'Payment Receipt', 'type' => 'payment_receipt']
                ];
              @endphp
              @foreach($docs as $index => $doc)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td class="fw-medium text-heading">{{ $doc['name'] }}</td>
                <td class="text-center">
                  <div class="d-flex justify-content-center gap-2">
                    @if($loanAccount && $loanAccount->id)
                      <a href="{{ route('client-loan-document-view', ['loanId' => $loanAccount->id, 'documentType' => $doc['type']]) }}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View Document">
                        <i class="icon-base ri ri-eye-line icon-20px"></i>
                      </a>
                      <a href="{{ route('client-loan-document-download', ['loanId' => $loanAccount->id, 'documentType' => $doc['type']]) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Download PDF">
                        <i class="icon-base ri ri-download-2-line icon-20px"></i>
                      </a>
                    @else
                      <span class="text-muted small">N/A</span>
                    @endif
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div> -->



      </div>
    </div>
  </div>
</div>

<!-- EMI Payment Modal -->
<div class="modal fade" id="payEmiModal" tabindex="-1" aria-labelledby="payEmiModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="payEmiModalLabel">
          <i class="icon-base ri ri-money-dollar-circle-line me-2"></i>
          Pay EMI #<span id="modalEmiNumber"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="payEmiForm">
        <div class="modal-body">
          <input type="hidden" id="emiId" name="emi_id">
          
          <!-- Total EMI Amount (Read-only) -->
          <div class="mb-3">
            <label for="totalEmiAmount" class="form-label" id="totalEmiAmountLabel">{{ $isKandhuvatti ? 'Cycle Interest' : 'Total EMI Amount' }}</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="text" class="form-control" id="totalEmiAmount" readonly>
            </div>
          </div>

          <!-- Interest Portion (Kandhuvatti Only, Read-only) -->
          <div class="mb-3 d-none" id="interestPortionGroup">
            <label for="interestPortion" class="form-label">Interest Portion</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="text" class="form-control" id="interestPortion" readonly>
            </div>
          </div>

          <!-- Principal Portion (Kandhuvatti Only, Read-only) -->
          <div class="mb-3 d-none" id="principalPortionGroup">
            <label for="principalPortion" class="form-label">Principal Portion</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="text" class="form-control" id="principalPortion" readonly>
            </div>
          </div>

          <!-- Penalty Amount (Read-only) -->
          <div class="mb-3">
            <label for="penaltyAmount" class="form-label">Penalty Amount</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="text" class="form-control" id="penaltyAmount" readonly>
            </div>
          </div>

          <!-- Amount to Pay -->
          <div class="mb-3">
            <label for="paidAmount" class="form-label">Total Amount to Pay <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" step="0.01" class="form-control" id="paidAmount" name="paid_amount" 
                     placeholder="Enter amount" required>
            </div>
            <small class="text-muted" id="paidAmountHelp">Enter the total amount being paid</small>
          </div>

          <!-- Principal Amount (Kandhuvatti Only) -->
          <div class="mb-3 d-none" id="principalAmountGroup">
            <label for="principalAmount" class="form-label">Principal Repayment (Optional)</label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" step="0.01" class="form-control" id="principalAmount" name="principal_amount" 
                     placeholder="Enter principal portion">
            </div>
            <small class="text-info">If entered, this amount will reduce the outstanding principal and recalculate future interest.</small>
          </div>

          <!-- Paid Date -->
          <div class="mb-3">
            <label for="paidDate" class="form-label">Paid Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="paidDate" name="paid_date" required>
          </div>

          <!-- Payment Method -->
          <div class="mb-3">
            <label for="paymentMethod" class="form-label">Payment Method <span class="text-danger">*</span></label>
            <select class="form-select" id="paymentMethod" name="payment_method" required>
              <option value="">Select Payment Method</option>
              @if(auth()->user()->hasRole('Agent'))
                <option value="in_hand" selected>Agent In-Hand</option>
                <option value="upi">UPI</option>
                <option value="bank_transfer">Bank Transfer</option>
              @else
                <option value="in_hand" selected>Admin In-Hand</option>
                <option value="upi">Admin UPI</option>
                <option value="bank_transfer">Admin Bank Transfer</option>
              @endif
            </select>
          </div>

          <!-- Payment Reference -->
          <div class="mb-3">
            <label for="paymentReference" class="form-label">Payment Reference</label>
            <input type="text" class="form-control" id="paymentReference" name="payment_reference" placeholder="e.g. UPI ID, Txn ID">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-{{ auth()->user()->hasRole('Agent') ? 'warning' : 'primary' }}" id="submitPaymentBtn">
            <i class="icon-base ri ri-{{ auth()->user()->hasRole('Agent') ? 'send-plane-line' : 'check-line' }} me-1"></i>
            {{ auth()->user()->hasRole('Agent') ? 'Submit for Approval' : 'Pay Now' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Partial Payment Modal -->
<div class="modal fade" id="partialPaymentModal" tabindex="-1" aria-labelledby="partialPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background-color: #26c6da; color: white;">
        <h5 class="modal-title" id="partialPaymentModalLabel">
          <i class="icon-base ri ri-percent-line me-2"></i>
          Partial Payment - EMI #<span id="partialEmiNumber"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="partialPaymentForm">
        <div class="modal-body">
          <input type="hidden" id="partialEmiId" name="emi_id">
          <input type="hidden" id="partialLoanAccountId" name="loan_account_id" value="{{ $loanAccount->id }}">
           
          <!-- EMI Details Section -->
          <div class="row g-3 mb-4">
            <div class="col-md-4" id="partialTotalEmiGroup">
              <div class="p-3" style="background-color: #e0f7fa; border-radius: 8px;">
                <small class="text-muted d-block mb-1" id="partialTotalEmiLabel">{{ $isKandhuvatti ? 'Cycle Interest' : 'Total EMI Amount' }}</small>
                <h6 class="mb-0" id="partialTotalEmiDisplay">₹0.00</h6>
                <input type="hidden" id="partialTotalEmi">
              </div>
            </div>
            <div class="col-md-4" id="partialPaidAmountGroup">
              <div class="p-3" style="background-color: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
                <small class="text-muted d-block mb-1" id="partialPaidAmountLabel">Already Paid</small>
                <h6 class="mb-0 text-success" id="partialPaidAmountDisplay">₹0.00</h6>
                <input type="hidden" id="partialPaidAmount">
              </div>
            </div>
            <div class="col-md-4" id="partialTotalDueGroup">
              <div class="p-3" style="background-color: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">
                <small class="text-muted d-block mb-1" id="partialTotalDueLabel">Remaining Amount</small>
                <h6 class="mb-0 text-danger" id="partialTotalDueDisplay">₹0.00</h6>
                <input type="hidden" id="partialTotalDue">
              </div>
            </div>

            <!-- Kandhuvatti Breakdown Cards -->
            <div class="col-md-6 d-none" id="partialInterestPortionCard">
              <div class="p-3" style="background-color: #f1f8e9; border-radius: 8px; border-left: 4px solid #8bc34a;">
                <small class="text-muted d-block mb-1">Interest Portion</small>
                <h6 class="mb-0 text-dark" id="partialInterestPortionDisplay">₹0.00</h6>
              </div>
            </div>
            <div class="col-md-6 d-none" id="partialPrincipalPortionCard">
              <div class="p-3" style="background-color: #fffde7; border-radius: 8px; border-left: 4px solid #fbc02d;">
                <small class="text-muted d-block mb-1">Principal Portion</small>
                <h6 class="mb-0 text-dark" id="partialPrincipalPortionDisplay">₹0.00</h6>
              </div>
            </div>
            <div class="col-md-6 d-none" id="partialRemainingInterestCard">
              <div class="p-3" style="background-color: #efebe9; border-radius: 8px; border-left: 4px solid #795548;">
                <small class="text-muted d-block mb-1">Remaining Interest Due</small>
                <h6 class="mb-0 text-danger" id="partialRemainingInterestDisplay">₹0.00</h6>
              </div>
            </div>
            <div class="col-md-6 d-none" id="partialPrincipalPaidCard">
              <div class="p-3" style="background-color: #ede7f6; border-radius: 8px; border-left: 4px solid #673ab7;">
                <small class="text-muted d-block mb-1">Principal Already Paid</small>
                <h6 class="mb-0 text-primary" id="partialPrincipalPaidDisplay">₹0.00</h6>
              </div>
            </div>

            <!-- Principal Balance Group (Kandhuvatti Only) -->
            <div class="col-12 d-none" id="partialPrincipalGroup">
              <div class="p-3" style="background-color: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">
                <small class="text-muted d-block mb-1">Current Principal Balance</small>
                <h6 class="mb-0 text-danger" id="partialPrincipalDisplay">₹0.00</h6>
              </div>
            </div>
          </div>

          <!-- Payment Amount -->
          <div class="mb-3">
            <label for="partialPaymentAmount" class="form-label" id="partialPaymentAmountLabel">
              Partial Payment Amount <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" step="1" class="form-control" id="partialPaymentAmount" 
                     name="partial_amount" placeholder="Enter amount" required>
            </div>
            <small class="text-muted" id="partialMinAmountHelp">Minimum: ₹0.00</small>
            <div class="invalid-feedback" id="partialAmountError"></div>
          </div>

          <!-- Partial Principal Repayment (Kandhuvatti Only) -->
          <div class="mb-3 d-none" id="partialPrincipalAmountGroup">
            <label for="partialPrincipalAmount" class="form-label">
              Partial Principal Repayment (Optional)
            </label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" step="1" class="form-control" id="partialPrincipalAmount" 
                     name="principal_amount" placeholder="Enter principal portion">
            </div>
            <small class="text-info">Reduces the outstanding principal balance and recalculates future interest.</small>
          </div>

          <!-- Payment Date -->
          <div class="mb-3">
            <label for="partialPaymentDate" class="form-label">Payment Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="partialPaymentDate" name="payment_date" 
                   value="{{ date('Y-m-d') }}" required>
          </div>

          <!-- Payment Method -->
          <div class="mb-3">
            <label for="partialPaymentMethod" class="form-label">Payment Method <span class="text-danger">*</span></label>
            <select class="form-select" id="partialPaymentMethod" name="payment_method" required>
              <option value="">Select Payment Method</option>
              @if(auth()->user()->hasRole('Agent'))
                <option value="in_hand" selected>Agent In-Hand</option>
                <option value="upi">UPI</option>
                <option value="bank_transfer">Bank Transfer</option>
              @else
                <option value="in_hand" selected>Admin In-Hand</option>
                <option value="upi">Admin UPI</option>
                <option value="bank_transfer">Admin Bank Transfer</option>
              @endif
            </select>
          </div>

          <!-- Payment Reference -->
          <div class="mb-3">
            <label for="partialPaymentReference" class="form-label">Payment Reference</label>
            <input type="text" class="form-control" id="partialPaymentReference" 
                   name="payment_reference" placeholder="Transaction ID / Reference Number">
          </div>
        </div>
        <div class="modal-footer justify-content-end">
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" class="btn btn-info px-4 ms-2" id="submitPartialPaymentBtn" style="background-color: #26c6da; border-color: #26c6da;">
            <i class="icon-base ri ri-check-line me-1"></i>
            Process Payment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-weight-bold">
          <i class="ri-history-line me-2"></i>Payment History - EMI #<span id="historyEmiNumber"></span>
          <span id="historyEmiStatus" class="ms-2"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-flush">
            <thead class="table-light">
              <tr>
                <th class="px-4">Date</th>
                <th class="text-end px-4">Amount</th>
                <th class="px-4">Approved By</th>
                <th class="px-4">Method</th>
                <th class="px-4">Reference</th>
                <th class="px-4">Status</th>
                @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
                  <th class="px-4 text-center">Action</th>
                @endif
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <!-- Content injected via JS -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection
 