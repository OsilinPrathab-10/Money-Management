<h3>Loan Statement</h3>

<p><strong>Client:</strong> {{ $client->client_name }}</p>
<p><strong>Loan ID:</strong> {{ $loan->id }}</p>
<p><strong>Loan Amount:</strong> {{ $loan->loan_amount }}</p>

<table width="100%">
    <thead>
        <tr>
            <th>S.No</th>
            <th>Due Date</th>
            <th>Amount</th>
            <th>Paid Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($emis as $index => $emi)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ \Carbon\Carbon::parse($emi->due_date)->format('d-m-Y') }}</td>
            <td>{{ number_format($emi->total_amount, 2) }}</td>
            <td>{{ $emi->paid_date ? \Carbon\Carbon::parse($emi->paid_date)->format('d-m-Y') : '-' }}</td>
            <td>{{ ucfirst($emi->status) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" style="text-align:center;">No EMI Records Found</td>
        </tr>
    @endforelse
    </tbody>
</table>
