@extends('website.layouts.master')

@section('title', ($brand['name'] ?? 'Star Finance') . ' — Financial Solutions Designed Around Your Future')
@section('meta_description', ($brand['tagline'] ?? 'Transparent lending with digital verification, competitive rates, and dedicated customer support.') . ' Apply online in 3 minutes.')

@push('styles')
<style>
/* ── Finora Animated Hero (No Image - Pure Functional & Clean) ── */
.sf-finora-hero {
  position: relative;
  background: linear-gradient(135deg, #eef5ff 0%, #dbeafe 45%, #e0ecfd 100%);
  padding: clamp(3rem, 5.5vw, 4.5rem) 0 clamp(4.5rem, 6.5vw, 5.5rem);
  overflow: hidden;
}

/* Floating ambient glowing orbs */
.sf-hero-orb {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(55px);
  z-index: 0;
  opacity: 0.55;
  animation: orbFloat 8s ease-in-out infinite alternate;
}
.sf-hero-orb-1 {
  width: 480px;
  height: 480px;
  top: -15%;
  right: -5%;
  background: radial-gradient(circle, rgba(26,92,255,.24) 0%, rgba(56,189,248,.12) 70%, transparent 100%);
}
.sf-hero-orb-2 {
  width: 360px;
  height: 360px;
  bottom: -10%;
  left: 5%;
  background: radial-gradient(circle, rgba(232,163,23,.18) 0%, rgba(26,92,255,.08) 70%, transparent 100%);
  animation-duration: 10s;
  animation-delay: -3s;
}

@keyframes orbFloat {
  0% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(25px, -20px) scale(1.08); }
  100% { transform: translate(-20px, 20px) scale(0.95); }
}

.hero-growth-badge {
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  background: #ffffff;
  color: #0e2b5c;
  font-weight: 700;
  font-size: .84rem;
  padding: .45rem 1.15rem;
  border-radius: 999px;
  box-shadow: 0 4px 16px rgba(14,43,92,.07);
  border: 1px solid rgba(226,232,240,.9);
  transition: transform .25s ease, box-shadow .25s ease;
}
.hero-growth-badge:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(14,43,92,.12);
}

.hero-live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16,185,129,0.7);
  animation: livePulseGlow 1.8s infinite;
}
@keyframes livePulseGlow {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.hero-finora-title {
  font-size: clamp(2.2rem, 4.5vw, 3.4rem);
  font-weight: 800;
  letter-spacing: -.035em;
  line-height: 1.15;
  color: #0e2b5c;
  margin-bottom: 1.15rem;
}
.hero-highlight-word {
  color: #1a5cff;
  position: relative;
  display: inline-block;
}
.hero-highlight-word::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: 4px;
  width: 100%;
  height: 8px;
  background: rgba(26,92,255,.16);
  border-radius: 4px;
  z-index: -1;
}

.hero-finora-sub {
  font-size: clamp(1rem, 1.8vw, 1.1rem);
  color: #475569;
  line-height: 1.7;
  max-width: 530px;
  margin-bottom: 1.35rem;
}

.hero-key-points {
  display: flex;
  flex-wrap: wrap;
  gap: .65rem 1.25rem;
  margin-bottom: 1.65rem;
  font-size: .84rem;
  font-weight: 600;
  color: #334155;
}
.hero-key-point-item {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}
.hero-key-point-item i {
  color: #10b981;
  font-size: 1.05rem;
}

.btn-hero-navy {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  background: #0e2b5c;
  color: #fff !important;
  font-weight: 700;
  font-size: .92rem;
  padding: .75rem 1.75rem;
  border-radius: 999px;
  text-decoration: none;
  transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 8px 24px rgba(14,43,92,.25);
  border: none;
}
.btn-hero-navy:hover {
  background: #184ca1;
  transform: translateY(-3px) scale(1.02);
  box-shadow: 0 14px 32px rgba(14,43,92,.32);
}

.btn-hero-white {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  background: #ffffff;
  color: #0e2b5c !important;
  font-weight: 700;
  font-size: .92rem;
  padding: .75rem 1.65rem;
  border-radius: 999px;
  text-decoration: none;
  transition: all .25s ease;
  box-shadow: 0 4px 14px rgba(14,43,92,.08);
  border: 1px solid rgba(226,232,240,.9);
}
.btn-hero-white:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(14,43,92,.14);
}

/* Hero Stats Micro-Strip */
.hero-stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1.25rem 2rem;
  margin-top: 1.75rem;
  padding-top: 1.35rem;
  border-top: 1px solid rgba(203,213,225,.6);
}
.hero-stat-box strong {
  display: block;
  font-size: 1.25rem;
  font-weight: 800;
  color: #0e2b5c;
  line-height: 1.1;
  letter-spacing: -.02em;
}
.hero-stat-box span {
  font-size: .76rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .03em;
}

/* ── Finora Reference Style Family Card (Right Column) ── */
.hero-visual-card {
  position: relative;
  border-radius: 1.5rem;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(14,43,92,.15);
  border: 4px solid #ffffff;
  background: #ffffff;
}
.hero-family-img {
  width: 100%;
  height: 380px;
  object-fit: cover;
  transition: transform .6s ease;
}
.hero-visual-card:hover .hero-family-img {
  transform: scale(1.03);
}
.hero-script-overlay {
  position: absolute;
  top: 1.25rem;
  right: 1.5rem;
  font-family: 'Caveat', cursive, sans-serif;
  font-size: clamp(1.4rem, 2.6vw, 2.1rem);
  font-weight: 700;
  color: #1a5cff;
  text-shadow: 0 2px 12px rgba(255,255,255,.95), 0 0 20px #fff;
  line-height: 1.15;
  text-align: right;
  pointer-events: none;
}
.hero-floating-pill {
  position: absolute;
  bottom: 1.25rem;
  left: 1.25rem;
  background: rgba(255,255,255,.94);
  backdrop-filter: blur(10px);
  padding: .55rem 1.1rem;
  border-radius: 999px;
  box-shadow: 0 8px 24px rgba(14,43,92,.18);
  border: 1px solid rgba(226,232,240,.9);
  display: flex;
  align-items: center;
  gap: .65rem;
  font-weight: 700;
  font-size: .84rem;
  color: #0e2b5c;
}
.hero-view-toggle-pill {
  background: rgba(255,255,255,.85);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(226,232,240,.9);
  border-radius: 999px;
  padding: 3px;
  display: inline-flex;
  gap: 3px;
  box-shadow: 0 4px 14px rgba(14,43,92,.06);
}
.hero-view-toggle-btn {
  border: none;
  background: transparent;
  padding: .35rem .95rem;
  font-size: .78rem;
  font-weight: 700;
  border-radius: 999px;
  color: #64748b;
  cursor: pointer;
  transition: all .2s;
}
.hero-view-toggle-btn.active {
  background: #0e2b5c;
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(14,43,92,.2);
}

