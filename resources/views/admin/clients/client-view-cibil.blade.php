@extends('layouts/layoutMaster')

@section('title', __('Client CIBIL'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/account/'.$client->id) }}"><i class="ri-user-3-line me-1"></i>{{ __('Account') }}</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/kyc/'.$client->id) }}"><i class="ri-shield-check-line me-1"></i>KYC</a></li>
      <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="ri-bar-chart-box-line me-1"></i>{{ __('CIBIL') }}</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/loans/'.$client->id) }}"><i class="ri-file-list-3-line me-1"></i>{{ __('Loans') }}</a></li>
    </ul>
    <a href="{{ route('cibil-report.index', ['client_id' => $client->id]) }}" class="btn btn-primary btn-sm">
      <i class="ri-add-line me-1"></i>{{ __('New CIBIL report') }}
    </a>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-2">{{ $client->client_name }}</h5>
      <p class="text-muted mb-1">{{ __('Customer ID:') }} {{ $client->customer_id ?? '—' }}</p>
      <p class="text-muted mb-1">{{ __('Phone:') }} {{ $client->client_phone }}</p>
      <p class="text-muted mb-1">{{ __('PAN:') }} {{ $client->kycDetail?->pan_number ?? '—' }}</p>
      <p class="mb-0">{{ __('Latest score:') }}
        <span class="badge bg-label-primary fs-6">{{ $latestScore ?? $client->cibil_score ?? '—' }}</span>
      </p>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>{{ __('Score') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Staff') }}</th>
            <th>{{ __('PDF') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($histories as $row)
            <tr>
              <td>{{ $row->score ?? '—' }}</td>
              <td>{{ $row->created_at?->format('d M Y H:i') }}</td>
              <td>{{ $row->creator?->name ?? '—' }}</td>
              <td>
                @if($row->pdf_status === 'saved')
                  <a href="{{ route('cibil-report.pdf', $row->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Download') }}</a>
                @else — @endif
              </td>
              <td><a href="{{ route('cibil-report.show', $row->id) }}" class="btn btn-sm btn-text-secondary">{{ __('View') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No CIBIL reports for this client yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
