/**
 * Agent View Visits Page
 */

'use strict';

document.addEventListener('DOMContentLoaded', function (e) {
    const dt_visits_table = document.querySelector('.datatables-agent-visits');
    const agentId = dt_visits_table ? dt_visits_table.dataset.agentId : null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    if (dt_visits_table && agentId) {
        const dt_visits = new DataTable(dt_visits_table, {
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'app/agents/view/' + agentId + '/visits/data',
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
                { data: 'fake_id' },
                { data: 'date' },
                { data: 'client_name' },
                { data: 'start_time' },
                { data: 'end_time' },
                { data: 'duration' },
                { data: 'location' },
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
                        return `<span>${full.fake_id}</span>`;
                    }
                },
                {
                    targets: 3,
                    responsivePriority: 1,
                    render: function (data, type, full) {
                        return `<span class="fw-medium text-heading">${full.client_name || 'N/A'}</span>`;
                    }
                },
                {
                    targets: 6, // Duration
                    render: function (data, type, full) {
                        if (full.duration === 'Ongoing') {
                            return `<span class="badge bg-label-warning">${full.duration}</span>`;
                        }
                        return `<span>${full.duration}</span>`;
                    }
                },
                {
                    targets: -1,
                    title: 'Actions',
                    searchable: false,
                    orderable: false,
                    render: function (data, type, full, meta) {
                        return (
                            '<div class="d-inline-block text-nowrap">' +
                            '<a href="' + baseUrl + 'app/agents/visits/' + full['id'] + '" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect waves-light" title="View Details"><i class="icon-base ri ri-eye-line icon-20px"></i></a>' +
                            '</div>'
                        );
                    }
                }
            ],
            order: [[2, 'desc']], // Order by Date desc
            displayLength: 20,
            dom:
                '<"row mx-1"' +
                '<"col-sm-12 col-md-3" l>' +
                '<"col-sm-12 col-md-9"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-md-end justify-content-center flex-wrap me-1"<"me-4"f>B>>' +
                '>t' +
                '<"row mx-2"' +
                '<"col-sm-12 col-md-6"i>' +
                '<"col-sm-12 col-md-6"p>' +
                '>',
            language: {
                sLengthMenu: 'Show _MENU_',
                search: '',
                searchPlaceholder: 'Search Client',
                paginate: {
                    next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
                    previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
                    first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
                    last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
                }
            },
            // Buttons with Dropdown
            buttons: [],
            scrollX: true,
            autoWidth: false
        });
    }
});
