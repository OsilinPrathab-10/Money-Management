@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Balances'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h4 class="mb-0">{{ $pageTitle }}</h4>
    <div class="d-flex flex-wrap align-items-center gap-2">
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => ($balanceType ?? '') === 'customer' ? 'account.reports.customer-balance.export' : 'account.reports.vendor-balance.export',
        'query' => ['as_of_date' => $filters['as_of_date'] ?? date('Y-m-d')],
      ])
      <a href="{{ route('account.reports.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Reports hub') }}</a>
    </div>
  </div>

  <p class="text-muted">{{ __('As of') }}: <strong>{{ $filters['as_of_date'] ?? date('Y-m-d') }}</strong></p>

  @if (!empty($data['error']))
    <div class="alert alert-warning">{{ $data['error'] }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>{{ __('Name') }}</th>
            <th class="text-end">{{ __('Balance') }}</th>
          </tr>
        </thead>
        <tbody>
          @if (($balanceType ?? '') === 'customer')
            @forelse ($data['customers'] ?? [] as $row)
              <tr>
                <td>{{ $row['customer_name'] ?? '—' }}</td>
                <td class="text-end">₹{{ number_format((float) ($row['balance'] ?? 0), 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">{{ __('No balances in this scope.') }}</td></tr>
            @endforelse
          @else
            @forelse ($data['vendors'] ?? [] as $row)
              <tr>
                <td>{{ $row['vendor_name'] ?? '—' }}</td>
                <td class="text-end">₹{{ number_format((float) ($row['balance'] ?? 0), 2) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">{{ __('No balances in this scope.') }}</td></tr>
            @endforelse
          @endif
        </tbody>
      </table>
    </div>
    @if (isset($data['total_balance']))
      <p class="mt-2"><strong>{{ __('Total') }}:</strong> ₹{{ number_format((float) $data['total_balance'], 2) }}</p>
    @endif
  @endif
</div>
@endsection
