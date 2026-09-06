/**
 * App user list
 */

'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function (e) {
  const dtUserTable = document.querySelector('.datatables-users');
  let dt_User,
    userView = baseUrl + 'app/user/view/account';

  // Users List datatable
  if (dtUserTable) {
    const deleteModalEl = document.getElementById('confirmDeleteRoleModal');
    const deleteRoleNameLabel = document.getElementById('deleteRoleName');
    const confirmDeleteRoleBtn = document.getElementById('confirmDeleteRoleBtn');
    let deleteModalInstance = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;
    let pendingDeleteRoleName = null;

    async function handleDeleteConfirmation() {
      if (!pendingDeleteRoleName) return;
      try {
        const resp = await fetch(baseUrl + 'roles/destroy', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ name: pendingDeleteRoleName })
        });
        const json = await resp.json();

        if (resp.ok && json.success) {
          if (typeof toastr !== 'undefined') toastr.success(json.message || 'Role deleted successfully');
          pendingDeleteRoleName = null;
          deleteModalInstance?.hide();
          if (json.redirect) {
            window.location.href = json.redirect;
          } else {
            dt_User.ajax.reload();
          }
        } else {
          if (typeof toastr !== 'undefined') toastr.error(json.message || 'Failed to delete role');
        }
      } catch (error) {
        if (typeof toastr !== 'undefined') toastr.error('Failed to delete role');
      }
    }

    if (confirmDeleteRoleBtn) {
      confirmDeleteRoleBtn.addEventListener('click', handleDeleteConfirmation);
    }

    const protectedRoleNames = ['super admin', 'admin'];

    dt_User = new DataTable(dtUserTable, {
      ajax: {
        url: baseUrl + 'roles/users',
        dataSrc: 'data'
      },
      columns: [
        { data: null, defaultContent: '' }, // control
        { data: null, defaultContent: '' }, // checkbox
        { data: null, defaultContent: '' }, // S.No
        { data: null, defaultContent: '' }, // user name
        { data: null, defaultContent: '' }, // role
        { data: null, defaultContent: '' }  // actions
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          orderable: false,
          searchable: false,
          responsivePriority: 5,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          // For Checkboxes
          targets: 1,
          orderable: false,
          searchable: false,
          responsivePriority: 3,
          checkboxes: true,
          render: function () {
            return '<input type="checkbox" class="dt-checkboxes form-check-input">';
          },
          checkboxes: {
            selectAllRender: '<input type="checkbox" class="form-check-input">'
          }
        },
        {
          // Serial number
          targets: 2,
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            return meta.row + 1 + meta.settings._iDisplayStart;
          }
        },
        {
          // User (name only)
          targets: 3,
          responsivePriority: 1,
          render: function (data, type, full, meta) {
            const name = full['primary_user'] || 'Unassigned';
            const count = full['user_count'] || 0;
            const subLabel = count === 0 ? 'No users' : count === 1 ? '1 user' : `${count} users`;
            return `
              <div class="d-flex flex-column">
                <span class="text-heading fw-medium">${name}</span>
                <small class="text-muted">${subLabel}</small>
              </div>
            `;
          }
        },
        {
          // Role
          targets: 4,
          render: function (data, type, full, meta) {
            const role = full['role_name'] || '-';
            const roleBadgeObj = {
              Subscriber: '<i class="icon-base ri ri-user-line icon-22px text-primary me-2"></i>',
              Author: '<i class="icon-base ri ri-vip-crown-line icon-22px text-warning me-2"></i>',
              Maintainer: '<i class="icon-base ri ri-pie-chart-line icon-22px text-success me-2"></i>',
              Editor: '<i class="icon-base ri ri-edit-box-line icon-22px text-info me-2"></i>',
              Admin: '<i class="icon-base ri ri-computer-line icon-22px  text-danger me-2"></i>'
            };

            return `<span class='text-truncate d-flex align-items-center'>${roleBadgeObj[role] || ''}${role}</span>`;
          }
        },
        {
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            const roleName = full['role_name'] || '';
            const isProtected = protectedRoleNames.includes(roleName.toLowerCase());
            return `
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect edit-role" title="Edit Role" data-role-name="${roleName}">
                  <i class="icon-base ri ri-shield-user-line icon-22px"></i>
                </button>
                <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-role ${isProtected ? 'disabled' : ''}" title="${isProtected ? 'Super Admin role cannot be deleted' : 'Delete Role'}" data-role-name="${roleName}" data-is-protected="${isProtected}">
                  <i class="icon-base ri ri-delete-bin-7-line icon-22px"></i>
                </button>
              </div>
            `;
          }
        }
      ],
      select: {
        style: 'multi',
        selector: 'td:nth-child(2)'
      },
      order: [[3, 'asc']],
      layout: {
        topStart: {
          rowClass: 'row mx-2',
          features: [
            {
              pageLength: {
                menu: [10, 25, 50, 100],
                text: 'Show _MENU_'
              }
            },
            {
              buttons: [
                {
                  extend: 'collection',
                  className: 'btn btn-outline-secondary dropdown-toggle',
                  text: '<span class="d-flex align-items-center gap-1"><i class="icon-base ri ri-download-line icon-16px me-1"></i> <span class="d-none d-sm-inline-block">Export</span></span>',
                  buttons: [
                    {
                      extend: 'print',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ri ri-printer-line me-1"></i>Print</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            if (inner.indexOf('<') > -1) {
                              const parser = new DOMParser();
                              const doc = parser.parseFromString(inner, 'text/html');
                              return (doc.body.textContent || doc.body.innerText || '').trim();
                            }
                            return inner;
                          }
                        }
                      },
                      customize: function (win) {
                        win.document.body.style.color = config.colors.headingColor;
                        win.document.body.style.borderColor = config.colors.borderColor;
                        win.document.body.style.backgroundColor = config.colors.bodyBg;
                        win.document.body.style.fontFamily = '"Public Sans", sans-serif';
                        const table = win.document.body.querySelector('table');
                        if (table) {
                          table.classList.add('table', 'table-bordered', 'table-sm', 'compact');
                          table.style.color = 'inherit';
                          table.style.borderColor = 'inherit';
                          table.style.backgroundColor = 'inherit';
                          table.style.borderCollapse = 'collapse';
                          table.style.width = '100%';
                          table.querySelectorAll('th, td').forEach(cell => {
                            cell.style.border = '1px solid ' + config.colors.borderColor;
                            cell.style.padding = '8px';
                            cell.style.textAlign = 'left';
                          });
                        }
                      }
                    },
                    {
                      extend: 'csv',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ri ri-file-text-line me-1"></i>Csv</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      }
                    },
                    {
                      extend: 'excel',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ri ri-file-excel-line me-1"></i>Excel</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      }
                    },
                    {
                      extend: 'pdfHtml5',
                      text: `<span class="d-flex align-items-center"><i class="icon-base ri ri-file-pdf-line me-1"></i>Pdf</span>`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      },
                      customize: function (doc) {
                        doc.content[0].text = 'User Roles | Loan App';
                        doc.defaultStyle.fontSize = 10;
                        doc.styles.tableHeader.fontSize = 10;
                        doc.styles.tableHeader.alignment = 'left';
                        const tableContent = doc.content.find(item => item.table);
                        if (tableContent) {
                          tableContent.table.widths = ['40%', '30%', '30%'];
                          tableContent.layout = {
                            hLineWidth: function () { return 0.5; },
                            vLineWidth: function () { return 0.5; },
                            hLineColor: function () { return '#cccccc'; },
                            vLineColor: function () { return '#cccccc'; },
                            paddingLeft: function () { return 6; },
                            paddingRight: function () { return 6; },
                            paddingTop: function () { return 6; },
                            paddingBottom: function () { return 6; }
                          };
                        }
                      }
                    },
                    {
                      extend: 'copy',
                      text: `<i class="icon-base ri ri-file-copy-line me-1"></i>Copy`,
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4],
                        format: {
                          body: function (inner) {
                            if (inner.length <= 0) return inner;
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');
                            return (doc.body.textContent || doc.body.innerText || '').trim();
                          }
                        }
                      }
                    }
                  ]
                }
              ]
            }
          ]
        },
        topEnd: {
          features: [
            {
              search: {
                placeholder: 'Search User',
                text: '_INPUT_'
              }
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
      // For responsive popup
      responsive: {
        details: {
          display: DataTable.Responsive.display.modal({
            header: function (row) {
              const data = row.data();
              return 'Details of ' + data['full_name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            const data = columns
              .map(function (col) {
                return col.title !== '' // Do not show row in modal popup if title is blank (for check box)
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
      },
      initComplete: function () {
        // No additional filters
      }
    });


    //? The 'delete-record' class is necessary for the functionality of the following code.
    function deleteRecord(event) {
      let row = document.querySelector('.dtr-expanded');
      if (event) {
        row = event.target.parentElement.closest('tr');
      }
      if (row) {
        dt_User.row(row).remove().draw();
      }
    }

    function bindRoleActions() {
      const tableBody = document.querySelector('.datatables-users tbody');
      if (!tableBody) return;

      tableBody.addEventListener('click', async function (event) {
        const editBtn = event.target.closest('.edit-role');
        const deleteBtn = event.target.closest('.delete-role');

        if (editBtn) {
          const roleName = editBtn.getAttribute('data-role-name');
          const modalEl = document.getElementById('addRoleModal');
          if (!modalEl) return;
          const $form = $('#addRoleForm');
          // Set to update endpoint
          $form.data('url', baseUrl + 'roles/update');
          // Set title and fields
          document.querySelector('.role-title').innerHTML = 'Edit Role';
          $('#roleName').val(roleName);
          // ensure hidden original name field exists and set
          if (!$('#originalRoleName').length) {
            $('<input>').attr({ type: 'hidden', name: 'original_name', id: 'originalRoleName' }).appendTo($form);
          }
          $('#originalRoleName').val(roleName);

          // Clear selections
          $('.permission-checkbox').prop('checked', false);
          $('#selectAllPermissions').prop('checked', false);

          // Load current permissions
          try {
            const resp = await fetch(baseUrl + 'roles/permissions?role=' + encodeURIComponent(roleName));
            const json = await resp.json();
            const perms = json.permissions || [];
            perms.forEach(p => {
              $(".permission-checkbox[value='" + p.replace(/'/g, "\\'") + "']").prop('checked', true);
            });
          } catch (e) {}

          // Show modal
          const bsModal = new bootstrap.Modal(modalEl);
          bsModal.show();
        }

        if (deleteBtn) {
          const roleName = deleteBtn.getAttribute('data-role-name');
          const isProtected = deleteBtn.getAttribute('data-is-protected') === 'true';

          if (isProtected) {
            if (typeof toastr !== 'undefined') toastr.info('This role cannot be deleted.');
            return;
          }

          if (!roleName) return;
          pendingDeleteRoleName = roleName;
          if (deleteRoleNameLabel) deleteRoleNameLabel.textContent = roleName;
          if (!deleteModalInstance && deleteModalEl) {
            deleteModalInstance = new bootstrap.Modal(deleteModalEl);
          }
          deleteModalInstance?.show();
        }
      });
    }

    // Initial event binding
    bindRoleActions();

    // Re-bind events when modal is shown or hidden
    document.addEventListener('show.bs.modal', function (event) {
      if (event.target.classList.contains('dtr-bs-modal')) {
        bindRoleActions();
      }
    });

    document.addEventListener('hide.bs.modal', function (event) {
      if (event.target.classList.contains('dtr-bs-modal')) {
        bindRoleActions();
      }
    });
  }

  // Filter form control to default size
  // ? setTimeout used for multilingual table initialization
  setTimeout(() => {
    const elementsToModify = [
      { selector: '.dt-length', classToAdd: 'my-md-5 my-0 me-lg-2 me-md-1 me-2' },
      { selector: '.dt-buttons', classToAdd: 'd-block w-auto', classToRemove: 'flex-wrap' },
      { selector: '.user_role', classToAdd: 'w-px-200' },
      { selector: '.dt-search', classToRemove: 'mt-5', classToAdd: 'mb-sm-5 mb-0' },
      {
        selector: '.dt-layout-start',
        classToAdd: 'mt-5 mt-md-0 px-lg-5 pe-0 ps-2 d-flex justify-content-center',
        classToRemove: 'justify-content-between'
      },
      {
        selector: '.dt-layout-end',
        classToRemove: 'justify-content-between',
        classToAdd:
          'justify-content-md-between justify-content-center d-flex flex-wrap gap-sm-4 gap-5 ps-lg-3 ps-0 mt-0 mb-sm-0 mb-5 gap-md-3 gap-lg-4'
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

  // On edit role click, update text
  var roleEditList = document.querySelectorAll('.role-edit-modal'),
    roleAdd = document.querySelector('.add-new-role'),
    roleTitle = document.querySelector('.role-title');

  roleAdd.onclick = function () {
    roleTitle.innerHTML = 'Add New Role'; // reset text
    const $form = $('#addRoleForm');
    // Reset endpoint to create
    $form.data('url', baseUrl + 'roles/store');
    // Clear hidden original name if exists
    $('#originalRoleName').remove();
    $('#roleName').val('');
    // Clear selections
    $('.permission-checkbox').prop('checked', false);
    $('#selectAllPermissions').prop('checked', false);
  };
  if (roleEditList) {
    roleEditList.forEach(function (roleEditEl) {
      roleEditEl.onclick = function () {
        roleTitle.innerHTML = 'Edit Role'; // reset text
      };
    });
  }
});
