<!-- Manual interest per cycle (Open Loan + Manual only) -->
<div class="col-12 manual-interest-wrapper" style="display: none;">
  <div class="p-3 border border-warning border-opacity-50 rounded-3">
    <div class="row g-3">
      <div class="col-md-7">
        <label class="form-label fw-bold manual-interest-label" for="manual_interest_amount">Monthly Interest Amount (₹) <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-light fw-bold">₹</span>
          <input type="number" class="form-control form-control-lg manual-interest-input" id="manual_interest_amount" name="manual_interest_amount" placeholder="Interest collected every month" min="0.01" step="0.01">
          <span class="input-group-text manual-interest-unit">/ month</span>
        </div>
      </div>
      <div class="col-md-5">
        <label class="form-label fw-bold manual-tenure-label" for="manual_tenure">Tenure (Months) <span class="text-muted fw-normal small">Optional</span></label>
        <div class="input-group">
          <input type="number" class="form-control form-control-lg manual-tenure-input" id="manual_tenure" name="manual_tenure" placeholder="Leave blank for open" min="1" step="1">
          <span class="input-group-text manual-tenure-unit">months</span>
        </div>
      </div>
    </div>
    <small class="text-muted manual-interest-help">Collected every month on the full loan amount. When principal is repaid, interest reduces proportionally.</small>
  </div>
</div>
