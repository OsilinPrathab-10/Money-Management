/**
 * Agent View Account
 */

'use strict';

(function () {
    const formAccountSettings = document.querySelector('#formAccountSettings');
    const enableAccountEditBtn = document.querySelector('#enableAccountEditBtn');
    const accountFormActions = document.querySelector('#accountFormActions');

    function setAccountFieldsEditable(editable) {
        if (!formAccountSettings) return;

        const editableFields = formAccountSettings.querySelectorAll('[data-editable="true"]');
        editableFields.forEach(field => {
            if (field.tagName === 'SELECT') {
                field.disabled = !editable;
            } else {
                if (editable) {
                    field.removeAttribute('readonly');
                    field.readOnly = false;
                } else {
                    field.setAttribute('readonly', 'readonly');
                    field.readOnly = true;
                }
            }

            if (editable) {
                field.classList.add('is-editable');
            } else {
                field.classList.remove('is-editable');
            }
        });

        // Password fields must be fully writable in edit mode
        document.querySelectorAll('.password-edit-section').forEach(section => {
            section.classList.toggle('d-none', !editable);
        });

        ['password', 'password_confirmation'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.removeAttribute('readonly');
            el.removeAttribute('disabled');
            el.readOnly = false;
            el.disabled = false;
            if (!editable) {
                el.value = '';
            }
        });
    }

    // Enable edit mode
    if (enableAccountEditBtn) {
        enableAccountEditBtn.addEventListener('click', function () {
            setAccountFieldsEditable(true);

            // Show form actions
            accountFormActions.classList.remove('d-none');

            // Hide edit button
            enableAccountEditBtn.style.display = 'none';

            // Focus new password so user can type immediately
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                setTimeout(() => passwordInput.focus(), 50);
            }
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

                setAccountFieldsEditable(false);

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

            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            const passwordError = document.getElementById('passwordError');
            const confirmError = document.getElementById('passwordConfirmationError');

            if (passwordError) passwordError.textContent = '';
            if (confirmError) confirmError.textContent = '';
            if (passwordInput) passwordInput.classList.remove('is-invalid');
            if (confirmInput) confirmInput.classList.remove('is-invalid');

            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = confirmInput ? confirmInput.value : '';

            if (password || confirmPassword) {
                let passwordInvalid = false;
                if (!password) {
                    if (passwordInput) passwordInput.classList.add('is-invalid');
                    if (passwordError) passwordError.textContent = 'New password is required';
                    passwordInvalid = true;
                } else if (password.length < 8) {
                    if (passwordInput) passwordInput.classList.add('is-invalid');
                    if (passwordError) passwordError.textContent = 'Password must be at least 8 characters';
                    passwordInvalid = true;
                }

                if (!confirmPassword) {
                    if (confirmInput) confirmInput.classList.add('is-invalid');
                    if (confirmError) confirmError.textContent = 'Please confirm the new password';
                    passwordInvalid = true;
                } else if (password !== confirmPassword) {
                    if (confirmInput) confirmInput.classList.add('is-invalid');
                    if (confirmError) confirmError.textContent = 'Passwords do not match';
                    passwordInvalid = true;
                }

                if (passwordInvalid) {
                    return;
                }
            }

            const ifscInput = document.getElementById('ifsc_code');
            if (ifscInput) ifscInput.classList.remove('is-invalid');
            const ifscValue = ifscInput ? ifscInput.value.trim().toUpperCase() : '';
            if (ifscValue && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifscValue)) {
                ifscInput.classList.add('is-invalid');
                if (typeof showAlert === 'function') {
                    showAlert('danger', 'Please enter a valid IFSC code (e.g. SBIN0001234)');
                }
                return;
            }

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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const firstError = data.errors
                            ? Object.values(data.errors).flat()[0]
                            : (data.message || 'Failed to update agent profile');

                        if (data.errors?.password?.[0] && passwordError) {
                            passwordInput?.classList.add('is-invalid');
                            passwordError.textContent = data.errors.password[0];
                        }
                        if (data.errors?.password_confirmation?.[0] && confirmError) {
                            confirmInput?.classList.add('is-invalid');
                            confirmError.textContent = data.errors.password_confirmation[0];
                        }

                        throw new Error(firstError);
                    }
                    return data;
                })
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

                        // Clear password fields after successful save
                        if (passwordInput) passwordInput.value = '';
                        if (confirmInput) confirmInput.value = '';

                        setAccountFieldsEditable(false);

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
                    showAlert('danger', error.message || 'An error occurred while updating the profile');

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
