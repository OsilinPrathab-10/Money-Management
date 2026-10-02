/**
 * Common Payment Receipts — Loan, Chit, FD
 */

'use strict';

$(function () {
  const sanitizeExportValue = value => {
    if (value == null) return '';
    if (typeof value === 'string') {
      return value.replace(/<[^>]*>/g, '').trim();
    }
    return String(value);
  };
  const pdfTitle = document.title ? `Payment Receipts | ${document.title}` : 'Payment Receipts Report';

  let receiptsTable;
  const dt_receipts_table = $('#receiptsTable');
  const module = dt_receipts_table.attr('data-module') || 'all';

  function receiptPrintRoot() {
    const path = window.location.pathname || '';
    const marker = '/payment-receipts';
    const idx = path.indexOf(marker);
    if (idx >= 0) {
      return window.location.origin + path.substring(0, idx) + marker;
    }

    const htmlBase = (document.documentElement.getAttribute('data-base-url') || window.baseUrl || window.location.origin || '')
      .toString()
      .replace(/\/$/, '');

    return htmlBase + '/payment-receipts';
  }

  const printBase = receiptPrintRoot();
  const hasRealRows = dt_receipts_table.find('tbody tr').not('.receipts-empty-row').length > 0;

  if (dt_receipts_table.length && hasRealRows && $.fn.DataTable) {
    receiptsTable = dt_receipts_table.DataTable({
      paging: false,
      info: false,
      searching: false,
      ordering: false,
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"B>>' +
        '>t',
      language: {
        search: '',
        searchPlaceholder: 'Search Receipts',
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
          className: 'btn btn-label-secondary dropdown-toggle me-4',
          text: '<i class="icon-base ri ri-download-line me-2"></i>Export',
          buttons: [
            {
              extend: 'print',
              text: '<i class="icon-base ri ri-printer-line me-2"></i>Print',
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8], format: { body: sanitizeExportValue } }
            },
            {
              extend: 'csv',
              text: '<i class="icon-base ri ri-file-text-line me-2"></i>CSV',
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8], format: { body: sanitizeExportValue } }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8], format: { body: sanitizeExportValue } }
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              title: pdfTitle,
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8], format: { body: sanitizeExportValue } }
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
              className: 'dropdown-item',
              exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8], format: { body: sanitizeExportValue } }
            }
          ]
        }
      ],
      scrollX: true,
      autoWidth: false
    });

    $(window).on('resize', function () {
      if (receiptsTable) {
        receiptsTable.columns.adjust();
      }
    });
  }

  function parseSplits(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw;
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (err) {
      return [];
    }
  }

  function formatSplitAmount(amount) {
    return '₹' + Number(amount || 0).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  $(document).on('click', '.view-bulk-emis', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $btn = $(this);
    const splits = parseSplits($btn.attr('data-splits') || $btn.data('splits'));
    const label = $btn.attr('data-label') || 'EMI';
    const title = $btn.attr('data-title') || (label + ' details');
    const $body = $('#bulkEmiModalBody');
    let html = '';
    let total = 0;

    if (splits.length) {
      splits.forEach(function (split) {
        const amount = Number(split.amount || 0);
        total += amount;
        html += '<tr><td>' + label + ' #' + (split.instalment_number ?? 'N/A') +
          '</td><td class="text-end">' + formatSplitAmount(amount) + '</td></tr>';
      });
      html += '<tr class="fw-semibold"><td>Total</td><td class="text-end">' + formatSplitAmount(total) + '</td></tr>';
    } else {
      html = '<tr><td colspan="2" class="text-center text-muted py-3">No ' + label + ' details found.</td></tr>';
    }

    $('#bulkEmiModalTitle').text(title);
    $('#bulkEmiModalLabel').text(label);
    $body.html(html);

    const modalEl = document.getElementById('bulkEmiModal');
    if (modalEl && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  });

  $(document).on('click', '.print-receipt', function (e) {
    const $btn = $(this);
    const href = ($btn.attr('href') || '').trim();
    if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
      return;
    }

    e.preventDefault();
    const receiptId = $btn.attr('data-id') || $btn.data('id');
    const receiptModule = $btn.attr('data-module') || $btn.data('module') || module;
    if (!receiptId || receiptModule === 'all') {
      return;
    }

    const bulkParam = $btn.attr('data-bulk') || $btn.data('bulk');
    let printUrl = printBase + '/' + encodeURIComponent(receiptModule) + '/' + encodeURIComponent(String(receiptId)) + '/print';
    if (bulkParam) {
      printUrl += (printUrl.indexOf('?') === -1 ? '?' : '&') + 'bulk=' + encodeURIComponent(String(bulkParam));
    }

    window.open(
      printUrl,
      '_blank'
    );
  });

  $(document).on('click', '.view-receipt-preview', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const url = $btn.attr('data-url') || $btn.data('url');
    const title = $btn.attr('data-title') || 'Payment Receipt';
    if (!url) return;

    $('#receiptPreviewModalTitle').text(title);
    $('#modalOpenReceiptBtn').attr('href', url);
    $('#receiptPreviewSpinner').show();
    $('#receiptPreviewFrame').hide().attr('src', url);

    const modalEl = document.getElementById('receiptPreviewModal');
    if (modalEl && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  });

  $(document).on('click', '#modalPrintReceiptBtn', function (e) {
    e.preventDefault();
    const frame = document.getElementById('receiptPreviewFrame');
    if (frame && frame.contentWindow) {
      try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      } catch (err) {
        const fallbackUrl = $('#modalOpenReceiptBtn').attr('href');
        if (fallbackUrl && fallbackUrl !== '#') {
          window.open(fallbackUrl, '_blank');
        }
      }
    }
  });
});
