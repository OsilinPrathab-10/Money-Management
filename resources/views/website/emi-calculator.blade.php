@extends('website.layouts.master')

@section('title', 'EMI Calculator — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Calculate your monthly EMI, total interest, and loan repayment schedule instantly with ' . ($brand['name'] ?? 'Star Finance') . ' loan EMI calculator.')

@push('styles')
<style>
.calc-card {
  background: #fff;
  border-radius: 1.5rem;
  box-shadow: 0 12px 40px rgba(11,31,58,.08);
  border: 1px solid #e8edf5;
  overflow: hidden;
}
.slider-wrap {
  position: relative;
}
.calc-slider {
  width: 100%;
  height: 8px;
  border-radius: 999px;
  background: #e2e8f0;
  accent-color: #1e4fd6;
  cursor: pointer;
}
.calc-slider::-webkit-slider-thumb {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #1e4fd6;
  box-shadow: 0 4px 12px rgba(30,79,214,.4);
  cursor: pointer;
  border: 3px solid #fff;
}
.chip-btn {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: .78rem;
  font-weight: 600;
  padding: .25rem .75rem;
  border-radius: 999px;
  transition: all .2s;
  cursor: pointer;
}
.chip-btn:hover, .chip-btn.active {
  background: #e8f0ff;
  border-color: #1e4fd6;
  color: #1e4fd6;
}
.freq-pill-group { display: flex; gap: .5rem; flex-wrap: wrap; }
.freq-pill {
  flex: 1;
  min-width: 5.5rem;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: .78rem;
  font-weight: 700;
  padding: .5rem .75rem;
  border-radius: .75rem;
  text-transform: uppercase;
  letter-spacing: .03em;
  transition: all .2s;
  cursor: pointer;
}
.freq-pill:hover { border-color: #93c5fd; background: #eff6ff; color: #1e4fd6; }
.freq-pill.active {
  border-color: #1e4fd6;
  background: linear-gradient(135deg, #1e4fd6 0%, #2563eb 100%);
  color: #fff;
  box-shadow: 0 6px 18px rgba(30, 79, 214, 0.28);
}
.live-calc-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .72rem;
  font-weight: 700;
  color: #059669;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 999px;
  padding: .2rem .65rem;
}
.live-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
  animation: emiPulse 1.6s infinite;
}
@keyframes emiPulse {
  0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(16,185,129,.6); }
  70% { opacity: .85; box-shadow: 0 0 0 6px rgba(16,185,129,0); }
}
.donut-chart-container {
  width: 180px;
  height: 180px;
  border-radius: 50%;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  box-shadow: inset 0 0 15px rgba(0,0,0,.05);
}
.donut-hole {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(0,0,0,.06);
}
.result-card {
  background: linear-gradient(135deg, #0b1f3a 0%, #1535a0 100%);
  color: #fff;
  border-radius: 1.25rem;
  padding: 2rem;
}
</style>
@endpush

@section('content')
{{-- Hero banner --}}
<section class="sf-page-hero">
  <div class="container text-center">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb justify-content-center mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">EMI Calculator</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.35rem)">EMI calculator</h1>
    <p class="opacity-90 mb-0 mx-auto" style="max-width:560px">Plan daily, weekly, or monthly instalments with the same reducing-balance formula used in our loan module.</p>
  </div>
</section>

{{-- Calculator Section --}}
<section class="container py-5 my-2">
  <div class="calc-card p-4 p-lg-5 mb-5">
    <div class="row g-5">
      {{-- Controls Left --}}
      <div class="col-lg-7">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
          <h4 class="fw-800 text-dark mb-0 d-flex align-items-center gap-2">
            <i class="ri-sound-module-line text-primary"></i> Loan Parameters
          </h4>
          <span class="live-calc-badge"><span class="live-dot" aria-hidden="true"></span> LIVE calculation</span>
        </div>

        <label class="form-label fw-bold text-secondary small d-block mb-2">REPAYMENT FREQUENCY</label>
        <div class="freq-pill-group mb-4" role="group" aria-label="Repayment frequency">
          <button type="button" class="freq-pill" data-freq="daily">Daily</button>
          <button type="button" class="freq-pill" data-freq="weekly">Weekly</button>
          <button type="button" class="freq-pill active" data-freq="monthly">Monthly</button>
        </div>

        {{-- Loan Amount --}}
        <div class="mb-4 pb-2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-bold mb-0 text-secondary small">LOAN AMOUNT</label>
            <div class="input-group" style="width: 170px;">
              <span class="input-group-text bg-light border-end-0 fw-bold text-primary">₹</span>
              <input type="number" id="amtInput" class="form-control fw-bold border-start-0 text-end" value="500000" min="10000" max="10000000" step="10000">
            </div>
          </div>
          <input type="range" class="calc-slider" id="amtSlider" min="10000" max="5000000" step="10000" value="500000">
          <div class="d-flex justify-content-between text-muted mt-1" style="font-size:.72rem">
            <span>₹10,000</span>
            <span>₹25,00,000</span>
            <span>₹50,00,000</span>
          </div>
          <div class="d-flex gap-2 mt-2 flex-wrap">
            <button type="button" class="chip-btn" onclick="setAmount(100000)">₹1 Lakh</button>
            <button type="button" class="chip-btn" onclick="setAmount(300000)">₹3 Lakh</button>
            <button type="button" class="chip-btn active" onclick="setAmount(500000)">₹5 Lakh</button>
            <button type="button" class="chip-btn" onclick="setAmount(1000000)">₹10 Lakh</button>
            <button type="button" class="chip-btn" onclick="setAmount(2000000)">₹20 Lakh</button>
          </div>
        </div>

        {{-- Interest Rate --}}
        <div class="mb-4 pb-2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-bold mb-0 text-secondary small">INTEREST RATE (% P.A.)</label>
            <div class="input-group" style="width: 130px;">
              <input type="number" id="rateInput" class="form-control fw-bold border-end-0 text-end" value="12" min="1" max="36" step="0.25">
              <span class="input-group-text bg-light border-start-0 text-muted fw-bold">%</span>
            </div>
          </div>
          <input type="range" class="calc-slider" id="rateSlider" min="7" max="30" step="0.25" value="12">
          <div class="d-flex justify-content-between text-muted mt-1" style="font-size:.72rem">
            <span>7%</span>
            <span>18%</span>
            <span>30%</span>
          </div>
          <div class="d-flex gap-2 mt-2 flex-wrap">
            <button type="button" class="chip-btn" onclick="setRate(9.5)">9.5% (Gold)</button>
            <button type="button" class="chip-btn active" onclick="setRate(12)">12% (Standard)</button>
            <button type="button" class="chip-btn" onclick="setRate(14.5)">14.5% (Personal)</button>
            <button type="button" class="chip-btn" onclick="setRate(18)">18% (Business)</button>
          </div>
        </div>

        {{-- Tenure --}}
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <label class="form-label fw-bold mb-0 text-secondary small" id="tenureBlockLabel">TENURE (MONTHS)</label>
            <div class="input-group" style="width: 140px;">
              <input type="number" id="tenureInput" class="form-control fw-bold border-end-0 text-end" value="36" min="1" max="360" step="1">
              <span class="input-group-text bg-light border-start-0 text-muted small" id="tenureUnitLabel">Mo</span>
            </div>
          </div>
          <input type="range" class="calc-slider" id="tenureSlider" min="6" max="120" step="1" value="36">
          <div class="d-flex justify-content-between text-muted mt-1" style="font-size:.72rem">
            <span id="tenureMinText">6 Months</span>
            <span id="tenureMidText">36 Months</span>
            <span id="tenureMaxText">120 Months</span>
          </div>
          <div class="d-flex gap-2 mt-2 flex-wrap" id="tenureChipRow"></div>
        </div>
      </div>

      {{-- Results Right --}}
      <div class="col-lg-5">
        <div class="result-card h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="text-white-50 text-uppercase fw-bold small mb-1" style="letter-spacing:.05em">YOUR INSTALLMENT</div>
            <div class="d-flex align-items-baseline gap-2 mb-3">
              <span class="display-6 fw-900 text-warning" id="resEmi">₹16,607</span>
              <span class="text-white-50 small" id="resEmiUnit">/ month</span>
            </div>

            {{-- Donut Chart Visual --}}
            <div class="my-4 text-center">
              <div class="donut-chart-container" id="donutChart" style="background: conic-gradient(#1e4fd6 0% 83%, #f4b942 83% 100%);">
                <div class="donut-hole">
                  <span class="text-muted small" style="font-size:.7rem">PRINCIPAL</span>
                  <span class="fw-bold text-primary fs-5" id="donutPrincipalPct">83%</span>
                </div>
              </div>
            </div>

            {{-- Detailed Stats --}}
            <div class="p-3 rounded-3 mb-4" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15)">
              <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-white border-opacity-10">
                <span class="small text-white-50"><i class="ri-checkbox-blank-circle-fill me-1" style="color:#1e4fd6"></i> Principal Amount:</span>
                <strong class="text-white" id="resPrincipal">₹5,00,000</strong>
              </div>
              <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-white border-opacity-10">
                <span class="small text-white-50"><i class="ri-checkbox-blank-circle-fill text-warning me-1"></i> Total Interest:</span>
                <strong class="text-warning fw-bold" id="resInterest">₹97,858</strong>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="small text-white-50">Total Amount Payable:</span>
                <strong class="text-white fs-6" id="resTotal">₹5,97,858</strong>
              </div>
            </div>
          </div>

          {{-- Actions --}}
          <div>
            <a href="{{ route('website.apply') }}" id="btnApply" class="btn btn-warning btn-lg fw-bold text-dark w-100 rounded-pill mb-2 shadow">
              <i class="ri-send-plane-fill me-1"></i> Apply for this Loan
            </a>
            <div class="text-center">
              <small class="text-white-50" style="font-size:.75rem">
                <i class="ri-shield-check-line me-1"></i> Calculated using standard reducing balance method
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Amortization Preview Accordion --}}
  <div class="card sf-card p-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold text-dark mb-0"><i class="ri-calendar-todo-line text-primary me-2"></i>Repayment Schedule <span id="scheduleTitleSuffix">(first 12 installments)</span></h5>
        <small class="text-muted" id="scheduleSubtitle">Installment-by-installment principal and interest</small>
      </div>
      <button class="btn btn-sm btn-outline-primary rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#amortTableWrap">
        Toggle Schedule View <i class="ri-arrow-down-s-line"></i>
      </button>
    </div>
    <div class="collapse show" id="amortTableWrap">
      <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th id="scheduleColPeriod">#</th>
              <th>Opening Balance</th>
              <th>EMI Amount</th>
              <th>Principal Repaid</th>
              <th>Interest Charged</th>
              <th>Closing Balance</th>
            </tr>
          </thead>
          <tbody id="scheduleBody">
            {{-- Dynamic rows inserted by JS --}}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Educational FAQ / Formula Info --}}
  <div class="row g-4">
    <div class="col-md-6">
      <div class="card sf-card p-4 h-100">
        <h5 class="fw-bold mb-3 text-dark"><i class="ri-function-line text-primary me-2"></i> How EMI is Calculated</h5>
        <p class="text-muted small" style="line-height:1.8">
          The monthly Equated Monthly Installment (EMI) is calculated using the standard reducing-balance amortization mathematical formula:
        </p>
        <div class="p-3 bg-light rounded-3 text-center my-3 font-monospace fw-bold text-primary">
          EMI = [P × r × (1 + r)ⁿ] ÷ [(1 + r)ⁿ – 1]
        </div>
        <ul class="text-muted small mb-0 ps-3" style="line-height:1.8">
          <li><strong>P:</strong> Principal Loan Amount borrowed</li>
          <li><strong>r:</strong> Interest rate per period (annual ÷ 365, 52, or 12)</li>
          <li><strong>n:</strong> Number of installments (days, weeks, or months)</li>
        </ul>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card sf-card p-4 h-100">
        <h5 class="fw-bold mb-3 text-dark"><i class="ri-lightbulb-line text-warning me-2"></i> Tips to Reduce Your EMI</h5>
        <ul class="text-muted small mb-0 ps-3" style="line-height:2">
          <li><strong>Choose a higher down payment:</strong> Borrowing a smaller principal lowers both monthly installment and total interest paid.</li>
          <li><strong>Opt for optimal tenure:</strong> While a longer tenure reduces your monthly EMI, it increases total interest. Balance affordability with overall cost.</li>
          <li><strong>Maintain a good credit score:</strong> A score above 750 qualifies you for our lowest preferential interest rate brackets.</li>
          <li><strong>Part-prepayment:</strong> Making occasional lump-sum prepayments directly reduces principal and shortens repayment duration.</li>
        </ul>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(function(){
  var payFrequency = 'monthly';
  var amtInput = document.getElementById('amtInput');
  var amtSlider = document.getElementById('amtSlider');
  var rateInput = document.getElementById('rateInput');
  var rateSlider = document.getElementById('rateSlider');
  var tenureInput = document.getElementById('tenureInput');
  var tenureSlider = document.getElementById('tenureSlider');
  var tenureUnitLabel = document.getElementById('tenureUnitLabel');
  var tenureBlockLabel = document.getElementById('tenureBlockLabel');
  var tenureChipRow = document.getElementById('tenureChipRow');
  var resEmi = document.getElementById('resEmi');
  var resEmiUnit = document.getElementById('resEmiUnit');
  var resPrincipal = document.getElementById('resPrincipal');
  var resInterest = document.getElementById('resInterest');
  var resTotal = document.getElementById('resTotal');
  var donutChart = document.getElementById('donutChart');
  var donutPrincipalPct = document.getElementById('donutPrincipalPct');
  var btnApply = document.getElementById('btnApply');
  var scheduleBody = document.getElementById('scheduleBody');
  var scheduleColPeriod = document.getElementById('scheduleColPeriod');

  var chipPresets = {
    daily: [
      { v: 30, l: '30 days' },
      { v: 60, l: '60 days' },
      { v: 90, l: '90 days' },
      { v: 180, l: '6 mo' }
    ],
    weekly: [
      { v: 12, l: '12 wks' },
      { v: 26, l: '26 wks' },
      { v: 52, l: '1 year' },
      { v: 104, l: '2 yrs' }
    ],
    monthly: [
      { v: 12, l: '12 mo' },
      { v: 24, l: '24 mo' },
      { v: 36, l: '36 mo' },
      { v: 60, l: '60 mo' }
    ]
  };

  function fmt(num) {
    return '₹' + Math.round(num).toLocaleString('en-IN');
  }

  function periodsPerYear(freq) {
    if (freq === 'daily') return 365;
    if (freq === 'weekly') return 52;
    return 12;
  }

  function installmentUnit(freq) {
    if (freq === 'daily') return '/ day';
    if (freq === 'weekly') return '/ week';
    return '/ month';
  }

  function tenureMonthsEquivalent(periods, freq) {
    if (freq === 'daily') return Math.max(1, Math.ceil(periods / 30));
    if (freq === 'weekly') return Math.max(1, Math.ceil(periods / 4.345));
    return Math.max(1, periods);
  }

  function renderTenureChips(freq) {
    if (!tenureChipRow) return;
    var presets = chipPresets[freq] || chipPresets.monthly;
    tenureChipRow.innerHTML = presets.map(function(p) {
      return '<button type="button" class="chip-btn" data-tenure-val="' + p.v + '">' + p.l + '</button>';
    }).join('');
    tenureChipRow.querySelectorAll('[data-tenure-val]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var v = parseInt(btn.getAttribute('data-tenure-val'), 10);
        tenureInput.value = v;
        tenureSlider.value = v;
        tenureChipRow.querySelectorAll('.chip-btn').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        calculate();
      });
    });
  }

  function configureFrequency(freq) {
    payFrequency = freq;
    document.querySelectorAll('.freq-pill').forEach(function(b) {
      b.classList.toggle('active', b.getAttribute('data-freq') === freq);
    });

    var cur = parseInt(tenureInput.value, 10) || 1;
    if (freq === 'daily') {
      tenureBlockLabel.textContent = 'TENURE (DAYS)';
      tenureUnitLabel.textContent = 'Days';
      tenureSlider.min = 30;
      tenureSlider.max = 365;
      tenureSlider.step = 1;
      if (cur < 30 || cur > 365) { cur = 90; }
      document.getElementById('tenureMinText').textContent = '30 days';
      document.getElementById('tenureMidText').textContent = '180 days';
      document.getElementById('tenureMaxText').textContent = '365 days';
      if (scheduleColPeriod) scheduleColPeriod.textContent = 'Day #';
    } else if (freq === 'weekly') {
      tenureBlockLabel.textContent = 'TENURE (WEEKS)';
      tenureUnitLabel.textContent = 'Wks';
      tenureSlider.min = 4;
      tenureSlider.max = 260;
      tenureSlider.step = 1;
      if (cur < 4 || cur > 260) { cur = 52; }
      document.getElementById('tenureMinText').textContent = '4 weeks';
      document.getElementById('tenureMidText').textContent = '52 weeks';
      document.getElementById('tenureMaxText').textContent = '260 weeks';
      if (scheduleColPeriod) scheduleColPeriod.textContent = 'Week #';
    } else {
      tenureBlockLabel.textContent = 'TENURE (MONTHS)';
      tenureUnitLabel.textContent = 'Mo';
      tenureSlider.min = 6;
      tenureSlider.max = 120;
      tenureSlider.step = 1;
      if (cur < 6 || cur > 120) { cur = 36; }
      document.getElementById('tenureMinText').textContent = '6 months';
      document.getElementById('tenureMidText').textContent = '36 months';
      document.getElementById('tenureMaxText').textContent = '120 months';
      if (scheduleColPeriod) scheduleColPeriod.textContent = 'Month #';
    }
    tenureInput.value = cur;
    tenureSlider.value = cur;
    renderTenureChips(freq);
  }

  function calculate() {
    var P = parseFloat(amtInput.value) || 0;
    var annualRate = parseFloat(rateInput.value) || 0;
    var n = parseInt(tenureInput.value, 10) || 1;
    if (n < 1) n = 1;

    var r = (annualRate / 100) / periodsPerYear(payFrequency);
    var emi = 0;
    if (r === 0) {
      emi = P / n;
    } else {
      var factor = Math.pow(1 + r, n);
      emi = (P * r * factor) / (factor - 1);
    }

    var totalPayment = emi * n;
    var totalInterest = Math.max(0, totalPayment - P);
    var principalPct = totalPayment > 0 ? Math.round((P / totalPayment) * 100) : 100;

    resEmi.textContent = fmt(emi);
    if (resEmiUnit) resEmiUnit.textContent = installmentUnit(payFrequency);
    resPrincipal.textContent = fmt(P);
    resInterest.textContent = fmt(totalInterest);
    resTotal.textContent = fmt(totalPayment);
    donutPrincipalPct.textContent = principalPct + '%';
    donutChart.style.background = 'conic-gradient(#1e4fd6 0% ' + principalPct + '%, #f4b942 ' + principalPct + '% 100%)';

    var months = tenureMonthsEquivalent(n, payFrequency);
    btnApply.href = "{{ route('website.apply') }}?amount=" + Math.round(P) + "&tenure=" + months;
    renderSchedule(P, r, emi, n);
  }

  function renderSchedule(P, r, emi, totalPeriods) {
    var html = '';
    var balance = P;
    var toShow = Math.min(12, totalPeriods);
    for (var i = 1; i <= toShow; i++) {
      var interestPart = balance * r;
      var principalPart = emi - interestPart;
      if (principalPart > balance) principalPart = balance;
      var closing = Math.max(0, balance - principalPart);
      html += '<tr><td class="fw-bold text-primary">#' + i + '</td><td>' + fmt(balance) + '</td><td class="fw-bold">' + fmt(emi) + '</td><td class="text-success fw-medium">+' + fmt(principalPart) + '</td><td class="text-danger">-' + fmt(interestPart) + '</td><td class="fw-bold text-dark">' + fmt(closing) + '</td></tr>';
      balance = closing;
      if (balance <= 0) break;
    }
    if (totalPeriods > 12) {
      html += '<tr class="table-light text-center text-muted"><td colspan="6">… and ' + (totalPeriods - 12) + ' more installments.</td></tr>';
    }
    scheduleBody.innerHTML = html;
  }

  document.querySelectorAll('.freq-pill').forEach(function(btn) {
    btn.addEventListener('click', function() {
      configureFrequency(btn.getAttribute('data-freq'));
      calculate();
    });
  });

  amtSlider.addEventListener('input', function() { amtInput.value = this.value; calculate(); });
  amtInput.addEventListener('input', function() { amtSlider.value = this.value; calculate(); });
  rateSlider.addEventListener('input', function() { rateInput.value = this.value; calculate(); });
  rateInput.addEventListener('input', function() { rateSlider.value = this.value; calculate(); });
  tenureSlider.addEventListener('input', function() { tenureInput.value = this.value; calculate(); });
  tenureInput.addEventListener('input', function() { tenureSlider.value = this.value; calculate(); });

  window.setAmount = function(val) {
    amtInput.value = val;
    amtSlider.value = val;
    if (event && event.target) highlightChip(event.target);
    calculate();
  };

  window.setRate = function(val) {
    rateInput.value = val;
    rateSlider.value = val;
    if (event && event.target) highlightChip(event.target);
    calculate();
  };

  function highlightChip(target) {
    if (!target || !target.parentElement) return;
    target.parentElement.querySelectorAll('.chip-btn').forEach(function(b) { b.classList.remove('active'); });
    target.classList.add('active');
  }

  configureFrequency('monthly');
  calculate();
})();
</script>
@endpush
