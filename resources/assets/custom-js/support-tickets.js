/**
 * Support Tickets Management
 */

'use strict';

$(function () {
  let borderColor, bodyBg, headingColor;

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
  let ticketsTable;
  const dt_tickets_table = $('#ticketsTable');

  if (dt_tickets_table.length) {
    ticketsTable = dt_tickets_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'support/tickets/data',
        type: 'GET',
        data: function (d) {
          d.status = $('#statusFilter').val();
          d.priority = $('#priorityFilter').val();
        }
      },
      columns: [
        { 
          data: null, 
          width: '5%',
          orderable: true,
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        { data: 'ticket_number', width: '12%' },
        { data: 'client_name', width: '20%' },
        { data: 'priority_badge', width: '12%' },
        { data: 'status_badge', width: '12%' },
        { data: 'created_at', width: '15%' },
        {
          data: 'id',
          width: '14%',
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center gap-2">' +
              `<button class="btn btn-icon btn-text-warning btn-sm rounded-pill change-status" data-id="${full.id}" data-status="${full.status}" title="Change Status"><i class="icon-base ri ri-refresh-line icon-22px"></i></button>` +
              `<button class="btn btn-icon btn-text-info btn-sm rounded-pill assign-ticket" data-id="${full.id}" title="Assign"><i class="icon-base ri ri-user-add-line icon-22px"></i></button>` +
              `<a href="${baseUrl}support/tickets/${full.id}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View"><i class="icon-base ri ri-eye-line icon-22px"></i></a>` +
              `<button class="btn btn-icon btn-text-danger btn-sm rounded-pill delete-ticket" data-id="${full.id}" title="Delete"><i class="icon-base ri ri-delete-bin-line icon-22px"></i></button>` +
              '</div>'
            );
          }
        }
      ],
      columnDefs: [
        { className: 'text-center', targets: [0, 3, 4, 6] }
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
        searchPlaceholder: 'Search Tickets',
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
                columns: [0, 1, 2, 3, 4, 5, 6, 7]
              }
            },
            {
              extend: 'csv',
              text: '<i class="icon-base ri ri-file-text-line me-2"></i>CSV',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5]
              }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5]
              }
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5]
              }
            }
          ]
        }
      ]
    });


  }

  // Delete Ticket
  $(document).on('click', '.delete-ticket', function () {
    const ticketId = $(this).data('id');

    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: {
        confirmButton: 'btn btn-primary me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        $.ajax({
          url: baseUrl + 'support/tickets/' + ticketId,
          type: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          },
          success: function (response) {
            showAlert('success', response.message);
            ticketsTable.ajax.reload();
          },
          error: function () {
            showAlert('danger', 'Failed to delete ticket');
          }
        });
      }
    });
  });

  // Change Status Button
  $(document).on('click', '.change-status', function () {
    const ticketId = $(this).data('id');
    const currentStatus = $(this).data('status');

    $('#statusTicketId').val(ticketId);
    $('#statusSelect').val(currentStatus);

    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    statusModal.show();
  });

  // Confirm Status Change
  $('#confirmStatusChange').on('click', function () {
    const ticketId = $('#statusTicketId').val();
    const newStatus = $('#statusSelect').val();

    $.ajax({
      url: baseUrl + 'support/tickets/' + ticketId + '/status',
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: { status: newStatus },
      success: function (response) {
        showAlert('success', response.message);
        bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
        ticketsTable.ajax.reload();
      },
      error: function () {
        showAlert('danger', 'Failed to update status');
      }
    });
  });

  // Assign Ticket Button
  $(document).on('click', '.assign-ticket', function () {
    const ticketId = $(this).data('id');
    $('#assignTicketId').val(ticketId);

    // Load users if not already loaded
    if ($('#assignUserSelect option').length === 1) {
      $.ajax({
        url: baseUrl + 'support/tickets/users',
        type: 'GET',
        success: function (users) {
          users.forEach(function (user) {
            $('#assignUserSelect').append(`<option value="${user.id}">${user.name}</option>`);
          });
        }
      });
    }

    const assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
    assignModal.show();
  });

  // Confirm Assign
  $('#confirmAssign').on('click', function () {
    const ticketId = $('#assignTicketId').val();
    const userId = $('#assignUserSelect').val();

    if (!userId) {
      showAlert('warning', 'Please select a user');
      return;
    }

    $.ajax({
      url: baseUrl + 'support/tickets/' + ticketId + '/assign',
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: { assigned_to: userId },
      success: function (response) {
        showAlert('success', response.message);
        bootstrap.Modal.getInstance(document.getElementById('assignModal')).hide();
        ticketsTable.ajax.reload();
      },
      error: function () {
        showAlert('danger', 'Failed to assign ticket');
      }
    });
  });

  // Toast notification function (matching payment-methods.js style)
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
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
      const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
      });
      toast.show();
      toastElement.addEventListener('hidden.bs.toast', function () {
        toastElement.remove();
      });
    }
  }

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }
});
