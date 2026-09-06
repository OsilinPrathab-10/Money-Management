(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // ================= API Toggle Logic =================
    const apiToggles = document.querySelectorAll('.api-toggle');

    apiToggles.forEach(toggle => {
        toggle.addEventListener('change', function () {
            const isEnabled = this.checked;
            const service = this.dataset.service;
            const badgeId = this.dataset.badge;
            const toggleEl = this;

            toggleEl.disabled = true;

            // Prepare payload
            // We need to send 'is_enabled' and 'active_service'
            // The controller expects these fields to update the status
            const payload = {
                is_enabled: isEnabled,
                active_service: service
            };

            fetch(`/setup-configuration/api-configuration/${service}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            })
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to update status');
                    }
                    return data;
                })
                .then(data => {
                    // Update Badge
                    const badge = document.getElementById(badgeId);
                    if (badge) {
                        badge.classList.toggle('bg-label-success', isEnabled);
                        badge.classList.toggle('bg-label-secondary', !isEnabled);
                        badge.textContent = isEnabled ? 'Active' : 'Inactive';
                    }
                    showAlert('success', data.message || 'Status updated successfully');
                })
                .catch(error => {
                    console.error('Error:', error);
                    toggleEl.checked = !isEnabled; // Revert toggle
                    showAlert('danger', error.message);
                })
                .finally(() => {
                    toggleEl.disabled = false;
                });
        });
    });

    // ================= Modal Save Logic =================
    const saveButtons = document.querySelectorAll('.save-api-btn');

    saveButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const formId = this.dataset.form;
            const form = document.getElementById(formId);

            if (!form) return;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const btnEl = this;
            const originalHTML = btnEl.innerHTML;
            btnEl.disabled = true;
            btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action');

            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            })
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to save configuration');
                    }
                    return data;
                })
                .then(data => {
                    showAlert('success', data.message || 'Configuration saved successfully');

                    // Close Modal
                    const modalEl = btnEl.closest('.modal');
                    if (modalEl) {
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }

                    // Optionally reload or update UI if needed
                    // For now, we just show success message
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', error.message);
                })
                .finally(() => {
                    btnEl.disabled = false;
                    btnEl.innerHTML = originalHTML;
                });
        });
    });

    // ================= Toast Notification Helper =================
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
        } else {
            iconClass = 'ri-information-line';
            bgClass = 'bg-info';
        }

        const toastHTML = `
      <div id="${toastId}" class="bs-toast toast fade show rounded-3 shadow-lg border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header ${bgClass} text-white">
          <i class="${iconClass} me-2"></i>
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

})();
