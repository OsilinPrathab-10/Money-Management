/**
 * Payment Methods
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  function createToastContainer() {
    const existing = document.querySelector('.toast-container');
    if (existing) return existing;

    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }

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

  const alertContainer = document.querySelector('.alert-container');
  if (alertContainer) {
    const successMessage = alertContainer.getAttribute('data-success');
    const errorMessage = alertContainer.getAttribute('data-error');
    const warningMessage = alertContainer.getAttribute('data-warning');
    const infoMessage = alertContainer.getAttribute('data-info');

    if (successMessage) {
      showAlert('success', successMessage);
    }

    if (errorMessage) {
      showAlert('danger', errorMessage);
    }

    if (warningMessage) {
      showAlert('warning', warningMessage);
    }

    if (infoMessage) {
      showAlert('info', infoMessage);
    }
  }

  // Helper to toggle methods
  function toggleMethod(method, enabled, badgeEl, sectionToggleCallback) {
    fetch('/setup-configuration/payment-methods/toggle', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({ method, enabled })
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          throw new Error(data.message || 'Failed to update payment method');
        }

        if (badgeEl) {
          badgeEl.textContent = enabled ? 'Active' : 'Inactive';
          badgeEl.classList.toggle('bg-label-success', enabled);
          badgeEl.classList.toggle('bg-label-secondary', !enabled);
        }

        if (typeof sectionToggleCallback === 'function') {
          sectionToggleCallback(enabled);
        }

        showAlert('success', data.message);
      })
      .catch(error => {
        showAlert('danger', error.message);
      });
  }

  const razorpaySection = document.getElementById('razorpaySection');
  const addPaymentBtn = document.getElementById('addPaymentMethodBtn');

  function handleManualToggle(enabled) {
    if (razorpaySection) {
      if (enabled) {
        razorpaySection.classList.remove('d-none');
      } else {
        razorpaySection.classList.add('d-none');
        const razorpayStatus = document.getElementById('razorpayStatus');
        const razorpayEnabled = document.getElementById('razorpayEnabled');
        if (razorpayStatus && razorpayEnabled) {
          razorpayStatus.checked = false;
          razorpayEnabled.value = '0';
        }
      }
    }

    if (addPaymentBtn) {
      addPaymentBtn.classList.toggle('d-none', !enabled);
    }
  }

  const autopaySwitch = document.getElementById('autopaySwitch');
  if (autopaySwitch) {
    const autopayBadge = document.getElementById('autopayBadge');
    autopaySwitch.addEventListener('change', function () {
      const enabled = this.checked;
      toggleMethod('autopay_enach', enabled, autopayBadge);
    });
  }

  const manualSwitch = document.getElementById('manualSwitch');
  if (manualSwitch) {
    const manualBadge = document.getElementById('manualBadge');
    manualSwitch.addEventListener('change', function () {
      const enabled = this.checked;
      toggleMethod('manual_payment', enabled, manualBadge, handleManualToggle);
    });
  }

  // Helper to setup gateway interactions
  function setupGatewayInteractions(gatewayName, keyInputId, secretInputId, toggleIconId, statusId, enabledId, formId) {
    // Toggle Status
    const statusEl = document.getElementById(statusId);
    const enabledEl = document.getElementById(enabledId);
    const keyInput = document.getElementById(keyInputId);
    const secretInput = document.getElementById(secretInputId);
    const form = document.getElementById(formId);

    if (statusEl && enabledEl && form) {
      statusEl.addEventListener('change', function () {
        const isEnabled = this.checked;

        // Validation: Check if credentials are filled when enabling
        if (isEnabled) {
          if (!keyInput.value.trim() || !secretInput.value.trim()) {
            this.checked = false; // Revert toggle
            showAlert('warning', `Please fill ${gatewayName} credentials before enabling.`);
            return;
          }
        }

        // Update hidden field
        enabledEl.value = isEnabled ? '1' : '0';

        // Add hidden field to indicate this is a toggle action
        let toggleActionInput = form.querySelector('input[name="is_toggle_action"]');
        if (!toggleActionInput) {
          toggleActionInput = document.createElement('input');
          toggleActionInput.type = 'hidden';
          toggleActionInput.name = 'is_toggle_action';
          form.appendChild(toggleActionInput);
        }
        toggleActionInput.value = '1';

        // Auto-submit the form to save to database
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
        }

        // Submit the form
        form.submit();
      });
    }

    // Toggle Password Visibility
    const toggleIcon = document.getElementById(toggleIconId);
    const toggleBtn = toggleIcon ? toggleIcon.parentElement : null;

    if (toggleBtn && secretInput && toggleIcon) {
      toggleBtn.addEventListener('click', function () {
        const type = secretInput.getAttribute('type') === 'password' ? 'text' : 'password';
        secretInput.setAttribute('type', type);

        if (type === 'text') {
          toggleIcon.classList.remove('ri-eye-off-line');
          toggleIcon.classList.add('ri-eye-line');
        } else {
          toggleIcon.classList.remove('ri-eye-line');
          toggleIcon.classList.add('ri-eye-off-line');
        }
      });
    }

    // Form Submission
    if (form) {
      form.addEventListener('submit', function () {
        // Remove toggle action flag when submitting via Save button
        const toggleActionInput = this.querySelector('input[name="is_toggle_action"]');
        if (toggleActionInput) {
          toggleActionInput.remove();
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.disabled) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
        }
      });
    }
  }

  // Setup Razorpay
  setupGatewayInteractions(
    'Razorpay',
    'razor_key',
    'razor_secret',
    'toggleRazorSecretIcon',
    'razorpayStatus',
    'razorpayEnabled',
    'razorpayForm'
  );

  // Setup Cashfree
  setupGatewayInteractions(
    'Cashfree',
    'app_id',
    'secret_key',
    'toggleCashfreeSecretIcon',
    'cashfreeStatus',
    'cashfreeEnabled',
    'cashfreeForm'
  );

  // Setup PayU
  setupGatewayInteractions(
    'PayU',
    'payu_key',
    'payu_salt',
    'togglePayuSaltIcon',
    'payuStatus',
    'payuEnabled',
    'payuForm'
  );
});
