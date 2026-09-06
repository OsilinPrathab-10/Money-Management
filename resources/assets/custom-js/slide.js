/**
 * Slides Management
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

  // Flash messages from meta tags
  const successMeta = document.querySelector('meta[name="success-message"]');
  const errorMeta = document.querySelector('meta[name="error-message"]');

  if (successMeta && successMeta.content) {
    showToast('success', successMeta.content);
  }

  if (errorMeta && errorMeta.content) {
    showToast('danger', errorMeta.content);
  }

  // Delete functionality
  const deleteModalElement = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

  if (deleteModalElement && confirmDeleteBtn) {
    const deleteModal = new bootstrap.Modal(deleteModalElement);
    let deleteSlideId = null;

    document.querySelectorAll('.delete-slide').forEach(btn => {
      btn.addEventListener('click', function () {
        deleteSlideId = this.getAttribute('data-id');
        const slideTitle = this.getAttribute('data-title');
        const titleTarget = document.getElementById('deleteSlideTitle');
        if (titleTarget) {
          titleTarget.textContent = slideTitle;
        }
        deleteModal.show();
      });
    });

    confirmDeleteBtn.addEventListener('click', function () {
      if (!deleteSlideId) return;

      fetch(baseUrl + 'setup-app/slides/delete/' + deleteSlideId, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      })
        .then(response => response.json())
        .then(data => {
          deleteModal.hide();

          if (data.success) {
            showToast('success', data.message);
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            showToast('danger', data.message || 'Failed to delete slide');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          deleteModal.hide();
          showToast('danger', 'Failed to delete slide. Please try again.');
        });
    });
  }

  // Dynamic helper text for image recommendations
  const typeSelect = document.getElementById('type');
  const imageHelperText = document.getElementById('imageHelperText');
  const helperCopy = {
    onboarding: 'Accepted formats: JPG, PNG, GIF | Recommended size: 500x500px | Max size: 2MB',
    banner: 'Accepted formats: JPG, PNG, GIF | Recommended size: 378x208px | Max size: 2MB',
    other: 'Accepted formats: JPG, PNG, GIF | Max size: 2MB'
  };

  function updateImageHelper(value) {
    if (!imageHelperText) return;
    const text = helperCopy[value] || '';
    imageHelperText.textContent = text;
    if (text) {
      imageHelperText.classList.remove('d-none');
    } else {
      imageHelperText.classList.add('d-none');
    }
  }

  if (typeSelect) {
    updateImageHelper(typeSelect.value);
    typeSelect.addEventListener('change', event => {
      updateImageHelper(event.target.value);
    });
  }
});
