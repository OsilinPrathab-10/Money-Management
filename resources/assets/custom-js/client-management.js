/**
 * Page Client Management
 */

'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function (e) {
  let borderColor, bodyBg, headingColor;

  borderColor = config.colors.borderColor;
  bodyBg = config.colors.bodyBg;
  headingColor = config.colors.headingColor;

  // Variable declaration for table
  const dt_user_table = document.querySelector('.datatables-users'),
    userViewBase = baseUrl + 'clients/view/account/',
    offCanvasForm = document.getElementById('offcanvasAddUser');

  // Select2 initialization
  var select2 = $('.select2');
  if (select2.length) {
    var $this = select2;
    select2Focus($this);
    $this.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select Country',
      dropdownParent: $this.parent()
    });
  }

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Users datatable
  if (dt_user_table) {
    const dt_user = new DataTable(dt_user_table, {
      processing: true,
      serverSide: true,
      scrollX: true,
      scrollCollapse: true,
      autoWidth: false,
      ajax: {
        url: baseUrl + 'client-list',
        data: function (d) {
          d.location_id = $('#FilterLocation').val();
          d.status = $('#FilterStatus').val();
        },
        dataSrc: function (json) {
          // Ensure recordsTotal and recordsFiltered are numeric and not undefined/null
          if (typeof json.recordsTotal !== 'number') {
            json.recordsTotal = 0;
          }
          if (typeof json.recordsFiltered !== 'number') {
            json.recordsFiltered = 0;
          }

          // Fallback for empty data to avoid pagination NaN issue
          json.data = Array.isArray(json.data) ? json.data : [];

          return json.data;
        }
      },
      columns: [
        // columns according to JSON
        { data: 'id' },
        { 
          data: 'id',
          orderable: false,
          searchable: false,
          render: function (data) {
            return `<div class="form-check"><input class="form-check-input dt-checkboxes" type="checkbox" value="${data}"></div>`;
          }
        },
        { 
          render: function (data, type, full, meta) {
            return meta.row + meta.settings._iDisplayStart + 1;
          }
        },
        { data: 'name' },
        { data: 'email' },
        { data: 'mobile' },
        { data: 'zone' },
        { data: 'agent_name' },
        { data: 'added_by_name' },
        { data: 'status' },
        { data: 'action' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          searchable: false,
          orderable: false,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          searchable: false,
          orderable: false,
          targets: 1, // Checkbox column
          visible: (window.userRole !== 'Agent') // Hide for agents
        },
        {
          searchable: false,
          orderable: true,
          targets: 2, // S.No column
        },
        {
          // User full name
          targets: 3,
          render: function (data, type, full, meta) {
            const { name, id } = full;
            return `<a href="${userViewBase}${id}" class="text-heading"><span class="fw-medium">${name}</span></a>`;
          }
        },
        {
          // User email
          targets: 4,
          render: function (data, type, full, meta) {
            let email = full['email'];
            if (!email || email === 'null' || email.trim() === '') {
              email = 'N/A';
            }
            return '<span class="user-email">' + email + '</span>';
          }
        },
        {
          // Mobile number
          targets: 5,
          render: function (data, type, full) {
            const mobile = full['mobile'] || 'N/A';
            return `<span class="user-mobile">${mobile}</span>`;
          }
        },
        {
          // Zone
          targets: 6,
          render: function (data, type, full) {
            const zone = full['zone'] || 'N/A';
            return `<span class="badge bg-label-secondary">${zone}</span>`;
          }
        },
        {
          // Assigned Agent
          targets: 7,
          render: function (data, type, full) {
            const agentName = full['agent_name'];
            const isAgent = (window.userRole === 'Agent');
            
            if (agentName) {
              if (isAgent) return `<span class="fw-semibold text-heading">${agentName}</span>`;
              return `<a href="javascript:void(0)" class="text-primary fw-semibold reassign-agent" data-id="${full['id']}" data-current-agent-id="${full['agent_id']}" title="Click to Reassign Agent">${agentName}</a>`;
            }
            
            if (isAgent) return `<span class="text-muted small">N/A</span>`;
            return `<a href="javascript:void(0)" class="text-danger small fw-bold reassign-agent" data-id="${full['id']}" title="Assign Agent"><i class="ri-user-add-line me-1"></i>Assign</a>`;
          }
        },
        {
          // Added By
          targets: 8,
          render: function (data, type, full) {
            const addedByName = full['added_by_name'] || 'Admin';
            return `<span class="badge bg-label-info">${addedByName}</span>`;
          }
        },
        {
          // Status
          targets: 9,
          className: 'text-center',
          render: function (data, type, full, meta) {
            const status = (full['status'] || 'inactive').toLowerCase();
            // Map all 5 database status values to only 3 badge displays
            const statusMap = {
              active: { label: 'Active', color: 'success' },
              verified: { label: 'Active', color: 'success' },
              pending: { label: 'Pending', color: 'warning' },
              rejected: { label: 'Rejected', color: 'danger' },
              inactive: { label: 'Inactive', color: 'secondary' },
              unverified: { label: 'Inactive', color: 'secondary' },
              blacklist: { label: 'Blacklisted', color: 'dark' }
            };
            const statusConfig = statusMap[status] || { label: 'Inactive', color: 'danger' };
            return `<span class="badge bg-label-${statusConfig.color}">${statusConfig.label}</span>`;
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            let actions = '<div class="d-flex align-items-center gap-2 flex-nowrap">';
            
            // Apply Loan for Agents (only if active/verified)
            if (window.userRole === 'Agent' && (full['status'] === 'active' || full['status'] === 'verified')) {
              actions += `<button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill apply-loan-modal" data-id="${full['fake_id']}" title="Apply Loan"><i class="icon-base ri ri-hand-coin-line icon-22px"></i></button>`;
            }

            // Only show Toggle Status and Delete for Admin and Staff
            if (window.userRole === 'Admin' || window.userRole === 'Staff') {
              actions += `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill toggle-status" data-id="${full['id']}" title="Toggle Status"><i class="icon-base ri ri-refresh-line icon-22px"></i></button>`;
              actions += `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill delete-record" data-id="${full['id']}"><i class="icon-base ri ri-delete-bin-7-line icon-22px"></i></button>`;
            }
            
            actions += `<a href="${userViewBase}${full['id']}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill"><i class="icon-base ri ri-eye-line icon-22px"></i></a>`;
            actions += '</div>';
            return actions;
          }
        }
      ],
      order: [[2, 'desc']],
      layout: {
        topStart: {
          rowClass: 'row m-3 my-0 justify-content-between',
          features: [
            {
              pageLength: {
                menu: [7, 10, 20, 50, 70, 100],
                text: '_MENU_'
              }
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
            },
            {
              buttons: [
                {
                  extend: 'collection',
                  className: 'btn btn-label-secondary dropdown-toggle',
                  text: '<i class="icon-base ri ri-upload-2-line me-2 icon-sm"></i>Export',
                  buttons: [
                    {
                      extend: 'print',
                      title: 'Clients',
                      text: '<i class="icon-base ri ri-printer-line me-2" ></i>Print',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4, 5, 6, 7, 8, 9],
                        // prevent avatar to be print
                        format: {
                          body: function (inner, coldex, rowdex) {
                            if (inner == null) return '';
                            if (typeof inner !== 'string') return String(inner);
                            if (inner.trim().length === 0) return '';

                            // Check if inner is HTML content
                            if (inner.indexOf('<') > -1) {
                              const parser = new DOMParser();
                              const doc = parser.parseFromString(inner, 'text/html');

                              // Get all text content
                              let text = '';

                              // Handle specific elements
                              const userNameElements = doc.querySelectorAll('.user-name');
                              if (userNameElements.length > 0) {
                                userNameElements.forEach(el => {
                                  // Get text from nested structure
                                  const nameText =
                                    el.querySelector('.fw-medium')?.textContent ||
                                    el.querySelector('.d-block')?.textContent ||
                                    el.textContent;
                                  text += nameText.trim() + ' ';
                                });
                              } else {
                                // Get regular text content
                                text = doc.body.textContent || doc.body.innerText;
                              }

                              return text.trim();
                            }

                            // Handle badges / buttons
                            if (inner.indexOf('badge') > -1) {
                              const parserBadge = new DOMParser();
                              const docBadge = parserBadge.parseFromString(inner, 'text/html');
                              const badgeText = docBadge.body.textContent || docBadge.body.innerText;
                              return badgeText.trim();
                            }

                            return inner;
                          }
                        }
                      },
                      customize: function (win) {
                        win.document.body.style.color = headingColor;
                        win.document.body.style.borderColor = borderColor;
                        win.document.body.style.backgroundColor = bodyBg;
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
                            cell.style.border = '1px solid ' + borderColor;
                            cell.style.padding = '8px';
                            cell.style.textAlign = 'left';
                          });
                        }
                      }
                    },
                    {
                      extend: 'csv',
                      title: 'Clients',
                      text: '<i class="icon-base ri ri-file-text-line me-2" ></i>Csv',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4, 5, 6, 7, 8, 9],
                        format: {
                          body: function (inner, coldex, rowdex) {
                            if (inner == null) return '';
                            if (typeof inner !== 'string') return String(inner);
                            if (inner.trim().length === 0) return '';

                            // Parse HTML content
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');

                            let text = '';

                            // Handle user-name elements specifically
                            const userNameElements = doc.querySelectorAll('.user-name');
                            if (userNameElements.length > 0) {
                              userNameElements.forEach(el => {
                                // Get text from nested structure - try different selectors
                                const nameText =
                                  el.querySelector('.fw-medium')?.textContent ||
                                  el.querySelector('.d-block')?.textContent ||
                                  el.textContent;
                                text += nameText.trim() + ' ';
                              });
                            } else {
                              // Handle other elements (status, role, etc)
                              text = doc.body.textContent || doc.body.innerText;
                            }

                            return text.trim();
                          }
                        }
                      }
                    },
                    {
                      extend: 'excel',
                      title: 'Clients',
                      text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4, 5, 6, 7, 8, 9],
                        format: {
                          body: function (inner, coldex, rowdex) {
                            if (inner == null) return '';
                            if (typeof inner !== 'string') return String(inner);
                            if (inner.trim().length === 0) return '';

                            // Parse HTML content
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(inner, 'text/html');

                            let text = '';

                            // Handle user-name elements specifically
                            const userNameElements = doc.querySelectorAll('.user-name');
                            if (userNameElements.length > 0) {
                              userNameElements.forEach(el => {
                                // Get text from nested structure - try different selectors
                                const nameText =
                                  el.querySelector('.fw-medium')?.textContent ||
                                  el.querySelector('.d-block')?.textContent ||
                                  el.textContent;
                                text += nameText.trim() + ' ';
                              });
                            } else {
                              // Handle other elements (status, role, etc)
                              text = doc.body.textContent || doc.body.innerText;
                            }

                            return text.trim();
                          }
                        }
                      }
                    },
                    {
                      extend: 'pdfHtml5',
                      title: 'Clients',
                      text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>Pdf',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4, 5, 6, 7, 8, 9],
                        format: {
                          body: function (inner) {
                            if (inner == null) return '';
                            inner = typeof inner === 'string' ? inner : String(inner);
                            if (inner.trim().length === 0) return '';
                            const parser = new DOMParser();
                            const htmlDoc = parser.parseFromString(inner, 'text/html');
                            const userNameElements = htmlDoc.querySelectorAll('.user-name');
                            if (userNameElements.length) {
                              return Array.from(userNameElements)
                                .map(el => (el.querySelector('.fw-medium')?.textContent || el.textContent || '').trim())
                                .join(' ');
                            }
                            return (htmlDoc.body.textContent || htmlDoc.body.innerText || '').trim();
                          }
                        }
                      },
                      customize: function (doc) {
                        doc.content[0].text = 'Client Management | Loan App';
                        doc.defaultStyle.fontSize = 10;
                        doc.styles.tableHeader.fontSize = 10;
                        doc.styles.tableHeader.alignment = 'left';
                        doc.styles.tableHeader.fillColor = '#f5f5f5';
                        doc.styles.tableHeader.color = '#333333';

                        doc.content.splice(1, 0, {
                          text: 'Clients Information Report',
                          margin: [0, 0, 0, 12],
                          fontSize: 12,
                          bold: true
                        });

                        const tableContent = doc.content.find(item => item.table);
                        if (tableContent) {
                          tableContent.table.widths = ['10%', '30%', '25%', '15%', '20%'];
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
                          tableContent.table.body.forEach(function (row) {
                            row.forEach(function (cell) {
                              cell.alignment = 'left';
                              cell.margin = [0, 4, 0, 4];
                            });
                          });
                        }
                      }
                    },
                    {
                      extend: 'copy',
                      title: 'Clients',
                      text: '<i class="icon-base ri ri-file-copy-line me-2" ></i>Copy',
                      className: 'dropdown-item',
                      exportOptions: {
                        columns: [2, 3, 4, 5, 6, 7, 8, 9],
                        format: {
                          body: function (inner) {
                            if (inner == null) return '';
                            inner = typeof inner === 'string' ? inner : String(inner);
                            if (inner.trim().length === 0) return '';
                            const parser = new DOMParser();
                            const htmlDoc = parser.parseFromString(inner, 'text/html');
                            const userNameElements = htmlDoc.querySelectorAll('.user-name');
                            if (userNameElements.length) {
                              return Array.from(userNameElements)
                                .map(el => (el.querySelector('.fw-medium')?.textContent || el.textContent || '').trim())
                                .join(' ');
                            }
                            return (htmlDoc.body.textContent || htmlDoc.body.innerText || '').trim();
                          }
                        }
                      }
                    }
                  ]
                }
                // Removed "Add New Client" button
              ]
            }
          ]
        },
        bottomStart: {
          rowClass: 'row mx-3 justify-content-between',
          features: [
            {
              info: {
                text: 'Showing _START_ to _END_ of _TOTAL_ entries',
                callback: function (settings, start, end, max, total) {
                  const visibleCount = settings.fnRecordsDisplay();
                  return `Showing ${start} to ${end} of ${visibleCount} entries`;
                }
              }
            }
          ]
        },
        bottomEnd: 'paging'
      },
      displayLength: 20,
      language: {
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
          first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
          last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      // Horizontal scroll instead of responsive collapse
      // Horizontal scroll instead of responsive collapse
      responsive: false,
      initComplete: function () {
        // Remove btn-secondary from export buttons
        document.querySelectorAll('.dt-buttons .btn').forEach(btn => {
          btn.classList.remove('btn-secondary');
        });
      }
    });

    // Filter change event
    $('#FilterLocation, #FilterStatus').on('change', function () {
      dt_user.draw();
    });

    // Bulk Assignment Logic for Clients
    const selectAllClients = $('#selectAllClients');
    const btnBulkAssignClients = $('#btnBulkAssignClients');
    const btnBulkDeleteClients = $('#btnBulkDeleteClients');
    const assignAgentModal = new bootstrap.Modal(document.getElementById('assignAgentModal'));
    const assignCountLabel = $('#assignCount');
    const assignAgentForm = $('#assignAgentForm');
    const btnConfirmAssign = $('#btnConfirmAssign');

    function updateBulkActions() {
      const checkedCount = $('.dt-checkboxes:checked').length;
      if (checkedCount > 0) {
        btnBulkAssignClients.removeClass('d-none');
        btnBulkDeleteClients.removeClass('d-none');
      } else {
        btnBulkAssignClients.addClass('d-none');
        btnBulkDeleteClients.addClass('d-none');
        selectAllClients.prop('checked', false);
      }
    }

    dt_user.on('draw', function () {
      selectAllClients.prop('checked', false);
      updateBulkActions();
    });

    // Bulk Delete Action
    btnBulkDeleteClients.on('click', function() {
      const selectedIds = [];
      $('.dt-checkboxes:checked').each(function() {
        selectedIds.push($(this).val());
      });

      if (selectedIds.length === 0) return;

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Are you sure?',
          text: `You want to delete these ${selectedIds.length} selected clients? You won't be able to revert this!`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete them!',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'btn btn-danger me-3 waves-effect waves-light',
            cancelButton: 'btn btn-outline-secondary waves-effect'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            Swal.fire({
              title: 'Deleting...',
              text: 'Please wait while we delete the selected clients.',
              allowOutsideClick: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });

            $.ajax({
              url: baseUrl + 'client-management/bulk-delete',
              type: 'POST',
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              },
              data: {
                client_ids: selectedIds,
                _token: $('meta[name="csrf-token"]').attr('content')
              },
              success: function(response) {
                if (response.success) {
                  Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: response.message,
                    customClass: { confirmButton: 'btn btn-success' }
                  });
                  selectAllClients.prop('checked', false);
                  dt_user.draw(false);
                  updateBulkActions();
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: response.message,
                    customClass: { confirmButton: 'btn btn-primary' }
                  });
                }
              },
              error: function(xhr) {
                const error = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred while deleting clients.';
                Swal.fire({
                  icon: 'error',
                  title: 'Error!',
                  text: error,
                  customClass: { confirmButton: 'btn btn-primary' }
                });
              }
            });
          }
        });
      } else {
        // Fallback if Swal is not loaded
        if (confirm(`Are you sure you want to delete these ${selectedIds.length} selected clients?`)) {
          $.ajax({
            url: baseUrl + 'client-management/bulk-delete',
            type: 'POST',
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
              client_ids: selectedIds,
              _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
              if (response.success) {
                alert(response.message);
                selectAllClients.prop('checked', false);
                dt_user.draw(false);
                updateBulkActions();
              } else {
                alert('Error: ' + response.message);
              }
            },
            error: function(xhr) {
              alert('Error deleting clients.');
            }
          });
        }
      }
    });

    selectAllClients.on('change', function() {
      $('.dt-checkboxes').prop('checked', $(this).is(':checked'));
      updateBulkActions();
    });

    $('.datatables-users').on('change', '.dt-checkboxes', function() {
      updateBulkActions();
    });

    btnBulkAssignClients.on('click', function() {
      const checkedCount = $('.dt-checkboxes:checked').length;
      assignCountLabel.text(checkedCount);
      assignAgentModal.show();
    });

    // Handle Assignment Submission
    assignAgentForm.on('submit', function(e) {
      e.preventDefault();
      
      const selectedIds = [];
      $('.dt-checkboxes:checked').each(function() {
        selectedIds.push($(this).val());
      });

      if (selectedIds.length === 0) return;

      const formData = {
        client_ids: selectedIds,
        agent_id: $('#agentSelect').val(),
        remarks: $('#assignRemarks').val(),
        _token: $('input[name="_token"]').val()
      };

      // Show loading
      btnConfirmAssign.prop('disabled', true);
      btnConfirmAssign.find('.spinner-border').removeClass('d-none');

      $.ajax({
        url: baseUrl + 'client-management/bulk-assign',
        type: 'POST',
        data: formData,
        success: function(response) {
          btnConfirmAssign.prop('disabled', false);
          btnConfirmAssign.find('.spinner-border').addClass('d-none');
          
          if (response.success) {
            assignAgentModal.hide();
            
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'success',
                title: 'Assigned!',
                text: response.message,
                customClass: { confirmButton: 'btn btn-success' }
              });
            }
            
            selectAllClients.prop('checked', false);
            dt_user.draw(false);
            updateBulkActions();
          } else {
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: response.message,
                customClass: { confirmButton: 'btn btn-primary' }
              });
            }
          }
        },
        error: function(xhr) {
          btnConfirmAssign.prop('disabled', false);
          btnConfirmAssign.find('.spinner-border').addClass('d-none');
          const error = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred while assigning clients.';
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: error,
              customClass: { confirmButton: 'btn btn-primary' }
            });
          }
        }
      });
    });

    // Single Reassign Logic
    $('.datatables-users').on('click', '.reassign-agent', function() {
      const clientId = this.getAttribute('data-id');
      const currentAgentId = this.getAttribute('data-current-agent-id');
      
      // Clear previous selection
      $('.dt-checkboxes').prop('checked', false);
      
      // Set the specific client ID (we'll use this for bulk logic but with one ID)
      // Or we can modify the modal to handle single vs bulk
      // For simplicity, we'll just check the checkbox for this row and trigger bulk logic
      $(this).closest('tr').find('.dt-checkboxes').prop('checked', true);
      
      const checkedCount = 1;
      assignCountLabel.text(checkedCount);
      
      if (currentAgentId) {
        $('#agentSelect').val(currentAgentId).trigger('change');
      } else {
        $('#agentSelect').val('').trigger('change');
      }
      
      assignAgentModal.show();
    });

    // Apply Loan Modal Logic (for Agents)
    $('.datatables-users').on('click', '.apply-loan-modal', function() {
      const clientId = $(this).data('id');
      const modalEl = document.getElementById('modalApplyLoanGeneric');
      if (!modalEl) return;

      if (modalEl.classList.contains('show')) {
        return;
      }

      const applyModal = bootstrap.Modal.getOrCreateInstance(modalEl);

      $(modalEl).off('shown.bs.modal.applyLoanFromList').one('shown.bs.modal.applyLoanFromList', function () {
        const select = $('#apply_client_id');
        if (select.length) {
          select.val(clientId).trigger('change');
        }
      });

      applyModal.show();
    });

    // Initialize Select2 in modal
    $('#agentSelect').select2({
      dropdownParent: $('#assignAgentModal')
    });

    // Delete Record
    let currentDeleteId = null;

    document.addEventListener('click', function (e) {
      if (e.target.closest('.delete-record')) {
        const deleteBtn = e.target.closest('.delete-record');
        currentDeleteId = deleteBtn.dataset.id;
        const dtrModal = document.querySelector('.dtr-bs-modal.show');

        // hide responsive modal in small screen
        if (dtrModal) {
          const bsModal = bootstrap.Modal.getInstance(dtrModal);
          bsModal.hide();
        }

        // Show delete confirmation modal
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
      }
    });

    // Confirm Delete Button
    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
      // Close delete modal
      const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
      deleteModal.hide();

      if (currentDeleteId) {
        // delete the data
        fetch(`${baseUrl}client-list/${currentDeleteId}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
          }
        })
          .then(response => {
            if (response.ok) {
              dt_user.draw();

              // Show success modal
              setTimeout(() => {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
              }, 300);
            } else {
              throw new Error('Delete failed');
            }
          })
          .catch(error => {
            console.error('Delete error:', error);
            alert('Failed to delete client. Please try again.');
          });
      }
    });

    // edit record
    document.addEventListener('click', function (e) {
      if (e.target.closest('.edit-record')) {
        const editBtn = e.target.closest('.edit-record');
        const user_id = editBtn.dataset.id;
        const dtrModal = document.querySelector('.dtr-bs-modal.show');

        // hide responsive modal in small screen
        if (dtrModal) {
          const bsModal = bootstrap.Modal.getInstance(dtrModal);
          bsModal.hide();
        }

        // changing the title of offcanvas
        document.getElementById('offcanvasAddUserLabel').innerHTML = 'Edit User';

        // get data
        fetch(`${baseUrl}client-management/${user_id}/edit`)
          .then(response => response.json())
          .then(data => {
            document.getElementById('user_id').value = data.id;
            document.getElementById('add-user-fullname').value = data.name;
            document.getElementById('add-user-email').value = data.email;
          });
      }
    });

    // changing the title
    const addNewBtn = document.querySelector('.add-new');
    if (addNewBtn) {
      addNewBtn.addEventListener('click', function () {
        document.getElementById('user_id').value = ''; //resetting input field
        document.getElementById('offcanvasAddUserLabel').innerHTML = 'Add New Client';
      });
    }

    // Filter form control to default size
    setTimeout(() => {
      const elementsToModify = [
        { selector: '.dt-buttons .btn', classToRemove: 'btn-secondary' },
        { selector: '.dt-length .form-select', classToAdd: 'ms-0' },
        { selector: '.dt-length', classToAdd: 'mb-md-5 mb-0' },
        {
          selector: '.dt-layout-end',
          classToRemove: 'justify-content-between',
          classToAdd: 'd-flex gap-md-4 justify-content-md-between justify-content-center gap-md-2 flex-wrap mt-0'
        },
        { selector: '.dt-layout-start', classToAdd: 'mt-md-0 mt-5' },
        {
          selector: '.dt-layout-start .dt-buttons',
          classToAdd: 'd-md-flex d-block gap-4 justify-content-center'
        },
        {
          selector: '.dt-layout-end .dt-buttons',
          classToAdd: 'd-md-flex d-block gap-4 mb-md-0 mb-5 justify-content-center'
        },
        { selector: '.dt-layout-table', classToRemove: 'row mt-2' },
        { selector: '.dt-layout-full', classToRemove: 'col-md col-12' },
        { selector: '.dt-layout-full .table', classToAdd: 'table-responsive' }
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

    // Toggle Status Logic
    document.addEventListener('click', function (e) {
      if (e.target.closest('.toggle-status')) {
        const btn = e.target.closest('.toggle-status');
        const id = btn.dataset.id;
        const originalHtml = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

        fetch(`${baseUrl}client-management/${id}/toggle-status`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json'
          }
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              dt_user.draw(false);
              if (typeof Swal !== 'undefined') {
                Swal.fire({
                  icon: 'success',
                  title: 'Updated!',
                  text: data.message,
                  customClass: {
                    confirmButton: 'btn btn-success'
                  }
                });
              }
            } else {
              if (typeof Swal !== 'undefined') {
                Swal.fire({
                  icon: 'error',
                  title: 'Error!',
                  text: data.message || 'Failed to update status',
                  customClass: {
                    confirmButton: 'btn btn-primary'
                  }
                });
              }
            }
          })
          .catch(error => {
            console.error('Status toggle error:', error);
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An unexpected error occurred while updating status.',
                customClass: {
                  confirmButton: 'btn btn-primary'
                }
              });
            }
          })
          .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
          });
      }
    });
  }

  // --- New Client Modal Logic ---
  const modalAddNewClient = document.getElementById('modalAddNewClient');
  const formAddNewClient = document.getElementById('formAddNewClient');

  if (modalAddNewClient && formAddNewClient) {
    // 1. Employment Type Toggling
    const employmentRadios = formAddNewClient.querySelectorAll('input[name="employment_type"]');
    const salariedSection = document.getElementById('salariedSection');
    const businessSection = document.getElementById('businessSection');
    const payslipUpload = document.getElementById('payslipUpload');
    const businessDocUpload = document.getElementById('businessDocUpload');

    const toggleEmploymentFields = (type) => {
      if (type === 'salaried') {
        salariedSection.style.display = 'flex';
        businessSection.style.display = 'none';
        payslipUpload.style.display = 'block';
        businessDocUpload.style.display = 'none';
        // Reset validation for business fields if switching
        fv.resetField('business_name', true);
        fv.resetField('monthly_income', true);
      } else {
        salariedSection.style.display = 'none';
        businessSection.style.display = 'flex';
        payslipUpload.style.display = 'none';
        businessDocUpload.style.display = 'block';
        // Reset validation for salaried fields if switching
        fv.resetField('company_name', true);
        fv.resetField('monthly_salary', true);
      }
    };

    employmentRadios.forEach(radio => {
      radio.addEventListener('change', (e) => toggleEmploymentFields(e.target.value));
    });

    // Tab Fields Mapping (Synchronized with controller + modal tab ids)
    const tabFields = {
      '#tab-personal': ['name', 'email', 'phone', 'alternate_phone', 'date_of_birth', 'gender', 'marital_status', 'address', 'city', 'state', 'pincode'],
      '#tab-kyc': ['aadhar_number', 'pan_number', 'account_holder', 'account_number', 'ifsc_code', 'bank_name', 'account_type'],
      '#tab-nominee': ['nominee1_name', 'nominee1_relationship', 'nominee1_mobile'],
      '#tab-employment': ['employment_type', 'company_name', 'monthly_salary', 'business_name', 'monthly_income'],
      '#tab-documents': ['selfie_photo', 'aadhar_photo', 'pan_photo', 'bank_statement', 'payslip', 'business_document', 'terms']
    };

    const tabLinks = {
      0: '[data-bs-target="#tab-personal"]',
      1: '[data-bs-target="#tab-kyc"]',
      2: '[data-bs-target="#tab-nominee"]',
      3: '[data-bs-target="#tab-employment"]'
    };

    // 2. Form Validation & Submission
    const getEmploymentType = () => {
      const selected = formAddNewClient.querySelector('input[name="employment_type"]:checked');
      return selected ? selected.value : 'salaried';
    };

    const fv = FormValidation.formValidation(formAddNewClient, {
      fields: {
        name: { 
          validators: { 
            notEmpty: { message: 'Full Name is required' },
            regexp: {
              regexp: /^[a-zA-Z0-9\s]+$/,
              message: 'Full Name must contain only alphanumeric characters and spaces'
            }
          } 
        },
        phone: { 
          validators: { 
            notEmpty: { message: 'Mobile Number is required' },
            regexp: {
              regexp: /^[0-9 ]+$/,
              message: 'Mobile Number can only contain digits'
            },
            callback: {
              message: 'Mobile Number must be exactly 10 digits',
              callback: function(input) {
                const clean = input.value.replace(/\s+/g, '');
                return clean.length === 10;
              }
            },
            remote: {
              url: baseUrl + 'client-management/check-duplicate',
              method: 'POST',
              data: function() {
                return {
                  field: 'phone',
                  value: formAddNewClient.querySelector('[name="phone"]').value,
                  _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                };
              }
            }
          } 
        },
        alternate_phone: {
          validators: {
            regexp: {
              regexp: /^[0-9 ]+$/,
              message: 'Phone Number can only contain digits'
            },
            callback: {
              message: 'Phone Number must be exactly 10 digits',
              callback: function(input) {
                if (input.value === '') return true;
                const clean = input.value.replace(/\s+/g, '');
                return clean.length === 10;
              }
            }
          }
        },
        email: { 
          validators: { 
            notEmpty: { message: 'Email is required' },
            emailAddress: { message: 'Valid email is required' },
            remote: {
              url: baseUrl + 'client-management/check-duplicate',
              method: 'POST',
              data: function() {
                return {
                  field: 'email',
                  value: formAddNewClient.querySelector('[name="email"]').value,
                  _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                };
              }
            }
          } 
        },
        address: { validators: { notEmpty: { message: 'Address is required' } } },
        pincode: {
          validators: {
            regexp: {
              regexp: /^[0-9]+$/,
              message: 'Pincode can only contain digits'
            },
            stringLength: {
              min: 6,
              max: 6,
              message: 'Pincode must be exactly 6 digits'
            }
          }
        },
        aadhar_number: { 
          validators: { 
            notEmpty: { message: 'Aadhar Number is required' },
            callback: {
              message: 'Aadhar Number must be exactly 12 digits',
              callback: function(input) {
                const clean = input.value.replace(/\s+/g, '');
                return /^[0-9]{12}$/.test(clean);
              }
            },
            remote: {
              url: baseUrl + 'client-management/check-duplicate',
              method: 'POST',
              data: function() {
                return {
                  field: 'aadhar_number',
                  value: formAddNewClient.querySelector('[name="aadhar_number"]').value,
                  _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                };
              }
            }
          } 
        },
        pan_number: { 
          validators: { 
            notEmpty: { message: 'PAN Number is required' },
            regexp: {
              regexp: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i,
              message: 'Invalid PAN Number format (e.g., ABCDE1234F)'
            },
            remote: {
              url: baseUrl + 'client-management/check-duplicate',
              method: 'POST',
              data: function() {
                return {
                  field: 'pan_number',
                  value: formAddNewClient.querySelector('[name="pan_number"]').value,
                  _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                };
              }
            }
          } 
        },
        account_holder: { validators: { notEmpty: { message: 'Account Holder Name is required' } } },
        account_number: { validators: { notEmpty: { message: 'Account Number is required' } } },
                ifsc_code: { 
          validators: { 
            notEmpty: { message: 'IFSC Code is required' },
            regexp: {
              regexp: /^[A-Z]{4}0[A-Z0-9]{6}$/i,
              message: 'Invalid IFSC Code format (e.g., SBIN0123456)'
            }
          } 
        },
        bank_name: { validators: { notEmpty: { message: 'Bank Name is required' } } },
        account_type: { validators: { notEmpty: { message: 'Account Type is required' } } },
        nominee1_name: { validators: { notEmpty: { message: 'Nominee Name is required' } } },
        nominee1_relationship: { validators: { notEmpty: { message: 'Relationship is required' } } },
        nominee1_mobile: { 
          validators: { 
            notEmpty: { message: 'Nominee Mobile is required' },
            regexp: {
              regexp: /^[0-9]+$/,
              message: 'Mobile Number can only contain digits'
            },
            callback: {
              message: 'Mobile Number must be exactly 10 digits',
              callback: function(input) {
                const clean = input.value.replace(/\s+/g, '');
                return clean.length === 10;
              }
            }
          } 
        },
        nominee2_mobile: {
          validators: {
            regexp: {
              regexp: /^[0-9]+$/,
              message: 'Mobile Number can only contain digits'
            },
            callback: {
              message: 'Mobile Number must be exactly 10 digits',
              callback: function(input) {
                if (input.value === '') return true;
                const clean = input.value.replace(/\s+/g, '');
                return clean.length === 10;
              }
            }
          }
        },
        company_name: {
          validators: {
            callback: {
              message: 'Company Name is required',
              callback: function (input) {
                if (getEmploymentType() !== 'salaried') return true;
                return String(input.value || '').trim() !== '';
              }
            }
          }
        },
        monthly_salary: {
          validators: {
            callback: {
              message: 'Monthly Salary is required',
              callback: function (input) {
                if (getEmploymentType() !== 'salaried') return true;
                return String(input.value || '').trim() !== '';
              }
            }
          }
        },
        business_name: {
          validators: {
            callback: {
              message: 'Business Name is required',
              callback: function (input) {
                if (getEmploymentType() !== 'business') return true;
                return String(input.value || '').trim() !== '';
              }
            }
          }
        },
        monthly_income: {
          validators: {
            callback: {
              message: 'Monthly Income is required',
              callback: function (input) {
                if (getEmploymentType() !== 'business') return true;
                return String(input.value || '').trim() !== '';
              }
            }
          }
        },
        selfie_photo: { validators: { notEmpty: { message: 'Selfie is required' } } },
        aadhar_photo: { validators: { notEmpty: { message: 'Aadhar photo is required' } } },
        pan_photo: { validators: { notEmpty: { message: 'PAN photo is required' } } },
        bank_statement: { validators: { notEmpty: { message: 'Bank statement is required' } } },
        payslip: {
          validators: {
            callback: {
              message: 'Payslip is required for salaried employment',
              callback: function (input) {
                if (getEmploymentType() !== 'salaried') return true;
                return input.element.files && input.element.files.length > 0;
              }
            }
          }
        },
        business_document: {
          validators: {
            callback: {
              message: 'Business document is required for business employment',
              callback: function (input) {
                if (getEmploymentType() !== 'business') return true;
                return input.element.files && input.element.files.length > 0;
              }
            }
          }
        },
        terms: { validators: { notEmpty: { message: 'Please accept terms' } } }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: '.form-control-validation'
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    });

    // 3. Tab Switching — ZERO dependency on Bootstrap Tab JS
    // Directly manipulate classes to switch tabs. This is bulletproof.
    function switchToTab(targetSelector) {
      // targetSelector is like "#tab-kyc"
      const targetPane = modalAddNewClient.querySelector(targetSelector);
      if (!targetPane) return;

      // Deactivate ALL tab panes
      modalAddNewClient.querySelectorAll('.tab-pane').forEach(p => {
        p.classList.remove('show', 'active');
      });
      // Activate target pane
      targetPane.classList.add('show', 'active');

      // Deactivate ALL nav-links
      modalAddNewClient.querySelectorAll('.nav-link').forEach(l => {
        l.classList.remove('active');
        l.setAttribute('aria-selected', 'false');
      });
      // Activate the matching nav-link
      const targetLink = modalAddNewClient.querySelector(`.nav-link[data-bs-target="${targetSelector}"]`);
      if (targetLink) {
        targetLink.classList.add('active');
        targetLink.setAttribute('aria-selected', 'true');
      }

      // Scroll modal body to top
      const modalBody = modalAddNewClient.querySelector('.modal-body');
      if (modalBody) modalBody.scrollTop = 0;
    }

    function isFieldFilled(fieldName) {
      const field = formAddNewClient.querySelector(`[name="${fieldName}"]`);
      if (!field) return true;

      if (field.type === 'checkbox') {
        return field.checked;
      }

      if (field.type === 'file') {
        return field.files && field.files.length > 0;
      }

      if (field.type === 'radio') {
        return !!formAddNewClient.querySelector(`[name="${fieldName}"]:checked`);
      }

      return String(field.value || '').trim() !== '';
    }

    // "Next Step" button — validate current tab, show message if empty, move if filled
    modalAddNewClient.addEventListener('click', function(e) {
      const nextBtn = e.target.closest('.btn-next');
      if (nextBtn) {
        const currentPane = nextBtn.closest('.tab-pane');
        const currentTabId = currentPane ? `#${currentPane.id}` : null;
        const fieldsForTab = tabFields[currentTabId] || [];

        // Safe employment type check
        const empRadio = formAddNewClient.querySelector('input[name="employment_type"]:checked');
        const empType = empRadio ? empRadio.value : 'salaried';
        const activeFields = fieldsForTab.filter(f => {
          if (empType === 'salaried') return !['business_name', 'monthly_income', 'business_document'].includes(f);
          if (empType === 'business') return !['company_name', 'monthly_salary', 'payslip'].includes(f);
          return true;
        });

        // Check required fields and trigger validator messages inline
        let allFilled = true;
        activeFields.forEach(fieldName => {
          if (!isFieldFilled(fieldName)) {
            allFilled = false;
          }
        });

        if (!allFilled) {
          Swal.fire({
            title: 'Incomplete Details',
            text: 'Please fill all required fields before proceeding to the next step.',
            icon: 'warning',
            customClass: { confirmButton: 'btn btn-warning' }
          });
          return;
        }

        // Trigger validation for active fields (handles async remote checks)
        const promises = activeFields.map(fieldName => {
          if (fv.getFields()[fieldName]) {
            return fv.validateField(fieldName);
          }
          return Promise.resolve('Valid');
        });

        Promise.all(promises).then(results => {
          const isValid = results.every(result => result === 'Valid');
          if (isValid) {
            const nextTarget = nextBtn.getAttribute('data-next');
            if (nextTarget) switchToTab(nextTarget);
          } else {
            Swal.fire({
              title: 'Validation Error',
              text: 'Please fix the errors (including duplicates) before proceeding.',
              icon: 'error',
              customClass: { confirmButton: 'btn btn-primary' }
            });
          }
        });
        return;
      }

      // "Previous" button — no validation, just go back
      const prevBtn = e.target.closest('.btn-prev');
      if (prevBtn) {
        const prevTarget = prevBtn.getAttribute('data-prev');
        if (prevTarget) switchToTab(prevTarget);
      }
    });

    // Disable direct tab header clicks (as requested — remove the click function on Personal, KYC, etc.)
    modalAddNewClient.querySelectorAll('.nav-link').forEach(tab => {
      tab.style.pointerEvents = 'none';
      tab.style.cursor = 'default';
    });

    // AJAX Submission handler
    fv.on('core.form.valid', function () {
      const submitBtn = formAddNewClient.querySelector('.btn-submit');
      if (submitBtn.disabled) return;

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

      const formData = new FormData(formAddNewClient);

      fetch(`${baseUrl}client-management/store`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
      })
      .then(response => response.json())
      .then(json => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="icon-base ri ri-save-line me-2"></i> Complete Registration';

        if (json.success) {
          bootstrap.Modal.getInstance(modalAddNewClient).hide();
          formAddNewClient.reset();
          fv.resetForm(true);
          $('.datatables-users').DataTable().ajax.reload(null, false);
          Swal.fire({
            icon: 'success',
            title: 'Registration Successful!',
            text: 'Client account created and moved to KYC verification status.',
            customClass: { confirmButton: 'btn btn-success' }
          });
        } else {
          // Flatten validation errors if they exist
          let errorMsg = json.message || 'Validation failed on the server.';
          if (json.errors) {
            errorMsg = Object.values(json.errors).flat().join('<br>');
          }

          Swal.fire({
            title: 'Registration Failed',
            html: `<div class="text-start">${errorMsg}</div>`,
            icon: 'error',
            customClass: { confirmButton: 'btn btn-primary' }
          });
        }
      })
      .catch(err => {
        console.error('Submission error:', err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="icon-base ri ri-save-line me-2"></i> Complete Registration';
        Swal.fire({
          title: 'Connection Error',
          text: 'Unable to communicate with the server. Please check your connection.',
          icon: 'error',
          customClass: { confirmButton: 'btn btn-primary' }
        });
      });
    });

    // Error handler for invalid form
    fv.on('core.form.invalid', function() {
      Swal.fire({
        title: 'Missing Details',
        text: 'Please ensure all required documents and details are provided before submitting.',
        icon: 'warning',
        customClass: { confirmButton: 'btn btn-warning' }
      });
    });

    // Reset form when modal closed
    modalAddNewClient.addEventListener('hidden.bs.modal', function () {
      formAddNewClient.reset();
      fv.resetForm(true);
      // Reset to first tab using our custom function
      switchToTab('#tab-personal');
    });
  }

  // Phone mask initialization
  const phoneMaskList = document.querySelectorAll('.phone-mask');
  if (phoneMaskList) {
    phoneMaskList.forEach(function (phoneMask) {
      phoneMask.addEventListener('input', event => {
        const cleanValue = event.target.value.replace(/\D/g, '');
        // Simple 10 digit mask
        let formatted = cleanValue.substring(0, 10);
        if (formatted.length > 6) {
          formatted = `${formatted.substring(0, 3)} ${formatted.substring(3, 6)} ${formatted.substring(6)}`;
        } else if (formatted.length > 3) {
          formatted = `${formatted.substring(0, 3)} ${formatted.substring(3)}`;
        }
        phoneMask.value = formatted;
      });
    });
  }

  // Pincode mask initialization
  const pinMaskList = document.querySelectorAll('.pin-mask');
  if (pinMaskList) {
    pinMaskList.forEach(function (pinMask) {
      pinMask.addEventListener('input', event => {
        const cleanValue = event.target.value.replace(/\D/g, '');
        pinMask.value = cleanValue.substring(0, 6);
      });
    });
  }
});

