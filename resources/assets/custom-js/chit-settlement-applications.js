'use strict';

$(function () {
  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) baseUrl += '/';

  const csrfToken = $('meta[name="csrf-token"]').attr('content');
  const dtTable = $('.datatables-chit-settlements');

  if (dtTable.length) {
    const urlParams = new URLSearchParams(window.location.search);
    const presetGroupId = urlParams.get('group_id');
    if (presetGroupId) {
      $('#groupFilter').val(presetGroupId);
    }

    var dt = dtTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: `${baseUrl}admin/chit/settlement-applications/data`,
        type: 'GET',
        data: function (d) {
          d.status = $('#statusFilter').val();
          d.source = $('#sourceFilter').val();
          d.group_id = $('#groupFilter').val();
          d.from_date = $('#fromDateFilter').val();
          d.to_date = $('#toDateFilter').val();
          d.search = d.search?.value || '';
        },
        dataSrc: function (json) {
          updateCounts(json);
          return json.data;
        }
      },
      columns: [
        { data: 'member_number', className: 'text-center' },
        {
          data: 'payout_code',
          render: function (data) {
            return `<code>${data}</code>`;
          }
        },
        {
          data: null,
          render: function (data) {
            const icon = data.source === 'customer'
              ? 'ri-smartphone-line'
              : (data.source === 'agent' ? 'ri-user-star-line' : 'ri-computer-line');
            const login = data.applicant_login
              ? `<div class="small text-muted mt-1">${data.applicant_login}</div>`
              : '';
            return `<span class="badge bg-label-${data.source_badge || 'primary'} text-nowrap"><i class="${icon} me-1"></i>${data.source_label || 'Admin'}</span>${login}`;
          }
        },
        {
          data: 'client_name',
          render: function (data, type, row) {
            const phone = row.client_phone && row.client_phone !== '—'
              ? `<div class="small text-muted">${row.client_phone}</div>`
              : '';
            return `<div>${data || '—'}</div>${phone}`;
          }
        },
        { data: 'client_phone' },
        { data: 'group_code' },
        { data: 'scheme_name' },
        { data: 'chit_value', className: 'text-end' },
        {
          data: 'settlement_amount',
          className: 'text-end',
          render: function (data) {
            return `<span class="text-success fw-semibold">₹${data || '0'}</span>`;
          }
        },
        {
          data: 'month_label',
          render: function (data, type, row) {
            if (!data) return '<span class="text-muted">—</span>';
            const kind = row.payout_kind_label
              ? `<span class="badge bg-label-${row.payout_kind_badge || 'secondary'} ms-1">${row.payout_kind_label}</span>`
              : '';
            return `<span class="badge bg-label-primary">${data}</span>${kind}`;
          }
        },
        {
          data: null,
          render: function (data) {
            return `<span class="badge bg-${data.status_badge}">${data.status_label}</span>`;
          }
        },
        { data: 'applied_at' },
        {
          data: null,
          orderable: false,
          className: 'text-nowrap',
          render: function (data) {
            let html = `<a href="${baseUrl}admin/chit/settlement-applications/${data.id}" class="btn btn-icon btn-sm btn-label-primary rounded-circle me-1" title="View Review"><i class="ri-eye-line"></i></a>`;
            if (data.status === 'pending' || data.status === 'processing') {
              html += `<a href="${baseUrl}admin/chit/settlements/${data.group_id}/${data.member_id}/confirm?payout_id=${data.id}" class="btn btn-icon btn-sm btn-success rounded-circle me-1 text-white" title="Approve / Confirm Release" style="background-color:#28a745;border-color:#28a745;"><i class="ri-checkbox-circle-line text-white"></i></a>`;
              html += `<button type="button" class="btn btn-icon btn-sm btn-label-danger rounded-circle btn-reject-settlement" data-payout-id="${data.id}" data-client="${data.client_name}" data-code="${data.payout_code}" title="Reject Application"><i class="ri-close-circle-line"></i></button>`;
            }
            return html;
          }
        }
      ],
      order: [[11, 'desc']],
      pageLength: 15,
      dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: {
        emptyTable: 'No chit settlement applications found',
        zeroRecords: 'No matching settlement applications'
      }
    });

    $('#sourceFilter, #groupFilter, #fromDateFilter, #toDateFilter').on('change input', function () {
      dt.ajax.reload();
    });

    function setSettlementTab(status) {
      const tabStatus = status || 'pending';
      $('#statusFilter').val(tabStatus);
      $('#settlementApplicationTabs button').removeClass('active');
      $(`#settlementApplicationTabs button[data-status="${tabStatus}"]`).addClass('active');
      const titles = {
        pending: 'Applied Applications',
        approved: 'Approved Applications',
        rejected: 'Rejected Applications'
      };
      $('#settlementTableTitle').html(
        `<i class="icon-base ri ri-file-list-3-line me-2"></i>${titles[tabStatus] || 'Settlement Applications'}`
      );
      dt.ajax.reload();
    }

    $('#settlementApplicationTabs button').on('shown.bs.tab click', function (e) {
      e.preventDefault();
      setSettlementTab($(this).attr('data-status'));
    });

    $('.stat-card').on('click', function () {
      const status = $(this).data('status');
      if (status !== undefined) {
        setSettlementTab(status);
      }
    });

    $('#btnResetFilters').on('click', function () {
      $('#sourceFilter').val('all');
      $('#groupFilter').val('');
      $('#fromDateFilter').val('');
      $('#toDateFilter').val('');
      var preset = document.getElementById('settlementAppDatePreset');
      if (preset) preset.value = '';
      var root = document.querySelector('[data-preset-id="settlementAppDatePreset"]');
      if (root) {
        var custom = root.querySelector('[data-custom-range]');
        if (custom) custom.style.display = 'none';
      }
      setSettlementTab('pending');
    });
  }

  function updateCounts(json) {
    if (json.counts) {
      $('#countPending').text(json.counts.pending || 0);
      $('#countApproved').text(json.counts.approved || ((json.counts.paid || 0) + (json.counts.processing || 0)));
      $('#countRejected').text(json.counts.rejected || 0);
      $('#tab-count-pending').text(json.counts.pending || 0);
      $('#tab-count-approved').text(json.counts.approved || 0);
      $('#tab-count-rejected').text(json.counts.rejected || 0);
    }
  }

  const modalEl = document.getElementById('modalApplySettlement');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

  $('#btnApplySettlement').on('click', function () {
    if (modal) modal.show();
  });

  let allMemberOptions = [];

  function mapSettlementMember(row) {
    const monthPayouts = row.month_payouts && typeof row.month_payouts === 'object'
      ? row.month_payouts
      : {};

    return {
      id: String(row.id),
      groupId: String(row.group_id || ''),
      clientId: String(row.client_id || ''),
      isShared: !!row.is_shared,
      enrollmentSuffix: row.enrollment_suffix || '',
      clientName: row.client_name || '—',
      groupCode: row.group_code || '—',
      scheme: row.scheme_name || '—',
      memberNumber: row.display_member_number || row.member_number || '—',
      chitValue: row.chit_value_formatted || '₹0.00',
      installment: row.installment_formatted || '₹0.00',
      frequency: row.frequency || 'Monthly',
      goingMonth: parseInt(row.going_month || '1', 10),
      totalMonths: parseInt(row.total_months || '20', 10),
      nextSettlement: parseInt(row.next_settlement_month || '1', 10),
      estSettlement: row.estimated_settlement_formatted || '₹0.00',
      sharePercentage: parseFloat(row.share_percentage || '100') || 100,
      sharePercentageLabel: row.share_percentage_label || '100%',
      monthPayouts: monthPayouts,
      allowsAdvance: !!row.allows_advance,
      canApplyAdvance: !!row.can_apply_advance,
      advancePeriod: parseInt(row.advance_period || '0', 10),
      advancePeriodLabel: row.advance_period_label || '',
      usesContributionSettlement: !!row.uses_contribution_settlement,
      isOutgoingTransferred: !!row.is_outgoing_transferred,
      isCancelledWithdrawn: !!row.is_cancelled_withdrawn,
      needMonth: row.chit_need_month_label || 'Not set',
      needPeriods: row.chit_need_periods_label || '',
      preferredNeedMonth: Array.isArray(row.chit_need_periods) && row.chit_need_periods.length
        ? parseInt(row.chit_need_periods[0], 10)
        : (row.preferred_chit_need_period ? parseInt(row.preferred_chit_need_period, 10) : null),
      bankName: row.bank_name || '',
      accountNumber: row.account_number || '',
      ifscCode: row.ifsc_code || '',
      accountHolderName: row.account_holder_name || '',
      existingStatus: row.existing_application_status || '',
      existingId: row.existing_application_id ? String(row.existing_application_id) : '',
      existingCode: row.existing_payout_code || '',
      text: row.client_name || '—'
    };
  }

  function cacheMemberOptions() {
    const source = Array.isArray(window.chitSettlementMembers) ? window.chitSettlementMembers : [];
    allMemberOptions = source.map(mapSettlementMember);
  }

  cacheMemberOptions();

  function initSelect2($el, placeholder) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.select2({
      dropdownParent: $(modalEl),
      width: '100%',
      placeholder: placeholder || $el.data('placeholder') || 'Select member or client',
      allowClear: true
    });
  }

  function renderMemberGroupsList(memberId) {
    const $container = $('#memberGroupsContainer');
    const $list = $('#memberGroupsList');

    if (!memberId) {
      $container.hide();
      $list.empty();
      return;
    }

    const selectedOpt = allMemberOptions.find(m => String(m.id) === String(memberId));
    if (!selectedOpt) {
      $container.hide();
      $list.empty();
      return;
    }

    const targetClientId = selectedOpt.clientId;
    const clientName = selectedOpt.clientName || 'Client';
    const isMultiSeatSeat = !!selectedOpt.enrollmentSuffix;

    let matchingMembers = allMemberOptions.filter(m => {
      // Shared / contribution seats are unique per membership — do not merge by client.
      if (selectedOpt.isShared || m.isShared
        || selectedOpt.usesContributionSettlement || m.usesContributionSettlement
        || selectedOpt.isOutgoingTransferred || m.isOutgoingTransferred
        || selectedOpt.isCancelledWithdrawn || m.isCancelledWithdrawn) {
        return String(m.id) === String(selectedOpt.id);
      }
      if (!targetClientId || String(m.clientId) !== String(targetClientId)) {
        return false;
      }
      // Same client, 2+ seats in one group: separate flow — only the selected seat
      // for that group; still include this client's seats in other groups.
      if (isMultiSeatSeat && String(m.groupId) === String(selectedOpt.groupId)) {
        return String(m.id) === String(selectedOpt.id);
      }
      return true;
    });

    if (!matchingMembers.length) {
      matchingMembers = [selectedOpt];
    }

    $('#selectedMemberNameBadge').text('Client: ' + clientName);

    let html = '';
    matchingMembers.forEach(m => {
      const totalMonths = m.totalMonths > 0 ? m.totalMonths : 20;

      let firstValidMonth = null;
      for (let i = 1; i <= totalMonths; i++) {
        if (m.monthPayouts && Object.keys(m.monthPayouts).length > 0 && !m.monthPayouts[i]) {
          continue;
        }
        if (firstValidMonth === null) {
          firstValidMonth = i;
        }
      }

      let defaultMonth = m.nextSettlement || m.goingMonth || firstValidMonth || 1;
      if (m.canApplyAdvance && m.advancePeriod > 0) {
        defaultMonth = m.advancePeriod;
      } else if (m.preferredNeedMonth && m.monthPayouts && m.monthPayouts[m.preferredNeedMonth]) {
        defaultMonth = m.preferredNeedMonth;
      } else if (m.monthPayouts && Object.keys(m.monthPayouts).length > 0 && !m.monthPayouts[defaultMonth]) {
        defaultMonth = firstValidMonth || 1;
      }

      const isPending = m.existingStatus === 'pending' || m.existingStatus === 'processing';
      const isPaid = m.existingStatus === 'paid';
      const blocked = isPending || isPaid;
      // Contribution refunds (outgoing transfer / cancelled) are not locked to chit-need month.
      const needLocked = !!(m.preferredNeedMonth && !blocked && !m.canApplyAdvance && !m.usesContributionSettlement);

      // When advance is available, preferred need lock should not block same-month advance.
      const isAdvanceDefault = !!(m.canApplyAdvance && m.advancePeriod > 0 && parseInt(defaultMonth, 10) === m.advancePeriod);

      let monthOptionsHtml = '';
      for (let i = 1; i <= totalMonths; i++) {
        if (m.monthPayouts && Object.keys(m.monthPayouts).length > 0 && !m.monthPayouts[i]) {
          continue;
        }

        // When chit need is set, only that applied period is selectable.
        if (needLocked && i !== m.preferredNeedMonth) {
          continue;
        }

        const isSelected = i === defaultMonth ? 'selected' : '';
        const payoutInfo = m.monthPayouts && m.monthPayouts[i] ? m.monthPayouts[i] : null;
        const formattedAmount = payoutInfo ? payoutInfo.formatted : m.estSettlement;
        const monthName = payoutInfo && payoutInfo.month_name ? payoutInfo.month_name : '';
        const monthLabel = monthName ? `Month ${i} — ${monthName}` : `Month ${i}`;

        monthOptionsHtml += `<option value="${i}" ${isSelected} data-amount-formatted="${formattedAmount}" data-month-name="${monthName}" data-month-label="${monthLabel}">${monthLabel} (${formattedAmount})</option>`;
      }

      // Fallback if preferred month was filtered out of schedule (e.g. foreman).
      if (!monthOptionsHtml) {
        for (let i = 1; i <= totalMonths; i++) {
          if (m.monthPayouts && Object.keys(m.monthPayouts).length > 0 && !m.monthPayouts[i]) {
            continue;
          }
          const isSelected = i === defaultMonth ? 'selected' : '';
          const payoutInfo = m.monthPayouts && m.monthPayouts[i] ? m.monthPayouts[i] : null;
          const formattedAmount = payoutInfo ? payoutInfo.formatted : m.estSettlement;
          const monthName = payoutInfo && payoutInfo.month_name ? payoutInfo.month_name : '';
          const monthLabel = monthName ? `Month ${i} — ${monthName}` : `Month ${i}`;
          monthOptionsHtml += `<option value="${i}" ${isSelected} data-amount-formatted="${formattedAmount}" data-month-name="${monthName}" data-month-label="${monthLabel}">${monthLabel} (${formattedAmount})</option>`;
        }
      }

      const defaultPayoutFormatted = m.monthPayouts && m.monthPayouts[defaultMonth]
        ? m.monthPayouts[defaultMonth].formatted
        : m.estSettlement;

      const defaultMonthName = m.monthPayouts && m.monthPayouts[defaultMonth]
        ? m.monthPayouts[defaultMonth].month_name
        : '';

      const defaultNeedDisplay = defaultMonthName
        ? `Month ${defaultMonth} — ${defaultMonthName}`
        : (m.needMonth && m.needMonth !== 'Not set' ? m.needMonth : `Month ${defaultMonth}`);

      const needMatchesNext = !m.preferredNeedMonth || parseInt(m.preferredNeedMonth, 10) === parseInt(m.nextSettlement, 10);
      const applyBlockedByNeed = needLocked && !needMatchesNext && !blocked;

      const bankInfoHtml = (m.bankName || m.accountNumber)
        ? `<div class="mt-2 pt-2 border-top small text-muted d-flex align-items-center justify-content-between flex-wrap gap-1">
            <div>
              <i class="ri-bank-line me-1 text-primary"></i><strong>Auto-fetched KYC Bank:</strong> ${m.bankName || 'Bank'} &bull; A/C: <code>${m.accountNumber || 'N/A'}</code> &bull; IFSC: <code>${m.ifscCode || 'N/A'}</code>
            </div>
            <span class="badge bg-label-success"><i class="ri-checkbox-circle-line me-1"></i>KYC Verified</span>
           </div>`
        : `<div class="mt-2 pt-2 border-top small text-muted"><i class="ri-information-line me-1"></i>No bank details saved in KYC. Staff can enter bank details during settlement release.</div>`;

      let warningHtml = '';
      if (blocked) {
        if (isPaid) {
          const viewUrl = m.existingId ? `${baseUrl}admin/chit/settlement-applications/${m.existingId}` : '';
          const viewLink = viewUrl ? `<a href="${viewUrl}" class="alert-link" target="_blank">View Settlement Review</a>` : '';

          warningHtml = `
            <div class="existing-app-warning alert alert-success d-flex align-items-start gap-2 mb-0 mt-2 py-2 px-3" role="alert">
              <i class="ri-checkbox-circle-line fs-5 flex-shrink-0 mt-1"></i>
              <div>
                <strong>Settlement Already Completed (Paid)</strong><br>
                <small>This member's chit settlement for Group <code>${m.groupCode}</code> has already been completed and marked as paid.
                ${m.existingCode ? `(Payout Code: <code>${m.existingCode}</code>)` : ''}
                ${viewLink ? '&nbsp;&mdash;&nbsp;' + viewLink : ''}
                </small>
              </div>
            </div>`;
        } else {
          const statusLabel = m.existingStatus.charAt(0).toUpperCase() + m.existingStatus.slice(1);
          const viewUrl = m.existingId ? `${baseUrl}admin/chit/settlement-applications/${m.existingId}` : '';
          const viewLink = viewUrl ? `<a href="${viewUrl}" class="alert-link" target="_blank">View &amp; Cancel Application</a>` : '';

          warningHtml = `
            <div class="existing-app-warning alert alert-warning d-flex align-items-start gap-2 mb-0 mt-2 py-2 px-3" role="alert">
              <i class="ri-error-warning-line fs-5 flex-shrink-0 mt-1"></i>
              <div>
                <strong>Application Already Exists</strong><br>
                <small>This member already has an active settlement application
                ${m.existingCode ? `(<code>${m.existingCode}</code>)` : ''} with status <span class="badge bg-warning text-dark">${statusLabel}</span>.
                Re-applying is only allowed after the current application is cancelled/rejected.
                ${viewLink ? '&nbsp;&mdash;&nbsp;' + viewLink : ''}
                </small>
              </div>
            </div>`;
        }
      } else if (applyBlockedByNeed) {
        warningHtml = `
          <div class="existing-app-warning alert alert-secondary d-flex align-items-start gap-2 mb-0 mt-2 py-2 px-3" role="alert">
            <i class="ri-lock-line fs-5 flex-shrink-0 mt-1"></i>
            <div>
              <strong>Settlement locked to applied need month</strong><br>
              <small>This member's applied chit need is <strong>Month ${m.preferredNeedMonth}</strong>.
              Apply is enabled when the group's next settlement month reaches that period
              (currently Month ${m.nextSettlement}).</small>
            </div>
          </div>`;
      } else if (m.canApplyAdvance) {
        warningHtml = `
          <div class="existing-app-warning alert alert-info d-flex align-items-start gap-2 mb-0 mt-2 py-2 px-3" role="alert">
            <i class="ri-flashlight-line fs-5 flex-shrink-0 mt-1"></i>
            <div>
              <strong>Same-month Advance Amount available</strong><br>
              <small>First settlement for Month ${m.advancePeriod} is already paid.
              This member will be marked as <strong>Advance Amount</strong> for the same month
              ${m.advancePeriodLabel ? `(${m.advancePeriodLabel})` : ''}.</small>
            </div>
          </div>`;
      }

      const disableApply = blocked || applyBlockedByNeed;
      const applyBtnDisabledAttr = disableApply ? 'disabled' : '';
      const applyBtnClass = disableApply ? 'btn-submit-group-settlement disabled' : 'btn-submit-group-settlement';
      const applyBtnText = blocked
        ? '<i class="ri-close-circle-line me-1"></i> Already Applied'
        : (applyBlockedByNeed
          ? `<i class="ri-lock-line me-1"></i> Locked until Month ${m.preferredNeedMonth}`
          : (isAdvanceDefault
            ? '<i class="ri-flashlight-line me-1"></i> Apply Advance Amount'
            : '<i class="ri-send-plane-line me-1"></i> Apply for Settlement'));
      const applyBtnTitle = blocked
        ? (isPaid ? 'Settlement already completed' : 'Cancel the existing application first')
        : (applyBlockedByNeed
          ? `Enabled when group reaches Month ${m.preferredNeedMonth}`
          : (isAdvanceDefault ? 'Same-month advance after original settlement' : ''));

      const seatBadge = m.enrollmentSuffix
        ? `<span class="badge bg-label-primary me-1">Seat ${m.enrollmentSuffix}</span>`
        : '';
      const shareBadge = Math.abs(m.sharePercentage - 100) > 0.001
        ? `<span class="badge bg-label-info me-1">${m.sharePercentageLabel} Share${m.sharePercentage > 100.001 ? ' (' + (Math.round(m.sharePercentage) / 100) + ' seats)' : ''}</span>`
        : '';
      const contributionBadge = m.isOutgoingTransferred
        ? `<span class="badge bg-label-danger me-1">Outgoing transfer</span><span class="badge bg-label-success me-1">Paid − foreman</span>`
        : (m.isCancelledWithdrawn
          ? `<span class="badge bg-label-secondary me-1">Cancelled</span><span class="badge bg-label-success me-1">Paid − foreman</span>`
          : '');

      const payoutKindSelectHtml = m.allowsAdvance && m.canApplyAdvance
        ? `<div class="col-md-3 col-6">
            <small class="text-muted d-block mb-1">Payout Type</small>
            <select class="form-select form-select-sm group-payout-kind fw-semibold" data-member-id="${m.id}" ${blocked ? 'disabled' : ''}>
              <option value="advance" selected>Advance Amount (same month)</option>
              <option value="original">Original Settlement</option>
            </select>
           </div>`
        : `<input type="hidden" class="group-payout-kind" value="${isAdvanceDefault ? 'advance' : 'original'}">`;

      html += `
        <div class="card border shadow-xs bg-white">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
              <div>
                <span class="badge bg-primary me-1">Group ${m.groupCode}</span>
                <strong class="text-dark me-2">${m.scheme}</strong>
                ${seatBadge}
                ${shareBadge}
                ${contributionBadge}
                <span class="badge bg-label-warning">Member #${m.memberNumber}</span>
                ${isAdvanceDefault ? '<span class="badge bg-label-info">Advance Amount</span>' : ''}
                <span class="text-muted small ms-1">${m.clientName}</span>
              </div>
              <span class="badge bg-label-info">Going Month: ${m.goingMonth} / ${totalMonths}</span>
            </div>

            <div class="row g-2 text-sm my-3 p-3 rounded" style="background-color: #f8f9fa;">
              <div class="col-md-3 col-6">
                <small class="text-muted d-block mb-1">Chit Value</small>
                <strong class="text-dark fs-6">${m.chitValue}</strong>
                ${Math.abs(m.sharePercentage - 100) > 0.001 ? `<div class="small text-muted">Share settlement uses ${m.sharePercentageLabel}</div>` : ''}
              </div>
              <div class="col-md-3 col-6">
                <small class="text-muted d-block mb-1">Select Settlement Month</small>
                <select class="form-select form-select-sm select-settlement-month fw-semibold text-primary" data-member-id="${m.id}" ${blocked ? 'disabled' : ''}>
                  ${monthOptionsHtml}
                </select>
              </div>
              <div class="col-md-3 col-6">
                <small class="text-muted d-block mb-1">Est. Settlement Payout</small>
                <strong class="text-success fs-6 est-payout-display" id="payout-display-${m.id}">${defaultPayoutFormatted}</strong>
                ${Math.abs(m.sharePercentage - 100) > 0.001 ? `<div class="small text-info">${m.sharePercentageLabel} of month payout</div>` : ''}
              </div>
              ${payoutKindSelectHtml}
              <div class="col-md-3 col-6">
                <small class="text-muted d-block mb-1">Chit Need Month</small>
                <strong class="text-warning fs-6" id="need-month-display-${m.id}">${defaultNeedDisplay}</strong>
              </div>
            </div>

            ${bankInfoHtml}
            ${warningHtml}

            <div class="d-flex justify-content-between align-items-center pt-2 border-top flex-wrap gap-2 mt-2">
              <button type="button" class="btn btn-sm ${isAdvanceDefault ? 'btn-info' : 'btn-primary'} ${applyBtnClass} px-3 ms-auto"
                      data-group-id="${m.groupId}"
                      data-member-id="${m.id}"
                      ${applyBtnDisabledAttr}
                      title="${applyBtnTitle}">
                ${applyBtnText}
              </button>
            </div>
          </div>
        </div>`;
    });

    $list.html(html);
    $container.show();
  } // end renderMemberGroupsList

  $(modalEl).on('shown.bs.modal', function () {
    cacheMemberOptions();
    initSelect2($('#settle_member_id'), 'Select member or client');

    const selectedMember = $('#settle_member_id').val();
    if (selectedMember) {
      renderMemberGroupsList(selectedMember);
    }
  });

  $(modalEl).on('hidden.bs.modal', function () {
    $('#settle_member_id').val(null).trigger('change.select2');
    $('#memberGroupsContainer').hide();
    $('#memberGroupsList').empty();
  });

  $('#settle_member_id').on('change', function () {
    const memberId = $(this).val();
    renderMemberGroupsList(memberId);
  });

  $(document).on('change', '.select-settlement-month', function () {
    const $select = $(this);
    const selectedOption = $select.find('option:selected');
    const payoutAmount = selectedOption.data('amount-formatted') || '₹0.00';
    const monthName = selectedOption.data('month-name') || '';
    const monthVal = $select.val();
    const memberId = $select.data('member-id');
    const card = $select.closest('.card');
    const member = allMemberOptions.find(m => String(m.id) === String(memberId));

    const needLabel = monthName ? `Month ${monthVal} — ${monthName}` : `Month ${monthVal}`;

    $(`#payout-display-${memberId}`).text(payoutAmount);
    $(`#need-month-display-${memberId}`).text(needLabel);

    const $kind = card.find('.group-payout-kind');
    if ($kind.length && member && member.canApplyAdvance && parseInt(monthVal, 10) === member.advancePeriod) {
      if ($kind.is('select')) {
        $kind.val('advance');
      } else {
        $kind.val('advance');
      }
      card.find('.btn-submit-group-settlement')
        .removeClass('btn-primary')
        .addClass('btn-info')
        .html('<i class="ri-flashlight-line me-1"></i> Apply Advance Amount');
    } else if ($kind.length) {
      if ($kind.is('select')) {
        $kind.val('original');
      } else {
        $kind.val('original');
      }
      card.find('.btn-submit-group-settlement')
        .removeClass('btn-info')
        .addClass('btn-primary')
        .html('<i class="ri-send-plane-line me-1"></i> Apply for Settlement');
    }
  });

  $(document).on('click', '.btn-submit-group-settlement', function () {
    const btn = $(this);

    // Guard: if button is disabled due to active application, do nothing
    if (btn.prop('disabled') || btn.hasClass('disabled') || btn.data('busy')) {
      return;
    }
    btn.data('busy', 1);

    const groupId = btn.data('group-id');
    const memberId = btn.data('member-id');
    const card = btn.closest('.card');
    const $payoutKindSelect = card.find('.group-payout-kind');
    const payoutKind = $payoutKindSelect.length ? ($payoutKindSelect.val() || 'original') : 'original';
    const selectedMonth = card.find('.select-settlement-month').val();

    $('#settle_group_id_hidden').val(groupId);
    $('#settle_member_id_hidden').val(memberId);
    $('#settle_payout_kind_hidden').val(payoutKind);
    $('#settle_month_number_hidden').val(selectedMonth || '');

    const origHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

    $.ajax({
      url: $('#formApplySettlement').attr('action'),
      type: 'POST',
      data: $('#formApplySettlement').serialize(),
      success: function (res) {
        if (res.success) {
          // Lock this seat immediately so a second apply needs reject first.
          allMemberOptions.forEach(m => {
            if (String(m.id) === String(memberId)) {
              m.existingStatus = 'pending';
              m.existingId = res.payout_id ? String(res.payout_id) : '';
              m.existingCode = '';
            }
          });
          if (Array.isArray(window.chitSettlementMembers)) {
            window.chitSettlementMembers.forEach(row => {
              if (String(row.id) === String(memberId)) {
                row.existing_application_status = 'pending';
                row.existing_application_id = res.payout_id || null;
              }
            });
          }

          if (modal) modal.hide();
          Swal.fire({
            icon: 'success',
            title: res.payout_kind_label
              ? (res.payout_kind_label + ' Requested')
              : ((res.payout_kind === 'advance') ? 'Advance Amount Requested' : 'Settlement Requested'),
            text: res.message,
            timer: 2000,
            showConfirmButton: false
          });
          if (dt) dt.ajax.reload();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: res.message });
        }
      },
      error: function (xhr) {
        const msg = xhr.responseJSON?.message || 'Failed to submit settlement application.';
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
      },
      complete: function () {
        btn.data('busy', 0).prop('disabled', false).html(origHtml);
      }
    });
  });
  // ── Reject (Cancel) Settlement Application ──────────────────────────────────
  $(document).on('click', '.btn-reject-settlement', function () {
    const btn    = $(this);
    const id     = btn.data('payout-id');
    const client = btn.data('client') || 'this member';
    const code   = btn.data('code')   || '';

    Swal.fire({
      title: 'Reject Application?',
      html: `Are you sure you want to <strong>reject</strong> the settlement application` +
            (code   ? ` <code>${code}</code>` : '') +
            (client ? ` for <strong>${client}</strong>` : '') +
            `?<br><small class="text-muted mt-1 d-block">The member will be allowed to re-apply after rejection.</small>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ff4d49',
      cancelButtonColor: '#6d788d',
      confirmButtonText: '<i class="ri-close-circle-line me-1"></i> Yes, Reject',
      cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary' },
      buttonsStyling: false,
    }).then(result => {
      if (!result.isConfirmed) return;

      btn.prop('disabled', true);

      $.ajax({
        url: `${baseUrl}admin/chit/settlements/${id}/cancel`,
        type: 'POST',
        data: { _token: csrfToken },
        success: function (res) {
          // Clear blocked state in the full membership cache (not just the unique <option> list).
          allMemberOptions.forEach(m => {
            if (String(m.existingId) === String(id)) {
              m.existingStatus = '';
              m.existingId = '';
              m.existingCode = '';
            }
          });

          if (Array.isArray(window.chitSettlementMembers)) {
            window.chitSettlementMembers.forEach(row => {
              if (String(row.existing_application_id || '') === String(id)) {
                row.existing_application_status = null;
                row.existing_application_id = null;
                row.existing_payout_code = null;
              }
            });
          }

          Swal.fire({
            icon: 'success',
            title: 'Application Rejected',
            text: 'The settlement application has been rejected. The member may now re-apply.',
            timer: 2500,
            showConfirmButton: false,
          });
          if (dt) dt.ajax.reload(null, false);

          const selectedMember = $('#settle_member_id').val();
          if (selectedMember) {
            renderMemberGroupsList(selectedMember);
          }
        },
        error: function (xhr) {
          const msg = xhr.responseJSON?.message || 'Failed to reject the application. Please try again.';
          Swal.fire({ icon: 'error', title: 'Error', text: msg });
          btn.prop('disabled', false);
        }
      });
    });
  });
});
