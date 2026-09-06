<div class="table-responsive">
  <table class="table table-hover align-middle table-striped">
    <thead class="table-light">
      <tr>
        <th scope="col" class="text-center" style="width: 60px;">S.No</th>
        <th scope="col">Customer Name</th>
        <th scope="col">Account No / Code</th>
        <th scope="col" class="text-center">Loan Type</th>
        <th scope="col" class="text-end">EMI / Cycle Amount</th>
        <th scope="col" class="text-end">Processing Fee</th>
        <th scope="col" class="text-end">Doc Charges</th>
        <th scope="col" class="text-end">Other Charges</th>
        <th scope="col" class="text-end">Interest Collected</th>
        <th scope="col" class="text-end">Foreclose Revenue</th>
        <th scope="col" class="text-end">Penalty Amount</th>
        <th scope="col" class="text-end">Total Revenue</th>
        <th scope="col" class="text-center" style="width: 80px;">Action</th>
      </tr>
    </thead>
    <tbody>
      @php
        $pageProcessingFee = 0;
        $pageDocCharges = 0;
        $pageOtherCharges = 0;
        $pageInterestCollected = 0;
        $pageForeclosureRevenue = 0;
        $pagePenaltyAmount = 0;
        $pageTotalRevenue = 0;
      @endphp
      @forelse($loans as $index => $loan)
        @php
          $pageProcessingFee += $loan->processing_fee;
          $pageDocCharges += $loan->document_charges;
          $pageOtherCharges += $loan->other_charges;
          $pageInterestCollected += $loan->interest_collected;
          $pageForeclosureRevenue += $loan->foreclosure_revenue;
          $pagePenaltyAmount += $loan->penalty_collected;
          $pageTotalRevenue += $loan->total_revenue;

          $typeColor = $loan->loan_mode === 'interest_only' ? 'bg-label-info' : 'bg-label-primary';
          $typeLabel = $loan->loan_mode === 'interest_only' ? 'Open Loan' : 'Standard EMI';
        @endphp
        <tr>
          <td class="text-center">{{ $loans->firstItem() + $index }}</td>
          <td>
            <div class="fw-semibold text-dark">{{ $loan->client->user->name ?? $loan->client->client_name ?? 'N/A' }}</div>
            <small class="text-muted">ID: {{ $loan->client->client_code ?? 'N/A' }}</small>
          </td>
          <td>
            <div class="fw-semibold text-primary">{{ $loan->account_number ?? $loan->id }}</div>
            <small class="text-muted">{{ $loan->loan_code }}</small>
          </td>
          <td class="text-center">
            <span class="badge {{ $typeColor }} rounded-pill text-uppercase">{{ $typeLabel }}</span>
          </td>
          <td class="text-end fw-medium text-dark">₹{{ number_format($loan->emi_amount, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($loan->processing_fee, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($loan->document_charges, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($loan->other_charges, 2) }}</td>
          <td class="text-end text-success fw-medium">₹{{ number_format($loan->interest_collected, 2) }}</td>
          <td class="text-end text-warning fw-medium">₹{{ number_format($loan->foreclosure_revenue, 2) }}</td>
          <td class="text-end text-danger fw-medium">₹{{ number_format($loan->penalty_collected, 2) }}</td>
          <td class="text-end text-primary fw-bold">₹{{ number_format($loan->total_revenue, 2) }}</td>
          <td class="text-center">
            <a href="{{ route('loan-account-view', $loan->id) }}" class="btn btn-sm btn-icon btn-outline-primary" title="View details">
              <i class="ri-eye-line"></i>
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="13" class="text-center text-muted py-5">
            <i class="ri-file-search-line ri-36px d-block mb-2 text-secondary"></i>
            No revenue records found matching the selected filters.
          </td>
        </tr>
      @endforelse
    </tbody>
    @if($loans->isNotEmpty())
      <tfoot class="table-light border-top-2">
        <tr class="fw-bold text-dark">
          <td colspan="4" class="text-end">Total (This Page):</td>
          <td class="text-end"></td>
          <td class="text-end text-secondary">₹{{ number_format($pageProcessingFee, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($pageDocCharges, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($pageOtherCharges, 2) }}</td>
          <td class="text-end text-success">₹{{ number_format($pageInterestCollected, 2) }}</td>
          <td class="text-end text-warning">₹{{ number_format($pageForeclosureRevenue, 2) }}</td>
          <td class="text-end text-danger">₹{{ number_format($pagePenaltyAmount, 2) }}</td>
          <td class="text-end text-primary">₹{{ number_format($pageTotalRevenue, 2) }}</td>
          <td></td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

<div class="mt-4">
  {{ $loans->links('pagination::bootstrap-5') }}
</div>