/* ── Hero EMI Card (Right Column) ── */
.hero-emi-card {
  background: #ffffff;
  border-radius: 1.5rem;
  border: 1px solid #e2edf8;
  box-shadow: 0 20px 50px rgba(14,43,92,.12);
  padding: 1.65rem;
}
.custom-slider {
  -webkit-appearance: none; appearance: none; width: 100%; height: 6px;
  border-radius: 999px; background: #e2edf8; outline: none; cursor: pointer;
}
.custom-slider::-webkit-slider-thumb {
  -webkit-appearance: none; appearance: none; width: 20px; height: 20px;
  border-radius: 50%; background: #0e2b5c; border: 3px solid #fff;
  box-shadow: 0 2px 10px rgba(14,43,92,.35); cursor: pointer;
}
.preset-tab-btn {
  background: #f8fafc; border: 1px solid #e2edf8; color: #475569;
  font-size: .74rem; font-weight: 700; padding: .35rem .75rem;
  border-radius: 999px; transition: .15s; cursor: pointer; white-space: nowrap;
}
.preset-tab-btn:hover, .preset-tab-btn.active {
  background: #eef4ff; border-color: #b8ccff; color: #1a5cff;
}
.freq-tab-btn {
  flex: 1; background: #f8fafc; border: 1px solid #e2edf8; color: #475569;
  font-size: .78rem; font-weight: 700; padding: .45rem .4rem;
  border-radius: 999px; transition: .15s; cursor: pointer;
}
.freq-tab-btn:hover, .freq-tab-btn.active {
  background: #0e2b5c; border-color: #0e2b5c; color: #fff;
}
.quick-val-chip {
  background: #f8fafc; border: 1px solid #e2edf8; color: #475569;
  font-size: .72rem; font-weight: 700; padding: .2rem .5rem;
  border-radius: 6px; cursor: pointer; transition: .15s;
}
.quick-val-chip:hover { background: #eef4ff; border-color: #b8ccff; color: #1a5cff; }

.hero-emi-result-strip {
  background: linear-gradient(145deg, #09121f 0%, #0e2b5c 55%, #184ca1 100%);
  border-radius: 1.15rem;
  padding: 1.25rem 1.4rem;
  color: #ffffff;
}

/* ── 5 Feature Badges Bar ── */
.finora-features-strip {
  position: relative;
  z-index: 5;
  margin-top: -2.75rem;
  margin-bottom: 3rem;
}
.feature-badge-pill {
  background: #ffffff;
  border-radius: 999px;
  padding: .85rem 1.35rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  box-shadow: 0 10px 30px rgba(14,43,92,.08);
  border: 1px solid #e2edf8;
  font-weight: 700;
  font-size: .86rem;
  color: #0e2b5c;
  white-space: nowrap;
  transition: all .28s cubic-bezier(0.16, 1, 0.3, 1);
}
.feature-badge-pill:hover {
  transform: translateY(-5px);
  box-shadow: 0 16px 38px rgba(14,43,92,.14);
  border-color: #b8ccff;
}
.feature-badge-icon {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #eef4ff;
  color: #1a5cff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  transition: transform .25s ease, background .25s ease, color .25s ease;
}
.feature-badge-pill:hover .feature-badge-icon {
  transform: scale(1.12);
  background: #1a5cff;
  color: #ffffff;
}

/* ── Category Filter Pills ── */
.home-cat-pill {
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
.home-cat-pill:hover {
  background: #f1f5f9;
  color: #0e2b5c;
  border-color: #94a3b8;
}
.home-cat-pill.active {
  background: #0e2b5c !important;
  color: #ffffff !important;
  border-color: #0e2b5c !important;
  box-shadow: 0 4px 14px rgba(14,43,92,.22);
}

/* ── Product Photo Cards ── */
.finora-prod-card {
  background: #ffffff;
  border-radius: 1.25rem;
  border: 1px solid #e2edf8;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(14,43,92,.05);
  display: flex;
  flex-direction: column;
  height: 100%;
  transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .3s ease, border-color .3s ease;
}
.finora-prod-card:hover {
  transform: translateY(-7px);
  border-color: #b8ccff;
  box-shadow: 0 20px 48px rgba(14,43,92,.13);
}
.finora-prod-media {
  position: relative;
  overflow: hidden;
  height: 175px;
}
.finora-prod-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform .6s cubic-bezier(0.16, 1, 0.3, 1);
}
.finora-prod-card:hover .finora-prod-img {
  transform: scale(1.08);
}
.finora-prod-tag {
  position: absolute;
  top: .75rem;
  right: .75rem;
  background: rgba(14,43,92,.88);
  backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: .72rem;
  font-weight: 700;
  padding: .28rem .75rem;
  border-radius: 999px;
  box-shadow: 0 4px 12px rgba(0,0,0,.2);
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

.finora-prod-body {
  padding: 1.35rem 1.4rem 1.4rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.finora-prod-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0e2b5c;
  margin-bottom: .35rem;
  letter-spacing: -.02em;
}
.finora-prod-sub {
  font-size: .84rem;
  color: #64748b;
  margin-bottom: .85rem;
  line-height: 1.5;
}
.finora-prod-range {
  font-size: .9rem;
  font-weight: 700;
  color: #1a5cff;
  margin-bottom: 1.15rem;
}
.finora-prod-footer {
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: .85rem;
  border-top: 1px solid #f1f5f9;
}
.btn-learn-more {
  color: #1a5cff;
  font-weight: 700;
  font-size: .85rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: .25rem;
  transition: gap .2s ease;
}
.btn-learn-more:hover {
  color: #0e2b5c;
  gap: .5rem;
}
.btn-card-apply {
  background: #0e2b5c;
  color: #fff !important;
  font-weight: 700;
  font-size: .82rem;
  padding: .42rem 1.05rem;
  border-radius: 999px;
  text-decoration: none;
  transition: all .2s ease;
  box-shadow: 0 4px 12px rgba(14,43,92,.18);
}
.btn-card-apply:hover {
  background: #1a5cff;
  transform: translateY(-1px);
}

/* ── Process Steps ── */
.process-step-v2 {
  background: #ffffff;
  border: 1px solid #e2edf8;
  border-radius: 1.25rem;
  padding: 1.85rem 1.4rem;
  text-align: center;
  height: 100%;
  box-shadow: 0 8px 24px rgba(14,43,92,.04);
  transition: transform .25s ease, box-shadow .25s ease;
}
.process-step-v2:hover {
  transform: translateY(-6px);
  box-shadow: 0 18px 45px rgba(14,43,92,.12);
  border-color: #b8ccff;
}
.step-number-circle {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: #0e2b5c;
  color: #fff;
  font-weight: 800;
  font-size: 1.15rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.15rem;
  box-shadow: 0 6px 18px rgba(14,43,92,.25);
}

/* ── Testimonials ── */
.home-testi-card {
  background: #ffffff;
  border: 1px solid #e2edf8;
  border-radius: 1.25rem;
  padding: 1.75rem;
  height: 100%;
  box-shadow: 0 8px 24px rgba(14,43,92,.04);
  display: flex;
  flex-direction: column;
  transition: transform .25s ease, box-shadow .25s ease;
}
.home-testi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 36px rgba(14,43,92,.1);
  border-color: #b8ccff;
}
.testi-stars-gold {
  color: #f59e0b;
  font-size: 1rem;
  letter-spacing: .15em;
  margin-bottom: .85rem;
}
.testi-avatar-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0e2b5c, #1a5cff);
  color: #ffffff;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
}

/* ── FAQ Accordion ── */
.home-faq-item {
  border: 1px solid #e2edf8 !important;
  border-radius: 1rem !important;
  margin-bottom: .85rem;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(14,43,92,.03);
}
.home-faq-button {
  font-weight: 700;
  font-size: .96rem;
  color: #0e2b5c;
  padding: 1.15rem 1.35rem;
  background: #ffffff;
}
.home-faq-button:not(.collapsed) {
  color: #1a5cff;
  background: #f8fbff;
  box-shadow: none;
}
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Str;
  $website = app(\App\Services\WebsiteService::class);
  $heroBanner = isset($banners) && $banners->isNotEmpty() ? $banners->first() : null;
@endphp

{{-- ── 1. FINORA-STYLE HERO (Pure Functional With Instant EMI Calculator) ── --}}
<section class="sf-finora-hero">
  <div class="sf-hero-orb sf-hero-orb-1"></div>
  <div class="sf-hero-orb sf-hero-orb-2"></div>

  <div class="container position-relative" style="z-index: 1;">
    <div class="row align-items-center g-4 g-xl-5">
      {{-- Left Column: Value Proposition & Dynamic Headlines --}}
      <div class="col-lg-6">
        <div class="hero-growth-badge mb-3">
          <span class="hero-live-dot"></span>
          <span>Your Growth Partner</span>
        </div>
        <h1 class="hero-finora-title">
          @if($heroBanner && $heroBanner->title)
            {{ $heroBanner->title }}
          @else
            Financial Solutions Designed Around <span class="hero-highlight-word">Your Future</span>
          @endif
        </h1>
        <p class="hero-finora-sub">
          @if($heroBanner && $heroBanner->subtitle)
            {{ $heroBanner->subtitle }}
          @else
            {{ $brand['tagline'] ?? 'Get the right loan, at the right time, with transparent terms and excellent support.' }}
          @endif
        </p>

        {{-- Key Trust Badges --}}
        <div class="hero-key-points">
          <span class="hero-key-point-item"><i class="ri-checkbox-circle-fill"></i> 24h Fast Approval</span>
          <span class="hero-key-point-item"><i class="ri-checkbox-circle-fill"></i> 100% Paperless KYC</span>
          <span class="hero-key-point-item"><i class="ri-checkbox-circle-fill"></i> Clear &amp; Fair Rates</span>
        </div>

        <div class="d-flex flex-wrap gap-3">
          <a href="{{ route('website.apply') }}" class="btn-hero-navy btn-shimmer">
            Apply Now <i class="ri-arrow-right-line ms-1"></i>
          </a>
          <a href="{{ route('website.loans.index') }}" class="btn-hero-white">
            Explore Loans <i class="ri-compass-3-line ms-1"></i>
          </a>
        </div>

        {{-- Dynamic Live Counters Strip --}}
        <div class="hero-stats-row">
          <div class="hero-stat-box">
            <strong data-counter="50" data-prefix="₹" data-suffix=" Cr+">₹50 Cr+</strong>
            <span>Disbursed</span>
          </div>
          <div class="hero-stat-box">
            <strong data-counter="2000" data-suffix="+">2,000+</strong>
            <span>Borrowers</span>
          </div>
          <div class="hero-stat-box">
            <strong data-counter="99" data-suffix="%">99%</strong>
            <span>Approval Rate</span>
          </div>
          <div class="hero-stat-box">
            <strong data-counter="24" data-suffix="h">24h</strong>
            <span>Turnaround</span>
          </div>
        </div>
      </div>

      {{-- Right Column: Family Dream Visual (Default Match) with Instant EMI Toggle --}}
      <div class="col-lg-6">
        <div class="d-flex justify-content-end mb-2">
          <div class="hero-view-toggle-pill" role="tablist">
            <button type="button" class="hero-view-toggle-btn active" id="btnHeroFamilyView" onclick="switchHeroView('family')">
              <i class="ri-funds-box-line me-1"></i> Financial Goals
            </button>
            <button type="button" class="hero-view-toggle-btn" id="btnHeroEmiView" onclick="switchHeroView('emi')">
              <i class="ri-calculator-line me-1"></i> Quick Calculator
            </button>
          </div>
        </div>

        {{-- Panel 1: Financial Goals Visual Card --}}
        <div id="heroFamilyPanel">
          <div class="hero-visual-card">
            <img src="{{ asset('assets/img/website/hero-financial-goals.jpg') }}" alt="Financial Goals & Lending Solutions" class="hero-family-img">
            <div class="hero-script-overlay">
              Dream Bigger...<br>
              <span>Achieve More...</span>
            </div>
            <div class="hero-floating-pill">
              <div style="width:34px;height:34px;border-radius:50%;background:#eef4ff;color:#1a5cff;display:flex;align-items:center;justify-content:center">
                <i class="ri-shield-check-fill fs-5"></i>
              </div>
              <div>
                <div style="font-size:.82rem;font-weight:800;color:#0e2b5c;line-height:1.2">Fast Digital Disbursal</div>
                <div style="font-size:.7rem;color:#64748b">Transparent Terms · Paperless KYC</div>
              </div>
            </div>
          </div>
        </div>

        {{-- Panel 2: Interactive Quick EMI Calculator --}}
        <div id="heroEmiPanel" class="d-none">
          <div class="hero-emi-card">
          <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <div>
              <span class="sf-section-label mb-1" style="font-size:.68rem">Instant Estimate</span>
              <h3 class="fw-800 h6 mb-0" style="color:#0e2b5c">Quick EMI Calculator</h3>
            </div>
            <div class="d-flex gap-1" id="hFreqGroup" role="group">
              <button type="button" class="freq-tab-btn" data-freq="daily">Daily</button>
              <button type="button" class="freq-tab-btn" data-freq="weekly">Weekly</button>
              <button type="button" class="freq-tab-btn active" data-freq="monthly">Monthly</button>
            </div>
          </div>

          {{-- Preset Pills --}}
          <div class="d-flex gap-1 overflow-auto pb-2 mb-2" style="scrollbar-width:none">
            <button type="button" class="preset-tab-btn active" onclick="applyPreset(200000, 13.5, 24, this, 'monthly')">Personal (13.5%)</button>
            <button type="button" class="preset-tab-btn" onclick="applyPreset(500000, 15, 36, this, 'monthly')">Business (15%)</button>
            <button type="button" class="preset-tab-btn" onclick="applyPreset(100000, 9.5, 12, this, 'monthly')">Gold (9.5%)</button>
            <button type="button" class="preset-tab-btn" onclick="applyPreset(1500000, 8.5, 120, this, 'monthly')">Home (8.5%)</button>
            <button type="button" class="preset-tab-btn" onclick="applyPreset(100000, 15, 52, this, 'weekly')">Weekly loan</button>
          </div>

          {{-- Loan Amount Slider --}}
          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small text-muted mb-0 fw-semibold" style="font-size:.78rem">Loan Amount</label>
              <span class="fw-800 text-primary" id="hLoanAmtLabel" style="font-size:.92rem">₹ 2,00,000</span>
            </div>
            <input type="range" class="custom-slider my-1" id="hLoanSlider" min="10000" max="2500000" step="10000" value="200000">
            <div class="d-flex justify-content-between align-items-center mt-1">
              <div class="d-flex gap-1">
                <button type="button" class="quick-val-chip" onclick="setQuickVal(50000)">₹50K</button>
                <button type="button" class="quick-val-chip" onclick="setQuickVal(100000)">₹1L</button>
                <button type="button" class="quick-val-chip" onclick="setQuickVal(200000)">₹2L</button>
                <button type="button" class="quick-val-chip" onclick="setQuickVal(500000)">₹5L</button>
                <button type="button" class="quick-val-chip" onclick="setQuickVal(1000000)">₹10L</button>
              </div>
              <span class="text-muted" style="font-size:.7rem">Max ₹25L</span>
            </div>
          </div>

          {{-- Rate & Tenure in 2 columns --}}
          <div class="row g-2 mb-2">
            <div class="col-6">
              <div class="p-2 rounded-3 bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="text-muted mb-0 fw-semibold" style="font-size:.72rem">Interest Rate</label>
                  <span class="fw-800 text-primary" id="hRateLabel" style="font-size:.75rem">13.5%</span>
                </div>
                <input type="range" class="custom-slider my-1" id="hRateSlider" min="7" max="28" step="0.25" value="13.5">
                <div class="d-flex justify-content-between text-muted" style="font-size:.65rem">
                  <span>7%</span>
                  <span>28% p.a.</span>
                </div>
              </div>
            </div>
            <div class="col-6">
              <div class="p-2 rounded-3 bg-light border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="text-muted mb-0 fw-semibold" style="font-size:.72rem">Tenure</label>
                  <span class="fw-800 text-primary" id="hTenureLabel" style="font-size:.75rem">24 Months</span>
                </div>
                <input type="range" class="custom-slider my-1" id="hTenureSlider" min="6" max="120" step="6" value="24">
                <div class="d-flex justify-content-between text-muted" style="font-size:.65rem">
                  <span id="hTenureMinHint">6 Mo</span>
                  <span id="hTenureMaxHint">10 Yrs</span>
                </div>
              </div>
            </div>
          </div>

          {{-- Compact Result Strip --}}
          <div class="hero-emi-result-strip mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-white-50 text-uppercase fw-bold" style="font-size:.68rem;letter-spacing:.04em">Estimated Instalment</span>
              <div class="d-flex align-items-baseline gap-1">
                <span class="fw-800 fs-5 text-white lh-1" id="hEmi">₹9,553</span>
                <span class="text-white-50 small" id="hEmiUnit">/ mo</span>
              </div>
            </div>

            <div class="d-flex justify-content-between text-white-50 mt-2 pt-2 border-top border-white border-opacity-15" style="font-size:.72rem">
              <span>Principal: <strong class="text-white" id="hBarPrincipal">₹2,00,000</strong></span>
              <span>Total Interest: <strong class="text-white" id="hInterest">₹29,268</strong></span>
            </div>

            <div class="progress mt-2" style="height: 5px; background: rgba(255,255,255,.2); border-radius: 999px; overflow:hidden">
              <div class="progress-bar bg-white" id="hProgressPrincipal" role="progressbar" style="width: 86%;"></div>
              <div class="progress-bar bg-info" id="hProgressInterest" role="progressbar" style="width: 14%;"></div>
            </div>
          </div>

          {{-- Direct Action Button --}}
          <div class="d-grid">
            <a href="{{ route('website.apply') }}" id="hApplyBtn" class="btn btn-primary fw-800 py-2 rounded-pill shadow-sm" style="font-size:.85rem">
              Apply for this Loan <i class="ri-arrow-right-line ms-1"></i>
            </a>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
</section>

{{-- ── 2. FIVE FEATURE BADGES STRIP (Overlapping) ── --}}
<div class="container finora-features-strip">
  <div class="d-flex flex-wrap gap-2 gap-lg-3 justify-content-center justify-content-xl-between">
    <div class="feature-badge-pill">
      <div class="feature-badge-icon"><i class="ri-shield-check-line"></i></div>
      <span>Secure &amp; Trusted</span>
    </div>
    <div class="feature-badge-pill">
      <div class="feature-badge-icon"><i class="ri-flashlight-line"></i></div>
      <span>Fast Approval</span>
    </div>
    <div class="feature-badge-pill">
      <div class="feature-badge-icon"><i class="ri-hand-coin-line"></i></div>
      <span>Transparent Charges</span>
    </div>
    <div class="feature-badge-pill">
      <div class="feature-badge-icon"><i class="ri-customer-service-2-line"></i></div>
      <span>Dedicated Support</span>
    </div>
    <div class="feature-badge-pill">
      <div class="feature-badge-icon"><i class="ri-award-line"></i></div>
      <span>Customer First</span>
    </div>
  </div>
</div>

{{-- ── 3. OUR LOAN PRODUCTS (Curated Photo Cards with Filters) ── --}}
<section class="container py-4 my-2">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-3">
    <div>
      <span class="sf-section-label mb-1">Financing Options</span>
      <h2 class="fw-800 h3 mb-1" style="color:#0e2b5c;letter-spacing:-.02em">Our Loan Products</h2>
      <p class="text-muted mb-0">Choose from a wide range of flexible loan options designed for your needs</p>
    </div>
    <a href="{{ route('website.loans.index') }}" class="fw-700 text-primary text-decoration-none d-inline-flex align-items-center gap-1">
      View All Loans <i class="ri-arrow-right-line"></i>
    </a>
  </div>

  {{-- Category Filter Pills --}}
  @if($categories->count() > 1)
  <div class="overflow-auto pb-2 mb-4" style="scrollbar-width:none">
    <div class="d-flex gap-2 flex-nowrap align-items-center" id="homeCatFilterGroup">
      <button type="button" class="home-cat-pill active d-inline-flex align-items-center gap-1" data-cat="all">
        <i class="ri-apps-line"></i> <span>All Products</span>
      </button>
      @foreach($categories as $cat)
        @if($cat->products->count())
          <button type="button" class="home-cat-pill d-inline-flex align-items-center gap-2" data-cat="{{ Str::slug($cat->name) }}">
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
  @endif

  <div class="row g-4" id="homeProductGrid">
    @forelse($featuredProducts as $product)
      @php
        $banner = $product->loanType?->loan_type_banner_url
          ?: ($product->loanType?->loan_type_image_url ?: $website->productImage($product));
        $icon = $product->loanType?->loan_type_icon_url;
        $slug = $website->productSlug($product);
        $rate = $product->interest_rate_min ?? $product->interest_rate ?? null;
        $catSlug = $product->loanType ? Str::slug($product->loanType->name) : 'general';
      @endphp
      <div class="col-sm-6 col-lg-4 col-xl-3 home-product-col" data-cat="{{ $catSlug }}">
        <article class="finora-prod-card">
          <div class="finora-prod-media">
            <img src="{{ $banner }}" alt="{{ $product->loan_name }}" class="finora-prod-img" loading="lazy">
            @if(!empty($icon))
              <div class="prod-card-logo-badge" title="{{ $product->loanType?->name }}">
                <img src="{{ $icon }}" alt="{{ $product->loanType?->name }}" class="prod-card-logo-img">
              </div>
            @endif
            @if($rate)
              <span class="finora-prod-tag">{{ $rate }}% p.a.</span>
            @endif
          </div>
          <div class="finora-prod-body">
            @if($product->loanType)
              <div class="d-flex align-items-center gap-1 mb-2">
                <span class="badge rounded-pill bg-light text-primary border" style="font-size:.72rem">
                  {{ $product->loanType->name }}
                </span>
              </div>
            @endif
            <h3 class="finora-prod-title">{{ $product->loan_name }}</h3>
            <p class="finora-prod-sub">
              {{ Str::limit($product->short_description ?? $product->description ?? 'Tailored terms with fast digital verification.', 65) }}
            </p>
            <div class="finora-prod-range">
              ₹{{ number_format($product->loan_amount_min ?? 0) }} – ₹{{ number_format($product->loan_amount_max ?? 0) }}
            </div>
            <div class="finora-prod-footer">
              <a href="{{ route('website.loans.show', $slug) }}" class="btn-learn-more">
                Learn More <i class="ri-arrow-right-line"></i>
              </a>
              <a href="{{ route('website.apply', ['product' => $slug]) }}" class="btn-card-apply">
                Apply Now <i class="ri-arrow-right-line ms-1"></i>
              </a>
            </div>
          </div>
        </article>
      </div>
    @empty
      <div class="col-12 text-center py-5">
        <p class="text-muted">Explore all our loan products on the loan catalogue.</p>
        <a href="{{ route('website.loans.index') }}" class="btn btn-primary rounded-pill">View Loan Products</a>
      </div>
    @endforelse
  </div>
</section>

{{-- ── 4. SIMPLE 4-STEP PROCESS ── --}}
<section class="container py-5 my-2">
  <div class="text-center mb-5">
    <span class="sf-section-label mb-2">Easy Journey</span>
    <h2 class="sf-section-title mb-2">How It Works</h2>
    <p class="sf-section-sub mx-auto">Apply online, complete digital verification, and receive your funds promptly.</p>
  </div>

  <div class="row g-4">
    @foreach([
      ['num'=>'1', 'title'=>'Apply Online', 'desc'=>'Pick your loan product, choose tenure, and fill our simple 3-minute form with basic details.'],
      ['num'=>'2', 'title'=>'Digital Verification', 'desc'=>'Instant e-KYC using Aadhaar and PAN check for seamless paperless confirmation.'],
      ['num'=>'3', 'title'=>'Credit Review', 'desc'=>'Our operations team reviews your application against product criteria with complete transparency.'],
      ['num'=>'4', 'title'=>'Approval & Disbursal', 'desc'=>'Funds are disbursed directly to your verified bank account once approved.'],
    ] as $s)
    <div class="col-sm-6 col-lg-3">
      <div class="process-step-v2">
        <span class="step-number-circle">{{ $s['num'] }}</span>
        <h4 class="fw-800 h6 mb-2" style="color:#0e2b5c">{{ $s['title'] }}</h4>
        <p class="text-muted small mb-0" style="line-height:1.65">{{ $s['desc'] }}</p>
      </div>
    </div>
    @endforeach
  </div>
</section>

{{-- ── 5. WHY CHOOSE US ── --}}
<section class="py-5" style="background:#f8fbff;border-top:1px solid #e2edf8;border-bottom:1px solid #e2edf8">
  <div class="container py-2">
    <div class="row align-items-center g-5">
      <div class="col-lg-5">
        <span class="sf-section-label mb-2">Why {{ $brand['name'] ?? 'Star Finance' }}</span>
        <h2 class="sf-section-title mb-3">Lending Made Transparent &amp; Simple</h2>
        <p class="text-muted mb-4" style="line-height:1.75">
          We combine automated digital workflows with real customer care. Clear rates, honest guidelines, and zero hidden charges ensure you borrow with total peace of mind.
        </p>
        <div class="d-flex flex-column gap-3 mb-4">
          <div class="d-flex align-items-start gap-2">
            <i class="ri-checkbox-circle-fill text-primary fs-5 mt-1"></i>
            <div>
              <strong class="d-block text-dark small fw-bold">No Hidden Charges</strong>
              <span class="text-muted small">Every fee and interest calculation is explained before you sign.</span>
            </div>
          </div>
          <div class="d-flex align-items-start gap-2">
            <i class="ri-checkbox-circle-fill text-primary fs-5 mt-1"></i>
            <div>
              <strong class="d-block text-dark small fw-bold">Dedicated Relationship Manager</strong>
              <span class="text-muted small">Speak directly to an advisor whenever you need guidance.</span>
            </div>
          </div>
          <div class="d-flex align-items-start gap-2">
            <i class="ri-checkbox-circle-fill text-primary fs-5 mt-1"></i>
            <div>
              <strong class="d-block text-dark small fw-bold">100% Digital &amp; Paperless</strong>
              <span class="text-muted small">Apply, upload documents, and track status right from your phone.</span>
            </div>
          </div>
        </div>
        <a href="{{ route('website.about') }}" class="btn btn-outline-primary rounded-pill px-4">
          Learn More About Us <i class="ri-arrow-right-line ms-1"></i>
        </a>
      </div>

      <div class="col-lg-7">
        <div class="row g-3">
          @foreach([
            ['icon'=>'ri-flashlight-fill','bg'=>'#fff4e5','color'=>'#b45309','title'=>'Fast Processing','desc'=>'Decisions in as little as 24 hours of submission.'],
            ['icon'=>'ri-percent-line','bg'=>'#ecfdf5','color'=>'#065f46','title'=>'Competitive Rates','desc'=>'Interest rates starting from just 8.5% p.a.'],
            ['icon'=>'ri-file-reduce-line','bg'=>'#eff6ff','color'=>'#1e40af','title'=>'Minimal Documents','desc'=>'Just PAN, Aadhaar, and bank account for verification.'],
            ['icon'=>'ri-customer-service-2-fill','bg'=>'#fdf4ff','color'=>'#7e22ce','title'=>'Expert Support','desc'=>'Available 6 days a week via phone, email or WhatsApp.'],
            ['icon'=>'ri-shield-check-fill','bg'=>'#f0fdf4','color'=>'#15803d','title'=>'100% Secure','desc'=>'Bank-grade encryption and RBI-compliant data protection.'],
            ['icon'=>'ri-refresh-line','bg'=>'#fff1f2','color'=>'#be123c','title'=>'Flexible Repayment','desc'=>'Daily, weekly, or monthly instalment options.'],
          ] as $w)
          <div class="col-sm-6">
            <div class="p-3 rounded-4 bg-white border h-100" style="border-color:#e2edf8!important">
              <div style="width:42px;height:42px;border-radius:.75rem;background:{{ $w['bg'] }};display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:{{ $w['color'] }};margin-bottom:.75rem">
                <i class="{{ $w['icon'] }}"></i>
              </div>
              <h4 class="fw-700 h6 mb-1" style="color:#0e2b5c">{{ $w['title'] }}</h4>
              <p class="text-muted small mb-0" style="line-height:1.55;font-size:.8rem">{{ $w['desc'] }}</p>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── 6. CUSTOMER TESTIMONIALS ── --}}
