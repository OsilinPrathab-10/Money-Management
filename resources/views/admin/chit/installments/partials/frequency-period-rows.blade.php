{{-- Nested daily/weekly period rows under a monthly installment --}}
@php
    $fpInst = $inst;
    $fpMember = $fpInst->member;
    $fpGroup = $group ?? $fpInst->group;
    $fpLayout = $layout ?? 'client'; // client | show | client_chits_group | client_chits_member
    $fpShowBulkCheckbox = !empty($showBulkCheckbox);
    $fpClientId = $viewingClientId
        ?? ($fpInst->viewing_client_id ?? null)
        ?? ((isset($mode) && $mode === 'client' && isset($client)) ? (int) $client->id : null);
    $fpAmount = isset($fpInst->client_share_amount)
        ? (float) $fpInst->client_share_amount
        : ($fpMember
            ? $fpMember->displayAmountForInstallment($fpInst, $fpClientId ?: null)
            : (float) $fpInst->amount);
    $fpPaid = isset($fpInst->client_share_paid)
        ? (float) $fpInst->client_share_paid
        : ($fpClientId && $fpMember && !empty($fpInst->is_share_row)
            ? $fpInst->clientPaidShare((int) $fpClientId)
            : (float) $fpInst->paid_amount);
    $fpBalance = isset($fpInst->client_share_balance)
        ? (float) $fpInst->client_share_balance
        : ($fpClientId && $fpMember && !empty($fpInst->is_share_row)
            ? $fpInst->clientBalanceShare((int) $fpClientId)
            : (float) $fpInst->balance);
    $fpPenalty = $fpMember
        ? $fpMember->penaltyAmountForClient((float) $fpInst->penalty_amount, $fpClientId ?: null)
        : (float) $fpInst->penalty_amount;
    if ($fpMember && (float) $fpInst->paid_amount < 0.01 && abs((float) $fpInst->amount - $fpAmount) > 0.05) {
        $fpBalance = max(0, round($fpAmount + $fpPenalty - $fpPaid, 2));
    }
    if ($fpLayout === 'show' && !isset($fpInst->client_share_amount)) {
        $fpAmount = (float) $fpInst->amount;
        $fpPaid = (float) $fpInst->paid_amount;
        $fpBalance = (float) $fpInst->balance;
    }
    $fpFreq = $fpMember->collection_frequency ?? 'monthly';
    $fpGroupFreq = $fpGroup->installment_frequency ?? 'monthly';
    $fpPeriods = ($fpGroupFreq === 'monthly' || $fpGroupFreq === '')
        && in_array($fpFreq, ['daily', 'weekly'], true)
        && $fpMember
        ? $fpMember->collectionPeriodSchedule($fpInst->due_date, (float) $fpAmount, (float) $fpPaid)
        : [];
    $fpSplitCount = count($fpPeriods);
    $fpClientName = $fpInst->client_display_name
        ?? $fpMember?->displayClientName()
        ?? $fpMember?->client?->client_name
        ?? (isset($client) ? $client->client_name : '—');
    $fpPeriodLabel = match($fpGroupFreq) {
        'daily'  => 'Day ' . $fpInst->month_number,
        'weekly' => 'Week ' . $fpInst->month_number,
        default  => 'Month ' . $fpInst->month_number,
    };
    $fpPartialRulesUrl = route('chit.installments.partial-rules', $fpInst);
    if ($fpClientId) {
        $fpPartialRulesUrl .= '?client_id=' . $fpClientId;
    }
    $fpMemberNo = $fpInst->display_member_number
        ?? $fpMember?->display_member_number
        ?? '';
    $fpRowHidden = !empty($rowHidden);
@endphp

