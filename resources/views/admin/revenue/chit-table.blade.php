<div class="table-responsive">
  <table class="table table-hover align-middle table-striped">
    <thead class="table-light">
      <tr>
        <th scope="col" class="text-center" style="width: 60px;">S.No</th>
        <th scope="col">Group / Scheme</th>
        <th scope="col" class="text-end">Chit Value</th>
        <th scope="col" class="text-end">Foreman Profit</th>
        <th scope="col" class="text-end">Processing Fee</th>
        <th scope="col" class="text-end">Doc Charges</th>
        <th scope="col" class="text-end">Other Charges</th>
        <th scope="col" class="text-end">Penalty Collected</th>
        <th scope="col" class="text-end">Total Revenue</th>
        <th scope="col" class="text-center" style="width: 80px;">Action</th>
      </tr>
    </thead>
    <tbody>
      @php
        $pageForeman = $pageProcessing = $pageDoc = $pageOther = $pagePenalty = $pageTotal = 0;
      @endphp
      @forelse($items as $index => $group)
        @php
          $pageForeman += $group->foreman_profit;
          $pageProcessing += $group->processing_fee;
          $pageDoc += $group->document_charges;
          $pageOther += $group->other_charges;
          $pagePenalty += $group->penalty_collected;
          $pageTotal += $group->total_revenue;
        @endphp
        <tr>
          <td class="text-center">{{ $items->firstItem() + $index }}</td>
          <td>
            <div class="fw-semibold text-dark">{{ $group->group_code }}</div>
            <small class="text-muted">{{ $group->scheme->name ?? 'N/A' }} · {{ ucfirst($group->status ?? '') }}</small>
          </td>
          <td class="text-end fw-medium">₹{{ number_format((float) $group->chit_value, 2) }}</td>
          <td class="text-end text-success fw-medium">₹{{ number_format($group->foreman_profit, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($group->processing_fee, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($group->document_charges, 2) }}</td>
          <td class="text-end text-secondary">₹{{ number_format($group->other_charges, 2) }}</td>
          <td class="text-end text-danger fw-medium">₹{{ number_format($group->penalty_collected, 2) }}</td>
          <td class="text-end text-primary fw-bold">₹{{ number_format($group->total_revenue, 2) }}</td>
          <td class="text-center">
            <a href="{{ route('chit.groups.show', $group->id) }}" class="btn btn-sm btn-icon btn-outline-primary" title="View group">
              <i class="ri-eye-line"></i>
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="10" class="text-center text-muted py-5">
            <i class="ri-file-search-line ri-36px d-block mb-2 text-secondary"></i>
            No chit revenue records found matching the selected filters.
          </td>
        </tr>
      @endforelse
    </tbody>
    @if($items->isNotEmpty())
      <tfoot class="table-light border-top-2">
        <tr class="fw-bold text-dark">
          <td colspan="3" class="text-end">Total (This Page):</td>
          <td class="text-end text-success">₹{{ number_format($pageForeman, 2) }}</td>
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
