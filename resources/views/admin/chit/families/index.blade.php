@extends('layouts/layoutMaster')
@section('title', 'Chit Family Members')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/chit-families.js'])
@endsection

@section('content')
{{-- INDEX — aligned with Loan Accounts UI --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Family Members</h4>
        <p class="text-muted mb-0">Group clients from different chit groups into families and pay all current dues in one shot.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('chit.members.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
            <i class="icon-base ri ri-arrow-left-line me-1"></i>Back to Chit Members
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFamilyModal">
            <i class="icon-base ri ri-parent-line me-1"></i>Create Family
        </button>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">
    <i class="icon-base ri ri-checkbox-circle-line me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">
    <i class="icon-base ri ri-error-warning-line me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <h5 class="mb-0">
            All Families
            <span class="badge bg-label-primary ms-2">{{ $families->total() }}</span>
        </h5>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap fw-medium">Search:</label>
                <input type="text" name="search" class="form-control form-control-sm" style="min-width: 220px;"
                       placeholder="Family or member name..." value="{{ request('search') }}">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-filter-3-line me-1"></i>Search
                </button>
                <a href="{{ route('chit.families.index') }}" class="btn btn-sm btn-label-secondary">
                    <i class="icon-base ri ri-refresh-line me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
    <div class="card-datatable table-responsive">
        <table class="table text-nowrap mb-0">
            <thead>
                <tr>
                    <th>Family Name</th>
                    <th>Members</th>
                    <th>Active Chits</th>
                    <th>Primary Contact</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($families as $family)
                <tr>
                    <td>
                        <a href="{{ route('chit.families.show', $family) }}" class="fw-semibold text-primary text-decoration-none">
                            {{ $family->name }}
                        </a>
                        @if($family->notes)
                        <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($family->notes, 60) }}</small>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-label-primary">{{ $family->family_members_count }} member(s)</span>
                        @if($family->members->isNotEmpty())
                        <br>
                        <small class="text-muted">
                            {{ $family->members->take(2)->pluck('client_name')->join(', ') }}
                            @if($family->members->count() > 2) +{{ $family->members->count() - 2 }} more @endif
                        </small>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-label-success">{{ $family->active_chits_count }} active</span>
                    </td>
                    <td>
                        @if($family->primaryClient)
                            @php
                                $pPhone = $family->primaryClient->client_phone ?? '';
                                $cleanPPhone = preg_replace('/\D/', '', $pPhone);
                                if (strlen($cleanPPhone) === 10) { $cleanPPhone = '91' . $cleanPPhone; }
                            @endphp
                            <div class="fw-semibold text-heading">{{ $family->primaryClient->client_name }}</div>
                            <div class="d-flex align-items-center gap-1">
                                <small class="text-muted">{{ $pPhone }}</small>
                                @if($cleanPPhone)
                                    <a href="https://wa.me/{{ $cleanPPhone }}" target="_blank" class="btn btn-xs btn-icon btn-text-secondary rounded-pill text-success ms-1" title="WhatsApp Primary Contact">
                                        <i class="icon-base ri ri-whatsapp-line icon-16px"></i>
                                    </a>
                                    <a href="sms:+{{ $cleanPPhone }}" class="btn btn-xs btn-icon btn-text-secondary rounded-pill text-info" title="SMS Primary Contact">
                                        <i class="icon-base ri ri-message-3-line icon-16px"></i>
                                    </a>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $family->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <a href="{{ route('chit.families.show', $family) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View Family">
                                <i class="icon-base ri ri-eye-line icon-22px"></i>
                            </a>
                            <form method="POST" action="{{ route('chit.families.destroy', $family) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this family? This will unassign all members.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Delete Family">
                                    <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="icon-base ri ri-parent-line icon-32px d-block mb-2"></i>
                        No families created yet. Create a family to group members across different chit groups.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($families->hasPages())
    <div class="card-footer">{{ $families->links() }}</div>
    @endif
</div>

{{-- Create Family Modal --}}
<div class="modal fade" id="createFamilyModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('chit.families.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="icon-base ri ri-parent-line me-2 text-primary"></i>Create Family
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Family Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Kumar Family" required value="{{ old('name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Primary Contact</label>
                            <select name="primary_client_id" id="primaryClientSelect" class="form-select select2">
                                <option value="">Select later...</option>
                                @foreach($clients as $c)
                                <option value="{{ $c->id }}" {{ old('primary_client_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->client_name }} — {{ $c->client_phone }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Family Members <span class="text-danger">*</span></label>
                            <select name="client_ids[]" id="familyMembersSelect" class="form-select select2" multiple required
                                data-client-labels="{{ $unassignedClients->mapWithKeys(fn($c) => [$c->id => $c->client_name])->toJson() }}">
                                @foreach($unassignedClients as $c)
                                <option value="{{ $c->id }}">{{ $c->client_name }} — {{ $c->client_phone }} ({{ $c->group_members_count }} {{ \Illuminate\Support\Str::plural('group', $c->group_members_count) }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Select clients in this family. Each client can only belong to one family and may be in different chit groups.</small>
                        </div>
                        <div class="col-12" id="memberRelationshipsWrap" style="display:none;">
                            <label class="form-label fw-semibold">Member Relationships <span class="text-muted fw-normal">(optional)</span></label>
                            <div id="memberRelationships" class="row g-2"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-base ri ri-save-line me-1"></i>Create Family
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
