<div class="row g-4 mb-4">
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Processing Fee</span>
        <h4 class="mb-0 fw-bold mt-2">₹{{ number_format($totals['processing_fee'] ?? 0, 2) }}</h4>
        <small class="text-muted">Maturity / closure / premature fees</small>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Doc Charges</span>
        <h4 class="mb-0 fw-bold mt-2">₹{{ number_format($totals['document_charges'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Other Charges</span>
        <h4 class="mb-0 fw-bold mt-2">₹{{ number_format($totals['other_charges'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Premature Penalty</span>
        <h4 class="mb-0 fw-bold mt-2 text-danger">₹{{ number_format($totals['penalty_collected'] ?? 0, 2) }}</h4>
        <small class="text-muted">Income from early withdrawal penalties</small>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card shadow-sm border-0 text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <span class="text-white-50 small fw-bold text-uppercase">Overall FD Revenue</span>
          <h3 class="mb-0 fw-bold text-white mt-1">₹{{ number_format($overallTotalRevenue, 2) }}</h3>
        </div>
        <small class="text-white-50">Processing, document, other charges &amp; penalties</small>
      </div>
    </div>
  </div>
</div>
