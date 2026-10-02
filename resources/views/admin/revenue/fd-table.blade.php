<div class="table-responsive">
  <table class="table table-hover align-middle table-striped">
    <thead class="table-light">
      <tr>
        <th scope="col" class="text-center" style="width: 60px;">S.No</th>
        <th scope="col">Customer Name</th>
        <th scope="col">FD No / Scheme</th>
        <th scope="col" class="text-end">Deposit Amount</th>
        <th scope="col" class="text-end">Processing Fee</th>
        <th scope="col" class="text-end">Doc Charges</th>
        <th scope="col" class="text-end">Other Charges</th>
        <th scope="col" class="text-end">Premature Penalty</th>
        <th scope="col" class="text-end">Total Revenue</th>
        <th scope="col" class="text-center" style="width: 80px;">Action</th>
      </tr>
    </thead>
    <tbody>
      @php
        $pageProcessing = $pageDoc = $pageOther = $pagePenalty = $pageTotal = 0;
      @endphp
      @forelse($items as $index => $fd)
        @php
          $pageProcessing += $fd->processing_fee;
          $pageDoc += $fd->document_charges;
          $pageOther += $fd->other_charges;
          $pagePenalty += $fd->penalty_collected;
          $pageTotal += $fd->total_revenue;
        @endphp
        <tr>
          <td class="text-center">{{ $items->firstItem() + $index }}</td>
          <td>
            <div class="fw-semibold text-dark">{{ $fd->client->user->name ?? $fd->client->client_name ?? 'N/A' }}</div>
            <small class="text-muted">ID: {{ $fd->client->client_code ?? 'N/A' }}</small>
          </td>
          <td>
            <div class="fw-semibold text-primary">{{ $fd->fd_number }}</div>
            <small class="text-muted">{{ $fd->scheme->name ?? 'N/A' }} · {{ ucfirst(str_replace('_', ' ', $fd->status ?? '')) }}</small>
          </td>
          <td class="text-end fw-medium">₹{{ number_format((float) $fd->deposit_amount, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($fd->processing_fee, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($fd->document_charges, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($fd->other_charges, 2) }}</td>
          <td class="text-end text-danger fw-medium">₹{{ number_format($fd->penalty_collected, 2) }}</td>
          <td class="text-end text-primary fw-bold">₹{{ number_format($fd->total_revenue, 2) }}</td>
          <td class="text-center">
            <a href="{{ route('fd.deposits.show', $fd->id) }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View FD">
              <i class="icon-base ri ri-eye-line icon-22px"></i>
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="text-center text-muted py-5">
            <i class="ri-file-search-line ri-36px d-block mb-2 text-secondary"></i>
            No fixed deposit revenue records found matching the selected filters.
          </td>
        </tr>
      @endforelse
    </tbody>
    @if($items->isNotEmpty())
      <tfoot class="table-light border-top-2">
        <tr class="fw-bold text-dark">
          <td colspan="4" class="text-end">Total (This Page):</td>
          <td class="text-end text-secondary">₹{{ number_format($pageProcessing, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($pageDoc, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($pageOther, 2) }}</td>
          <td class="text-end text-danger">₹{{ number_format($pagePenalty, 2) }}</td>
          <td class="text-end text-primary">₹{{ number_format($pageTotal, 2) }}</td>
          <td></td>
        </tr>
      </tfoot>
    @endif
  </table>
</div>

<div class="mt-4">
  {{ $items->links('pagination::bootstrap-5') }}
</div>
