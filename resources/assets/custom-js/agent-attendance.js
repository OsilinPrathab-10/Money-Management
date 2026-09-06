/**
 * Page Agent Attendance
 */

'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function (e) {
    let borderColor, bodyBg, headingColor;

    borderColor = config.colors.borderColor;
    bodyBg = config.colors.bodyBg;
    headingColor = config.colors.headingColor;

    // Variable declaration for table
    const dt_attendance_table = document.querySelector('.datatables-attendance');

    // ajax setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Flatpickr for date inputs
    const startDatePicker = flatpickr('#startDate', {
        dateFormat: 'Y-m-d',
        maxDate: 'today',
        appendTo: document.body,
        static: false,
        onOpen: function (selectedDates, dateStr, instance) {
            instance.calendarContainer.style.zIndex = 9999;
        }
    });

    const endDatePicker = flatpickr('#endDate', {
        dateFormat: 'Y-m-d',
        maxDate: 'today',
        appendTo: document.body,
        static: false,
        onOpen: function (selectedDates, dateStr, instance) {
            instance.calendarContainer.style.zIndex = 9999;
        }
    });

    // Filter state
    let currentFilter = {
        start_date: '',
        end_date: ''
    };

    // Attendance datatable
    if (dt_attendance_table) {
        const dt_attendance = new DataTable(dt_attendance_table, {
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'app/agents/agent-attendance/list',
                data: function (d) {
                    d.start_date = currentFilter.start_date;
                    d.end_date = currentFilter.end_date;
                },
                dataSrc: function (json) {
                    if (typeof json.recordsTotal !== 'number') {
                        json.recordsTotal = 0;
                    }
                    if (typeof json.recordsFiltered !== 'number') {
                        json.recordsFiltered = 0;
                    }
                    json.data = Array.isArray(json.data) ? json.data : [];
                    return json.data;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'id' },
                { data: 'agent_name' },
                { data: 'check_in_at' },
                { data: 'check_out_at' },
                { data: 'total_hours' },
                { data: 'action' }
            ],
            columnDefs: [
                {
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
                    searchable: false,
                    orderable: false,
                    targets: 1,
                    render: function (data, type, full, meta) {
                        return `<span>${meta.row + meta.settings._iDisplayStart + 1}</span>`;
                    }
                },
                {
                    targets: 2,
                    responsivePriority: 4,
                    render: function (data, type, full, meta) {
                        return `<span class="fw-medium">${full.agent_name || 'N/A'}</span>`;
                    }
                },
                {
                    targets: 3,
                    render: function (data, type, full) {
                        if (full.check_in_at && full.check_in_at !== 'N/A') {
                            const date = new Date(full.check_in_at).toLocaleString('en-IN', {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                            return `<span>${date}</span>`;
                        }
                        return `<span>N/A</span>`;
                    }
                },
                {
                    targets: 4,
                    render: function (data, type, full) {
                        if (full.check_out_at === 'Still Working') {
                            return `<span class="badge bg-label-success">Still Working</span>`;
                        }
                        if (full.check_out_at && full.check_out_at !== 'N/A') {
                            const date = new Date(full.check_out_at).toLocaleString('en-IN', {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                            return `<span>${date}</span>`;
                        }
                        return `<span>N/A</span>`;
                    }
                },
                {
                    targets: 5,
                    className: 'text-center',
                    render: function (data, type, full) {
                        if (full.total_hours === 'In Progress') {
                            return `<span class="badge bg-label-primary">In Progress</span>`;
                        }
                        return `<span class="fw-semibold">${full.total_hours}</span>`;
                    }
                },
                {
                    targets: -1,
                    title: 'Actions',
                    searchable: false,
                    orderable: false,
                    render: function (data, type, full, meta) {
                        const viewUrl = `${baseUrl}app/agents/agent-attendance/${full.id}`;
                        const viewBtn = `<a href="${viewUrl}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill"><i class="icon-base ri ri-eye-line icon-22px"></i></a>`;
                        return `<div class="d-flex align-items-center gap-4">${viewBtn}</div>`;
                    }
                }
            ],
            order: [[3, 'desc']],
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
                                placeholder: 'Search Attendance',
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
                                            title: 'Agent Attendance',
                                            text: '<i class="icon-base ri ri-printer-line me-2"></i>Print',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [1, 2, 3, 4, 5],
                                                format: {
                                                    body: function (inner, coldex, rowdex) {
                                                        if (inner.length <= 0) return inner;
                                                        const parser = new DOMParser();
                                                        const doc = parser.parseFromString(inner, 'text/html');
                                                        return (doc.body.textContent || doc.body.innerText || '').trim();
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
                                            title: 'Agent Attendance',
                                            text: '<i class="icon-base ri ri-file-text-line me-2"></i>Csv',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [1, 2, 3, 4, 5],
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
                                            title: 'Agent Attendance',
                                            text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [1, 2, 3, 4, 5],
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
                                            title: 'Agent Attendance',
                                            text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>Pdf',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [1, 2, 3, 4, 5],
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
                                                doc.content[0].text = 'Agent Attendance | Loan App';
                                                doc.defaultStyle.fontSize = 10;
                                                doc.styles.tableHeader.fontSize = 10;
                                                doc.styles.tableHeader.alignment = 'left';
                                                doc.styles.tableHeader.fillColor = '#f5f5f5';
                                                doc.styles.tableHeader.color = '#333333';

                                                doc.content.splice(1, 0, {
                                                    text: 'Agent Attendance Report',
                                                    margin: [0, 0, 0, 12],
                                                    fontSize: 12,
                                                    bold: true
                                                });

                                                const tableContent = doc.content.find(item => item.table);
                                                if (tableContent) {
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
                                            title: 'Agent Attendance',
                                            text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [1, 2, 3, 4, 5],
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
                bottomStart: {
                    rowClass: 'row mx-3 justify-content-between',
                    features: [
                        {
                            info: {
                                text: 'Showing _START_ to _END_ of _TOTAL_ entries'
                            }
                        }
                    ]
                },
                bottomEnd: 'paging'
            },
            pageLength: 20,
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
                            return 'Attendance Details #' + data['id'];
                        }
                    }),
                    type: 'column',
                    renderer: function (api, rowIdx, columns) {
                        const data = columns
                            .map(function (col) {
                                return col.title !== ''
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
                document.querySelectorAll('.dt-buttons .btn').forEach(btn => {
                    btn.classList.remove('btn-secondary');
                });
            }
        });

        // Apply custom date filter
        document.getElementById('applyFilter').addEventListener('click', function () {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (!startDate || !endDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Dates',
                    text: 'Please select both start and end dates',
                    customClass: {
                        confirmButton: 'btn btn-warning'
                    }
                });
                return;
            }

            currentFilter.start_date = startDate;
            currentFilter.end_date = endDate;

            dt_attendance.draw();

            // Close the modal
            const filterModal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
            if (filterModal) {
                filterModal.hide();
            }
        });

        // Reset filter
        document.getElementById('resetFilter').addEventListener('click', function () {
            currentFilter.start_date = '';
            currentFilter.end_date = '';

            startDatePicker.clear();
            endDatePicker.clear();

            dt_attendance.draw();

            // Close the modal
            const filterModal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
            if (filterModal) {
                filterModal.hide();
            }
        });

        // View Attendance Details
        document.addEventListener('click', function (e) {
            if (e.target.closest('.view-attendance')) {
                const viewBtn = e.target.closest('.view-attendance');
                const attendanceId = viewBtn.dataset.id;
                const dtrModal = document.querySelector('.dtr-bs-modal.show');

                if (dtrModal) {
                    const bsModal = bootstrap.Modal.getInstance(dtrModal);
                    bsModal.hide();
                }

                // Fetch attendance details
                fetch(`${baseUrl}app/agents/agent-attendance/${attendanceId}`)
                    .then(response => response.json())
                    .then(data => {
                        const content = `
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Attendance ID</label>
                  <p>#${data.id}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Agent Name</label>
                  <p>${data.agent_name || 'N/A'}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Check In</label>
                  <p>${data.check_in_at || 'N/A'}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Check Out</label>
                  <p>${data.check_out_at || 'Still Working'}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Check In Location</label>
                  <p>${data.check_in_location || 'N/A'}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Check Out Location</label>
                  <p>${data.check_out_location || 'N/A'}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Total Hours</label>
                  <p class="h5">${data.total_hours || 'In Progress'}</p>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Status</label>
                  <p>${data.status || 'N/A'}</p>
                </div>
                ${data.notes && data.notes !== 'No notes' ? `
                <div class="col-12 mb-3">
                  <label class="form-label fw-semibold">Notes</label>
                  <p>${data.notes}</p>
                </div>
                ` : ''}
              </div>
            `;
                        document.getElementById('attendanceDetailsContent').innerHTML = content;
                        const viewModal = new bootstrap.Modal(document.getElementById('viewAttendanceModal'));
                        viewModal.show();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to load attendance details');
                    });
            }
        });

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
    }
});
