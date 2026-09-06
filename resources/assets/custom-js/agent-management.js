/**
 * Page Agent Management
 */

'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function (e) {
  let borderColor, bodyBg, headingColor;

  borderColor = config.colors.borderColor;
  bodyBg = config.colors.bodyBg;
  headingColor = config.colors.headingColor;

  // Variable declaration for table
  const dt_agent_table = document.querySelector('.datatables-agents');
  let dt_agent; // Declare at higher scope

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Initialize Select2 specifically for Add Agent Modal
  $('#addAgentModal .select2').select2({
    dropdownParent: $('#addAgentModal')
  });

  $('.select2-modal').each(function () {
    const parent = $(this).closest('.modal');
    $(this).select2({
      dropdownParent: parent.length ? parent : $(document.body)
    });
  });
  
  // Agents datatable
  if (dt_agent_table) {
    dt_agent = new DataTable(dt_agent_table, {
      responsive: true,
      scrollX: true,
      autoWidth: false,
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'app/agents/data',
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
        { data: 'id' },
        { data: 'name' },
        { data: 'email' },
        { data: 'mobile' },
        { data: 'status' },
        { data: 'action' }
      ],
      columnDefs: [
        {
          searchable: false,
          orderable: false,
          width: '80px',
          className: 'text-start',
          responsivePriority: 1,
          targets: 0,
          render: function (data, type, full, meta) {
            return `<span>${meta.settings._iDisplayStart + meta.row + 1}</span>`;
          }
        },
        {
          // Agent full name
          targets: 1,
          responsivePriority: 4,
          width: '220px',
          render: function (data, type, full, meta) {
            const { name } = full;
            return `<span class="fw-medium">${name}</span>`;
          }
        },
        {
          // Agent email
          targets: 2,
          render: function (data, type, full, meta) {
            const email = full['email'];

            return '<span class="user-email">' + email + '</span>';
          }
        },
        {
          // Mobile number
          targets: 3,
          render: function (data, type, full) {
            const mobile = full['mobile'] || 'N/A';
            return `<span class="user-mobile">${mobile}</span>`;
          }
        },
        {
          // Status
          targets: 4,
          className: 'text-center',
          render: function (data, type, full, meta) {
            const status = (full['status'] || 'inactive').toLowerCase();
            // Map all status values to badge displays
            const statusMap = {
              active: { label: 'Active', color: 'success' },
              inactive: { label: 'Inactive', color: 'danger' },
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
            return (
              '<div class="d-flex align-items-center gap-1">' +
              `<a href="${baseUrl}app/agents/view/${full['id']}" class="btn btn-sm btn-icon btn-label-primary shadow-sm view-record" title="View"><i class="ri ri-eye-line"></i></a>` +
              `<button class="btn btn-sm btn-icon btn-label-danger shadow-sm delete-record" data-id="${full['id']}" title="Delete"><i class="ri ri-delete-bin-line"></i></button>` +
              '</div>'
            );
          }
        }
      ],
      order: [[0, 'desc']],
      layout: {
        topStart: null,
        topEnd: null,
        bottomStart: 'info',
        bottomEnd: 'paging'
      },
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-label-secondary dropdown-toggle',
          text: '<i class="icon-base ri ri-upload-2-line me-2 icon-sm"></i>Export',
          buttons: [
            {
              extend: 'print',
              title: 'Agents',
              text: '<i class="icon-base ri ri-printer-line me-2" ></i>Print',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                // prevent avatar to be print
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner == null) return '';
                    if (typeof inner !== 'string') inner = String(inner);
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
              title: 'Agents',
              text: '<i class="icon-base ri ri-file-text-line me-2" ></i>Csv',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner == null) return '';
                    if (typeof inner !== 'string') inner = String(inner);
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
              title: 'Agents',
              text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                format: {
                  body: function (inner, coldex, rowdex) {
                    if (inner == null) return '';
                    if (typeof inner !== 'string') inner = String(inner);
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
              title: 'Agents',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>Pdf',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                format: {
                  body: function (inner) {
                    if (inner == null) return '';
                    if (typeof inner !== 'string') inner = String(inner);
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
                doc.content[0].text = 'Agent Management | Loan App';
                doc.defaultStyle.fontSize = 10;
                doc.styles.tableHeader.fontSize = 10;
                doc.styles.tableHeader.alignment = 'left';
                doc.styles.tableHeader.fillColor = '#f5f5f5';
                doc.styles.tableHeader.color = '#333333';

                doc.content.splice(1, 0, {
                  text: 'Agents Information Report',
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
              title: 'Agents',
              text: '<i class="icon-base ri ri-file-copy-line me-2" ></i>Copy',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5],
                format: {
                  body: function (inner) {
                    if (inner == null) return '';
                    if (typeof inner !== 'string') inner = String(inner);
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
      ],
      displayLength: 20,
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
              return 'Details of ' + data['name'];
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
        // Remove btn-secondary from export buttons
        document.querySelectorAll('.dt-buttons .btn').forEach(btn => {
          btn.classList.remove('btn-secondary');
        });
      }
    });

    // Expose instance for any inline/UI integrations.
    window.dt_agent = dt_agent;

    // Staff-style header controls: search + page length.
    $('#agentSearch').on('keyup', function () {
      if (!dt_agent) return;
      dt_agent.search(this.value).draw();
    });

    $('#agentPerPage').on('change', function () {
      if (!dt_agent) return;
      dt_agent.page.len(parseInt(this.value, 10) || 10).draw();
    });

    // Delete Record
    $(document).on('click', '.delete-record', function () {
      const agentId = $(this).data('id');
      if ($(this).data('loading') === 1) return;
      $(this).data('loading', 1).prop('disabled', true);
      const dtrModal = $('.dtr-bs-modal.show');

      // hide responsive modal in small screen
      if (dtrModal.length) {
        const bsModal = bootstrap.Modal.getInstance(dtrModal[0]);
        if (bsModal) bsModal.hide();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '');
      }

      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this agent deletion!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        customClass: {
          confirmButton: 'btn btn-danger me-3 waves-effect waves-light',
          cancelButton: 'btn btn-outline-secondary waves-effect'
        },
        buttonsStyling: false,
        allowOutsideClick: false,
        showLoaderOnConfirm: true,
        preConfirm: () => {
          return fetch(`${baseUrl}app/agents/${agentId}`, {
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            }
          })
            .then(response => {
              if (!response.ok) {
                return response.json().then(data => {
                  throw new Error(data.message || 'Delete failed');
                });
              }
              return response.json();
            })
            .catch(error => {
              Swal.showValidationMessage(`Request failed: ${error}`);
            });
        }
      }).then(function (result) {
        if (result.isConfirmed && result.value) {
          if (dt_agent) dt_agent.draw();
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Agent has been deleted.',
            customClass: {
              confirmButton: 'btn btn-success waves-effect'
            }
          });
        }
        $(`.delete-record[data-id="${agentId}"]`).data('loading', 0).prop('disabled', false);
      });
    });

    // Filter form control to default size
    setTimeout(() => {
      const elementsToModify = [
        { selector: '.dt-buttons .btn', classToRemove: 'btn-secondary' },
        { selector: '.dt-length .form-select', classToAdd: 'ms-0' },
        { selector: '.dt-length', classToAdd: 'mb-md-2 mb-0' },
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
        { selector: '.dt-layout-full', classToRemove: 'col-md col-12' }
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

    // Initialize Collections table if in Agent Management dashboard
    const dt_agent_collections_table = document.querySelector('.datatables-agent-collections');
    if (dt_agent_collections_table) {
      new DataTable(dt_agent_collections_table, {
        processing: true,
        serverSide: true,
        ajax: {
          url: baseUrl + 'app/agents/agent-collections/list'
        },
        columns: [
          { data: 'id' },
          { data: 'agent_name' },
          { data: 'client_name' },
          { data: 'amount' },
          { data: 'payment_method' },
          { data: 'status' },
          { data: 'collected_at' }
        ],
        columnDefs: [
          {
            targets: 0,
            render: function (data, type, full, meta) {
              return `<span>${meta.row + meta.settings._iDisplayStart + 1}</span>`;
            }
          },
          {
            targets: 3,
            render: function (data, type, full) {
              return `<span class="fw-bold">₹${parseFloat(full.amount).toLocaleString('en-IN')}</span>`;
            }
          },
          {
            targets: 4,
            render: function (data, type, full) {
              const methods = { in_hand: 'primary', direct: 'success', payment_link: 'info' };
              return `<span class="badge bg-label-${methods[full.payment_method] || 'secondary'}">${full.payment_method.replace('_', ' ').toUpperCase()}</span>`;
            }
          },
          {
            targets: 5,
            render: function (data, type, full) {
              const statuses = { pending: 'warning', verified: 'success', rejected: 'danger', completed: 'success' };
              return `<span class="badge bg-label-${statuses[full.status] || 'secondary'}">${full.status.toUpperCase()}</span>`;
            }
          },
          {
            targets: '_all',
            className: 'text-nowrap'
          },
          {
            targets: [1, 2], // Agent and Client
            width: '200px'
          }
        ],
        order: [[6, 'desc']],
        displayLength: 20,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        scrollX: true,
        autoWidth: false
      });
    }
  }

  // Attendance logic moved to Blade for route/date synchronization

  // Alert notification function (same as loan-types)
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

  // Clear validation errors
  function clearValidationErrors() {
    document.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
  }

  // Handle modal show event
  const addAgentModal = document.getElementById('addAgentModal');
  if (addAgentModal) {
    addAgentModal.addEventListener('show.bs.modal', function () {
      // Reset form
      document.getElementById('agentForm').reset();
      $('#agentLocation').val('').trigger('change');
      clearValidationErrors();
    });
  }

  // Password toggle is handled globally in main.js via delegated events.

  // Numeric-only input for phone and pincode
  const phoneInput = document.getElementById('agentPhone');
  const pincodeInput = document.getElementById('agentPincode');

  if (phoneInput) {
    phoneInput.addEventListener('input', function (e) {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  }

  if (pincodeInput) {
    pincodeInput.addEventListener('input', function (e) {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  }

  // Handle form submission
  const agentForm = document.getElementById('agentForm');
  if (agentForm) {
    agentForm.addEventListener('submit', function (e) {
      e.preventDefault();

      // Clear previous validation errors
      clearValidationErrors();

      // Get form values
      const name = document.getElementById('agentName').value.trim();
      const email = document.getElementById('agentEmail').value.trim();
      const phone = document.getElementById('agentPhone').value.trim();
      const address = document.getElementById('agentAddress').value.trim();
      const city = document.getElementById('agentCity').value.trim();
      const state = document.getElementById('agentState').value.trim();
      const pincode = document.getElementById('agentPincode').value.trim();
      const location_id = $('#agentLocation').val();
      const password = document.getElementById('agentPassword').value;
      const confirmPassword = document.getElementById('agentConfirmPassword').value;

      // Client-side validation
      let hasError = false;

      // Validate name
      if (name === '') {
        document.getElementById('agentName').classList.add('is-invalid');
        document.getElementById('nameError').textContent = 'Name is required';
        hasError = true;
      }

      // Validate email
      if (email === '') {
        document.getElementById('agentEmail').classList.add('is-invalid');
        document.getElementById('emailError').textContent = 'Email is required';
        hasError = true;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('agentEmail').classList.add('is-invalid');
        document.getElementById('emailError').textContent = 'Please enter a valid email address';
        hasError = true;
      }

      // Validate phone
      if (phone === '') {
        document.getElementById('agentPhone').classList.add('is-invalid');
        document.getElementById('phoneError').textContent = 'Phone is required';
        hasError = true;
      } else if (!/^\d{10}$/.test(phone)) {
        document.getElementById('agentPhone').classList.add('is-invalid');
        document.getElementById('phoneError').textContent = 'Phone number must be exactly 10 digits';
        hasError = true;
      }

      // Validate address
      if (address === '') {
        document.getElementById('agentAddress').classList.add('is-invalid');
        document.getElementById('addressError').textContent = 'Address is required';
        hasError = true;
      }

      // Validate city
      if (city === '') {
        document.getElementById('agentCity').classList.add('is-invalid');
        document.getElementById('cityError').textContent = 'City is required';
        hasError = true;
      } else if (!/^[a-zA-Z\s.]+$/.test(city)) {
        document.getElementById('agentCity').classList.add('is-invalid');
        document.getElementById('cityError').textContent = 'City must contain only letters and spaces';
        hasError = true;
      }

      // Validate state
      if (state === '') {
        document.getElementById('agentState').classList.add('is-invalid');
        document.getElementById('stateError').textContent = 'State is required';
        hasError = true;
      } else if (!/^[a-zA-Z\s.]+$/.test(state)) {
        document.getElementById('agentState').classList.add('is-invalid');
        document.getElementById('stateError').textContent = 'State must contain only letters and spaces';
        hasError = true;
      }

      // Validate pincode
      if (pincode === '') {
        document.getElementById('agentPincode').classList.add('is-invalid');
        document.getElementById('pincodeError').textContent = 'Pincode is required';
        hasError = true;
      } else if (!/^\d{6}$/.test(pincode)) {
        document.getElementById('agentPincode').classList.add('is-invalid');
        document.getElementById('pincodeError').textContent = 'Pincode must be exactly 6 digits';
        hasError = true;
      }

      // Validate location
      if (!location_id) {
        document.getElementById('locationError').textContent = 'Location is required';
        $('#agentLocation').addClass('is-invalid');
        hasError = true;
      }

      // Validate password
      if (password === '') {
        document.getElementById('agentPassword').classList.add('is-invalid');
        document.getElementById('passwordError').textContent = 'Password is required';
        hasError = true;
      } else if (password.length < 8) {
        document.getElementById('agentPassword').classList.add('is-invalid');
        document.getElementById('passwordError').textContent = 'Password must be at least 8 characters';
        hasError = true;
      }

      // Validate confirm password
      if (confirmPassword === '') {
        document.getElementById('agentConfirmPassword').classList.add('is-invalid');
        document.getElementById('confirmPasswordError').textContent = 'Please confirm your password';
        hasError = true;
      } else if (password !== confirmPassword) {
        document.getElementById('agentConfirmPassword').classList.add('is-invalid');
        document.getElementById('confirmPasswordError').textContent = 'Passwords do not match';
        hasError = true;
      }

      if (hasError) {
        return false;
      }

      // Prepare form data
      const formData = {
        _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        name: name,
        email: email,
        phone: phone,
        address: address,
        city: city,
        state: state,
        pincode: pincode,
        location_id: location_id,
        password: password,
        password_confirmation: confirmPassword
      };

      // Disable submit button
      const submitBtn = document.getElementById('submitBtn');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      // Submit form via AJAX
      fetch(baseUrl + 'app/agents/store', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(formData)
      })
        .then(response => {
          console.log('Response status:', response.status);
          // Check if response is ok (status 200-299)
          if (!response.ok) {
            console.log('Response not OK, handling error');
            // For validation errors (422) or other errors
            return response.json().then(data => {
              throw { isValidation: response.status === 422, data: data };
            });
          }
          console.log('Response OK, parsing JSON');
          return response.json();
        })
        .then(data => {
          console.log('Success response data:', data);
          // This only runs if response was successful
          // Close modal
          const modalInstance = bootstrap.Modal.getInstance(addAgentModal);
          if (modalInstance) {
            modalInstance.hide();
          }

          // Show success alert
          showAlert('success', 'Agent created successfully');

          // Reset form
          agentForm.reset();
          $('#agentLocation').val('').trigger('change');

          // Re-enable submit button
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="icon-base ri ri-save-line me-1"></i>Save Agent';

          // Auto Reload with delay to show success
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        })
        .catch(error => {
          console.error('Catch block - Error:', error);

          // Handle validation errors
          if (error.isValidation && error.data && error.data.errors) {
            console.log('Handling validation errors');
            Object.keys(error.data.errors).forEach(field => {
              const fieldMap = {
                name: 'agentName',
                email: 'agentEmail',
                phone: 'agentPhone',
                address: 'agentAddress',
                city: 'agentCity',
                state: 'agentState',
                pincode: 'agentPincode',
                location_id: 'agentLocation',
                password: 'agentPassword'
              };

              const fieldId = fieldMap[field];
              if (fieldId) {
                const element = document.getElementById(fieldId);
                if (element) {
                  element.classList.add('is-invalid');
                }
                const errorElement = document.getElementById(field + 'Error');
                if (errorElement) {
                  errorElement.textContent = error.data.errors[field][0];
                }
              }
            });

            showAlert('danger', 'Please fix the errors in the form');
          } else if (error.data && error.data.message) {
            showAlert('danger', error.data.message);
          } else {
            showAlert('danger', 'Failed to create agent');
          }

          // Re-enable submit button
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="icon-base ri ri-save-line me-1"></i>Save Agent';
        });
    });
  }
});
