@extends('layouts/layoutMaster')

@section('title', 'Menu Access')

@section('content')
@php
    $roleName = $role->name ?? 'Staff';
    $isAgentRole = $roleName === 'Agent';
    $isStaffRole = $roleName === 'Staff';
@endphp
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h4 class="mb-1">Menu Access</h4>
                <p class="text-muted mb-0">
                    Dynamically control sidebar menus for each login role. Agent and Staff use different default sets — Admin always sees everything.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('role-users') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-shield-user-line me-1"></i> Roles
                </a>
                <a href="{{ route('admin.staff.index', ['tab' => 'agent']) }}" class="btn btn-outline-success btn-sm">
                    <i class="ri-user-star-line me-1"></i> Agents
                </a>
                <a href="{{ route('admin.staff.index', ['tab' => 'staff']) }}" class="btn btn-outline-primary btn-sm">
                    <i class="ri-team-line me-1"></i> Staff
                </a>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4 mb-2">
    <div class="col-12">
        <div class="alert alert-{{ $isAgentRole ? 'success' : ($isStaffRole ? 'primary' : 'secondary') }} py-2 mb-0">
            @if($isAgentRole)
                <strong>Agent workflow:</strong> Assign field menus here (Agent Dashboard, Clients, Collections, EMI tools…).
                Agents log in to <code>/app/agents/dashboard</code> and only see these menus — not Admin menus.
                You can still override menus for one agent under Agent Directory → Edit.
            @elseif($isStaffRole)
                <strong>Staff workflow:</strong> Assign office menus here. Individual staff can get a personal override under Staff Directory → Edit.
                Give the <strong>Admin</strong> role only when full system access is required.
            @elseif($roleName === 'Admin')
                <strong>Admin:</strong> Login always shows all menus. Saving here is optional (used as documentation / presets only).
            @else
                Choose menus for <strong>{{ $roleName }}</strong>. Users inherit these unless a personal override is set.
            @endif
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Roles</h5>
            </div>
            <div class="list-group list-group-flush">
                @foreach($roles as $r)
                    <a href="{{ route('roles.menus', ['role' => $r->name]) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $role && $role->id === $r->id ? 'active' : '' }}">
                        <span>{{ $r->name }}</span>
                        @if($r->name === 'Staff')
                            <span class="badge bg-label-primary">Office</span>
                        @elseif($r->name === 'Agent')
                            <span class="badge bg-label-success">Field</span>
                        @elseif($r->name === 'Admin')
                            <span class="badge bg-label-warning">Full</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        @if($role)
            <form method="POST" action="{{ route('roles.menus.update') }}" id="roleMenusForm">
                @csrf
                <input type="hidden" name="role" value="{{ $role->name }}">
                <input type="hidden" name="apply_recommended" id="applyRecommendedFlag" value="0">

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h5 class="mb-1">Menus for <strong>{{ $role->name }}</strong></h5>
                            <small class="text-muted">Tick menus → Save. Or load the recommended set for this role, then adjust.</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success" id="loadRecommendedMenus"
                                    data-keys='@json($recommendedKeys ?? [])'>
                                Load recommended {{ $role->name }} menus
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllMenus">Select all</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllMenus">Clear</button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ri-save-line me-1"></i> Save menus
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('admin.roles-permissions.partials.menu-checkbox-tree', [
                            'catalog' => $catalog,
                            'assignedKeys' => $assignedKeys,
                            'recommendedKeys' => $recommendedKeys ?? [],
                            'highlightRole' => $role->name,
                            'inputName' => 'menus[]',
                        ])
                    </div>
                </div>
            </form>
        @else
            <div class="alert alert-warning mb-0">No roles found.</div>
        @endif
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('roleMenusForm');
  const boxes = () => form.querySelectorAll('.menu-access-checkbox');
  document.getElementById('selectAllMenus')?.addEventListener('click', () => {
    document.getElementById('applyRecommendedFlag').value = '0';
    boxes().forEach(cb => { cb.checked = true; });
  });
  document.getElementById('clearAllMenus')?.addEventListener('click', () => {
    document.getElementById('applyRecommendedFlag').value = '0';
    boxes().forEach(cb => { cb.checked = false; });
  });
  document.getElementById('loadRecommendedMenus')?.addEventListener('click', () => {
    const btn = document.getElementById('loadRecommendedMenus');
    let keys = [];
    try { keys = JSON.parse(btn.getAttribute('data-keys') || '[]'); } catch (e) { keys = []; }
    const lookup = {};
    keys.forEach(k => { lookup[k] = true; });
    boxes().forEach(cb => { cb.checked = !!lookup[cb.value]; });
    document.getElementById('applyRecommendedFlag').value = '0';
  });

  form?.querySelectorAll('[data-menu-parent]').forEach(parentCb => {
    parentCb.addEventListener('change', function () {
      const key = this.getAttribute('data-menu-parent');
      form.querySelectorAll(`[data-menu-child-of="${key}"]`).forEach(child => {
        child.checked = parentCb.checked;
      });
    });
  });
});
</script>
@endsection
