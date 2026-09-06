/**
 * App user list (js)
 */

'use strict';

console.log('user-permission.js loaded');
console.log('Bootstrap defined:', typeof bootstrap !== 'undefined');

document.addEventListener('DOMContentLoaded', function (e) {
  console.log('DOM Content Loaded in user-permission.js');
  const dataTablePermissions = document.querySelector('.datatables-permissions'),
    userList = baseUrl + 'user-management';
  let dt_permission;

  // Users List datatable
  if (dataTablePermissions) {
    dt_permission = new DataTable(dataTablePermissions, {
      ajax: {
        url: baseUrl + 'permissions/data',
        dataSrc: 'data'
      },
      // ajax: assetsPath + 'json/permissions-list.json', // JSON file to add data
      columns: [
        // columns according to JSON
        { data: 'id' },
        { data: 'id' },
        { data: 'name' },
        { data: 'assigned_to' },
        { data: 'created_date' },
        { data: 'id' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          orderable: false,
          searchable: false,
          responsivePriority: 2,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          targets: 1,
          searchable: false,
          visible: false
        },
        {
          // Name
          targets: 2,
          render: function (data, type, full, meta) {
            let name = full['name'];
            return '<span class="text-nowrap text-heading">' + name + '</span>';
          }
        },
        {
          // User Role
          targets: 3,
          orderable: false,
          render: function (data, type, full, meta) {
            const assignedTo = full['assigned_to'] || [];
            let output = '';
            const roleBadgeObj = {
              Admin: `<a href="${userList}"><span class="badge rounded-pill bg-label-primary me-4">Administrator</span></a>`,
              Manager: `<a href="${userList}"><span class="badge rounded-pill bg-label-warning me-4">Manager</span></a>`,
              Users: `<a href="${userList}"><span class="badge rounded-pill bg-label-success me-4">Users</span></a>`,
              Support: `<a href="${userList}"><span class="badge rounded-pill bg-label-info me-4">Support</span></a>`,
              Restricted: `<a href="${userList}"><span class="badge rounded-pill bg-label-danger me-4">Restricted User</span></a>`
            };

            assignedTo.forEach(role => {
              output += roleBadgeObj[role] || `<span class="badge rounded-pill bg-label-secondary me-2">${role}</span>`;
            });

            return `<span class="text-nowrap">${output}</span>`;
          }
        },
        {
          // remove ordering from Name
          targets: 4,
          orderable: false,
          render: function (data, type, full, meta) {
            let date = full['created_date'];
            return '<span class="text-nowrap">' + date + '</span>';
          }
        },
        {
          // Actions
          targets: -1,
          searchable: false,
          title: 'Actions',
          orderable: false,
          render: function (data, type, full, meta) {
            return `
              <div class="d-flex align-items-center">
                <span class="text-nowrap">
                  <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill delete-record text-body waves-effect me-1">
                    <i class="icon-base ri ri-delete-bin-7-line icon-20px"></i>
                  </button>
                  <a href="javascript:;" class="btn btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-target="#editPermissionModal" data-bs-toggle="modal" data-bs-dismiss="modal">
                    <i class="icon-base ri ri-edit-box-line icon-20px"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end m-0">
                    <a href="javascript:;" class="dropdown-item">Edit</a>
                    <a href="javascript:;" class="dropdown-item">Suspend</a>
                  </div>
                </span>
              </div>
            `;
          }
        }
      ],
      order: [[1, 'asc']],
      layout: {
        topStart: {
          rowClass: 'row mx-2',
          features: [
            {
              pageLength: {
                menu: [10, 25, 50, 100],
                text: 'Show_MENU_'
              }
            }
          ]
        },
        topEnd: {
          features: [
            {
              search: {
                placeholder: 'Search Permissions',
                text: '_INPUT_'
              }
            },
            {
              buttons: [
                {
                  text: `<i class="icon-base ri ri-add-line icon-sm me-0 me-sm-1"></i><span class="d-none d-sm-inline-block">Add Permission</span>`,
                  className: 'add-new btn btn-primary',
                  attr: {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#addPermissionModal'
                  }
                }
              ]
            }
          ]
        },
        bottomStart: {
          rowClass: 'row mx-3 justify-content-between',
          features: ['info']
        },
        bottomEnd: 'paging'
      },
      language: {
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
          first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
          last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      responsive: {
        details: {
          display: DataTable.Responsive.display.modal({
            header: function (row) {
              const data = row.data();
              return 'Details of ' + data['name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            const data = columns
              .map(function (col) {
                return col.title !== '' //? Do not show row in modal popup if title is blank (for check box)
                  ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                      <td>${col.title}:</td>
                      <td>${col.data}</td>
                    </tr>`
                  : '';
              })
              .join('');

            if (data) {
              const div = document.createElement('div');
              div.classList.add('table-responsive');
              const table = document.createElement('table');
              div.appendChild(table);
              table.classList.add('table');
              const tbody = document.createElement('tbody');
              tbody.innerHTML = data;
              table.appendChild(tbody);
              return div;
            }
            return false;
          }
        }
      }
    });
    // Expose instance for window.loadPermissions()
    dataTablePermissions._dt = dt_permission;
  }

  // Filter form control to default size
  // ? setTimeout used for multilingual table initialization
  setTimeout(() => {
    const elementsToModify = [
      { selector: '.dt-buttons .btn', classToRemove: 'btn-secondary' },
      { selector: '.dt-search', classToAdd: 'me-4' },
      { selector: '.dt-length', classToAdd: 'mb-0 mb-md-5' },
      { selector: '.dt-buttons', classToAdd: 'mb-0 w-auto' },
      { selector: '.dt-layout-start', classToAdd: 'mt-0 px-5' },
      {
        selector: '.dt-layout-end',
        classToAdd: 'justify-content-md-between justify-content-center d-flex',
        classToRemove: 'justify-content-between d-md-flex'
      },
      { selector: '.dt-layout-table', classToRemove: 'row mt-2' },
      { selector: '.dt-layout-full', classToRemove: 'col-md col-12', classToAdd: 'table-responsive' }
    ];

    // Delete record
    elementsToModify.forEach(({ selector, classToRemove, classToAdd }) => {
      document.querySelectorAll(selector).forEach(element => {
        if (classToRemove) {
          classToRemove.split(' ').forEach(className => element.classList.remove(className));
        }
        if (classToAdd) {
          classToAdd.split(' ').forEach(className => element.classList.add(className));
        }
      });
    });
  }, 100);

  // Delete Record
  const deleteModalEl = document.getElementById('confirmDeletePermissionModal');
  const deletePermissionNameLabel = document.getElementById('deletePermissionName');
  const confirmDeletePermissionBtn = document.getElementById('confirmDeletePermissionBtn');
  let deleteModalInstance = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;
  let pendingDeletePermissionId = null;

  console.log('Delete modal element:', deleteModalEl);

  async function handleDeleteConfirmation() {
    console.log('Confirm Delete clicked, ID:', pendingDeletePermissionId);
    if (!pendingDeletePermissionId) return;
    try {
      $.ajax({
        type: 'POST',
        url: baseUrl + 'permissions/destroy',
        data: {
          id: pendingDeletePermissionId,
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
          console.log('Delete success:', response);
          if (dt_permission) {
            dt_permission.ajax.reload();
          } else {
            const dataTablePermissions = document.querySelector('.datatables-permissions');
            if (dataTablePermissions && dataTablePermissions._dt) {
              dataTablePermissions._dt.ajax.reload();
            }
          }
          deleteModalInstance?.hide();
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: response.message,
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });
          }
        },
        error: function (error) {
          console.error('Delete error:', error);
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              title: 'Error!',
              text: 'An error occurred while deleting the permission.',
              icon: 'error',
              customClass: {
                confirmButton: 'btn btn-primary'
              }
            });
          }
        }
      });
    } catch (error) {
      console.error(error);
    }
  }

  if (confirmDeletePermissionBtn) {
    confirmDeletePermissionBtn.addEventListener('click', handleDeleteConfirmation);
  }

  $(document).on('click', '.delete-record', function () {
    console.log('Delete record clicked - handler START');

    // Ensure modal element is found
    const modalEl = document.getElementById('confirmDeletePermissionModal');
    console.log('Modal element check:', modalEl);

    if (!modalEl) {
      console.error('CRITICAL: #confirmDeletePermissionModal not found in DOM!');
      alert('Delete modal not found. Please refresh.');
      return;
    }

    const table = $('.datatables-permissions').DataTable();
    const row = table.row($(this).closest('tr'));
    const data = row.data();
    console.log('Row data:', data);

    if (!data || !data.id) {
      console.error('Could not retrieve ID from row data');
      return;
    }

    pendingDeletePermissionId = data.id;

    if (deletePermissionNameLabel) {
      deletePermissionNameLabel.textContent = data.name;
    }

    // Re-initialize or get instance
    if (typeof bootstrap !== 'undefined') {
      console.log('Bootstrap is available, showing modal');
      let modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (!modalInstance) {
        console.log('Creating new Bootstrap modal instance');
        modalInstance = new bootstrap.Modal(modalEl);
      }
      modalInstance.show();
    } else {
      console.error('Bootstrap is NOT defined in global scope!');
      alert('Bootstrap library missing. Cannot open modal.');
    }
  });
});

// Function to reload permissions table
window.loadPermissions = function () {
  const dataTablePermissions = document.querySelector('.datatables-permissions');
  if (dataTablePermissions && dataTablePermissions._dt) {
    dataTablePermissions._dt.ajax.reload();
  }
};
