/**
 * SMTP Settings
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
  const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');

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

    toastElement.addEventListener('hidden.bs.toast', function() {
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

  // Toggle password visibility
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('mail_password');
  const togglePasswordIcon = document.getElementById('togglePasswordIcon');

  if (togglePassword && passwordInput && togglePasswordIcon) {
    togglePassword.addEventListener('click', function () {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);

      if (type === 'text') {
        togglePasswordIcon.classList.remove('ri-eye-off-line');
        togglePasswordIcon.classList.add('ri-eye-line');
      } else {
        togglePasswordIcon.classList.remove('ri-eye-line');
        togglePasswordIcon.classList.add('ri-eye-off-line');
      }
    });
  }

  // Test Connection Button
  const testConnectionBtn = document.getElementById('testConnectionBtn');
  const testEmailModal = new bootstrap.Modal(document.getElementById('testEmailModal'));

  if (testConnectionBtn) {
    testConnectionBtn.addEventListener('click', function (e) {
      e.preventDefault();
      testEmailModal.show();
    });
  }

  // Test Email Form Submission
  const testEmailForm = document.getElementById('testEmailForm');
  const sendTestEmailBtn = document.getElementById('sendTestEmailBtn');

  if (testEmailForm) {
    testEmailForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const testEmail = document.getElementById('test_email').value;
      const smtpForm = document.getElementById('smtpSettingsForm');
      const formData = new FormData(smtpForm);
      
      const payload = {
        test_email: testEmail,
        mail_host: formData.get('mail_host'),
        mail_port: formData.get('mail_port'),
        mail_username: formData.get('mail_username'),
        mail_password: formData.get('mail_password'),
        mail_encryption: formData.get('mail_encryption'),
        mail_from_address: formData.get('mail_from_address'),
        mail_from_name: formData.get('mail_from_name')
      };

      if (!testEmail) {
        showToast('danger', 'Please enter a valid email address');
        return;
      }

      // Disable button and show loading
      sendTestEmailBtn.disabled = true;
      sendTestEmailBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';

      // Send AJAX request
      fetch(baseUrl + 'setup-configuration/smtp-settings/test', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(payload)
      })
      .then(async response => {
        const raw = await response.text();
        let data = {};
        try {
          data = raw ? JSON.parse(raw) : {};
        } catch (e) {
          data = {};
        }
        if (!response.ok) {
          throw new Error(data.message || `SMTP test failed (HTTP ${response.status})`);
        }
        return data;
      })
      .then(data => {
        // Re-enable button
        sendTestEmailBtn.disabled = false;
        sendTestEmailBtn.innerHTML = '<i class="ri-mail-send-line me-1"></i> Send Test Email';

        // Close modal
        testEmailModal.hide();

        if (data.success) {
          showToast('success', data.message);
        } else {
          showToast('danger', data.message || 'Failed to send test email');
        }
      })
      .catch(error => {
        console.error('Error:', error);

        // Re-enable button
        sendTestEmailBtn.disabled = false;
        sendTestEmailBtn.innerHTML = '<i class="ri-mail-send-line me-1"></i> Send Test Email';

        // Close modal
        testEmailModal.hide();

        showToast('danger', error.message || 'Failed to send test email. Please check your settings.');
      });
    });
  }

  // Form submission handling
  const smtpSettingsForm = document.getElementById('smtpSettingsForm');
  if (smtpSettingsForm) {
    smtpSettingsForm.addEventListener('submit', function() {
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
    });
  }
});
