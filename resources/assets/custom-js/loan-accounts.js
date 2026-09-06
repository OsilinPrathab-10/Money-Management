/**
 * Loan Accounts Page JavaScript
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const $loanAccountsTable = $('#loanAccountsTable');
  const $statusFilter = $('#statusFilter');
  const baseUrl = window.baseUrl || document.documentElement.getAttribute('data-base-url') + '/' || window.location.origin + '/';

  if (!$loanAccountsTable.length) {
    return;
  }

  const loanAccountsTable = $loanAccountsTable.DataTable({
    processing: true,
    serverSide: true,
    scrollX: true,
    autoWidth: false,
    ajax: {
      url: baseUrl + 'loan/loan-accounts/data',
      type: 'GET',
      data: function (d) {
        d.status = $statusFilter.val();
        d.from_date = $('#fromDate').val();
        d.to_date = $('#toDate').val();
      }
    },
    columns: [
      { 
        data: null, 
        title: 'S.No', 
        orderable: false, 
        searchable: false,
        render: function (data, type, full, meta) {
          return meta.settings._iDisplayStart + meta.row + 1;
        }
      },
      { data: 'account_number', title: 'Account Number' },
      { data: 'customer_id', title: 'Customer ID' },
      { data: 'client_name', title: 'Client Name' },
      { 
        data: 'zone',
        title: 'Zone',
        render: function (data) {
          return '<span class="badge bg-label-secondary">' + (data || 'N/A') + '</span>';
        }
      },
      { data: 'loan_name', title: 'Loan Type' },
      { data: 'loan_amount_formatted', title: 'Loan Amount', orderable: false },
      { data: 'tenure_formatted', title: 'Tenure', orderable: false },
      { data: 'emi_amount_formatted', title: 'EMI Amt/Cycle Inst', orderable: false },
      { data: 'outstanding_amount_formatted', title: 'Outstanding', orderable: false },
      {
        data: 'status',
        title: 'Status',
        render: function (data, type, row) {
          const statusColors = {
            'active': 'success',
            'closed': 'secondary',
            'foreclosed': 'warning'
          };
          const color = statusColors[data] || 'secondary';
          return `<span class="badge bg-label-${color}">${row.status_label}</span>`;
        }
      },
      {
        data: 'id',
        title: 'Actions',
        orderable: false,
        searchable: false,
        render: function (data, type, row) {
          return `<a href="${baseUrl}loan/loan-account/${data}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill">
                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                  </a>`;
        }
      }
    ],
    order: [[1, 'desc']],
    columnDefs: [
      {
        targets: '_all',
        className: 'text-nowrap'
      },
      {
        targets: [1], // Account Number
        width: '180px'
      },
      {
        targets: [3], // Client Name
        width: '220px'
      },
      {
        targets: -1,
        orderable: false,
        searchable: false
      }
    ],
    dom:
      '<"row mx-0 align-items-center justify-content-between g-3"' +
      '<"col-sm-12 col-md-6 mb-2 mb-md-0"l>' +
      '<"col-sm-12 col-md-6 d-flex flex-column flex-md-row justify-content-between gap-2"' +
      '<"dt-search-container flex-grow-1"f>' +
      '<"dt-action-buttons d-flex justify-content-md-end align-items-center"B>' +
      '>' +
      '>t' +
      '<"row mx-3 align-items-center justify-content-between"' +
      '<"col-sm-12 col-md-6"i>' +
      '<"col-sm-12 col-md-6 text-md-end"p>' +
      '>',
    lengthMenu: [10, 25, 50, 100],
    language: {
      search: '',
      searchPlaceholder: 'Search Loan Accounts',
      lengthMenu: '_MENU_',
      info: 'Showing _START_ to _END_ of _TOTAL_ accounts',
      paginate: {
        next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
        previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
        first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
        last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
      }
    },
    buttons: [
      {
        extend: 'collection',
        className: 'btn btn-label-secondary dropdown-toggle',
        text: '<i class="icon-base ri ri-upload-2-line me-2 icon-sm"></i>Export',
        buttons: [
          {
            extend: 'print',
            title: 'Loan Accounts',
            text: '<i class="icon-base ri ri-printer-line me-2"></i>Print',
            className: 'dropdown-item',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
          },
          {
            extend: 'csv',
            title: 'Loan Accounts',
            text: '<i class="icon-base ri ri-file-text-line me-2"></i>Csv',
            className: 'dropdown-item',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
          },
          {
            extend: 'excel',
            title: 'Loan Accounts',
            text: '<i class="icon-base ri ri-file-excel-2-line me-2"></i>Excel',
            className: 'dropdown-item',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
          },
          {
            extend: 'pdf',
            title: 'Loan Accounts',
            text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>Pdf',
            className: 'dropdown-item',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] },
            orientation: 'landscape',
            pageSize: 'A4'
          },
          {
            extend: 'copy',
            text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
            className: 'dropdown-item',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }
          }
        ]
      }
    ]
  });

  // Status filter - reload table with new filter
  if ($statusFilter.length) {
    $statusFilter.on('change', function () {
      loanAccountsTable.ajax.reload();
    });
  }

  // Date filters - reload table when dates change
  const $fromDate = $('#fromDate');
  const $toDate = $('#toDate');

  if ($fromDate.length) {
    $fromDate.on('change', function () {
      loanAccountsTable.ajax.reload();
    });
  }

  if ($toDate.length) {
    $toDate.on('change', function () {
      loanAccountsTable.ajax.reload();
    });
  }

  // Style search input
  const $searchInput = $('.dataTables_filter input[type="search"]');
  if ($searchInput.length) {
    $searchInput.addClass('form-control form-control-sm');
  }

  // Style length select
  const $lengthSelect = $('.dataTables_length select');
  if ($lengthSelect.length) {
    $lengthSelect.addClass('form-select form-select-sm');
  }

  // Fixed header alignment on resize
  $(window).on('resize', function() {
    loanAccountsTable.columns.adjust();
  });
});
