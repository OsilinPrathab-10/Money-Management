let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
if (!baseUrl.endsWith('/')) {
  baseUrl += '/';
}
document.addEventListener('DOMContentLoaded', function (e) {
  let borderColor, bodyBg, headingColor;

  borderColor = config.colors.borderColor;
  bodyBg = config.colors.bodyBg;
  headingColor = config.colors.headingColor;

  // Variable declaration for table
  const dt_kyc_table = document.querySelector('.datatables-kyc');

  // KYC Verification datatable
  if (dt_kyc_table) {
    const dt_kyc = new DataTable(dt_kyc_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'verification/kyc-verification',
        dataSrc: function (json) {
          if (typeof json.recordsTotal !== 'number') {
            json.recordsTotal = 0;
          }
          if (typeof json.recordsFiltered !== 'number') {
            json.recordsFiltered = 0;
          }
          json.data = Array.isArray(json.data) ? json.data : [];
          return json.data;
        }
      },
      columns: [
        { data: 'id' },
        { data: 'DT_RowIndex', orderable: false, searchable: false },
        { data: 'client_name' },
        { data: 'submitted_on' },
        { data: 'kyc_status' },
        { data: 'action' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          searchable: false,
          orderable: false,
          responsivePriority: 2,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          // ID
          searchable: false,
          orderable: false,
          targets: 1,
          render: function (data, type, full, meta) {
            return `<span>${data}</span>`;
          }
        },
        {
          // Client Name
          targets: 2,
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            return `<span class="fw-medium text-heading">${data}</span>`;
          }
        },
        {
          // Submitted On
          targets: 3,
          render: function (data, type, full, meta) {
            return `<span>${data}</span>`;
          }
        },
        {
          // KYC Status
          targets: 4,
          render: function (data, type, full, meta) {
            return data;
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return data;
          }
        }
      ],
      order: [[3, 'desc']],
      layout: {
        topStart: {
          rowClass: 'row m-3 my-0 justify-content-between',
          features: [
            {
              pageLength: {
                menu: [7, 10, 20, 50, 70, 100],
                text: '_MENU_'
              }
            }
          ]
        },
        topEnd: {
          features: [
            {
              search: {
                placeholder: 'Search KYC',
                text: '_INPUT_'
              }
            },
            {
              buttons: [
                {
                  extend: 'collection',
                  className: 'btn btn-label-secondary dropdown-toggle',
                  text: '<i class="icon-base ri ri-upload-2-line me-2 icon-sm"></i>Export',
                  buttons: [
                    {
                      extend: 'print',
                      title: 'KYC Verification',
                      text: '<i class="icon-base ri ri-printer-line me-2" ></i>Print',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            if (inner.indexOf('<') > -1) {
                              const parser = new DOMParser();
                              const doc = parser.parseFromString(inner, 'text/html');
                              return (doc.body.textContent || doc.body.innerText || '').trim();
                            }
                            return inner;
                          }
                        }
                      },
                      customize: function (win) {
                        win.document.body.style.color = headingColor;
                        win.document.body.style.borderColor = borderColor;
                        win.document.body.style.backgroundColor = bodyBg;
                        win.document.body.style.fontFamily = '"Public Sans", sans-serif';
                        const table = win.document.body.querySelector('table');
                        if (table) {
                          table.classList.add('table', 'table-bordered', 'table-sm', 'compact');
                          table.style.color = 'inherit';
                          table.style.borderColor = 'inherit';
                          table.style.backgroundColor = 'inherit';
                          table.style.borderCollapse = 'collapse';
                          table.style.width = '100%';
                          table.querySelectorAll('th, td').forEach(cell => {
                            cell.style.border = '1px solid ' + borderColor;
                            cell.style.padding = '8px';
                            cell.style.textAlign = 'left';
                          });
                        }
                      }
                    },
                    {
                      extend: 'csv',
                      title: 'KYC Verification',
                      text: '<i class="icon-base ri ri-file-text-line me-2" ></i>Csv',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      }
                    },
                    {
                      extend: 'excel',
                      title: 'KYC Verification',
                      text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      }
                    },
                    {
                      extend: 'pdfHtml5',
                      title: 'KYC Verification',
                      text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>Pdf',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      },
                      customize: function (doc) {
                        doc.content[0].text = 'KYC Verification | Loan App';
                        doc.defaultStyle.fontSize = 10;
                        doc.styles.tableHeader.fontSize = 10;
                        doc.styles.tableHeader.alignment = 'left';
                        doc.styles.tableHeader.fillColor = '#f5f5f5';
                        doc.styles.tableHeader.color = '#333333';

                        doc.content.splice(1, 0, {
                          text: 'KYC Verification Report',
                          margin: [0, 0, 0, 12],
                          fontSize: 12,
                          bold: true
                        });

                        const tableContent = doc.content.find(item => item.table);
                        if (tableContent) {
                          tableContent.table.widths = ['10%', '40%', '25%', '25%'];
                          tableContent.layout = {
                            hLineWidth: function () { return 0.5; },
                            vLineWidth: function () { return 0.5; },
                            hLineColor: function () { return '#cccccc'; },
                            vLineColor: function () { return '#cccccc'; },
                            paddingLeft: function () { return 6; },
                            paddingRight: function () { return 6; },
                            paddingTop: function () { return 6; },
                            paddingBottom: function () { return 6; }
                          };
                          tableContent.table.body.forEach(function (row) {
                            row.forEach(function (cell) {
                              cell.alignment = 'left';
                              cell.margin = [0, 4, 0, 4];
                            });
                          });
                        }
                      }
                    },
                    {
                      extend: 'copy',
                      title: 'KYC Verification',
                      text: '<i class="icon-base ri ri-file-copy-line me-2" ></i>Copy',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [1, 2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      }
                    }
                  ]
                }
              ]
            }
          ]
        },
        bottomStart: {
          rowClass: 'row mx-3 justify-content-between',
          features: [
            {
              info: {
                text: 'Showing _START_ to _END_ of _TOTAL_ entries'
              }
            }
          ]
        },
        bottomEnd: 'paging'
      },
      displayLength: 20,
      language: {
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
          first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
          last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      // For responsive popup
      responsive: {
        details: {
          display: DataTable.Responsive.display.modal({
            header: function (row) {
              const data = row.data();
              return 'Details of ' + data['name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            const data = columns
              .map(function (col) {
                return col.title !== ''
                  ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                      <td>${col.title}:</td>
                      <td>${col.data}</td>
                    </tr>`
                  : '';
              })
              .join('');

            if (data) {
              const div = document.createElement('div');
              div.classList.add('table-responsive');
              const table = document.createElement('table');
              div.appendChild(table);
              table.classList.add('table');
              const tbody = document.createElement('tbody');
              tbody.innerHTML = data;
              table.appendChild(tbody);
              return div;
            }
            return false;
          }
        }
      },
      initComplete: function () {
        // Remove btn-secondary from export buttons
        document.querySelectorAll('.dt-buttons .btn').forEach(btn => {
          btn.classList.remove('btn-secondary');
        });
      }
    });

    // View Documents
    document.addEventListener('click', function (e) {
      if (e.target.closest('.view-record') || e.target.closest('.view-documents')) {
        const btn = e.target.closest('.view-record') || e.target.closest('.view-documents');
        const kyc_id = btn.dataset.id;
        const dtrModal = document.querySelector('.dtr-bs-modal.show');

        if (dtrModal) {
          const bsModal = bootstrap.Modal.getInstance(dtrModal);
          bsModal.hide();
        }

        // Navigate to user view page
        window.location.href = `${baseUrl}/verification/view/kyc/${kyc_id}`;
      }
    });

    // Delete Record
    let currentDeleteId = null;
    let currentKycName = null;

    document.addEventListener('click', function (e) {
      if (e.target.closest('.delete-record') || e.target.closest('.delete-documents')) {
        const deleteBtn = e.target.closest('.delete-record') || e.target.closest('.delete-documents');
        currentDeleteId = deleteBtn.dataset.id;

        // Get KYC name from the row
        const row = dt_kyc.row(deleteBtn.closest('tr'));
        const rowData = row.data();
        currentKycName = rowData ? rowData.full_name : 'this record';

        const dtrModal = document.querySelector('.dtr-bs-modal.show');
        if (dtrModal) {
          const bsModal = bootstrap.Modal.getInstance(dtrModal);
          bsModal.hide();
        }

        // Show delete confirmation modal
        document.getElementById('deleteKycName').textContent = currentKycName;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
      }
    });

    // Confirm Delete Button
    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
      // Close delete modal
      const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
      deleteModal.hide();

      // Remove the row from DataTable
      if (currentDeleteId) {
        // Find and remove the row with the matching ID
        dt_kyc.rows().every(function () {
          const data = this.data();
          if (data.id == currentDeleteId) {
            this.remove();
            return false;
          }
        });
        dt_kyc.draw();
      }

      // Show success modal
      setTimeout(() => {
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
      }, 300);
    });

    // Filter form control to default size
    setTimeout(() => {
      const elementsToModify = [
        { selector: '.dt-buttons .btn', classToRemove: 'btn-secondary' },
        { selector: '.dt-length .form-select', classToAdd: 'ms-0' },
        { selector: '.dt-length', classToAdd: 'mb-md-5 mb-0' },
        {
          selector: '.dt-layout-end',
          classToRemove: 'justify-content-between',
          classToAdd: 'd-flex gap-md-4 justify-content-md-between justify-content-center gap-md-2 flex-wrap mt-0'
        },
        { selector: '.dt-layout-start', classToAdd: 'mt-md-0 mt-5' },
        {
          selector: '.dt-layout-start .dt-buttons',
          classToAdd: 'd-md-flex d-block gap-4 justify-content-center'
        },
        {
          selector: '.dt-layout-end .dt-buttons',
          classToAdd: 'd-md-flex d-block gap-4 mb-md-0 mb-5 justify-content-center'
        },
        { selector: '.dt-layout-table', classToRemove: 'row mt-2' },
        { selector: '.dt-layout-full', classToRemove: 'col-md col-12' },
        { selector: '.dt-layout-full .table', classToAdd: 'table-responsive' }
      ];

      elementsToModify.forEach(({ selector, classToRemove, classToAdd }) => {
        document.querySelectorAll(selector).forEach(element => {
          if (classToRemove) {
            classToRemove.split(' ').forEach(className => element.classList.remove(className));
          }
          if (classToAdd) {
            classToAdd.split(' ').forEach(className => element.classList.add(className));
          }
        });
      });
    }, 100);
  }
});

// ========================================
// Toast Notification Functions & Form Handlers
// ========================================

// Function to show professional toast notifications
function showAlert(type, message) {
  const toastContainer = document.querySelector('.toast-container') || createToastContainer();
  const toastId = 'toast-' + Date.now();
  let iconClass, bgClass;

  if (type === 'success') {
    iconClass = 'ri-check-line';
    bgClass = 'bg-success';
  } else if (type === 'danger') {
    iconClass = 'ri-close-circle-line';
    bgClass = 'bg-danger';
  } else if (type === 'warning') {
    iconClass = 'ri-alert-line';
    bgClass = 'bg-warning';
  } else if (type === 'info') {
    iconClass = 'ri-information-line';
    bgClass = 'bg-info';
  } else {
    iconClass = 'ri-error-warning-line';
    bgClass = 'bg-danger';
  }

  const toastHTML = `
    <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
      <div class="toast-header ${bgClass} text-white rounded-5 border-0">
        <i class="icon-base ${iconClass} me-2"></i>
        <div class="me-auto fw-medium">${message}</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  `;

  toastContainer.insertAdjacentHTML('beforeend', toastHTML);

  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, {
    autohide: true,
    delay: 3000
  });

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

$(document).ready(function () {
  // Handle Approve Form Submission
  $('#approveModal form').on('submit', function (e) {
    e.preventDefault();

    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();

    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Approving...');

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      data: form.serialize(),
      success: function (response) {
        $('#approveModal').modal('hide');
        showAlert('success', response.message);
        setTimeout(() => window.location.reload(), 1500);
      },
      error: function (xhr) {
        const message = xhr.responseJSON?.message || 'Failed to approve KYC';
        showAlert('danger', message);
        submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });

  // Handle Reject Form Submission
  $('#deleteModal form').on('submit', function (e) {
    e.preventDefault();

    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();

    // Validate reason field
    const reason = form.find('#reason').val().trim();
    if (!reason) {
      showAlert('warning', 'Please provide a reason for rejection');
      return;
    }

    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Rejecting...');

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      data: form.serialize(),
      success: function (response) {
        $('#deleteModal').modal('hide');
        showAlert('success', response.message);
        setTimeout(() => window.location.reload(), 1500);
      },
      error: function (xhr) {
        const message = xhr.responseJSON?.message || 'Failed to reject KYC';
        showAlert('danger', message);
        submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });

  // Handle Client Blacklist Form Submission
  $('#blacklistForm').on('submit', function (e) {
    e.preventDefault();

    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();

    // Validate reason field
    const reason = form.find('#blacklist_reason').val().trim();
    if (!reason) {
      showAlert('warning', 'Please provide a reason for blacklisting');
      return;
    }

    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Blacklisting...');

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      data: form.serialize(),
      success: function (response) {
        $('#blacklistModal').modal('hide');
        showAlert('success', response.message);
        setTimeout(() => window.location.reload(), 1500);
      },
      error: function (xhr) {
        const message = xhr.responseJSON?.message || 'Failed to blacklist client';
        showAlert('danger', message);
        submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });
});


// Note: Loan Application Modal Logic has been moved to resources/assets/custom-js/loan-applications.js
// to avoid duplication and conflicts.

