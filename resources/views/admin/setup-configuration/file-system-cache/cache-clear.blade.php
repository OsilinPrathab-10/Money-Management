@extends('layouts/layoutMaster')

@section('title', 'Cache Management')

@section('page-style')
<style>
  .cache-card-wrapper {
    max-width: 720px;
    margin: 0 auto;
  }

  .cache-card {
    border: 1px solid #e4e6f1;
    border-radius: 18px;
    background-color: #fff;
    box-shadow: 0 18px 35px rgba(15, 23, 42, 0.05);
    padding: 2rem;
    text-align: center;
  }

  .cache-card__title {
    font-size: 1.1rem;
    font-weight: 500;
    color: #2f2d3a;
  }

  .cache-card__description {
    color: #6f6b7d;
    margin-bottom: 1.5rem;
  }

  .cache-card__cta {
    min-width: 200px;
    border-radius: 999px;
    font-weight: 600;
    box-shadow: none;
  }

  @media (max-width: 767.98px) {
    .cache-card {
      padding: 1.5rem;
    }
  }
</style>
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/file-system-cache.js'])
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">Cache Management</h4>
    <p class="text-muted mb-0">Manage application cache and optimize performance</p>
  </div>
</div>

<!-- Clear Cache Section -->
<div class="cache-card-wrapper">
  <div class="cache-card">
    <h5 class="cache-card__title">Clear Application Cache</h5>
    <p class="cache-card__description">Instantly reset configuration, route, view, and optimized caches during deployments or maintenance.</p>
    <button type="button" class="btn btn-primary cache-card__cta" id="clearCacheBtn">
      <i class="ri-refresh-line me-1"></i>
      Clear Cache
    </button>
  </div>
</div>

@endsection
