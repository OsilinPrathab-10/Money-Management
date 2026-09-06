/**
 * Appearance Settings
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

  // Color picker synchronization
  const primaryColorInput = document.getElementById('primary_color');
  const primaryColorText = document.getElementById('primary_color_text');
  const primaryColorPreview = document.getElementById('primaryColorPreview');
  const secondaryColorInput = document.getElementById('secondary_color');
  const secondaryColorText = document.getElementById('secondary_color_text');
  const secondaryColorPreview = document.getElementById('secondaryColorPreview');

  const hexPattern = /^#[0-9A-Fa-f]{6}$/;

  function syncColorInputs(colorInput, textInput, previewEl) {
    if (!colorInput || !textInput) return;

    const updatePreview = value => {
      if (previewEl) {
        previewEl.style.backgroundColor = value;
      }
    };

    colorInput.addEventListener('input', function () {
      textInput.value = this.value;
      updatePreview(this.value);
    });

    textInput.addEventListener('input', function () {
      const value = this.value.trim();
      if (hexPattern.test(value)) {
        colorInput.value = value;
        updatePreview(value);
      }
    });

    textInput.addEventListener('blur', function () {
      if (!hexPattern.test(this.value.trim())) {
        this.value = colorInput.value;
      }
    });

    updatePreview(colorInput.value);
  }

  syncColorInputs(primaryColorInput, primaryColorText, primaryColorPreview);
  syncColorInputs(secondaryColorInput, secondaryColorText, secondaryColorPreview);

  // Image preview helpers
  function attachImagePreview(inputEl, imageEl, placeholderEl) {
    if (!inputEl || !imageEl || !placeholderEl) {
      return;
    }

    inputEl.addEventListener('change', function () {
      const file = this.files && this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (event) {
          imageEl.src = event.target.result;
          imageEl.style.display = 'block';
          placeholderEl.style.display = 'none';
        };
        reader.readAsDataURL(file);
      } else {
        imageEl.src = '';
        imageEl.style.display = 'none';
        placeholderEl.style.display = '';
      }
    });
  }

  const logoInput = document.getElementById('logo');
  const logoPreviewImage = document.getElementById('logoPreviewImage');
  const logoPreviewPlaceholder = document.getElementById('logoPreviewPlaceholder');
  attachImagePreview(logoInput, logoPreviewImage, logoPreviewPlaceholder);

  const faviconInput = document.getElementById('favicon');
  const faviconPreviewImage = document.getElementById('faviconPreviewImage');
  const faviconPreviewPlaceholder = document.getElementById('faviconPreviewPlaceholder');
  attachImagePreview(faviconInput, faviconPreviewImage, faviconPreviewPlaceholder);

  // Reset appearance (colors + previews) to defaults
  const resetAppearanceBtn = document.getElementById('resetAppearanceBtn');
  if (resetAppearanceBtn) {
    resetAppearanceBtn.addEventListener('click', function () {
      if (!confirm('Are you sure you want to reset appearance to default?')) return;

      const defaultPrimary = '#696cff';
      const defaultSecondary = '#8592a3';

      if (primaryColorInput) primaryColorInput.value = defaultPrimary;
      if (primaryColorText) primaryColorText.value = defaultPrimary;
      if (primaryColorPreview) primaryColorPreview.style.backgroundColor = defaultPrimary;

      if (secondaryColorInput) secondaryColorInput.value = defaultSecondary;
      if (secondaryColorText) secondaryColorText.value = defaultSecondary;
      if (secondaryColorPreview) secondaryColorPreview.style.backgroundColor = defaultSecondary;

      showToast('info', 'Appearance reset to default. Click Save Changes to apply.');
    });
  }
});
