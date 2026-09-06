/**
 * Payment Receipts Management
 */

'use strict';

$(function () {
  let borderColor, bodyBg, headingColor;
  const sanitizeExportValue = value => {
    if (value == null) return '';
    if (typeof value === 'string') {
      return value.replace(/<[^>]*>/g, '').trim();
    }
    return String(value);
  };
  const pdfTitle = document.title ? `Payment Receipts | ${document.title}` : 'Payment Receipts Report';

  if (isDarkStyle) {
    borderColor = config.colors_dark.borderColor;
    bodyBg = config.colors_dark.bodyBg;
    headingColor = config.colors_dark.headingColor;
  } else {
    borderColor = config.colors.borderColor;
    bodyBg = config.colors.bodyBg;
    headingColor = config.colors.headingColor;
  }

  // Initialize DataTable
  let receiptsTable;
  const dt_receipts_table = $('#receiptsTable');

  if (dt_receipts_table.length) {
    receiptsTable = dt_receipts_table.DataTable({
      ajax: {
        url: baseUrl + 'emi/receipts/data',
        type: 'GET',
        data: function (d) {
          const urlParams = new URLSearchParams(window.location.search);
          if (urlParams.has('application_number')) {
            d.application_number = urlParams.get('application_number');
          }
        }
      },
      columns: [
        { data: 'sno' },
        { data: 'receipt_number' },
        { data: 'client_name' },
        { 
          data: 'zone',
          render: function (data) {
            return '<span class="badge bg-label-secondary">' + (data || 'N/A') + '</span>';
          }
        },
        { data: 'application_number' },
        { data: 'paid_amount_formatted' },
        { data: 'payment_method' },
        { data: 'paid_date' },
        {
          data: 'id',
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center">' +
              `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill print-receipt" data-id="${full.id}" title="Print Receipt"><i class="icon-base ri ri-printer-line icon-22px"></i></button>` +
              '</div>'
            );
          }
        }
      ],
      order: [[0, 'desc']], // Order by S.No Descending
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
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6],
                format: {
                  body: sanitizeExportValue
                }
              }
            },
            {
              extend: 'csv',
              text: '<i class="icon-base ri ri-file-text-line me-2"></i>CSV',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6],
                format: {
                  body: sanitizeExportValue
                }
              }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6],
                format: {
                  body: sanitizeExportValue
                }
              }
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6],
                format: {
                  body: sanitizeExportValue
                }
              },
              title: pdfTitle,
              customize: function (doc) {
                const generatedDate = new Date().toLocaleString();
                doc.pageMargins = [30, 40, 30, 40];
                doc.defaultStyle.fontSize = 10;
                doc.styles.tableHeader.fontSize = 11;
                doc.styles.tableHeader.fillColor = '#eef1f5';
                doc.styles.tableHeader.color = '#111111';
                doc.styles.tableHeader.alignment = 'left';

                if (doc.content?.length) {
                  doc.content[0].text = pdfTitle;
                  doc.content[0].alignment = 'center';
                  doc.content[0].margin = [0, 0, 0, 12];
                }

                const tableContent = doc.content?.find(item => item.table);
                if (tableContent) {
                  const table = tableContent.table;
                  table.widths = ['6%', '15%', '18%', '18%', '15%', '14%', '14%'];
                  table.body.forEach((row, rowIndex) => {
                    row.forEach((cell, colIndex) => {
                      const cellObj = typeof cell === 'object' ? cell : { text: cell };
                      cellObj.margin = [6, 4, 6, 4];
                      cellObj.alignment = colIndex === 0 ? 'center' : colIndex === 4 ? 'right' : 'left';
                      if (rowIndex === 0) {
                        cellObj.fillColor = '#eef1f5';
                        cellObj.color = '#111111';
                        cellObj.bold = true;
                      }
                      row[colIndex] = cellObj;
                    });
                  });
                }

                doc.footer = function (currentPage, pageCount) {
                  return {
                    columns: [
                      { text: 'Generated on ' + generatedDate, alignment: 'left', margin: [30, 0, 0, 0] },
                      { text: 'Page ' + currentPage + ' of ' + pageCount, alignment: 'right', margin: [0, 0, 30, 0] }
                    ],
                    fontSize: 9
                  };
                };
              }
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6],
                format: {
                  body: sanitizeExportValue
                }
              }
            }
          ]
        }
      ],
      scrollX: true,
      autoWidth: false
    });

    // Fixed header alignment on resize
    $(window).on('resize', function() {
      receiptsTable.columns.adjust();
    });
  }

  // Print Receipt
  $(document).on('click', '.print-receipt', function () {
    const receiptId = $(this).data('id');
    window.open(baseUrl + 'emi/receipts/print/' + receiptId, '_blank');
  });

  // Toast notification function
  function showAlert(type, title, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    const toastId = 'toast-' + Date.now();
    
    const bgColor = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'ri-check-line' : 'ri-close-line';
    
    const toastHtml = `
      <div id="${toastId}" class="toast rounded-5 shadow-lg border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header ${bgColor} text-white rounded-top-5 border-0">
          <i class="icon-base ${icon} me-2"></i>
          <strong class="me-auto">${title}</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        ${message ? `<div class="toast-body">${message}</div>` : ''}
      </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function () {
      toastElement.remove();
    });
  }

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }
});
