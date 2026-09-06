/**
 * WhatsApp Template Management
 * Handles status toggle and variable mapping form
 */

'use strict';

(function () {
    // ==================== STATUS TOGGLE FUNCTIONALITY ====================

    const statusToggles = document.querySelectorAll('.status-toggle');

    statusToggles.forEach(toggle => {
        toggle.addEventListener('change', function () {
            const templateId = this.getAttribute('data-id');
            const isActive = this.checked;
            const statusBadge = document.querySelector(`.status-badge-${templateId}`);

            // Send AJAX request to toggle status
            fetch(`${window.baseUrl}templates/whatsapp/${templateId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    is_active: isActive
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update badge
                        if (statusBadge) {
                            statusBadge.textContent = data.is_active ? 'Active' : 'Inactive';
                            statusBadge.className = `badge rounded-pill bg-label-${data.is_active ? 'success' : 'secondary'} status-badge-${templateId}`;
                        }

                        // Show success message
                        showToast('success', data.message);
                    } else {
                        // Revert toggle on error
                        this.checked = !isActive;
                        showToast('danger', data.message || 'Failed to update template status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Revert toggle on error
                    this.checked = !isActive;
                    showToast('danger', 'An error occurred while updating the template status');
                });
        });
    });

    // ==================== DELETE TEMPLATE FUNCTIONALITY ====================

    const deleteButtons = document.querySelectorAll('.delete-template');
    const deleteModal = document.getElementById('deleteModal');
    const deleteTemplateName = document.getElementById('deleteTemplateName');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    let templateIdToDelete = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            templateIdToDelete = this.getAttribute('data-id');
            const templateName = this.getAttribute('data-name');

            // Set template name in modal
            if (deleteTemplateName) {
                deleteTemplateName.textContent = templateName;
            }

            // Show modal
            const modal = new bootstrap.Modal(deleteModal);
            modal.show();
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!templateIdToDelete) return;

            // Send DELETE request
            fetch(`${window.baseUrl}templates/whatsapp/${templateIdToDelete}/delete`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(response => response.json())
                .then(data => {
                    // Hide modal
                    const modal = bootstrap.Modal.getInstance(deleteModal);
                    modal.hide();

                    if (data.success) {
                        // Show success message
                        showToast('success', data.message);

                        // Reload page after 1 second
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast('danger', data.message || 'Failed to delete template');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const modal = bootstrap.Modal.getInstance(deleteModal);
                    modal.hide();
                    showToast('danger', 'An error occurred while deleting the template');
                });
        });
    }

    // ==================== VARIABLE MAPPING FORM FUNCTIONALITY ====================

    const variablesContainer = document.getElementById('variablesContainer');
    const addVariableBtn = document.getElementById('addVariable');
    const templateForm = document.getElementById('templateForm');
    const variablesJsonInput = document.getElementById('variablesJson');

    if (variablesContainer && addVariableBtn && templateForm) {
        // Get next position number
        function getNextPosition() {
            const positions = Array.from(document.querySelectorAll('.variable-position'))
                .map(input => parseInt(input.value) || 0);
            return positions.length > 0 ? Math.max(...positions) + 1 : 1;
        }

        // Add new variable row
        addVariableBtn.addEventListener('click', function () {
            const nextPosition = getNextPosition();
            const newRow = document.createElement('div');
            newRow.className = 'variable-row mb-3';
            newRow.innerHTML = `
        <div class="row g-2 align-items-center">
          <div class="col-md-1">
            <input type="text" class="form-control variable-position" 
              placeholder="Position" value="${nextPosition}" readonly>
          </div>
          <div class="col-md-10">
            <input type="text" class="form-control variable-name" 
              placeholder="Variable name (e.g., customer_name)">
          </div>
          <div class="col-md-1 text-center">
            <a href="javascript:void(0);" class="remove-variable" title="Remove">
              <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
            </a>
          </div>
        </div>
      `;
            variablesContainer.appendChild(newRow);
            attachRemoveHandler(newRow.querySelector('.remove-variable'));
        });

        // Remove variable row
        function attachRemoveHandler(button) {
            button.addEventListener('click', function () {
                const row = this.closest('.variable-row');
                row.remove();
                updatePositions();
            });
        }

        // Attach remove handlers to existing rows
        document.querySelectorAll('.remove-variable').forEach(button => {
            attachRemoveHandler(button);
        });

        // Update positions after removal
        function updatePositions() {
            const rows = document.querySelectorAll('.variable-row');
            rows.forEach((row, index) => {
                const positionInput = row.querySelector('.variable-position');
                positionInput.value = index + 1;
            });
        }

        // Convert variables to JSON before form submission
        templateForm.addEventListener('submit', function (e) {
            const variables = {};
            const rows = document.querySelectorAll('.variable-row');

            rows.forEach(row => {
                const position = row.querySelector('.variable-position').value;
                const name = row.querySelector('.variable-name').value.trim();

                if (position && name) {
                    variables[position] = name;
                }
            });

            // Set JSON value - send null if empty, otherwise JSON string
            if (Object.keys(variables).length > 0) {
                variablesJsonInput.value = JSON.stringify(variables);
            } else {
                variablesJsonInput.value = ''; // Empty string will be ignored by filled() check
            }
        });
    }

    // ==================== TOAST NOTIFICATION FUNCTION ====================

    /**
     * Show Toast Notification
     */
    function showToast(type, title, message) {
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
            iconClass = 'ri-error-warning-line';
            bgClass = 'bg-warning';
        } else {
            iconClass = 'ri-information-line';
            bgClass = 'bg-info';
        }

        // If no message provided, show title only (no body section)
        const toastHTML = message ? `
          <div id="${toastId}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
            <div class="toast-header ${bgClass} text-white rounded-top-5 border-0">
              <i class="icon-base ${iconClass} me-2"></i>
              <div class="me-auto fw-medium">${title}</div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body rounded-bottom-3">
              ${message}
            </div>
          </div>
        ` : `
          <div id="${toastId}" class="bs-toast toast fade show rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
            <div class="toast-header ${bgClass} text-white rounded-3 border-0">
              <i class="icon-base ${iconClass} me-2"></i>
              <div class="me-auto fw-medium">${title}</div>
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

        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    /**
     * Create Toast Container
     */
    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    // ==================== CHECK FOR ALERTS ON PAGE LOAD ====================

    // Check alert-container for session messages
    const alertContainer = document.querySelector('.alert-container');
    if (alertContainer) {
        const successMsg = alertContainer.getAttribute('data-success');
        const errorMsg = alertContainer.getAttribute('data-error');
        const validationMsg = alertContainer.getAttribute('data-validation');

        if (successMsg) {
            showToast('success', 'Success', successMsg);
        }

        if (errorMsg) {
            showToast('danger', 'Error', errorMsg);
        }

        if (validationMsg) {
            showToast('danger', 'Validation Error', validationMsg);
        }
    }

    // Check for success/error messages from meta tags (fallback)
    const successMeta = document.querySelector('meta[name="success-message"]');
    const errorMeta = document.querySelector('meta[name="error-message"]');

    if (successMeta) {
        showToast('success', 'Success', successMeta.getAttribute('content'));
    }

    if (errorMeta) {
        showToast('danger', 'Error', errorMeta.getAttribute('content'));
    }

    // ==================== FETCH GALLABOX TEMPLATES ====================

    const fetchGallaboxBtn = document.getElementById('fetchGallaboxBtn');
    const gallaboxModal = document.getElementById('gallaboxTemplatesModal');

    if (fetchGallaboxBtn && gallaboxModal) {
        fetchGallaboxBtn.addEventListener('click', function () {
            // Show modal
            const modal = new bootstrap.Modal(gallaboxModal);
            modal.show();

            // Reset modal state
            document.getElementById('gallaboxTemplatesLoading').classList.remove('d-none');
            document.getElementById('gallaboxTemplatesError').classList.add('d-none');
            document.getElementById('gallaboxTemplatesList').classList.add('d-none');

            // Fetch templates from Gallabox
            fetch(`${window.baseUrl}templates/whatsapp/fetch-gallabox`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(response => response.json())
                .then(data => {
                    // Hide loading
                    document.getElementById('gallaboxTemplatesLoading').classList.add('d-none');

                    if (data.success && data.templates && data.templates.length > 0) {
                        // Show templates list
                        document.getElementById('gallaboxTemplatesList').classList.remove('d-none');

                        // Populate table
                        const tbody = document.getElementById('gallaboxTemplatesBody');
                        tbody.innerHTML = '';

                        data.templates.forEach(template => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><strong>${template.name || 'N/A'}</strong></td>
                                <td>${template.language || 'en'}</td>
                                <td><span class="badge bg-label-success">${template.status || 'APPROVED'}</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary select-template" 
                                        data-name="${template.name || ''}"
                                        data-language="${template.language || 'en'}">
                                        <i class="ri-add-line me-1"></i> Select
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });

                        // Attach click handlers to select buttons
                        document.querySelectorAll('.select-template').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const templateName = this.getAttribute('data-name');
                                const language = this.getAttribute('data-language');

                                // Close modal
                                const modalInstance = bootstrap.Modal.getInstance(gallaboxModal);
                                modalInstance.hide();

                                // Redirect to create page with template name pre-filled
                                const createUrl = new URL('/templates/whatsapp/create', window.location.origin);
                                createUrl.searchParams.set('template_name', templateName);
                                createUrl.searchParams.set('language', language);
                                window.location.href = createUrl.toString();
                            });
                        });

                    } else {
                        // Show error
                        const errorDiv = document.getElementById('gallaboxTemplatesError');
                        errorDiv.textContent = data.message || 'No approved templates found in Gallabox.';
                        errorDiv.classList.remove('d-none');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('gallaboxTemplatesLoading').classList.add('d-none');
                    const errorDiv = document.getElementById('gallaboxTemplatesError');
                    errorDiv.textContent = 'Failed to fetch templates from Gallabox. Please check your API configuration.';
                    errorDiv.classList.remove('d-none');
                });
        });
    }
})();
