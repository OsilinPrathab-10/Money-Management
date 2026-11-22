@extends('layouts/layoutMaster')

@section('title', 'EMI Collections')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/custom-js/bank-payment-fields.js'
])
@endsection

@section('page-style')
<style>
  .card-datatable.table-responsive { overflow-x: auto !important; }
  .datatables-client-collections { width: 100% !important; margin: 0 !important; }
  .client-collection-details { background-color: #f8f9fa; padding: 1rem 1.25rem; }
  .client-collection-details .section-title {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.5rem;
  }
  .client-collection-details table { margin-bottom: 1rem; background: #fff; }
  td.details-control { cursor: pointer; text-align: center; vertical-align: middle; }
  td.details-control i { font-size: 1.25rem; color: #7367f0; transition: transform 0.2s ease; }
  tr.shown td.details-control i { transform: rotate(90deg); }

  #collectionsTabs { border-bottom: none; }
  #collectionsTabs .nav-item { margin-bottom: -1px; }
  #collectionsTabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1.25rem 1rem;
    font-weight: 500;
    color: #5d596c;
    transition: all 0.3s ease;
    border-radius: 0;
  }
  #collectionsTabs .nav-link:hover { color: #7367f0; background-color: rgba(115, 103, 240, 0.04); }
  #collectionsTabs .nav-link.active {
    color: #7367f0;
    border-bottom-color: #7367f0;
    background-color: transparent;
    font-weight: 600;
  }
  #collectionsTabs .nav-link.active .badge { transform: scale(1.05); }
  #collectionsTabs .badge { transition: all 0.3s ease; font-weight: 600; padding: 0.25em 0.6em; }
  #collectionsTabs .nav-link i { font-size: 1.2rem; vertical-align: middle; transition: transform 0.3s ease; }
  #collectionsTabs .nav-link:hover i { transform: translateY(-2px); }
  .datatables-client-collections .dropdown-menu { z-index: 1080; }
  .bulk-action-bar {
    background: #f0f2ff;
    border-bottom: 2px solid #7367f0;
  }
</style>
@endsection

@section('page-script')
<script>
window.clientCollectionsDataUrl = @json(route('client-collections.data'));
window.clientCollectionsCsrf = @json(csrf_token());
window.clientCollectionsPayUrl = @json(route('client-collections.pay'));
window.clientCollectionsBulkPayUrl = @json(route('client-collections.bulk-pay'));
window.clientCollectionsNotesUrl = @json(route('client-collections.notes'));
window.clientCollectionsSaveNoteUrl = @json(route('client-collections.notes.store'));
window.isAdminOrStaff = @json(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff') || auth()->user()->hasRole('Super Admin'));

window._bankPaymentGroupsQueue = window._bankPaymentGroupsQueue || [];
window._bankPaymentGroupsQueue.push({
  methodSelectId: 'clientPayMethod',
  bankSelectId: 'clientPayBankAccount',
  bankContainerId: 'clientPayBankWrap',
  bankDetailsCardId: 'clientPayBankDetailsCard',
  qrContainerId: 'clientPayQrContainer',
  qrBankNameId: 'clientPayQrBankName',
  qrUpiIdId: 'clientPayQrUpiId',
  qrImageWrapperId: 'clientPayQrImageWrapper',
  bankTransferContainerId: 'clientPayBankTransferContainer',
  bankTransferContentId: 'clientPayBankTransferContent'
});
window._bankPaymentGroupsQueue.push({
  methodSelectId: 'bulkPayMethod',
  bankSelectId: 'bulkPayBankAccount',
  bankContainerId: 'bulkPayBankWrap',
  bankDetailsCardId: 'bulkPayBankDetailsCard',
  qrContainerId: 'bulkPayQrContainer',
  qrBankNameId: 'bulkPayQrBankName',
  qrUpiIdId: 'bulkPayQrUpiId',
  qrImageWrapperId: 'bulkPayQrImageWrapper',
  bankTransferContainerId: 'bulkPayBankTransferContainer',
  bankTransferContentId: 'bulkPayBankTransferContent'
});
</script>
<script>
'use strict';
document.addEventListener('DOMContentLoaded', function () {
  const dataUrl = window.clientCollectionsDataUrl;
  let module = 'all';
  let status = 'all';
  const titles = {
    all: 'All Accounts (EMIs To Collect)',
    unpaid: 'EMIs To Collect',
    overdue: 'Overdue Accounts',
    pending: 'Pending Accounts',
    upcoming: 'Upcoming Accounts',
    partial: 'Partial Paid Accounts',
    paid: 'Paid Accounts'
  };
  const dueHeaders = {
    all: 'Collectible Due',
    unpaid: 'Collectible Due',
    overdue: 'Overdue Due',
    pending: 'Pending Due',
    upcoming: 'Upcoming Due',
    partial: 'Partial Due',
    paid: 'Total Paid'
  };

  const escapeHtml = function (value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  };

  const money = function (amount) {
    return '₹' + Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const statusSections = [
    { key: 'overdue', label: 'Overdue', color: 'danger' },
    { key: 'pending', label: 'Pending', color: 'warning' },
    { key: 'upcoming', label: 'Upcoming', color: 'secondary' },
    { key: 'partial', label: 'Partial Paid', color: 'info' },
    { key: 'paid', label: 'Paid', color: 'success' }
  ];

  // Selection Map for Bulk Pay: rowId => rowData
  const selectedRows = new Map();

  const updateBulkActionBar = function () {
    const count = selectedRows.size;
    let totalDue = 0;
    selectedRows.forEach(function (row) {
      totalDue += parseFloat(row.payable_amount || row.total_due || 0);
    });

    $('#bulkSelectedCount').text(count);
    $('#bulkSelectedTotal').text(money(totalDue));

    if (count > 0) {
      $('#bulkActionBar').removeClass('d-none');
    } else {
      $('#bulkActionBar').addClass('d-none');
    }
  };

  const getCleanPhone = function (phone) {
    if (!phone || phone === 'N/A') return '';
    let digits = String(phone).replace(/\D/g, '');
    if (!digits) return '';
    if (digits.length === 10) {
      digits = '91' + digits;
    }
    return digits;
  };

  const buildEmiCommLinks = function (row, emi) {
    const phone = getCleanPhone(row.client_phone);
    if (!phone) return { whatsapp: null, sms: null };

    const clientName = row.client_name || 'Client';
    const accNo = row.account_number || 'Loan';
    const label = emi.label || ('EMI #' + (emi.instalment_number || ''));
    const dueDate = emi.due_date || '—';
    const amount = emi.pending_amount_formatted || money(emi.pending_amount || emi.amount || 0);
    const slogan = row.company_slogan || 'Finance';
    const compPhone = row.company_phone || '';
    const link = (row.public_url && row.public_url !== '#') ? row.public_url : (emi.url && emi.url !== '#' ? emi.url : '');

    const waLines = [
      'Dear *' + clientName + '*,',
      '',
      'Reminder for your loan *' + accNo + '* (' + label + '):',
      '- Due Date: ' + dueDate,
      '- Amount Due: ' + amount
    ];
    if (link) {
      waLines.push('');
      waLines.push('View Details: ' + link);
    }
    waLines.push('');
    waLines.push('Thank you, ' + slogan + '.' + (compPhone ? ' Queries: ' + compPhone : ''));

    const smsLines = [
      'Dear ' + clientName + ',',
      '',
      'Reminder for your loan ' + accNo + ' (' + label + '):',
      '- Due Date: ' + dueDate,
      '- Amount Due: ' + amount
    ];
    if (link) {
      smsLines.push('');
      smsLines.push('View Details: ' + link);
    }
    smsLines.push('');
    smsLines.push('Thank you, ' + slogan + '.' + (compPhone ? ' Queries: ' + compPhone : ''));

    return {
      whatsapp: 'https://wa.me/' + phone + '?text=' + encodeURIComponent(waLines.join('\n')),
      sms: 'sms:+' + phone + '?body=' + encodeURIComponent(smsLines.join('\n'))
    };
  };


  const renderLoanTable = function (items, row) {
    if (!items || !items.length) return '';
    const isOpen = !!row.is_open_loan;
    let html = '<div class="table-responsive mb-3"><table class="table table-sm table-bordered align-middle mb-0">';
    html += '<thead class="table-light"><tr>';
    html += '<th>' + (isOpen ? 'Cycle #' : 'Inst. #') + '</th><th>Due Date</th><th>' + (isOpen ? 'Cycle Interest' : 'EMI Amt') + '</th>';
    if (!isOpen) {
      html += '<th>Interest</th><th>Principal</th>';
    } else {
      html += '<th>Principal Paid</th>';
    }
    html += '<th>Paid</th><th>Pending</th><th>Status</th><th>Actions</th>';
    html += '</tr></thead><tbody>';

    items.forEach(function (emi) {
      const scheduleUrl = emi.url || row.account_url || '#';
      const emiId = emi.raw_id || emi.id;
      const canPayItem = (emi.can_pay !== undefined) ? emi.can_pay : (emi.status !== 'paid' && (parseFloat(emi.pending_amount || 0) > 0.009 || isOpen));
      const emiComm = buildEmiCommLinks(row, emi);
      const uid = 'sub-emi-' + String(emiId).replace(/[^a-zA-Z0-9_-]/g, '') + '-' + Math.random().toString(36).slice(2, 6);
      let menuItems = '';

      if (canPayItem && window.isAdminOrStaff) {
        if (isOpen) {
          menuItems += '<li><a class="dropdown-item btn-sub-open-pay text-warning py-1" href="javascript:void(0)" data-emi-id="' + escapeHtml(emiId) + '" data-account-id="' + escapeHtml(row.id) + '"><i class="ri-fire-line me-2"></i>Pay Cycle</a></li>';
        } else {
          menuItems += '<li><a class="dropdown-item btn-sub-loan-pay text-primary py-1" href="javascript:void(0)" data-pay-type="full" data-emi-id="' + escapeHtml(emiId) + '" data-account-id="' + escapeHtml(row.id) + '"><i class="ri-check-line me-2"></i>Full Pay (' + escapeHtml(emi.pending_amount_formatted || money(emi.pending_amount)) + ')</a></li>';
          menuItems += '<li><a class="dropdown-item btn-sub-loan-pay text-info py-1" href="javascript:void(0)" data-pay-type="partial" data-emi-id="' + escapeHtml(emiId) + '" data-account-id="' + escapeHtml(row.id) + '"><i class="ri-pie-chart-line me-2"></i>Partial Pay</a></li>';
        }
      }

      if (emiComm.whatsapp) {
        if (menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
        menuItems += '<li><a class="dropdown-item text-success py-1" href="' + escapeHtml(emiComm.whatsapp) + '" target="_blank"><i class="ri-whatsapp-line me-2"></i>WhatsApp</a></li>';
      }
      if (emiComm.sms) {
        if (!emiComm.whatsapp && menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
        menuItems += '<li><a class="dropdown-item text-info py-1" href="' + escapeHtml(emiComm.sms) + '"><i class="ri-message-3-line me-2"></i>SMS</a></li>';
      }

      if (scheduleUrl && scheduleUrl !== '#') {
        if (menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
        menuItems += '<li><a class="dropdown-item py-1" href="' + escapeHtml(scheduleUrl) + '" target="_blank"><i class="ri-file-list-3-line me-2"></i>View Schedule</a></li>';
      }

      const emiPending = parseFloat(emi.pending_amount || 0);
      const isEmiZeroDue = (emi.status === 'paid') || (emiPending <= 0.009);

      if (menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
      menuItems += '<li><a class="dropdown-item btn-open-notes text-secondary py-1" href="javascript:void(0)" data-type="loan" data-id="' + escapeHtml(emiId) + '" data-emi-id="' + escapeHtml(emiId) + '" data-loan-account-id="' + escapeHtml(row.id ? String(row.id).replace('loan_', '') : '') + '" data-client-id="' + escapeHtml(row.client_id || '') + '" data-label="' + escapeHtml(emi.label || ('EMI #' + (emi.instalment_number || ''))) + '" data-account="' + escapeHtml(row.account_number) + '" data-client="' + escapeHtml(row.client_name) + '" data-due-zero="' + (isEmiZeroDue ? '1' : '0') + '"><i class="ri-calendar-event-line me-2 text-warning"></i>' + (isEmiZeroDue ? 'Notes History' : 'Notes & Reminder') + '</a></li>';

      let actions = '';
      if (!menuItems) {
        actions = '<span class="text-muted">—</span>';
      } else {
        const emiHeader = '<li class="dropdown-header px-3 py-2 border-bottom mb-1">' +
          '<div class="d-flex align-items-center gap-2">' +
          '<span class="avatar avatar-xs"><span class="avatar-initial rounded-circle bg-label-primary"><i class="ri-user-3-line"></i></span></span>' +
          '<div class="overflow-hidden">' +
          '<div class="fw-semibold text-truncate">' + escapeHtml(row.client_name || 'Client') + '</div>' +
          '<small class="text-muted text-truncate d-block">' + escapeHtml(emi.label || ('EMI #' + (emi.instalment_number || ''))) + '</small>' +
          '</div></div></li>';
        actions = '<div class="dropdown">' +
          '<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" id="' + uid + '">' +
          '<i class="icon-base ri ri-more-2-line icon-20px"></i></button>' +
          '<ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:15rem" aria-labelledby="' + uid + '">' + emiHeader + menuItems + '</ul></div>';
      }

      let reminderBadge = '';
      if (emi.reminder_date) {
        const isToday = !!emi.is_reminder_today;
        const isOverdue = !!emi.is_reminder_overdue;
        const badgeColor = isToday ? 'bg-warning text-dark' : (isOverdue ? 'bg-danger text-white' : 'bg-label-warning');
        const badgeIcon = isToday ? 'ri-alarm-warning-line' : 'ri-calendar-event-line';
        reminderBadge = '<div class="mt-1"><span class="badge ' + badgeColor + ' cursor-pointer btn-open-notes text-wrap text-start" data-type="loan" data-id="' + escapeHtml(emiId) + '" data-emi-id="' + escapeHtml(emiId) + '" data-loan-account-id="' + escapeHtml(row.id ? String(row.id).replace('loan_', '') : '') + '" data-client-id="' + escapeHtml(row.client_id || '') + '" data-label="' + escapeHtml(emi.label || ('EMI #' + (emi.instalment_number || ''))) + '" data-account="' + escapeHtml(row.account_number) + '" data-client="' + escapeHtml(row.client_name) + '" title="' + escapeHtml(emi.latest_note || 'Reminder set') + '"><i class="' + badgeIcon + ' me-1"></i>Reminder: ' + escapeHtml(emi.reminder_date) + '</span></div>';
      }

      html += '<tr>';
      html += '<td class="fw-semibold">' + escapeHtml(emi.label || ('#' + emi.instalment_number)) + reminderBadge + '</td>';
      html += '<td>' + escapeHtml(emi.due_date || '—') + '</td>';
      html += '<td>' + escapeHtml(emi.total_amount_formatted || money(emi.amount)) + '</td>';
      if (!isOpen) {
        html += '<td>' + escapeHtml(emi.interest_amount_formatted || '—') + '</td>';
        html += '<td>' + escapeHtml(emi.principal_amount_formatted || '—') + '</td>';
      } else {
        html += '<td>' + escapeHtml(emi.principal_amount_formatted || '—') + '</td>';
      }
      html += '<td>' + escapeHtml(emi.paid_formatted || '—') + '</td>';
      html += '<td>' + escapeHtml(emi.pending_amount_formatted || '—') + '</td>';
      html += '<td>' + (emi.status_badge || '') + '</td>';
      html += '<td class="text-nowrap text-center">' + actions + '</td>';
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
  };


  const formatDetails = function (row) {
    let html = '<div class="client-collection-details">';
    const items = row.items || row.loan_items || [];
    const isOpen = !!row.is_open_loan;
    html += '<div class="d-flex justify-content-between align-items-center mb-2">';
    html += '<h6 class="fw-semibold ' + (isOpen ? 'text-warning' : 'text-primary') + ' mb-0">';
    html += '<i class="' + (isOpen ? 'ri-fire-line' : 'ri-bank-line') + ' me-1"></i>';
    html += (isOpen ? 'Open Loan Cycle Repayments' : 'Loan EMI Repayments') + ' — ' + escapeHtml(row.account_number);
    html += '</h6>';
    if (isOpen) {
      html += '<span class="badge bg-label-warning">Remaining Principal: ' + escapeHtml(row.principal_outstanding_formatted) + '</span>';
    }
    html += '</div>';

    const grouped = row.loan_items_grouped || {};
    let hasGrouped = false;
    statusSections.forEach(function (section) {
      const secItems = grouped[section.key] || [];
      if (secItems.length) {
        hasGrouped = true;
        html += '<div class="section-title text-' + section.color + '">' + section.label + ' (' + secItems.length + ')</div>';
        html += renderLoanTable(secItems, row);
      }
    });
    if (!hasGrouped) {
      html += renderLoanTable(items, row);
    }
    html += '</div>';
    return html;
  };

  const table = $('#clientCollectionsTable').DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    scrollX: true,
    autoWidth: false,
    pageLength: 25,
    lengthMenu: [[25, 50, 100, 150], [25, 50, 100, 150]],
    ajax: {
      url: dataUrl,
      data: function (d) {
        d.module = module;
        d.status = status;
        d.from_date = $('#fromDateFilter').val() || '';
        d.to_date = $('#toDateFilter').val() || '';
        const searchVal = $('#collectionsSearchInput').val() || (d.search ? d.search.value : '');
        d.search_filter = searchVal;
        d.q = searchVal;
      }
    },
    columns: [
      {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center',
        render: function (data, type, row) {
          const checked = selectedRows.has(row.id) ? 'checked' : '';
          return '<input type="checkbox" class="form-check-input row-checkbox" data-id="' + escapeHtml(row.id) + '" ' + checked + '>';
        }
      },
      {
        className: 'details-control',
        orderable: false,
        searchable: false,
        data: null,
        defaultContent: '<i class="icon-base ri ri-arrow-right-s-line"></i>'
      },
      {
        data: 'sno',
        orderable: false,
        render: function (data, type, full, meta) {
          return meta.settings._iDisplayStart + meta.row + 1;
        }
      },
      {
        data: 'client_name',
        render: function (data, type, row) {
          let html = '<div class="d-flex flex-column">';
          html += '<a href="' + escapeHtml(row.client_url || '#') + '" class="text-primary fw-semibold">' + escapeHtml(data || 'Client') + '</a>';
          if (row.client_nickname) {
            html += '<small class="text-muted"><i class="ri-user-smile-line me-1"></i>' + escapeHtml(row.client_nickname) + '</small>';
          }
          if (row.client_phone && row.client_phone !== 'N/A') {
            html += '<a href="tel:' + escapeHtml(String(row.client_phone).replace(/\s/g, '')) + '" class="small text-muted mt-0_5">' + escapeHtml(row.client_phone) + '</a>';
          }
          html += '</div>';
          return html;
        }
      },
      {
        data: 'account_number',
        render: function (data, type, row) {
          let html = '<div class="d-flex flex-column align-items-start">';
          html += '<div class="d-flex align-items-center gap-1 flex-wrap">';
          html += (row.account_badge || '');
          html += '<a href="' + escapeHtml(row.account_url || '#') + '" class="fw-semibold text-primary ms-1">' + escapeHtml(data || '—') + '</a>';
          html += '</div>';
          if (row.reminder_date) {
            const isToday = !!row.is_reminder_today;
            const isOverdue = !!row.is_reminder_overdue;
            const badgeColor = isToday ? 'bg-warning text-dark' : (isOverdue ? 'bg-danger text-white' : 'bg-label-warning');
            const badgeIcon = isToday ? 'ri-alarm-warning-line' : 'ri-calendar-event-line';
            html += '<div class="mt-1"><span class="badge ' + badgeColor + ' cursor-pointer btn-open-notes text-wrap text-start" data-type="' + escapeHtml(row.record_type) + '" data-id="' + escapeHtml(row.id) + '" data-account="' + escapeHtml(row.account_number) + '" data-client="' + escapeHtml(row.client_name) + '" title="' + escapeHtml(row.latest_note || 'Reminder set') + '"><i class="' + badgeIcon + ' me-1"></i>Reminder: ' + escapeHtml(row.reminder_date) + '</span></div>';
          }
          if (row.is_open_loan) {
            html += '<small class="text-muted mt-1">Remaining Principal: <strong class="text-dark">' + escapeHtml(row.principal_outstanding_formatted || '—') + '</strong></small>';
          }
          html += '</div>';
          return html;
        }
      },
      {
        data: 'agent_name',
        render: function (data) {
          return '<span class="badge bg-label-info">' + escapeHtml(data || 'Unassigned') + '</span>';
        }
      },
      {
        data: 'zone',
        render: function (data) {
          return '<span class="badge bg-label-secondary">' + escapeHtml(data || 'N/A') + '</span>';
        }
      },
      {
        data: 'status_summary',
        orderable: false,
        searchable: false
      },
      {
        data: 'total_due_formatted',
        className: 'text-end',
        render: function (data, type, row) {
          let html = '<div class="d-flex flex-column align-items-end">';
          if (row.total_due > 0.009) {
            html += '<span class="fw-semibold text-danger">' + escapeHtml(data) + '</span>';
          } else {
            html += '<span class="text-muted">' + escapeHtml(data || '—') + '</span>';
          }
          if (row.is_open_loan) {
            html += '<small class="text-muted">Interest Due</small>';
          }
          html += '</div>';
          return html;
        }
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        render: function (data, type, row) {
          const uid = 'act-' + (row.id || Math.random().toString(36).slice(2, 7));
          let items = '';

          if (row.can_pay) {
            if (row.is_open_loan) {
              items += '<li><a class="dropdown-item btn-row-pay" href="javascript:void(0)" data-pay-type="open_loan"><i class="ri-wallet-3-line me-2 text-warning"></i>Pay Open Loan</a></li>';
            } else {
              items += '<li><a class="dropdown-item btn-row-pay" href="javascript:void(0)" data-pay-type="full"><i class="ri-wallet-3-line me-2 text-primary"></i>Full Pay</a></li>';
              items += '<li><a class="dropdown-item btn-row-pay" href="javascript:void(0)" data-pay-type="partial"><i class="ri-pie-chart-line me-2 text-info"></i>Partial Pay</a></li>';
            }
            items += '<li><hr class="dropdown-divider"></li>';
          }

          if (row.client_phone && row.client_phone !== 'N/A') {
            items += '<li><a class="dropdown-item" href="tel:' + escapeHtml(String(row.client_phone).replace(/\s/g, '')) + '"><i class="ri-phone-line me-2"></i>Call</a></li>';
          }
          if (row.whatsapp_url) {
            items += '<li><a class="dropdown-item text-success" href="' + escapeHtml(row.whatsapp_url) + '" target="_blank"><i class="ri-whatsapp-line me-2"></i>WhatsApp</a></li>';
          }
          if (row.sms_url) {
            items += '<li><a class="dropdown-item text-info" href="' + escapeHtml(row.sms_url) + '"><i class="ri-message-3-line me-2"></i>SMS</a></li>';
          }
          if (row.account_url && row.account_url !== '#') {
            items += '<li><a class="dropdown-item" href="' + escapeHtml(row.account_url) + '" target="_blank"><i class="ri-file-list-3-line me-2"></i>View Schedule</a></li>';
          }
          if (row.public_url && row.public_url !== '#') {
            items += '<li><hr class="dropdown-divider"></li>';
            items += '<li><a class="dropdown-item btn-copy-public-link" href="javascript:void(0)" data-link="' + escapeHtml(row.public_url) + '"><i class="ri-link me-2"></i>Copy Public Link</a></li>';
            items += '<li><a class="dropdown-item" href="' + escapeHtml(row.public_url) + '" target="_blank"><i class="ri-external-link-line me-2"></i>View Public Link</a></li>';
          }

          if (items) {
            items += '<li><hr class="dropdown-divider"></li>';
          }
          items += '<li><a class="dropdown-item btn-open-notes text-secondary" href="javascript:void(0)" data-type="' + escapeHtml(row.record_type) + '" data-id="' + escapeHtml(row.id) + '" data-account="' + escapeHtml(row.account_number) + '" data-client="' + escapeHtml(row.client_name) + '"><i class="ri-calendar-event-line me-2 text-warning"></i>Notes & Reminder</a></li>';

          if (!items) {
            return '<span class="text-muted">—</span>';
          }

          const menuHeader = '<li class="dropdown-header px-3 py-2 border-bottom mb-1">' +
            '<div class="d-flex align-items-center gap-2">' +
            '<span class="avatar avatar-xs"><span class="avatar-initial rounded-circle bg-label-primary"><i class="ri-user-3-line"></i></span></span>' +
            '<div class="overflow-hidden">' +
            '<div class="fw-semibold text-truncate">' + escapeHtml(row.client_name || 'Client') + '</div>' +
            '<small class="text-muted text-truncate d-block">' + escapeHtml(row.account_number || '') + '</small>' +
            '</div></div></li>';

          return '<div class="dropdown">' +
            '<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" id="' + uid + '">' +
            '<i class="icon-base ri ri-more-2-line icon-22px"></i></button>' +
            '<ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:15rem" aria-labelledby="' + uid + '">' + menuHeader + items + '</ul></div>';
        }
      }
    ],
    language: {
      sLengthMenu: 'Show _MENU_',
      search: '',
      searchPlaceholder: 'Search client, nickname, account...',
      info: 'Showing _START_ to _END_ of _TOTAL_ accounts',
      paginate: {
        next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
        previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
      }
    },
    dom:
      '<"card-header d-flex border-top rounded-0 flex-wrap justify-content-end py-2 px-4"' +
      '<"dt-action-buttons d-flex align-items-center gap-4"l>' +
      '>t' +
      '<"row mx-1"' +
      '<"col-sm-12 col-md-6"i>' +
      '<"col-sm-12 col-md-6"p>' +
      '>'
  });

  const openRowIds = new Set();

  $('#clientCollectionsTable tbody').on('click', 'td.details-control', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    const rowData = row.data();
    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass('shown');
      if (rowData) openRowIds.delete(rowData.id);
      return;
    }
    row.child(formatDetails(rowData)).show();
    tr.addClass('shown');
    if (rowData) openRowIds.add(rowData.id);
  });

  table.on('draw', function () {
    table.rows().every(function () {
      const d = this.data();
      if (d && openRowIds.has(d.id)) {
        this.child(formatDetails(d)).show();
        $(this.node()).addClass('shown');
      }
    });
  });

  // Checkbox Selection Logic
  $('#checkAllRows').on('change', function () {
    const checked = $(this).is(':checked');
    table.rows().every(function () {
      const data = this.data();
      if (!data) return;
      if (checked) {
        selectedRows.set(data.id, data);
      } else {
        selectedRows.delete(data.id);
      }
    });
    $('.row-checkbox').prop('checked', checked);
    updateBulkActionBar();
  });

  $(document).on('change', '.row-checkbox', function () {
    const id = $(this).data('id');
    const rowData = table.row($(this).closest('tr')).data();
    if (!rowData) return;

    if ($(this).is(':checked')) {
      selectedRows.set(id, rowData);
    } else {
      selectedRows.delete(id);
      $('#checkAllRows').prop('checked', false);
    }
    updateBulkActionBar();
  });

  $('#btnClearSelected').on('click', function () {
    selectedRows.clear();
    $('.row-checkbox, #checkAllRows').prop('checked', false);
    updateBulkActionBar();
  });

  table.on('xhr.dt', function (e, settings, json) {
    if (!json || !json.stats) return;
    const stats = json.stats;
    const fmt = function (v) {
      return '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    $('#stat-total').text(parseFloat(stats.total || 0).toLocaleString('en-IN'));
    $('#stat-paid').text(parseFloat(stats.paid || 0).toLocaleString('en-IN'));
    $('#stat-total-collected').text(fmt(stats.collected));
    $('#stat-pending').text(parseFloat(stats.pending || 0).toLocaleString('en-IN'));
    $('#stat-total-pending').text(fmt(stats.pending_amount));
    $('#stat-overdue').text(parseFloat(stats.overdue || 0).toLocaleString('en-IN'));
    const collectibleCount = (stats.collectible ?? stats.unpaid_accounts ?? stats.total) || 0;
    $('#tab-count-all').text(parseFloat(collectibleCount).toLocaleString('en-IN'));
    $('#tab-count-overdue').text(parseFloat(stats.overdue || 0).toLocaleString('en-IN'));
    $('#tab-count-pending').text(parseFloat(stats.pending || 0).toLocaleString('en-IN'));
    $('#tab-count-upcoming').text(parseFloat(stats.upcoming || 0).toLocaleString('en-IN'));
    $('#tab-count-partial').text(parseFloat(stats.partial || 0).toLocaleString('en-IN'));
    $('#tab-count-paid').text(parseFloat(stats.paid || 0).toLocaleString('en-IN'));
  });

  table.on('draw', function () {
    table.rows().every(function () {
      if (this.child.isShown()) {
        this.child.hide();
        $(this.node()).removeClass('shown');
      }
    });

    // Sync checkboxes on draw
    let allOnPageChecked = true;
    let anyRows = false;
    table.rows().every(function () {
      anyRows = true;
      const data = this.data();
      if (!data || !selectedRows.has(data.id)) {
        allOnPageChecked = false;
      }
    });
    $('#checkAllRows').prop('checked', anyRows && allOnPageChecked);
  });

  const setTitle = function () {
    $('#tableTitle').text(titles[status] || 'Accounts');
    $('#dueHeader').text(dueHeaders[status] || 'Total Due');
  };

  $('#collectionModuleTabs').on('click', 'button[data-module]', function () {
    $('#collectionModuleTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    module = $(this).data('module');
    table.ajax.reload();
  });

  $('#collectionsTabs button').on('click', function () {
    $('#collectionsTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    status = $(this).attr('data-status');
    $('#statusFilter').val(status);
    if (status === 'upcoming' && window.DateRangeFilter) {
      const root = document.querySelector('[data-date-range-filter]');
      if (root) {
        window.DateRangeFilter.applyPreset(root, 'all');
      }
      $('#fromDateFilter').val('');
      $('#toDateFilter').val('');
    }
    setTitle();
    table.ajax.reload();
  });

  document.addEventListener('date-range:change', function () {
    table.ajax.reload();
  });

  let searchTimer = null;
  $('#collectionsSearchInput').on('input keyup', function () {
    const val = $(this).val();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
      table.search(val).draw();
    }, 300);
  });

  $('#resetFilters').on('click', function () {
    if (window.DateRangeFilter) {
      const root = document.querySelector('[data-date-range-filter]');
      if (root) window.DateRangeFilter.applyPreset(root, 'all');
    }
    $('#fromDateFilter').val('');
    $('#toDateFilter').val('');
    $('#collectionsSearchInput').val('');
    table.search('').draw();
  });

  $(document).on('click', '.btn-copy-public-link', function (e) {
    e.preventDefault();
    const link = $(this).data('link');
    if (!link) return;
    const done = function () {
      if (window.Swal) Swal.fire({ icon: 'success', title: 'Copied', text: 'Public schedule link copied.', timer: 1600, showConfirmButton: false });
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(link).then(done).catch(function () { prompt('Copy this link:', link); });
    } else {
      prompt('Copy this link:', link);
    }
  });

  // -------------------------------------------------------------------------
  // SINGLE PAY MODAL LOGIC
  // -------------------------------------------------------------------------
  let activePayTarget = null; // { row, item, type: 'loan_open' | 'loan_emi' | 'loan_account' }

  const rowFromElement = function (el) {
    const child = $(el).closest('tr.child');
    if (child.length) {
      return table.row(child.prev('tr')).data();
    }
    return table.row($(el).closest('tr')).data();
  };

  const getRowById = function (accountId) {
    let found = null;
    table.rows().every(function () {
      const d = this.data();
      if (d && String(d.id) === String(accountId)) {
        found = d;
      }
    });
    return found;
  };

  const getParentRowData = function (el) {
    const trChild = $(el).closest('tr.child');
    if (trChild.length) {
      return table.row(trChild.prev('tr')).data();
    }
    const tableInside = $(el).closest('table');
    const directTr = tableInside.closest('tr');
    if (directTr.hasClass('child')) {
      return table.row(directTr.prev('tr')).data();
    }
    return rowFromElement(el);
  };

  const openSinglePayModal = function (row, payType, item) {
    if (!row) return;
    const isRowOpenLoan = !!row.is_open_loan;
    activePayTarget = {
      row: row,
      item: item || null,
      type: isRowOpenLoan ? 'loan_open' : (item ? 'loan_emi' : 'loan_account')
    };

    $('#singlePayClientName').text(row.client_name + (row.client_nickname ? ' (@' + row.client_nickname + ')' : ''));
    $('#singlePayDate').val(new Date().toISOString().slice(0, 10));
    $('#singlePayRemarks').val('');
    $('#singlePayMethod').val('in_hand').trigger('change');

    if (isRowOpenLoan) {
      $('#singlePayStandardSection').addClass('d-none');
      $('#singlePayOpenLoanSection').removeClass('d-none');

      let titleHtml = (row.account_badge || '') + ' ' + escapeHtml(row.account_number);
      if (item) {
        titleHtml += ' &bull; <span class="badge bg-label-warning">' + escapeHtml(item.label || ('Cycle #' + item.instalment_number)) + '</span>';
      }
      $('#singlePayAccountTitle').html(titleHtml);

      const cycleInterest = item ? parseFloat(item.pending_amount || 0) : parseFloat(row.payable_amount || row.total_due || 0);
      const principalRem = parseFloat(row.principal_outstanding || 0);

      $('#openLoanInterestDue').text(money(cycleInterest));
      $('#openLoanPrincipalRemaining').text(money(principalRem));

      $('#openLoanInterestInput').val(cycleInterest.toFixed(2));
      $('#openLoanPrincipalInput').val('0.00');

      $('input[name="open_loan_pay_mode"][value="interest"]').prop('checked', true);
      $('#openLoanInterestInput').prop('readonly', false);
      $('#openLoanPrincipalInput').prop('readonly', true);

      calculateOpenLoanTotal();
    } else {
      $('#singlePayOpenLoanSection').addClass('d-none');
      $('#singlePayStandardSection').removeClass('d-none');

      let titleHtml = (row.account_badge || '') + ' ';
      let payableAmt = 0;

      if (row.record_type === 'loan') {
        if (item) {
          titleHtml += escapeHtml(row.account_number) + ' &bull; <span class="badge bg-label-primary">' + escapeHtml(item.label || ('EMI #' + item.instalment_number)) + '</span>';
          payableAmt = parseFloat(item.pending_amount || item.amount || 0);
        } else {
          titleHtml += escapeHtml(row.account_number) + ' (EMI Loan)';
          payableAmt = parseFloat(row.payable_amount || row.total_due || 0);
        }
      } else {
        if (item) {
          titleHtml += 'Group ' + escapeHtml(row.account_number) + ' &bull; <span class="badge bg-label-info">' + escapeHtml(item.period_label || ('Month ' + item.month_number)) + '</span>';
          payableAmt = parseFloat(item.balance || item.amount || 0);
        } else {
          titleHtml += 'Group ' + escapeHtml(row.account_number) + ' (' + escapeHtml(row.member_number !== '—' ? 'Member #' + row.member_number : 'Seat') + ')';
          payableAmt = parseFloat(row.payable_amount || row.total_due || 0);
        }
      }

      $('#singlePayAccountTitle').html(titleHtml);
      $('#singlePayDueAmount').text(money(payableAmt)).data('amount', payableAmt);

      if (payType === 'partial') {
        $('input[name="single_pay_type"][value="partial"]').prop('checked', true);
        $('#singlePayAmount').val(payableAmt.toFixed(2)).prop('readonly', false);
      } else {
        $('input[name="single_pay_type"][value="full"]').prop('checked', true);
        $('#singlePayAmount').val(payableAmt.toFixed(2)).prop('readonly', true);
      }
    }

    const modalEl = document.getElementById('clientPayModal');
    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
      $(modalEl).modal('show');
    }
  };

  const calculateOpenLoanTotal = function () {
    const interest = parseFloat($('#openLoanInterestInput').val() || 0);
    const principal = parseFloat($('#openLoanPrincipalInput').val() || 0);
    const total = interest + principal;
    $('#openLoanTotalPreview').text(money(total));
  };

  $('input[name="open_loan_pay_mode"]').on('change', function () {
    const mode = $(this).val();
    let payableInterest = 0;
    if (activePayTarget) {
      if (activePayTarget.item) {
        payableInterest = parseFloat(activePayTarget.item.pending_amount || 0);
      } else if (activePayTarget.row) {
        payableInterest = parseFloat(activePayTarget.row.payable_amount || activePayTarget.row.total_due || 0);
      }
    }

    if (mode === 'interest') {
      $('#openLoanInterestInput').prop('readonly', false).val(payableInterest.toFixed(2));
      $('#openLoanPrincipalInput').prop('readonly', true).val('0.00');
    } else if (mode === 'principal') {
      $('#openLoanInterestInput').prop('readonly', true).val('0.00');
      $('#openLoanPrincipalInput').prop('readonly', false);
    } else if (mode === 'both') {
      $('#openLoanInterestInput').prop('readonly', false).val(payableInterest.toFixed(2));
      $('#openLoanPrincipalInput').prop('readonly', false);
    }
    calculateOpenLoanTotal();
  });

  $('#openLoanInterestInput, #openLoanPrincipalInput').on('input', calculateOpenLoanTotal);

  $('input[name="single_pay_type"]').on('change', function () {
    const type = $(this).val();
    const due = parseFloat($('#singlePayDueAmount').data('amount') || 0);
    if (type === 'full') {
      $('#singlePayAmount').val(due.toFixed(2)).prop('readonly', true);
    } else {
      $('#singlePayAmount').prop('readonly', false);
    }
  });

  $(document).on('click', '.btn-row-pay', function (e) {
    e.preventDefault();
    openSinglePayModal(rowFromElement(this), $(this).data('pay-type') || 'full', null);
  });

  $(document).on('click', '.btn-sub-loan-pay', function (e) {
    e.preventDefault();
    const accountId = $(this).data('account-id');
    const emiId = $(this).data('emi-id');
    const payType = $(this).data('pay-type') || 'full';
    const row = getRowById(accountId) || getParentRowData(this);
    if (!row) return;
    const emi = (row.items || row.loan_items || []).find(function (it) {
      return String(it.raw_id || it.id) === String(emiId);
    });
    if (!emi) return;
    openSinglePayModal(row, payType, emi);
  });

  $(document).on('click', '.btn-sub-open-pay', function (e) {
    e.preventDefault();
    const accountId = $(this).data('account-id');
    const emiId = $(this).data('emi-id');
    const row = getRowById(accountId) || getParentRowData(this);
    if (!row) return;
    const emi = (row.items || row.loan_items || []).find(function (it) {
      return String(it.raw_id || it.id) === String(emiId);
    });
    if (!emi) return;
    openSinglePayModal(row, 'open_loan', emi);
  });


  $('#clientPayForm').on('submit', function (e) {
    e.preventDefault();
    if (!activePayTarget || !activePayTarget.row) return;

    const row = activePayTarget.row;
    const item = activePayTarget.item;
    const targetType = activePayTarget.type;
    const isOpen = targetType === 'loan_open';
    const method = $('#clientPayMethod').val();
    const bankId = $('#clientPayBankAccount').val();
    const paidDate = $('#clientPayDate').val();
    const remarks = $('#clientPayRemarks').val();

    if ((method === 'upi' || method === 'bank_transfer') && !bankId) {
      if (window.Swal) Swal.fire({ icon: 'warning', title: 'Select bank', text: 'Choose a collection bank account.' });
      return;
    }

    let payload = {
      module: row.record_type,
      paid_date: paidDate,
      payment_method: method,
      remarks: remarks,
      internal_bank_account_id: bankId || null,
    };

    if (isOpen) {
      const interest = parseFloat($('#openLoanInterestInput').val() || 0);
      const principal = parseFloat($('#openLoanPrincipalInput').val() || 0);

      if (interest <= 0.009 && principal <= 0.009) {
        if (window.Swal) Swal.fire({ icon: 'warning', title: 'Enter amount', text: 'Please enter interest or principal repayment amount.' });
        return;
      }

      payload.pay_type = 'partial';
      payload.paid_amount = interest.toFixed(2);
      payload.principal_amount = principal.toFixed(2);
      payload.loan_account_id = row.record_id;
      if (item) {
        payload.emi_id = item.raw_id || item.id;
      }
    } else if (targetType === 'loan_emi') {
      const amount = parseFloat($('#singlePayAmount').val() || 0);
      if (amount <= 0) {
        if (window.Swal) Swal.fire({ icon: 'warning', title: 'Enter amount', text: 'Payment amount is required.' });
        return;
      }

      payload.pay_type = $('input[name="single_pay_type"]:checked').val() || 'full';
      payload.paid_amount = amount.toFixed(2);
      payload.loan_account_id = row.record_id;
      payload.emi_id = item.raw_id || item.id;
      payload.emi_ids = [item.raw_id || item.id];
    } else if (targetType === 'loan_account') {
      const amount = parseFloat($('#singlePayAmount').val() || 0);
      if (amount <= 0) {
        if (window.Swal) Swal.fire({ icon: 'warning', title: 'Enter amount', text: 'Payment amount is required.' });
        return;
      }

      payload.pay_type = $('input[name="single_pay_type"]:checked').val() || 'full';
      payload.paid_amount = amount.toFixed(2);
      payload.loan_account_id = row.record_id;
      payload.emi_ids = row.payable_loan_ids || [];
   
    const btn = $('#clientPaySubmit');
    btn.prop('disabled', true);

    fetch(window.clientCollectionsPayUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': window.clientCollectionsCsrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
      .then(function (res) {
        btn.prop('disabled', false);
        if (res.ok && res.json.success) {
          const modalEl = document.getElementById('clientPayModal');
          if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          else $(modalEl).modal('hide');

          selectedRows.delete(row.id);
          updateBulkActionBar();
          table.ajax.reload();

          if (window.Swal) {
            Swal.fire({ icon: 'success', title: 'Paid', text: res.json.message || 'Payment collected successfully.' });
          }
        } else {
          if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Payment Failed', text: res.json.message || 'Could not process payment.' });
          }
        }
      })
      .catch(function (err) {
        btn.prop('disabled', false);
        if (window.Swal) {
          Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Payment request failed.' });
        }
      });
  });

  // -------------------------------------------------------------------------
  // BULK PAY MODAL LOGIC
  // -------------------------------------------------------------------------
  const recalculateBulkModalTotal = function () {
    let grandTotal = 0;
    $('#bulkPayTableBody tr').each(function () {
      const interest = parseFloat($(this).find('.bulk-item-amount').val() || 0);
      const principal = parseFloat($(this).find('.bulk-item-principal').val() || 0);
      const rowTotal = interest + principal;
      $(this).find('.bulk-row-total').text(money(rowTotal));
      grandTotal += rowTotal;
    });
    $('#bulkModalNetTotal').text(money(grandTotal));
  };

  $('#btnOpenBulkPayModal').on('click', function () {
    if (selectedRows.size === 0) return;

    let rowsHtml = '';
    selectedRows.forEach(function (row, key) {
      const isOpen = !!row.is_open_loan;
      const due = parseFloat(row.payable_amount || row.total_due || 0);

      rowsHtml += '<tr data-row-id="' + escapeHtml(row.id) + '">';
      rowsHtml += '<td><div class="fw-semibold">' + escapeHtml(row.client_name) + '</div>';
      if (row.client_nickname) {
        rowsHtml += '<small class="text-muted">(@' + escapeHtml(row.client_nickname) + ')</small>';
      }
      rowsHtml += '</td>';

      rowsHtml += '<td>' + (row.account_badge || '') + ' <span class="ms-1 fw-medium">' + escapeHtml(row.account_number) + '</span></td>';

      rowsHtml += '<td>' + money(due);
      if (isOpen) {
        rowsHtml += '<div class="small text-muted">Prin: ' + escapeHtml(row.principal_outstanding_formatted) + '</div>';
      }
      rowsHtml += '</td>';

      rowsHtml += '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-item-amount" value="' + due.toFixed(2) + '"></td>';

      if (isOpen) {
        rowsHtml += '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm bulk-item-principal" value="0.00" placeholder="Optional"></td>';
      } else {
        rowsHtml += '<td><input type="text" class="form-control form-control-sm bg-light text-muted" value="—" readonly></td>';
      }

      rowsHtml += '<td class="fw-semibold text-end bulk-row-total">' + money(due) + '</td>';
      rowsHtml += '<td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-text-danger rounded-pill btn-remove-bulk-row" title="Remove"><i class="ri-delete-bin-line"></i></button></td>';
      rowsHtml += '</tr>';
    });

    $('#bulkPayTableBody').html(rowsHtml);
    $('#bulkPayDate').val(new Date().toISOString().slice(0, 10));
    $('#bulkPayRemarks').val('');
    $('#bulkPayMethod').val('in_hand').trigger('change');

    recalculateBulkModalTotal();

    const modalEl = document.getElementById('bulkPayModal');
    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
      $(modalEl).modal('show');
    }
  });

  $(document).on('input', '.bulk-item-amount, .bulk-item-principal', recalculateBulkModalTotal);

  $(document).on('click', '.btn-remove-bulk-row', function () {
    const tr = $(this).closest('tr');
    const rowId = tr.data('row-id');
    selectedRows.delete(rowId);
    $('input.row-checkbox[data-id="' + rowId + '"]').prop('checked', false);
    tr.remove();
    updateBulkActionBar();
    recalculateBulkModalTotal();

    if (selectedRows.size === 0) {
      const modalEl = document.getElementById('bulkPayModal');
      if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      else $(modalEl).modal('hide');
    }
  });

  $('#bulkPayForm').on('submit', function (e) {
    e.preventDefault();
    if (selectedRows.size === 0) return;

    const method = $('#bulkPayMethod').val();
    const bankId = $('#bulkPayBankAccount').val();
    const paidDate = $('#bulkPayDate').val();
    const remarks = $('#bulkPayRemarks').val();

    if ((method === 'upi' || method === 'bank_transfer') && !bankId) {
      if (window.Swal) Swal.fire({ icon: 'warning', title: 'Select bank', text: 'Choose a collection bank account.' });
      return;
    }

    const items = [];
    $('#bulkPayTableBody tr').each(function () {
      const rowId = $(this).data('row-id');
      const row = selectedRows.get(rowId);
      if (!row) return;

      const amount = parseFloat($(this).find('.bulk-item-amount').val() || 0);
      const principal = parseFloat($(this).find('.bulk-item-principal').val() || 0);

      items.push({
        record_type: row.record_type,
        account_id: row.record_id,
        client_id: row.client_id,
        amount: amount,
        principal_amount: principal
      });
    });

    if (items.length === 0) return;

    const btn = $('#bulkPaySubmit');
    btn.prop('disabled', true);

    fetch(window.clientCollectionsBulkPayUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': window.clientCollectionsCsrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        paid_date: paidDate,
        payment_method: method,
        internal_bank_account_id: bankId || null,
        remarks: remarks,
        items: items
      })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
      .then(function (res) {
        btn.prop('disabled', false);
        if (res.ok && res.json.success) {
          const modalEl = document.getElementById('bulkPayModal');
          if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          else $(modalEl).modal('hide');

          selectedRows.clear();
          $('.row-checkbox, #checkAllRows').prop('checked', false);
          updateBulkActionBar();
          table.ajax.reload();

          if (window.Swal) {
            Swal.fire({ icon: 'success', title: 'Bulk Payment Done', text: res.json.message });
          }
        } else {
          if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Bulk Payment Failed', text: res.json.message || 'Error occurred.' });
          }
        }
      })
      .catch(function () {
        btn.prop('disabled', false);
        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.' });
      });
  });

  // -------------------------------------------------------------------------
  // NOTES & REMINDER MODAL LOGIC
  // -------------------------------------------------------------------------
  const renderNotesHistory = function (notes) {
    $('#notesCountBadge').text(notes.length + ' Note' + (notes.length === 1 ? '' : 's'));
    if (!notes.length) {
      $('#notesHistoryList').html('<div class="text-center py-4 text-muted small"><i class="ri-chat-voice-line fs-3 d-block mb-1 opacity-50"></i>No previous notes recorded for this item.</div>');
      return;
    }

    const statusBadgeMap = {
      postponed: 'bg-label-warning',
      promise_to_pay: 'bg-label-primary',
      not_available: 'bg-label-danger',
      visit_scheduled: 'bg-label-info',
      dispute: 'bg-label-danger',
      general: 'bg-label-secondary'
    };

    let html = '<div class="timeline px-1">';
    notes.forEach(function (n) {
      const badgeCls = statusBadgeMap[n.status] || 'bg-label-secondary';
      const statusLabel = (n.status || 'Note').replace(/_/g, ' ').toUpperCase();
      let reminderInfo = '';
      if (n.reminder_date) {
        const isToday = n.is_today;
        const isOverdue = n.is_overdue;
        const rColor = isToday ? 'badge bg-warning text-dark' : (isOverdue ? 'badge bg-danger text-white' : 'badge bg-label-warning');
        reminderInfo = '<span class="' + rColor + ' ms-2"><i class="ri-calendar-event-line me-1"></i>Reminder: ' + escapeHtml(n.reminder_date_formatted || n.reminder_date) + '</span>';
      }

      html += '<div class="border rounded p-3 mb-2 bg-white shadow-none">';
      html += '<div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">';
      html += '<div><span class="badge ' + badgeCls + ' me-1">' + escapeHtml(statusLabel) + '</span>' + reminderInfo + '</div>';
      html += '<small class="text-muted">' + escapeHtml(n.created_at || '') + ' (' + escapeHtml(n.created_at_human || '') + ')</small>';
      html += '</div>';
      html += '<div class="text-dark my-2" style="white-space: pre-wrap; font-size: 0.9rem;">' + escapeHtml(n.note) + '</div>';
      html += '<div class="small text-muted"><i class="ri-user-line me-1"></i>Added by: <strong>' + escapeHtml(n.created_by || 'Admin') + '</strong></div>';
      html += '</div>';
    });
    html += '</div>';

    $('#notesHistoryList').html(html);
  };

  $(document).on('click', '.btn-open-notes', function (e) {
    e.preventDefault();
    e.stopPropagation();

    // Close any open dropdown menus so they don't block clicks or focus
    $('.dropdown-menu.show').removeClass('show');
    $('[data-bs-toggle="dropdown"][aria-expanded="true"]').attr('aria-expanded', 'false');

    // Ensure save button is enabled and reset to original label
    const btn = $('#btnSaveNote');
    btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i>Save Note & Reminder');

    const type = $(this).data('type') || 'loan';
    const rawId = String($(this).data('id') || '');
    let emiId = $(this).data('emi-id');
    let instId = $(this).data('installment-id');
    let loanAccId = $(this).data('loan-account-id');
    let memberId = $(this).data('group-member-id');
    let clientId = $(this).data('client-id');

    if (type === 'loan') {
      if (!emiId && rawId) {
        emiId = rawId.startsWith('loan_') ? rawId.replace('loan_', '') : rawId;
      }
    } else {
      if (!instId && rawId) {
        instId = rawId;
      }
    }

    const label = $(this).data('label') || '';
    const account = $(this).data('account') || '';
    const client = $(this).data('client') || '';

    // Set form hidden fields
    $('#noteFieldType').val(type);
    $('#noteFieldEmiId').val(emiId || '');
    $('#noteFieldInstallmentId').val(instId || '');
    $('#noteFieldLoanAccountId').val(loanAccId || '');
    $('#noteFieldGroupMemberId').val(memberId || '');
    $('#noteFieldClientId').val(clientId || '');

    // Set header labels
    $('#notesModalClientInfo').text((client || 'Client') + ' — ' + (account || 'Account'));
    $('#notesEntityTypeBadge').text(type.toUpperCase()).removeClass('bg-label-primary bg-label-info').addClass(type === 'loan' ? 'bg-label-primary' : 'bg-label-info');
    $('#notesEntityLabel').text(label || 'Loan Collection');
    $('#notesEntityDueDate').text('—');
    $('#notesEntityDueAmount').text('—');

    const isDueZeroInit = $(this).data('due-zero') === '1' || $(this).data('due-zero') === 1;

    // Reset input fields
    $('#noteText').val('');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowStr = tomorrow.toISOString().split('T')[0];

    if (isDueZeroInit) {
      $('#notesDueZeroWarning').removeClass('d-none').addClass('d-flex');
      $('#noteReminderDate').prop('disabled', true).val('');
      $('#noteReminderDateHelp').html('<span class="text-danger"><i class="ri-forbid-line me-1"></i>Reminder cannot be scheduled because the due amount is ₹0.00.</span>');
      $('#noteStatus').val('general');
    } else {
      $('#notesDueZeroWarning').addClass('d-none').removeClass('d-flex');
      $('#noteReminderDate').prop('disabled', false).val(tomorrowStr);
      $('#noteReminderDateHelp').html('<span class="text-muted">EMI will be highlighted for collection on this reminder date.</span>');
      $('#noteStatus').val('postponed');
    }

    $('#notesHistoryList').html('<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading notes...</div>');
    $('#notesCountBadge').text('Loading...');

    const modalEl = document.getElementById('collectionNotesModal');
    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
      $(modalEl).modal('show');
    }

    $.ajax({
      url: window.clientCollectionsNotesUrl,
      type: 'GET',
      data: {
        type: type,
        id: rawId,
        emi_id: emiId,
        installment_id: instId,
        loan_account_id: loanAccId,
        group_member_id: memberId,
        client_id: clientId
      },
      success: function (res) {
        if (!res.success) return;
        const entity = res.entity || {};
        if (entity.client_name) {
          $('#notesModalClientInfo').text(entity.client_name + ' — ' + (entity.account_number || account));
        }
        if (entity.label) {
          $('#notesEntityLabel').text(entity.label);
        }
        if (entity.due_date) {
          $('#notesEntityDueDate').text(entity.due_date);
        }
        if (entity.amount_due !== undefined) {
          $('#notesEntityDueAmount').text(money(entity.amount_due));
        }

        const amtDue = parseFloat(entity.amount_due !== undefined ? entity.amount_due : 0);
        const isCleared = (amtDue <= 0.009) || !!entity.is_paid;
        if (isCleared) {
          $('#notesDueZeroWarning').removeClass('d-none').addClass('d-flex');
          $('#noteReminderDate').prop('disabled', true).val('');
          $('#noteReminderDateHelp').html('<span class="text-danger"><i class="ri-forbid-line me-1"></i>Reminder cannot be scheduled because the due amount is ₹0.00.</span>');
          if ($('#noteStatus').val() === 'postponed') {
            $('#noteStatus').val('general');
          }
        } else {
          $('#notesDueZeroWarning').addClass('d-none').removeClass('d-flex');
          $('#noteReminderDate').prop('disabled', false);
          if (!$('#noteReminderDate').val()) {
            $('#noteReminderDate').val(tomorrowStr);
          }
          $('#noteReminderDateHelp').html('<span class="text-muted">EMI will be highlighted for collection on this reminder date.</span>');
        }

        renderNotesHistory(res.notes || []);
      },
      error: function () {
        $('#notesHistoryList').html('<div class="text-center py-4 text-danger small">Failed to load notes. Please try again.</div>');
        $('#notesCountBadge').text('0 Notes');
      }
    });
  });

  $('#collectionNotesModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });
    $('#btnSaveNote').prop('disabled', false).html('<i class="ri-save-line me-1"></i>Save Note & Reminder');
    $('#noteText').val('');
  });

  $('#collectionNotesForm').on('submit', function (e) {
    e.preventDefault();
    const btn = $('#btnSaveNote');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving...');

    const isReminderDisabled = $('#noteReminderDate').is(':disabled') || $('#notesDueZeroWarning').is(':visible');
    const reminderDateVal = isReminderDisabled ? null : ($('#noteReminderDate').val() || null);

    const payload = {
      _token: window.clientCollectionsCsrf,
      type: $('#noteFieldType').val(),
      emi_id: $('#noteFieldEmiId').val() || null,
      installment_id: $('#noteFieldInstallmentId').val() || null,
      loan_account_id: $('#noteFieldLoanAccountId').val() || null,
      group_member_id: $('#noteFieldGroupMemberId').val() || null,
      client_id: $('#noteFieldClientId').val() || null,
      note: $('#noteText').val(),
      reminder_date: reminderDateVal,
      status: $('#noteStatus').val()
    };

    $.ajax({
      url: window.clientCollectionsSaveNoteUrl,
      type: 'POST',
      data: payload,
      success: function (res) {
        btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i>Save Note & Reminder');
        if (res.success) {
          // Hide modal immediately so user can continue without stuck backdrop
          const modalEl = document.getElementById('collectionNotesModal');
          if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          } else {
            $(modalEl).modal('hide');
          }

          $('.modal-backdrop').remove();
          $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });

          if (window.Swal) {
            Swal.fire({
              icon: 'success',
              title: 'Saved',
              text: res.message || 'Note & reminder saved successfully.',
              timer: 1600,
              showConfirmButton: false,
              toast: true,
              position: 'top-end'
            });
          }
          $('#noteText').val('');

          // Reload collections table via AJAX — expanded rows will automatically stay open via openRowIds!
          table.ajax.reload(null, false);
        } else {
          if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to save note.' });
          }
        }
      },
      error: function (xhr) {
        btn.prop('disabled', false).html('<i class="ri-save-line me-1"></i>Save Note & Reminder');
        let errMsg = 'Failed to save note.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errMsg = xhr.responseJSON.message;
        }
        if (window.Swal) {
          Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
        }
      }
    });
  });
});
</script>
@endsection

