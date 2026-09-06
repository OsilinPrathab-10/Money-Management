/**
 * Ticket Details
 */

'use strict';

$(function () {
  // Close Ticket Button
  $('#closeTicketBtn').on('click', function () {
    const closeTicketModal = new bootstrap.Modal(document.getElementById('closeTicketModal'));
    closeTicketModal.show();
  });

  // Confirm Close Ticket
  $('#confirmCloseTicket').on('click', function () {
    const ticketId = $('#ticketId').val();

    $.ajax({
      url: baseUrl + 'support/tickets/' + ticketId + '/status',
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: { status: 'closed' },
      success: function (response) {
        showAlert('success', response.message);
        bootstrap.Modal.getInstance(document.getElementById('closeTicketModal')).hide();
        // Reload page to reflect changes
        setTimeout(function () {
          window.location.reload();
        }, 1000);
      },
      error: function () {
        showAlert('danger', 'Failed to close ticket');
      }
    });
  });

  // Reply Form Submission
  $('#replyForm').on('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const ticketId = $('#ticketId').val();
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();

    submitBtn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...').prop('disabled', true);

    $.ajax({
      url: baseUrl + 'support/tickets/' + ticketId + '/reply',
      type: 'POST',
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        showAlert('success', response.message);
        $('#replyForm')[0].reset();

        // Reload page to show new reply
        setTimeout(function () {
          window.location.reload();
        }, 1000);
      },
      error: function (xhr) {
        submitBtn.html(originalBtnText).prop('disabled', false);
        const message = xhr.responseJSON?.message || 'Failed to send reply';
        showAlert('danger', message);
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