<section class="container py-5 my-2">
  <div class="text-center mb-5">
    <span class="sf-section-label mb-2">Testimonials</span>
    <h2 class="sf-section-title mb-2">Loved by Thousands of Borrowers</h2>
    <p class="sf-section-sub mx-auto">Real stories from people who achieved their personal and business goals with us.</p>
  </div>

  <div class="row g-4">
    @forelse(($testimonials ?? collect()) as $t)
      <div class="col-md-4">
        <div class="home-testi-card">
          <div class="testi-stars-gold">
            @for($i = 0; $i < min(5, max(1, (int) $t->rating)); $i++)★@endfor
          </div>
          <p class="text-muted small flex-grow-1" style="line-height:1.75">"{{ $t->content }}"</p>
          <div class="d-flex align-items-center gap-3 pt-3 border-top border-light">
            <div class="testi-avatar-circle">{{ mb_substr($t->name, 0, 1) }}</div>
            <div>
              <strong class="d-block text-dark small fw-bold">{{ $t->name }}</strong>
              <span class="text-muted" style="font-size:.78rem">{{ $t->role ?? $t->location ?? 'Verified Customer' }}</span>
            </div>
          </div>
        </div>
      </div>
    @empty
      {{-- Default Rich Customer Reviews --}}
      @foreach([
        ['name'=>'Rajesh Kumar', 'role'=>'Business Loan · Coimbatore', 'stars'=>5, 'review'=>'Got my business working capital loan sanctioned within 24 hours. The paperwork was completely paperless and interest rates were very fair!'],
        ['name'=>'Priya Sundaram', 'role'=>'Personal Loan · Chennai', 'stars'=>5, 'review'=>'Excellent customer support. They guided me through every step of Aadhaar KYC and the funds were in my account the very next morning.'],
        ['name'=>'Anand Mahadevan', 'role'=>'Home Improvement · Bangalore', 'stars'=>5, 'review'=>'No hidden charges and crystal-clear EMI schedule. I calculated my repayment online and got the exact same terms. Highly recommended!'],
      ] as $rev)
      <div class="col-md-4">
        <div class="home-testi-card">
          <div class="testi-stars-gold">
            @for($i = 0; $i < $rev['stars']; $i++)★@endfor
          </div>
          <p class="text-muted small flex-grow-1" style="line-height:1.75">"{{ $rev['review'] }}"</p>
          <div class="d-flex align-items-center gap-3 pt-3 border-top border-light">
            <div class="testi-avatar-circle">{{ mb_substr($rev['name'], 0, 1) }}</div>
            <div>
              <strong class="d-block text-dark small fw-bold">{{ $rev['name'] }}</strong>
              <span class="text-muted" style="font-size:.78rem">{{ $rev['role'] }}</span>
            </div>
          </div>
        </div>
      </div>
      @endforeach
    @endforelse
  </div>
