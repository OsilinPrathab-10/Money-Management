@extends('website.layouts.master')

@section('title', 'Our Loan Products — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Explore flexible loan solutions for personal, business, vehicle, property, and gold finance from ' . ($brand['name'] ?? 'Star Finance') . '.')

@push('styles')
<style>
/* ── Top-Right Reference Style Banner ── */
.loans-hero-banner {
  background: linear-gradient(135deg, #09121f 0%, #0e2b5c 55%, #184ca1 100%);
  color: #ffffff;
  padding: clamp(2.5rem, 5vw, 4rem) 0;
  position: relative;
  overflow: hidden;
}
.loans-hero-title {
  font-size: clamp(2rem, 4.5vw, 3.1rem);
  font-weight: 800;
  letter-spacing: -.03em;
  margin-bottom: .65rem;
}
.loans-hero-sub {
  font-size: 1.05rem;
  color: rgba(255,255,255,.82);
  margin-bottom: 0;
}
.loans-hero-img-wrap {
  text-align: center;
}
.loans-hero-img-wrap img {
  max-height: 220px;
  width: auto;
  border-radius: 1.25rem;
  box-shadow: 0 16px 40px rgba(0,0,0,.35);
  border: 3px solid rgba(255,255,255,.15);
}

/* ── Category Filter Pills ── */
.finora-cat-pill {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  font-weight: 700;
  font-size: .84rem;
  padding: .45rem 1.15rem;
  border-radius: 999px;
  text-decoration: none;
  transition: all .2s ease;
  white-space: nowrap;
  cursor: pointer;
}
.finora-cat-pill:hover {
  background: #f1f5f9;
  color: #0e2b5c;
  border-color: #94a3b8;
}
.finora-cat-pill.active {
  background: #0e2b5c !important;
  color: #ffffff !important;
  border-color: #0e2b5c !important;
  box-shadow: 0 4px 14px rgba(14,43,92,.22);
}

/* ── Product Cards Grid ── */
.loan-card-v2 {
  background: #ffffff;
  border-radius: 1.15rem;
  border: 1px solid #e2edf8;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(14,43,92,.05);
  height: 100%;
  display: flex;
  flex-direction: column;
  transition: transform .25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .25s ease, border-color .25s ease;
}
.loan-card-v2:hover {
  transform: translateY(-6px);
  border-color: #b8ccff;
  box-shadow: 0 18px 45px rgba(14,43,92,.12);
}
.loan-card-media {
  position: relative;
  height: 175px;
  overflow: hidden;
}
.loan-card-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .5s ease;
}
.loan-card-v2:hover .loan-card-media img {
  transform: scale(1.05);
}
.loan-card-badge {
  position: absolute;
  top: .75rem;
  right: .75rem;
  background: rgba(14,43,92,.85);
  backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: .72rem;
  font-weight: 700;
  padding: .25rem .7rem;
  border-radius: 999px;
  z-index: 2;
}

.prod-card-logo-badge {
  position: absolute;
  top: .75rem;
  left: .75rem;
  width: 40px;
  height: 40px;
  border-radius: .75rem;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(0,0,0,.15);
  border: 1px solid rgba(255,255,255,.9);
  padding: 5px;
  z-index: 2;
}
.prod-card-logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.cat-pill-icon {
  width: 18px;
  height: 18px;
  object-fit: contain;
  border-radius: 4px;
}

