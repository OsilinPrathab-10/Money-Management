@php
    $canCollect = $inst->isCollectible();
    $viewingClientId = $viewingClientId
        ?? ($inst->viewing_client_id ?? null)
        ?? ((isset($mode) && $mode === 'client' && isset($client)) ? (int) $client->id : null);
    $balance = isset($inst->client_share_balance)
        ? round((float) $inst->client_share_balance, 2)
        : round((float) $inst->balance, 2);
    $displayAmount = isset($inst->client_share_amount)
        ? (float) $inst->client_share_amount
        : (float) $inst->amount;
    $paidAmount = isset($inst->client_share_paid)
        ? (float) $inst->client_share_paid
        : (float) $inst->paid_amount;
    $periodLabel = isset($group)
        ? match($group->installment_frequency ?? 'monthly') {
            'daily'  => 'Day ' . $inst->month_number,
            'weekly' => 'Week ' . $inst->month_number,
            default  => 'Month ' . $inst->month_number,
        }
        : 'Month ' . $inst->month_number;
    $isSettled = in_array($inst->status, ['paid', 'waived']) || ($viewingClientId && $balance <= 0.009 && $paidAmount > 0.009);
    $clientName = $inst->client_display_name
        ?? $inst->member?->displayClientName()
        ?? $inst->member->client->client_name
        ?? '—';
    $clientPhone = $inst->member->client->client_phone ?? '';
    $groupCode = isset($group) ? ($group->group_code ?? '') : ($inst->group->group_code ?? '');
    $company = \App\Models\CompanyDetail::first();
    $companyPhone = $company?->company_mobile ?? '';
    $companySlogan = $company?->company_slogan ?? $company?->company_name ?? 'Codepluse Gen PVT Ltd';
    $cleanPhone = preg_replace('/\D/', '', $clientPhone);
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = '91' . $cleanPhone;
    }
    $paidDate = $inst->paid_date ? $inst->paid_date->format('d-m-Y') : '';
    $isPartial = (!$isSettled) && ($inst->status === 'partial' || ($paidAmount > 0 && $inst->status !== 'paid'));
    $isClientView = isset($mode) && $mode === 'client';
    $actionClientId = $viewingClientId ?: ($inst->member->client_id ?? '');
    $partialRulesUrl = route('chit.installments.partial-rules', $inst);
    if ($viewingClientId) {
        $partialRulesUrl .= '?client_id=' . $viewingClientId;
    }
@endphp

@php
    $isConsolidated = !empty($inst->is_consolidated);
    $memberNumbers = $inst->member_numbers ?? ($inst->member?->display_member_number ?? '');
    $cumulativeAmount = $displayAmount;
    $seatInstallment = $inst->member
        ? $inst->member->seatInstallmentAmount($group ?? $inst->group, (int) $inst->month_number)
        : (float) ($group->installment_amount ?? $displayAmount);
    if ($viewingClientId && $inst->member && !empty($inst->member->is_shared)) {
        $seatInstallment = $inst->member->installmentAmountForClient(
            (int) $viewingClientId,
            $group ?? $inst->group,
            (int) $inst->month_number
        );
    } elseif ($inst->member && empty($inst->client_share_amount)) {
        $displayAmount = $inst->member->displayAmountForInstallment($inst, $viewingClientId ?: null);
    }
    $singleAmount = $isConsolidated && $seatInstallment > 0
        ? $seatInstallment
        : $displayAmount;
@endphp

