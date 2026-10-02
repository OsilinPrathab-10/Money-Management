/**
 * Accounting GST configuration page
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('gstConfigForm');
  const statusSwitch = document.getElementById('gstStatusSwitch');
  const hiddenEnabled = document.getElementById('gstEnabled');
  const badge = document.getElementById('gstStatusBadge');
  const percentageInput = document.getElementById('gstPercentageInput');
  const percentageWrap = document.getElementById('gstPercentageWrap');

  if (!form) {
    return;
  }

  function syncGstToggleUi() {
    const isOn = statusSwitch.checked;
    hiddenEnabled.value = isOn ? '1' : '0';
    badge.textContent = isOn ? 'ON' : 'OFF';
    badge.classList.toggle('bg-label-success', isOn);
    badge.classList.toggle('bg-label-secondary', !isOn);

    if (percentageInput) {
      percentageInput.disabled = !isOn;
      percentageInput.required = isOn;
    }

    if (percentageWrap) {
      percentageWrap.classList.toggle('opacity-50', !isOn);
    }
  }

  statusSwitch.addEventListener('change', syncGstToggleUi);
  syncGstToggleUi();

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    fetch(form.action, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
      },
      body: new FormData(form),
    })
      .then(async (response) => {
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.message || 'Failed to save GST configuration');
        }
        return data;
      })
      .then((data) => {
        if (data.success) {
          showGstToast('success', 'Success', data.message || 'GST configuration saved successfully');
        } else {
          showGstToast('danger', 'Error', data.message || 'Failed to save GST configuration');
        }
      })
      .catch((error) => {
        showGstToast('danger', 'Error', error.message || 'An error occurred while saving GST configuration');
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
      });
  });
});

function showGstToast(type, title, message) {
  const toastContainer = document.querySelector('.toast-container') || createGstToastContainer();
  const toastId = 'toast-' + Date.now();
  const iconClass = type === 'success' ? 'ri-check-line' : 'ri-close-circle-line';
  const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';

  toastContainer.insertAdjacentHTML('beforeend', `
    <div id="${toastId}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert">
      <div class="toast-header ${bgClass} text-white rounded-top-5 border-0">
        <i class="icon-base ${iconClass} me-2"></i>
        <div class="me-auto fw-medium">${title}</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body rounded-bottom-3">${message}</div>
    </div>
  `);

  const toastElement = document.getElementById(toastId);
  if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
    toast.show();
  }
}

function createGstToastContainer() {
  const container = document.createElement('div');
  container.className = 'toast-container position-fixed top-0 end-0 p-3';
  container.style.zIndex = '9999';
  document.body.appendChild(container);
  return container;
}
