'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const pageCsrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

  // Blacklist form (client view) — must work even if account form is absent
  const blacklistForm = document.getElementById('blacklistForm');
  if (blacklistForm) {
    blacklistForm.addEventListener('submit', function (event) {
      event.preventDefault();

      const reasonInput = document.getElementById('blacklist_reason');
      const submitBtn = document.getElementById('blacklistSubmitBtn')
        || blacklistForm.querySelector('button[type="submit"]');
      const reason = (reasonInput?.value || '').trim();

      if (reasonInput) {
        reasonInput.classList.toggle('is-invalid', !reason);
      }
      if (!reason) {
        showAlert('warning', 'Please provide a reason for blacklisting.');
        return;
      }

      const originalHtml = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Blacklisting...';
      }

      const tokenInput = blacklistForm.querySelector('input[name="_token"]');
      const csrfToken = tokenInput?.value || pageCsrf;

      fetch(blacklistForm.getAttribute('action'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(blacklistForm)
      })
        .then(async response => {
          let data = {};
          try {
            data = await response.json();
          } catch (e) {
            throw new Error('Failed to blacklist client.');
          }
          if (!response.ok || data.success === false) {
            const msg = data.message
              || (data.errors && Object.values(data.errors).flat()[0])
              || 'Failed to blacklist client.';
            throw new Error(msg);
          }
          return data;
        })
        .then(data => {
          const modalEl = document.getElementById('blacklistModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            instance.hide();
          }
          showAlert('success', data.message || 'Client has been blacklisted successfully.');
          setTimeout(() => window.location.reload(), 1200);
        })
        .catch(error => {
          showAlert('danger', error.message || 'Failed to blacklist client.');
        })
        .finally(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
          }
        });
    });
  }

  // Unblacklist form
  const unblacklistForm = document.getElementById('unblacklistForm');
  if (unblacklistForm) {
    unblacklistForm.addEventListener('submit', function (event) {
      event.preventDefault();

      const submitBtn = document.getElementById('unblacklistSubmitBtn')
        || unblacklistForm.querySelector('button[type="submit"]');
      const originalHtml = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
      }

      const tokenInput = unblacklistForm.querySelector('input[name="_token"]');
      const csrfToken = tokenInput?.value || pageCsrf;

      fetch(unblacklistForm.getAttribute('action'), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new FormData(unblacklistForm)
      })
        .then(async response => {
          let data = {};
          try {
            data = await response.json();
          } catch (e) {
            throw new Error('Failed to unblacklist client.');
          }
          if (!response.ok || data.success === false) {
            const msg = data.message
              || (data.errors && Object.values(data.errors).flat()[0])
              || 'Failed to unblacklist client.';
            throw new Error(msg);
          }
          return data;
        })
        .then(data => {
          const modalEl = document.getElementById('unblacklistModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            instance.hide();
          }
          showAlert('success', data.message || 'Client removed from blacklist.');
          setTimeout(() => window.location.reload(), 1200);
        })
        .catch(error => {
          showAlert('danger', error.message || 'Failed to unblacklist client.');
        })
        .finally(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
          }
        });
    });
  }

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
  const sidebarNickname = document.getElementById('sidebarClientNickname');
  const sidebarNicknameDetail = document.getElementById('sidebarClientNicknameDetail');
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
        const updatedNickname = formData.get('nickname');
        const updatedEmail = formData.get('client_email');
        const updatedPhone = formData.get('client_phone');
        const updatedAltPhone = formData.get('alternate_phone');
        const updatedStatus = formData.get('status');

        if (sidebarName) sidebarName.textContent = updatedName;
        if (sidebarNickname) sidebarNickname.textContent = updatedNickname ? '(' + updatedNickname + ')' : '';
        if (sidebarNicknameDetail) sidebarNicknameDetail.textContent = updatedNickname || 'N/A';
        if (sidebarEmail) sidebarEmail.textContent = updatedEmail;
        if (sidebarPhone) sidebarPhone.textContent = updatedPhone;
        if (sidebarAltPhone) sidebarAltPhone.textContent = updatedAltPhone || 'N/A';
        if (sidebarStatusBadge) {
          // Always trust the status returned by the server:
          // a client stays "pending" until KYC is verified by admin.
          const effectiveStatus = data.status || updatedStatus;
          const statusLabels = {
            active: 'Active',
            verified: 'Active',
            pending: 'Pending',
            unverified: 'Pending',
            inactive: 'Inactive',
            rejected: 'Rejected',
            blacklist: 'Blacklisted'
          };
          sidebarStatusBadge.textContent = statusLabels[effectiveStatus] || 'Pending';
          sidebarStatusBadge.className = `badge rounded-pill ${statusBadgeClass(effectiveStatus)}`;

          const statusSelect = document.getElementById('status');
          if (statusSelect && data.status) {
            statusSelect.value = data.status;
          }
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
    if (status === 'pending' || status === 'unverified') return 'bg-label-warning';
    if (status === 'inactive' || status === 'rejected') return 'bg-label-danger';
    if (status === 'blacklist') return 'bg-label-dark';
    return 'bg-label-warning'; // Default to pending
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
