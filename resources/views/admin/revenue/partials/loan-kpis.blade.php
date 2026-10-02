<div class="row g-4 mb-4">
  <div class="col-md-6 col-xl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Processing Fee</span>
        <h4 class="mb-0 fw-bold mt-2">₹{{ number_format($totals['processing_fee'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Doc Charges</span>
        <h4 class="mb-0 fw-bold mt-2">₹{{ number_format($totals['document_charges'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Other Charges</span>
        <h4 class="mb-0 fw-bold mt-2">₹{{ number_format($totals['other_charges'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Interest Collected</span>
        <h4 class="mb-0 fw-bold mt-2 text-success">₹{{ number_format($totals['interest_collected'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Foreclose Revenue</span>
        <h4 class="mb-0 fw-bold mt-2 text-warning">₹{{ number_format($totals['foreclosure_revenue'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <span class="text-muted small fw-semibold text-uppercase">Penalty</span>
        <h4 class="mb-0 fw-bold mt-2 text-danger">₹{{ number_format($totals['penalty_collected'] ?? 0, 2) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card shadow-sm border-0 text-white" style="background: linear-gradient(135deg, #696cff 0%, #3f3dbe 100%);">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <span class="text-white-50 small fw-bold text-uppercase">Overall Loan Revenue</span>
          <h3 class="mb-0 fw-bold text-white mt-1">₹{{ number_format($overallTotalRevenue, 2) }}</h3>
        </div>
        <small class="text-white-50">Fees, charges, interest, foreclose &amp; penalty</small>
      </div>
    </div>
  </div>
</div>
