'use strict';

$(function () {
  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) baseUrl += '/';

  const csrfToken = $('meta[name="csrf-token"]').attr('content');
  const dtTable = $('.datatables-chit-applications');
  let dt = null;

  // Deep-link status from URL (same idea as loan applications)
  const urlParamsEarly = new URLSearchParams(window.location.search);
  const urlStatus = urlParamsEarly.get('status');
  if (urlStatus && ['applied', 'approved', 'active', 'rejected'].includes(urlStatus)) {
    $('#statusFilter').val(urlStatus);
  }

  if (dtTable.length) {
    dt = dtTable.DataTable({
      processing: true,
      serverSide: true,
      scrollX: true,
      autoWidth: false,
      ajax: {
        url: `${baseUrl}admin/chit/applications/data`,
        type: 'GET',
        data: function (d) {
          d.status = $('#statusFilter').val();
          d.group_id = $('#groupFilter').val();
          d.from_date = $('#fromDate').val();
          d.to_date = $('#toDate').val();
          d.search = d.search?.value || '';
        },
        dataSrc: function (json) {
          updateCounts(json);
          return json.data;
        }
      },
      columns: [
        {
          data: null,
          title: 'S.No',
          orderable: false,
          searchable: false,
          className: 'text-start',
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        {
          data: 'member_number',
          title: 'Member #',
          render: function (data) {
            return '<span class="fw-medium">#' + (data || '—') + '</span>';
          }
        },
        {
          data: 'client_name',
          title: 'Client Name',
          render: function (data) {
            return '<span class="fw-medium text-heading">' + (data || '—') + '</span>';
          }
        },
        {
          data: 'client_phone',
          title: 'Phone Number',
          render: function (data) {
            return '<span class="fw-medium">' + (data || '—') + '</span>';
          }
        },
        {
          data: 'group_code',
          title: 'Group',
          render: function (data) {
            return '<span class="badge bg-label-secondary">' + (data || '—') + '</span>';
          }
        },
        {
          data: 'scheme_name',
          title: 'Scheme Name',
          render: function (data) {
            return '<span class="fw-medium text-heading">' + (data || '—') + '</span>';
          }
        },
        {
          data: 'chit_value',
          title: 'Chit Value',
          className: 'text-end',
          render: function (data) {
            return '<span class="fw-medium">₹' + (data || '0') + '</span>';
          }
        },
        {
          data: 'collection_frequency',
          title: 'Frequency',
          className: 'text-center',
          render: function (data, type, full) {
            const freq = (data || 'monthly').toLowerCase();
            const label = full.collection_frequency_label || (freq.charAt(0).toUpperCase() + freq.slice(1));
            const badge = freq === 'daily' ? 'warning' : (freq === 'weekly' ? 'info' : 'secondary');
            let html = '<span class="badge bg-label-' + badge + '">' + label + '</span>';
            if ((freq === 'daily' || freq === 'weekly') && full.collection_split_amount) {
              html += '<div class="small text-muted mt-1">₹' + full.collection_split_amount + '/' + (freq === 'daily' ? 'day' : 'week') + '</div>';
            }
            return html;
          }
        },
        {
          data: 'status',
          title: 'Status',
          className: 'text-center',
          render: function (data, type, full) {
            const badges = { applied: 'warning', approved: 'info', rejected: 'danger', active: 'success' };
            const labels = { applied: 'Pending', approved: 'Approved', rejected: 'Rejected', active: 'Active' };
            const badge = badges[full.status] || 'secondary';
            const label = labels[full.status] || ((full.status || '').charAt(0).toUpperCase() + (full.status || '').slice(1));
            return '<span class="badge rounded-pill bg-label-' + badge + '">' + label + '</span>';
          }
        },
        {
          data: null,
          title: 'Actions',
          orderable: false,
          searchable: false,
          className: 'text-center text-nowrap',
          render: function (data, type, full) {
            var $status = (full.status || '').toLowerCase();
            var safeName = String(full.client_name || '')
              .replace(/&/g, '&amp;')
              .replace(/"/g, '&quot;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;');

            // Only View + Approve + Reject (Approve/Reject for applied only)
            var actionHtml = '<div class="d-flex align-items-center justify-content-center gap-2">' +
              `<button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill btn-view-app" data-id="${full.id}" title="View"><i class="icon-base ri ri-eye-line icon-22px"></i></button>`;

            if ($status === 'applied' && window.isAdmin) {
              actionHtml += `<button type="button" class="btn btn-icon btn-text-success btn-sm rounded-pill btn-approve-app" data-id="${full.id}" data-name="${safeName}" title="Approve"><i class="icon-base ri ri-checkbox-circle-line icon-22px"></i></button>`;
              actionHtml += `<button type="button" class="btn btn-icon btn-text-danger btn-sm rounded-pill btn-reject-app" data-id="${full.id}" data-name="${safeName}" title="Reject"><i class="icon-base ri ri-close-circle-line icon-22px"></i></button>`;
            }

            actionHtml += '</div>';
            return actionHtml;
          }
        }
      ],
      order: [[1, 'desc']],
      pageLength: 15,
      buttons: [],
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"B>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      language: {
        search: '',
        searchPlaceholder: 'Search Applications',
        emptyTable: 'No chit applications found',
        zeroRecords: 'No matching applications',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
          first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
          last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      columnDefs: [
        { targets: '_all', className: 'text-nowrap' },
        { targets: [2], width: '220px' },
        { targets: [5], width: '200px' }
      ]
    });

    $('#statusFilter, #groupFilter, #fromDate, #toDate').on('change', function () {
      dt.ajax.reload();
    });

    // Card filtering — same pattern as loan applications
    $('#card-total-applications').on('click', function () { $('#statusFilter').val('').trigger('change'); });
    $('#card-pending-applications').on('click', function () { $('#statusFilter').val('applied').trigger('change'); });
    $('#card-approved-applications').on('click', function () { $('#statusFilter').val('approved').trigger('change'); });
    $('#card-active-applications').on('click', function () { $('#statusFilter').val('active').trigger('change'); });
    $('#card-rejected-applications').on('click', function () { $('#statusFilter').val('rejected').trigger('change'); });

    $(window).on('resize', function () {
      dt.columns.adjust();
    });
  }

  function updateCounts(json) {
    if (json.counts) {
      $('#countApplied').text(json.counts.applied || 0);
      $('#countApproved').text(json.counts.approved || 0);
      $('#countActive').text(json.counts.active || 0);
      $('#countRejected').text(json.counts.rejected || 0);
      $('#countTotal').text(json.counts.total || 0);
    }
  }

  // ── View application popup ──────────────────────────────────────────────
  const viewModalEl = document.getElementById('modalViewChitApplication');
  const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;
  let viewingAppId = null;
  let viewingAppName = '';

  const statusBadges = { applied: 'warning', approved: 'info', rejected: 'danger', active: 'success' };
  const statusLabels = { applied: 'Pending', approved: 'Approved', rejected: 'Rejected', active: 'Active' };

  function fillViewModal(row) {
    viewingAppId = row.id;
    viewingAppName = row.client_name || '';

    const status = (row.status || '').toLowerCase();
    const badge = statusBadges[status] || 'secondary';
    const label = statusLabels[status] || status;

    $('#viewAppSubtitle').text((row.client_name || 'Client') + ' · ' + (row.group_code || ''));
    $('#viewAppStatus')
      .attr('class', 'badge rounded-pill fs-6 px-3 py-2 bg-label-' + badge)
      .text(label);
    $('#viewAppAppliedAt').text(row.applied_at || '—');
    $('#viewAppClientName').text(row.client_name || '—');
    $('#viewAppClientPhone').text(row.client_phone || '—');
    $('#viewAppClientEmail').text(row.client_email || '—');
    $('#viewAppAgent').text(row.assigned_agent || '—');
    $('#viewAppGroup').text(row.group_code || '—');
    $('#viewAppScheme').text(row.scheme_name || '—');
    $('#viewAppMemberNo').text('#' + (row.member_number || '—'));
    $('#viewAppChitValue').text('₹' + (row.chit_value || '0'));
    $('#viewAppInstallment').text('₹' + (row.installment || '0'));
    const freqLabel = row.collection_frequency_label || 'Monthly';
    const freq = (row.collection_frequency || 'monthly').toLowerCase();
    let freqText = freqLabel;
    if ((freq === 'daily' || freq === 'weekly') && row.collection_split_amount) {
      freqText += ' (₹' + row.collection_split_amount + '/' + (freq === 'daily' ? 'day' : 'week') + ')';
    }
    $('#viewAppFrequency').text(freqText);
    $('#viewAppDuration').text((row.total_months || '—') + ' Months');
    $('#viewAppNeedMonth').text(row.chit_need_month || 'Not set');
    $('#viewAppGroupStatus').text((row.group_status || '—').toString().charAt(0).toUpperCase() + (row.group_status || '').toString().slice(1));

    if (row.remarks) {
      $('#viewAppRemarks').text(row.remarks);
      $('#viewAppRemarksWrap').removeClass('d-none');
    } else {
      $('#viewAppRemarksWrap').addClass('d-none');
    }

    // Approve / Reject only for applied applications (Admin/Staff)
    if (status === 'applied' && window.isAdmin) {
      $('#viewAppBtnApprove, #viewAppBtnReject').removeClass('d-none');
    } else if ((status === 'approved') && window.isAdmin) {
      $('#viewAppBtnApprove').addClass('d-none');
      $('#viewAppBtnReject').removeClass('d-none');
    } else {
      $('#viewAppBtnApprove, #viewAppBtnReject').addClass('d-none');
    }
  }

  $(document).on('click', '.btn-view-app', function () {
    if (!dt) return;
    const id = $(this).data('id');
    const row = dt.rows().data().toArray().find(r => String(r.id) === String(id));
    if (!row) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Application details not found.' });
      return;
    }
    fillViewModal(row);
    if (viewModal) viewModal.show();
  });

  $('#viewAppBtnApprove').on('click', function () {
    if (!viewingAppId) return;
    if (viewModal) viewModal.hide();
    runApprove(viewingAppId, viewingAppName);
  });

  $('#viewAppBtnReject').on('click', function () {
    if (!viewingAppId) return;
    if (viewModal) viewModal.hide();
    runReject(viewingAppId, viewingAppName);
  });

  const chitActionLocks = new Set();

  function runApprove(id, name) {
    if (chitActionLocks.has('approve-' + id)) return;
    chitActionLocks.add('approve-' + id);
    Swal.fire({
      title: 'Approve Application?',
      html: `Are you sure you want to approve <strong>${name}</strong>'s chit application?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Approve',
      cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-outline-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (!result.isConfirmed) {
        chitActionLocks.delete('approve-' + id);
        return;
      }
      $.ajax({
        url: `${baseUrl}admin/chit/applications/${id}/approve`,
        type: 'POST',
        data: { _token: csrfToken },
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function (res) {
          Swal.fire({
            icon: 'success',
            title: 'Approved!',
            text: res.message || `${name}'s application has been approved.`,
            timer: 2000,
            showConfirmButton: false
          });
          if (dt) dt.ajax.reload(null, false);
        },
        error: function (xhr) {
          chitActionLocks.delete('approve-' + id);
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to approve.' });
        }
      });
    });
  }

  function runReject(id, name) {
    if (chitActionLocks.has('reject-' + id)) return;
    chitActionLocks.add('reject-' + id);
    Swal.fire({
      title: 'Reject Application?',
      html: `Are you sure you want to reject <strong>${name}</strong>'s chit application?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Reject',
      cancelButtonText: 'Cancel',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-outline-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (!result.isConfirmed) {
        chitActionLocks.delete('reject-' + id);
        return;
      }
      $.ajax({
        url: `${baseUrl}admin/chit/applications/${id}/reject`,
        type: 'POST',
        data: { _token: csrfToken },
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function (res) {
          Swal.fire({
            icon: 'success',
            title: 'Rejected',
            text: res.message || `${name}'s application has been rejected.`,
            timer: 2000,
            showConfirmButton: false
          });
          if (dt) dt.ajax.reload(null, false);
        },
        error: function (xhr) {
          chitActionLocks.delete('reject-' + id);
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to reject.' });
        }
      });
    });
  }

  // Apply for Chit Modal
  const modalEl = document.getElementById('modalApplyChit');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

  function chitGroupAttr($option, name) {
    if (!$option || !$option.length) return '';
    const raw = $option.attr('data-' + name);
    if (raw !== undefined && raw !== null && raw !== '') return raw;
    return $option.data(name) ?? '';
  }

  function resetChitGroupDetails() {
    $('#infoGroupTitle').text('Select a chit group to see details');
    $('#infoStatus').text('—').removeClass('bg-label-success bg-label-warning bg-label-primary').addClass('bg-label-secondary');
    $('#infoChitValue, #infoInstallment, #infoSettlementAmount, #infoMembers, #infoDuration, #infoStartDate, #infoEndDate, #infoFrequency').text('—');
    $('#collectionSplitPreview').hide();
  }

  function updateCollectionSplitPreview() {
    const selected = $('#chit_group_id').find(':selected');
    if (!selected.val()) {
      resetChitGroupDetails();
      return;
    }

    const fmt = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const monthly = Number(chitGroupAttr(selected, 'installment') || 0);
    const groupCode = chitGroupAttr(selected, 'group-code') || selected.text().trim();
    const schemeName = chitGroupAttr(selected, 'scheme-name') || '';
    const status = String(chitGroupAttr(selected, 'status') || '—');
    const vacancy = chitGroupAttr(selected, 'vacancy');
    const members = chitGroupAttr(selected, 'members') || '—';
    const currentMonth = chitGroupAttr(selected, 'current-month') || '0';
    const totalMonths = chitGroupAttr(selected, 'total-months') || '—';
    const frequency = chitGroupAttr(selected, 'frequency') || 'monthly';

    $('#infoGroupTitle').text(schemeName ? (groupCode + ' — ' + schemeName) : groupCode);
    $('#infoStatus')
      .text(status)
      .removeClass('bg-label-secondary bg-label-success bg-label-warning bg-label-primary')
      .addClass(status.toLowerCase() === 'active' ? 'bg-label-success' : (status.toLowerCase() === 'forming' ? 'bg-label-warning' : 'bg-label-primary'));
    $('#infoChitValue').text(fmt(chitGroupAttr(selected, 'chit-value')));
    $('#infoInstallment').text(fmt(monthly));
    $('#infoSettlementAmount').text(fmt(chitGroupAttr(selected, 'settlement-amount')));
    $('#infoMembers').text(vacancy !== '' ? (members + ' · ' + vacancy + ' open') : members);
    $('#infoDuration').text('Month ' + currentMonth + ' / ' + totalMonths);
    $('#infoStartDate').text(chitGroupAttr(selected, 'start-label') || '—');
    $('#infoEndDate').text(chitGroupAttr(selected, 'end-label') || '—');
    $('#infoFrequency').text(frequency);

    const form = document.getElementById('formApplyChit');
    const block = form?.querySelector('.chit-need-month-block');
    if (block) {
      block.setAttribute('data-group-start', selected.attr('data-start-date') || '');
      block.setAttribute('data-total-months', selected.attr('data-total-months') || '');
    }

    const freq = ($('#chit_collection_frequency').val() || 'monthly').toLowerCase();
    const parts = freq === 'daily' ? 30 : (freq === 'weekly' ? 4 : 1);
    if (parts > 1 && monthly > 0) {
      const split = monthly / parts;
      $('#splitMonthlyAmount').text(fmt(monthly));
      $('#splitFrequencyLabel').text(freq === 'daily' ? 'Daily' : 'Weekly');
      $('#splitPeriodAmount').text(fmt(split));
      $('#splitPartsHint').text(freq === 'daily' ? '30 days' : '4 weeks');
      $('#collectionSplitPreview').slideDown(150);
    } else {
      $('#collectionSplitPreview').hide();
    }
  }

  $('#btnApplyChit').on('click', function () {
    if (modal) modal.show();
  });

  $(modalEl).on('shown.bs.modal', function () {
    const $modal = $(this);
    const $freq = $('#chit_collection_frequency');
    if (!$freq.val()) {
      $freq.val('monthly');
    }
    $('.select2', $modal).each(function () {
      const $el = $(this);
      if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
      $el.select2({
        dropdownParent: $modal.find('.modal-content'),
        width: '100%',
        placeholder: $el.data('placeholder') || 'Select',
        allowClear: $el.attr('id') !== 'chit_collection_frequency',
        minimumResultsForSearch: $el.attr('id') === 'chit_collection_frequency' ? Infinity : 0
      });
    });
    $freq.trigger('change.select2');
    updateCollectionSplitPreview();
  });

  $(document).on('change select2:select select2:clear', '#chit_group_id, #chit_collection_frequency', function () {
    updateCollectionSplitPreview();
  });

  $('#formApplyChit').on('submit', function (e) {
    e.preventDefault();

    const $freq = $('#chit_collection_frequency');
    if (!['monthly', 'weekly', 'daily'].includes(($freq.val() || '').toLowerCase())) {
      $freq.val('monthly').trigger('change');
    }

    const btn = $('#btnSubmitChitApp');
    const origHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

    $.ajax({
      url: `${baseUrl}admin/chit/applications/apply`,
      type: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        if (res.success) {
          if (modal) modal.hide();
          $('#formApplyChit')[0].reset();
          $('#chit_collection_frequency').val('monthly').trigger('change');
          $('.select2', '#modalApplyChit').val(null).trigger('change');
          $('#chit_collection_frequency').val('monthly').trigger('change');
          resetChitGroupDetails();
          $('#collectionSplitPreview').hide();

          Swal.fire({ icon: 'success', title: 'Success', text: res.message, timer: 2500, showConfirmButton: false });
          if (dt) dt.ajax.reload();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: res.message });
        }
      },
      error: function (xhr) {
        const msg = xhr.responseJSON?.message || 'Something went wrong. Please try again.';
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
      },
      complete: function () {
        btn.prop('disabled', false).html(origHtml);
      }
    });
  });

  $(document).on('click', '.btn-approve-app', function () {
    if (!window.isAdmin) {
      Swal.fire({ icon: 'warning', title: 'Not allowed', text: 'Only administrators and staff can approve chit applications.' });
      return;
    }
    runApprove($(this).data('id'), $(this).data('name'));
  });

  $(document).on('click', '.btn-reject-app', function () {
    if (!window.isAdmin) {
      Swal.fire({ icon: 'warning', title: 'Not allowed', text: 'Only administrators and staff can reject chit applications.' });
      return;
    }
    runReject($(this).data('id'), $(this).data('name'));
  });
});
