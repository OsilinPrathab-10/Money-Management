@extends('website.layouts.master')
@section('title', 'FAQ — ' . ($brand['name'] ?? 'Star Finance'))

@push('styles')
<style>
.faq-search { max-width: 500px; margin: 0 auto; }
.accordion-item { border: 1px solid #e8edf5 !important; border-radius: .85rem !important; margin-bottom: .75rem; overflow: hidden; }
.accordion-button { font-weight: 600; font-size: .95rem; color: #0b1f3a; background: #fff; }
.accordion-button:not(.collapsed) { color: #1e4fd6; background: #f0f5ff; box-shadow: none; }
.accordion-button::after { filter: none; }
.accordion-body { color: #6b7280; line-height: 1.8; font-size: .92rem; }
.faq-category-btn { border-radius: 999px; font-size: .82rem; font-weight: 600; padding: .35rem 1rem; }
</style>
@endpush

@section('content')
<section class="sf-page-hero">
  <div class="container text-center">
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.35rem)">Frequently asked questions</h1>
    <p class="opacity-90 mb-0 mx-auto" style="max-width:520px">Clear answers on products, eligibility, KYC, and repayments.</p>
  </div>
</section>

<section class="container py-5">
  {{-- Search --}}
  <div class="faq-search mb-5">
    <div class="input-group shadow-sm">
      <span class="input-group-text bg-white border-end-0 text-muted"><i class="ri-search-line"></i></span>
      <input type="text" id="faqSearch" class="form-control border-start-0 ps-0" placeholder="Search questions…" oninput="filterFaq(this.value)">
    </div>
  </div>

  <div class="row g-5">
    <div class="col-lg-8">
      <div class="accordion" id="faqAcc">
        @forelse($faqs as $i => $faq)
          <div class="accordion-item faq-item" data-q="{{ strtolower($faq->question) }}">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#f{{ $faq->id }}">
                {{ $faq->question }}
              </button>
            </h2>
            <div id="f{{ $faq->id }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqAcc">
              <div class="accordion-body">{{ $faq->answer }}</div>
            </div>
          </div>
        @empty
          {{-- Static fallback FAQs --}}
          @foreach([
            ['q'=>'What documents are required to apply for a loan?','a'=>'For most loans you need: PAN Card, Aadhaar Card, Address Proof, latest 3-6 months bank statements, and income proof (salary slips or ITR). Specific products may need additional documents.'],
            ['q'=>'How quickly will my loan be processed?','a'=>'Most loan applications are reviewed within 24 hours of receiving complete documentation. Once approved, funds are typically disbursed to your account within 1-2 business days.'],
            ['q'=>'What is the minimum and maximum loan amount?','a'=>'Loan amounts vary by product. Personal loans start from ₹10,000, business loans from ₹50,000, and home loans from ₹1 lakh. Maximum amounts depend on your income, credit score, and loan type.'],
            ['q'=>'What is the interest rate on your loans?','a'=>'Interest rates start from 9% per annum for gold loans and go up to 24% depending on the loan type, your credit profile, and repayment tenure. We always disclose the rate before you sign any agreement.'],
            ['q'=>'Can I prepay my loan?','a'=>'Yes! We offer flexible prepayment options. Prepayment charges may apply depending on the loan product — please check the specific product terms or speak to our loan officer.'],
            ['q'=>'How do I track my loan application status?','a'=>'You can track your application on the Track Application page with your reference number, or sign in via Customer Login ('.url('/client/login').').'],
            ['q'=>'Is my data safe with Star Finance?','a'=>'Absolutely. We use bank-grade encryption for all data storage and transmission. Your KYC documents are stored in a secure, encrypted vault and never shared with third parties without your consent.'],
            ['q'=>'What happens if I miss an EMI payment?','a'=>'A late payment fee applies as per your loan agreement. We recommend contacting our support team immediately — we can discuss restructuring options before defaults affect your credit score.'],
          ] as $i => $faq)
          <div class="accordion-item faq-item" data-q="{{ strtolower($faq['q']) }}">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#fs{{ $i }}">
                {{ $faq['q'] }}
              </button>
            </h2>
            <div id="fs{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqAcc">
              <div class="accordion-body">{{ $faq['a'] }}</div>
            </div>
          </div>
          @endforeach
        @endforelse
        <div id="faqNoResult" class="text-center text-muted py-4 d-none">
          <i class="ri-search-eye-line fs-2 d-block mb-2"></i>
          No questions found. <a href="{{ route('website.contact') }}">Contact us</a> and we'll help!
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="p-4 rounded-4" style="background:#f8faff;border:1px solid #dbe8ff;position:sticky;top:90px">
        <h5 class="fw-700 mb-3">Still have questions?</h5>
        <p class="text-muted small mb-4" style="line-height:1.7">Our loan advisors are available Monday to Saturday, 9 AM – 6 PM</p>
        <a href="{{ route('website.contact') }}" class="btn btn-primary rounded-pill w-100 mb-2">
          <i class="ri-phone-line me-2"></i>Contact Support
        </a>
        <a href="{{ route('website.apply') }}" class="btn btn-outline-primary rounded-pill w-100">
          <i class="ri-file-edit-line me-2"></i>Apply for a Loan
        </a>
        <hr class="my-4">
        <div class="d-flex flex-column gap-2">
          @if(!empty($brand['phone']))<a href="tel:{{ $brand['phone'] }}" class="d-flex align-items-center gap-2 text-muted text-decoration-none small"><i class="ri-phone-fill text-primary"></i>{{ $brand['phone'] }}</a>@endif
          @if(!empty($brand['email']))<a href="mailto:{{ $brand['email'] }}" class="d-flex align-items-center gap-2 text-muted text-decoration-none small"><i class="ri-mail-fill text-primary"></i>{{ $brand['email'] }}</a>@endif
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
function filterFaq(q) {
  q = q.toLowerCase().trim();
  var items = document.querySelectorAll('.faq-item');
  var visible = 0;
  items.forEach(function(item) {
    var match = !q || item.dataset.q.includes(q);
    item.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  var noRes = document.getElementById('faqNoResult');
  if (noRes) noRes.classList.toggle('d-none', visible > 0);
}
</script>
@endpush
