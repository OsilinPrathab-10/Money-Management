@extends('layouts/layoutMaster')

@section('title', 'Create FAQ')

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Create New FAQ</h5>
        <a href="{{ route('faq-index') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to List
        </a>
      </div>
      <div class="card-body">
        <form action="{{ route('faq-store') }}" method="POST">
          @csrf
          
          <div class="mb-4">
            <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
            <input 
              type="text" 
              class="form-control @error('question') is-invalid @enderror" 
              id="question" 
              name="question" 
              placeholder="Enter FAQ question"
              value="{{ old('question') }}"
              required
            >
            @error('question')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-4">
            <label for="answer" class="form-label">Answer <span class="text-danger">*</span></label>
            <textarea 
              class="form-control @error('answer') is-invalid @enderror" 
              id="answer" 
              name="answer" 
              rows="6"
              placeholder="Enter FAQ answer"
              required
            >{{ old('answer') }}</textarea>
            @error('answer')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-4">
            <label for="order" class="form-label">Display Order</label>
            <input 
              type="number" 
              class="form-control @error('order') is-invalid @enderror" 
              id="order" 
              name="order" 
              placeholder="0"
              value="{{ old('order', 0) }}"
              min="0"
            >
            <small class="text-muted">Lower numbers appear first</small>
            @error('order')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary">
              <i class="icon-base ri ri-save-line me-1"></i> Save FAQ
            </button>
            <a href="{{ route('faq-index') }}" class="btn btn-outline-secondary">
              <i class="icon-base ri ri-close-line me-1"></i> Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
