/**
 * Email Template Management
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

    // Show flash messages from server-side sessions
    const successMessage = document.querySelector('meta[name="success-message"]');
    const errorMessage = document.querySelector('meta[name="error-message"]');

    if (successMessage) {
        showToast('success', successMessage.getAttribute('content'));
    }

    if (errorMessage) {
        showToast('danger', errorMessage.getAttribute('content'));
    }

    // Delete template functionality
    const deleteButtons = document.querySelectorAll('.delete-template');
    const deleteModal = document.getElementById('deleteModal');
    const deleteTemplateName = document.getElementById('deleteTemplateName');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    let templateToDelete = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            templateToDelete = this.getAttribute('data-id');
            const templateName = this.getAttribute('data-name');

            deleteTemplateName.textContent = templateName;

            const modal = new bootstrap.Modal(deleteModal);
            modal.show();
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!templateToDelete) return;

            // Show loading state
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deleting...';
            this.disabled = true;

            fetch(`${baseUrl}templates/email/${templateToDelete}/delete`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
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
                        throw new Error(data.message || `HTTP ${response.status}`);
                    }
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message);

                        // Close modal
                        const modal = bootstrap.Modal.getInstance(deleteModal);
                        modal.hide();

                        // Reload page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast('danger', data.message || 'Failed to delete template');

                        // Reset button
                        this.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('danger', 'An error occurred while deleting the template');

                    // Reset button
                    this.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
                    this.disabled = false;
                });
        });
    }

    // Auto-generate identifier from name
    const nameInput = document.getElementById('name');
    const identifierInput = document.getElementById('identifier');

    if (nameInput && identifierInput) {
        nameInput.addEventListener('input', function () {
            // Only auto-generate if identifier is empty (for new templates)
            if (!identifierInput.value.trim()) {
                const identifier = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9\s]/g, '') // Remove special characters
                    .replace(/\s+/g, '_') // Replace spaces with underscores
                    .replace(/_+/g, '_') // Replace multiple underscores with single
                    .replace(/^_|_$/g, ''); // Remove leading/trailing underscores

                identifierInput.value = identifier;
            }
        });
    }

    // Character counter for email body
    const emailBodyTextarea = document.getElementById('email_body');
    if (emailBodyTextarea) {
        // Create character counter
        const counterDiv = document.createElement('div');
        counterDiv.className = 'text-end mt-1';
        counterDiv.innerHTML = `<small class="text-muted"><span id="charCount">0</span> characters</small>`;
        emailBodyTextarea.parentNode.appendChild(counterDiv);

        const charCount = document.getElementById('charCount');

        function updateCharCount() {
            const length = emailBodyTextarea.value.length;
            charCount.textContent = length;
        }

        emailBodyTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
    }

    // Image preview
    const imageInput = document.getElementById('image');
    if (imageInput) {
        imageInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showToast('warning', 'Image size should not exceed 2MB');
                    this.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function (e) {
                    let preview = document.getElementById('imagePreview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'imagePreview';
                        preview.className = 'mt-3';
                        imageInput.parentNode.appendChild(preview);
                    }
                    preview.innerHTML = `
            <img src="${e.target.result}" alt="Preview" class="img-thumbnail" style="max-width: 200px;">
            <p class="text-muted small mt-2">Image preview</p>
          `;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
