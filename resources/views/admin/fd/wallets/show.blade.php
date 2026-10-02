@extends('layouts/layoutMaster')

@section('title', 'Wallet — ' . ($wallet->client->client_name ?? 'Customer'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-0">{{ $wallet->client->client_name ?? 'Customer Wallet' }}</h4>
        <p class="text-muted mb-0">
            {{ $wallet->client->client_phone ?? '' }}
            · Wallet #{{ $wallet->id }}
        </p>
    </div>
    <a href="{{ route('fd.wallets.index') }}" class="btn btn-label-secondary">
        <i class="ri-arrow-left-line me-1"></i>Back to Wallets
    </a>
    @if(($wallet->status ?? 'active') === 'active' && (float) $wallet->balance > 0)
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#withdrawModal">
        <i class="ri-hand-coin-line me-1"></i>Withdraw
    </button>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-border-shadow-primary h-100">
            <div class="card-body">
                <small class="text-muted">Current Balance</small>
                <h3 class="mb-0 fw-bold text-primary">₹{{ number_format((float) $wallet->balance, 2) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Status</small>
                <h5 class="mb-0 mt-1">
                    <span class="badge bg-{{ ($wallet->status ?? 'active') === 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($wallet->status ?? 'active') }}
                    </span>
                </h5>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Last Updated</small>
                <h5 class="mb-0 mt-1">{{ optional($wallet->updated_at)->format('d M Y H:i') }}</h5>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            Transactions
            <span class="badge bg-label-primary ms-2">{{ $transactions->total() }}</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance After</th>
                        <th>Reference</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $txn)
                    <tr>
                        <td>{{ optional($txn->created_at)->format('d M Y H:i') }}</td>
                        <td>
                            <span class="badge bg-{{ $txn->type === 'credit' ? 'success' : 'danger' }}">
                                {{ ucfirst($txn->type) }}
                            </span>
                        </td>
                        <td class="text-end fw-semibold {{ $txn->type === 'credit' ? 'text-success' : 'text-danger' }}">
                            {{ $txn->type === 'credit' ? '+' : '-' }}₹{{ number_format((float) $txn->amount, 2) }}
                        </td>
                        <td class="text-end">₹{{ number_format((float) $txn->balance_after, 2) }}</td>
                        <td>
                            @if($txn->reference_type)
                                <span class="badge bg-label-secondary">{{ $txn->reference_type }}</span>
                                @if($txn->reference_id)#{{ $txn->reference_id }}@endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $txn->description ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">No wallet transactions yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>

<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('fd.wallets.withdraw', $wallet) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Withdraw from Wallet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Available Balance: <strong>₹{{ number_format((float) $wallet->balance, 2) }}</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                           max="{{ $wallet->balance }}" value="{{ $wallet->balance }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Mode <span class="text-danger">*</span></label>
                    <select name="payment_mode" id="wallet_withdraw_mode" class="form-select" required>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="upi">UPI</option>
                    </select>
                </div>
                <div class="mb-3" id="wallet_withdraw_bank_wrap" style="display:none;">
                    <label class="form-label fw-semibold">Company Bank Account <span class="text-danger">*</span></label>
                    <select name="internal_bank_account_id" id="wallet_withdraw_bank" class="form-select">
                        <option value="">— Select bank —</option>
                        @foreach(($bankAccounts ?? []) as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->bank_name }} - {{ $account->account_name }} (Bal: ₹{{ number_format((float) $account->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reference</label>
                    <input type="text" name="reference" class="form-control" placeholder="UTR / receipt no.">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Process Withdrawal</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('wallet_withdraw_mode');
    const wrap = document.getElementById('wallet_withdraw_bank_wrap');
    const bank = document.getElementById('wallet_withdraw_bank');
    const sync = () => {
        const need = mode && (mode.value === 'upi' || mode.value === 'bank_transfer');
        if (wrap) wrap.style.display = need ? '' : 'none';
        if (bank) bank.required = !!need;
    };
    mode?.addEventListener('change', sync);
    sync();
});
</script>
@endsection