@section('content')
<ul class="nav nav-pills mb-4 gap-2" id="collectionModuleTabs">
  <li class="nav-item">
    <button type="button" class="nav-link active" data-module="all"><i class="ri-list-check-2 me-1"></i> All</button>
  </li>
  <li class="nav-item">
    <button type="button" class="nav-link" data-module="loan"><i class="ri-bank-line me-1"></i> Loan EMI</button>
  </li>
</ul>

<div class="row g-4 mb-4">
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Total</p>
            <h4 class="mb-0" id="stat-total">0</h4>
            <small class="text-muted">Loan accounts</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="icon-base ri ri-file-list-3-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Paid</p>
            <h4 class="mb-0" id="stat-paid">0</h4>
            <small class="text-muted" id="stat-total-collected">₹0.00</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success">
              <i class="icon-base ri ri-checkbox-circle-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Pending (This Month)</p>
            <h4 class="mb-0" id="stat-pending">0</h4>
            <small class="text-muted" id="stat-total-pending">₹0.00</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-warning">
              <i class="icon-base ri ri-time-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Overdue</p>
            <h4 class="mb-0" id="stat-overdue">0</h4>
            <small class="text-muted">Needs attention</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-danger">
              <i class="icon-base ri ri-alarm-warning-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header p-0 border-bottom">
    <div class="nav-align-top">
      <ul class="nav nav-tabs nav-fill" role="tablist" id="collectionsTabs">
        <li class="nav-item">
          <button type="button" class="nav-link active" role="tab" data-status="all">
            <i class="icon-base ri ri-wallet-3-line me-1_5 text-primary"></i>
            All
            <span class="badge rounded-pill bg-primary ms-1" id="tab-count-all">0</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="overdue">
            <i class="icon-base ri ri-alarm-warning-line me-1_5 text-danger"></i>
            Overdue
            <span class="badge rounded-pill bg-danger ms-1" id="tab-count-overdue">0</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="pending">
            <i class="icon-base ri ri-time-line me-1_5 text-warning"></i>
            Pending
            <span class="badge rounded-pill bg-warning ms-1 text-dark" id="tab-count-pending">0</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="upcoming">
            <i class="icon-base ri ri-calendar-schedule-line me-1_5 text-secondary"></i>
            Upcoming
            <span class="badge rounded-pill bg-secondary ms-1" id="tab-count-upcoming">0</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="partial">
            <i class="icon-base ri ri-pie-chart-line me-1_5 text-info"></i>
            Partial Paid
            <span class="badge rounded-pill bg-info ms-1" id="tab-count-partial">0</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="paid">
            <i class="icon-base ri ri-checkbox-circle-line me-1_5 text-success"></i>
            Paid
            <span class="badge rounded-pill bg-success ms-1" id="tab-count-paid">0</span>
          </button>
        </li>
      </ul>
    </div>
  </div>

  <div class="card-body border-bottom py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
      <h5 class="mb-0 fw-semibold text-primary text-nowrap d-flex align-items-center" id="tableTitle">
        <i class="icon-base ri ri-wallet-3-line me-2 text-primary"></i>All Accounts (EMIs To Collect)
      </h5>
      <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-md-end">
        <input type="hidden" id="statusFilter" value="all" />
        <div class="position-relative" style="min-width: 240px; max-width: 320px;">
          <input type="search" id="collectionsSearchInput" class="form-control form-control-sm pe-4" placeholder="Search client, nickname, account, phone..." />
          <i class="icon-base ri ri-search-line position-absolute top-50 end-0 translate-middle-y me-2 text-muted" style="pointer-events: none;"></i>
        </div>
        @include('partials.date-range-filter', [
          'fromId' => 'fromDateFilter',
          'toId' => 'toDateFilter',
          'presetId' => 'clientCollectionsDatePreset',
          'presetValue' => 'all',
          'fromValue' => '',
          'toValue' => '',
        ])
        <button type="button" id="resetFilters" class="btn btn-sm btn-outline-secondary text-nowrap">
          <i class="icon-base ri ri-refresh-line me-1"></i>Reset
        </button>
      </div>
    </div>
  </div>

  <div class="card-datatable table-responsive">
    <div id="bulkActionBar" class="bulk-action-bar d-none px-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary rounded-pill px-2" id="bulkSelectedCount">0</span>
        <span class="fw-semibold text-primary">Accounts Selected</span>
        <span class="text-muted">|</span>
        <span>Total Selected Due: <strong id="bulkSelectedTotal" class="text-primary">₹0.00</strong></span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-primary" id="btnOpenBulkPayModal">
          <i class="ri-wallet-3-line me-1"></i>Bulk Pay Selected
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearSelected">
          Clear
        </button>
      </div>
    </div>

    <div class="px-4 pt-3 pb-1 text-muted small">
      Accounts are displayed separately per client (Loan accounts). Expand a row to view specific EMIs.
    </div>
    <table class="datatables-client-collections table table-hover" id="clientCollectionsTable">
      <thead>
        <tr>
          <th style="width: 25px;" class="text-center">
            <input type="checkbox" class="form-check-input" id="checkAllRows" title="Select all on this page">
          </th>
          <th style="width: 25px;"></th>
          <th>S.No</th>
          <th>Client</th>
          <th>Account / Type</th>
          <th>Agent</th>
          <th>Zone</th>
          <th>Status</th>
          <th class="text-end" id="dueHeader">Total Due</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- SINGLE PAY MODAL -->
