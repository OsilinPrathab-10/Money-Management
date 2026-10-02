'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const $table = $('#chitAccountsTable');
  const $statusFilter = $('#statusFilter');
  const rawBaseUrl = window.baseUrl || document.documentElement.getAttribute('data-base-url') || window.location.origin || '';
  const baseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl : rawBaseUrl + '/';

  if (!$table.length) {
    return;
  }

  $table.DataTable({
    processing: true,
    serverSide: true,
    scrollX: true,
    autoWidth: false,
    ajax: {
      url: baseUrl + 'admin/chit/accounts/data',
      type: 'GET',
      data: function (d) {
        d.status = $statusFilter.val();
        d.from_date = $('#fromDate').val();
        d.to_date = $('#toDate').val();
        d.account_number = $('#accountNumberFilter').val();
      }
    },
    columns: [
      {
        data: null,
        orderable: false,
        searchable: false,
        render: function (data, type, full, meta) {
          return meta.settings._iDisplayStart + meta.row + 1;
        }
      },
      {
        data: 'account_number',
        render: function (data, type, row) {
          return `<a href="${baseUrl}admin/chit/accounts/${row.id}" class="fw-semibold text-primary chit-account-link">${data || 'N/A'}</a>`;
        }
      },
      { data: 'client_name' },
      {
        data: 'zone',
        render: function (data) {
          return '<span class="badge bg-label-secondary">' + (data || 'N/A') + '</span>';
        }
      },
      {
        data: 'group_code',
        render: function (data, type, row) {
          return `<div>${data || 'N/A'}</div><small class="text-muted">${row.scheme_name || ''}</small>`;
        }
      },
      { data: 'chit_value_formatted', orderable: false },
      { data: 'installment_amount_formatted', orderable: false },
      { data: 'progress_formatted', orderable: false },
      { data: 'outstanding_formatted', orderable: false },
      {
        data: 'status',
        render: function (data, type, row) {
          const colors = { active: 'success', completed: 'primary', defaulted: 'danger' };
          const color = colors[data] || 'secondary';
          return `<span class="badge bg-label-${color}">${row.status_label}</span>`;
        }
      },
      {
        data: 'id',
        orderable: false,
        searchable: false,
        render: function (data) {
          return `<a href="${baseUrl}admin/chit/accounts/${data}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill">
                    <i class="icon-base ri ri-eye-line icon-22px"></i>
                  </a>`;
        }
      }
    ],
    order: [[1, 'desc']],
    columnDefs: [
      { targets: '_all', className: 'text-nowrap' },
      { targets: -1, orderable: false, searchable: false }
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
      searchPlaceholder: 'Search Chit Accounts',
      lengthMenu: '_MENU_',
      info: 'Showing _START_ to _END_ of _TOTAL_ accounts',
      paginate: {
        next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
        previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>'
      }
    },
    buttons: [
      {
        extend: 'collection',
        className: 'btn btn-label-secondary dropdown-toggle',
        text: '<i class="icon-base ri ri-upload-2-line me-2 icon-sm"></i>Export',
        buttons: [
          { extend: 'print', title: 'Chit Accounts', text: '<i class="icon-base ri ri-printer-line me-2"></i>Print', className: 'dropdown-item' },
          { extend: 'csv', title: 'Chit Accounts', text: '<i class="icon-base ri ri-file-text-line me-2"></i>Csv', className: 'dropdown-item' },
          { extend: 'excel', title: 'Chit Accounts', text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel', className: 'dropdown-item' }
        ]
      }
    ]
  });

  let filterTimer;
  $('#accountNumberFilter, #fromDate, #toDate').on('change keyup', function () {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(function () {
      $table.DataTable().ajax.reload();
    }, 400);
  });

  $statusFilter.on('change', function () {
    $table.DataTable().ajax.reload();
  });
});
