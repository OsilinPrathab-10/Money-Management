'use strict';

$(function () {
  const tableEl = $('#deletionRequestsTable');
  let dataTable;

  if (tableEl.length) {
    dataTable = tableEl.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'admin/account-deletion/data',
        data: function (d) {
          d.status = $('#statusFilter').val();
        }
      },
      columns: [
        { data: 'request_number', width: '12%' },
        { data: 'full_name', width: '14%' },
        { data: 'email', width: '16%' },
        { data: 'mobile', width: '10%' },
        { data: 'client_link', width: '12%', orderable: false },
        { data: 'status_badge', width: '10%', orderable: false },
        { data: 'created_at', width: '14%' },
        {
          data: 'id',
          width: '12%',
          orderable: false,
          searchable: false,
          render: function (data) {
            return (
              '<div class="d-flex align-items-center gap-1">' +
              `<button class="btn btn-icon btn-text-info btn-sm rounded-pill view-request" data-id="${data}" title="View / Update"><i class="icon-base ri ri-eye-line icon-22px"></i></button>` +
              `<button class="btn btn-icon btn-text-danger btn-sm rounded-pill delete-request" data-id="${data}" title="Delete"><i class="icon-base ri ri-delete-bin-line icon-22px"></i></button>` +
              '</div>'
            );
          }
        }
      ],
      order: [[6, 'desc']],
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"l>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      lengthMenu: [10, 25, 50, 100],
      language: {
        search: '',
        searchPlaceholder: 'Search requests...',
        processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>'
      }
    });

    $('#statusFilter').on('change', function () {
      dataTable.ajax.reload();
    });
  }

  const requestModal = new bootstrap.Modal(document.getElementById('requestModal'));

  $(document).on('click', '.view-request', function () {
    const id = $(this).data('id');

    $.get(baseUrl + 'admin/account-deletion/' + id, function (res) {
      if (!res.success) return;

      const d = res.data;
      $('#modalRequestId').val(d.id);
      $('#modalRequestNumber').text(d.request_number);
      $('#modalFullName').text(d.full_name);
      $('#modalEmail').text(d.email);
      $('#modalMobile').text(d.mobile);
      $('#modalCreatedAt').text(d.created_at);
      $('#modalReason').text(d.reason || 'No reason provided.');
      $('#modalIp').text(d.ip_address || '—');
      $('#modalStatus').val(d.status);
      $('#modalAdminNotes').val(d.admin_notes || '');

      if (d.client_url) {
        $('#modalClient').html(`<a href="${d.client_url}" class="text-primary fw-medium" target="_blank">${d.client_name} <i class="ri-external-link-line"></i></a>`);
      } else {
        $('#modalClient').html('<span class="text-muted">No matching client found</span>');
      }

      const reviewed = d.reviewer_name
        ? `${d.reviewer_name} — ${d.reviewed_at}`
        : 'Not reviewed yet';
      $('#modalReviewed').text(reviewed);

      requestModal.show();
    });
  });

  $('#saveRequestStatus').on('click', function () {
    const id = $('#modalRequestId').val();
    const btn = $(this);
    btn.prop('disabled', true);

    $.ajax({
      url: baseUrl + 'admin/account-deletion/' + id + '/status',
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        status: $('#modalStatus').val(),
        admin_notes: $('#modalAdminNotes').val()
      },
      success: function (res) {
        requestModal.hide();
        if (dataTable) dataTable.ajax.reload(null, false);
        Swal.fire({ icon: 'success', title: 'Updated', text: res.message, timer: 2000, showConfirmButton: false });
      },
      error: function (xhr) {
        Swal.fire('Error', xhr.responseJSON?.message || 'Failed to update request.', 'error');
      },
      complete: function () {
        btn.prop('disabled', false);
      }
    });
  });

  $(document).on('click', '.delete-request', function () {
    const id = $(this).data('id');

    Swal.fire({
      title: 'Delete this request?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-label-secondary ms-2' },
      buttonsStyling: false
    }).then(function (result) {
      if (!result.isConfirmed) return;

      $.ajax({
        url: baseUrl + 'admin/account-deletion/' + id,
        type: 'DELETE',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function (res) {
          if (dataTable) dataTable.ajax.reload(null, false);
          Swal.fire({ icon: 'success', title: 'Deleted', text: res.message, timer: 2000, showConfirmButton: false });
        },
        error: function () {
          Swal.fire('Error', 'Failed to delete request.', 'error');
        }
      });
    });
  });
});
