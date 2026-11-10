@extends('layouts/layoutMaster')

@section('title', __('CIBIL Report') . ' #' . $history->id)

@php
  $svc = app(\App\Services\ApiHubCibilService::class);
  $scoreColor = $svc->scoreColor($history->score);
  $summary = $analysis['summary'] ?? [];
  $accounts = $analysis['account_summary'] ?? [];
  $inquiries = $analysis['inquiries'] ?? [];
  $observations = $analysis['observations'] ?? [];
  $rawReport = $history->report_json['data']['results']['cibil']['data']['credit_report'][0] ?? [];
@endphp

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-4">
    <div>
      <h4 class="mb-1">{{ $history->applicant_name }}</h4>
      <p class="text-muted mb-0">{{ $history->pan_number }} · {{ $history->phone }}</p>
      @if($history->status === 'failed')
        <div class="alert alert-danger mt-3 mb-0">{{ $history->error_message }}</div>
      @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('cibil-report.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary btn-sm">{{ __('History') }}</a>
      @if($history->pdf_status === 'saved')
        <a href="{{ route('cibil-report.pdf', $history->id) }}" class="btn btn-primary btn-sm"><i class="ri-download-line me-1"></i>{{ __('Download PDF') }}</a>
        <a href="{{ route('cibil-report.pdf', [$history->id, 'inline' => 1]) }}" class="btn btn-outline-primary btn-sm" target="_blank">{{ __('View PDF') }}</a>
      @endif
      @if($history->credit_report_link && !$history->is_mock)
        <form method="POST" action="{{ route('cibil-report.retry-pdf', $history->id) }}">@csrf
          <button class="btn btn-outline-info btn-sm" type="submit">{{ __('Retry PDF download') }}</button>
        </form>
      @endif
      <form method="POST" action="{{ route('cibil-report.destroy', $history->id) }}" onsubmit="return confirm('{{ __('Delete?') }}')">@csrf @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" type="submit">{{ __('Delete') }}</button>
      </form>
    </div>
  </div>

  @if(request('download') && $history->pdf_status === 'saved')
    <script>window.addEventListener('load', () => { window.location.href = @json(route('cibil-report.pdf', $history->id)); });</script>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <p class="text-muted mb-1">{{ __('CIBIL score') }}</p>
          @if($history->score)
            <h1 class="display-4 text-{{ $scoreColor }} mb-0">{{ $history->score }}<small class="fs-6 text-muted">/900</small></h1>
            <span class="badge bg-{{ $scoreColor }} mt-2">{{ $history->rating }}</span>
          @else
            <p class="text-muted mb-0">{{ __('No score') }}</p>
          @endif
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <p class="text-muted mb-1">{{ __('TransUnion PDF link') }}</p>
          @if($history->is_mock)
            <p class="mb-0">{{ __('Test mode — bureau PDF not downloaded.') }}</p>
          @elseif($history->link_expires_at)
            <p class="mb-1 @if($history->link_expires_at->isPast()) text-danger @endif">
              {{ $history->link_expires_at->isPast() ? __('Expired') : __('Expires') }}
              {{ $history->link_expires_at->diffForHumans() }}
            </p>
            <p class="small mb-0">{{ __('PDF on disk:') }} {{ $history->pdf_status === 'saved' ? __('Yes') : ($history->pdf_error ?? __('Pending')) }}</p>
          @else
            <p class="text-muted mb-0">—</p>
          @endif
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <p class="text-muted mb-2">{{ __('Client') }}</p>
          @if($history->client)
            <a href="{{ route('client-view-cibil', $history->client_id) }}">{{ $history->client->client_name }}</a>
          @elseif($history->status === 'success')
            @if($suggestedClients->isNotEmpty())
              <p class="small text-muted">{{ __('Suggested matches:') }}</p>
              <ul class="list-unstyled mb-2">
                @foreach($suggestedClients as $sc)
                  <li><a href="#" onclick="document.getElementById('linkClient').value='{{ $sc->id }}';return false;">{{ $sc->client_name }}</a></li>
                @endforeach
              </ul>
            @endif
            <form method="POST" action="{{ route('cibil-report.link', $history->id) }}" class="row g-2">@csrf
              <div class="col-8">
                <select name="client_id" id="linkClient" class="form-select form-select-sm" required>
                  <option value="">{{ __('Select client') }}</option>
                  @foreach($allClients as $c)
                    <option value="{{ $c->id }}">{{ $c->client_name }} @if($c->customer_id)({{ $c->customer_id }})@endif</option>
                  @endforeach
                </select>
              </div>
              <div class="col-4"><button class="btn btn-sm btn-primary w-100" type="submit">{{ __('Link') }}</button></div>
            </form>
            <a href="{{ route('cibil-report.create-client', $history->id) }}" class="btn btn-sm btn-outline-primary mt-2">{{ __('Create client from report') }}</a>
          @else
            <p class="text-muted mb-0">{{ __('Unavailable for failed reports.') }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  @if($history->status === 'success')
    <div class="row g-3 mb-4">
      @foreach([
        ['Total accounts', $summary['total_accounts'] ?? '—'],
        ['Active / closed', ($summary['active_accounts'] ?? '—') . ' / ' . ($summary['closed_accounts'] ?? '—')],
        ['Current balance', isset($summary['total_current_balance']) ? '₹'.number_format($summary['total_current_balance']) : '—'],
        ['Overdue', isset($summary['total_past_due']) ? '₹'.number_format($summary['total_past_due']) : '—'],
        ['On-time %', isset($summary['on_time_payment_rate']) ? $summary['on_time_payment_rate'].'%' : '—'],
        ['Utilisation', isset($summary['credit_utilization']) ? $summary['credit_utilization'].'%' : '—'],
        ['Enquiries (90d)', $analysis['inquiry_summary']['last_90_days'] ?? count($inquiries)],
      ] as $item)
        <div class="col-6 col-md-3">
          <div class="card border-0 bg-light"><div class="card-body py-2">
            <small class="text-muted">{{ $item[0] }}</small>
            <div class="fw-semibold">{{ $item[1] }}</div>
          </div></div>
        </div>
      @endforeach
    </div>

    @if(!empty($observations))
      <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Observations') }}</h6></div>
        <ul class="list-group list-group-flush">
          @foreach($observations as $ob)
            <li class="list-group-item"><span class="badge bg-label-secondary me-2">{{ $ob['type'] ?? 'note' }}</span>{{ $ob['text'] ?? '' }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card mb-4">
      <div class="card-header"><h6 class="mb-0">{{ __('Credit accounts') }}</h6></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr>
            <th>{{ __('Lender') }}</th><th>{{ __('Account') }}</th><th>{{ __('Status') }}</th>
            <th>{{ __('Balance') }}</th><th>{{ __('Overdue') }}</th><th>{{ __('EMI') }}</th>
          </tr></thead>
          <tbody>
            @forelse($accounts as $acc)
              <tr @if(($acc['amount_past_due'] ?? 0) > 0) class="table-warning" @endif>
                <td>{{ $acc['lender_name'] ?? '—' }}<br><small class="text-muted">{{ $acc['account_type'] ?? '' }}</small></td>
                <td>****{{ substr((string)($acc['account_number'] ?? ''), -4) }}</td>
                <td>{{ !empty($acc['is_open']) ? __('Active') : __('Closed') }}</td>
                <td>{{ isset($acc['current_balance']) ? '₹'.number_format($acc['current_balance']) : '—' }}</td>
                <td>{{ isset($acc['amount_past_due']) ? '₹'.number_format($acc['amount_past_due']) : '—' }}</td>
                <td>{{ isset($acc['emi_amount']) ? '₹'.number_format($acc['emi_amount']) : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No account summary in bureau data.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><h6 class="mb-0">{{ __('Enquiries') }}</h6></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Lender') }}</th><th>{{ __('Amount') }}</th></tr></thead>
          <tbody>
            @forelse($inquiries as $enq)
              <tr>
                <td>{{ $enq['date'] ?? '—' }}</td>
                <td>{{ $enq['lender'] ?? '—' }}</td>
                <td>{{ isset($enq['amount']) ? '₹'.number_format((float) preg_replace('/\D/','',$enq['amount'])) : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-muted text-center py-3">—</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(!empty($rawReport))
      <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Personal information (bureau)') }}</h6></div>
        <div class="card-body small">
          @php
            $phones = collect($rawReport['telephones'] ?? [])->pluck('telephoneNumber')->unique()->take(10);
            $emails = collect($rawReport['emails'] ?? [])->pluck('emailID')->unique();
          @endphp
          <p class="mb-1"><strong>{{ __('Phones:') }}</strong> {{ $phones->implode(', ') ?: '—' }}</p>
          <p class="mb-1"><strong>{{ __('Emails:') }}</strong> {{ $emails->implode(', ') ?: '—' }}</p>
          @foreach($rawReport['addresses'] ?? [] as $addr)
            <p class="mb-1">{{ ($addr['line1'] ?? '') }} {{ ($addr['pinCode'] ?? '') }} <span class="text-muted">({{ $addr['dateReported'] ?? '' }})</span></p>
          @endforeach
        </div>
      </div>
    @endif
  @endif
</div>
@endsection
