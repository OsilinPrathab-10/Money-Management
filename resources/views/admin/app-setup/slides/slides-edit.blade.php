@extends('layouts/layoutMaster')

@section('title', $slideId ? 'Edit Slide' : 'Create Slide')

@section('page-script')
@vite(['resources/assets/custom-js/slide.js'])
@endsection

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $slideId ? 'Edit Slide' : 'Create Slide' }}</h5>
        <a href="{{ route('app-setup-slides') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back
        </a>
      </div>
      <div class="card-body">
        <form action="{{ $slideId ? route('app-setup-slides-update', ['id' => $slideId]) : route('app-setup-slides-store') }}" 
              method="POST" 
              enctype="multipart/form-data">
          @csrf
          @if($slideId)
            @method('PUT')
          @endif

          <!-- Title -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="title">Title <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" 
                placeholder="Enter slide title" value="{{ old('title', $slideTitle) }}" required />
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Description -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="description">Description</label>
            <div class="col-sm-9">
              <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" 
                rows="4" placeholder="Enter slide description">{{ old('description', $slideDescription) }}</textarea>
              @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Type -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="type">Type <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                <option value="onboarding" {{ old('type', $slideType) === 'onboarding' ? 'selected' : '' }}>Onboarding</option>
                <option value="banner" {{ old('type', $slideType) === 'banner' ? 'selected' : '' }}>Banner</option>
                <option value="other" {{ old('type', $slideType) === 'other' ? 'selected' : '' }}>Other</option>
              </select>
              @error('type')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Image -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="image">Image <span class="text-danger">{{ $slideId ? '' : '*' }}</span></label>
            <div class="col-sm-9">
              @if($slideImage)
                <div class="mb-3">
                  <img src="{{ asset('storage/' . $slideImage) }}" alt="Current Image" class="rounded" width="200" height="200" style="object-fit: cover;">
                  <p class="text-muted mt-2 mb-0"><small>Current image</small></p>
                </div>
              @endif
              <input type="file" id="image" name="image" class="form-control @error('image') is-invalid @enderror" 
                accept="image/*" {{ $slideId ? '' : 'required' }} />
              @error('image')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              @php
                $initialType = old('type', $slideType);
                $imageHints = [
                  'onboarding' => 'Accepted formats: JPG, PNG, GIF | Recommended size: 500x500px | Max size: 2MB',
                  'banner' => 'Accepted formats: JPG, PNG, GIF | Recommended size: 378x208px | Max size: 2MB',
                ];
              @endphp
              <small id="imageHelperText" class="text-muted">{{ $imageHints[$initialType] ?? '' }}</small>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="row">
            <div class="col-sm-9 offset-sm-3">
              <button type="submit" class="btn btn-primary me-3">
                <i class="icon-base ri ri-save-line me-1"></i> {{ $slideId ? 'Update' : 'Create' }}
              </button>
              <a href="{{ route('app-setup-slides') }}" class="btn btn-outline-secondary">
                <i class="icon-base ri ri-close-line me-1"></i> Cancel
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
