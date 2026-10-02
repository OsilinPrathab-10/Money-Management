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
  const colors = (typeof config !== 'undefined' && config && (window.isDarkStyle ? config.colors_dark : config.colors)) || {};

  let receiptsTable;
  const dt_receipts_table = $('#receiptsTable');
  const module = dt_receipts_table.attr('data-module') || 'all';
  const printBase = (dt_receipts_table.attr('data-print-base') || ((typeof baseUrl !== 'undefined' ? baseUrl : '/') + 'payment-receipts')).replace(/\/$/, '');
  const hasRealRows = dt_receipts_table.find('tbody tr').not('.receipts-empty-row').length > 0;

  if (dt_receipts_table.length && hasRealRows && $.fn.DataTable) {
    receiptsTable = dt_receipts_table.DataTable({
      order: [[0, 'desc']],
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"lB>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      lengthMenu: [10, 25, 50, 100],
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

  $(document).on('click', '.print-receipt', function () {
    const receiptId = $(this).attr('data-id') || $(this).data('id');
    const receiptModule = $(this).attr('data-module') || $(this).data('module') || module;
    if (!receiptId || receiptModule === 'all') return;
    window.open(printBase + '/' + receiptModule + '/' + receiptId + '/print', '_blank');
  });
});
