/**
 * Website Appearance Settings
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

  // Color picker functionality
  const colorPicker = document.getElementById('colorPicker');
  const hexInput = document.getElementById('hexInput');
  const colorPreview = document.getElementById('colorPreview');

  if (colorPicker && hexInput && colorPreview) {
    // Color picker change
    colorPicker.addEventListener('input', function () {
      const color = this.value;
      hexInput.value = color;
      colorPreview.style.backgroundColor = color;
      applyPrimaryColor(color);
    });

    // Hex input change
    hexInput.addEventListener('input', function () {
      const color = this.value;
      if (isValidHex(color)) {
        colorPicker.value = color;
        colorPreview.style.backgroundColor = color;
        applyPrimaryColor(color);
      }
    });

    // Hex input blur validation
    hexInput.addEventListener('blur', function () {
      let color = this.value;
      if (!color.startsWith('#')) {
        color = '#' + color;
      }
      if (isValidHex(color)) {
        this.value = color;
        colorPicker.value = color;
        colorPreview.style.backgroundColor = color;
        applyPrimaryColor(color);
      } else {
        // Reset to current picker value if invalid
        this.value = colorPicker.value;
      }
    });
  }

  // Secondary color picker functionality
  const secondaryColorPicker = document.getElementById('secondaryColorPicker');
  const secondaryHexInput = document.getElementById('secondaryHexInput');
  const secondaryColorPreview = document.getElementById('secondaryColorPreview');

  if (secondaryColorPicker && secondaryHexInput && secondaryColorPreview) {
    // Color picker change
    secondaryColorPicker.addEventListener('input', function () {
      const color = this.value;
      secondaryHexInput.value = color;
      secondaryColorPreview.style.backgroundColor = color;
    });

    // Hex input change
    secondaryHexInput.addEventListener('input', function () {
      const color = this.value;
      if (isValidHex(color)) {
        secondaryColorPicker.value = color;
        secondaryColorPreview.style.backgroundColor = color;
      }
    });

    // Hex input blur validation
    secondaryHexInput.addEventListener('blur', function () {
      let color = this.value;
      if (!color.startsWith('#')) {
        color = '#' + color;
      }
      if (isValidHex(color)) {
        this.value = color;
        secondaryColorPicker.value = color;
        secondaryColorPreview.style.backgroundColor = color;
      } else {
        // Reset to current picker value if invalid
        this.value = secondaryColorPicker.value;
      }
    });
  }

  // Validate hex color
  function isValidHex(hex) {
    return /^#[0-9A-Fa-f]{6}$/.test(hex);
  }

  // Theme options
  const themeOptions = document.querySelectorAll('.theme-option');
  themeOptions.forEach(option => {
    option.addEventListener('change', function () {
      // Remove active class from all theme labels
      themeOptions.forEach(opt => {
        const label = opt.nextElementSibling;
        if (label) {
          const span = label.querySelector('span');
          if (span) {
            span.classList.remove('btn-primary');
            span.classList.add('btn-outline-secondary');
          }
        }
      });

      // Add active class to selected theme
      if (this.checked) {
        const label = this.nextElementSibling;
        if (label) {
          const span = label.querySelector('span');
          if (span) {
            span.classList.remove('btn-outline-secondary');
            span.classList.add('btn-primary');
          }
        }

        // Apply theme immediately
        applyTheme(this.value);

        // Store theme preference
        localStorage.setItem('theme-preference', this.value);
      }
    });
  });

  // Apply primary color
  function applyPrimaryColor(color) {
    const r = parseInt(color.substr(1, 2), 16);
    const g = parseInt(color.substr(3, 2), 16);
    const b = parseInt(color.substr(5, 2), 16);

    // Calculate contrast color
    const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
    const contrastColor = (yiq >= 150) ? '#000' : '#fff';

    document.documentElement.style.setProperty('--bs-primary', color);
    document.documentElement.style.setProperty('--bs-primary-rgb', `${r}, ${g}, ${b}`);
    document.documentElement.style.setProperty('--bs-primary-bg-subtle', `rgba(${r}, ${g}, ${b}, 0.1)`);
    document.documentElement.style.setProperty('--bs-primary-border-subtle', `rgba(${r}, ${g}, ${b}, 0.3)`);
    document.documentElement.style.setProperty('--bs-primary-contrast', contrastColor);
  }

  // Apply theme mode
  function applyTheme(theme) {
    const html = document.documentElement;

    if (theme === 'dark') {
      html.setAttribute('data-bs-theme', 'dark');
      html.classList.add('dark-style');
      html.classList.remove('light-style');
    } else if (theme === 'light') {
      html.setAttribute('data-bs-theme', 'light');
      html.classList.add('light-style');
      html.classList.remove('dark-style');
    } else {
      // System theme
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (prefersDark) {
        html.setAttribute('data-bs-theme', 'dark');
        html.classList.add('dark-style');
        html.classList.remove('light-style');
      } else {
        html.setAttribute('data-bs-theme', 'light');
        html.classList.add('light-style');
        html.classList.remove('dark-style');
      }
    }
  }

  // Helper: Convert hex to RGB
  function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result
      ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}`
      : '105, 108, 255';
  }

  // Reset to default
  const resetBtn = document.getElementById('resetBtn');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (confirm('Are you sure you want to reset to default settings?')) {
        // Reset to default color
        const defaultColor = '#696cff';
        if (colorPicker) {
          colorPicker.value = defaultColor;
        }
        if (hexInput) {
          hexInput.value = defaultColor;
        }
        if (colorPreview) {
          colorPreview.style.backgroundColor = defaultColor;
        }
        applyPrimaryColor(defaultColor);

        // Reset secondary color to default
        const defaultSecondaryColor = '#8592a3';
        if (secondaryColorPicker) {
          secondaryColorPicker.value = defaultSecondaryColor;
        }
        if (secondaryHexInput) {
          secondaryHexInput.value = defaultSecondaryColor;
        }
        if (secondaryColorPreview) {
          secondaryColorPreview.style.backgroundColor = defaultSecondaryColor;
        }

        // Reset to light theme
        const lightThemeOption = document.getElementById('themeLight');
        if (lightThemeOption) {
          lightThemeOption.checked = true;
          lightThemeOption.dispatchEvent(new Event('change'));
        }

        showToast('info', 'Settings reset to default. Click Save to apply.');
      }
    });
  }

  // Form submission
  const appearanceForm = document.getElementById('appearanceForm');
  if (appearanceForm) {
    appearanceForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showToast('success', data.message || 'Settings saved successfully!');

            // Reload after 1.5 seconds to apply changes
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            showToast('danger', data.message || 'Failed to save settings');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('danger', 'Failed to save settings. Please try again.');
        });
    });
  }

  // Initialize theme on page load
  const savedTheme = localStorage.getItem('theme-preference');
  if (savedTheme) {
    const themeOption = document.getElementById('theme' + savedTheme.charAt(0).toUpperCase() + savedTheme.slice(1));
    if (themeOption) {
      themeOption.checked = true;
      themeOption.dispatchEvent(new Event('change'));
    }
  }

  // Apply current primary color on load
  const initialColorPicker = document.getElementById('colorPicker');
  if (initialColorPicker && initialColorPicker.value) {
    applyPrimaryColor(initialColorPicker.value);
  }
});