</section>

{{-- ── 7. FREQUENTLY ASKED QUESTIONS (FAQ) ── --}}
<section class="py-5" style="background:#f8fbff;border-top:1px solid #e2edf8">
  <div class="container py-2">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <span class="sf-section-label mb-2">Got Questions?</span>
        <h2 class="sf-section-title mb-3">Frequently Asked Questions</h2>
        <p class="text-muted mb-4" style="line-height:1.75">
          Everything you need to know about our products, interest rates, eligibility requirements, and repayment process.
        </p>
        <div class="p-4 rounded-4 bg-white border" style="border-color:#e2edf8!important">
          <h5 class="fw-800 mb-2" style="color:#0e2b5c;font-size:1.05rem">Need personalized help?</h5>
          <p class="text-muted small mb-3">Our dedicated loan officers are available Monday to Saturday to guide you.</p>
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('website.contact') }}" class="btn btn-sm btn-primary rounded-pill px-3">
              <i class="ri-phone-line me-1"></i> Contact Us
            </a>
            <a href="{{ route('website.faq') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
              View All FAQs
            </a>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="accordion" id="homeFaqAccordion">
          @php
            $displayFaqs = (!empty($faqs) && $faqs->isNotEmpty()) ? $faqs : collect([
              (object)['id'=>1, 'question'=>'What documents are needed to apply for a loan?', 'answer'=>'You only need basic KYC documents: your PAN Card, Aadhaar Card for digital OTP verification, and latest 3 to 6 months bank statements. No physical paperwork required.'],
              (object)['id'=>2, 'question'=>'How quickly will my loan get approved and disbursed?', 'answer'=>'Most online applications receive in-principle approval within 2 to 24 hours of document submission. Once approved, funds are directly transferred to your verified bank account.'],
              (object)['id'=>3, 'question'=>'Are there any hidden fees or pre-closure penalties?', 'answer'=>'None. Star Finance operates on 100% transparency. All interest rates, processing fees, and terms are fully disclosed upfront before you sign any agreement.'],
              (object)['id'=>4, 'question'=>'Can I choose between daily, weekly, and monthly repayments?', 'answer'=>'Yes! We offer daily, weekly, and monthly repayment schedules tailored to salary earners, business owners, and self-employed professionals.'],
              (object)['id'=>5, 'question'=>'How can I track my submitted loan application status?', 'answer'=>'You can track your application in real-time on our Track Application page using your reference number, or sign in via our Customer Portal.']
            ]);
          @endphp

          @foreach($displayFaqs as $idx => $fq)
          <div class="accordion-item home-faq-item">
            <h3 class="accordion-header" id="headingFaq{{ $fq->id }}">
              <button class="accordion-button home-faq-button {{ $idx > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFaq{{ $fq->id }}" aria-expanded="{{ $idx === 0 ? 'true' : 'false' }}" aria-controls="collapseFaq{{ $fq->id }}">
                {{ $fq->question }}
              </button>
            </h3>
            <div id="collapseFaq{{ $fq->id }}" class="accordion-collapse collapse {{ $idx === 0 ? 'show' : '' }}" aria-labelledby="headingFaq{{ $fq->id }}" data-bs-parent="#homeFaqAccordion">
              <div class="accordion-body text-muted small" style="line-height:1.75">
                {{ $fq->answer }}
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── 8. READY TO TAKE THE NEXT STEP CTA ── --}}
<section class="container py-5 my-3">
  <div class="p-4 p-lg-5 text-center rounded-4 text-white" style="background:linear-gradient(135deg, #09121f 0%, #0e2b5c 55%, #184ca1 100%);box-shadow:0 20px 50px rgba(14,43,92,.25)">
    <h2 class="fw-800 mb-2 display-6" style="letter-spacing:-.03em">Ready to take the next step?</h2>
    <p class="text-white-50 mb-4 mx-auto" style="max-width:520px;font-size:1.05rem">
      Submit your application online in just 3 minutes. Disbursal goes directly to your verified bank account.
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-3">
      <a href="{{ route('website.apply') }}" class="btn btn-light fw-800 rounded-pill px-4 py-2 text-primary shadow-sm">
        Apply Now <i class="ri-arrow-right-line ms-1"></i>
      </a>
      <a href="{{ route('website.contact') }}" class="btn btn-outline-light fw-700 rounded-pill px-4 py-2">
        Contact an Advisor
      </a>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
