(function () {
  'use strict';

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  // Maintenance Mode Toggle
  const maintenanceSwitch = document.getElementById('maintenanceModeSwitch');
  
  if (maintenanceSwitch) {
    maintenanceSwitch.addEventListener('change', function() {
      const isEnabled = this.checked;
      const toggle = this;
      
      toggle.disabled = true;
      
      fetch('/setup-configuration/feature-activation/toggle-maintenance', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ enabled: isEnabled })
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const badge = document.getElementById('maintenanceBadge');
            if (badge) {
              badge.classList.toggle('bg-label-success', data.value === '1');
              badge.classList.toggle('bg-label-secondary', data.value !== '1');
              badge.textContent = data.value === '1' ? 'Active' : 'Inactive';
            }

            showAlert('success', data.message);
          } else {
            toggle.checked = !isEnabled;
            showAlert('danger', data.message || 'Failed to update maintenance mode');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          toggle.checked = !isEnabled;
          showAlert('danger', 'An error occurred. Please try again.');
        })
        .finally(() => {
          toggle.disabled = false;
        });
    });
  }

  // ================= AWS S3 File System =================
  const s3Toggle = document.getElementById('s3Toggle');
  const s3StatusBadge = document.getElementById('s3Status');
  const s3Form = document.getElementById('s3ConfigForm');
  const saveS3Btn = document.getElementById('saveS3Config');

  function updateS3Badge(isEnabled) {
    if (!s3StatusBadge) return;
    s3StatusBadge.textContent = isEnabled ? 'Active' : 'Inactive';
    s3StatusBadge.classList.remove('bg-label-secondary', 'bg-label-success');
    s3StatusBadge.classList.add(isEnabled ? 'bg-label-success' : 'bg-label-secondary');
  }

  function loadS3Config() {
    if (!s3Toggle) return;

    fetch('/setup-configuration/s3/config')
      .then(response => response.json())
      .then(data => {
        s3Toggle.checked = Boolean(data.enabled);
        updateS3Badge(Boolean(data.enabled));

        if (data.configured && s3Form) {
          s3Form.querySelector('[name="region"]').value = data.region || '';
          s3Form.querySelector('[name="bucket"]').value = data.bucket || '';
          s3Form.querySelector('[name="url"]').value = data.url || '';
        }
      })
      .catch(() => {
        showAlert('danger', 'Failed to load S3 configuration.');
      });
  }

  if (s3Toggle) {
    s3Toggle.addEventListener('change', function () {
      const enabled = this.checked;
      const toggle = this;
      toggle.disabled = true;

      fetch('/setup-configuration/s3/toggle', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ enabled })
      })
        .then(async response => {
          if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to update S3 status');
          }
          return response.json();
        })
        .then(data => {
          updateS3Badge(enabled);
          showAlert('success', data.message || 'S3 status updated successfully');
        })
        .catch(error => {
          toggle.checked = !enabled;
          showAlert('danger', error.message);
        })
        .finally(() => {
          toggle.disabled = false;
        });
    });
  }

  if (saveS3Btn && s3Form) {
    saveS3Btn.addEventListener('click', function () {
      if (!s3Form.checkValidity()) {
        s3Form.reportValidity();
        return;
      }

      const btn = this;
      const originalHTML = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      const formData = new FormData(s3Form);

      fetch('/setup-configuration/s3/config', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken
        },
        body: formData
      })
        .then(async response => {
          const data = await response.json();
          if (!response.ok) {
            throw new Error(data.message || 'Failed to save S3 configuration');
          }
          return data;
        })
        .then(data => {
          showAlert('success', data.message || 'S3 credentials updated successfully');
          const modalEl = document.getElementById('s3ConfigModal');
          if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modalInstance.hide();
          }
          s3Form.reset();
          loadS3Config();
        })
        .catch(error => {
          showAlert('danger', error.message);
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalHTML;
        });
    });
  }

  // Initial load
  loadS3Config();
  
  /**
   * Show alert toast notification
   * @param {string} type - 'success' or 'danger'
   * @param {string} message - Toast message
   */
  function showAlert(type, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    let iconClass, bgClass;
    
    // Map alert types to toast styles
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
    
    // Create toast with rounded corners and shadow
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
    
    // Remove toast element after it's hidden
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
})();