@foreach($fpPeriods as $period)
    @php
        $pStatusColor = match ($period['status']) {
            'paid' => 'success',
            'partial' => 'info',
            'overdue' => 'danger',
            default => 'warning',
        };
        $fpPeriodTitle = $period['label'] . (! empty($period['is_current']) ? ' · Today' : '');
        $canPayPeriod = $fpInst->isCollectible() && $fpBalance > 0.009 && $period['balance'] > 0.009;
        $periodBulkCb = !empty($fpShowBulkCheckbox) && $canPayPeriod
            ? '<input type="checkbox" class="form-check-input chit-bulk-cb" value="' . (int) $fpInst->id . '"'
                . ' data-client-id="' . e((string) ($fpClientId ?? '')) . '"'
                . ' data-balance="' . e((string) $fpBalance) . '"'
                . ' data-suggested="' . e((string) $period['balance']) . '"'
                . ' data-period-amount="' . e((string) $period['balance']) . '"'
                . ' data-period-index="' . (int) $period['index'] . '"'
                . ' data-period-label="' . e($period['label']) . '"'
                . ' data-month-number="' . (int) $fpInst->month_number . '"'
                . ' data-is-next="' . (!empty($period['is_next']) ? '1' : '0') . '"'
                . ' data-is-freq="1">'
            : '';
    @endphp
    <tr class="inst-row inst-freq-row group-month-inst-row bg-light"
        data-month="month-{{ $fpInst->month_number }}"
        data-status="{{ $period['status'] }}"
        data-period-index="{{ $period['index'] }}"
        data-parent-inst="{{ $fpInst->id }}"
        @if($fpRowHidden) style="display:none" @endif>

        @if($fpLayout === 'show')
            @if(!empty($fpShowBulkCheckbox))
            <td class="text-center">{!! $periodBulkCb !!}</td>
            @endif
            <td class="text-center text-muted small">—</td>
            <td class="text-muted small ps-3"><i class="ri-corner-down-right-line me-1"></i>{{ $fpPeriodTitle }}</td>
            <td class="text-end small">₹{{ number_format($period['amount'], 2) }}</td>
            <td class="text-end small text-muted">—</td>
            <td class="text-end text-success small">₹{{ number_format($period['paid'], 2) }}</td>
            <td class="text-end fw-semibold small {{ $period['balance'] > 0.009 ? 'text-danger' : 'text-muted' }}">
                {{ $period['balance'] > 0.009 ? '₹' . number_format($period['balance'], 2) : '—' }}
            </td>
            <td class="small">{{ $period['due_date'] ? $period['due_date']->format('d M Y') : '—' }}</td>
            <td class="text-center"><span class="badge bg-label-{{ $pStatusColor }} text-capitalize" style="font-size:.65rem;">{{ $period['status'] }}</span></td>
        @elseif($fpLayout === 'client_chits_group')
            @if(!empty($fpShowBulkCheckbox))
            <td class="text-center">{!! $periodBulkCb !!}</td>
            @endif
            <td class="text-center text-muted small">—</td>
            <td class="text-muted small ps-3"><i class="ri-corner-down-right-line me-1"></i>{{ $fpPeriodTitle }}</td>
            <td class="small text-muted">—</td>
            <td class="small">{{ $period['due_date'] ? $period['due_date']->format('d-m-Y') : '—' }}</td>
            <td class="text-end small">₹{{ number_format($period['amount'], 2) }}</td>
            <td class="text-end small text-muted">—</td>
            <td class="text-end text-success small">₹{{ number_format($period['paid'], 2) }}</td>
            <td class="text-end fw-semibold small {{ $period['balance'] > 0.009 ? 'text-danger' : 'text-muted' }}">
                {{ $period['balance'] > 0.009 ? '₹' . number_format($period['balance'], 2) : '—' }}
            </td>
            <td class="text-center"><span class="badge bg-label-{{ $pStatusColor }} text-capitalize" style="font-size:.65rem;">{{ $period['status'] }}</span></td>
        @elseif($fpLayout === 'client_chits_member')
            @if(!empty($fpShowBulkCheckbox))
            <td class="text-center">{!! $periodBulkCb !!}</td>
            @endif
            <td class="text-center text-muted small">—</td>
            <td class="text-muted small ps-3"><i class="ri-corner-down-right-line me-1"></i>{{ $fpPeriodTitle }}</td>
            <td class="small">{{ $period['due_date'] ? $period['due_date']->format('d-m-Y') : '—' }}</td>
            <td class="text-end small">₹{{ number_format($period['amount'], 2) }}</td>
            <td class="text-end small text-muted">—</td>
            <td class="text-end text-success small">₹{{ number_format($period['paid'], 2) }}</td>
            <td class="text-end fw-semibold small {{ $period['balance'] > 0.009 ? 'text-danger' : 'text-muted' }}">
                {{ $period['balance'] > 0.009 ? '₹' . number_format($period['balance'], 2) : '—' }}
            </td>
            <td class="text-center"><span class="badge bg-label-{{ $pStatusColor }} text-capitalize" style="font-size:.65rem;">{{ $period['status'] }}</span></td>
        @else
            @if(!empty($fpShowBulkCheckbox))
            <td class="text-center">{!! $periodBulkCb !!}</td>
            @endif
            <td class="text-muted small ps-4"><i class="ri-corner-down-right-line me-1"></i>{{ $fpPeriodTitle }}</td>
            <td class="small text-muted">—</td>
            <td class="small">{{ $period['due_date'] ? $period['due_date']->format('d M Y') : '—' }}</td>
            <td class="text-end small">₹{{ number_format($period['amount'], 2) }}</td>
            <td class="text-end small text-muted">—</td>
            <td class="text-end text-success small">₹{{ number_format($period['paid'], 2) }}</td>
            <td class="text-end fw-semibold small {{ $period['balance'] > 0.009 ? 'text-danger' : 'text-muted' }}">
                {{ $period['balance'] > 0.009 ? '₹' . number_format($period['balance'], 2) : '—' }}
            </td>
            <td class="text-center"><span class="badge bg-label-{{ $pStatusColor }} text-capitalize" style="font-size:.65rem;">{{ $period['status'] }}</span></td>
        @endif

        <td class="text-center text-nowrap">
            @if($canPayPeriod)
                <a href="javascript:void(0);"
                   class="btn btn-xs btn-primary chit-partial-btn"
                   data-installment-id="{{ $fpInst->id }}"
                   data-collect-url="{{ route('chit.installments.collect', $fpInst) }}"
                   data-partial-rules-url="{{ $fpPartialRulesUrl }}"
                   data-client="{{ $fpClientName }}"
                   data-client-id="{{ $fpClientId }}"
                   data-single-seat-only="{{ !empty($fpInst->is_share_row) ? 1 : 0 }}"
                   data-period="{{ $fpPeriodLabel }} — {{ $period['label'] }}"
                   data-amount="{{ $fpAmount }}"
                   data-penalty="0"
                   data-paid="{{ $fpPaid }}"
                   data-balance="{{ $fpBalance }}"
                   data-is-consolidated="0"
                   data-member-numbers="{{ $fpMemberNo }}"
                   data-single-amount="{{ $fpAmount }}"
                   data-cumulative-amount="{{ $fpAmount }}"
                   data-collection-frequency="{{ $fpFreq }}"
                   data-suggested-amount="{{ $period['balance'] }}"
                   data-split-amount="{{ $period['amount'] }}"
                   data-split-count="{{ $fpSplitCount }}"
                   data-force-frequency-partial="1"
                   data-min-percentage="1"
                   data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                    <i class="ri-money-dollar-circle-line me-1"></i>Pay {{ $period['label'] }}
                </a>
            @elseif($period['status'] === 'paid')
                <span class="badge bg-label-success" style="font-size:.65rem;">Paid</span>
            @else
                <span class="text-muted">—</span>
            @endif
        </td>
    </tr>
@endforeach
