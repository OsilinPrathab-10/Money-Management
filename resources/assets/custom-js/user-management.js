/**
 * User Management DataTable
 */

'use strict';

// Toast notification function (same as loan-types)
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

  const toastHTML = message ? `
    <div id="${toastId}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
      <div class="toast-header ${bgClass} text-white rounded-top-5 border-0">
        <i class="icon-base ${iconClass} me-2"></i>
        <div class="me-auto fw-medium">${title}</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body rounded-bottom-5">
        ${message}
      </div>
    </div>
  ` : `
    <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
      <div class="toast-header ${bgClass} text-white rounded-5 border-0">
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

$(function () {
  let borderColor, bodyBg, headingColor;

  // Use the global baseUrl defined in the layout, or fallback
  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) {
    baseUrl += '/';
  }

  if (isDarkStyle) {
    borderColor = config.colors_dark.borderColor;
    bodyBg = config.colors_dark.bodyBg;
    headingColor = config.colors_dark.headingColor;
  } else {
    borderColor = config.colors.borderColor;
    bodyBg = config.colors.bodyBg;
    headingColor = config.colors.headingColor;
  }

  // Variable declaration for table
  var dt_user_table = $('.datatables-users'),
    statusObj = {
      active: { title: 'Active', class: 'bg-label-success' },
      inactive: { title: 'Inactive', class: 'bg-label-secondary' }
    };

  // Users datatable
  if (dt_user_table.length) {
    var dt_user = dt_user_table.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'user-management/data',
        type: 'GET'
      },
      columns: [
        { data: '' },
        { data: 'id' }, // Use id for sorting
        { data: 'name' },
        { data: 'role' },
        { data: 'status' },
        { data: 'id' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          searchable: false,
          orderable: false,
          responsivePriority: 2,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          // S.No
          targets: 1,
          orderable: false,
          searchable: false,
          responsivePriority: 3,
          render: function (data, type, full, meta) {
            return meta.row + meta.settings._iDisplayStart + 1;
          }
        },
        {
          // Name
          targets: 2,
          responsivePriority: 1,
          render: function (data, type, full, meta) {
            var $name = full['name'];
            var $email = full['email'];
            return '<div class="d-flex flex-column">' +
              '<span class="fw-medium text-heading">' + $name + '</span>' +
              '<small class="text-muted">' + $email + '</small>' +
              '</div>';
          }
        },
        {
          // Role
          targets: 3,
          render: function (data, type, full, meta) {
            var $role = full['role'];
            return '<span class="text-truncate">' + $role + '</span>';
          }
        },
        {
          // Status - Switch Toggle
          targets: 4,
          orderable: false,
          render: function (data, type, full, meta) {
            var $status = full['status'];
            var checked = $status === 'active' ? 'checked' : '';
            var validClass = $status === 'active' ? 'is-valid' : 'is-invalid';
            
            // Protect Super Admin
            var isSuperAdmin = full['email'] === 'admin@example.com';
            var disabled = isSuperAdmin ? 'disabled' : '';

            return '<label class="switch">' +
              '<input type="checkbox" class="switch-input ' + validClass + ' status-switch" ' + checked + ' ' + disabled + ' data-id="' + full['id'] + '" />' +
              '<span class="switch-toggle-slider">' +
              '<span class="switch-on"></span>' +
              '<span class="switch-off"></span>' +
              '</span>' +
              '</label>';
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            var phone = full['phone'] || '';
            var isSuperAdmin = full['email'] === 'admin@example.com';
            var isCurrentUserSuperAdmin = window.authEmail === 'admin@example.com';

            // Delete button - Always hidden for Super Admin
            var deleteBtn = isSuperAdmin ? '' : 
              '<button class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-record" data-id="' + full['id'] + '" data-name="' + full['name'] + '">' +
              '<i class="icon-base ri ri-delete-bin-7-line icon-20px"></i>' +
              '</button>';

            // Edit button - Only visible to the Super Admin themselves or for other users
            var editBtn = (isSuperAdmin && !isCurrentUserSuperAdmin) ? '' : 
              '<button class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect edit-record" data-id="' + full['id'] + '" data-name="' + full['name'] + '" data-email="' + full['email'] + '" data-phone="' + phone + '">' +
              '<i class="icon-base ri ri-edit-line icon-20px"></i>' +
              '</button>';

            // Role button - Always hidden for Super Admin
            var roleBtn = isSuperAdmin ? '' : 
              '<button class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect assign-role" data-id="' + full['id'] + '" data-role="' + full['role'] + '">' +
              '<i class="icon-base ri ri-shield-user-line icon-20px"></i>' +
              '</button>';

            // Actions column
            var actions = '<div class="d-flex align-items-center gap-2">' + editBtn + roleBtn + deleteBtn + '</div>';
            return isSuperAdmin && !isCurrentUserSuperAdmin ? '<span class="text-muted small">Protected</span>' : actions;
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"row"' +
        '<"col-md-2"<"me-3"l>>' +
        '<"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-6 mb-md-0 mt-n6 mt-md-0 gap-md-4"fB>>' +
        '>t' +
        '<"row mx-2"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search User',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line icon-22px"></i>'
        }
      },
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-outline-secondary dropdown-toggle me-4 waves-effect waves-light',
          text: '<i class="icon-base ri ri-download-line icon-sm me-1"></i> <span class="d-none d-sm-inline-block">Export</span>',
          buttons: [
            {
              extend: 'print',
              text: '<i class="icon-base ri ri-printer-line me-1"></i>Print',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('user-name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              },
              customize: function (win) {
                $(win.document.body)
                  .css('color', headingColor)
                  .css('border-color', borderColor)
                  .css('background-color', bodyBg)
                  .css('font-family', '"Public Sans", sans-serif');
                var $table = $(win.document.body).find('table');
                $table
                  .addClass('table table-bordered table-sm compact')
                  .css('color', 'inherit')
                  .css('border-color', 'inherit')
                  .css('background-color', 'inherit')
                  .css('border-collapse', 'collapse')
                  .css('width', '100%');
                $table.find('th, td')
                  .css('border', '1px solid ' + borderColor)
                  .css('padding', '8px');
              }
            },
            {
              extend: 'csv',
              text: '<i class="icon-base ri ri-file-text-line me-1"></i>Csv',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('user-name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-line me-1"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('user-name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            },
            {
              extend: 'pdfHtml5',
              text: '<i class="icon-base ri ri-file-pdf-line me-1"></i>Pdf',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('user-name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              },
              customize: function (doc) {
                doc.content[0].text = 'User Management | Loan App';
                if (doc.content[1] && doc.content[1].table) {
                  doc.content[1].table.widths = ['10%', '45%', '25%', '20%'];
                  doc.content[1].layout = {
                    hLineWidth: function () { return 0.5; },
                    vLineWidth: function () { return 0.5; },
                    hLineColor: function () { return '#cccccc'; },
                    vLineColor: function () { return '#cccccc'; },
                    paddingLeft: function () { return 6; },
                    paddingRight: function () { return 6; },
                    paddingTop: function () { return 6; },
                    paddingBottom: function () { return 6; }
                  };
                  var rowCount = doc.content[1].table.body.length;
                  for (var i = 0; i < rowCount; i++) {
                    var row = doc.content[1].table.body[i];
                    row.forEach(function (cell, index) {
                      cell.alignment = 'left';
                      cell.margin = [0, 4, 0, 4];
                    });
                  }
                  doc.styles.tableHeader.fillColor = '#f4f4f4';
                  doc.styles.tableHeader.color = '#333333';
                  doc.styles.tableHeader.alignment = 'left';
                }
              }
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-1"></i>Copy',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner.length <= 0) return inner;
                    var el = $.parseHTML(inner);
                    var result = '';
                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('user-name')) {
                        result = result + item.lastChild.firstChild.textContent;
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result;
                  }
                }
              }
            }
          ]
        }
      ],
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Details of ' + data['name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.title !== ''
                ? '<tr data-dt-row="' +
                col.rowIndex +
                '" data-dt-column="' +
                col.columnIndex +
                '">' +
                '<td>' +
                col.title +
                ':' +
                '</td> ' +
                '<td>' +
                col.data +
                '</td>' +
                '</tr>'
                : '';
            }).join('');

            return data ? $('<table class="table"/><tbody />').append(data) : false;
          }
        }
      }
    });
  }

  // Status Switch Toggle with AJAX and Toast
  $('.datatables-users tbody').on('change', '.status-switch', function () {
    var $switch = $(this);
    var userId = $switch.data('id');
    var newStatus = $switch.is(':checked') ? 'active' : 'inactive';
    var statusText = newStatus === 'active' ? 'Active' : 'Inactive';

    $.ajax({
      url: baseUrl + 'user-management/' + userId + '/toggle-status',
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        status: newStatus
      },
      success: function (response) {
        if (response.success) {
          // Update switch class
          if (newStatus === 'active') {
            $switch.removeClass('is-invalid').addClass('is-valid');
          } else {
            $switch.removeClass('is-valid').addClass('is-invalid');
          }

          // Show toast with color based on status
          const toastType = newStatus === 'active' ? 'success' : 'danger';
          showToast(toastType, 'Status updated to ' + statusText);
        } else {
          // Revert switch on error
          $switch.prop('checked', !$switch.is(':checked'));
          showToast('danger', 'Failed to update status');
        }
      },
      error: function () {
        // Revert switch on error
        $switch.prop('checked', !$switch.is(':checked'));
        showToast('danger', 'Failed to update status');
      }
    });
  });

  // Delete Record with Confirmation
  var currentDeleteId = null;
  var currentUserName = null;

  $('.datatables-users tbody').on('click', '.delete-record', function () {
    currentDeleteId = $(this).data('id');
    currentUserName = $(this).data('name');

    // Show confirmation modal
    $('#deleteUserName').text(currentUserName);
    $('#deleteModal').modal('show');
  });

  // Handle delete confirmation
  $('#confirmDeleteBtn').off('click').on('click', function () {
    if (!currentDeleteId) return;

    $.ajax({
      url: baseUrl + 'user-management/' + currentDeleteId,
      type: 'DELETE',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content')
      },
      success: function (response) {
        $('#deleteModal').modal('hide');

        if (response.success) {
          showToast('success', 'User deleted successfully');
          dt_user.ajax.reload(null, false);
        } else {
          showToast('danger', response.message || 'Failed to delete user');
        }

        currentDeleteId = null;
        currentUserName = null;
      },
      error: function (xhr) {
        $('#deleteModal').modal('hide');
        const message = xhr.responseJSON?.message || 'Failed to delete user';
        showToast('danger', message);

        currentDeleteId = null;
        currentUserName = null;
      }
    });
  });

  // Assign Role Modal handler
  $('.datatables-users tbody').on('click', '.assign-role', function () {
    var userId = $(this).data('id');
    var currentRole = $(this).data('role').toLowerCase();

    $('#assignRoleUserId').val(userId);
    $('#roleSelect').val(currentRole);
    $('#assignRoleModal').modal('show');
  });

  // Handle assign role form submission
  $('#assignRoleForm').on('submit', function (e) {
    e.preventDefault();

    var userId = $('#assignRoleUserId').val();
    var role = $('#roleSelect').val();

    $('#assignRoleSubmitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

    $.ajax({
      url: baseUrl + 'user-management/' + userId + '/assign-role',
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        role: role
      },
      success: function (response) {
        $('#assignRoleModal').modal('hide');
        $('#assignRoleSubmitBtn').prop('disabled', false).html('<i class="icon-base ri ri-save-line me-1"></i>Save Role');

        if (response.success) {
          showToast('success', 'Role assigned successfully');
          dt_user.ajax.reload(null, false);
        } else {
          showToast('danger', response.message || 'Failed to assign role');
        }
      },
      error: function (xhr) {
        $('#assignRoleSubmitBtn').prop('disabled', false).html('<i class="icon-base ri ri-save-line me-1"></i>Save Role');
        var message = xhr.responseJSON?.message || 'Failed to assign role';
        showToast('danger', message);
      }
    });
  });

  // Clear validation errors
  function clearValidationErrors() {
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback').text('');
  }

  // Variable to track if we're in edit mode
  let isEditMode = false;

  // Handle Edit button click
  $('.datatables-users tbody').on('click', '.edit-record', function (e) {
    e.preventDefault();

    const userId = $(this).data('id');
    const userName = $(this).data('name');
    const userEmail = $(this).data('email');
    const userPhone = $(this).data('phone');

    // Set edit mode flag
    isEditMode = true;

    // Clear validation errors
    clearValidationErrors();

    // Reset form first
    $('#userForm')[0].reset();

    // Populate form with current data
    $('#userId').val(userId);
    $('#userName').val(userName);
    $('#userEmail').val(userEmail);
    $('#userPhone').val(userPhone);

    // Update modal for edit mode
    $('#modalTitle').text('Edit User');
    $('#submitBtn').html('<i class="icon-base ri ri-save-line me-1"></i>Update User');

    // Make password optional for edit
    $('#userPassword').prop('required', false).val('');
    $('#userConfirmPassword').prop('required', false).val('');
    $('#passwordField').hide();
    $('#confirmPasswordField').hide();
    $('#passwordRequired').hide();
    $('#confirmPasswordRequired').hide();

    // Show modal
    $('#addUserModal').modal('show');
  });

  // Handle modal show event (for Add New User button)
  $('#addUserModal').on('show.bs.modal', function (e) {
    // Only reset if not in edit mode
    if (!isEditMode) {
      const button = $(e.relatedTarget);
      const modal = $(this);

      // Reset form
      $('#userForm')[0].reset();
      $('#userId').val('');
      $('#modalTitle').text('Add New User');
      $('#submitBtn').html('<i class="icon-base ri ri-save-line me-1"></i>Save User');
      $('#userPassword').prop('required', true);
      $('#userConfirmPassword').prop('required', true);
      $('#passwordField').show();
      $('#confirmPasswordField').show();
      $('#passwordRequired').show();
      $('#confirmPasswordRequired').show();
      clearValidationErrors();
    }
  });

  // Reset edit mode flag when modal is hidden
  $('#addUserModal').on('hidden.bs.modal', function () {
    isEditMode = false;
  });

  // Handle form submission
  $('#userForm').on('submit', function (e) {
    e.preventDefault();

    // Clear previous validation errors
    clearValidationErrors();

    const userId = $('#userId').val();
    const isEdit = userId !== '';
    const password = $('#userPassword').val();
    const confirmPassword = $('#userConfirmPassword').val();

    // Client-side validation
    let hasError = false;

    // Validate name
    if ($('#userName').val().trim() === '') {
      $('#userName').addClass('is-invalid');
      $('#nameError').text('Name is required');
      hasError = true;
    }

    // Validate email
    const email = $('#userEmail').val().trim();
    if (email === '') {
      $('#userEmail').addClass('is-invalid');
      $('#emailError').text('Email is required');
      hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      $('#userEmail').addClass('is-invalid');
      $('#emailError').text('Please enter a valid email address');
      hasError = true;
    }

    // Validate phone
    if ($('#userPhone').val().trim() === '') {
      $('#userPhone').addClass('is-invalid');
      $('#phoneError').text('Phone is required');
      hasError = true;
    } else if (/^0+$/.test($('#userPhone').val().trim())) {
      $('#userPhone').addClass('is-invalid');
      $('#phoneError').text('Invalid phone number (cannot be all zeros)');
      hasError = true;
    }

    // Validate password (only for new users or if password is provided)
    if (!isEdit) {
      // For new users, password is required
      if (password === '') {
        $('#userPassword').addClass('is-invalid');
        $('#passwordError').text('Password is required');
        hasError = true;
      } else if (password.length < 8) {
        $('#userPassword').addClass('is-invalid');
        $('#passwordError').text('Password must be at least 8 characters');
        hasError = true;
      }

      // Validate confirm password
      if (confirmPassword === '') {
        $('#userConfirmPassword').addClass('is-invalid');
        $('#confirmPasswordError').text('Please confirm your password');
        hasError = true;
      } else if (password !== confirmPassword) {
        $('#userConfirmPassword').addClass('is-invalid');
        $('#confirmPasswordError').text('Passwords do not match');
        hasError = true;
      }
    } else {
      // For edit, only validate if password is provided
      if (password !== '' && password.length < 8) {
        $('#userPassword').addClass('is-invalid');
        $('#passwordError').text('Password must be at least 8 characters');
        hasError = true;
      }

      if (password !== '' && password !== confirmPassword) {
        $('#userConfirmPassword').addClass('is-invalid');
        $('#confirmPasswordError').text('Passwords do not match');
        hasError = true;
      }
    }

    if (hasError) {
      return false;
    }

    const url = isEdit ? baseUrl + 'user-management/' + userId + '/update' : baseUrl + 'user-management/store';
    const method = 'POST';

    const formData = {
      _token: $('meta[name="csrf-token"]').attr('content'),
      name: $('#userName').val(),
      email: $('#userEmail').val(),
      phone: $('#userPhone').val(),
      role: $('#userRole').val()
    };

    // Add password only if provided
    if (password) {
      formData.password = password;
      formData.password_confirmation = confirmPassword;
    }

    // Disable submit button
    $('#submitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

    $.ajax({
      url: url,
      type: method,
      data: formData,
      success: function (response) {
        $('#addUserModal').modal('hide');
        showToast('success', isEdit ? 'User updated successfully' : 'User created successfully');
        dt_user.ajax.reload(null, false);

        // Reset button
        $('#submitBtn').prop('disabled', false).html('<i class="icon-base ri ri-save-line me-1"></i>Save User');
      },
      error: function (xhr) {
        // Reset button
        $('#submitBtn').prop('disabled', false).html('<i class="icon-base ri ri-save-line me-1"></i>Save User');

        if (xhr.responseJSON && xhr.responseJSON.errors) {
          const errors = xhr.responseJSON.errors;

          // Display individual field errors
          $.each(errors, function (field, messages) {
            const fieldId = field === 'name' ? '#userName' :
              field === 'email' ? '#userEmail' :
                field === 'phone' ? '#userPhone' :
                  field === 'password' ? '#userPassword' : '';

            const errorId = field + 'Error';

            if (fieldId) {
              $(fieldId).addClass('is-invalid');
              $('#' + errorId).text(messages[0]);
            }
          });

          showToast('danger', 'Please fix the errors in the form');
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
          showToast('danger', xhr.responseJSON.message);
        } else {
          showToast('danger', 'Failed to save user');
        }
      }
    });
  });

  // Handle Agent modal show event
  $('#addAgentModal').on('show.bs.modal', function () {
    if ($('#agentUserForm').length) {
      $('#agentUserForm')[0].reset();
    }
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback').text('');
  });

  // Handle Location selection to auto-populate City, State, and Pincode
  $(document).on('change', '#agentUserLocation', function () {
    const selectedOption = $(this).find('option:selected');
    if (selectedOption.length && selectedOption.val()) {
      $('#agentUserCity').val(selectedOption.data('city') || '');
      $('#agentUserState').val(selectedOption.data('state') || '');
      $('#agentUserPincode').val(selectedOption.data('pincode') || '');
    }
  });

  // Handle agent form submission
  $('#agentUserForm').on('submit', function (e) {
    e.preventDefault();

    // Clear previous validation errors
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback').text('');

    const name = $('#agentUserName').val().trim();
    const email = $('#agentUserEmail').val().trim();
    const phone = $('#agentUserPhone').val().trim();
    const pincode = $('#agentUserPincode').val().trim();
    const location_id = $('#agentUserLocation').val();
    const address = $('#agentUserAddress').val().trim();
    const city = $('#agentUserCity').val().trim();
    const state = $('#agentUserState').val().trim();
    const password = $('#agentUserPassword').val();
    const confirmPassword = $('#agentUserConfirmPassword').val();

    // Client-side validation
    let hasError = false;

    // Validate name
    if (name === '') {
      $('#agentUserName').addClass('is-invalid');
      $('#agentNameError').text('Name is required');
      hasError = true;
    }

    // Validate email
    if (email === '') {
      $('#agentUserEmail').addClass('is-invalid');
      $('#agentEmailError').text('Email is required');
      hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      $('#agentUserEmail').addClass('is-invalid');
      $('#agentEmailError').text('Please enter a valid email address');
      hasError = true;
    }

    // Validate phone
    if (phone === '') {
      $('#agentUserPhone').addClass('is-invalid');
      $('#agentPhoneError').text('Phone is required');
      hasError = true;
    } else if (!/^\d{10}$/.test(phone)) {
      $('#agentUserPhone').addClass('is-invalid');
      $('#agentPhoneError').text('Phone number must be exactly 10 digits');
      hasError = true;
    }

    // Validate pincode
    if (pincode === '') {
      $('#agentUserPincode').addClass('is-invalid');
      $('#agentPincodeError').text('Pincode is required');
      hasError = true;
    } else if (!/^\d{6}$/.test(pincode)) {
      $('#agentUserPincode').addClass('is-invalid');
      $('#agentPincodeError').text('Pincode must be exactly 6 digits');
      hasError = true;
    }

    // Validate location
    if (!location_id) {
      $('#agentUserLocation').addClass('is-invalid');
      $('#agentLocationError').text('Location is required');
      hasError = true;
    }

    // Validate address
    if (address === '') {
      $('#agentUserAddress').addClass('is-invalid');
      $('#agentAddressError').text('Address is required');
      hasError = true;
    }

    // Validate city
    if (city === '') {
      $('#agentUserCity').addClass('is-invalid');
      $('#agentCityError').text('City is required');
      hasError = true;
    }

    // Validate state
    if (state === '') {
      $('#agentUserState').addClass('is-invalid');
      $('#agentStateError').text('State is required');
      hasError = true;
    }

    // Validate password
    if (password === '') {
      $('#agentUserPassword').addClass('is-invalid');
      $('#agentPasswordError').text('Password is required');
      hasError = true;
    } else if (password.length < 8) {
      $('#agentUserPassword').addClass('is-invalid');
      $('#agentPasswordError').text('Password must be at least 8 characters');
      hasError = true;
    }

    // Validate confirm password
    if (confirmPassword === '') {
      $('#agentUserConfirmPassword').addClass('is-invalid');
      $('#agentConfirmPasswordError').text('Please confirm your password');
      hasError = true;
    } else if (password !== confirmPassword) {
      $('#agentUserConfirmPassword').addClass('is-invalid');
      $('#agentConfirmPasswordError').text('Passwords do not match');
      hasError = true;
    }

    if (hasError) {
      return false;
    }

    // Disable submit button
    $('#agentSubmitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

    $.ajax({
      url: baseUrl + 'app/agents/store',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        _token: $('meta[name="csrf-token"]').attr('content'),
        name: name,
        email: email,
        phone: phone,
        pincode: pincode,
        location_id: location_id,
        address: address,
        city: city,
        state: state,
        password: password,
        password_confirmation: confirmPassword
      }),
      success: function (response) {
        $('#addAgentModal').modal('hide');
        showToast('success', 'Agent created successfully');
        dt_user.ajax.reload(null, false);
        $('#agentSubmitBtn').prop('disabled', false).html('Save Agent');
      },
      error: function (xhr) {
        $('#agentSubmitBtn').prop('disabled', false).html('Save Agent');

        if (xhr.responseJSON && xhr.responseJSON.errors) {
          const errors = xhr.responseJSON.errors;

          $.each(errors, function (field, messages) {
            const fieldMap = {
              name: '#agentUserName',
              email: '#agentUserEmail',
              phone: '#agentUserPhone',
              pincode: '#agentUserPincode',
              location_id: '#agentUserLocation',
              address: '#agentUserAddress',
              city: '#agentUserCity',
              state: '#agentUserState',
              password: '#agentUserPassword'
            };

            const fieldId = fieldMap[field];
            const errorId = field === 'location_id' ? '#agentLocationError' : '#agent' + field.charAt(0).toUpperCase() + field.slice(1) + 'Error';

            if (fieldId) {
              $(fieldId).addClass('is-invalid');
            }
            if (errorId) {
              $(errorId).text(messages[0]);
            }
          });

          showToast('danger', 'Please fix the errors in the form');
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
          showToast('danger', xhr.responseJSON.message);
        } else {
          showToast('danger', 'Failed to save agent');
        }
      }
    });
  });
});
