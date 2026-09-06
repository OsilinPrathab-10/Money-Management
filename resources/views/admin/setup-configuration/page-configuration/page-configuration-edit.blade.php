@extends('layouts/layoutMaster')

@section('title', 'Edit ' . ($pageName ?? 'Page Configuration'))
<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/quill/typography.scss', 'resources/assets/vendor/libs/highlight/highlight.scss', 'resources/assets/vendor/libs/quill/katex.scss', 'resources/assets/vendor/libs/quill/editor.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/quill/katex.js', 'resources/assets/vendor/libs/highlight/highlight.js', 'resources/assets/vendor/libs/quill/quill.js'])
@endsection

@section('content')

 <!-- Full Editor -->
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">{{ $pageName ?? 'Page Configuration' }}</h5>
          <a href="{{ route('page-configuration') }}" class="btn btn-sm btn-secondary">
            <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to List
          </a>
        </div>
        <div class="card-body">
          <form action="{{ $pageId ? route('page-configuration-update', $pageId) : route('page-configuration-store') }}" method="POST" id="policyPageForm">
            @csrf
            @if($pageId)
              @method('PUT')
            @endif
            
            <!-- Page Name -->
            <div class="mb-4">
              <label for="pageName" class="form-label">Page Name</label>
              <input type="text" class="form-control" id="pageName" name="name" 
                     placeholder="Enter page name" value="{{ $pageName ?? '' }}" required>
            </div>
            
            <!-- Page Content -->
            <div class="mb-4">
              <label for="pageContent" class="form-label">Page Content</label>
              <div id="full-editor">
                {!! $pageContent ?? '<h6>Enter your policy content here</h6><p>You can format text, add links, images, and more using the rich text editor.</p>' !!}
              </div>
              <input type="hidden" id="pageContent" name="content">
            </div>

            <!-- Status -->
            <div class="mb-4">
              <label for="status" class="form-label">Status</label>
              <select class="form-select" id="status" name="status" required>
                <option value="active" {{ ($pageStatus ?? 'active') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ ($pageStatus ?? '') == 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <!-- Submit Buttons -->
            <div class="d-flex gap-3 mt-4">
              <button type="submit" class="btn btn-primary">
                <i class="icon-base ri ri-save-line me-1"></i> Save Changes
              </button>
              <a href="{{ route('page-configuration') }}" class="btn btn-outline-secondary">
                <i class="icon-base ri ri-close-line me-1"></i> Cancel
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- /Full Editor -->

@endsection

@section('page-script')
  @vite(['resources/assets/js/forms-editors.js'])
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('policyPageForm');
      
      if (form) {
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          
          // Get the Quill editor instance
          const fullEditorEl = document.querySelector('#full-editor');
          if (fullEditorEl && typeof Quill !== 'undefined') {
            const quill = Quill.find(fullEditorEl);
            
            if (quill) {
              // Get the HTML content from Quill
              const content = quill.root.innerHTML;
              document.getElementById('pageContent').value = content;
              
              // Submit the form
              form.submit();
            }
          } else {
            // If Quill is not loaded, submit anyway
            form.submit();
          }
        });
      }
    });
  </script>
@endsection
