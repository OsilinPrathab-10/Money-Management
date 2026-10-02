@extends('layouts/layoutMaster')

@section('title', 'Client Recycle Bin')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
  <script>
    window.userRole = "{{ auth()->user()->roles->first()->name ?? 'Guest' }}";
    window.recycleBinDataUrl = "{{ route('client-management-recycle-bin') }}";
  </script>
  @vite(['resources/assets/custom-js/client-recycle-bin.js'])
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="mb-1">Client Recycle Bin</h4>
    <p class="text-muted mb-0">
      Deleted clients are kept here with their loans, chit memberships and fixed deposits.
      Restoring a client brings all of those accounts back.
    </p>
  </div>
  <a href="{{ route('client-management') }}" class="btn btn-outline-secondary">
    <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to Clients
  </a>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center border-bottom py-3">
    <h5 class="card-title mb-0">
      Deleted Clients
      <span class="badge bg-label-danger ms-1" id="trashedCount">{{ $trashedCount }}</span>
    </h5>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-recycle-bin table table-hover" id="recycleBinTable">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Name</th>
          <th>Mobile</th>
          <th>Email</th>
          <th>Archived Accounts</th>
          <th>Deleted On</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>
@endsection
