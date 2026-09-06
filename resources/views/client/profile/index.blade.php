@php
  $isMenu = false;
  $navbarFull = true;
  
  // Extract initials for avatar
  $nameParts = explode(' ', $client->client_name);
  $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
@endphp

@extends('layouts/layoutMaster')

@section('title', 'My Profile')

@section('content')
<div class="row g-6">
    <!-- Header & Back Button -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Profile</li>
                </ol>
            </nav>
            <a href="{{ route('client.dashboard') }}" class="btn btn-outline-secondary d-flex align-items-center">
                <i class="ri-arrow-left-line me-2"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- User Profile Header -->
    <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <div class="bg-primary p-10 position-relative" style="height: 100px;">
                    <!-- Decorative patterns can go here -->
                </div>
                <div class="px-6 pb-6 position-relative" style="margin-top: -50px;">
                    <div class="d-flex align-items-end flex-wrap gap-4">
                        <div class="avatar avatar-xl border border-5 border-card rounded-circle bg-white shadow-sm">
                            <span class="avatar-initial rounded-circle bg-label-primary fs-2 fw-bold text-uppercase">{{ $initials }}</span>
                        </div>
                        <div class="flex-grow-1 mt-3 mt-sm-0">
                            <h4 class="mb-1 fw-bold text-heading">{{ $client->client_name }}</h4>
                            <div class="d-flex align-items-center flex-wrap gap-3">
                                <span class="text-muted d-flex align-items-center"><i class="ri-hashtag me-1"></i> ID: CUST-{{ str_pad($client->id, 5, '0', STR_PAD_LEFT) }}</span>
                                <span class="badge bg-label-success rounded-pill px-3">
                                    <i class="ri-checkbox-circle-line me-1"></i> {{ $client->verified ? 'Verified Customer' : 'Pending Verification' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Info Sections -->
    <div class="col-lg-8">
        <div class="row g-6">
            <!-- Personal Information -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom py-4">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-3 bg-label-info rounded">
                                <i class="ri-user-line fs-4"></i>
                            </div>
                            <h5 class="mb-0 fw-bold">Personal Information</h5>
                        </div>
                    </div>
                    <div class="card-body py-5">
                        <div class="row g-5">
                            <div class="col-md-6 text-start">
                                <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">Full Legal Name</label>
                                <span class="text-heading fw-medium fs-5">{{ $client->client_name }}</span>
                            </div>
                            <div class="col-md-6 text-start">
                                <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">Mother's Name</label>
                                <span class="text-heading fw-medium fs-5">{{ $client->mother_name ?? 'N/A' }}</span>
                            </div>
                            <div class="col-md-6 text-start">
                                <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">Aadhaar Number</label>
                                <span class="text-heading fw-medium fs-5">{{ $client->aadhaar_no ? 'XXXX-XXXX-' . substr($client->aadhaar_no, -4) : 'Not Provided' }}</span>
                            </div>
                            <div class="col-md-6 text-start">
                                <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">PAN Number</label>
                                <span class="text-heading fw-medium fs-5 text-uppercase">{{ $client->pan_no ?? 'Not Provided' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Residential Address -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom py-4">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-3 bg-label-warning rounded">
                                <i class="ri-map-pin-line fs-4"></i>
                            </div>
                            <h5 class="mb-0 fw-bold">Permanent Address</h5>
                        </div>
                    </div>
                    <div class="card-body py-5">
                        <div class="d-flex align-items-start gap-4">
                            <div class="flex-grow-1 text-start">
                                <p class="text-heading fw-medium mb-1 fs-5">{{ $client->address }}</p>
                                <p class="text-muted mb-0">
                                    {{ $client->district ?? '' }}, {{ $client->state ?? '' }}<br>
                                    Postal Code: <span class="text-heading fw-medium">{{ $client->pincode }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact & Account Summary -->
    <div class="col-lg-4">
        <div class="row g-6">
            <!-- Contact Details -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom py-4 text-start">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-3 bg-label-success rounded">
                                <i class="ri-phone-line fs-4"></i>
                            </div>
                            <h5 class="mb-0 fw-bold">Contact Details</h5>
                        </div>
                    </div>
                    <div class="card-body py-5 text-start">
                        <div class="mb-5">
                            <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">Mobile Number</label>
                            <span class="text-heading fw-medium fs-6 d-flex align-items-center">
                                <i class="ri-whatsapp-line text-success me-2"></i> +91 {{ $client->mobile_no }}
                            </span>
                        </div>
                        <div class="mb-0">
                            <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">Secondary Phone</label>
                            <span class="text-heading fw-medium fs-6">{{ $client->client_phone ?? 'N/A' }}</span>
                        </div>
                        <div class="mt-5">
                            <label class="text-muted small text-uppercase fw-semibold mb-1 d-block">Email Address</label>
                            <span class="text-heading fw-medium fs-6 text-lowercase d-flex align-items-center">
                                <i class="ri-mail-line text-info me-2"></i> {{ $client->client_email }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Footer Metadata -->
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-label-secondary">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center mb-3 text-start">
                            <i class="ri-shield-check-line ri-24px me-3 text-primary"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Customer since</h6>
                                <small class="text-muted">{{ $client->created_at->format('M Y') }}</small>
                            </div>
                        </div>
                        <hr class="my-3 opacity-50">
                        <p class="small text-muted mb-0 lh-base text-start">
                            <i class="ri-information-line me-1"></i> These details are synchronized with your original KYC documents and cannot be edited online.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