.loan-card-body-v2 {
  padding: 1.25rem 1.35rem 1.35rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.loan-card-title-v2 {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0e2b5c;
  margin-bottom: .25rem;
  letter-spacing: -.02em;
}
.loan-card-sub-v2 {
  font-size: .84rem;
  color: #64748b;
  margin-bottom: .65rem;
}
.loan-card-metrics-v2 {
  font-size: .88rem;
  font-weight: 700;
  color: #1a5cff;
  margin-bottom: 1.15rem;
}
.loan-card-footer-v2 {
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: .85rem;
  border-top: 1px solid #f1f5f9;
}
.btn-loan-learn {
  color: #1a5cff;
  font-weight: 700;
  font-size: .84rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  transition: gap .15s ease;
}
.btn-loan-learn:hover {
  color: #0e2b5c;
  gap: .45rem;
}
.btn-loan-apply {
  background: #0e2b5c;
  color: #ffffff !important;
  font-weight: 700;
  font-size: .82rem;
  padding: .42rem 1.1rem;
  border-radius: 999px;
  text-decoration: none;
  transition: all .2s ease;
  box-shadow: 0 4px 12px rgba(14,43,92,.18);
}
.btn-loan-apply:hover {
  background: #184ca1;
  transform: translateY(-1px);
}
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Str;
  $ws = app(\App\Services\WebsiteService::class);

  $productRows = collect();
  foreach ($categories as $cat) {
    foreach ($cat->products as $product) {
      $productRows->push(['category' => $cat, 'product' => $product]);
    }
  }
  $totalProducts = $productRows->count();
@endphp

{{-- ── 1. DARK NAVY HERO BANNER (Top-Right Reference Style) ── --}}
<section class="loans-hero-banner">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <nav aria-label="breadcrumb" class="mb-2">
          <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
            <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
            <li class="breadcrumb-item text-white active">Loan Products</li>
          </ol>
        </nav>
        <h1 class="loans-hero-title">Our Loan Products</h1>
        <p class="loans-hero-sub">Flexible loan solutions for every need</p>
      </div>
      <div class="col-lg-5 text-lg-end d-none d-md-block">
        <div class="loans-hero-img-wrap">
          {{-- Mini house in hand finance photography matching reference --}}
          <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=700&q=80" alt="Flexible Loan Solutions">
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── 2. CATEGORY FILTER PILLS ── --}}
<section class="container py-4">
  <div class="overflow-auto pb-2 mb-4" style="scrollbar-width:none">
    <div class="d-flex gap-2 flex-nowrap align-items-center" id="loanCatPillGroup">
      <button type="button" class="finora-cat-pill active d-inline-flex align-items-center gap-1" data-cat="all">
        <i class="ri-apps-line"></i> <span>All</span>
      </button>
      @foreach($categories as $cat)
        @if($cat->products->count())
          <button type="button" class="finora-cat-pill d-inline-flex align-items-center gap-2" data-cat="{{ Str::slug($cat->name) }}">
            @if(!empty($cat->loan_type_icon_url))
              <img src="{{ $cat->loan_type_icon_url }}" alt="" class="cat-pill-icon">
            @else
              <i class="ri-bank-line text-primary"></i>
            @endif
            <span>{{ $cat->name }}</span>
            <span class="badge bg-light text-muted border rounded-pill" style="font-size:.68rem">{{ $cat->products->count() }}</span>
          </button>
        @endif
      @endforeach
    </div>
  </div>

  {{-- ── 3. PRODUCT CARDS GRID ── --}}
  <div class="row g-4" id="loanProductGrid">
    @forelse($productRows as $row)
      @php
        $cat = $row['category'];
        $product = $row['product'];
        $catSlug = Str::slug($cat->name);
        $slug = $ws->productSlug($product);
        $banner = $cat->loan_type_banner_url
          ?: ($cat->loan_type_image_url ?: $ws->productImage($product));
        $icon = $cat->loan_type_icon_url;
        $rate = $product->interest_rate_min ?? $product->interest_rate ?? null;
      @endphp
      <div class="col-sm-6 col-lg-4 col-xl-4 loan-product-item" data-cat="{{ $catSlug }}">
        <article class="loan-card-v2">
          <div class="loan-card-media">
            <img src="{{ $banner }}" alt="{{ $product->loan_name }}" loading="lazy">
            @if(!empty($icon))
              <div class="prod-card-logo-badge" title="{{ $cat->name }}">
                <img src="{{ $icon }}" alt="{{ $cat->name }}" class="prod-card-logo-img">
              </div>
            @endif
            @if($rate)
              <span class="loan-card-badge">{{ $rate }}% p.a.</span>
            @else
              <span class="loan-card-badge">{{ $cat->name }}</span>
            @endif
          </div>
          <div class="loan-card-body-v2">
            <div class="d-flex align-items-center gap-1 mb-2">
              <span class="badge rounded-pill bg-light text-primary border" style="font-size:.72rem">
                {{ $cat->name }}
              </span>
            </div>
            <h3 class="loan-card-title-v2">{{ $product->loan_name }}</h3>
            <p class="loan-card-sub-v2">
              {{ Str::limit($product->short_description ?? $product->description ?? 'Tailored repayment with fast approval.', 75) }}
            </p>
            <div class="loan-card-metrics-v2">
              ₹{{ number_format($product->loan_amount_min ?? 0) }} – ₹{{ number_format($product->loan_amount_max ?? 0) }}
            </div>
            <div class="loan-card-footer-v2">
              <a href="{{ route('website.loans.show', $slug) }}" class="btn-loan-learn">
                Learn More <i class="ri-arrow-right-line"></i>
              </a>
              <a href="{{ route('website.apply', ['product' => $slug]) }}" class="btn-loan-apply">
                Apply Now <i class="ri-arrow-right-line ms-1"></i>
              </a>
            </div>
          </div>
        </article>
      </div>
    @empty
      <div class="col-12 text-center py-5">
        <p class="text-muted">No loan products currently available.</p>
      </div>
    @endforelse
  </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const pills = document.querySelectorAll('#loanCatPillGroup .finora-cat-pill');
  const items = document.querySelectorAll('.loan-product-item');

  pills.forEach(pill => {
    pill.addEventListener('click', function () {
      pills.forEach(p => p.classList.remove('active'));
      this.classList.add('active');

      const target = this.getAttribute('data-cat');
      items.forEach(item => {
        if (target === 'all' || item.getAttribute('data-cat') === target) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });
});
</script>
@endpush
