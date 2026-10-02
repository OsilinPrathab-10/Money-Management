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
    let dt_chit_collections = null;
    let chitTableInitialized = false;
    let activeCollectionTab = 'loan';
    let clickedAssignButton = null;
    const isAgentUser = window.isAgentUser === true || window.userRole === 'Agent';
    const canVerifyCollections = window.canVerifyCollections === true
        || window.userRole === 'Admin'
        || window.userRole === 'Staff'
        || (Array.isArray(window.userRoles) && window.userRoles.some(r => r === 'Admin' || r === 'Staff'));

    const AGENT_BANK_PAYMENT_GROUPS = [
        {
            methodSelectId: 'payment_method',
            bankSelectId: 'add_internal_bank_account_id',
            bankContainerId: 'addBankContainer',
            qrContainerId: 'addQrContainer',
            qrBankNameId: 'addQrBankName',
            qrUpiIdId: 'addQrUpiId',
            qrImageWrapperId: 'addQrImageWrapper',
            bankTransferContainerId: 'addBankTransferContainer',
            bankTransferContentId: 'addBankTransferContent'
        },
        {
            methodSelectId: 'bulkPaymentMethod',
            bankSelectId: 'bulk_internal_bank_account_id',
            bankContainerId: 'bulkBankContainer',
            qrContainerId: 'bulkQrContainer',
            qrBankNameId: 'bulkQrBankName',
            qrUpiIdId: 'bulkQrUpiId',
            qrImageWrapperId: 'bulkQrImageWrapper',
            bankTransferContainerId: 'bulkBankTransferContainer',
            bankTransferContentId: 'bulkBankTransferContent'
        }
    ];

    if (window.BankPaymentFields) {
        window.BankPaymentFields.initGroups(AGENT_BANK_PAYMENT_GROUPS);
    }

    function getCollectionFilterParams() {
        const params = {
            status: $('#filterStatus').val(),
            method: $('#filterMethod').val(),
            start_date: $('#filterStartDate').val(),
            end_date: $('#filterEndDate').val()
        };
        if ($('#filterCollector').length) {
            params.collector = $('#filterCollector').val();
        }
        if ($('#filterAgent').length) {
            params.agent_id = $('#filterAgent').val();
        }
        return params;
    }

    function syncDateFilterClearButton() {
        const hasDates = !!($('#filterStartDate').val() || $('#filterEndDate').val());
        $('#btnClearDateFilter').toggleClass('d-none', !hasDates);
    }

    function reloadCollectionTables() {
        if (dt_collections) {
            dt_collections.draw();
        }
        if (dt_chit_collections) {
            dt_chit_collections.draw();
        }
        refreshAgentStats();
    }

    function loanEmiNumberList(full) {
        if (Array.isArray(full.emi_numbers) && full.emi_numbers.length) {
            return full.emi_numbers.filter(function (n) {
                return n !== null && n !== undefined && n !== '';
            });
        }

        const raw = String(full.emi_split || full.emi_id || '');
        const matches = raw.match(/\d+/g);
        return matches ? matches.filter(function (n, i, arr) { return arr.indexOf(n) === i; }) : [];
    }

    function formatLoanEmiIdHtml(full) {
        const numbers = loanEmiNumberList(full);
        const lines = [];

        if (numbers.length) {
            for (let i = 0; i < numbers.length; i += 5) {
                const chunk = numbers.slice(i, i + 5).map(function (n) {
                    return '#' + n;
                }).join(', ');
                lines.push('EMI ' + chunk);
            }
        } else {
            lines.push(full.emi_split || full.emi_id || 'N/A');
        }

        // <br> is required: table/DataTables text-nowrap ignores CSS wrap but still honors breaks.
        const linesHtml = lines.join('<br>');

        if (full.is_bulk || full.is_grouped) {
            const splits = encodeURIComponent(JSON.stringify(full.emi_splits || []));
            return '<div class="emi-id-wrap d-flex align-items-center gap-1">' +
                '<button type="button" class="btn btn-icon btn-text-info btn-sm rounded-pill view-bulk-emis" data-splits="' + splits + '" data-label="EMI" data-title="EMI details" title="View EMIs">' +
                '<i class="icon-base ri ri-eye-line icon-22px"></i></button>' +
                '</div>';
        }

        return '<div class="emi-id-wrap">' + linesHtml + '</div>';
    }

    function switchCollectionTab(tab) {
        activeCollectionTab = tab;
        const isLoan = tab === 'loan';

        document.querySelectorAll('.loan-only-stat').forEach(el => {
            el.classList.toggle('d-none', !isLoan);
        });
        document.querySelectorAll('.chit-only-stat').forEach(el => {
            el.classList.toggle('d-none', isLoan);
        });

        const titleEl = document.getElementById('activeCollectionTitle');
        if (titleEl) {
            if (isAgentUser) {
                titleEl.textContent = isLoan ? 'My Loan Collections' : 'My Chit Collections';
            } else {
                titleEl.textContent = isLoan ? 'Loan Collections' : 'Chit Collections';
            }
        }

        if (!isLoan && !chitTableInitialized) {
            initChitCollectionsTable();
        } else if (!isLoan && dt_chit_collections) {
            dt_chit_collections.columns.adjust();
        }
    }

    // Bind Bootstrap tab switch event listener for collection type tabs
    const collectionTypeTabsEl = document.getElementById('collectionTypeTabs');
    if (collectionTypeTabsEl) {
        collectionTypeTabsEl.addEventListener('shown.bs.tab', function (e) {
            const targetId = e.target.id;
            if (targetId === 'chit-collections-tab') {
                switchCollectionTab('chit');
            } else {
                switchCollectionTab('loan');
            }
        });
    }

    // Auto-switch tab if tab=chit is passed in URL
    const urlTabParam = new URLSearchParams(window.location.search).get('tab');
    if (urlTabParam === 'chit') {
        const chitTabBtn = document.getElementById('chit-collections-tab');
        if (chitTabBtn) {
            const tabTrigger = new bootstrap.Tab(chitTabBtn);
            tabTrigger.show();
            switchCollectionTab('chit');
        }
    }

    function refreshAgentStats() {
        const agentId = $('#filterAgent').val() || '';
        const startDate = $('#filterStartDate').val() || '';
        const endDate = $('#filterEndDate').val() || '';
        fetch(`${baseUrl}app/agents/agent-collections/stats?agent_id=${agentId}&start_date=${startDate}&end_date=${endDate}`)
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

                    const elChitCount = document.getElementById('stat-chit-count') || document.getElementById('chit-tab-count');
                    if (elChitCount) elChitCount.textContent = data.chitCollectedCount ?? 0;
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
                    action: 'collection',
                    collection_type: activeCollectionTab
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
        placeholder: 'Select client or search by name, account or phone',
        minimumInputLength: 0,
        allowClear: true,
        width: '100%',
        dropdownParent: $('#addCollectionModal')
    });

    $('#addCollectionModal').on('shown.bs.modal', function () {
        $('#emiSearchSelect').select2('open');
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
                        Object.assign(d, getCollectionFilterParams());
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
                    { data: 'agent_name', visible: !isAgentUser },
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
                            if (isPending && canVerifyCollections) {
                                const verifyId = full.record_id || full.id;
                                return `<div class="d-flex align-items-center gap-1"><input type="checkbox" class="collection-checkbox" data-id="${verifyId}"><span>${num}</span></div>`;
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
                        visible: !isAgentUser,
                        render: function (data, type, full, meta) {
                            const label = full.agent_name || 'Admin';
                            const badgeClass = full.collector_type === 'agent' ? 'bg-label-primary' : 'bg-label-success';
                            return `<span class="badge ${badgeClass}">${label}</span>`;
                        }
                    },
                    {
                        targets: 3, // EMI ID — 5 loan EMIs per line
                        className: 'emi-id-cell',
                        width: '240px',
                        render: function (data, type, full) {
                            if (type !== 'display') {
                                return full.emi_split || full.emi_id || '';
                            }
                            return formatLoanEmiIdHtml(full);
                        }
                    },
                    {
                        targets: 4, // Amount
                        render: function (data, type, full) {
                            const amountHtml = `<span class="fw-semibold">₹${parseFloat(full.amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                            return amountHtml;
                        }
                    },
                    {
                        targets: 5, // Method
                        className: 'text-center',
                        render: function (data, type, full, meta) {
                            const method = (full.payment_method || '').toLowerCase();
                            const isAdmin = !isAgentUser && full.collector_type === 'admin';
                            const methodMap = {
                                in_hand:      { label: isAdmin ? 'Admin In-Hand' : (isAgentUser ? 'In-Hand' : 'Agent In-Hand'), color: isAdmin ? 'success' : 'primary', icon: 'ri-hand-coin-line' },
                                 upi:          { label: isAdmin ? 'Admin UPI'           : (isAgentUser ? 'UPI' : 'Agent UPI'),           color: isAdmin ? 'success' : 'info',    icon: 'ri-qr-code-line' },
                                bank_transfer:{ label: isAdmin ? 'Admin Bank Transfer' : (isAgentUser ? 'Bank Transfer' : 'Agent Bank Transfer'), color: isAdmin ? 'warning' : 'warning', icon: 'ri-bank-line' },
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
                            let actions = `<a href="${baseUrl}app/agents/agent-collections/${full.id}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill view-collection" data-id="${full.id}" title="View Details"><i class="icon-base ri ri-eye-line icon-22px"></i></a>`;
                            
                            // [NEW] History Button
                            actions += `<button class="btn btn-icon btn-text-info btn-sm rounded-pill view-history" data-emi-id="${full.real_emi_id}" title="Payment History Breakdown"><i class="icon-base ri ri-history-line icon-22px"></i></button>`;

                            // Add verify button for pending collections - ADMIN/STAFF ONLY
                            if ((full.status === 'pending' || full.status === 'in_progress') && canVerifyCollections) {
                                const verifyId = full.record_id || full.id;
                                actions += `<button type="button" class="btn btn-icon btn-text-success btn-sm rounded-pill verify-collection" data-id="${verifyId}" data-type="loan" title="Approve / Reject Collection"><i class="icon-base ri ri-check-double-line icon-22px"></i></button>`;
                            }
                            
                            return `<div class="d-flex align-items-center gap-1">${actions}</div>`;
                        }
                    },
                {
                    targets: [0, 1, 2, 4, 5, 6, 7, 8, 9],
                    className: 'text-nowrap'
                },
                {
                    targets: 3,
                    className: 'emi-id-cell'
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
            createdRow: function (row) {
                const cell = row.querySelector('td.emi-id-cell');
                if (cell) {
                    cell.classList.remove('text-nowrap');
                }
            },
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
    }

    function initChitCollectionsTable() {
        const chitTable = document.querySelector('.datatables-chit-collections');
        if (!chitTable || chitTableInitialized) {
            return;
        }

        chitTableInitialized = true;
        dt_chit_collections = new DataTable(chitTable, {
            processing: true,
            serverSide: true,
            ajax: {
                url: baseUrl + 'app/agents/agent-collections/chit-list',
                data: function (d) {
                    Object.assign(d, getCollectionFilterParams());
                },
                dataSrc: function (json) {
                    if (typeof json.recordsTotal !== 'number') json.recordsTotal = 0;
                    if (typeof json.recordsFiltered !== 'number') json.recordsFiltered = 0;
                    json.data = Array.isArray(json.data) ? json.data : [];
                    return json.data;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'client_name' },
                { data: 'agent_name', visible: !isAgentUser },
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
                    searchable: false,
                    orderable: false,
                    targets: 0,
                    render: function (data, type, full, meta) {
                        const num = meta.row + meta.settings._iDisplayStart + 1;
                        const isPending = (full.status === 'in_progress' || full.status === 'pending');
                        if (isPending && canVerifyCollections) {
                            const collectionId = full.chit_collection_id || ('chit_' + full.id);
                            return `<div class="d-flex align-items-center gap-1"><input type="checkbox" class="collection-checkbox" data-id="${collectionId}"><span>${num}</span></div>`;
                        }
                        return `<span>${num}</span>`;
                    }
                },
                {
                    targets: 1,
                    render: function (data, type, full) {
                        return `<span>${full.client_name || 'N/A'}</span>`;
                    }
                },
                {
                    targets: 2,
                    visible: !isAgentUser,
                    render: function (data, type, full) {
                        const label = full.agent_name || 'Admin';
                        const badgeClass = full.collector_type === 'agent' ? 'bg-label-primary' : 'bg-label-success';
                        return `<span class="badge ${badgeClass}">${label}</span>`;
                    }
                },
                {
                    targets: 3,
                    render: function (data, type, full) {
                        const emiLabel = full.emi_split || full.emi_id || 'N/A';
                        if (full.is_bulk || full.is_grouped) {
                            const splits = encodeURIComponent(JSON.stringify(full.emi_splits || []));
                            return `<button type="button" class="btn btn-icon btn-text-info btn-sm rounded-pill view-bulk-emis" data-splits="${splits}" data-label="Inst" data-title="Installment details" title="View Installments"><i class="icon-base ri ri-eye-line icon-22px"></i></button>`;
                        }
                        return `<span class="badge bg-label-info">${emiLabel}</span>`;
                    }
                },
                {
                    targets: 4,
                    render: function (data, type, full) {
                        const amountHtml = `<span class="fw-semibold">₹${parseFloat(full.amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                            return amountHtml;
                    }
                },
                {
                    targets: 5,
                    className: 'text-center',
                    render: function (data, type, full) {
                        const method = (full.payment_method || '').toLowerCase();
                        const isAdmin = !isAgentUser && full.collector_type === 'admin';
                        const methodMap = {
                            in_hand: { label: isAdmin ? 'Admin In-Hand' : (isAgentUser ? 'In-Hand' : 'Agent In-Hand'), color: isAdmin ? 'success' : 'primary', icon: 'ri-hand-coin-line' },
                            cash: { label: isAdmin ? 'Admin Cash' : (isAgentUser ? 'Cash' : 'Agent Cash'), color: isAdmin ? 'success' : 'primary', icon: 'ri-hand-coin-line' },
                            upi: { label: isAdmin ? 'Admin UPI' : (isAgentUser ? 'UPI' : 'Agent UPI'), color: isAdmin ? 'success' : 'info', icon: 'ri-qr-code-line' },
                            bank_transfer: { label: isAdmin ? 'Admin Bank Transfer' : (isAgentUser ? 'Bank Transfer' : 'Agent Bank Transfer'), color: isAdmin ? 'warning' : 'warning', icon: 'ri-bank-line' },
                            wallet: { label: 'Wallet', color: 'info', icon: 'ri-wallet-3-line' }
                        };
                        const methodConfig = methodMap[method] || { label: method || 'Unknown', color: 'secondary', icon: 'ri-question-line' };
                        return `<span class="badge bg-label-${methodConfig.color}"><i class="icon-base ${methodConfig.icon} me-1"></i>${methodConfig.label}</span>`;
                    }
                },
                {
                    targets: 6,
                    className: 'text-center',
                    render: function (data, type, full) {
                        const paymentType = (full.payment_type || '').toLowerCase();
                        const typeMap = {
                            partial: { label: 'Partial', color: 'warning' },
                            full: { label: 'Full', color: 'success' }
                        };
                        const typeConfig = typeMap[paymentType] || { label: paymentType, color: 'secondary' };
                        return `<span class="badge bg-label-${typeConfig.color}">${typeConfig.label}</span>`;
                    }
                },
                {
                    targets: 7,
                    className: 'text-center',
                    render: function (data, type, full) {
                        const status = (full.status || '').toLowerCase();
                        const statusMap = {
                            verified: { label: 'Paid', color: 'success' },
                            paid: { label: 'Paid', color: 'success' },
                            partial: { label: 'Partial', color: 'warning' },
                            in_progress: { label: 'Pending Verification', color: 'warning' },
                            pending: { label: 'Pending Verification', color: 'warning' },
                            rejected: { label: 'Rejected', color: 'danger' }
                        };
                        const statusConfig = statusMap[status] || { label: status, color: 'secondary' };
                        return `<span class="badge bg-label-${statusConfig.color}">${statusConfig.label}</span>`;
                    }
                },
                {
                    targets: 8,
                    render: function (data, type, full) {
                        if (!full.collected_at) return '<span class="text-muted">N/A</span>';
                        const d = new Date(full.collected_at);
                        const date = `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`;
                        const time = d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
                        return `<span class="d-block">${date}</span><small class="text-muted">${time}</small>`;
                    }
                },
                {
                    targets: -1,
                    searchable: false,
                    orderable: false,
                    render: function (data, type, full) {
                        let targetUrl = full.view_url;
                        if (!targetUrl || targetUrl.includes('chit/installments')) {
                            targetUrl = full.group_id
                                ? `${baseUrl}groups/${full.group_id}`
                                : `${baseUrl}installments`;
                        }

                        let actions = `<a href="${targetUrl}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill view-collection" data-url="${targetUrl}" title="View Details">
                                <i class="icon-base ri ri-eye-line icon-22px"></i>
                            </a>`;

                        if (full.chit_collection_id && (full.status === 'pending' || full.status === 'in_progress') && canVerifyCollections) {
                            const chitVerifyId = full.chit_collection_id || full.record_id || full.id;
                            actions += `<button type="button" class="btn btn-icon btn-text-success btn-sm rounded-pill verify-collection" data-id="${chitVerifyId}" data-type="chit" title="Approve / Reject Collection"><i class="icon-base ri ri-check-double-line icon-22px"></i></button>`;
                        }

                        return `<div class="d-flex align-items-center gap-1">${actions}</div>`;
                    }
                },
                { targets: '_all', className: 'text-nowrap' }
            ],
            order: [[8, 'desc']],
            layout: {
                topStart: {
                    rowClass: 'row m-3 my-0 justify-content-between',
                    features: [{ pageLength: { menu: [7, 10, 20, 50, 70, 100], text: '_MENU_' } }]
                },
                topEnd: {
                    features: [{ search: { placeholder: 'Search Chit Collections', text: '_INPUT_' } }]
                },
                bottomStart: {
                    rowClass: 'row mx-3 justify-content-between',
                    features: [{ info: { text: 'Showing _START_ to _END_ of _TOTAL_ entries' } }]
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
            autoWidth: false
        });
    }

        document.querySelectorAll('#collectionTypeTabs [data-bs-toggle="tab"]').forEach(tabEl => {
            tabEl.addEventListener('shown.bs.tab', function (event) {
                const targetId = event.target.getAttribute('data-bs-target');
                switchCollectionTab(targetId === '#chitCollectionsPane' ? 'chit' : 'loan');
            });
        });

        // Filter change events
        $('#filterStatus, #filterCollector, #filterMethod, #filterAgent, #filterStartDate, #filterEndDate').on('change', function () {
            syncDateFilterClearButton();
            reloadCollectionTables();
        });

        $('#btnClearDateFilter').on('click', function () {
            $('#filterStartDate').val('');
            $('#filterEndDate').val('');
            var preset = document.getElementById('agentCollectionDatePreset');
            if (preset) {
                preset.value = '';
            }
            var root = document.querySelector('[data-preset-id="agentCollectionDatePreset"]');
            if (root) {
                var custom = root.querySelector('[data-custom-range]');
                if (custom) {
                    custom.style.display = 'none';
                }
            }
            syncDateFilterClearButton();
            reloadCollectionTables();
        });

        syncDateFilterClearButton();

        // View Collection Details
        document.addEventListener('click', function (e) {
            const viewBtn = e.target.closest('.view-collection');
            if (!viewBtn) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const targetUrl = viewBtn.dataset.url
                || (viewBtn.dataset.id ? `${baseUrl}app/agents/agent-collections/${viewBtn.dataset.id}` : '');
            if (targetUrl) {
                window.location.href = targetUrl;
            }
        });

        // Verify Collection — stay on this page; do not follow any href
        document.addEventListener('click', function (e) {
            const verifyBtn = e.target.closest('.verify-collection');
            if (!verifyBtn) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const collectionId = verifyBtn.dataset.id;
            if (!collectionId) {
                return;
            }

            const dtrModal = document.querySelector('.dtr-bs-modal.show');
            if (dtrModal) {
                const bsModal = bootstrap.Modal.getInstance(dtrModal);
                if (bsModal) bsModal.hide();
            }

            document.getElementById('verifyCollectionId').value = collectionId;
            const typeInput = document.getElementById('verifyCollectionType');
            if (typeInput) {
                typeInput.value = verifyBtn.dataset.type
                    || (String(collectionId).startsWith('chit_') ? 'chit' : 'loan');
            }

            const verifyModal = new bootstrap.Modal(document.getElementById('verifyCollectionModal'));
            verifyModal.show();
        });

        // Handle Verify Form Submission (approve or reject, stay on collections list)
        const verifyCollectionForm = document.getElementById('verifyCollectionForm');
        const submitSingleVerify = function (status, submitBtn) {
            if (!verifyCollectionForm) {
                return;
            }

            const statusInput = verifyCollectionForm.querySelector('[name="status"]');
            if (statusInput) {
                statusInput.value = status;
            }

            const allActionBtns = verifyCollectionForm.querySelectorAll('#verifyApproveBtn, #verifyRejectBtn, [data-verify-status]');
            if (submitBtn && submitBtn.disabled) {
                return;
            }
            allActionBtns.forEach(btn => { btn.disabled = true; });
            if (submitBtn) {
                submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = status === 'rejected'
                    ? '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting...'
                    : '<span class="spinner-border spinner-border-sm me-1"></span>Approving...';
            }

            const formData = new FormData(verifyCollectionForm);
            let collectionId = String(formData.get('collection_id') || document.getElementById('verifyCollectionId')?.value || '').trim();
            const collectionType = String(formData.get('collection_type') || document.getElementById('verifyCollectionType')?.value || '').trim();
            if (collectionType === 'chit' && collectionId && !collectionId.startsWith('chit_')) {
                collectionId = 'chit_' + collectionId;
            }

            formData.set('collection_id', collectionId);
            formData.set('collection_type', collectionType || (collectionId.startsWith('chit_') ? 'chit' : 'loan'));
            formData.set('status', status);

            const restoreSubmit = () => {
                allActionBtns.forEach(btn => { btn.disabled = false; });
                if (submitBtn) {
                    submitBtn.innerHTML = submitBtn.dataset.originalHtml || (status === 'rejected' ? 'Reject' : 'Approve');
                }
            };

            if (!collectionId) {
                restoreSubmit();
                Swal.fire({ icon: 'error', title: 'Verification Failed', text: 'Collection id is missing.' });
                return;
            }

            fetch(`${baseUrl}app/agents/agent-collections/verify-one`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(response => response.json().catch(() => ({
                    success: false,
                    message: 'Verification failed. Please try again.'
                })))
                .then(data => {
                    restoreSubmit();
                    const verifyModalInstance = bootstrap.Modal.getInstance(document.getElementById('verifyCollectionModal'));
                    if (verifyModalInstance) verifyModalInstance.hide();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'Collection updated successfully',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            }
                        }).then(() => {
                            if (dt_collections) {
                                dt_collections.ajax.reload(null, false);
                            }
                            if (dt_chit_collections) {
                                dt_chit_collections.ajax.reload(null, false);
                            }
                            refreshAgentStats();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Verification Failed',
                            text: data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Failed to verify collection',
                            customClass: {
                                confirmButton: 'btn btn-danger'
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    restoreSubmit();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to verify collection',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        }
                    });
                });
        };

        if (!window.__agentCollectionVerifyBound) {
            window.__agentCollectionVerifyBound = true;
            if (verifyCollectionForm) {
                verifyCollectionForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const submitter = e.submitter;
                    const status = (submitter && submitter.dataset.verifyStatus) || (this.querySelector('[name="status"]') || {}).value || 'verified';
                    submitSingleVerify(status, submitter && submitter.type === 'submit' ? submitter : null);
                });
            }

            const verifyApproveBtn = document.getElementById('verifyApproveBtn');
            const verifyRejectBtn = document.getElementById('verifyRejectBtn');
            if (verifyApproveBtn) {
                verifyApproveBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    submitSingleVerify('verified', this);
                });
            }
            if (verifyRejectBtn) {
                verifyRejectBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    submitSingleVerify('rejected', this);
                });
            }
        }

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
                
                const methodSelectEl = document.getElementById('payment_method');
                const bankSelectEl = document.getElementById('add_internal_bank_account_id');
                const bankValidationError = window.BankPaymentFields
                    ? window.BankPaymentFields.validateBankPayment(methodSelectEl, bankSelectEl, this)
                    : null;
                if (bankValidationError) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    Swal.fire({
                        icon: 'error',
                        title: 'Bank Account Required',
                        text: bankValidationError,
                        customClass: { confirmButton: 'btn btn-danger' }
                    });
                    return;
                }

                let formData;
                try {
                    formData = window.BankPaymentFields
                        ? window.BankPaymentFields.prepareFormData(this, methodSelectEl, bankSelectEl)
                        : new FormData(this);
                } catch (err) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: err.message || 'Please check payment details.',
                        customClass: { confirmButton: 'btn btn-danger' }
                    });
                    return;
                }

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
                        if (window.BankPaymentFields) {
                            window.BankPaymentFields.resetToInHand('payment_method');
                        }
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

                            const companySlogan = d.company_slogan || 'Codepluse Gen PVT Ltd';

                            if (!msgText || !waMsgText) {
                                let fallbackMsgText = '';
                                if (isKandhuvatti) {
                                    if (paymentType === 'principal') {
                                        fallbackMsgText = `Dear ${clientName},\nYour Principal payment of ₹${amountPaid} towards ${companySlogan} Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
                                    } else {
                                        if (isPartial) {
                                            fallbackMsgText = `Dear ${clientName},\nYour Partial Interest payment of ₹${amountPaid} towards ${companySlogan} Open Loan Account ${accountNo} has been received successfully.\nBalance Interest to pay: ₹${emiBalance}.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
                                        } else {
                                            fallbackMsgText = `Dear ${clientName},\nYour Interest payment of ₹${amountPaid} towards ${companySlogan} Open Loan Account ${accountNo} has been received successfully.\nRemaining Principal Balance: ₹${remainingBalance}.\nThank you!`;
                                        }
                                    }
                                } else {
                                    if (isPartial) {
                                        fallbackMsgText = `Dear ${clientName},\nYour Partial EMI payment of ₹${amountPaid} towards ${companySlogan} Loan Account ${accountNo} has been received successfully.\nBalance EMI to pay: ₹${emiBalance}.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
                                    } else {
                                        fallbackMsgText = `Dear ${clientName},\nYour EMI payment of ₹${amountPaid} towards ${companySlogan} Loan Account ${accountNo} has been received successfully.\nOutstanding Balance: ₹${remainingBalance}.\nThank you!`;
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
                                            <button type="button" id="swal-payment-wa-btn" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #25D366; border-color: #25D366; color: white; font-weight: 500;">
                                                <i class="ri-whatsapp-line fs-5"></i> Send WhatsApp Confirmation
                                            </button>
                                            
                                            <button type="button" id="swal-payment-sms-btn" class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #0088cc; border-color: #0088cc; color: white; font-weight: 500;">
                                                <i class="ri-message-3-line fs-5"></i> Send Native SMS
                                            </button>
                                        </div>
                                    </div>
                                `,
                                showCancelButton: false,
                                showCloseButton: true,
                                confirmButtonText: 'Done & Close',
                                customClass: {
                                    confirmButton: 'btn btn-primary px-5 mt-3'
                                },
                                didOpen: (popup) => {
                                    popup.querySelector('#swal-payment-wa-btn')?.addEventListener('click', (e) => {
                                        e.preventDefault();
                                        window.open(waUrl, '_blank', 'noopener,noreferrer');
                                    });
                                    popup.querySelector('#swal-payment-sms-btn')?.addEventListener('click', (e) => {
                                        e.preventDefault();
                                        window.location.href = smsUrl;
                                    });
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

        // Agent Bulk Collect
        const bulkCollectModalEl = document.getElementById('bulkCollectModal');
        const bulkCollectForm = document.getElementById('bulkCollectForm');
        if (isAgentUser && bulkCollectModalEl && bulkCollectForm) {
            const selectedBulkDues = new Map();
            let bulkDuePage = 1;
            let bulkDueLastPage = 1;
            let currentBulkRows = [];

            const formatBulkCurrency = value => '₹' + Number(value || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            const escapeBulkHtml = value => $('<div>').text(value == null ? '' : String(value)).html();

            const refreshBulkCollectSummary = () => {
                let total = 0;
                selectedBulkDues.forEach(item => {
                    total += Number(item.amount || 0);
                });
                document.getElementById('bulkSelectedCount').textContent = `${selectedBulkDues.size} selected`;
                document.getElementById('bulkSelectedTotal').textContent = formatBulkCurrency(total);
                document.getElementById('btnSubmitBulkCollect').disabled = selectedBulkDues.size === 0;

                const selectable = currentBulkRows.filter(row => !row.disabled);
                const selectAll = document.getElementById('bulkSelectAll');
                selectAll.checked = selectable.length > 0 && selectable.every(row => selectedBulkDues.has(row.id));
                selectAll.indeterminate = selectable.some(row => selectedBulkDues.has(row.id)) && !selectAll.checked;
            };

            const renderBulkDues = rows => {
                const body = document.getElementById('bulkDuesBody');
                currentBulkRows = rows;

                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No assigned dues found.</td></tr>';
                    refreshBulkCollectSummary();
                    return;
                }

                body.innerHTML = rows.map(row => {
                    const selected = selectedBulkDues.get(row.id);
                    const amount = selected ? selected.amount : row.amount;
                    const disabledReason = row.disabled_reason ? `<div class="small text-danger">${escapeBulkHtml(row.disabled_reason)}</div>` : '';
                    return `
                        <tr class="${row.disabled ? 'table-light' : ''}">
                            <td>
                                <input class="form-check-input bulk-due-checkbox" type="checkbox"
                                    data-id="${escapeBulkHtml(row.id)}" ${selected ? 'checked' : ''} ${row.disabled ? 'disabled' : ''}>
                            </td>
                            <td>
                                <span class="badge bg-label-${row.type === 'loan' ? 'primary' : 'info'} me-1">${row.type === 'loan' ? 'Loan' : 'Chit'}</span>
                                <span class="fw-medium">${escapeBulkHtml(row.label)}</span>
                                ${disabledReason}
                            </td>
                            <td>${escapeBulkHtml(row.client_name)}</td>
                            <td>${escapeBulkHtml(row.account_or_group)}</td>
                            <td>${escapeBulkHtml(row.due_date || '-')}</td>
                            <td>
                                <input type="number" class="form-control form-control-sm bulk-due-amount"
                                    data-id="${escapeBulkHtml(row.id)}" value="${Number(amount).toFixed(2)}"
                                    min="0.01" max="${Number(row.amount).toFixed(2)}" step="0.01"
                                    ${row.disabled || !selected ? 'disabled' : ''}>
                                <div class="small text-muted mt-1">Max ${formatBulkCurrency(row.amount)}</div>
                            </td>
                        </tr>`;
                }).join('');

                refreshBulkCollectSummary();
            };

            const renderBulkPagination = pagination => {
                bulkDuePage = pagination.page;
                bulkDueLastPage = pagination.last_page;
                const holder = document.getElementById('bulkDuePagination');
                holder.innerHTML = `
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="bulkDuePrev" ${bulkDuePage <= 1 ? 'disabled' : ''}>Previous</button>
                    Page ${bulkDuePage} of ${bulkDueLastPage} (${pagination.total} dues)
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="bulkDueNext" ${bulkDuePage >= bulkDueLastPage ? 'disabled' : ''}>Next</button>`;
            };

            const loadBulkDues = (page = 1) => {
                const body = document.getElementById('bulkDuesBody');
                body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading assigned dues...</td></tr>';

                const params = new URLSearchParams({
                    type: document.getElementById('bulkDueType').value,
                    q: document.getElementById('bulkDueSearch').value.trim(),
                    page: page,
                    per_page: 25
                });

                fetch(`${baseUrl}app/agents/agent-collections/assigned-dues?${params.toString()}`, {
                    headers: { Accept: 'application/json' }
                })
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok || !data.success) throw data;
                        renderBulkDues(data.data || []);
                        renderBulkPagination(data.pagination);
                    })
                    .catch(error => {
                        body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeBulkHtml(error.message || 'Unable to load assigned dues.')}</td></tr>`;
                    });
            };

            document.getElementById('btnOpenBulkCollect')?.addEventListener('click', () => {
                selectedBulkDues.clear();
                bulkDuePage = 1;
                document.getElementById('bulkDueSearch').value = '';
                document.getElementById('bulkDueType').value = 'all';
                refreshBulkCollectSummary();
                bootstrap.Modal.getOrCreateInstance(bulkCollectModalEl).show();
                loadBulkDues(1);
            });

            document.getElementById('btnSearchBulkDues').addEventListener('click', () => loadBulkDues(1));
            document.getElementById('bulkDueType').addEventListener('change', () => loadBulkDues(1));
            document.getElementById('bulkDueSearch').addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    loadBulkDues(1);
                }
            });

            document.getElementById('bulkDuePagination').addEventListener('click', event => {
                if (event.target.id === 'bulkDuePrev' && bulkDuePage > 1) loadBulkDues(bulkDuePage - 1);
                if (event.target.id === 'bulkDueNext' && bulkDuePage < bulkDueLastPage) loadBulkDues(bulkDuePage + 1);
            });

            document.getElementById('bulkSelectAll').addEventListener('change', function () {
                currentBulkRows.filter(row => !row.disabled).forEach(row => {
                    if (this.checked) {
                        selectedBulkDues.set(row.id, { ...row, amount: Number(row.amount) });
                    } else {
                        selectedBulkDues.delete(row.id);
                    }
                });
                renderBulkDues(currentBulkRows);
            });

            document.getElementById('bulkDuesBody').addEventListener('change', event => {
                const checkbox = event.target.closest('.bulk-due-checkbox');
                if (checkbox) {
                    const row = currentBulkRows.find(item => item.id === checkbox.dataset.id);
                    if (!row) return;
                    if (checkbox.checked) {
                        selectedBulkDues.set(row.id, { ...row, amount: Number(row.amount) });
                    } else {
                        selectedBulkDues.delete(row.id);
                    }
                    renderBulkDues(currentBulkRows);
                    return;
                }

                const amountInput = event.target.closest('.bulk-due-amount');
                if (amountInput && selectedBulkDues.has(amountInput.dataset.id)) {
                    const row = currentBulkRows.find(item => item.id === amountInput.dataset.id);
                    let amount = Number(amountInput.value);
                    amount = Math.min(Math.max(amount, 0.01), Number(row.amount));
                    amountInput.value = amount.toFixed(2);
                    selectedBulkDues.set(amountInput.dataset.id, { ...selectedBulkDues.get(amountInput.dataset.id), amount });
                    refreshBulkCollectSummary();
                }
            });

            bulkCollectForm.addEventListener('submit', event => {
                event.preventDefault();
                if (!selectedBulkDues.size) return;

                const methodSelectEl = document.getElementById('bulkPaymentMethod');
                const bankSelectEl = document.getElementById('bulk_internal_bank_account_id');
                const bankValidationError = window.BankPaymentFields
                    ? window.BankPaymentFields.validateBankPayment(methodSelectEl, bankSelectEl, bulkCollectForm)
                    : null;
                if (bankValidationError) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Bank Account Required',
                        text: bankValidationError,
                        customClass: { confirmButton: 'btn btn-danger' }
                    });
                    return;
                }

                const button = document.getElementById('btnSubmitBulkCollect');
                const original = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

                const paymentMethod = methodSelectEl?.value || '';
                let bankAccountId = bankSelectEl?.value || null;
                if (paymentMethod === 'in_hand' || paymentMethod === 'cash') {
                    bankAccountId = null;
                }

                const payload = {
                    collected_at: document.getElementById('bulkCollectedAt').value,
                    payment_method: paymentMethod,
                    internal_bank_account_id: bankAccountId,
                    payment_reference: document.getElementById('bulkPaymentReference').value.trim() || null,
                    remarks: document.getElementById('bulkRemarks').value.trim() || null,
                    items: [...selectedBulkDues.values()].map(item => ({ id: item.id, amount: Number(item.amount) }))
                };

                fetch(`${baseUrl}app/agents/agent-collections/bulk-store`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        Accept: 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok || !data.success) throw data;
                        bootstrap.Modal.getInstance(bulkCollectModalEl)?.hide();
                        bulkCollectForm.reset();
                        if (window.BankPaymentFields) {
                            window.BankPaymentFields.resetToInHand('bulkPaymentMethod');
                        }
                        document.getElementById('bulkCollectedAt').value = new Date().toISOString().slice(0, 10);
                        selectedBulkDues.clear();
                        reloadCollectionTables();
                        Swal.fire({
                            icon: 'success',
                            title: 'Collections Submitted',
                            text: data.message,
                            customClass: { confirmButton: 'btn btn-success' }
                        });
                    })
                    .catch(error => {
                        let message = error.message || 'Bulk collection failed.';
                        if (error.errors) {
                            const firstErrors = Object.values(error.errors).flat();
                            if (firstErrors.length) message = firstErrors[0];
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Unable to Submit',
                            text: message,
                            customClass: { confirmButton: 'btn btn-danger' }
                        });
                    })
                    .finally(() => {
                        button.disabled = selectedBulkDues.size === 0;
                        button.innerHTML = original;
                    });
            });
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.view-bulk-emis');
            if (!btn) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            let splits = [];
            const raw = btn.getAttribute('data-splits') || '';
            try {
                splits = JSON.parse(decodeURIComponent(raw));
            } catch (err) {
                try {
                    splits = JSON.parse(raw);
                } catch (err2) {
                    splits = [];
                }
            }
            if (!Array.isArray(splits)) {
                splits = [];
            }

            const label = btn.getAttribute('data-label') || 'EMI';
            const title = btn.getAttribute('data-title') || (label + ' details');
            const formatAmount = function (amount) {
                return '₹' + Number(amount || 0).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };

            let html = '';
            let total = 0;
            if (splits.length) {
                splits.forEach(function (split) {
                    const amount = Number(split.amount || 0);
                    total += amount;
                    html += '<tr><td>' + label + ' #' + (split.instalment_number ?? 'N/A') +
                        '</td><td class="text-end">' + formatAmount(amount) + '</td></tr>';
                });
                html += '<tr class="fw-semibold"><td>Total</td><td class="text-end">' + formatAmount(total) + '</td></tr>';
            } else {
                html = '<tr><td colspan="2" class="text-center text-muted py-3">No ' + label + ' details found.</td></tr>';
            }

            const titleEl = document.getElementById('bulkEmiModalTitle');
            const labelEl = document.getElementById('bulkEmiModalLabel');
            const bodyEl = document.getElementById('bulkEmiModalBody');
            if (titleEl) titleEl.textContent = title;
            if (labelEl) labelEl.textContent = label;
            if (bodyEl) bodyEl.innerHTML = html;

            const modalEl = document.getElementById('bulkEmiModal');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });

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

    // ──────────────────────────────────────────────
    //  BULK VERIFY  — checkbox selection & action
    // ──────────────────────────────────────────────

    const isAdminOrStaff = canVerifyCollections;
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

    function bindTableCheckboxEvents(tableEl, selectAllId) {
        if (!tableEl) return;

        tableEl.addEventListener('draw.dt', function () {
            tableEl.querySelectorAll('.collection-checkbox').forEach(cb => {
                cb.checked = selectedCollectionIds.has(cb.dataset.id);
            });
            const allChecks = tableEl.querySelectorAll('.collection-checkbox');
            const selectAll = document.getElementById(selectAllId);
            if (selectAll) selectAll.checked = allChecks.length > 0 && [...allChecks].every(c => c.checked);
        });

        tableEl.addEventListener('change', function (e) {
            const cb = e.target.closest('.collection-checkbox');
            if (!cb) return;
            if (cb.checked) {
                selectedCollectionIds.add(cb.dataset.id);
            } else {
                selectedCollectionIds.delete(cb.dataset.id);
            }
            refreshBulkBar();
        });

        const selectAllCb = document.getElementById(selectAllId);
        if (selectAllCb) {
            selectAllCb.addEventListener('change', function () {
                const checkboxes = tableEl.querySelectorAll('.collection-checkbox');
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
    }

    bindTableCheckboxEvents(dt_collections_table, 'selectAllCollections');
    bindTableCheckboxEvents(document.querySelector('.datatables-chit-collections'), 'selectAllChitCollections');

    // Clear selection button
    const btnClear = document.getElementById('btnClearSelection');
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            selectedCollectionIds.clear();
            document.querySelectorAll('.collection-checkbox').forEach(cb => cb.checked = false);
            ['selectAllCollections', 'selectAllChitCollections'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.checked = false;
            });
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
                    if (dt_chit_collections) {
                        dt_chit_collections.ajax.reload(null, false);
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
