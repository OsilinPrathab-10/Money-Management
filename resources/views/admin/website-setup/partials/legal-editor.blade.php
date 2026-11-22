@php
  $publicUrl = \App\Http\Controllers\WebsiteSetupController::publicUrlForPolicy($page);
  $lockSlug = $lockSlug ?? false;
  $editorId = 'legal-editor-' . $page->id;
  $returnTab = $returnTab ?? 'legal';
@endphp

<form method="POST" action="{{ route('website-legal-pages.update', $page->id) }}" id="legalForm-{{ $page->id }}" class="legal-page-form">
  @csrf
  @method('PUT')
  <input type="hidden" name="return_tab" value="{{ $returnTab }}">
  <input type="hidden" name="content" id="legalContent-{{ $page->id }}">

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="mb-3">
        <label class="form-label fw-semibold">Page title</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $page->name) }}" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">URL path</label>
        <div class="input-group">
          <span class="input-group-text text-muted small">{{ $baseUrl }}/</span>
          <input type="text" name="slug" class="form-control" value="{{ old('slug', $page->slug) }}"
                 @if($lockSlug) readonly @endif required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
        </div>
        @if($lockSlug)
          <small class="text-muted">This slug is fixed so public links and footer menus stay valid.</small>
        @else
          <small class="text-muted">Lowercase letters, numbers, and hyphens only (e.g. <code>refund-policy</code>).</small>
        @endif
      </div>

      <div class="mb-0">
        <label class="form-label fw-semibold">Content</label>
        <div id="{{ $editorId }}" class="border rounded" style="min-height:320px">
          {!! old('content', $page->content) !!}
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card bg-label-primary border-0 mb-3">
        <div class="card-body">
          <h6 class="mb-2"><i class="ri-link me-1"></i> Live URL</h6>
          <a href="{{ $publicUrl }}" target="_blank" class="small text-break">{{ $publicUrl }}</a>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select" required>
          <option value="active" @selected(old('status', $page->status) === 'active')>Active (visible on website)</option>
          <option value="inactive" @selected(old('status', $page->status) === 'inactive')>Inactive (hidden)</option>
        </select>
      </div>

      <div class="mb-3 small text-muted">
        Last updated: {{ $page->updated_at?->format('d M Y, H:i') ?? '—' }}
      </div>

      <button type="submit" class="btn btn-primary w-100">
        <i class="ri-save-line me-1"></i> Save {{ $page->name }}
      </button>
    </div>
  </div>
</form>