<div class="modal fade" id="clientPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form id="clientPayForm" class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Collect Payment</h5>
          <small class="text-muted" id="singlePayClientName">Client</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-light border mb-3 d-flex align-items-center justify-content-between">
          <div>
            <small class="text-muted d-block">Account</small>
            <div id="singlePayAccountTitle" class="fw-semibold text-primary">Loan Account</div>
          </div>
        </div>

        <!-- Section for Open Loans (Kandhuvatti) -->
        <div id="singlePayOpenLoanSection" class="d-none">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="border rounded p-3 bg-light">
                <small class="text-muted d-block">Cycle Interest Due</small>
                <h5 class="mb-0 text-warning" id="openLoanInterestDue">₹0.00</h5>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 bg-light">
                <small class="text-muted d-block">Remaining Principal</small>
                <h5 class="mb-0 text-primary" id="openLoanPrincipalRemaining">₹0.00</h5>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold d-block">Payment Option</label>
            <div class="d-flex gap-3 flex-wrap">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="open_loan_pay_mode" id="openLoanModeInterest" value="interest" checked>
                <label class="form-check-label" for="openLoanModeInterest">Interest Only</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="open_loan_pay_mode" id="openLoanModePrincipal" value="principal">
                <label class="form-check-label" for="openLoanModePrincipal">Principal Repayment</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="open_loan_pay_mode" id="openLoanModeBoth" value="both">
                <label class="form-check-label" for="openLoanModeBoth">Both (Interest + Principal)</label>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="openLoanInterestInput">Cycle Interest Amount (₹)</label>
              <input type="number" step="0.01" min="0" id="openLoanInterestInput" class="form-control" value="0.00">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="openLoanPrincipalInput">Principal Repayment Amount (₹)</label>
              <input type="number" step="0.01" min="0" id="openLoanPrincipalInput" class="form-control" value="0.00" readonly>
            </div>
          </div>

          <div class="alert alert-primary py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
            <span>Total Payment to Collect:</span>
            <strong id="openLoanTotalPreview" class="fs-5">₹0.00</strong>
          </div>
        </div>

        <!-- Section for Standard Loans -->
        <div id="singlePayStandardSection">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="border rounded p-3 bg-light">
                <small class="text-muted d-block">Unpaid Due</small>
                <h5 class="mb-0 text-primary" id="singlePayDueAmount">₹0.00</h5>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold d-block">Pay type</label>
              <div class="d-flex gap-3 pt-2">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="single_pay_type" id="singlePayTypeFull" value="full" checked>
                  <label class="form-check-label" for="singlePayTypeFull">Full Pay</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="single_pay_type" id="singlePayTypePartial" value="partial">
                  <label class="form-check-label" for="singlePayTypePartial">Partial Pay</label>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="singlePayAmount">Amount (₹)</label>
            <input type="number" step="0.01" min="0.01" id="singlePayAmount" class="form-control" required readonly>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="clientPayDate">Payment Date</label>
            <input type="date" id="clientPayDate" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="clientPayMethod">Payment Method</label>
            <select id="clientPayMethod" class="form-select">
              <option value="in_hand" selected>Cash in hand</option>
              <option value="wallet">Customer Wallet</option>
              <option value="upi">UPI / GPay / QR</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="clientPayRemarks">Remarks</label>
          <input type="text" id="clientPayRemarks" class="form-control" placeholder="Optional notes">
        </div>

        @include('admin.partials.bank-collection-fields', [
          'bankAccounts' => $bankAccounts ?? [],
          'bankContainerId' => 'clientPayBankWrap',
          'bankSelectId' => 'clientPayBankAccount',
          'bankSelectName' => 'internal_bank_account_id',
          'bankDetailsCardId' => 'clientPayBankDetailsCard',
          'qrContainerId' => 'clientPayQrContainer',
          'qrBankNameId' => 'clientPayQrBankName',
          'qrUpiIdId' => 'clientPayQrUpiId',
          'qrImageWrapperId' => 'clientPayQrImageWrapper',
          'bankTransferContainerId' => 'clientPayBankTransferContainer',
          'bankTransferContentId' => 'clientPayBankTransferContent',
          'wrapperClass' => 'mb-0',
        ])
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success" id="clientPaySubmit">Confirm Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- BULK PAY MODAL -->
<div class="modal fade" id="bulkPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <form id="bulkPayForm" class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Bulk Payment Collection</h5>
          <small class="text-muted">Collect payment for multiple loan accounts in one batch</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive mb-3 border rounded">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Client</th>
                <th>Account / Type</th>
                <th>Unpaid Due</th>
                <th style="min-width: 140px;">Amount to Pay (₹)</th>
                <th style="min-width: 140px;">Principal Repay (Open Loan)</th>
                <th class="text-end">Total</th>
                <th style="width: 40px;"></th>
              </tr>
            </thead>
            <tbody id="bulkPayTableBody">
            </tbody>
          </table>
        </div>

        <div class="alert alert-primary p-3 mb-3 d-flex justify-content-between align-items-center">
          <div class="fw-semibold">Total Batch Amount to Collect:</div>
          <div class="fs-4 fw-bold text-primary" id="bulkModalNetTotal">₹0.00</div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="bulkPayDate">Payment Date</label>
            <input type="date" id="bulkPayDate" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="bulkPayMethod">Payment Method</label>
            <select id="bulkPayMethod" class="form-select">
              <option value="in_hand" selected>Cash in hand</option>
              <option value="wallet">Customer Wallet</option>
              <option value="upi">UPI / GPay / QR</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="bulkPayRemarks">Batch Remarks</label>
          <input type="text" id="bulkPayRemarks" class="form-control" placeholder="Optional notes for this batch">
        </div>

        @include('admin.partials.bank-collection-fields', [
          'bankAccounts' => $bankAccounts ?? [],
          'bankContainerId' => 'bulkPayBankWrap',
          'bankSelectId' => 'bulkPayBankAccount',
          'bankSelectName' => 'internal_bank_account_id',
          'bankDetailsCardId' => 'bulkPayBankDetailsCard',
          'qrContainerId' => 'bulkPayQrContainer',
          'qrBankNameId' => 'bulkPayQrBankName',
          'qrUpiIdId' => 'bulkPayQrUpiId',
          'qrImageWrapperId' => 'bulkPayQrImageWrapper',
          'bankTransferContainerId' => 'bulkPayBankTransferContainer',
          'bankTransferContentId' => 'bulkPayBankTransferContent',
          'wrapperClass' => 'mb-0',
        ])
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success" id="bulkPaySubmit">Confirm Bulk Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- NOTES & REMINDER MODAL -->
<div class="modal fade" id="collectionNotesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <div>
          <h5 class="modal-title mb-0 d-flex align-items-center gap-2">
            <i class="ri-sticky-note-line text-warning fs-4"></i>
            <span>Collection Notes &amp; Reminder</span>
          </h5>
          <small class="text-muted" id="notesModalClientInfo">Client — Account</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Entity Info Card -->
        <div class="alert alert-light border mb-4 py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <span class="badge bg-label-primary text-uppercase me-1" id="notesEntityTypeBadge">Loan</span>
            <span class="fw-semibold text-dark" id="notesEntityLabel">—</span>
          </div>
          <div class="d-flex align-items-center gap-3">
            <div><small class="text-muted">Due Date:</small> <strong id="notesEntityDueDate" class="text-dark">—</strong></div>
            <div><small class="text-muted">Due Amount:</small> <strong id="notesEntityDueAmount" class="text-danger">₹0.00</strong></div>
          </div>
        </div>

        <!-- Zero Due Warning Notice -->
        <div id="notesDueZeroWarning" class="alert alert-warning py-2 px-3 mb-3 d-none align-items-center gap-2">
          <i class="ri-alert-line fs-5 text-warning"></i>
          <div>
            <strong>Due amount is ₹0.00.</strong> Reminders cannot be scheduled for completed/cleared dues.
          </div>
        </div>

        <!-- Add New Note Form -->
        <form id="collectionNotesForm" class="border rounded p-3 mb-4 bg-light">
          <input type="hidden" id="noteFieldType" name="type" value="loan">
          <input type="hidden" id="noteFieldEmiId" name="emi_id" value="">
          <input type="hidden" id="noteFieldInstallmentId" name="installment_id" value="">
          <input type="hidden" id="noteFieldLoanAccountId" name="loan_account_id" value="">
          <input type="hidden" id="noteFieldGroupMemberId" name="group_member_id" value="">
          <input type="hidden" id="noteFieldClientId" name="client_id" value="">

          <div class="fw-semibold mb-2 text-primary d-flex align-items-center gap-1">
            <i class="ri-add-circle-line"></i> Add Collection Note / Postpone Reminder
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="noteText">Note / Remark <span class="text-danger">*</span></label>
            <textarea id="noteText" name="note" class="form-control" rows="2" placeholder="e.g. Client not available at shop, asked to collect tomorrow / postponed payment" required></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="noteReminderDate">
                <i class="ri-calendar-event-line text-warning me-1"></i>Reminder Follow-up Date
              </label>
              <input type="date" id="noteReminderDate" name="reminder_date" class="form-control" min="{{ date('Y-m-d') }}">
              <small class="text-muted" id="noteReminderDateHelp">EMI will be highlighted for collection on this reminder date.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="noteStatus">
                <i class="ri-flag-line text-info me-1"></i>Follow-up Status
              </label>
              <select id="noteStatus" name="status" class="form-select">
                <option value="postponed" selected>Postponed (Not Available / Asked Later)</option>
                <option value="promise_to_pay">Promise to Pay (PTP)</option>
                <option value="not_available">Client Not Available</option>
                <option value="visit_scheduled">Visit Scheduled</option>
                <option value="dispute">Payment Dispute</option>
                <option value="general">General Note</option>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-sm px-3" id="btnSaveNote">
              <i class="ri-save-line me-1"></i>Save Note &amp; Reminder
            </button>
          </div>
        </form>

        <!-- Notes History Section -->
        <div>
          <h6 class="fw-semibold text-secondary d-flex align-items-center justify-content-between mb-3">
            <span><i class="ri-history-line me-1"></i>Notes &amp; Follow-up History</span>
            <span class="badge bg-label-secondary" id="notesCountBadge">0 Notes</span>
          </h6>
          <div id="notesHistoryList" style="max-height: 280px; overflow-y: auto;">
            <div class="text-center py-4 text-muted small">No previous notes recorded.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection
