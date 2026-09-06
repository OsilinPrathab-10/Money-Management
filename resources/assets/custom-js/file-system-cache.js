/**
 * File System & Cache Configuration
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // Toast notification function
  function showToast(type, message) {
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

  // Flash alerts from server-side sessions
  const alertContainer = document.querySelector('.alert-container');
  if (alertContainer) {
    const successMessage = alertContainer.getAttribute('data-success');
    const errorMessage = alertContainer.getAttribute('data-error');
    const warningMessage = alertContainer.getAttribute('data-warning');
    const infoMessage = alertContainer.getAttribute('data-info');

    if (successMessage) {
      showToast('success', successMessage);
    }

    if (errorMessage) {
      showToast('danger', errorMessage);
    }

    if (warningMessage) {
      showToast('warning', warningMessage);
    }

    if (infoMessage) {
      showToast('info', infoMessage);
    }
  }

  // Helper function to call cache clear endpoint
  function clearCache(buttonElement) {
    const originalHTML = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Clearing...';

    fetch('/system/cache/clear', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({})
    })
      .then(response => response.json())
      .then(data => {
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHTML;

        if (data.success) {
          showToast('success', data.message);
        } else {
          showToast('danger', data.message || 'Failed to clear cache');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHTML;
        showToast('danger', 'Failed to clear cache. Please try again.');
      });
  }

  // Clear Cache Button
  const clearCacheBtn = document.getElementById('clearCacheBtn');
  if (clearCacheBtn) {
    clearCacheBtn.addEventListener('click', function () {
      clearCache(this);
    });
  }

});
