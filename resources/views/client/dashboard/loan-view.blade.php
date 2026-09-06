@php
  $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Loan Schedule')

@section('content')
<div class="row g-6">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Loan Details</li>
            </ol>
        </nav>
    </div>

    <!-- Loan Info -->
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-label-primary">
                <h5 class="mb-0">Loan Information</h5>
            </div>
            <div class="card-body pt-5">
                <div class="info-container">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-4">
                            <span class="fw-medium text-heading me-2">Account Number:</span>
                            <span>{{ $loanAccount->account_number }}</span>
                        </li>
                        <li class="mb-4">
                            <span class="fw-medium text-heading me-2">Loan Product:</span>
                            <span>{{ optional($loanAccount->loanApplication->product)->loan_name }}</span>
                        </li>
                        <li class="mb-4">
                            <span class="fw-medium text-heading me-2">Loan Amount:</span>
                            <span class="text-primary fw-bold">₹{{ number_format($loanAccount->loan_amount, 2) }}</span>
                        </li>
                        <li class="mb-4">
                            <span class="fw-medium text-heading me-2">Tenure:</span>
                            <span>
                                @if($isKandhuvatti)
                                    Flexible (Interest Only)
                                @else
                                    {{ $loanAccount->tenure }} {{ $loanAccount->tenure_type }}
                                @endif
                            </span>
                        </li>
                        <li class="mb-4">
                            <span class="fw-medium text-heading me-2">{{ $isKandhuvatti ? 'Remaining Principal Balance' : 'Outstanding' }}:</span>
                            <span class="text-danger fw-bold">₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
                        </li>
                        <li class="mb-0">
                            <span class="fw-medium text-heading me-2">Disbursed Date:</span>
                            <span>{{ $loanAccount->disbursed_at ? $loanAccount->disbursed_at->format('d-m-Y') : 'N/A' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- EMI Schedule -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'Repayment Schedule' }}</h5>
                <div class="d-flex align-items-center gap-3">
                    @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
                        <span class="badge bg-label-danger fw-bold">Remaining Principal Balance: ₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
                    @endif
                    <span class="badge bg-label-info">{{ $loanAccount->emis->count() }} {{ $isKandhuvatti ? 'Interest Cycles' : 'EMI Installments' }}</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ $isKandhuvatti ? 'Cycle' : '#' }}</th>
                            <th>Due Date</th>
                            <th>{{ $isKandhuvatti ? 'Cycle Interest' : 'EMI Amount' }}</th>
                            @if($isKandhuvatti)
                                <th>Principal Paid</th>
                            @endif
                            <th>Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $firstUnpaid = $loanAccount->emis->where('status', '!=', 'paid')->sortBy('instalment_number')->first();
                            $firstUnpaidId = $firstUnpaid ? $firstUnpaid->id : null;
                        @endphp
                        @foreach($loanAccount->emis as $emi)
                        <tr class="{{ $emi->status == 'overdue' ? 'table-danger' : ($emi->status == 'paid' ? 'table-light' : '') }}">
                            <td>{{ $emi->instalment_number }}</td>
                            <td>{{ $emi->due_date->format('d-m-Y') }}</td>
                            <td class="fw-bold">₹{{ number_format($isKandhuvatti ? $emi->interest_amount : $emi->total_amount, 2) }}</td>
                            @if($isKandhuvatti)
                                <td class="text-info">
                                    @php
                                        $displayPrincipal = $emi->principal_amount;
                                        if ($isKandhuvatti && $emi->id === $firstUnpaidId && $loanAccount->outstanding_amount > 0) {
                                            $displayPrincipal = $loanAccount->outstanding_amount;
                                        }
                                    @endphp
                                    {{ $displayPrincipal > 0 ? '₹'.number_format($displayPrincipal, 2) : '-' }}
                                </td>
                            @endif
                            <td class="text-success">
                                {{ $emi->paid_amount ? '₹'.number_format($emi->paid_amount, 2) : '-' }}
                            </td>
                            <td>
                                @php
                                    $hasInProgress = $emi->collections->where('status', 'in_progress')->isNotEmpty();
                                @endphp

                                @if($hasInProgress && $emi->status != 'paid')
                                    <span class="badge bg-label-warning">Paid</span>
                                @elseif($emi->status == 'paid')
                                    <span class="badge bg-label-success">Paid</span>
                                @elseif($emi->status == 'overdue')
                                    <span class="badge bg-danger">Overdue</span>
                                @elseif($emi->status == 'partial')
                                    <span class="badge bg-label-info">Partial</span>
                                @else
                                    <span class="badge bg-label-warning">Pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="{{ $isKandhuvatti ? 6 : 5 }}" class="text-end fw-bold py-3 text-danger border-top">
                                Unallocated Principal: ₹{{ number_format($loanAccount->outstanding_amount, 2) }} (Carry Forward)
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
