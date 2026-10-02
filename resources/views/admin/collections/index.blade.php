@extends('layouts/layoutMaster')

@section('title', 'EMI & Chit Collections')

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
    all: 'All Accounts',
    overdue: 'Overdue Accounts',
    pending: 'Pending Accounts',
    upcoming: 'Upcoming Accounts',
    partial: 'Partial Paid Accounts',
    paid: 'Paid Accounts'
  };
  const dueHeaders = {
    all: 'Total Due',
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

  const buildChitCommLinks = function (row, inst) {
    const phone = getCleanPhone(row.client_phone);
    if (!phone) return { whatsapp: null, sms: null };

    const clientName = row.client_name || 'Client';
    const groupCode = inst.group_code || row.account_number || 'Chit';
    const label = inst.period_label || inst.label || ('Month #' + (inst.month_number || ''));
    const memberNo = inst.member_number || row.member_number;
    const seatInfo = (memberNo && memberNo !== '—') ? ' (Member #' + memberNo + ')' : '';
    const dueDate = inst.due_date || '—';
    const amount = inst.balance_formatted || money(inst.balance || inst.amount || 0);
    const slogan = row.company_slogan || 'Finance';
    const compPhone = row.company_phone || '';
    const link = (row.public_url && row.public_url !== '#') ? row.public_url : (inst.url && inst.url !== '#' ? inst.url : '');

    const waLines = [
      'Dear *' + clientName + '*,',
      '',
      'Reminder for your chit *' + groupCode + '*' + seatInfo + ' (' + label + '):',
      '- Due Date: ' + dueDate,
      '- Balance Due: ' + amount
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
      'Reminder for your chit ' + groupCode + seatInfo + ' (' + label + '):',
      '- Due Date: ' + dueDate,
      '- Balance Due: ' + amount
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

      let actions = '';
      if (!menuItems) {
        actions = '<span class="text-muted">—</span>';
      } else {
        actions = '<div class="dropdown">' +
          '<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" id="' + uid + '">' +
          '<i class="icon-base ri ri-more-2-line icon-20px"></i></button>' +
          '<ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="' + uid + '">' + menuItems + '</ul></div>';
      }

      html += '<tr>';
      html += '<td class="fw-semibold">' + escapeHtml(emi.label || ('#' + emi.instalment_number)) + '</td>';
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

  const renderChitTable = function (items, row) {
    if (!items || !items.length) return '';
    let html = '<div class="table-responsive mb-0"><table class="table table-sm table-bordered align-middle mb-0">';
    html += '<thead class="table-light"><tr>';
    html += '<th>Period</th><th>Due Date</th><th>Amount</th><th>Penalty</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>';
    html += '</tr></thead><tbody>';

    items.forEach(function (inst) {
      const canPayInst = (inst.can_collect !== undefined) ? inst.can_collect : (inst.status !== 'paid' && parseFloat(inst.balance || 0) > 0.009);
      const scheduleUrl = inst.url || row.account_url || '#';
      const instComm = buildChitCommLinks(row, inst);
      const uid = 'sub-chit-' + String(inst.installment_id).replace(/[^a-zA-Z0-9_-]/g, '') + '-' + Math.random().toString(36).slice(2, 6);
      let menuItems = '';

      if (canPayInst && window.isAdminOrStaff) {
        menuItems += '<li><a class="dropdown-item btn-sub-chit-pay text-primary py-1" href="javascript:void(0)" data-pay-type="full" data-inst-id="' + escapeHtml(inst.installment_id) + '" data-account-id="' + escapeHtml(row.id) + '"><i class="ri-check-line me-2"></i>Full Pay (' + escapeHtml(inst.balance_formatted || money(inst.balance)) + ')</a></li>';
        menuItems += '<li><a class="dropdown-item btn-sub-chit-pay text-info py-1" href="javascript:void(0)" data-pay-type="partial" data-inst-id="' + escapeHtml(inst.installment_id) + '" data-account-id="' + escapeHtml(row.id) + '"><i class="ri-pie-chart-line me-2"></i>Partial Pay</a></li>';
      }

      if (instComm.whatsapp) {
        if (menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
        menuItems += '<li><a class="dropdown-item text-success py-1" href="' + escapeHtml(instComm.whatsapp) + '" target="_blank"><i class="ri-whatsapp-line me-2"></i>WhatsApp</a></li>';
      }
      if (instComm.sms) {
        if (!instComm.whatsapp && menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
        menuItems += '<li><a class="dropdown-item text-info py-1" href="' + escapeHtml(instComm.sms) + '"><i class="ri-message-3-line me-2"></i>SMS</a></li>';
      }

      let hasViewDivider = false;
      if (scheduleUrl && scheduleUrl !== '#') {
        if (menuItems) {
          menuItems += '<li><hr class="dropdown-divider my-1"></li>';
          hasViewDivider = true;
        }
        menuItems += '<li><a class="dropdown-item py-1" href="' + escapeHtml(scheduleUrl) + '" target="_blank"><i class="ri-file-list-3-line me-2"></i>View Schedule</a></li>';
      }
      if (inst.group_url && inst.group_url !== '#') {
        if (!hasViewDivider && menuItems) menuItems += '<li><hr class="dropdown-divider my-1"></li>';
        menuItems += '<li><a class="dropdown-item py-1" href="' + escapeHtml(inst.group_url) + '" target="_blank"><i class="ri-calendar-event-line me-2"></i>View Group Month</a></li>';
      }

      let actions = '';
      if (!menuItems) {
        actions = '<span class="text-muted">—</span>';
      } else {
        actions = '<div class="dropdown">' +
          '<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" id="' + uid + '">' +
          '<i class="icon-base ri ri-more-2-line icon-20px"></i></button>' +
          '<ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="' + uid + '">' + menuItems + '</ul></div>';
      }

      html += '<tr>';
      html += '<td class="fw-semibold">' + escapeHtml(inst.period_label || inst.label || '—') + '</td>';
      html += '<td>' + escapeHtml(inst.due_date || '—') + '</td>';
      html += '<td class="text-end">' + escapeHtml(inst.amount_formatted || money(inst.amount)) + '</td>';
      html += '<td class="text-end ' + ((inst.penalty_formatted && inst.penalty_formatted !== '—') ? 'text-danger' : 'text-muted') + '">' + escapeHtml(inst.penalty_formatted || '—') + '</td>';
      html += '<td class="text-end">' + escapeHtml(inst.paid_formatted || '—') + '</td>';
      html += '<td class="text-end fw-semibold">' + escapeHtml(inst.balance_formatted || '—') + '</td>';
      html += '<td class="text-center">' + (inst.status_badge || '') + '</td>';
      html += '<td class="text-center">' + actions + '</td>';
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
  };

  const formatDetails = function (row) {
    let html = '<div class="client-collection-details">';
    if (row.record_type === 'loan') {
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
    } else {
      const items = row.items || row.chit_items || [];
      html += '<div class="d-flex justify-content-between align-items-center mb-2">';
      html += '<h6 class="fw-semibold text-info mb-0"><i class="ri-group-line me-1"></i>Chit Installments — Group ' + escapeHtml(row.account_number) + ' (' + escapeHtml(row.member_number !== '—' ? 'Member #' + row.member_number : 'Seat') + ')</h6>';
      html += '</div>';

      const grouped = row.chit_items_grouped || {};
      let hasGrouped = false;
      statusSections.forEach(function (section) {
        const secItems = grouped[section.key] || [];
        if (secItems.length) {
          hasGrouped = true;
          html += '<div class="section-title text-' + section.color + '">' + section.label + ' (' + secItems.length + ')</div>';
          html += renderChitTable(secItems, row);
        }
      });
      if (!hasGrouped) {
        html += renderChitTable(items, row);
      }
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
          if (row.is_open_loan) {
            html += '<small class="text-muted mt-1">Remaining Principal: <strong class="text-dark">' + escapeHtml(row.principal_outstanding_formatted || '—') + '</strong></small>';
          } else if (row.record_type === 'chit' && row.member_number && row.member_number !== '—') {
            html += '<small class="text-muted mt-1">Member #' + escapeHtml(String(row.member_number)) + '</small>';
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

          if (!items) {
            return '<span class="text-muted">—</span>';
          }

          return '<div class="dropdown">' +
            '<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" id="' + uid + '">' +
            '<i class="icon-base ri ri-more-2-line icon-22px"></i></button>' +
            '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="' + uid + '">' + items + '</ul></div>';
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

  $('#clientCollectionsTable tbody').on('click', 'td.details-control', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass('shown');
      return;
    }
    row.child(formatDetails(row.data())).show();
    tr.addClass('shown');
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
    $('#tab-count-all').text(parseFloat(stats.total || 0).toLocaleString('en-IN'));
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
  let activePayTarget = null; // { row, item, type: 'loan_open' | 'loan_emi' | 'loan_account' | 'chit_installment' | 'chit_account' }

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
      type: isRowOpenLoan ? 'loan_open' : (row.record_type === 'loan' ? (item ? 'loan_emi' : 'loan_account') : (item ? 'chit_installment' : 'chit_account'))
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

  $(document).on('click', '.btn-sub-chit-pay', function (e) {
    e.preventDefault();
    const accountId = $(this).data('account-id');
    const instId = $(this).data('inst-id');
    const payType = $(this).data('pay-type') || 'full';
    const row = getRowById(accountId) || getParentRowData(this);
    if (!row) return;
    const inst = (row.items || row.chit_items || []).find(function (it) {
      return String(it.installment_id) === String(instId);
    });
    if (!inst) return;
    openSinglePayModal(row, payType, inst);
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
    } else if (targetType === 'chit_installment') {
      const amount = parseFloat($('#singlePayAmount').val() || 0);
      if (amount <= 0) {
        if (window.Swal) Swal.fire({ icon: 'warning', title: 'Enter amount', text: 'Payment amount is required.' });
        return;
      }

      payload.pay_type = $('input[name="single_pay_type"]:checked').val() || 'full';
      payload.paid_amount = amount.toFixed(2);
      payload.installment_id = item.installment_id;
      payload.client_id = item.client_id || row.client_id;
      payload.items = [{
        installment_id: item.installment_id,
        client_id: item.client_id || row.client_id,
        amount: amount
      }];
    } else {
      // chit_account
      const amount = parseFloat($('#singlePayAmount').val() || 0);
      if (amount <= 0) {
        if (window.Swal) Swal.fire({ icon: 'warning', title: 'Enter amount', text: 'Payment amount is required.' });
        return;
      }

      payload.pay_type = $('input[name="single_pay_type"]:checked').val() || 'full';
      payload.paid_amount = amount.toFixed(2);
      payload.items = (row.payable_chit_items || []).map(function (it) {
        return {
          installment_id: it.installment_id,
          client_id: it.client_id,
          amount: it.amount
        };
      });
    }

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
  <li class="nav-item">
    <button type="button" class="nav-link" data-module="chit"><i class="ri-group-line me-1"></i> Chit Installment</button>
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
            <small class="text-muted">EMI &amp; chit accounts</small>
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
            <i class="icon-base ri ri-file-list-3-line me-1_5 text-primary"></i>
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
        <i class="icon-base ri ri-file-list-3-line me-2 text-primary"></i>All Accounts
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
      Accounts are displayed separately per client (Loan accounts & Chit group seats). Expand a row to view specific EMIs or installments.
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
            <div id="singlePayAccountTitle" class="fw-semibold text-primary">Loan / Chit</div>
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

        <!-- Section for Standard Loans & Chits -->
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
          <small class="text-muted">Collect payment for multiple loan and chit accounts in one batch</small>
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
@endsection
