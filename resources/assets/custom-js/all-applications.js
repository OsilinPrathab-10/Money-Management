/**
 * Combined Applications — Loan, Chit, FD, Settlement
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
  const pdfTitle = document.title ? `Applications | ${document.title}` : 'Applications Report';

  let applicationsTable;
  const dt_table = $('#applicationsTable');
  const hasRealRows = dt_table.find('tbody tr').not('.applications-empty-row').length > 0;

  if (dt_table.length && hasRealRows && $.fn.DataTable) {
    applicationsTable = dt_table.DataTable({
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
        searchPlaceholder: 'Search Applications'
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
              exportOptions: { columns: ':not(:last-child)', format: { body: sanitizeExportValue } }
            },
            {
              extend: 'csv',
              text: '<i class="icon-base ri ri-file-text-line me-2"></i>CSV',
              className: 'dropdown-item',
              exportOptions: { columns: ':not(:last-child)', format: { body: sanitizeExportValue } }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: { columns: ':not(:last-child)', format: { body: sanitizeExportValue } }
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              title: pdfTitle,
              exportOptions: { columns: ':not(:last-child)', format: { body: sanitizeExportValue } }
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
              className: 'dropdown-item',
              exportOptions: { columns: ':not(:last-child)', format: { body: sanitizeExportValue } }
            }
          ]
        }
      ],
      scrollX: true,
      autoWidth: false
    });

    $(window).on('resize', function () {
      if (applicationsTable) {
        applicationsTable.columns.adjust();
      }
    });
  }

  $(document).on('submit', '.form-reject-settlement', function (e) {
    const form = this;
    if (form.dataset.confirmed === '1') return true;
    e.preventDefault();
    const code = $(form).closest('tr').find('code').first().text() || '';
    const client = $(form).closest('tr').find('td').eq(3).clone().children().remove().end().text().trim() || 'this member';

    const submitConfirmed = function () {
      form.dataset.confirmed = '1';
      form.submit();
    };

    if (window.Swal) {
      Swal.fire({
        title: 'Reject Application?',
        html: 'Are you sure you want to <strong>reject</strong> the settlement application' +
          (code ? ' <code>' + code + '</code>' : '') +
          (client ? ' for <strong>' + $('<div>').text(client).html() + '</strong>' : '') +
          '?<br><small class="text-muted mt-1 d-block">The member will be allowed to re-apply after rejection.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-close-circle-line me-1"></i> Yes, Reject',
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary' },
        buttonsStyling: false
      }).then(function (result) {
        if (result.isConfirmed) submitConfirmed();
      });
      return;
    }

    if (confirm('Reject this settlement application?')) {
      submitConfirmed();
    }
  });
});