// Category Filter Interaction on Homepage
document.addEventListener('DOMContentLoaded', function () {
  const catPills = document.querySelectorAll('#homeCatFilterGroup .home-cat-pill');
  const prodCols = document.querySelectorAll('.home-product-col');

  catPills.forEach(pill => {
    pill.addEventListener('click', function () {
      catPills.forEach(p => p.classList.remove('active'));
      this.classList.add('active');

      const target = this.getAttribute('data-cat');
      prodCols.forEach(col => {
        if (target === 'all' || col.getAttribute('data-cat') === target) {
          col.style.display = '';
        } else {
          col.style.display = 'none';
        }
      });
    });
  });
});

// Interactive Quick EMI calculator
(function(){
  var loanSlider = document.getElementById('hLoanSlider');
  var rateSlider = document.getElementById('hRateSlider');
  var tenureSlider = document.getElementById('hTenureSlider');

  var loanLabel = document.getElementById('hLoanAmtLabel');
  var rateLabel = document.getElementById('hRateLabel');
  var tenureLabel = document.getElementById('hTenureLabel');
  var tenureMinHint = document.getElementById('hTenureMinHint');
  var tenureMaxHint = document.getElementById('hTenureMaxHint');

  var emiEl = document.getElementById('hEmi');
  var emiUnitEl = document.getElementById('hEmiUnit');
  var interestEl = document.getElementById('hInterest');
  var totalEl = document.getElementById('hTotal');

  var barPrincipal = document.getElementById('hBarPrincipal');
  var progPrincipal = document.getElementById('hProgressPrincipal');
  var progInterest = document.getElementById('hProgressInterest');
  var applyBtn = document.getElementById('hApplyBtn');

  var payFrequency = 'monthly';

  var fmt = function(v){
    return '₹ ' + Math.round(v).toLocaleString('en-IN');
  };

  function periodsPerYear(freq) {
    if (freq === 'daily') return 365;
    if (freq === 'weekly') return 52;
    return 12;
  }

  function installmentUnit(freq) {
    if (freq === 'daily') return '/ day';
    if (freq === 'weekly') return '/ wk';
    return '/ mo';
  }

  function setFrequency(freq, btn) {
    payFrequency = freq;
    document.querySelectorAll('.freq-tab-btn').forEach(function(b) {
      b.classList.toggle('active', b.getAttribute('data-freq') === freq);
    });
    configureTenureSlider(freq);
    update();
  }

  function configureTenureSlider(freq) {
    var current = parseInt(tenureSlider.value, 10) || 1;
    if (freq === 'daily') {
      tenureSlider.min = 30;
      tenureSlider.max = 365;
      tenureSlider.step = 1;
      if (current < 30 || current > 365) tenureSlider.value = 90;
      if (tenureMinHint) tenureMinHint.textContent = '30 days';
      if (tenureMaxHint) tenureMaxHint.textContent = '1 year';
    } else if (freq === 'weekly') {
      tenureSlider.min = 4;
      tenureSlider.max = 260;
      tenureSlider.step = 1;
      if (current < 4 || current > 260) tenureSlider.value = 52;
      if (tenureMinHint) tenureMinHint.textContent = '4 wks';
      if (tenureMaxHint) tenureMaxHint.textContent = '5 years';
    } else {
      tenureSlider.min = 6;
      tenureSlider.max = 120;
      tenureSlider.step = 6;
      if (current < 6 || current > 120) tenureSlider.value = 24;
      if (tenureMinHint) tenureMinHint.textContent = '6 Mo';
      if (tenureMaxHint) tenureMaxHint.textContent = '10 Yrs';
    }
  }

  function tenureMonthsEquivalent(periods, freq) {
    if (freq === 'daily') return Math.max(1, Math.ceil(periods / 30));
    if (freq === 'weekly') return Math.max(1, Math.ceil(periods / 4.345));
    return Math.max(1, periods);
  }

  function formatTenureLabel(periods, freq) {
    if (freq === 'daily') return periods + ' Days';
    if (freq === 'weekly') return periods + ' Weeks';
    var yrs = periods / 12;
    return periods + ' Months' + (yrs >= 1 ? ' (' + (yrs % 1 === 0 ? yrs : yrs.toFixed(1)) + ' Yrs)' : '');
  }

  function update() {
    var P = parseFloat(loanSlider.value) || 200000;
    var annualRate = parseFloat(rateSlider.value) || 13.5;
    var n = parseInt(tenureSlider.value, 10) || 1;
    if (n < 1) n = 1;

    var r = (annualRate / 100) / periodsPerYear(payFrequency);

    loanLabel.textContent = fmt(P);
    rateLabel.textContent = annualRate + '% p.a.';
    tenureLabel.textContent = formatTenureLabel(n, payFrequency);
    if (emiUnitEl) emiUnitEl.textContent = installmentUnit(payFrequency);

    var emi = 0;
    if (r === 0) {
      emi = P / n;
    } else {
      var factor = Math.pow(1 + r, n);
      emi = (P * r * factor) / (factor - 1);
    }

    var totalRepayment = emi * n;
    var totalInterest = Math.max(0, totalRepayment - P);
    var principalPct = totalRepayment > 0 ? Math.round((P / totalRepayment) * 100) : 100;
    var interestPct = Math.max(0, 100 - principalPct);

    emiEl.textContent = fmt(emi);
    interestEl.textContent = fmt(totalInterest);
    totalEl.textContent = fmt(totalRepayment);
    barPrincipal.textContent = fmt(P);
    progPrincipal.style.width = principalPct + '%';
    progInterest.style.width = interestPct + '%';

    if (applyBtn) {
      var months = tenureMonthsEquivalent(n, payFrequency);
      applyBtn.href = "{{ route('website.apply') }}?amount=" + Math.round(P) + "&tenure=" + months;
    }
  }

  window.switchHeroView = function(v) {
    var famPanel = document.getElementById('heroFamilyPanel');
    var emiPanel = document.getElementById('heroEmiPanel');
    var btnFam = document.getElementById('btnHeroFamilyView');
    var btnEmi = document.getElementById('btnHeroEmiView');
    if (v === 'emi') {
      if (famPanel) famPanel.classList.add('d-none');
      if (emiPanel) emiPanel.classList.remove('d-none');
      if (btnFam) btnFam.classList.remove('active');
      if (btnEmi) btnEmi.classList.add('active');
    } else {
      if (famPanel) famPanel.classList.remove('d-none');
      if (emiPanel) emiPanel.classList.add('d-none');
      if (btnFam) btnFam.classList.add('active');
      if (btnEmi) btnEmi.classList.remove('active');
    }
  };

  window.setQuickVal = function(val) {
    loanSlider.value = val;
    update();
  };

  window.applyPreset = function(amt, rate, tenure, btn, freq) {
    if (freq) {
      payFrequency = freq;
      document.querySelectorAll('.freq-tab-btn').forEach(function(b) {
        b.classList.toggle('active', b.getAttribute('data-freq') === freq);
      });
      configureTenureSlider(freq);
    }
    loanSlider.value = amt;
    rateSlider.value = rate;
    tenureSlider.value = tenure;
    document.querySelectorAll('.preset-tab-btn').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    update();
  };

  document.querySelectorAll('.freq-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      setFrequency(btn.getAttribute('data-freq'), btn);
    });
  });

  [loanSlider, rateSlider, tenureSlider].forEach(function(el) {
    if (el) {
      el.addEventListener('input', update);
      el.addEventListener('change', update);
    }
  });

  configureTenureSlider(payFrequency);
  update();
})();
</script>
@endpush
