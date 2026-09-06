/**
 * Loan Products DataTable
 */

'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function (e) {
  let borderColor, bodyBg, headingColor;

  borderColor = config.colors.borderColor;
  bodyBg = config.colors.bodyBg;
  headingColor = config.colors.headingColor;

  // Prevent multiple form submissions
  const createProductForm = document.getElementById('createProductForm');
  const submitProductBtn = document.getElementById('submitProductBtn');
  let isSubmitting = false;

  if (createProductForm && submitProductBtn) {
    createProductForm.addEventListener('submit', function (e) {
      if (isSubmitting) {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }

      isSubmitting = true;
      submitProductBtn.disabled = true;
      submitProductBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
    });

    // Reset on modal close
    const createProductModal = document.getElementById('createProductModal');
    if (createProductModal) {
      createProductModal.addEventListener('hidden.bs.modal', function () {
        isSubmitting = false;
        submitProductBtn.disabled = false;
        submitProductBtn.innerHTML = '<i class="icon-base ri ri-save-line me-1"></i> Save Product';

        // Clear form values and validation states after closing the modal
        const inputs = createProductForm.querySelectorAll('input');
        inputs.forEach(function (input) {
          if (input.type === 'hidden') {
            return;
          }

          if (input.type === 'checkbox' || input.type === 'radio') {
            input.checked = false;
          } else {
            input.value = '';
          }
          input.classList.remove('is-invalid', 'is-valid');
        });

        const selects = createProductForm.querySelectorAll('select');
        selects.forEach(function (select) {
          select.value = '';
          select.classList.remove('is-invalid', 'is-valid');
          select.dispatchEvent(new Event('change'));
        });

        const textareas = createProductForm.querySelectorAll('textarea');
        textareas.forEach(function (textarea) {
          textarea.value = '';
          textarea.classList.remove('is-invalid', 'is-valid');
        });

        const feedbackMessages = createProductForm.querySelectorAll('.invalid-feedback');
        feedbackMessages.forEach(function (feedback) {
          feedback.style.display = 'none';
        });
      });
    }
  }

  // Variable declaration for table
  const dt_loan_products_table = document.querySelector('.datatables-loan-products');
  const alertContainer = document.querySelector('.alert-container');

  // Loan Products datatable
  if (dt_loan_products_table) {
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

    const dt_loan_products = new DataTable(dt_loan_products_table, {
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'loan/loan-products/data',
        dataSrc: 'data'
      },
      columns: [
        { 
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        {
          // Loan Code
          targets: 1,
          render: function (data, type, full, meta) {
            return `<span class="fw-medium">${full.loan_code}</span>`;
          }
        },
        {
          // Name
          targets: 2,
          responsivePriority: 4,
          render: function (data, type, full, meta) {
            return `<span class="text-heading">${full.name}</span>`;
          }
        },
        {
          // Status
          targets: 3,
          render: function (data, type, full, meta) {
            const status = full.status;
            const checked = status === 'Active' ? 'checked' : '';
            const validClass = status === 'Active' ? 'is-valid' : 'is-invalid';

            return `
              <label class="switch">
                <input type="checkbox" class="switch-input ${validClass} status-switch" ${checked} data-id="${full.id}" />
                <span class="switch-toggle-slider">
                  <span class="switch-on"></span>
                  <span class="switch-off"></span>
                </span>
                <span class="switch-label">${status}</span>
              </label>
            `;
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center gap-4">' +
              `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill delete-record" data-id="${full.id}"><i class="icon-base ri ri-delete-bin-7-line icon-22px"></i></button>` +
              `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill view-record" data-id="${full.id}"><i class="icon-base ri ri-eye-line icon-22px"></i></button>` +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"B>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      language: {
        search: '',
        searchPlaceholder: 'Search Loan Products',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
          first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
          last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      buttons: [],
      // For responsive popup
      scrollX: true,
      autoWidth: false
    });

    // Fixed header alignment on resize
    $(window).on('resize', function() {
      dt_loan_products.columns.adjust();
    });

    // Status Switch Handler with AJAX and Toaster
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('status-switch')) {
        const productId = e.target.dataset.id;
        const isChecked = e.target.checked;
        const newStatus = isChecked ? 'active' : 'inactive';
        const switchLabel = e.target.parentElement.querySelector('.switch-label');
        const switchInput = e.target;

        // Send AJAX request to update status
        fetch(`${baseUrl}loan/loan-products/${productId}/toggle-status`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ status: newStatus })
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update UI
              if (isChecked) {
                switchInput.classList.remove('is-invalid');
                switchInput.classList.add('is-valid');
                switchLabel.textContent = 'Active';
              } else {
                switchInput.classList.remove('is-valid');
                switchInput.classList.add('is-invalid');
                switchLabel.textContent = 'Inactive';
              }

              // Show success toaster with appropriate color
              const toastType = isChecked ? 'success' : 'danger';
              const statusText = isChecked ? 'Active' : 'Inactive';
              showToast(toastType, `Status ${statusText} successfully`);
            } else {
              // Revert switch on error
              switchInput.checked = !isChecked;
              showToast('error', 'Error', data.message || 'Failed to update status');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            // Revert switch on error
            switchInput.checked = !isChecked;
            showToast('error', 'Error', 'Failed to update status');
          });
      }
    });

    // View Record
    document.addEventListener('click', function (e) {
      if (e.target.closest('.view-record')) {
        const btn = e.target.closest('.view-record');
        const product_id = btn.dataset.id;

        // Navigate to loan product view page
        window.location.href = `${baseUrl}loan/loan-product-view/${product_id}`;
      }
    });

    // Delete Record
    let currentDeleteId = null;
    let currentProductName = null;

    document.addEventListener('click', function (e) {
      if (e.target.closest('.delete-record')) {
        const deleteBtn = e.target.closest('.delete-record');
        currentDeleteId = deleteBtn.dataset.id;

        // Get product name from the row
        const row = dt_loan_products.row(deleteBtn.closest('tr'));
        const rowData = row.data();
        currentProductName = rowData ? rowData.name : 'this product';

        const dtrModal = document.querySelector('.dtr-bs-modal.show');
        if (dtrModal) {
          const bsModal = bootstrap.Modal.getInstance(dtrModal);
          bsModal.hide();
        }

        // Show delete confirmation modal
        document.getElementById('deleteProductName').textContent = currentProductName;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
      }
    });

    // Confirm Delete Button with AJAX
    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
      if (!currentDeleteId) return;
      if (this.dataset.loading === '1') return;
      this.dataset.loading = '1';
      this.disabled = true;

      // Send AJAX delete request using POST with _method spoofing to avoid server blocking DELETE requests
      fetch(`${baseUrl}loan/loan-products/${currentDeleteId}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          _method: 'DELETE'
        })
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
          // Close delete modal
          const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
          deleteModal.hide();

          if (data.success !== false) {
            // Refresh the server-side DataTable
            dt_loan_products.draw(false);

            // Show success
            showToast('success', 'Deleted', 'Loan product deleted successfully');
          } else {
            showToast('error', 'Error', data.message || 'Failed to delete loan product');
          }

          currentDeleteId = null;
          currentProductName = null;
          this.disabled = false;
          this.dataset.loading = '0';
        })
        .catch(error => {
          console.error('Error:', error);
          const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
          deleteModal.hide();
          showToast('error', 'Error', 'Failed to delete loan product');
          this.disabled = false;
          this.dataset.loading = '0';
        });
    });
  }

  // Toast notification function
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
    } else {
      iconClass = 'ri-error-warning-line';
      bgClass = 'bg-danger';
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

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }
});
