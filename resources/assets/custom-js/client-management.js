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
          d.account_type = $('#FilterAccountType').val();
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
        { data: 'customer_id' },
        { data: 'name' },
        { data: 'email' },
        { data: 'mobile' },
        { data: 'zone' },
        { data: 'loans_count' },
        { data: 'chits_count' },
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
          searchable: true,
          orderable: true,
          targets: 2, // Customer ID
          render: function (data, type, full) {
            const id = data || full.customer_id || ('#' + (full.fake_id || ''));
            return `<span class="fw-semibold text-heading">${id}</span>`;
          }
        },
        {
          // User full name
          targets: 3,
          render: function (data, type, full, meta) {
            const { name, id } = full;
            const avatarUrl = full.profile_image_url || '';
            const safeName = $('<div>').text(name || '').html();
            const safeUrl = $('<div>').text(avatarUrl).html();
            const avatarHtml = avatarUrl
              ? `<a href="javascript:void(0)" class="avatar avatar-sm me-2 flex-shrink-0 view-client-selfie"
                     data-selfie-url="${safeUrl}" data-client-name="${safeName}" title="View profile photo">
                   <img src="${safeUrl}" alt="${safeName}" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;"
                        onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'U')}&size=64&background=696cff&color=fff';">
                 </a>`
              : `<span class="avatar avatar-sm me-2 flex-shrink-0" title="No profile photo">
                   <span class="avatar-initial rounded-circle bg-label-primary">${(name || 'U').substring(0, 2).toUpperCase()}</span>
                 </span>`;
            return `<div class="d-flex align-items-center">
              ${avatarHtml}
              <a href="${userViewBase}${id}" class="text-heading"><span class="fw-medium">${safeName}</span></a>
            </div>`;
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
          // Loans Count
          targets: 7,
          className: 'text-center',
          render: function (data, type, full) {
            const loansCount = full['loans_count'] || 0;
            const emiCount = full['emi_count'] || 0;
            const openCount = full['open_loan_count'] || 0;
            const canManagePenalty = window.userRole === 'Admin' || window.userRole === 'Staff';
            let breakdown = '';
            if (loansCount > 0) {
              const parts = [];
              if (emiCount > 0) parts.push(`EMI: ${emiCount}`);
              if (openCount > 0) parts.push(`Open: ${openCount}`);
              if (parts.length > 0) breakdown = ` (${parts.join(', ')})`;
            }
            if (loansCount >= 1 && canManagePenalty) {
              return `<button type="button" class="btn btn-sm rounded-pill btn-label-primary fw-medium open-client-penalty"
                        data-client-id="${full['id']}" data-client-name="${$('<div>').text(full['name'] || '').html()}"
                        data-penalty-type="loan" title="Set loan penalty${breakdown}">
                        <i class="ri-hand-coin-line me-1_5"></i>${loansCount}
                      </button>`;
            }
            return `<span class="badge rounded-pill bg-label-primary fw-medium" title="${breakdown ? breakdown.trim() : ''}"><i class="ri-hand-coin-line me-1_5"></i>${loansCount}</span>`;
          }
        },
        {
          // Chits Count
          targets: 8,
          className: 'text-center',
          render: function (data, type, full) {
            const chitsCount = full['chits_count'] || 0;
            const canManagePenalty = window.userRole === 'Admin' || window.userRole === 'Staff';
            if (chitsCount >= 1 && canManagePenalty) {
              return `<button type="button" class="btn btn-sm rounded-pill btn-label-success fw-medium open-client-penalty"
                        data-client-id="${full['id']}" data-client-name="${$('<div>').text(full['name'] || '').html()}"
                        data-penalty-type="chit" title="Set chit penalty">
                        <i class="ri-group-2-line me-1_5"></i>${chitsCount}
                      </button>`;
            }
            return `<span class="badge rounded-pill bg-label-success fw-medium"><i class="ri-group-2-line me-1_5"></i>${chitsCount}</span>`;
          }
        },
        {
          // Assigned Agent
          targets: 9,
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
          targets: 10,
          render: function (data, type, full) {
            const addedByName = full['added_by_name'] || 'Admin';
            return `<span class="badge bg-label-info">${addedByName}</span>`;
          }
        },
        {
          // Status
          targets: 11,
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
            
            // Apply Options (Loan, Chit, FD) (only if active/verified)
            if (full['status'] === 'active' || full['status'] === 'verified') {
              const rawId = full['fake_id'] || full['id'];
              const clientNameSafe = $('<div>').text(full['name'] || '').html();
              actions += `
                <div class="d-inline-block">
                  <button type="button" class="btn btn-icon btn-text-secondary btn-sm rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="Apply Application (Loan, Chit, FD)">
                    <i class="icon-base ri ri-hand-coin-line icon-22px text-primary"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><h6 class="dropdown-header text-uppercase small text-muted py-1">Apply Service</h6></li>
                    <li>
                      <a class="dropdown-item apply-loan-modal d-flex align-items-center py-2" href="javascript:void(0);" data-id="${full['id']}" data-raw-id="${rawId}" data-client-name="${clientNameSafe}">
                        <i class="ri-bank-line me-2 text-primary fs-5"></i>
                        <div class="d-flex flex-column">
                          <span class="fw-semibold">Apply Loan</span>
                          <small class="text-muted" style="font-size: 0.75rem;">Standard EMI / Open Loan</small>
                        </div>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item apply-chit-modal d-flex align-items-center py-2" href="javascript:void(0);" data-id="${full['id']}" data-raw-id="${rawId}" data-client-name="${clientNameSafe}">
                        <i class="ri-group-line me-2 text-info fs-5"></i>
                        <div class="d-flex flex-column">
                          <span class="fw-semibold">Apply Chit</span>
                          <small class="text-muted" style="font-size: 0.75rem;">Chit Fund Scheme</small>
                        </div>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item apply-fd-modal d-flex align-items-center py-2" href="javascript:void(0);" data-id="${full['id']}" data-raw-id="${rawId}" data-client-name="${clientNameSafe}">
                        <i class="ri-safe-2-line me-2 text-warning fs-5"></i>
                        <div class="d-flex flex-column">
                          <span class="fw-semibold">Apply Fixed Deposit</span>
                          <small class="text-muted" style="font-size: 0.75rem;">FD Investment</small>
                        </div>
                      </a>
                    </li>
                  </ul>
                </div>`;
            }

            // Only show Toggle Status and Delete for Admin and Staff
            if (window.userRole === 'Admin' || window.userRole === 'Staff') {
              actions += `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill toggle-status" data-id="${full['id']}" title="Toggle Status"><i class="icon-base ri ri-refresh-line icon-22px"></i></button>`;
              actions += `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill delete-record" data-id="${full['id']}"><i class="icon-base ri ri-delete-bin-7-line icon-22px"></i></button>`;
            }
            
            actions += `<a href="${baseUrl}clients/view/ledger/${full['id']}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Ledger"><i class="icon-base ri ri-wallet-3-line icon-22px"></i></a>`;
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
                        columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
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
                        columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
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
                        columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
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
                        columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
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
                          tableContent.table.widths = ['8%', '18%', '16%', '12%', '10%', '8%', '8%', '10%', '10%', '10%'];
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
                        columns: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
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

    // Filter change and reset events
    $('#FilterLocation, #FilterStatus, #FilterAccountType').on('change', function () {
      dt_user.draw();
    });

    $('#btnResetClientFilters').on('click', function () {
      $('#FilterLocation').val('');
      $('#FilterStatus').val('');
      $('#FilterAccountType').val('');
      dt_user.draw();
    });

    // Bulk Assignment Logic for Clients
    const selectAllClients = $('#selectAllClients');
    const btnBulkAssignClients = $('#btnBulkAssignClients');
    const btnBulkAssignZone = $('#btnBulkAssignZone');
    const btnBulkDeleteClients = $('#btnBulkDeleteClients');
    const assignAgentModal = new bootstrap.Modal(document.getElementById('assignAgentModal'));
    const assignZoneModal = new bootstrap.Modal(document.getElementById('assignZoneModal'));
    const assignCountLabel = $('#assignCount');
    const assignZoneCountLabel = $('#assignZoneCount');
    const assignAgentForm = $('#assignAgentForm');
    const assignZoneForm = $('#assignZoneForm');
    const btnConfirmAssign = $('#btnConfirmAssign');
    const btnConfirmAssignZone = $('#btnConfirmAssignZone');

    function updateBulkActions() {
      const checkedCount = $('.dt-checkboxes:checked').length;
      if (checkedCount > 0) {
        btnBulkAssignClients.removeClass('d-none');
        btnBulkAssignZone.removeClass('d-none');
        btnBulkDeleteClients.removeClass('d-none');
      } else {
        btnBulkAssignClients.addClass('d-none');
        btnBulkAssignZone.addClass('d-none');
        btnBulkDeleteClients.addClass('d-none');
        selectAllClients.prop('checked', false);
      }
    }

    dt_user.on('draw', function () {
      selectAllClients.prop('checked', false);
      updateBulkActions();
    });

    // Open selfie / profile photo in popup modal
    $('.datatables-users').on('click', '.view-client-selfie', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const url = $(this).data('selfie-url');
      const clientName = $(this).data('client-name') || 'Profile Photo';
      if (!url) return;

      $('#clientSelfieModalTitle').text(clientName);
      $('#clientSelfieModalImg').attr('src', url).attr('alt', clientName + ' selfie');

      const modalEl = document.getElementById('clientSelfieModal');
      if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    });

    selectAllClients.on('change', function() {
      $('.dt-checkboxes').prop('checked', $(this).is(':checked'));
      updateBulkActions();
    });

    $('.datatables-users').on('change', '.dt-checkboxes', function() {
      updateBulkActions();
    });

    btnBulkDeleteClients.on('click', function() {
      const selectedIds = [];
      $('.dt-checkboxes:checked').each(function() {
        selectedIds.push($(this).val());
      });

      if (selectedIds.length === 0) return;

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Move to Recycle Bin?',
          text: `${selectedIds.length} selected client(s) and their loan, chit and deposit accounts will be moved to the recycle bin. You can restore them later.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, move them!',
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
                  const hasBlocked = Array.isArray(response.blocked_names) && response.blocked_names.length > 0;
                  Swal.fire({
                    icon: hasBlocked ? 'warning' : 'success',
                    title: hasBlocked ? 'Partially Completed' : 'Deleted!',
                    text: response.message,
                    customClass: { confirmButton: 'btn btn-success' },
                    buttonsStyling: false
                  });
                  selectAllClients.prop('checked', false);
                  dt_user.draw(false);
                  updateBulkActions();
                } else {
                  Swal.fire({
                    icon: response.blocked ? 'warning' : 'error',
                    title: response.blocked ? 'Cannot Delete Client(s)' : 'Error!',
                    text: response.message,
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                  });
                }
              },
              error: function(xhr) {
                const payload = xhr.responseJSON || {};
                const error = payload.message || 'An error occurred while deleting clients.';
                Swal.fire({
                  icon: payload.blocked ? 'warning' : 'error',
                  title: payload.blocked ? 'Cannot Delete Client(s)' : 'Error!',
                  text: error,
                  customClass: { confirmButton: 'btn btn-primary' },
                  buttonsStyling: false
                });
              }
            });
          }
        });
      } else {
        if (confirm(`Are you sure you want to delete these ${selectedIds.length} selected client(s)?`)) {
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
              const payload = xhr.responseJSON || {};
              alert(payload.message || 'Error deleting clients.');
            }
          });
        }
      }
    });

    btnBulkAssignClients.on('click', function() {
      const checkedCount = $('.dt-checkboxes:checked').length;
      assignCountLabel.text(checkedCount);
      assignAgentModal.show();
    });

    btnBulkAssignZone.on('click', function() {
      const checkedCount = $('.dt-checkboxes:checked').length;
      assignZoneCountLabel.text(checkedCount);
      assignZoneModal.show();
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

    // Handle Zone Assignment Submission
    assignZoneForm.on('submit', function(e) {
      e.preventDefault();
      
      const selectedIds = [];
      $('.dt-checkboxes:checked').each(function() {
        selectedIds.push($(this).val());
      });

      if (selectedIds.length === 0) return;

      const formData = {
        client_ids: selectedIds,
        location_id: $('#zoneSelect').val(),
        _token: $('input[name="_token"]').val()
      };

      // Show loading
      btnConfirmAssignZone.prop('disabled', true);
      btnConfirmAssignZone.find('.spinner-border').removeClass('d-none');

      $.ajax({
        url: baseUrl + 'client-management/bulk-assign-zone',
        type: 'POST',
        data: formData,
        success: function(response) {
          btnConfirmAssignZone.prop('disabled', false);
          btnConfirmAssignZone.find('.spinner-border').addClass('d-none');
          
          if (response.success) {
            assignZoneModal.hide();
            
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
          btnConfirmAssignZone.prop('disabled', false);
          btnConfirmAssignZone.find('.spinner-border').addClass('d-none');
          const error = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred while assigning zone.';
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

    // ---------------------------------------------------------
    // Quick Apply Actions (Loan, Chit, FD) from Client Row
    // ---------------------------------------------------------

    // 1. Apply Loan
    $('.datatables-users').on('click', '.apply-loan-modal', function() {
      const clientId = $(this).data('id');
      const rawId = $(this).data('raw-id') || clientId;
      const clientName = $(this).data('client-name') || '';
      const modalEl = document.getElementById('modalApplyLoanGeneric');
      if (!modalEl) return;

      if (modalEl.classList.contains('show')) {
        return;
      }

      const applyModal = bootstrap.Modal.getOrCreateInstance(modalEl);

      $(modalEl).off('shown.bs.modal.applyLoanFromList').one('shown.bs.modal.applyLoanFromList', function () {
        const select = $('#apply_client_id');
        if (select.length) {
          let opt = select.find(`option[value="${rawId}"], option[value="${clientId}"]`);
          if (!opt.length && clientName) {
            const newOption = new Option(clientName, rawId, true, true);
            select.append(newOption).trigger('change');
          } else {
            const val = opt.length ? opt.val() : String(rawId);
            select.val(val).trigger('change');
          }
        }
      });

      applyModal.show();
    });

    // 2. Apply Chit
    $('.datatables-users').on('click', '.apply-chit-modal', function() {
      const clientId = $(this).data('id');
      const rawId = $(this).data('raw-id') || clientId;
      const clientName = $(this).data('client-name') || '';
      const modalEl = document.getElementById('modalApplyChit');
      if (!modalEl) return;

      if (modalEl.classList.contains('show')) {
        return;
      }

      const applyModal = bootstrap.Modal.getOrCreateInstance(modalEl);

      $(modalEl).off('shown.bs.modal.applyChitFromList').one('shown.bs.modal.applyChitFromList', function () {
        const select = $('#chit_client_id');
        if (select.length) {
          let opt = select.find(`option[value="${rawId}"], option[value="${clientId}"]`);
          if (!opt.length && clientName) {
            const newOption = new Option(clientName, rawId, true, true);
            select.append(newOption).trigger('change');
          } else {
            const val = opt.length ? opt.val() : String(rawId);
            select.val(val).trigger('change');
          }
        }
      });

      applyModal.show();
    });

    // 3. Apply Fixed Deposit (FD)
    $('.datatables-users').on('click', '.apply-fd-modal', function() {
      const clientId = $(this).data('id');
      const rawId = $(this).data('raw-id') || clientId;
      const clientName = $(this).data('client-name') || '';
      const modalEl = document.getElementById('modalApplyFdGeneric');
      if (!modalEl) return;

      if (modalEl.classList.contains('show')) {
        return;
      }

      const applyModal = bootstrap.Modal.getOrCreateInstance(modalEl);

      $(modalEl).off('shown.bs.modal.applyFdFromList').one('shown.bs.modal.applyFdFromList', function () {
        const select = $('#formApplyFdGeneric_client_id');
        if (select.length) {
          let opt = select.find(`option[value="${rawId}"], option[value="${clientId}"]`);
          if (!opt.length && clientName) {
            const newOption = new Option(clientName, rawId, true, true);
            select.append(newOption).trigger('change');
          } else {
            const val = opt.length ? opt.val() : String(rawId);
            select.val(val).trigger('change');
          }
        }
      });

      applyModal.show();
    });

    // Initialize Select2 in modal
    $('#agentSelect').select2({
      dropdownParent: $('#assignAgentModal')
    });
    $('#zoneSelect').select2({
      dropdownParent: $('#assignZoneModal')
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
        performDelete(currentDeleteId);
      }
    });

    function performDelete(id) {
      const url = `${baseUrl}client-list/${id}`;
      fetch(url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(async response => {
          const data = await response.json().catch(() => ({}));

          if (response.ok && data.success !== false) {
            dt_user.draw();

            setTimeout(() => {
              const successModal = new bootstrap.Modal(document.getElementById('successModal'));
              successModal.show();
            }, 300);
            return;
          }

          const errMsg = data.message || 'Failed to delete client. Please try again.';
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: data.blocked ? 'warning' : 'error',
              title: data.blocked ? 'Cannot Delete Client' : 'Deletion Failed',
              text: errMsg,
              customClass: { confirmButton: 'btn btn-primary' },
              buttonsStyling: false
            });
          } else {
            alert(errMsg);
          }
        })
        .catch(error => {
          console.error('Delete error:', error);
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'error',
              title: 'Deletion Failed',
              text: error.message || 'Failed to delete client. Please try again.',
              customClass: { confirmButton: 'btn btn-primary' },
              buttonsStyling: false
            });
          } else {
            alert(error.message || 'Failed to delete client. Please try again.');
          }
        });
    }

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
      const sameOriginStore = `${window.location.pathname.replace(/\/+$/, '').replace(/\/client-management.*$/, '')}/client-management/store`.replace(/\/{2,}/g, '/');

      fetch(sameOriginStore, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'ngrok-skip-browser-warning': '1'
        },
        body: formData
      })
      .then(async response => {
        const raw = await response.text();
        let json = null;
        try {
          json = raw ? JSON.parse(raw) : {};
        } catch (e) {
          const status = response.status;
          let message = 'Unable to complete registration. Please try again.';
          if (status === 403) {
            message = 'The server blocked this registration (403). Try smaller photos and submit again.';
          } else if (status === 413) {
            message = 'Uploaded files are too large. Please use smaller photos and try again.';
          } else if (status === 419) {
            message = 'Your session expired. Please refresh the page and try again.';
          }
          throw new Error(message);
        }
        json._httpStatus = response.status;
        json._ok = response.ok;
        return json;
      })
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
          title: 'Registration Failed',
          text: err?.message || 'Unable to communicate with the server. Please check your connection.',
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

  // Bulk Import AJAX Submission
  const bulkImportForm = document.getElementById('bulkImportForm');
  const bulkImportModalEl = document.getElementById('bulkImportModal');
  if (bulkImportForm && bulkImportModalEl) {
    const bulkImportModal = bootstrap.Modal.getOrCreateInstance(bulkImportModalEl);
    const btnConfirmImport = document.getElementById('btnConfirmImport');
    const importErrorsContainer = document.getElementById('importErrorsContainer');
    const importErrorsList = document.getElementById('importErrorsList');

    bulkImportForm.addEventListener('submit', function (e) {
      e.preventDefault();

      // Clear previous errors
      importErrorsContainer.classList.add('d-none');
      importErrorsList.innerHTML = '';

      // Show spinner
      btnConfirmImport.disabled = true;
      btnConfirmImport.querySelector('.spinner-border').classList.remove('d-none');

      const formData = new FormData(bulkImportForm);

      fetch(`${baseUrl}client-management/bulk-import`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
      })
      .then(response => response.json())
      .then(json => {
        btnConfirmImport.disabled = false;
        btnConfirmImport.querySelector('.spinner-border').classList.add('d-none');

        if (json.success) {
          bulkImportModal.hide();
          bulkImportForm.reset();
          
          // Reload Datatable
          $('.datatables-users').DataTable().ajax.reload(null, false);

          Swal.fire({
            icon: 'success',
            title: 'Import Successful!',
            text: json.message,
            customClass: { confirmButton: 'btn btn-success' }
          });
        } else {
          // Display errors inside the modal
          importErrorsContainer.classList.remove('d-none');
          
          if (json.errors && Array.isArray(json.errors)) {
            json.errors.forEach(err => {
              const li = document.createElement('li');
              li.textContent = err;
              importErrorsList.appendChild(li);
            });
          } else {
            const li = document.createElement('li');
            li.textContent = json.message || 'An error occurred during import.';
            importErrorsList.appendChild(li);
          }

          Swal.fire({
            title: 'Import Failed',
            text: 'Some errors were found in the uploaded sheet. Please check the list below.',
            icon: 'error',
            customClass: { confirmButton: 'btn btn-primary' }
          });
        }
      })
      .catch(err => {
        console.error('Import error:', err);
        btnConfirmImport.disabled = false;
        btnConfirmImport.querySelector('.spinner-border').classList.add('d-none');

        Swal.fire({
          title: 'Connection Error',
          text: 'Unable to communicate with the server. Please check your connection.',
          icon: 'error',
          customClass: { confirmButton: 'btn btn-primary' }
        });
      });
    });

    // Clear form and errors when modal is closed
    bulkImportModalEl.addEventListener('hidden.bs.modal', function () {
      bulkImportForm.reset();
      importErrorsContainer.classList.add('d-none');
      importErrorsList.innerHTML = '';
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

  // ---- Per-client Loan / Chit Penalty popup ----
  const penaltyModalEl = document.getElementById('clientPenaltyModal');
  const penaltyModal = penaltyModalEl ? new bootstrap.Modal(penaltyModalEl) : null;
  const penaltyListEl = document.getElementById('clientPenaltyList');
  const penaltyLoadingEl = document.getElementById('clientPenaltyLoading');
  const penaltyEmptyEl = document.getElementById('clientPenaltyEmpty');
  const penaltyTitleEl = document.getElementById('clientPenaltyModalTitle');
  const penaltyClientNameEl = document.getElementById('clientPenaltyClientName');
  let currentPenaltyType = 'loan';

  function formatPenaltyMoney(amount) {
    const n = Number(amount) || 0;
    return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function setPenaltyModalState(state) {
    if (penaltyLoadingEl) penaltyLoadingEl.classList.toggle('d-none', state !== 'loading');
    if (penaltyEmptyEl) penaltyEmptyEl.classList.toggle('d-none', state !== 'empty');
    if (penaltyListEl) penaltyListEl.classList.toggle('d-none', state !== 'list');
  }

  function renderPenaltyRows(type, accounts) {
    if (!penaltyListEl) return;
    penaltyListEl.innerHTML = '';

    accounts.forEach(function (account) {
      const card = document.createElement('div');
      card.className = 'border rounded p-3';
      const amountLabel = type === 'loan' ? 'Loan amount' : 'Chit value';
      const amountValue = formatPenaltyMoney(account.amount);
      const extra =
        type === 'loan'
          ? `<span class="text-muted small">Outstanding: ${formatPenaltyMoney(account.outstanding)}</span>`
          : `<span class="text-muted small">Group: ${account.group_name || '—'}</span>`;
      const currentType = account.penalty_type === 'percentage' ? 'percentage' : 'fixed';
      const currentValue = account.penalty != null ? account.penalty : '';

      card.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
          <div>
            <h6 class="mb-1">${account.label}</h6>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <span class="badge bg-label-secondary text-capitalize">${account.status || '—'}</span>
              <span class="text-muted small">${amountLabel}: ${amountValue}</span>
              ${extra}
            </div>
          </div>
        </div>
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small mb-1">Penalty Type</label>
            <select class="form-select form-select-sm penalty-type-select">
              <option value="fixed" ${currentType === 'fixed' ? 'selected' : ''}>Fixed (₹)</option>
              <option value="percentage" ${currentType === 'percentage' ? 'selected' : ''}>Percentage (%)</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small mb-1">Penalty Value</label>
            <input type="number" min="0" step="0.01" class="form-control form-control-sm penalty-value-input"
                   value="${currentValue}" placeholder="0.00">
          </div>
          <div class="col-md-4">
            <button type="button" class="btn btn-primary btn-sm w-100 apply-account-penalty"
                    data-account-id="${account.id}">
              <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
              Apply
            </button>
          </div>
        </div>
      `;
      penaltyListEl.appendChild(card);
    });
  }

  function openClientPenaltyModal(clientId, clientName, type) {
    if (!penaltyModal) return;
    currentPenaltyType = type;
    if (penaltyTitleEl) {
      penaltyTitleEl.textContent = type === 'loan' ? 'Loan Penalty' : 'Chit Penalty';
    }
    if (penaltyClientNameEl) {
      penaltyClientNameEl.textContent = clientName || '';
    }
    setPenaltyModalState('loading');
    if (penaltyListEl) penaltyListEl.innerHTML = '';
    penaltyModal.show();

    fetch(`${baseUrl}client-management/${clientId}/penalty-accounts?type=${encodeURIComponent(type)}`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(res => res.json())
      .then(json => {
        if (!json.success) {
          setPenaltyModalState('empty');
          Swal.fire({
            icon: 'error',
            title: 'Unable to load',
            text: json.message || 'Failed to load accounts.',
            customClass: { confirmButton: 'btn btn-primary' }
          });
          return;
        }
        const accounts = Array.isArray(json.accounts) ? json.accounts : [];
        if (accounts.length === 0) {
          setPenaltyModalState('empty');
          return;
        }
        renderPenaltyRows(type, accounts);
        setPenaltyModalState('list');
      })
      .catch(err => {
        console.error(err);
        setPenaltyModalState('empty');
        Swal.fire({
          icon: 'error',
          title: 'Connection Error',
          text: 'Unable to load penalty accounts.',
          customClass: { confirmButton: 'btn btn-primary' }
        });
      });
  }

  document.addEventListener('click', function (e) {
    const openBtn = e.target.closest('.open-client-penalty');
    if (openBtn) {
      e.preventDefault();
      openClientPenaltyModal(
        openBtn.getAttribute('data-client-id'),
        openBtn.getAttribute('data-client-name'),
        openBtn.getAttribute('data-penalty-type') || 'loan'
      );
      return;
    }

    const applyBtn = e.target.closest('.apply-account-penalty');
    if (!applyBtn || !penaltyListEl || !penaltyListEl.contains(applyBtn)) return;

    e.preventDefault();
    const card = applyBtn.closest('.border');
    if (!card) return;

    const accountId = applyBtn.getAttribute('data-account-id');
    const typeSelect = card.querySelector('.penalty-type-select');
    const valueInput = card.querySelector('.penalty-value-input');
    const penaltyType = typeSelect ? typeSelect.value : 'fixed';
    const penaltyValue = valueInput ? parseFloat(valueInput.value) : NaN;

    if (!accountId || isNaN(penaltyValue) || penaltyValue < 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Invalid value',
        text: 'Enter a valid penalty value (0 or greater).',
        customClass: { confirmButton: 'btn btn-primary' }
      });
      return;
    }

    if (penaltyType === 'percentage' && penaltyValue > 100) {
      Swal.fire({
        icon: 'warning',
        title: 'Invalid percentage',
        text: 'Percentage penalty cannot exceed 100%.',
        customClass: { confirmButton: 'btn btn-primary' }
      });
      return;
    }

    const spinner = applyBtn.querySelector('.spinner-border');
    applyBtn.disabled = true;
    if (spinner) spinner.classList.remove('d-none');

    const url =
      currentPenaltyType === 'chit'
        ? `${baseUrl}client-management/penalty/apply-chit`
        : `${baseUrl}client-management/penalty/apply-loan`;

    const body =
      currentPenaltyType === 'chit'
        ? {
            group_member_id: accountId,
            penalty_type: penaltyType,
            penalty_value: penaltyValue
          }
        : {
            loan_account_id: accountId,
            penalty_type: penaltyType,
            penalty_value: penaltyValue
          };

    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(body)
    })
      .then(async res => {
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) {
          const msg =
            json.message ||
            (json.errors ? Object.values(json.errors).flat().join(' ') : null) ||
            'Failed to apply penalty.';
          throw new Error(msg);
        }
        return json;
      })
      .then(json => {
        Swal.fire({
          icon: 'success',
          title: 'Penalty Applied',
          text: json.message || 'Penalty saved successfully.',
          customClass: { confirmButton: 'btn btn-primary' }
        });
      })
      .catch(err => {
        Swal.fire({
          icon: 'error',
          title: 'Apply Failed',
          text: err.message || 'Unable to apply penalty.',
          customClass: { confirmButton: 'btn btn-primary' }
        });
      })
      .finally(() => {
        applyBtn.disabled = false;
        if (spinner) spinner.classList.add('d-none');
      });
  });
});

