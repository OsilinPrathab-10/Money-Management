<div class="row g-4">
  <div class="col-lg-7">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>Title</th>
            <th>URL</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($legalPages as $p)
            @php $url = \App\Http\Controllers\WebsiteSetupController::publicUrlForPolicy($p); @endphp
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td class="fw-semibold">{{ $p->name }}</td>
              <td class="small">
                <a href="{{ $url }}" target="_blank" class="text-primary">{{ str_replace($baseUrl, '', $url) ?: '/'.$p->slug }}</a>
              </td>
              <td>
                <span class="badge bg-label-{{ $p->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($p->status) }}</span>
              </td>
              <td class="text-end text-nowrap">
                @if($p->slug === 'privacy-policy')
                  <a href="{{ route('website-homepage', ['tab' => 'privacy']) }}" class="btn btn-sm btn-icon btn-text-primary"><i class="ri-pencil-line"></i></a>
                @elseif($p->slug === 'terms-and-conditions')
                  <a href="{{ route('website-homepage', ['tab' => 'terms']) }}" class="btn btn-sm btn-icon btn-text-primary"><i class="ri-pencil-line"></i></a>
                @else
                  <a href="{{ route('page-configuration-edit', $p->id) }}" class="btn btn-sm btn-icon btn-text-secondary" title="Full editor"><i class="ri-external-link-line"></i></a>
                @endif
                @unless(in_array($p->slug, ['privacy-policy', 'terms-and-conditions']))
                  <form method="POST" action="{{ route('website-legal-pages.destroy', $p->id) }}" class="d-inline" onsubmit="return confirm('Delete this page?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger"><i class="ri-delete-bin-line"></i></button>
                  </form>
                @endunless
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border shadow-none">
      <div class="card-header"><h6 class="mb-0">Add custom legal page</h6></div>
      <div class="card-body">
        <form method="POST" action="{{ route('website-legal-pages.store') }}" id="legalCreateForm">
          @csrf
          <input type="hidden" name="content" id="legalCreateContent">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="name" class="form-control" placeholder="Refund Policy" required>
          </div>
          <div class="mb-3">
            <label class="form-label">URL path</label>
            <div class="input-group">
              <span class="input-group-text small text-muted">{{ $baseUrl }}/</span>
              <input type="text" name="slug" class="form-control" placeholder="refund-policy" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Content</label>
            <h6 class="text-muted small">Use Page Configuration for rich editor, or paste HTML below:</h6>
            <textarea name="content_html" class="form-control" rows="8" placeholder="<h3>Refund Policy</h3><p>...</p>"></textarea>
          </div>
          <div class="mb-3">
            <select name="status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100">Create page</button>
        </form>
        <script>
        document.getElementById('legalCreateForm')?.addEventListener('submit', function () {
          const ta = this.querySelector('[name=content_html]');
          document.getElementById('legalCreateContent').value = ta?.value || '<p></p>';
        });
        </script>
      </div>
    </div>
  </div>
</div>