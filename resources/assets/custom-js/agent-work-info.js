/**
 * Agent Work Information Page
 */

'use strict';

document.addEventListener('DOMContentLoaded', function (e) {
    let borderColor, bodyBg, headingColor;

    borderColor = config.colors.borderColor;
    bodyBg = config.colors.bodyBg;
    headingColor = config.colors.headingColor;

    const dt_clients_table = document.querySelector('.datatables-assigned-clients');
    const agentId = dt_clients_table ? dt_clients_table.dataset.agentId : null;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    if (dt_clients_table && agentId) {
        const dt_clients = new DataTable(dt_clients_table, {
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'app/agents/view/' + agentId + '/work/clients',
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
                { data: 'emi_id' },
                { data: 'client_name' },
                { data: 'mobile' },
                { data: 'loan_amount' },
                { data: 'outstanding' },
                { data: 'status' },
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
                    render: function (data, type, full) {
                        return `<span class="badge bg-label-primary">#${full.emi_id || 'N/A'}</span>`;
                    }
                },
                {
                    targets: 3,
                    responsivePriority: 1,
                    render: function (data, type, full) {
                        if (full.loan_account_id) {
                            return `<a href="${baseUrl}loan/loan-account/${full.loan_account_id}" class="fw-medium text-primary">${full.client_name || 'N/A'}</a>`;
                        }
                        return `<span class="fw-medium">${full.client_name || 'N/A'}</span>`;
                    }
                },
                {
                    targets: 4,
                    render: function (data, type, full) {
                        return `<span>${full.mobile || 'N/A'}</span>`;
                    }
                },
                {
                    targets: 5,
                    render: function (data, type, full) {
                        return `<span class="fw-semibold">₹${parseFloat(full.loan_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                    }
                },
                {
                    targets: 6,
                    render: function (data, type, full) {
                        const outstanding = parseFloat(full.outstanding || 0);
                        const color = outstanding > 0 ? 'text-danger' : 'text-success';
                        return `<span class="fw-semibold ${color}">₹${outstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                    }
                },
                {
                    targets: 7,
                    render: function (data, type, full) {
                        const statusMap = {
                            'active': '<span class="badge bg-label-success">Active</span>',
                            'overdue': '<span class="badge bg-label-danger">Overdue</span>',
                            'pending': '<span class="badge bg-label-warning">Pending</span>',
                            'paid': '<span class="badge bg-label-info">Paid</span>',
                            'default': '<span class="badge bg-label-secondary">Unknown</span>'
                        };
                        return statusMap[full.status] || statusMap['default'];
                    }
                },
                {
                    targets: -1,
                    title: 'Actions',
                    searchable: false,
                    orderable: false,
                    render: function (data, type, full, meta) {
                        const viewUrl = `${baseUrl}app/agents/view/${agentId}/work/client/${full.id}`;
                        const viewBtn = `<a href="${viewUrl}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Details"><i class="icon-base ri ri-eye-line icon-22px"></i></a>`;
                        return `<div class="d-flex align-items-center gap-4">${viewBtn}</div>`;
                    }
                }
            ],
            order: [[2, 'desc']],
            displayLength: 20,
            language: {
                paginate: {
                    next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
                    previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
                    first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
                    last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
                }
            },
            scrollX: true,
            autoWidth: false
        });
    }
});