<div class="d-flex justify-content-center align-items-center gap-1 flex-wrap text-nowrap">
@php
    $undoClientId = $viewingClientId ?: $actionClientId;
    $isUndoable = $inst->isUndoable($undoClientId ? (int) $undoClientId : null) && $paidAmount > 0.009;
    $undoFormId = 'undo-form-' . $inst->id . ($undoClientId ? ('-c' . $undoClientId) : '');
    $collectionFrequency = $inst->member?->collection_frequency ?? 'monthly';
    $groupSchedFreq = isset($group)
        ? ($group->installment_frequency ?? 'monthly')
        : ($inst->group->installment_frequency ?? 'monthly');
    $isFreqCollection = in_array($collectionFrequency, ['daily', 'weekly'], true)
        && ($groupSchedFreq === 'monthly' || $groupSchedFreq === '');
    $dueForSplit = $inst->due_date;
    $splitAmount = $inst->member
        ? $inst->member->collectionSplitAmount((float) $displayAmount, $dueForSplit)
        : (float) $displayAmount;
    $suggestedAmount = $inst->member
        ? $inst->member->suggestedCollectionAmount((float) $displayAmount, (float) $balance, $dueForSplit)
        : (float) $balance;
    $splitCount = $inst->member?->collectionSplitCount($dueForSplit) ?? 1;
    $freqPeriods = ($isFreqCollection && $inst->member)
        ? $inst->member->collectionPeriodSchedule($dueForSplit, (float) $displayAmount, (float) $paidAmount)
        : [];
    $nextFreqPeriod = collect($freqPeriods)->firstWhere('is_next', true);
    $freqUnitLabel = $collectionFrequency === 'daily' ? 'Day' : ($collectionFrequency === 'weekly' ? 'Week' : 'Month');
    $payFullLabel = $isFreqCollection ? ('Pay Full ' . $periodLabel) : ('Pay ' . $periodLabel);
@endphp

