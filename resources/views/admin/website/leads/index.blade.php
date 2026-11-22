@extends('layouts/layoutMaster')
@section('title', 'Website loan applications')
@section('content')
<div class="container-xxl py-4">
  <h4 class="mb-4">Website loan applications</h4>
  <form class="row g-2 mb-3" method="GET">
    <div class="col-md-4"><input name="search" class="form-control" placeholder="Ref / name / phone" value="{{ request('search') }}"></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">All status</option>@foreach(['new','under_review','approved','rejected'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
  </form>
  <div class="table-responsive card">
    <table class="table table-hover mb-0">
      <thead><tr><th>Ref</th><th>Applicant</th><th>Product</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        @foreach($leads as $lead)
          <tr>
            <td>{{ $lead->application_ref }}</td>
            <td>{{ $lead->applicant_name }}<br><small>{{ $lead->phone }}</small></td>
            <td>{{ $lead->product?->loan_name }}</td>
            <td><span class="badge bg-label-primary">{{ $lead->status }}</span></td>
            <td>{{ $lead->created_at->format('d M Y') }}</td>
            <td><a href="{{ route('admin.website.leads.show', $lead->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="p-3">{{ $leads->links() }}</div>
  </div>
</div>
@endsection
