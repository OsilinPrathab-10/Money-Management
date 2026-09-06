/**
 * Agent View Account
 */

'use strict';

(function () {
    const formAccountSettings = document.querySelector('#formAccountSettings');
    const enableAccountEditBtn = document.querySelector('#enableAccountEditBtn');
    const accountFormActions = document.querySelector('#accountFormActions');

    // Enable edit mode
    if (enableAccountEditBtn) {
        enableAccountEditBtn.addEventListener('click', function () {
            // Get all editable fields
            const editableFields = formAccountSettings.querySelectorAll('[data-editable="true"]');

            editableFields.forEach(field => {
                if (field.tagName === 'SELECT') {
                    field.disabled = false;
                } else {
                    field.readOnly = false;
                }
                field.classList.add('is-editable');
            });

            // Show form actions
            accountFormActions.classList.remove('d-none');

            // Hide edit button
            enableAccountEditBtn.style.display = 'none';
        });
    }

    // Handle cancel button
    if (formAccountSettings) {
        const cancelBtn = formAccountSettings.querySelector('button[type="reset"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function (e) {
                e.preventDefault();

                // Reset form
                formAccountSettings.reset();

                // Disable all editable fields
                const editableFields = formAccountSettings.querySelectorAll('[data-editable="true"]');
                editableFields.forEach(field => {
                    if (field.tagName === 'SELECT') {
                        field.disabled = true;
                    } else {
                        field.readOnly = true;
                    }
                    field.classList.remove('is-editable');
                });

                // Hide form actions
                accountFormActions.classList.add('d-none');

                // Show edit button
                enableAccountEditBtn.style.display = 'block';
            });
        }
    }

    // Handle form submission
    if (formAccountSettings) {
        formAccountSettings.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;

            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success alert
                        showAlert('success', data.message || 'Agent profile updated successfully');

                        // Update sidebar information
                        const agentName = document.querySelector('#agent_name').value;
                        const agentEmail = document.querySelector('#agent_email').value;
                        const agentPhone = document.querySelector('#agent_phone').value;
                        const status = document.querySelector('#status').value;

                        document.querySelector('#sidebarAgentName').textContent = agentName;
                        document.querySelector('#sidebarAgentEmail').textContent = agentEmail;
                        document.querySelector('#sidebarAgentPhone').textContent = agentPhone;

                        // Update status badge
                        const statusBadge = document.querySelector('#sidebarAgentStatusBadge');
                        if (status === 'active') {
                            statusBadge.className = 'badge bg-label-success rounded-pill';
                            statusBadge.textContent = 'Active';
                        } else {
                            statusBadge.className = 'badge bg-label-danger rounded-pill';
                            statusBadge.textContent = 'Inactive';
                        }

                        // Disable all editable fields
                        const editableFields = formAccountSettings.querySelectorAll('[data-editable="true"]');
                        editableFields.forEach(field => {
                            if (field.tagName === 'SELECT') {
                                field.disabled = true;
                            } else {
                                field.readOnly = true;
                            }
                            field.classList.remove('is-editable');
                        });

                        // Hide form actions
                        accountFormActions.classList.add('d-none');

                        // Show edit button
                        enableAccountEditBtn.style.display = 'block';
                    } else {
                        showAlert('danger', data.message || 'Failed to update agent profile');
                    }

                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while updating the profile');

                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                });
        });
    }

    // Alert notification function
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
})();
