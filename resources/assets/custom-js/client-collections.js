'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const dataUrl = window.clientCollectionsDataUrl || (window.location.pathname.replace(/\/$/, '') + '/data');
  let module = 'all';
  let status = 'overdue';

  const escapeHtml = function (value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  };

  const statusBadge = function (value) {
    const map = {
      overdue: 'danger',
      pending: 'warning',
      partial: 'info',
      paid: 'success',
      upcoming: 'secondary',
    };
    const color = map[value] || 'secondary';
    return '<span class="badge bg-label-' + color + ' text-capitalize">' + (value || '—') + '</span>';
  };

  const money = function (amount) {
    return '₹' + Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const itemsTable = function (title, items, emptyText) {
    let html = '<div class="mb-3"><h6 class="text-uppercase small fw-semibold mb-2">' + title + '</h6>';
    if (!items || !items.length) {
      html += '<p class="text-muted small mb-0">' + emptyText + '</p></div>';
      return html;
    }
    html += '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr>';
    html += '<th>Item</th><th>Account / Group</th><th>Due</th><th class="text-end">Amount</th><th>Status</th><th></th>';
    html += '</tr></thead><tbody>';
    items.forEach(function (row) {
      html += '<tr>';
      html += '<td>' + escapeHtml(row.label || '—') + '</td>';
      html += '<td>' + escapeHtml(row.account || '—') + '</td>';
      html += '<td>' + escapeHtml(row.due_date || '—') + '</td>';
      html += '<td class="text-end fw-semibold">' + money(row.amount) + '</td>';
      html += '<td>' + statusBadge(row.status) + '</td>';
      html += '<td><a class="btn btn-xs btn-outline-primary" href="' + escapeHtml(row.url || '#') + '">Open</a></td>';
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
  };

  const table = $('#clientCollectionsTable').DataTable({
    processing: true,
    serverSide: true,
    ordering: false,
    searching: true,
    pageLength: 15,
    ajax: {
      url: dataUrl,
      data: function (d) {
        d.module = module;
        d.status = status;
      },
    },
    columns: [
      {
        className: 'details-control',
        orderable: false,
        data: null,
        defaultContent: '<i class="ri-arrow-right-s-line"></i>',
      },
      {
        data: 'client_name',
        render: function (data, type, row) {
          return '<a href="' + escapeHtml(row.client_url || '#') + '" class="fw-semibold">' + escapeHtml(data || 'Client') + '</a>';
        },
      },
      { data: 'client_phone' },
      {
        data: 'loan_count',
        render: function (data, type, row) {
          return data + (row.loan_due > 0 ? ' <span class="text-muted">(' + money(row.loan_due) + ')</span>' : '');
        },
      },
      {
        data: 'chit_count',
        render: function (data, type, row) {
          return data + (row.chit_due > 0 ? ' <span class="text-muted">(' + money(row.chit_due) + ')</span>' : '');
        },
      },
      {
        data: 'total_due',
        className: 'text-end fw-semibold',
        render: function (data) {
          return money(data);
        },
      },
    ],
    language: {
      searchPlaceholder: 'Search client, phone, loan or group',
      search: '',
    },
  });

  $('#clientCollectionsTable tbody').on('click', 'td.details-control', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    if (row.child.isShown()) {
      row.child.hide();
      tr.removeClass('shown');
      return;
    }
    const data = row.data();
    const html = '<div class="client-collection-details">' +
      itemsTable('Loan EMI repayments', data.loan_items, 'No loan EMIs for this filter.') +
      itemsTable('Chit installments', data.chit_items, 'No chit installments for this filter.') +
      '</div>';
    row.child(html).show();
    tr.addClass('shown');
  });

  $('#collectionModuleTabs').on('click', 'button[data-module]', function () {
    $('#collectionModuleTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    module = $(this).data('module');
    table.ajax.reload();
  });

  $('#collectionStatusTabs').on('click', 'button[data-status]', function () {
    $('#collectionStatusTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    status = $(this).data('status');
    table.ajax.reload();
  });
});
