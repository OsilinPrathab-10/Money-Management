@extends('website.layouts.master')

@section('title', 'Eligibility Checker — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Check your loan eligibility instantly online based on age, income, and employment type with ' . ($brand['name'] ?? 'Star Finance') . '.')

@push('styles')
<style>
.elig-hero-trust {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 1rem 1.5rem;
  margin-top: 1.15rem;
  font-size: .875rem;
  opacity: .92;
}
.elig-hero-trust span i { margin-right: .35rem; }
.elig-shell {
  background: #fff;
  border-radius: 1.35rem;
  border: 1px solid #e8edf5;
  box-shadow: 0 12px 40px rgba(11, 31, 58, 0.08);
  overflow: hidden;
}
.elig-form-head {
  background: linear-gradient(135deg, #f8faff 0%, #fff 100%);
  border-bottom: 1px solid #e8edf5;
  padding: 1.35rem 1.75rem;
}
.elig-form-body { padding: 1.75rem; }
.input-icon-wrap { position: relative; }
.input-icon-wrap i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 1.1rem;
  z-index: 2;
}
.input-icon-wrap .form-control,
.input-icon-wrap .form-select {
  padding-left: 2.75rem;
  min-height: 48px;
  border-radius: .65rem;
  border: 1.5px solid #e2e8f0;
  font-size: .92rem;
}
.input-icon-wrap .form-control:focus,
.input-icon-wrap .form-select:focus {
  border-color: #1e4fd6;
  box-shadow: 0 0 0 3px rgba(30, 79, 214, 0.12);
}
.quick-chip {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-size: .78rem;
  font-weight: 600;
  padding: .3rem .75rem;
  border-radius: 999px;
  cursor: pointer;
  transition: all .2s;
}
.quick-chip:hover, .quick-chip.active {
  background: #e8f0ff;
  border-color: #1e4fd6;
  color: #1e4fd6;
}
.emp-type-card {
  border: 1.5px solid #e2e8f0;
  border-radius: 1rem;
  padding: .85rem .5rem;
  text-align: center;
  cursor: pointer;
  transition: all .2s;
  height: 100%;
  background: #fff;
}
.emp-type-card:hover { border-color: #93c5fd; background: #f8faff; }
.emp-type-card.selected {
  border-color: #1e4fd6;
  background: #eff6ff;
  box-shadow: 0 4px 14px rgba(30, 79, 214, 0.12);
}
.emp-type-icon {
  width: 40px;
  height: 40px;
  border-radius: .75rem;
  background: #e8f0ff;
  color: #1e4fd6;
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto .35rem;
}
.emp-type-card.selected .emp-type-icon { background: #1e4fd6; color: #fff; }
.elig-result-panel {
  border-radius: 1.35rem;
  border: 1px solid #e8edf5;
  box-shadow: 0 12px 40px rgba(11, 31, 58, 0.08);
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #fff;
}
.elig-result-top {
  padding: 1.75rem;
  color: #fff;
}
.elig-result-top.eligible {
  background: linear-gradient(135deg, #065f46 0%, #10b981 55%, #059669 100%);
}
.elig-result-top.review {
  background: linear-gradient(135deg, #92400e 0%, #f59e0b 55%, #d97706 100%);
}
.elig-result-top.idle {
  background: linear-gradient(135deg, #0b1f3a 0%, #1535a0 50%, #1e4fd6 100%);
}
.elig-result-body { padding: 1.75rem; flex: 1; }
.elig-stat-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: .75rem;
}
.elig-stat {
  background: #f8faff;
  border: 1px solid #e8edf5;
  border-radius: .85rem;
  padding: .85rem 1rem;
}
.elig-stat label {
  display: block;
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #64748b;
  margin-bottom: .15rem;
}
.elig-stat strong { font-size: .95rem; color: #0f172a; }
.elig-idle-icon {
  width: 80px;
  height: 80px;
  border-radius: 1.1rem;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.25rem;
  margin-bottom: 1rem;
}
.elig-steps {
  display: flex;
  flex-direction: column;
  gap: .65rem;
  margin-top: 1.25rem;
}
.elig-step-item {
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  font-size: .82rem;
  color: rgba(255, 255, 255, 0.88);
}
.elig-step-num {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  font-weight: 800;
  font-size: .75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
@endpush

@section('content')
@php
  $saved = $result['input'] ?? [];
  $savedProductId = old('loan_product_id', $saved['loan_product_id'] ?? null);
  $savedAge = old('age', $saved['age'] ?? 28);
  $savedIncome = old('monthly_income', $saved['monthly_income'] ?? 35000);
  $savedEmp = old('employment_type', $saved['employment_type'] ?? 'salaried');
@endphp

<section class="sf-page-hero">
  <div class="container text-center">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb justify-content-center mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">Eligibility</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.35rem)">Eligibility checker</h1>
    <p class="opacity-90 mb-0 mx-auto" style="max-width:540px">Quick pre-check by age, income, and employment — no bureau pull at this stage.</p>
    <div class="elig-hero-trust">
      <span><i class="ri-flashlight-line"></i>Instant result</span>
      <span><i class="ri-bar-chart-box-line"></i>No CIBIL hit</span>
      <span><i class="ri-shield-check-line"></i>Admin product rules</span>
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="row g-4 align-items-stretch">
    <div class="col-lg-5">
      <div class="elig-shell h-100">
        <div class="elig-form-head">
          <h2 class="h5 fw-bold mb-1 text-dark d-flex align-items-center gap-2">
            <i class="ri-user-search-line text-primary"></i> Your profile
          </h2>
          <p class="text-muted small mb-0">Match your details against product min age, income &amp; limits.</p>
        </div>
        <div class="elig-form-body">
          <form method="POST" action="{{ route('website.eligibility.check') }}" id="eligForm">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-bold text-secondary small">LOAN PRODUCT <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="ri-bank-line"></i>
                <select name="loan_product_id" id="eligProduct" class="form-select" required>
                  @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected($savedProductId == $p->id)>
                      {{ $p->loan_name }} @if($p->loanType)({{ $p->loanType->name }})@endif
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold text-secondary small">YOUR AGE (YEARS) <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="ri-user-line"></i>
                <input type="number" name="age" id="eligAge" class="form-control" min="18" max="80"
                  value="{{ $savedAge }}" required placeholder="e.g. 28">
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="quick-chip" data-elig-age="25">25</button>
                <button type="button" class="quick-chip" data-elig-age="30">30</button>
                <button type="button" class="quick-chip" data-elig-age="35">35</button>
                <button type="button" class="quick-chip" data-elig-age="45">45</button>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold text-secondary small">NET MONTHLY INCOME (₹) <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="ri-wallet-3-line"></i>
                <input type="number" name="monthly_income" id="eligIncome" class="form-control" min="0"
                  value="{{ $savedIncome }}" required placeholder="e.g. 35000">
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="quick-chip" data-elig-income="25000">₹25K</button>
                <button type="button" class="quick-chip" data-elig-income="35000">₹35K</button>
                <button type="button" class="quick-chip" data-elig-income="50000">₹50K</button>
                <button type="button" class="quick-chip" data-elig-income="100000">₹1L</button>
              </div>
            </div>

            <label class="form-label fw-bold text-secondary small mb-2">EMPLOYMENT TYPE <span class="text-danger">*</span></label>
            <input type="hidden" name="employment_type" id="eligEmpInput" value="{{ $savedEmp }}">
            <div class="row g-2 mb-4">
              @foreach([
                ['id'=>'salaried','icon'=>'ri-briefcase-4-line','title'=>'Salaried','sub'=>'Pvt / Govt'],
                ['id'=>'self_employed','icon'=>'ri-user-star-line','title'=>'Self employed','sub'=>'Professional'],
                ['id'=>'business','icon'=>'ri-store-2-line','title'=>'Business','sub'=>'Owner'],
              ] as $et)
              <div class="col-4">
                <div class="emp-type-card {{ $savedEmp === $et['id'] ? 'selected' : '' }}" data-elig-emp="{{ $et['id'] }}" role="button" tabindex="0">
                  <div class="emp-type-icon"><i class="{{ $et['icon'] }}"></i></div>
                  <div class="fw-bold small text-dark">{{ $et['title'] }}</div>
                  <div class="text-muted" style="font-size:.65rem">{{ $et['sub'] }}</div>
                </div>
              </div>
              @endforeach
            </div>

            <button class="btn btn-primary btn-lg fw-bold w-100 rounded-pill shadow-sm" type="submit">
              <i class="ri-check-double-line me-1"></i> Check eligibility
            </button>
            <p class="text-muted text-center small mt-3 mb-0"><i class="ri-information-line me-1"></i> Final approval still requires KYC, bank &amp; bureau verification.</p>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      @if($result)
        @php $ws = app(\App\Services\WebsiteService::class); @endphp
        <div class="elig-result-panel">
          <div class="elig-result-top {{ $result['eligible'] ? 'eligible' : 'review' }}">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div>
                <div class="small text-white text-opacity-75 text-uppercase fw-bold mb-1" style="letter-spacing:.06em">Assessment result</div>
                <h2 class="h4 fw-bold mb-0">{{ $result['product']->loan_name }}</h2>
                @if($result['product']->loanType)
                  <div class="small mt-1 text-white text-opacity-80">{{ $result['product']->loanType->name }}</div>
                @endif
              </div>
              @if($result['eligible'])
                <span class="badge bg-white text-success fw-bold px-3 py-2 rounded-pill shadow-sm"><i class="ri-checkbox-circle-fill me-1"></i> Eligible</span>
              @else
                <span class="badge bg-white text-warning-emphasis fw-bold px-3 py-2 rounded-pill shadow-sm"><i class="ri-error-warning-fill me-1"></i> Review needed</span>
              @endif
            </div>
          </div>

          <div class="elig-result-body">
            @if($result['eligible'])
              <p class="text-muted mb-4" style="line-height:1.7">You meet the <strong>basic</strong> criteria for this product. Continue to the 4-step application for Aadhaar, PAN, bank &amp; bureau checks.</p>

              <div class="elig-stat-grid mb-4">
                <div class="elig-stat"><label>Interest from</label><strong>{{ $result['product']->interest_rate ?? $result['product']->interest_rate_min ?? 12 }}% p.a.</strong></div>
                <div class="elig-stat"><label>Max amount</label><strong>₹{{ number_format($result['product']->loan_amount_max ?? 1000000) }}</strong></div>
                <div class="elig-stat"><label>Min age</label><strong>{{ $result['product']->min_age ?? 21 }} yrs</strong></div>
                <div class="elig-stat"><label>Min income</label><strong>₹{{ number_format($result['product']->min_monthly_income ?? 15000) }}/mo</strong></div>
              </div>

              <div class="d-grid gap-2">
                <a href="{{ route('website.apply.product', $ws->productSlug($result['product'])) }}" class="btn btn-primary btn-lg fw-bold rounded-pill">
                  <i class="ri-send-plane-fill me-2"></i> Apply for this loan
                </a>
                <a href="{{ route('website.loans.show', $ws->productSlug($result['product'])) }}" class="btn btn-outline-primary rounded-pill">View product details</a>
              </div>
            @else
              <p class="text-muted mb-3" style="line-height:1.7">Automated rules did not pass for the product you selected. You may still qualify with documents or another category.</p>
              <ul class="list-unstyled mb-4 d-flex flex-column gap-2">
                @foreach($result['reasons'] as $r)
                  <li class="d-flex align-items-start gap-2 small text-danger">
                    <i class="ri-close-circle-fill flex-shrink-0 mt-1"></i>
                    <span>{{ $r }}</span>
                  </li>
                @endforeach
              </ul>
              <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('website.loans.index') }}" class="btn btn-outline-primary rounded-pill px-4">Browse other loans</a>
                <a href="{{ route('website.contact') }}" class="btn btn-primary rounded-pill px-4"><i class="ri-customer-service-2-line me-1"></i> Talk to advisor</a>
              </div>
            @endif
          </div>
        </div>
      @else
        <div class="elig-result-panel">
          <div class="elig-result-top idle">
            <div class="elig-idle-icon"><i class="ri-shield-user-line"></i></div>
            <h2 class="h4 fw-bold mb-2">Instant eligibility assessment</h2>
            <p class="mb-0 text-white text-opacity-85" style="max-width:480px;line-height:1.65">Complete the form to compare your age and income against live product rules configured in admin — no login required.</p>
            <div class="elig-steps">
              <div class="elig-step-item"><span class="elig-step-num">1</span> Pick a loan product</div>
              <div class="elig-step-item"><span class="elig-step-num">2</span> Enter age &amp; monthly income</div>
              <div class="elig-step-item"><span class="elig-step-num">3</span> Select employment type</div>
              <div class="elig-step-item"><span class="elig-step-num">4</span> Get eligible / review outcome</div>
            </div>
          </div>
          <div class="elig-result-body text-center">
            <div class="row g-3 text-start">
              @foreach([
                ['ri-fingerprint-line','Digital KYC next','Aadhaar & PAN verified when you apply'],
                ['ri-secure-payment-line','Bank verify','Disbursal only to verified account'],
                ['ri-bar-chart-box-line','Bureau later','CIBIL pulled only during processing'],
              ] as $hint)
              <div class="col-md-4">
                <div class="elig-stat h-100">
                  <i class="{{ $hint[0] }} text-primary fs-5 mb-2 d-block"></i>
                  <label class="text-primary">{{ $hint[1] }}</label>
                  <strong class="fw-normal small text-muted d-block" style="font-size:.8rem;line-height:1.5">{{ $hint[2] }}</strong>
                </div>
              </div>
              @endforeach
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-elig-age]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('eligAge').value = btn.getAttribute('data-elig-age');
    btn.parentElement.querySelectorAll('.quick-chip').forEach(function(c) { c.classList.remove('active'); });
    btn.classList.add('active');
  });
});
document.querySelectorAll('[data-elig-income]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('eligIncome').value = btn.getAttribute('data-elig-income');
    btn.parentElement.querySelectorAll('.quick-chip').forEach(function(c) { c.classList.remove('active'); });
    btn.classList.add('active');
  });
});
document.querySelectorAll('[data-elig-emp]').forEach(function(card) {
  function pick() {
    var type = card.getAttribute('data-elig-emp');
    document.getElementById('eligEmpInput').value = type;
    document.querySelectorAll('[data-elig-emp]').forEach(function(c) { c.classList.remove('selected'); });
    card.classList.add('selected');
  }
  card.addEventListener('click', pick);
  card.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); }
  });
});
</script>
@endpush