/**
 * Ticket Details - Instant AJAX Reply without Page Reload & File Attachment Preview
 */

'use strict';

$(function () {
  const chatContainer = $('#chatContainer');

  // Auto-scroll to bottom of conversation
  if (chatContainer.length) {
    chatContainer.scrollTop(chatContainer[0].scrollHeight);
  }

  // File Input Preview handler
  $('#replyAttachments').on('change', function () {
    const files = this.files;
    const previewContainer = $('#filePreviewContainer');
    const selectedText = $('#fileSelectedText');

    previewContainer.empty();

    if (files && files.length > 0) {
      previewContainer.removeClass('d-none');
      selectedText.text(`${files.length} file(s) attached`);

      Array.from(files).forEach(function (file) {
        const sizeKb = (file.size / 1024).toFixed(0);
        const pill = `
          <span class="badge bg-label-primary d-inline-flex align-items-center gap-1 py-1 px-2">
            <i class="icon-base ri ri-attachment-2"></i>
            <span class="text-truncate" style="max-width: 160px;">${escapeHtml(file.name)}</span>
            <small class="opacity-75">(${sizeKb}KB)</small>
          </span>
        `;
        previewContainer.append(pill);
      });
    } else {
      previewContainer.addClass('d-none');
      selectedText.text('Attach file');
    }
  });

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
        setTimeout(function () {
          window.location.reload();
        }, 800);
      },
      error: function () {
        showAlert('danger', 'Failed to close ticket');
      }
    });
  });

  // Helper to escape HTML characters for secure DOM insertion
  function escapeHtml(text) {
    if (!text) return '';
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
      .replace(/\n/g, '<br>');
  }

  // Reply Form Submission via AJAX without page reload
  $('#replyForm').on('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    const ticketId = $('#ticketId').val();
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnText = submitBtn.html();

    submitBtn
      .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...')
      .prop('disabled', true);

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
        submitBtn.html(originalBtnText).prop('disabled', false);

        if (response.success && response.reply) {
          const reply = response.reply;

          let attachmentsHtml = '';
          if (reply.attachments && reply.attachments.length > 0) {
            attachmentsHtml = '<div class="mt-2 d-flex flex-wrap gap-1">';
            reply.attachments.forEach(function (att) {
              attachmentsHtml += `
                <a href="${att.url}" target="_blank" class="attachment-item text-decoration-none text-white border rounded px-2 py-1 d-inline-flex align-items-center">
                  <i class="icon-base ri ri-attachment-2 me-1"></i>
                  <span>${escapeHtml(att.file_name)}</span>
                </a>
              `;
            });
            attachmentsHtml += '</div>';
          }

          const newReplyHtml = `
            <div class="chat-message user-message">
              <div class="message-bubble">
                <div class="d-flex align-items-center mb-2">
                  <div class="avatar avatar-sm me-2">
                    <div class="avatar-initial bg-label-light rounded-circle">
                      ${reply.user_avatar || 'U'}
                    </div>
                  </div>
                  <div>
                    <strong>${escapeHtml(reply.user_name)}</strong>
                    <small class="ms-2 opacity-75">${reply.created_at}</small>
                  </div>
                </div>
                <p class="mb-0">${escapeHtml(reply.message)}</p>
                ${attachmentsHtml}
              </div>
            </div>
          `;

          chatContainer.append(newReplyHtml);
          chatContainer.animate({ scrollTop: chatContainer[0].scrollHeight }, 300);

          // Reset form and file preview state
          $('#replyForm')[0].reset();
          $('#filePreviewContainer').empty().addClass('d-none');
          $('#fileSelectedText').text('Attach file');

          showAlert('success', response.message);
        } else {
          showAlert('success', response.message || 'Reply sent');
        }
      },
      error: function (xhr) {
        submitBtn.html(originalBtnText).prop('disabled', false);
        const message = xhr.responseJSON?.message || 'Failed to send reply';
        showAlert('danger', message);
      }
    });
  });

  // Toast notification function
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
