@extends('website.layouts.master')

@section('title', $page->name . ' — ' . ($brand['name'] ?? 'Star Finance'))
@php
  $legalMeta = \Illuminate\Support\Str::limit(strip_tags($page->content), 155);
@endphp
@section('meta_description', $legalMeta)

@push('styles')
<style>
.legal-prose {
  font-size: 1rem;
  line-height: 1.85;
  color: #374151;
}
.legal-prose h2, .legal-prose h3, .legal-prose h4 {
  color: var(--sf-navy);
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: .75rem;
}
.legal-prose p { margin-bottom: 1rem; }
.legal-prose ul, .legal-prose ol { margin-bottom: 1rem; padding-left: 1.5rem; }
.legal-prose a { color: var(--sf-blue); }
.legal-updated { font-size: .875rem; color: #6b7280; }
.legal-nav a { font-size: .9rem; text-decoration: none; }
.legal-nav a.active { font-weight: 700; color: var(--sf-blue); }
</style>
@endpush

@section('content')
@php use Illuminate\Support\Str; @endphp

<section class="sf-page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">{{ $page->name }}</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.35rem)">{{ $page->name }}</h1>
    <p class="legal-updated mb-0 opacity-75">
      Managed in admin · Setup → Page Configuration
      @if($page->updated_at)
        · Last updated {{ $page->updated_at->format('d M Y') }}
      @endif
    </p>
  </div>
</section>

<section class="container py-5">
  <div class="row g-5">
    @if(isset($policyPages) && $policyPages->count() > 1)
    <aside class="col-lg-3">
      <div class="sticky-top pt-2" style="top:5.5rem">
        <h6 class="text-muted text-uppercase small fw-bold mb-3">Legal pages</h6>
        <nav class="legal-nav d-flex flex-column gap-2">
          @foreach($policyPages as $policy)
            @php
              $url = $policy->slug === 'privacy-policy'
                ? route('public.privacy-policy')
                : ($policy->slug === 'terms-and-conditions'
                  ? route('public.terms-and-conditions')
                  : route('public.page', $policy->slug));
            @endphp
            <a href="{{ $url }}" class="{{ $policy->slug === $page->slug ? 'active' : 'text-muted' }}">{{ $policy->name }}</a>
          @endforeach
        </nav>
      </div>
    </aside>
    @endif
    <div class="{{ isset($policyPages) && $policyPages->count() > 1 ? 'col-lg-9' : 'col-12' }}">
      <article class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="legal-prose">
          {!! $page->content !!}
        </div>
      </article>
      <div class="d-flex flex-wrap gap-2 mt-4">
        <a href="{{ route('website.home') }}" class="btn btn-outline-secondary rounded-pill btn-sm">← Back to home</a>
        <a href="{{ route('login') }}" class="btn btn-primary rounded-pill btn-sm">Login</a>
      </div>
    </div>
  </div>
</section>
@endsection