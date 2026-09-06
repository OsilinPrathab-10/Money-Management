/**
 * DataTables Loan Types
 */

'use strict';

// datatable (jquery)
$(function () {
  // Variable declaration for table
  var dt_loan_types_table = $('.datatables-loan-types'),
    statusObj = {
      1: { title: 'Active', class: 'bg-label-success' },
      0: { title: 'Inactive', class: 'bg-label-secondary' }
    };

  // Function to show professional toast notifications
  function showAlert(type, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();

    const toastId = 'toast-' + Date.now();
    let iconClass, bgClass;

    // Map alert types to toast styles
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

    // Create toast with rounded corners and shadow
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

  // Reset create loan type form when modal closes
  const createLoanTypeForm = document.getElementById('createLoanTypeForm');
  const createLoanTypeModal = document.getElementById('createLoanTypeModal');
  const submitLoanTypeBtn = createLoanTypeForm ? createLoanTypeForm.querySelector('button[type="submit"]') : null;

  if (createLoanTypeForm && createLoanTypeModal) {
    createLoanTypeModal.addEventListener('hidden.bs.modal', function () {
      // Reset button state
      if (submitLoanTypeBtn) {
        submitLoanTypeBtn.disabled = false;
        submitLoanTypeBtn.innerHTML = '<i class="icon-base ri ri-save-line me-1"></i> Save Loan Type';
      }

      // Clear all form controls
      createLoanTypeForm.reset();

      const controls = createLoanTypeForm.querySelectorAll('.is-valid, .is-invalid');
      controls.forEach(function (control) {
        control.classList.remove('is-valid', 'is-invalid');
      });

      const validationMessages = createLoanTypeForm.querySelectorAll('.invalid-feedback, .text-danger');
      validationMessages.forEach(function (message) {
        message.style.display = 'none';
        if (message.tagName === 'SMALL') {
          message.textContent = '';
        }
      });
    });
  }

  // Create Loan Type (AJAX) - show validation inside modal
  if (createLoanTypeForm) {
    createLoanTypeForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const form = this;
      const submitBtn = submitLoanTypeBtn;
      const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

      // Clear previous validation UI
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      form.querySelectorAll('.invalid-feedback.dynamic').forEach(el => el.remove());

      const formData = new FormData(form);

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...';
      }

      fetch(form.action, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        body: formData
      })
        .then(async response => {
          if (response.ok) return response.json();

          // Validation errors
          if (response.status === 422) {
            const data = await response.json();
            const errors = data.errors || {};

            Object.keys(errors).forEach(field => {
              const input = form.querySelector(`[name="${field}"]`);
              if (!input) return;
              input.classList.add('is-invalid');

              const msg = document.createElement('div');
              msg.className = 'invalid-feedback d-block dynamic';
              msg.textContent = errors[field][0] || 'This field is invalid.';

              // For inputs inside input-groups/textarea, append after the element
              input.parentNode.insertBefore(msg, input.nextSibling);
            });

            throw new Error('validation');
          }

          const data = await response.json().catch(() => null);
          const message = data?.message || 'Failed to create loan type.';
          throw new Error(message);
        })
        .then(data => {
          // Close modal and refresh table
          $('#createLoanTypeModal').modal('hide');
          showAlert('success', data?.message || 'Loan type created successfully');

          if (typeof dt_loan_types !== 'undefined') {
            dt_loan_types.ajax.reload();
          }
        })
        .catch(err => {
          if (err.message !== 'validation') {
            showAlert('danger', err.message || 'Failed to create loan type. Please try again.');
          }
        })
        .finally(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
          }
        });
    });
  }

  // File preview for create modal - Icon
  $('#loanTypeIcon').on('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $('#iconPreview img').attr('src', e.target.result);
        $('#iconPreview').show();
      };
      reader.readAsDataURL(file);
    } else {
      $('#iconPreview').hide();
    }
  });

  // File preview for create modal - Image
  $('#loanTypeImage').on('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $('#imagePreview img').attr('src', e.target.result);
        $('#imagePreview').show();
      };
      reader.readAsDataURL(file);
    } else {
      $('#imagePreview').hide();
    }
  });

  // File preview for create modal - Banner
  $('#loanTypeBanner').on('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $('#bannerPreview img').attr('src', e.target.result);
        $('#bannerPreview').show();
      };
      reader.readAsDataURL(file);
    } else {
      $('#bannerPreview').hide();
    }
  });

  // File preview for edit modal - Icon
  $('#editLoanTypeIcon').on('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $('#editIconPreview img').attr('src', e.target.result);
        $('#editIconPreview').show();
      };
      reader.readAsDataURL(file);
    } else {
      $('#editIconPreview').hide();
    }
  });

  // File preview for edit modal - Image
  $('#editLoanTypeImage').on('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $('#editImagePreview img').attr('src', e.target.result);
        $('#editImagePreview').show();
      };
      reader.readAsDataURL(file);
    } else {
      $('#editImagePreview').hide();
    }
  });

  // File preview for edit modal - Banner
  $('#editLoanTypeBanner').on('change', function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        $('#editBannerPreview img').attr('src', e.target.result);
        $('#editBannerPreview').show();
      };
      reader.readAsDataURL(file);
    } else {
      $('#editBannerPreview').hide();
    }
  });

  // Flash alerts from server-side sessions
  const alertContainer = document.querySelector('.alert-container');
  if (alertContainer) {
    const successMessage = alertContainer.getAttribute('data-success');
    const errorMessage = alertContainer.getAttribute('data-error');
    const warningMessage = alertContainer.getAttribute('data-warning');
    const infoMessage = alertContainer.getAttribute('data-info');

    if (successMessage) {
      showAlert('success', successMessage);
    }

    if (errorMessage) {
      showAlert('danger', errorMessage);
    }

    if (warningMessage) {
      showAlert('warning', warningMessage);
    }

    if (infoMessage) {
      showAlert('info', infoMessage);
    }
  }

  // Loan Types datatable
  if (dt_loan_types_table.length) {
    var dt_loan_types = dt_loan_types_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'loan/loan-types',
        dataSrc: 'data'
      },
      columns: [
        { data: '' },
        { data: 'id' },
        { data: 'name' },
        { data: 'description' },
        { data: 'status' },
        { data: 'action' }
      ],
      columnDefs: [
        {
          className: 'control',
          searchable: false,
          orderable: false,
          targets: 0,
          render: function () {
            return '';
          }
        },
        {
          // S.No
          targets: 1,
          title: 'S.No',
          searchable: false,
          orderable: true,
          render: function (data, type, full, meta) {
            return meta.settings._iRecordsTotal - meta.settings._iDisplayStart - meta.row;
          }
        },
        {
          // Name
          targets: 2,
          render: function (data, type, full) {
            var $name = full['name'];
            return '<span class="fw-medium">' + $name + '</span>';
          }
        },
        {
          // Description
          targets: 3,
          render: function (data, type, full) {
            var $description = full['description'];
            if ($description && $description.length > 50) {
              return '<span title="' + $description + '">' + $description.substring(0, 50) + '...</span>';
            }
            return $description || 'No description';
          }
        },
        {
          // Status
          targets: 4,
          render: function (data, type, full) {
            var status = full['status'];
            var statusText = status == 1 ? 'Active' : 'Inactive';
            var checked = status == 1 ? 'checked' : '';
            var validClass = status == 1 ? 'is-valid' : 'is-invalid';

            return '<label class="switch">' +
              '<input type="checkbox" class="switch-input ' + validClass + ' status-switch" ' + checked + ' data-id="' + full['id'] + '" />' +
              '<span class="switch-toggle-slider">' +
              '<span class="switch-on"></span>' +
              '<span class="switch-off"></span>' +
              '</span>' +
              '<span class="switch-label">' + statusText + '</span>' +
              '</label>';
          }
        },
        {
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full) {
            return '<div class="d-flex align-items-center gap-3">' +
              '<a href="javascript:;" class="btn btn-icon btn-text-secondary rounded-pill view-record" style="width: 38px; height: 38px;" data-id="' + full['id'] + '" title="View">' +
              '<i class="ri ri-eye-line" style="font-size: 20px;"></i></a>' +
              '<a href="javascript:;" class="btn btn-icon btn-text-primary rounded-pill edit-record" style="width: 38px; height: 38px;" data-id="' + full['id'] + '" title="Edit">' +
              '<i class="ri ri-edit-box-line" style="font-size: 20px;"></i></a>' +
              '<a href="javascript:;" class="btn btn-icon btn-text-danger rounded-pill delete-record" style="width: 38px; height: 38px;" data-id="' + full['id'] + '" title="Delete">' +
              '<i class="ri ri-delete-bin-7-line" style="font-size: 20px;"></i></a>' +
              '</div>';
          }
        }
      ],
      order: [[1, 'desc']],
      dom: 't<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      searching: false,
      lengthChange: false,
      paging: true
    });
  }

  // Status Switch Toggle
  $('.datatables-loan-types tbody').on('change', '.status-switch', function () {
    var $switch = $(this);
    var loanTypeId = $switch.data('id');
    var newStatus = $switch.is(':checked') ? 1 : 0;
    var statusText = newStatus == 1 ? 'Active' : 'Inactive';

    $.ajax({
      url: baseUrl + 'loan/loan-types/' + loanTypeId + '/toggle-status',
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        status: newStatus
      },
      success: function (response) {
        // Update switch class and label
        $switch.removeClass('is-valid is-invalid').addClass(newStatus == 1 ? 'is-valid' : 'is-invalid');
        $switch.closest('label').find('.switch-label').text(statusText);

        // Show toast with color based on status (green for active, red for inactive)
        const toastType = newStatus == 1 ? 'success' : 'danger';
        showAlert(toastType, 'Status ' + statusText + ' successfully');
      },
      error: function () {
        // Revert the switch if error
        $switch.prop('checked', !$switch.is(':checked'));

        // Show error toast
        showAlert('danger', 'Failed to update status');
      }
    });
  });

  // View Record - Fetch fresh data from server
  $('.datatables-loan-types tbody').on('click', '.view-record', function () {
    var loanTypeId = $(this).data('id');

    // Fetch fresh data from server via AJAX
    $.ajax({
      url: baseUrl + 'loan/loan-types/' + loanTypeId,
      type: 'GET',
      success: function (response) {
        // Update modal with fresh data from server
        $('#viewLoanTypeName').text(response.name);
        $('#viewLoanTypeDescription').text(response.description || 'No description');
        $('#viewLoanTypeStatus').html('<span class="badge ' + (response.status == 1 ? 'bg-label-success' : 'bg-label-secondary') + '">' + (response.status == 1 ? 'Active' : 'Inactive') + '</span>');

        // Display icon if exists
        if (response.icon) {
          $('#viewIconImage').attr('src', response.icon).show();
          $('#viewIconPlaceholder').hide();
        } else {
          $('#viewIconImage').hide();
          $('#viewIconPlaceholder').show();
        }

        // Display image if exists
        if (response.image) {
          $('#viewImageImage').attr('src', response.image).show();
          $('#viewImagePlaceholder').hide();
        } else {
          $('#viewImageImage').hide();
          $('#viewImagePlaceholder').show();
        }

        // Display banner if exists
        if (response.banner) {
          $('#viewBannerImage').attr('src', response.banner).show();
          $('#viewBannerPlaceholder').hide();
        } else {
          $('#viewBannerImage').hide();
          $('#viewBannerPlaceholder').show();
        }

        $('#viewLoanTypeModal').modal('show');
      },
      error: function () {
        showAlert('danger', 'Failed to load loan type details. Please try again.');
      }
    });
  });

  // Edit Record - Fetch and populate edit form
  $('.datatables-loan-types tbody').on('click', '.edit-record', function () {
    var loanTypeId = $(this).data('id');

    // Fetch data from server via AJAX
    $.ajax({
      url: baseUrl + 'loan/loan-types/' + loanTypeId,
      type: 'GET',
      success: function (response) {
        // Populate edit form with data
        $('#editLoanTypeId').val(response.id);
        $('#editLoanTypeName').val(response.name);
        $('#editDescription').val(response.description);

        // Reset file previews
        $('#editIconPreview').hide();
        $('#editImagePreview').hide();
        $('#editBannerPreview').hide();

        // Display current icon if exists
        if (response.icon) {
          $('#currentIcon').attr('src', response.icon);
          $('#currentIconContainer').show();
        } else {
          $('#currentIconContainer').hide();
        }

        // Display current image if exists
        if (response.image) {
          $('#currentImage').attr('src', response.image);
          $('#currentImageContainer').show();
        } else {
          $('#currentImageContainer').hide();
        }

        // Display current banner if exists
        if (response.banner) {
          $('#currentBanner').attr('src', response.banner);
          $('#currentBannerContainer').show();
        } else {
          $('#currentBannerContainer').hide();
        }

        $('#editLoanTypeModal').modal('show');
      },
      error: function () {
        showAlert('danger', 'Failed to load loan type details. Please try again.');
      }
    });
  });

  // Handle Edit Form Submission
  $('#editLoanTypeForm').on('submit', function (e) {
    e.preventDefault();

    var loanTypeId = $('#editLoanTypeId').val();

    // Use FormData for file uploads
    var formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('_method', 'PUT');
    formData.append('name', $('#editLoanTypeName').val());
    formData.append('description', $('#editDescription').val());

    // Append files if selected
    var iconFile = $('#editLoanTypeIcon')[0].files[0];
    if (iconFile) {
      formData.append('loan_type_icon', iconFile);
    }

    var imageFile = $('#editLoanTypeImage')[0].files[0];
    if (imageFile) {
      formData.append('loan_type_image', imageFile);
    }

    var bannerFile = $('#editLoanTypeBanner')[0].files[0];
    if (bannerFile) {
      formData.append('loan_type_banner', bannerFile);
    }

    var submitBtn = $('#updateLoanTypeBtn');
    submitBtn.prop('disabled', true).html('<i class="icon-base ri ri-loader-4-line me-1 spinner-border spinner-border-sm"></i> Updating...');

    $.ajax({
      url: baseUrl + 'loan/loan-types/' + loanTypeId,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        $('#editLoanTypeModal').modal('hide');
        showAlert('success', 'Loan type updated successfully');
        dt_loan_types.ajax.reload();
        submitBtn.prop('disabled', false).html('<i class="icon-base ri ri-save-line me-1"></i> Update Loan Type');
      },
      error: function (xhr) {
        submitBtn.prop('disabled', false).html('<i class="icon-base ri ri-save-line me-1"></i> Update Loan Type');

        if (xhr.responseJSON && xhr.responseJSON.errors) {
          var errors = xhr.responseJSON.errors;
          var errorMessage = Object.values(errors).flat().join('<br>');
          showAlert('danger', errorMessage);
        } else {
          showAlert('danger', 'Failed to update loan type. Please try again.');
        }
      }
    });
  });

  // Delete Record with Confirmation
  $('.datatables-loan-types tbody').on('click', '.delete-record', function () {
    var loanTypeId = $(this).data('id');
    var row = dt_loan_types.row($(this).closest('tr')).data();
    var loanTypeName = row.name;

    // Show confirmation modal
    $('#deleteLoanTypeName').text(loanTypeName);
    $('#deleteModal').modal('show');

    // Handle delete confirmation
    $('#confirmDeleteBtn').off('click').on('click', function () {
      $.ajax({
        url: baseUrl + 'loan/loan-types/' + loanTypeId,
        type: 'DELETE',
        data: {
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
          $('#deleteModal').modal('hide');
          showAlert('success', 'Loan type deleted successfully');
          dt_loan_types.ajax.reload();
        },
        error: function () {
          $('#deleteModal').modal('hide');
          showAlert('danger', 'Failed to delete loan type');
        }
      });
    });
  });

  // Foreclosure Configuration Form Handler
  $('#foreclosureConfigForm').on('submit', function (e) {
    e.preventDefault();

    const formData = {
      _token: $('meta[name="csrf-token"]').attr('content'),
      eligibility_months: $('#eligibilityMonths').val() || null,
      charges_percentage: $('#chargesPercentage').val() || 0
    };

    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
      url: baseUrl + 'loan/foreclosure-config/update',
      type: 'POST',
      data: formData,
      success: function (response) {
        showAlert('success', response.message);
        submitBtn.prop('disabled', false).html(originalText);
      },
      error: function (xhr) {
        const errorMessage = xhr.responseJSON?.message || 'Failed to update configuration';
        showAlert('danger', errorMessage);
        submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });
});