@if(($canCollect && $balance > 0) || $isUndoable)
    <div class="dropdown d-inline-block">
        <button type="button" 
                class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" 
                data-bs-toggle="dropdown" 
                aria-expanded="false" 
                title="Actions">
            <i class="icon-base ri ri-more-2-fill fs-5"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            @if($canCollect && $balance > 0)
                @if($nextFreqPeriod)
                <li>
                    <a href="javascript:void(0);"
                       class="dropdown-item chit-partial-btn text-primary"
                       data-installment-id="{{ $inst->id }}"
                       data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                       data-partial-rules-url="{{ $partialRulesUrl }}"
                       data-client="{{ $clientName }}"
                       data-client-id="{{ $actionClientId }}"
                       data-period="{{ $periodLabel }} — {{ $nextFreqPeriod['label'] }}"
                       data-amount="{{ $displayAmount }}"
                       data-penalty="{{ $inst->penalty_amount }}"
                       data-paid="{{ $paidAmount }}"
                       data-balance="{{ $balance }}"
                       data-is-consolidated="{{ $isConsolidated ? 1 : 0 }}"
                       data-member-numbers="{{ $memberNumbers }}"
                       data-single-amount="{{ $singleAmount }}"
                       data-cumulative-amount="{{ $cumulativeAmount }}"
                       data-single-seat-only="{{ !empty($inst->is_share_row) ? 1 : 0 }}"
                       data-collection-frequency="{{ $collectionFrequency }}"
                       data-suggested-amount="{{ $nextFreqPeriod['balance'] }}"
                       data-split-amount="{{ $nextFreqPeriod['amount'] }}"
                       data-split-count="{{ $splitCount }}"
                       data-force-frequency-partial="1"
                       data-min-percentage="1"
                       data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                        <i class="icon-base ri ri-calendar-check-line me-2 text-primary"></i>Pay {{ $nextFreqPeriod['label'] }} (₹{{ number_format($nextFreqPeriod['balance'], 2) }})
                    </a>
                </li>
                @endif
                <li>
                    <a href="javascript:void(0);"
                       class="dropdown-item chit-pay-btn text-success"
                       data-installment-id="{{ $inst->id }}"
                       data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                       data-partial-rules-url="{{ $partialRulesUrl }}"
                       data-client="{{ $clientName }}"
                       data-client-id="{{ $actionClientId }}"
                       data-period="{{ $periodLabel }}"
                       data-amount="{{ $displayAmount }}"
                       data-penalty="{{ $inst->penalty_amount }}"
                       data-paid="{{ $paidAmount }}"
                       data-balance="{{ $balance }}"
                       data-is-consolidated="{{ $isConsolidated ? 1 : 0 }}"
                       data-member-numbers="{{ $memberNumbers }}"
                       data-single-amount="{{ $singleAmount }}"
                       data-cumulative-amount="{{ $cumulativeAmount }}"
                       data-single-seat-only="{{ !empty($inst->is_share_row) ? 1 : 0 }}"
                       data-due-date="{{ $inst->due_date?->format('Y-m-d') }}"
                       data-collection-frequency="{{ $collectionFrequency }}"
                       data-suggested-amount="{{ $suggestedAmount }}"
                       data-split-amount="{{ $splitAmount }}"
                       data-split-count="{{ $splitCount }}">
                        <i class="icon-base ri ri-money-dollar-circle-line me-2 text-success"></i>{{ $payFullLabel }}
                    </a>
                </li>

                @if(($partialPaymentConfig['is_active'] ?? false) || $isFreqCollection)
                <li>
                    <a href="javascript:void(0);"
                       class="dropdown-item chit-partial-btn text-info"
                       data-installment-id="{{ $inst->id }}"
                       data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                       data-partial-rules-url="{{ $partialRulesUrl }}"
                       data-client="{{ $clientName }}"
                       data-client-id="{{ $actionClientId }}"
                       data-period="{{ $periodLabel }}"
                       data-amount="{{ $displayAmount }}"
                       data-penalty="{{ $inst->penalty_amount }}"
                       data-paid="{{ $paidAmount }}"
                       data-balance="{{ $balance }}"
                       data-is-consolidated="{{ $isConsolidated ? 1 : 0 }}"
                       data-member-numbers="{{ $memberNumbers }}"
                       data-single-amount="{{ $singleAmount }}"
                       data-cumulative-amount="{{ $cumulativeAmount }}"
                       data-single-seat-only="{{ !empty($inst->is_share_row) ? 1 : 0 }}"
                       data-min-percentage="{{ $isFreqCollection ? 1 : ($partialPaymentConfig['minimum_partial_percentage'] ?? 10) }}"
                       data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}"
                       data-collection-frequency="{{ $collectionFrequency }}"
                       data-suggested-amount="{{ $suggestedAmount }}"
                       data-split-amount="{{ $splitAmount }}"
                       data-split-count="{{ $splitCount }}"
                       @if($isFreqCollection) data-force-frequency-partial="1" @endif>
                        <i class="icon-base ri ri-percent-line me-2 text-info"></i>{{ $isFreqCollection ? 'Custom Partial' : 'Partially Pay' }}
                    </a>
                </li>
                @endif
            @endif

            @if($isUndoable)
                @if($canCollect && $balance > 0) <li><hr class="dropdown-divider"></li> @endif
                <li>
                    <a href="javascript:void(0);"
                       class="dropdown-item text-danger chit-undo-btn"
                       data-form-id="{{ $undoFormId }}"
                       onclick="confirmChitUndoPayment('{{ $undoFormId }}')">
                        <i class="icon-base ri ri-history-line me-2 text-danger"></i>Undo Payment
                    </a>
                </li>
            @endif
        </ul>
    </div>

    @if($isUndoable)
    <form id="{{ $undoFormId }}" action="{{ route('chit.installments.undo', $inst) }}" method="POST" class="d-none">
        @csrf
        @if(!empty($undoClientId))
            <input type="hidden" name="client_id" value="{{ $undoClientId }}">
        @elseif(!empty($inst->client_id))
            <input type="hidden" name="client_id" value="{{ $inst->client_id }}">
        @endif
    </form>
    @endif
@elseif($isSettled)
    <span class="badge bg-label-success">Paid</span>
    @if(!($inst->status === 'paid' || ($viewingClientId && $balance <= 0.009 && $paidAmount > 0.009)))
        <small class="text-info ms-1">Waived</small>
    @endif
