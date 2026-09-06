'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const accountForm = document.getElementById('formAccountSettings');
  if (!accountForm) {
    return;
  }

  // Initialize flatpickr for DOB
  const dobInput = document.getElementById('date_of_birth');
  if (dobInput && typeof flatpickr !== 'undefined') {
    flatpickr(dobInput, {
      dateFormat: 'd-m-Y',
      allowInput: true
    });
  }

  const submitBtn = accountForm.querySelector('button[type="submit"]');
  const cancelBtn = accountForm.querySelector('button[type="reset"]');
  const csrfToken = accountForm.querySelector('input[name="_token"]').value;
  const editToggleBtn = document.getElementById('enableAccountEditBtn');
  const formActions = document.getElementById('accountFormActions');
  const editableFields = accountForm.querySelectorAll('[data-editable="true"]');

  const sidebarName = document.getElementById('sidebarClientName');
  const sidebarStatusBadge = document.getElementById('sidebarClientStatusBadge');
  const sidebarEmail = document.getElementById('sidebarClientEmail');
  const sidebarPhone = document.getElementById('sidebarClientPhone');
  const sidebarAltPhone = document.getElementById('sidebarClientAlternatePhone');

  let isEditMode = false;

  const setEditableState = enable => {
    editableFields.forEach(field => {
      if (field.tagName === 'SELECT' || field.type === 'date' || field.classList.contains('flatpickr-dob')) {
        field.disabled = !enable;
      } else {
        field.readOnly = !enable;
        if (enable) {
          field.removeAttribute('readonly');
        } else {
          field.setAttribute('readonly', 'readonly');
        }
      }

      field.classList.toggle('text-muted', !enable);
      field.classList.toggle('bg-transparent', !enable);
    });

    if (formActions) {
      formActions.classList.toggle('d-none', !enable);
    }

    isEditMode = enable;
  };

  const updateDefaultValues = () => {
    editableFields.forEach(field => {
      if (field.tagName === 'SELECT') {
        Array.from(field.options).forEach(option => {
          option.defaultSelected = option.selected;
        });
      } else {
        field.defaultValue = field.value;
      }
    });
  };

  const enterEditMode = () => {
    if (isEditMode) return;
    setEditableState(true);
    if (editToggleBtn) {
      editToggleBtn.disabled = true;
      editToggleBtn.classList.add('active');
    }
  };

  const exitEditMode = () => {
    setEditableState(false);
    if (editToggleBtn) {
      editToggleBtn.disabled = false;
      editToggleBtn.classList.remove('active');
    }
  };

  setEditableState(false);
  updateDefaultValues();

  if (editToggleBtn) {
    editToggleBtn.addEventListener('click', () => {
      enterEditMode();
    });
  }

  accountForm.addEventListener('reset', () => {
    setTimeout(() => {
      exitEditMode();
    }, 0);
  });

  accountForm.addEventListener('submit', function (event) {
    event.preventDefault();

    const formData = new FormData(accountForm);
    const actionUrl = accountForm.getAttribute('action');

    const originalSubmitText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    if (cancelBtn) {
      cancelBtn.disabled = true;
    }

    fetch(actionUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    })
      .then(async response => {
        let responseData = {};

        try {
          responseData = await response.json();
        } catch (error) {
          const fallbackText = await response.text();
          throw new Error(fallbackText || 'Failed to update client details');
        }

        if (!response.ok || !responseData.success) {
          throw new Error(extractErrorMessage(responseData));
        }

        return responseData;
      })
      .then(data => {
        const updatedName = formData.get('client_name');
        const updatedEmail = formData.get('client_email');
        const updatedPhone = formData.get('client_phone');
        const updatedAltPhone = formData.get('alternate_phone');
        const updatedStatus = formData.get('status');

        if (sidebarName) sidebarName.textContent = updatedName;
        if (sidebarEmail) sidebarEmail.textContent = updatedEmail;
        if (sidebarPhone) sidebarPhone.textContent = updatedPhone;
        if (sidebarAltPhone) sidebarAltPhone.textContent = updatedAltPhone || 'N/A';
        if (sidebarStatusBadge) {
          // Map all 5 database status values to only 3 badge displays
          const statusLabels = {
            active: 'Active',
            verified: 'Active',
            inactive: 'Inactive',
            unverified: 'Inactive',
            blacklist: 'Blacklisted'
          };
          sidebarStatusBadge.textContent = statusLabels[updatedStatus] || 'Inactive';
          sidebarStatusBadge.className = `badge rounded-pill ${statusBadgeClass(updatedStatus)}`;
        }

        showAlert('success', data.message || 'Client profile updated successfully.');
        updateDefaultValues();
        exitEditMode();
      })
      .catch(error => {
        console.error('Account update error:', error);
        showAlert('danger', error.message || 'Failed to update client details.');
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalSubmitText;
        if (cancelBtn) {
          cancelBtn.disabled = false;
        }
      });
  });

  function statusBadgeClass(status) {
    if (status === 'active' || status === 'verified') return 'bg-label-success';
    if (status === 'inactive' || status === 'unverified') return 'bg-label-danger';
    if (status === 'blacklist') return 'bg-label-dark';
    return 'bg-label-danger'; // Default to inactive
  }

  function showAlert(type, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    const toastId = 'toast-' + Date.now();

    const iconMap = {
      success: 'ri-check-line',
      danger: 'ri-close-circle-line',
      warning: 'ri-alert-line',
      info: 'ri-information-line'
    };

    const bgMap = {
      success: 'bg-success',
      danger: 'bg-danger',
      warning: 'bg-warning',
      info: 'bg-info'
    };

    const iconClass = iconMap[type] || iconMap.danger;
    const bgClass = bgMap[type] || bgMap.danger;

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

    toastElement.addEventListener('hidden.bs.toast', () => {
      toastElement.remove();
    });
  }

  function extractErrorMessage(responseData) {
    if (!responseData || typeof responseData !== 'object') {
      return 'Failed to update client details';
    }

    if (responseData.message) {
      return responseData.message;
    }

    if (responseData.errors) {
      const firstKey = Object.keys(responseData.errors)[0];
      if (firstKey && Array.isArray(responseData.errors[firstKey])) {
        return responseData.errors[firstKey][0];
      }
    }

    return 'Failed to update client details';
  }

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }

  // Zone change listener for auto-population
  const locationSelect = document.getElementById('location_id');
  if (locationSelect) {
    locationSelect.addEventListener('change', function () {
      const selected = this.options[this.selectedIndex];
      if (selected && selected.value) {
        const city = selected.getAttribute('data-city');
        const state = selected.getAttribute('data-state');
        const pincode = selected.getAttribute('data-pincode');

        const cityInput = accountForm.querySelector('input[name="city"]');
        const stateInput = accountForm.querySelector('input[name="state"]');
        const pincodeInput = accountForm.querySelector('input[name="pincode"]');

        if (cityInput) cityInput.value = city || '';
        if (stateInput) stateInput.value = state || '';
        if (pincodeInput && pincode) pincodeInput.value = pincode;
      }
    });
  }
});
