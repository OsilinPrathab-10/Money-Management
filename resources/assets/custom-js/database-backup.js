/**
 * Database Backup
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // Ensure baseUrl is defined
  const baseUrl = window.baseUrl || (document.querySelector('meta[name="base-url"]') ? document.querySelector('meta[name="base-url"]').content : '/');
  
  let deleteFilename = '';

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

  // Create Backup
  const createBackupBtn = document.getElementById('createBackupBtn');
  const createFirstBackupBtn = document.getElementById('createFirstBackupBtn');

  function createBackup(button) {
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Creating...';

    fetch(baseUrl + 'system/database-backup/create', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
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
          throw new Error(data.message || `Backup failed (HTTP ${response.status})`);
        }
        return data;
      })
      .then(data => {
        button.disabled = false;
        button.innerHTML = originalHTML;

        if (data.success) {
          showToast('success', data.message);
          // Reload page after 1 second to show new backup
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showToast('danger', data.message || 'Failed to create backup');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        button.disabled = false;
        button.innerHTML = originalHTML;
        showToast('danger', error.message || 'Failed to create backup. Please try again.');
      });
  }

  if (createBackupBtn) {
    createBackupBtn.addEventListener('click', function () {
      createBackup(this);
    });
  }

  if (createFirstBackupBtn) {
    createFirstBackupBtn.addEventListener('click', function () {
      createBackup(this);
    });
  }

  // Delete Backup
  const deleteBackupModal = new bootstrap.Modal(document.getElementById('deleteBackupModal'));
  const deleteBackupBtns = document.querySelectorAll('.delete-backup-btn');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

  deleteBackupBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      deleteFilename = this.getAttribute('data-filename');
      deleteBackupModal.show();
    });
  });

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function () {
      const originalHTML = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deleting...';

      fetch(baseUrl + 'system/database-backup/delete/' + deleteFilename, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      })
        .then(response => response.json())
        .then(data => {
          this.disabled = false;
          this.innerHTML = originalHTML;
          deleteBackupModal.hide();

          if (data.success) {
            showToast('success', data.message);
            // Reload page after 1 second
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            showToast('danger', data.message || 'Failed to delete backup');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          this.disabled = false;
          this.innerHTML = originalHTML;
          deleteBackupModal.hide();
          showToast('danger', 'Failed to delete backup. Please try again.');
        });
    });
  }

  // Auto Backup Configuration
  const autoBackupForm = document.getElementById('autoBackupConfigForm');
  const autoBackupSwitch = document.getElementById('autoBackupStatus');
  const autoBackupEnabled = document.getElementById('autoBackupEnabled');

  if (autoBackupForm) {
    autoBackupForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      fetch(baseUrl + 'system/database-backup/auto-config/save', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        },
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;

          if (data.success) {
            showToast('success', data.message || 'Auto backup configuration saved successfully');
          } else {
            showToast('danger', data.message || 'Failed to save configuration');
          }
        })
        .catch(error => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
          console.error('Error:', error);
          showToast('danger', 'An error occurred while saving configuration');
        });
    });
  }

  if (autoBackupSwitch && autoBackupEnabled) {
    autoBackupSwitch.addEventListener('change', function () {
      const isChecked = this.checked;
      autoBackupEnabled.value = isChecked ? '1' : '0';

      // Save immediately
      const formData = new FormData();
      formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
      formData.append('enabled', isChecked ? '1' : '0');
      formData.append('frequency', document.querySelector('input[name="frequency"]:checked').value);

      fetch(baseUrl + 'system/database-backup/auto-config/save', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json'
        },
        body: formData
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const statusText = isChecked ? 'Enabled' : 'Disabled';
            const toastType = isChecked ? 'success' : 'danger';
            showToast(toastType, `Auto Backup ${statusText}`);
          } else {
            // Revert toggle on error
            autoBackupSwitch.checked = !isChecked;
            autoBackupEnabled.value = !isChecked ? '1' : '0';
            showToast('danger', 'Failed to update status');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // Revert toggle on error
          autoBackupSwitch.checked = !isChecked;
          autoBackupEnabled.value = !isChecked ? '1' : '0';
          showToast('danger', 'Failed to update status');
        });
    });
  }
});