@else
    <span class="badge bg-label-secondary me-1" title="Previous installment(s) pending">
        <i class="ri-lock-line me-1 text-danger"></i> Locked
    </span>
@endif

@php
    $clientId = $actionClientId ?: ($inst->member->client_id ?? null);
    $clientPublicToken = $clientId ? \App\Support\HashId::encode($clientId) : null;
    $clientScheduleUrl = $clientPublicToken ? route('public.view-chit-schedule', $clientPublicToken) : '#';
@endphp

<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-copy-public-link" data-link="{{ $clientScheduleUrl }}" title="Copy Public Schedule Link">
    <i class="icon-base ri ri-link icon-20px"></i>
</button>

@if($clientPhone)
    <a href="tel:{{ preg_replace('/\s+/', '', $clientPhone) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Client">
        <i class="icon-base ri ri-phone-line icon-20px"></i>
    </a>
@endif

@if($cleanPhone)
    @php
        $pubToken = $clientId ? \App\Support\HashId::encode($clientId) : null;
        $pubLink = $pubToken ? route('public.view-chit-schedule', $pubToken) : '';
        $msgData = [
            'client_name' => $clientName,
            'mobile_no' => $cleanPhone,
            'group_name' => $groupCode,
            'period_label' => $periodLabel,
            'month_number' => $inst->month_number,
            'amount_paid' => $paidAmount,
            'amount_due' => $balance > 0 ? $balance : $displayAmount,
            'due_date' => $inst->due_date ? $inst->due_date->format('d-m-Y') : '',
            'remaining_balance' => $balance,
            'client_id' => $clientId,
            'public_link' => $pubLink,
        ];

        if ($inst->status === 'paid' || $isPartial) {
            $msgRes = \App\Helpers\NotificationTemplateHelper::getChitRepaymentMessages($msgData);
            $btnTitleWa = "Send WhatsApp Confirmation";
            $btnTitleSms = "Send SMS Confirmation";
        } elseif ($inst->status === 'overdue') {
            $msgRes = \App\Helpers\NotificationTemplateHelper::getChitOverdueReminderMessages($msgData);
            $btnTitleWa = "Send Overdue Reminder via WhatsApp";
            $btnTitleSms = "Send Overdue Reminder via SMS";
        } else {
            $msgRes = \App\Helpers\NotificationTemplateHelper::getChitPendingReminderMessages($msgData);
            $btnTitleWa = "Send Pending Reminder via WhatsApp";
            $btnTitleSms = "Send Pending Reminder via SMS";
        }
        $waMsg = $msgRes['whatsapp_message'] ?? '';
        $smsMsg = $msgRes['sms_message'] ?? '';
    @endphp
    <a href="https://wa.me/{{ $cleanPhone }}?text={{ rawurlencode($waMsg) }}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-success" title="{{ $btnTitleWa }}">
        <i class="icon-base ri ri-whatsapp-line icon-20px"></i>
    </a>
    <a href="sms:+{{ $cleanPhone }}?body={{ rawurlencode($smsMsg) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info" title="{{ $btnTitleSms }}">
        <i class="icon-base ri ri-message-3-line icon-20px"></i>
    </a>
@endif
</div>

<script>
if (typeof window.confirmChitUndoPayment !== 'function') {
  window.confirmChitUndoPayment = function(formId) {
    var form = document.getElementById(formId);
    if (!form) return;
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Are you sure?',
        text: 'Are you sure you want to undo this payment? This action will reverse the payment and restore the installment balance.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, undo payment!',
        cancelButtonText: 'Cancel',
        customClass: {
          confirmButton: 'btn btn-danger me-2',
          cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
      }).then(function(result) {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    } else {
      if (confirm('Are you sure you want to undo this payment?')) {
        form.submit();
      }
    }
  };
}
</script>
