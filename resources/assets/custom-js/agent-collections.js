/**
 * Page Agent Collections
 */

'use strict';

// Datatable (js)
document.addEventListener('DOMContentLoaded', function (e) {
    let borderColor, bodyBg, headingColor;

    borderColor = config.colors.borderColor;
    bodyBg = config.colors.bodyBg;
    headingColor = config.colors.headingColor;

    let dt_collections = null;
    let clickedAssignButton = null;

    function refreshAgentStats() {
        fetch(`${baseUrl}app/agents/agent-collections/stats`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const data = res.data;
                    const formatCurrency = (val) => '₹' + Math.round(val).toLocaleString('en-IN');
                    
                    const elAgentCount = document.getElementById('stat-agent-count');
                    const elAgentAmount = document.getElementById('stat-agent-amount');
                    const elAdminCount = document.getElementById('stat-admin-count');
                    const elAdminAmount = document.getElementById('stat-admin-amount');
                    const elLinkCount = document.getElementById('stat-link-count');
                    const elLinkAmount = document.getElementById('stat-link-amount');

                    if (elAgentCount) elAgentCount.textContent = data.agentCollectedCount;
                    if (elAgentAmount) elAgentAmount.textContent = formatCurrency(data.agentCollectedAmount);
                    if (elAdminCount) elAdminCount.textContent = data.adminCollectedCount;
                    if (elAdminAmount) elAdminAmount.textContent = formatCurrency(data.adminCollectedAmount);
                    if (elLinkCount) elLinkCount.textContent = data.paymentLinkCount;
                    if (elLinkAmount) elLinkAmount.textContent = formatCurrency(data.paymentLinkAmount);
                }
            })
            .catch(err => console.error('Failed to refresh stats:', err));
    }

    // Variable declaration for table
    const dt_collections_table = document.querySelector('.datatables-collections');

    // ajax setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Initialize Select2 specifically for Add Collection Modal
    $('#addCollectionModal .select2').select2({
        dropdownParent: $('#addCollectionModal')
    });

    // Initialize Select2 specifically for Assign Agent Modal
    $('#assignAgentModal .select2').select2({
        dropdownParent: $('#assignAgentModal')
    });

    // Initialize Select2 AJAX for EMI search (Add Collection)
    $('#emiSearchSelect').select2({
        ajax: {
            url: baseUrl + 'app/agents/agent-collections/search-emis',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    page: params.page || 1,
                    agent_id: $('#addCollectionModal select[name="agent_id"]').val() || $('#addCollectionModal input[name="agent_id"]').val(),
                    action: 'collection'
                };
            },
            processResults: function (data) {
                return {
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
        placeholder: 'Search for client, account number or phone number',
        minimumInputLength: 0,
        allowClear: true,
        width: '100%',
        dropdownParent: $('#addCollectionModal')
    });

    // Initialize Select2 AJAX for EMI search (Assign Agent)
    $('#emiAssignSelect').select2({
        ajax: {
            url: baseUrl + 'app/agents/agent-collections/search-emis',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    page: params.page || 1,
                    agent_id: $('#assignAgentModal select[name="agent_id"]').val(),
                    action: 'assign'
                };
            },
            processResults: function (data) {
                return {
                    results: data.results,
                    pagination: {
                        more: data.pagination.more
                    }
                };
            },
            cache: true
        },
        placeholder: 'Search for client, account number or phone number',
        minimumInputLength: 0,
        allowClear: true,
        width: '100%',
        dropdownParent: $('#assignAgentModal')
    });

    // Clear EMI selection if agent changes in assign modal
    $('#assignAgentModal select[name="agent_id"]').on('change', function() {
        $('#emiAssignSelect').val(null).trigger('change');
    });

        let activePartialRules = window.partialPaymentGlobal || null;

        const applyPartialRulesToCollectionForm = (rules) => {
            activePartialRules = rules;
            const helpEl = document.getElementById('partialCollectionHelp');
            const partialRadio = document.getElementById('type_partial');

            if (!rules || !rules.is_active) {
                if (partialRadio) {
                    partialRadio.disabled = true;
                    if ($('.payment-type-radio:checked').val() === 'partial') {
                        $('#type_full').prop('checked', true).trigger('change');
                    }
                }
                if (helpEl) {
                    helpEl.classList.add('d-none');
                    helpEl.textContent = '';
                }
                return;
            }

            if (partialRadio) {
                if (typeof rules.allows_partial === 'boolean') {
                    partialRadio.disabled = !rules.allows_partial;
                } else {
                    partialRadio.disabled = false;
                }
            }

            if (helpEl) {
                if ($('.payment-type-radio:checked').val() === 'partial') {
                    helpEl.classList.remove('d-none');
                    const pct = rules.minimum_partial_percentage ?? 10;
                    const baseLabel = rules.penalty_calculation_method === 'emi_plus_partial_remaining'
                        ? 'outstanding balance'
                        : 'EMI amount';

                    if (typeof rules.minimum_partial_amount === 'number' && rules.minimum_partial_amount > 0) {
                        helpEl.textContent = rules.timing_allowed !== false
                            ? `Min partial: ₹${Math.round(rules.minimum_partial_amount)} (${pct}% of ${baseLabel}). Max: ₹${Math.round(rules.maximum_partial_amount || 0)}.`
                            : (rules.timing_message || 'Partial payment is not allowed at this time.');
                    } else {
                        helpEl.textContent = `Partial payment: minimum ${pct}% of ${baseLabel} (select EMI to calculate amount).`;
                    }
                } else {
                    helpEl.classList.add('d-none');
                }
            }
        };

        const fetchPartialRules = (emiId) => {
            if (!emiId) {
                applyPartialRulesToCollectionForm(null);
                return Promise.resolve(null);
            }

            return fetch(`${baseUrl}app/agents/agent-collections/partial-payment-rules/${emiId}`, {
                headers: { Accept: 'application/json' }
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        applyPartialRulesToCollectionForm(res.data);
                        return res.data;
                    }
                    applyPartialRulesToCollectionForm(null);
                    return null;
                })
                .catch(() => {
                    applyPartialRulesToCollectionForm(null);
                    return null;
                });
        };

        const applyCollectionAmountLimits = (maxAmount, paymentType, rules) => {
            $('#collectionAmount').data('max-amount', maxAmount);

            if (paymentType === 'full') {
                const formattedAmount = maxAmount.toFixed(2);
                $('#collectionAmount')
                    .val(formattedAmount)
                    .attr('max', formattedAmount)
                    .attr('min', '0.01')
                    .attr('step', '0.01')
                    .prop('readonly', true);
            } else {
                const roundedMax = Math.floor(maxAmount);
                const minPartial = (rules && rules.is_active && rules.minimum_partial_amount > 0)
                    ? rules.minimum_partial_amount
                    : 0;
                $('#collectionAmount')
                    .val(minPartial > 0 ? minPartial : roundedMax)
                    .attr('max', roundedMax)
                    .attr('min', String(Math.max(1, minPartial)))
                    .attr('step', '1')
                    .prop('readonly', false);
            }
        };

        // Apply global partial config on load (disable partial if not enabled in settings)
        if (window.partialPaymentGlobal) {
            applyPartialRulesToCollectionForm(window.partialPaymentGlobal);
        }

        // Handle EMI selection to update amount
        $('#emiSearchSelect').on('select2:select', function (e) {
            const data = e.params.data;
            const emiId = data.id;

            fetchPartialRules(emiId).then(rules => {
                if (data.amount !== undefined) {
                    const maxAmount = parseFloat(data.amount);
                    const paymentType = $('.payment-type-radio:checked').val();
                    applyCollectionAmountLimits(maxAmount, paymentType, rules);
                }
            });
        });

        // Handle Payment Type changes
        $('.payment-type-radio').on('change', function() {
            const type = $(this).val();
            const maxAmount = $('#collectionAmount').data('max-amount');
            const emiId = $('#emiSearchSelect').val();

            if (type === 'partial' && !emiId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Select EMI first',
                    text: 'Please select an EMI before choosing partial payment.',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
                $('#type_full').prop('checked', true);
                return;
            }

            if (type === 'partial' && emiId) {
                fetchPartialRules(emiId).then(rules => {
                    applyPartialRulesToCollectionForm(rules);
                    if (maxAmount) {
                        applyCollectionAmountLimits(parseFloat(maxAmount), type, rules);
                    }
                });
                return;
            }

            if (type === 'partial' && activePartialRules && !activePartialRules.allows_partial) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Partial payment not allowed',
                    text: activePartialRules.timing_message || 'Partial payments are not allowed for this EMI at this time.',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
                $('#type_full').prop('checked', true);
                return;
            }

            applyPartialRulesToCollectionForm(activePartialRules);

            if (maxAmount) {
                applyCollectionAmountLimits(parseFloat(maxAmount), type, activePartialRules);
            }
        });

        // Block decimal points for partial payments
        $('#collectionAmount').on('keypress', function(e) {
            const paymentType = $('.payment-type-radio:checked').val();
            if (paymentType === 'partial' && (e.which === 46 || e.key === '.')) {
                e.preventDefault();
            }
        });

        $('#collectionAmount').on('input', function() {
            const paymentType = $('.payment-type-radio:checked').val();
            if (paymentType === 'partial') {
                let val = $(this).val();
                if (val.indexOf('.') !== -1) {
                    $(this).val(val.split('.')[0]);
                }
            }
        });

        // [NEW] Handle pre-selected EMI from URL (e.g. from Assignments page)
    const urlParams = new URLSearchParams(window.location.search);
    const emiIdParam = urlParams.get('emi_id');
    if (emiIdParam) {
        $.ajax({
            url: baseUrl + 'app/agents/agent-collections/get-emi-info/' + emiIdParam,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Pre-populate Select2
                    const newOption = new Option(data.text, data.id, true, true);
                    $('#emiSearchSelect').append(newOption).trigger('change');
                    
                    const maxAmount = parseFloat(data.amount);
                    const paymentType = $('.payment-type-radio:checked').val();
                    applyPartialRulesToCollectionForm(data.partial_payment || null);
                    applyCollectionAmountLimits(maxAmount, paymentType, data.partial_payment || null);

                    // Open the modal
                    $('#addCollectionModal').modal('show');
                }
            },
            error: function() {
                console.error('Failed to fetch pre-selected EMI info');
            }
        });
    }

    // ─── My Assignments: "Collect" button → pre-fill Add Collection modal ────
    $(document).on('click', '.btn-collect-assigned', function () {
        clickedAssignButton = this;
        const emiId   = $(this).data('emi-id');
        const amount  = parseFloat($(this).data('amount'));
        const client  = $(this).data('client');
        const account = $(this).data('account');
        const emiNo   = $(this).data('emi-no');

        if (!emiId) return;

        const label = `[#${account}] ${client} - EMI #${emiNo} - Pending: ₹${amount.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}`;

        // Inject pre-selected option into the EMI Select2
        const newOption = new Option(label, emiId, true, true);
        $('#emiSearchSelect').empty().append(newOption).trigger('change');

        $('#type_full').prop('checked', true);
        fetchPartialRules(emiId).then(rules => {
            applyCollectionAmountLimits(amount, 'full', rules);
        });

        // Open the Add Collection modal
        const modal = new bootstrap.Modal(document.getElementById('addCollectionModal'));
        modal.show();
    });

    // Collections datatable
        if (dt_collections_table) {
            dt_collections = new DataTable(dt_collections_table, {
                processing: true,
                serverSide: true,
                ajax: {
                    url: baseUrl + 'app/agents/agent-collections/list',
                    data: function (d) {
                        d.status = $('#filterStatus').val();
                        d.collector = $('#filterCollector').val();
                        d.method = $('#filterMethod').val();
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
                    { data: 'client_name' },
                    { data: 'agent_name' },
                    { data: 'emi_id' },
                    { data: 'amount' },
                    { data: 'payment_method' },
                    { data: 'payment_type' },
                    { data: 'status' },
                    { data: 'collected_at' },
                    { data: 'action' }
                ],
                columnDefs: [
                    {
                        // S.No column — includes checkbox for pending rows (Admin/Staff only)
                        searchable: false,
                        orderable: false,
                        targets: 0,
                        render: function (data, type, full, meta) {
                            const num = meta.row + meta.settings._iDisplayStart + 1;
                            const isPending = (full.status === 'in_progress' || full.status === 'pending');
                            if (isPending && window.userRole !== 'Agent') {
                                return `<div class="d-flex align-items-center gap-1"><input type="checkbox" class="collection-checkbox" data-id="${full.id}"><span>${num}</span></div>`;
                            }
                            return `<span>${num}</span>`;
                        }
                    },
                    {
                        targets: 1, // Client
                        render: function (data, type, full, meta) {
                            return `<span>${full.client_name || 'N/A'}</span>`;
                        }
                    },
                    {
                        targets: 2, // Collected By
                        render: function (data, type, full, meta) {
                            return `<span class="fw-medium text-heading">${full.agent_name || 'Admin'}</span>`;
                        }
                    },
                    {
                        targets: 3, // EMI ID
                        render: function (data, type, full) {
                            return `<span class="badge bg-label-secondary">${full.emi_id}</span>`;
                        }
                    },
                    {
                        targets: 4, // Amount
                        render: function (data, type, full) {
                            return `<span class="fw-semibold">₹${parseFloat(full.amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                        }
                    },
                    {
                        targets: 5, // Method
                        className: 'text-center',
                        render: function (data, type, full, meta) {
                            const method = (full.payment_method || '').toLowerCase();
                            const agentName = (full.agent_name || '').toLowerCase();
                            const isAdmin = agentName.startsWith('admin');
                            const methodMap = {
                                in_hand:      { label: isAdmin ? 'Admin In-Hand' : 'Agent In-Hand', color: isAdmin ? 'success' : 'primary', icon: 'ri-hand-coin-line' },
                                 upi:          { label: isAdmin ? 'Admin UPI'           : 'Agent UPI',           color: isAdmin ? 'success' : 'info',    icon: 'ri-qr-code-line' },
                                bank_transfer:{ label: isAdmin ? 'Admin Bank Transfer' : 'Agent Bank Transfer', color: isAdmin ? 'warning' : 'warning', icon: 'ri-bank-line' },
                             };
                            let methodConfig = methodMap[method];
                            if (!methodConfig) {
                                let label = method ? method.charAt(0).toUpperCase() + method.slice(1).replace(/_/g,' ') : 'Unknown';
                                if (isAdmin && !label.toLowerCase().startsWith('admin')) {
                                    label = 'Admin ' + label;
                                }
                                methodConfig = { label: label, color: isAdmin ? 'success' : 'secondary', icon: 'ri-question-line' };
                            }
                            return `<span class="badge bg-label-${methodConfig.color}"><i class="icon-base ${methodConfig.icon} me-1"></i>${methodConfig.label}</span>`;
                        }
                    },
                    {
                        targets: 6, // Type
                        className: 'text-center',
                        render: function (data, type, full, meta) {
                            const paymentType = (full.payment_type || '').toLowerCase();
                            const typeMap = {
                                overdue: { label: 'Overdue', color: 'danger' },
                                partial: { label: 'Partial', color: 'warning' },
                                full: { label: 'Full', color: 'success' }
                            };
                            const typeConfig = typeMap[paymentType] || { label: paymentType.charAt(0).toUpperCase() + paymentType.slice(1), color: 'secondary' };
                            return `<span class="badge bg-label-${typeConfig.color}">${typeConfig.label}</span>`;
                        }
                    },
                    {
                        targets: 7, // Status
                        className: 'text-center',
                        render: function (data, type, full, meta) {
                            const status = (full.status || '').toLowerCase();
                            const statusMap = {
                                in_progress: { label: 'Pending', color: 'warning' },
                                verified: { label: 'Verified', color: 'success' },
                                rejected: { label: 'Rejected', color: 'danger' },
                                completed: { label: 'Completed', color: 'success' }
                            };
                            const statusConfig = statusMap[status] || { label: 'Unknown', color: 'secondary' };
                            return `<span class="badge bg-label-${statusConfig.color}">${statusConfig.label}</span>`;
                        }
                    },
                    {
                        targets: 8, // Date
                        render: function (data, type, full) {
                            if (!full.collected_at) return '<span class="text-muted">N/A</span>';
                            const d = new Date(full.collected_at);
                            const day = String(d.getDate()).padStart(2, '0');
                            const month = String(d.getMonth() + 1).padStart(2, '0');
                            const year = d.getFullYear();
                            const date = day + '-' + month + '-' + year;
                            
                            let time = '12:00 am';
                            if (full.created_at) {
                                const cTime = new Date(full.created_at);
                                time = cTime.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
                            } else {
                                time = d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
                            }
                            
                            return `<span class="d-block">${date}</span><small class="text-muted">${time}</small>`;
                        }
                    },
                    {
                        targets: -1,
                        title: 'Actions',
                        searchable: false,
                        orderable: false,
                        render: function (data, type, full, meta) {
                            let actions = `<button class="btn btn-icon btn-text-secondary btn-sm rounded-pill view-collection" data-id="${full.id}" title="View Details"><i class="icon-base ri ri-eye-line icon-22px"></i></button>`;
                            
                            // [NEW] History Button
                            actions += `<button class="btn btn-icon btn-text-info btn-sm rounded-pill view-history" data-emi-id="${full.real_emi_id}" title="Payment History Breakdown"><i class="icon-base ri ri-history-line icon-22px"></i></button>`;
 
                            // Add verify button for pending collections - ADMIN/STAFF ONLY
                            if ((full.status === 'pending' || full.status === 'in_progress') && (window.userRole === 'Admin' || window.userRole === 'Staff')) {
                                actions += `<button class="btn btn-icon btn-text-success btn-sm rounded-pill verify-collection" data-id="${full.id}" title="Verify Collection"><i class="icon-base ri ri-check-double-line icon-22px"></i></button>`;
                            }
                            
                            return `<div class="d-flex align-items-center gap-1">${actions}</div>`;
                        }
                    },
                {
                    targets: '_all',
                    className: 'text-nowrap'
                },
                {
                    targets: [1], // Client
                    width: '200px'
                },
                {
                    targets: [2], // Collected By
                    width: '150px'
                },
                {
                    targets: [4], // Amount
                    width: '120px'
                },
                {
                    targets: [8], // Date
                    width: '150px'
                }
            ],
            order: [[8, 'desc']],  // Collected At date descending
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
                                placeholder: 'Search Collections',
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
                                            title: 'Agent Collections',
                                            text: '<i class="icon-base ri ri-printer-line me-2"></i>Print',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
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
                                            title: 'Agent Collections',
                                            text: '<i class="icon-base ri ri-file-text-line me-2"></i>Csv',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
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
                                            title: 'Agent Collections',
                                            text: '<i class="icon-base ri ri-file-excel-line me-2"></i>Excel',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
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
                                            title: 'Agent Collections',
                                            text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>Pdf',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
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
                                                doc.content[0].text = 'Agent Collections | Loan App';
                                                doc.defaultStyle.fontSize = 10;
                                                doc.styles.tableHeader.fontSize = 10;
                                                doc.styles.tableHeader.alignment = 'left';
                                                doc.styles.tableHeader.fillColor = '#f5f5f5';
                                                doc.styles.tableHeader.color = '#333333';
 
                                                doc.content.splice(1, 0, {
                                                    text: 'Agent Collections Report',
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
                                            title: 'Agent Collections',
                                            text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
                                            className: 'dropdown-item',
                                            exportOptions: {
                                                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
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
            scrollX: true,
            autoWidth: false,
            initComplete: function () {
                document.querySelectorAll('.dt-buttons .btn').forEach(btn => {
                    btn.classList.remove('btn-secondary');
                });
            }
        });

        // Filter change events
        $('#filterStatus, #filterCollector, #filterMethod').on('change', function () {
            dt_collections.draw();
        });

        // View Collection Details - Navigate to view page
        document.addEventListener('click', function (e) {
            if (e.target.closest('.view-collection')) {
                const viewBtn = e.target.closest('.view-collection');
                const collectionId = viewBtn.dataset.id;

                // Navigate to view page
                window.location.href = `${baseUrl}app/agents/agent-collections/${collectionId}`;
            }
        });

        // Verify Collection
        document.addEventListener('click', function (e) {
            if (e.target.closest('.verify-collection')) {
                const verifyBtn = e.target.closest('.verify-collection');
                const collectionId = verifyBtn.dataset.id;
                const dtrModal = document.querySelector('.dtr-bs-modal.show');

                if (dtrModal) {
                    const bsModal = bootstrap.Modal.getInstance(dtrModal);
                    bsModal.hide();
                }

                document.getElementById('verifyCollectionId').value = collectionId;
                const verifyModal = new bootstrap.Modal(document.getElementById('verifyCollectionModal'));
                verifyModal.show();
            }
        });

        // Handle Verify Form Submission
        document.getElementById('verifyCollectionForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const collectionId = formData.get('collection_id');

            fetch(`${baseUrl}app/agents/agent-collections/${collectionId}/verify`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    const verifyModal = bootstrap.Modal.getInstance(document.getElementById('verifyCollectionModal'));
                    verifyModal.hide();

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message || 'Collection verified successfully',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        }
                    }).then(() => {
                        if (dt_collections) {
                            dt_collections.ajax.reload(null, false);
                        }
                        refreshAgentStats();
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to verify collection',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        }
                    });
                });
        });

        // Handle Assign Agent Form Submission
        const assignAgentForm = document.getElementById('assignAgentForm');
        if (assignAgentForm) {
            assignAgentForm.addEventListener('submit', function (e) {
                e.preventDefault();
                
                const saveBtn = document.getElementById('saveAssignBtn');
                const originalText = saveBtn.innerHTML;
                
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Assigning...';
                
                const formData = new FormData(this);
                const data = {};
                formData.forEach((value, key) => data[key] = value);

                fetch(`${baseUrl}app/agents/agent-collections/assign`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('assignAgentModal')).hide();
                        assignAgentForm.reset();
                        $('#emiAssignSelect').val(null).trigger('change');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Assigned!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            if (dt_collections) {
                                dt_collections.ajax.reload(null, false);
                            }
                            refreshAgentStats();
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: error.message || 'Failed to assign agent',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        }
                    });
                });
            });
        }

        // Handle Add Collection Form Submission
        const addCollectionForm = document.getElementById('addCollectionForm');
        if (addCollectionForm) {
            addCollectionForm.addEventListener('submit', function (e) {
                e.preventDefault();
                
                const saveBtn = document.getElementById('saveCollectionBtn');
                const originalText = saveBtn.innerHTML;
                
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
                
                const formData = new FormData(this);
                const data = {};
                formData.forEach((value, key) => data[key] = value);

                // Frontend validation for max amount
                const amount = parseFloat(data.amount);
                const maxAmount = parseFloat($('#collectionAmount').data('max-amount'));
                
                if (maxAmount && amount > (maxAmount + 1)) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Amount',
                        text: `Collection amount (₹${amount}) cannot exceed the pending EMI amount (₹${maxAmount}).`,
                        customClass: { confirmButton: 'btn btn-danger' }
                    });
                    return;
                }

                // Check for whole numbers in partial payment
                if (data.payment_type === 'partial' && !Number.isInteger(Number(data.amount))) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Amount',
                        text: 'Partial payment amount must be a whole number (no decimal values).',
                        customClass: { confirmButton: 'btn btn-danger' }
                    });
                    return;
                }

                if (data.payment_type === 'partial' && activePartialRules) {
                    const minPartial = activePartialRules.minimum_partial_amount || 0;
                    if (!activePartialRules.allows_partial) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                        Swal.fire({
                            icon: 'error',
                            title: 'Not allowed',
                            text: activePartialRules.timing_message || 'Partial payment is not allowed at this time.',
                            customClass: { confirmButton: 'btn btn-danger' }
                        });
                        return;
                    }
                    if (amount < minPartial) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Amount',
                            text: `Minimum partial payment is ₹${minPartial} (${activePartialRules.minimum_partial_percentage}% configured).`,
                            customClass: { confirmButton: 'btn btn-danger' }
                        });
                        return;
                    }
                }

                fetch(`${baseUrl}app/agents/agent-collections`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('addCollectionModal')).hide();
                        addCollectionForm.reset();
                        $('#emiSearchSelect').val(null).trigger('change');
                        
                        if (data.sms_data) {
                            const d = data.sms_data;
                            const clientName = d.client_name || 'Client';
                            const mobileNo = d.mobile_no || '';
                            const accountNo = d.account_no || '';
                            const amountPaid = parseFloat(d.amount_paid || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const remainingBalance = parseFloat(d.remaining_balance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            const isKandhuvatti = d.loan_mode === 'interest_only';
                            const paymentType = d.payment_type || '';

                            const isPartial = d.is_partial || false;
                            const emiBalance = parseFloat(d.emi_balance || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                            let msgText = d.sms_message || '';
                            let waMsgText = d.whatsapp_message || '';

                            if (!msgText || !waMsgText) {
                                let fallbackMsgText = '';
                                if (isKandhuvatti) {
                                    if (paymentType === 'principal') {
                                        fallbackMsgText = `Dear ${clientName},\nYour Principal payment of ₹${amountPaid} towards Shanmuga Finance Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
                                    } else {
                                        if (isPartial) {
                                            fallbackMsgText = `Dear ${clientName},\nYour Partial Interest payment of ₹${amountPaid} towards Shanmuga Finance Open Loan Account ${accountNo} has been received successfully.\nBalance Interest to pay: ₹${emiBalance}.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
                                        } else {
                                            fallbackMsgText = `Dear ${clientName},\nYour Interest payment of ₹${amountPaid} towards Shanmuga Finance Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
                                        }
                                    }
                                } else {
                                    if (isPartial) {
                                        fallbackMsgText = `Dear ${clientName},\nYour Partial EMI payment of ₹${amountPaid} towards Shanmuga Finance Loan Account ${accountNo} has been received successfully.\nBalance EMI to pay: ₹${emiBalance}.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
                                    } else {
                                        fallbackMsgText = `Dear ${clientName},\nYour EMI payment of ₹${amountPaid} towards Shanmuga Finance Loan Account ${accountNo} has been received successfully.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
                                    }
                                }

                                if (!msgText) {
                                    msgText = fallbackMsgText;
                                }
                                if (!waMsgText) {
                                    waMsgText = fallbackMsgText;
                                    if (d.application_number) {
                                        const publicToken = btoa(d.application_number);
                                        const publicLink = `${window.location.origin}/view-schedule/${publicToken}`;
                                        waMsgText += `\n\nPlease check your EMI Schedule here: ${publicLink}`;
                                    }
                                }
                            }

                            // Clean phone number (keep only digits)
                            let cleanMobile = mobileNo.replace(/\D/g, '');
                            if (cleanMobile.length === 10) {
                                cleanMobile = '91' + cleanMobile;
                            }

                            const waUrl = `https://wa.me/${cleanMobile}?text=${encodeURIComponent(waMsgText)}`;

                            // Determine iOS or Android separator for native SMS client
                            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                            const smsSeparator = isIOS ? '&' : '?';
                            const smsUrl = `sms:+${cleanMobile}${smsSeparator}body=${encodeURIComponent(msgText)}`;

                            const titleText = isKandhuvatti && paymentType === 'principal' ? 'Principal Payment Successful!' : 'Payment Successful!';

                            const badgeHtml = isPartial 
                                ? `<span class="badge bg-label-warning mb-3 fs-6 px-3 py-2"><i class="ri-alert-line me-1"></i>Partially Paid</span>` 
                                : `<span class="badge bg-label-success mb-3 fs-6 px-3 py-2"><i class="ri-checkbox-circle-line me-1"></i>Fully Paid</span>`;

                            Swal.fire({
                                title: titleText,
                                icon: 'success',
                                html: `
                                    <div class="py-2 text-center">
                                        ${badgeHtml}
                                        <h6 class="text-success mb-3">${data.message || 'Payment recorded successfully.'}</h6>
                                        <p class="text-muted small mb-4">Send payment confirmation receipt to client number: <strong>+${cleanMobile}</strong></p>
                                        
                                        <div class="d-grid gap-2 col-10 mx-auto">
                                            <a href="${waUrl}" target="_blank" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #25D366; border-color: #25D366; color: white; font-weight: 500;">
                                                <i class="ri-whatsapp-line fs-5"></i> Send WhatsApp Confirmation
                                            </a>
                                            
                                            <a href="${smsUrl}" class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #0088cc; border-color: #0088cc; color: white; font-weight: 500;">
                                                <i class="ri-message-3-line fs-5"></i> Send Native SMS
                                            </a>
                                        </div>
                                    </div>
                                `,
                                showCancelButton: false,
                                showCloseButton: true,
                                confirmButtonText: 'Done & Close',
                                customClass: {
                                    confirmButton: 'btn btn-primary px-5 mt-3'
                                }
                            }).then(() => {
                                if (dt_collections) {
                                    dt_collections.ajax.reload(null, false);
                                }
                                refreshAgentStats();
                                if (clickedAssignButton) {
                                    const tr = clickedAssignButton.closest('tr');
                                    if (tr) tr.remove();
                                    clickedAssignButton = null;
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                if (dt_collections) {
                                    dt_collections.ajax.reload(null, false);
                                }
                                refreshAgentStats();
                                if (clickedAssignButton) {
                                    const tr = clickedAssignButton.closest('tr');
                                    if (tr) tr.remove();
                                    clickedAssignButton = null;
                                }
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: error.message || 'Failed to add collection',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        }
                    });
                });
            });
        }

        // Handle History View
        $(dt_collections_table).on('click', '.view-history', function() {
            const emiId = $(this).data('emi-id');
            const $content = $('#paymentHistoryContent');
            $content.html('<tr><td colspan="5" class="text-center p-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading history...</td></tr>');
            $('#paymentHistoryModal').modal('show');

            $.get(baseUrl + `app/agents/agent-collections/${emiId}/history`, function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '';
                    response.data.forEach(item => {
                        html += `
                            <tr>
                                <td class="py-3">
                                    <span class="text-heading fw-medium">${item.date}</span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-success fs-6">${item.amount}</span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-label-primary">${item.method}</span>
                                </td>
                                <td class="py-3">
                                    <span class="text-muted small">${item.reference}</span>
                                </td>
                                <td class="py-3">
                                    <span class="text-muted small text-wrap" style="max-width: 150px; display: inline-block;">${item.remarks}</span>
                                </td>
                                <td class="py-3">
                                    <span class="text-muted small">${item.agent}</span>
                                </td>
                            </tr>
                        `;
                    });
                    $content.html(html);
                } else {
                    $content.html('<tr><td colspan="6" class="text-center text-muted p-4">No detailed history found for this collection.</td></tr>');
                }
            }).fail(function() {
                $content.html('<tr><td colspan="6" class="text-center text-danger p-4">Error loading history. Please try again.</td></tr>');
            });
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
                { selector: '.dt-layout-full', classToAdd: 'table-responsive' }
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

    // ──────────────────────────────────────────────
    //  BULK VERIFY  — checkbox selection & action
    // ──────────────────────────────────────────────

    const isAdminOrStaff = (window.userRole === 'Admin' || window.userRole === 'Staff');
    let selectedCollectionIds = new Set();

    function refreshBulkBar() {
        const count = selectedCollectionIds.size;
        const bar = document.getElementById('bulkVerifyBar');
        const badge = document.getElementById('selectedCountBadge');
        if (!bar) return;
        if (count > 0 && isAdminOrStaff) {
            bar.classList.remove('d-none');
            bar.classList.add('d-flex');
            badge.textContent = count + ' selected';
        } else {
            bar.classList.add('d-none');
            bar.classList.remove('d-flex');
        }
    }

    // Re-attach checkbox events after each DataTable draw
    if (dt_collections_table) {
        dt_collections_table.addEventListener('draw.dt', function () {
            // Restore checked state for already-selected IDs
            dt_collections_table.querySelectorAll('.collection-checkbox').forEach(cb => {
                cb.checked = selectedCollectionIds.has(cb.dataset.id);
            });
            // Update select-all header checkbox
            const allChecks = dt_collections_table.querySelectorAll('.collection-checkbox');
            const selectAll = document.getElementById('selectAllCollections');
            if (selectAll) selectAll.checked = allChecks.length > 0 && [...allChecks].every(c => c.checked);
        });

        // Row checkbox click
        dt_collections_table.addEventListener('change', function (e) {
            const cb = e.target.closest('.collection-checkbox');
            if (!cb) return;
            if (cb.checked) {
                selectedCollectionIds.add(cb.dataset.id);
            } else {
                selectedCollectionIds.delete(cb.dataset.id);
            }
            refreshBulkBar();
        });
    }

    // Select-all header checkbox
    const selectAllCb = document.getElementById('selectAllCollections');
    if (selectAllCb) {
        selectAllCb.addEventListener('change', function () {
            const checkboxes = dt_collections_table ? dt_collections_table.querySelectorAll('.collection-checkbox') : [];
            checkboxes.forEach(cb => {
                cb.checked = selectAllCb.checked;
                if (selectAllCb.checked) {
                    selectedCollectionIds.add(cb.dataset.id);
                } else {
                    selectedCollectionIds.delete(cb.dataset.id);
                }
            });
            refreshBulkBar();
        });
    }

    // Clear selection button
    const btnClear = document.getElementById('btnClearSelection');
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            selectedCollectionIds.clear();
            if (dt_collections_table) {
                dt_collections_table.querySelectorAll('.collection-checkbox').forEach(cb => cb.checked = false);
            }
            const selectAll = document.getElementById('selectAllCollections');
            if (selectAll) selectAll.checked = false;
            refreshBulkBar();
        });
    }

    // Open Bulk Verify Modal
    const btnBulkVerify = document.getElementById('btnBulkVerify');
    if (btnBulkVerify) {
        btnBulkVerify.addEventListener('click', function () {
            document.getElementById('bulkVerifyCount').textContent = selectedCollectionIds.size;
            document.getElementById('bulkVerifyRemarks').value = '';
            const modal = new bootstrap.Modal(document.getElementById('bulkVerifyModal'));
            modal.show();
        });
    }

    // Confirm Bulk Verify
    const btnConfirmBulkVerify = document.getElementById('btnConfirmBulkVerify');
    if (btnConfirmBulkVerify) {
        btnConfirmBulkVerify.addEventListener('click', function () {
            if (selectedCollectionIds.size === 0) return;

            const spinner = document.getElementById('bulkVerifySpinner');
            btnConfirmBulkVerify.disabled = true;
            spinner.classList.remove('d-none');

            const remarks = document.getElementById('bulkVerifyRemarks').value;

            fetch(`${baseUrl}app/agents/agent-collections/bulk-verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    collection_ids: [...selectedCollectionIds],
                    remarks: remarks
                })
            })
            .then(res => res.json())
            .then(data => {
                btnConfirmBulkVerify.disabled = false;
                spinner.classList.add('d-none');

                bootstrap.Modal.getInstance(document.getElementById('bulkVerifyModal')).hide();

                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? 'Bulk Verify Complete!' : 'Error',
                    text: data.message,
                    customClass: { confirmButton: 'btn btn-' + (data.success ? 'success' : 'danger') }
                }).then(() => {
                    selectedCollectionIds.clear();
                    refreshBulkBar();
                    if (dt_collections) {
                        dt_collections.ajax.reload(null, false);
                    }
                    refreshAgentStats();
                });
            })
            .catch(err => {
                btnConfirmBulkVerify.disabled = false;
                spinner.classList.add('d-none');
                console.error('Bulk verify error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Bulk verify failed. Please try again.' });
            });
        });
    }
});
