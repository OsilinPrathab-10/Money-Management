@extends('layouts/layoutMaster')

@section('title', 'EMI Calculator')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/apex-charts/apex-charts.scss'
])
@endsection

@section('page-style')
@vite([
  'resources/assets/vendor/scss/pages/page-misc.scss'
])
<style>
.emi-calculator-card {
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.slider-container {
  margin: 15px 0 20px 0;
}

.slider-label {
  font-size: 14px;
  color: #6c757d;
  margin-bottom: 8px;
  font-weight: 500;
}

.slider-value {
  background: #e8f5e9;
  color: #00a86b;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 16px;
  display: inline-block;
  min-width: 120px;
  text-align: center;
}

.form-range {
  height: 6px;
  border-radius: 3px;
  background: linear-gradient(to right, #00a86b 0%, #e0e0e0 0%);
}

.form-range::-webkit-slider-thumb {
  width: 20px;
  height: 20px;
  background: #00a86b;
  border: 3px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.form-range::-moz-range-thumb {
  width: 20px;
  height: 20px;
  background: #00a86b;
  border: 3px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.result-item {
  padding: 16px 0;
  border-bottom: 1px solid #f0f0f0;
}

.result-item:last-child {
  border-bottom: none;
}

.result-label {
  font-size: 14px;
  color: #6c757d;
  margin-bottom: 4px;
}

.result-value {
  font-size: 20px;
  font-weight: 600;
  color: #2c3e50;
}

.chart-legend {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-top: 20px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.legend-color.principal {
  background: #44d7b6;
}

.legend-color.interest {
  background: #5b6ef8;
}

.amortization-section {
  margin-top: 20px;
}

.amortization-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.year-selector {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 8px 16px;
  cursor: pointer;
  background: white;
  font-weight: 500;
}

.amortization-table {
  width: 100%;
  border-collapse: collapse;
}

.amortization-table th {
  background: #f8f9fa;
  padding: 12px;
  text-align: left;
  font-weight: 600;
  font-size: 13px;
  color: #6c757d;
  border-bottom: 2px solid #e0e0e0;
}

.amortization-table td {
  padding: 12px;
  border-bottom: 1px solid #f0f0f0;
  font-size: 14px;
}

.amortization-table tr:hover {
  background: #f8f9fa;
}

#yearDropdown {
  max-height: 300px;
  overflow-y: auto;
}
</style>
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/apex-charts/apexcharts.js'
])
@endsection

@section('page-script')
@vite([
  'resources/assets/custom-js/emi-calculator.js'
])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <h4 class="mb-4">
    EMI Calculator
  </h4>

  <div class="row">
    <!-- Left Column - Input Controls -->
    <div class="col-lg-6 col-12 mb-4">
      <div class="card emi-calculator-card">
        <div class="card-body">
          <h5 class="card-title mb-4">Calculate EMI for Loan</h5>

          <!-- Loan Amount Slider -->
          <div class="slider-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="slider-label">Loan amount</span>
              <span class="slider-value" id="loanAmountDisplay">₹ 2,410,000</span>
            </div>
            <input type="range" class="form-range" id="loanAmount" min="10000" max="10000000" step="10000" value="2410000">
          </div>

          <!-- Interest Rate Slider -->
          <div class="slider-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="slider-label">Rate of interest (p.a)</span>
              <span class="slider-value" id="interestRateDisplay">5.5 %</span>
            </div>
            <input type="range" class="form-range" id="interestRate" min="0.5" max="30" step="0.1" value="5.5">
          </div>

          <!-- Repayment Frequency -->
          <div class="mb-4">
            <label class="form-label" for="repaymentFrequency">Repayment Frequency</label>
            <select class="form-select" id="repaymentFrequency">
              <option value="monthly">Monthly</option>
              <option value="weekly" selected>Weekly</option>
              <option value="daily">Daily</option>
            </select>
          </div>

          <!-- Loan Tenure Slider -->
          <div class="slider-container">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="slider-label" id="tenureLabel">Loan tenure</span>
              <span class="slider-value" id="tenureDisplay">12 Weeks</span>
            </div>
            <input type="range" class="form-range" id="tenure" min="1" max="104" step="1" value="12">
          </div>

          <!-- Results -->
          <div class="mt-2">
            <div class="result-item">
              <div class="result-label">Monthly EMI</div>
              <div class="result-value" id="monthlyEmi">₹4,60,338</div>
            </div>
            <div class="result-item">
              <div class="result-label">Principal amount</div>
              <div class="result-value" id="principalAmount">₹2,41,00,000</div>
            </div>
            <div class="result-item">
              <div class="result-label">Total interest</div>
              <div class="result-value" id="totalInterest">₹35,20,281</div>
            </div>
            <div class="result-item">
              <div class="result-label">Total amount</div>
              <div class="result-value" id="totalAmount">₹2,76,20,281</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column - Chart -->
    <div class="col-lg-6 col-12 mb-4">
      <div class="card emi-calculator-card">
        <div class="card-body">
          <div class="chart-legend">
            <div class="legend-item">
              <span class="legend-color principal"></span>
              <span>Principal amount</span>
            </div>
            <div class="legend-item">
              <span class="legend-color interest"></span>
              <span>Interest amount</span>
            </div>
          </div>
          <div id="emiChart"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Amortization Schedule -->
  <div class="row">
    <div class="col-12">
      <div class="card emi-calculator-card">
        <div class="card-body">
          <div class="amortization-section">
            <div class="amortization-header">
              <h5 class="mb-0">Your Amortization Details (Yearly/Monthly)</h5>
              <div class="dropdown">
                <button class="year-selector dropdown-toggle" type="button" id="yearDropdownButton" data-bs-toggle="dropdown" aria-expanded="false">
                  <span id="selectedYear">2025</span>
                </button>
                <ul class="dropdown-menu" id="yearDropdown" aria-labelledby="yearDropdownButton">
                  <!-- Years will be populated dynamically -->
                </ul>
              </div>
            </div>

            <div class="table-responsive">
              <table class="amortization-table">
                <thead>
                  <tr>
                    <th>Month</th>
                    <th>Due Date</th>
                    <th>EMI Amount</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Balance</th>
                  </tr>
                </thead>
                <tbody id="amortizationTableBody">
                  <!-- Table rows will be populated dynamically -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
