/**
 * EMI Repayments DataTable and Interactions
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const rawBaseUrl = window.baseUrl || document.documentElement.getAttribute('data-base-url') || window.location.origin || '';
  const baseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl : rawBaseUrl + '/';
  let table;
  let emiDetailsModal;
  let emiDetailsBody;
  let emiDetailsTitle;
  let emiScheduleLink;
  let emiPrintReceiptLink;
  let selectedEmis = new Map();
  let updateBulkPaymentState;

  // Apply dashboard / deep-link query params before DataTable loads
  const urlParams = new URLSearchParams(window.location.search);
  const urlStatus = urlParams.get('status');
  const urlFromDate = urlParams.get('from_date');
  const urlToDate = urlParams.get('to_date');
  const urlTermUnit = urlParams.get('term_unit');
  const urlLoanMode = urlParams.get('loan_mode');

  if (urlStatus && ['overdue', 'pending', 'upcoming', 'partial', 'paid'].includes(urlStatus)) {
    $('#statusFilter').val(urlStatus);
    $('#repaymentsTabs button').removeClass('active');
    $(`#repaymentsTabs button[data-status="${urlStatus}"]`).addClass('active');
    const titles = {
      overdue: 'Overdue Clients',
      pending: 'Pending Clients (This Month)',
      upcoming: 'Upcoming Clients',
      partial: 'Partial Paid Clients',
      paid: 'Paid Clients',
    };
    $('#tableTitle').text(titles[urlStatus] || 'EMI Repayments List');
  }
  if (urlFromDate) {
    $('#fromDateFilter').val(urlFromDate);
  }
  if (urlToDate) {
    $('#toDateFilter').val(urlToDate);
  }
  if (urlTermUnit) {
    $('#termUnitFilter').val(urlTermUnit);
  }
  if (urlLoanMode) {
    $('#loanModeFilter').val(urlLoanMode);
  }

  const sanitizeExportValue = value => {
    if (value == null || value === false) return '';
    if (typeof value === 'object') {
      if (Array.isArray(value)) {
        return value.map(sanitizeExportValue).filter(Boolean).join(', ');
      }
      if (typeof value.text === 'string' || typeof value.text === 'number') {
        return sanitizeExportValue(value.text);
      }
      return '';
    }
    let text = String(value);
    text = text.replace(/<script[\s\S]*?<\/script>/gi, ' ');
    text = text.replace(/<style[\s\S]*?<\/style>/gi, ' ');
    text = text.replace(/<[^>]+>/g, ' ');
    text = text.replace(/&nbsp;/gi, ' ').replace(/&#?\w+;/g, ' ');
    text = text.replace(/₹/g, 'Rs. ');
    return text.replace(/\s+/g, ' ').trim();
  };

  const repaymentExportTitle = () => {
    const status = ($('#statusFilter').val() || 'overdue').toLowerCase();
    const titles = {
      overdue: 'Overdue Loans',
      pending: 'Pending Loans',
      upcoming: 'Upcoming Loans',
      partial: 'Partial Paid Loans',
      paid: 'Paid Loans'
    };
    return titles[status] || 'EMI Repayments';
  };

  const statusSummaryExportText = full => {
    const parts = [];
    [
      ['overdue_count', 'Overdue'],
      ['pending_count', 'Pending'],
      ['upcoming_count', 'Upcoming'],
      ['partial_count', 'Partial'],
      ['paid_count', 'Paid']
    ].forEach(([key, label]) => {
      const count = parseInt(full?.[key], 10) || 0;
      if (count > 0) {
        parts.push(count + ' ' + label);
      }
    });
    return parts.join(', ');
  };

  const exportColumnOptions = {
    columns: [1, 2, 3, 4, 5, 6, 7, 8],
    orthogonal: 'export',
    stripHtml: true,
    format: {
      body: sanitizeExportValue
    }
  };

  const statusSectionConfig = [
    { key: 'overdue', label: 'Overdue', color: 'danger' },
    { key: 'pending', label: 'Pending', color: 'warning' },
    { key: 'upcoming', label: 'Upcoming', color: 'secondary' },
    { key: 'partial', label: 'Partial Paid', color: 'info' },
    { key: 'paid', label: 'Paid', color: 'success' },
  ];

  const renderEmiActionButtons = (emi, full) => {
    const publicToken = emi.application_number ? btoa(emi.application_number) : '';
    const publicLink = publicToken ? `${window.location.origin}/view-schedule/${publicToken}` : '#';
    const phoneNumber = full.client_phone && full.client_phone !== 'N/A' ? full.client_phone : '';
    const callBtn = phoneNumber
      ? `<a href="tel:${phoneNumber.replace(/\s/g, '')}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Client"><i class="icon-base ri ri-phone-line icon-20px"></i></a>`
      : '';

    let whatsappBtn = '';
    let smsBtn = '';
    const status = emi?.status || '';
    const paidAmount = parseFloat(emi?.paid_amount_raw || 0);
    const balance = parseFloat(emi?.balance_raw || emi?.balance || 0);
    const amountDue = balance > 0 ? balance : parseFloat(emi?.amount_raw || emi?.amount || 0);
    const clientName = emi?.client_name || full?.client_name || 'Client';
    const isPartialPayment = status === 'partial' || (paidAmount > 0 && status !== 'paid');

    if (phoneNumber && phoneNumber !== 'N/A') {
      let cleanPhone = phoneNumber.replace(/\D/g, '');
      if (cleanPhone.length === 10) {
        cleanPhone = '91' + cleanPhone;
      }
      const linkPart = publicLink !== '#' ? `\n\nPlease check your EMI Schedule here: ${publicLink}` : '';
      const companySlogan = emi.company_slogan || full.company_slogan || 'Codepluse Gen PVT Ltd';
      const companyPhone = emi.company_phone || full.company_phone || '';
      const accountNo = emi.account_number || full.account_number || '';
      const period = emi.period_label || ('EMI Month ' + (emi.month_number || ''));
      const dueDate = emi.due_date_formatted || emi.due_date || '';
      const paidDate = emi.paid_date_formatted || emi.paid_date || '';

      let waMessage = '';
      let smsMessage = '';
      let waTitle = 'Send WhatsApp Message';
      let smsTitle = 'Send SMS Message';

      if (status === 'paid' || isPartialPayment) {
        if (isPartialPayment) {
          waTitle = 'Send WhatsApp Partial Payment Confirmation';
          smsTitle = 'Send SMS Partial Payment Confirmation';
          waMessage = `Dear Customer, a partial payment of *Rs.${paidAmount}* for your EMI on *Loan Account No: ${accountNo}* has been successfully received on *${paidDate}*.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
          smsMessage = `Dear Customer, a partial payment of Rs.${paidAmount} for your EMI on Loan Account No: ${accountNo} has been successfully received on ${paidDate}.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
        } else {
          waTitle = 'Send WhatsApp Payment Confirmation';
          smsTitle = 'Send SMS Payment Confirmation';
          waMessage = `Dear Customer, your EMI for *Loan Account No: ${accountNo}* of *Rs.${paidAmount}* has been successfully paid on *${paidDate}*.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
          smsMessage = `Dear Customer, your EMI for Loan Account No: ${accountNo} of Rs.${paidAmount} has been successfully paid on ${paidDate}.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
        }
      } else if (status === 'overdue') {
        waTitle = 'Send Overdue Reminder via WhatsApp';
        smsTitle = 'Send Overdue Reminder via SMS';
        waMessage = `Dear *${clientName}*, your EMI payment of *Rs.${amountDue}* for *Loan Account No: ${accountNo}* (${period}) was due on *${dueDate}*. Please clear your overdue EMI at the earliest to avoid penalty and credit score impact.${linkPart}\n\nThank you, ${companySlogan}. For queries: ${companyPhone}.`;
        smsMessage = `Dear ${clientName}, your EMI payment of Rs.${amountDue} for Loan Account No: ${accountNo} (${period}) was due on ${dueDate}. Please clear your overdue EMI at the earliest to avoid penalty.${linkPart}\n\nThank you, ${companySlogan}.`;
      } else {
        waTitle = 'Send Pending Reminder via WhatsApp';
        smsTitle = 'Send Pending Reminder via SMS';
        waMessage = `Dear *${clientName}*, your upcoming EMI payment of *Rs.${amountDue}* for *Loan Account No: ${accountNo}* (${period}) is due on *${dueDate}*. Kindly arrange payment on or before the due date.${linkPart}\n\nThank you, ${companySlogan}. For queries: ${companyPhone}.`;
        smsMessage = `Dear ${clientName}, your upcoming EMI payment of Rs.${amountDue} for Loan Account No: ${accountNo} (${period}) is due on ${dueDate}. Kindly arrange payment on or before the due date.${linkPart}\n\nThank you, ${companySlogan}.`;
      }

      const encodedWaMsg = encodeURIComponent(waMessage);
      whatsappBtn = `<a href="https://wa.me/${cleanPhone}?text=${encodedWaMsg}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-success" title="${waTitle}"><i class="icon-base ri ri-whatsapp-line icon-20px"></i></a>`;

      const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
      const smsSeparator = isIOS ? '&' : '?';
      const encodedSmsMsg = encodeURIComponent(smsMessage);
      smsBtn = `<a href="sms:+${cleanPhone}${smsSeparator}body=${encodedSmsMsg}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info" title="${smsTitle}"><i class="icon-base ri ri-message-3-line icon-20px"></i></a>`;
    }

    return (
      '<div class="d-flex align-items-center gap-1 text-nowrap">' +
      `<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-view-emi" data-emi-id="${emi.id}" data-application-number="${emi.application_number || full.application_number || ''}" title="View EMI Details">` +
      '<i class="icon-base ri ri-eye-line icon-20px"></i>' +
      '</button>' +
      `<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-copy-public-link" data-link="${publicLink}" title="Copy Public Schedule Link">` +
      '<i class="icon-base ri ri-link icon-20px"></i>' +
      '</button>' +
      callBtn +
      whatsappBtn +
      smsBtn +
      '</div>'
    );
  };

  const formatClientEmiDetails = (clientRow) => {
    const grouped = clientRow.emis_grouped || {};
    const activeStatus = $('#statusFilter').val();
    const bulkSelectableStatuses = ['overdue', 'pending', 'partial', 'upcoming', 'paid'];
    const showCheckbox = window.isAdmin && bulkSelectableStatuses.includes(activeStatus);

    let html = '<div class="client-emi-details">';

    statusSectionConfig.forEach(({ key, label, color }) => {
      const emis = grouped[key] || [];
      if (!emis.length) return;

      html += `<div class="emi-section-title text-${color}">${label} (${emis.length})</div>`;
      html += '<div class="table-responsive mb-3"><table class="table table-sm table-bordered align-middle mb-0">';
      html += '<thead class="table-light"><tr>';
      if (showCheckbox) {
        html += '<th style="width:30px;"></th>';
      }
      html += '<th>Inst. #</th><th>Due Date</th><th>EMI Amt</th><th>Interest</th><th>Principal</th><th>Paid</th><th>Pending</th><th>Status</th><th>Actions</th>';
      html += '</tr></thead><tbody>';

      emis.forEach((emi) => {
        const checkboxAmount = emi.status === 'paid'
          ? (parseFloat(emi.paid_amount_raw) || parseFloat(emi.total_amount) || 0)
          : (parseFloat(emi.pending_amount) || parseFloat(emi.total_amount) || 0);
        html += `<tr data-emi-id="${emi.id}" data-instalment="${emi.instalment_number}" data-total="${emi.total_amount_formatted}">`;
        if (showCheckbox) {
          html += `<td class="text-center"><input type="checkbox" class="form-check-input emi-select-checkbox align-middle" data-id="${emi.id}" data-amount="${checkboxAmount}" data-client="${clientRow.client_name}" data-account="${clientRow.account_number}"></td>`;
        }
        html += `<td class="fw-semibold">#${emi.instalment_number}</td>`;
        html += `<td>${emi.due_date}</td>`;
        html += `<td>${emi.total_amount_formatted}</td>`;
        html += `<td>${emi.interest_amount_formatted}</td>`;
        html += `<td>${emi.principal_amount_formatted}</td>`;
        html += `<td>${emi.paid_amount}</td>`;
        html += `<td>${emi.pending_amount_formatted}</td>`;
        html += `<td>${emi.status_badge}</td>`;
        html += `<td>${renderEmiActionButtons(emi, clientRow)}</td>`;
        html += '</tr>';
      });

      html += '</tbody></table></div>';
    });

    if (!clientRow.emis || clientRow.emis.length === 0) {
      html += '<p class="text-muted mb-0">No EMI details available for this client.</p>';
    }

    html += '</div>';
    return html;
  };

  // Initialize DataTable
  if ($('#repaymentsTable').length) {
    table = $('#repaymentsTable').DataTable({
      processing: true,
      serverSide: true,
      scrollX: true,
      autoWidth: false,
      ajax: {
        url: baseUrl + 'emi/repayments/data',
        type: 'GET',
        data: function (d) {
          d.status = $('#statusFilter').val();
          d.from_date = $('#fromDateFilter').val();
          d.to_date = $('#toDateFilter').val();
          d.location_id = $('#areaFilter').val();
          d.account_number = $('#accountNumberFilter').val();
          d.term_unit = $('#termUnitFilter').val();
          d.loan_mode = $('#loanModeFilter').val();
          d.loan_type_id = $('#loanTypeFilter').val();
        }
      },
      columns: [
        {
          className: 'details-control',
          orderable: false,
          searchable: false,
          data: null,
          defaultContent: '<i class="icon-base ri ri-arrow-right-s-line"></i>'
        },
        {
          data: 'sno',
          defaultContent: '',
          render: function (data, type, full, meta) {
            return data || (meta.settings._iDisplayStart + meta.row + 1);
          }
        },
        {
          data: 'account_number',
          render: function (data, type, full) {
            const systemAccountNumber = data || 'N/A';
            const displayNumber = full.customer_loan_account_number || systemAccountNumber;
            if (type === 'export') {
              return displayNumber;
            }
            if (!full.loan_account_id) {
              return `<span class="text-muted">${displayNumber}</span>`;
            }
            const scheduleTarget = full.application_number
              ? `${baseUrl}emi/repayments/view/${full.application_number}`
              : `${baseUrl}loan/loan-account/${full.loan_account_id}`;
            if (full.customer_loan_account_number) {
              return `<div class="d-flex flex-column">
                        <a href="${scheduleTarget}" class="text-primary fw-semibold loan-account-link">${full.customer_loan_account_number}</a>
                        <small class="text-muted" style="font-size: 0.75rem;">Sys ID: ${systemAccountNumber}</small>
                      </div>`;
            }
            return `<a href="${scheduleTarget}" class="text-primary fw-semibold loan-account-link">${systemAccountNumber}</a>`;
          }
        },
        { data: 'client_name' },
        {
          data: 'agent_name',
          render: function (data, type) {
            const label = data || 'Unassigned';
            if (type === 'export') {
              return label;
            }
            return `<span class="badge bg-label-info">${label}</span>`;
          }
        },
        {
          data: 'client_phone',
          render: function (data, type) {
            if (!data || data === 'N/A') return type === 'export' ? '' : (data || '');
            if (type === 'export') {
              return data;
            }
            return `<a href="tel:${data.replace(/\s/g, '')}" class="text-body">${data}</a>`;
          }
        },
        {
          data: 'zone',
          render: function (data, type) {
            const label = data || 'N/A';
            if (type === 'export') {
              return label;
            }
            return '<span class="badge bg-label-secondary">' + label + '</span>';
          }
        },
        {
          data: 'status_summary',
          orderable: false,
          searchable: false,
          render: function (data, type, full) {
            if (type === 'export') {
              return statusSummaryExportText(full) || sanitizeExportValue(data);
            }
            return data;
          }
        },
        {
          data: 'total_due_formatted',
          render: function (data, type, full) {
            if (type === 'export') {
              return data || '';
            }
            if (full.total_due > 0) {
              return `<span class="fw-semibold text-danger">${data}</span>`;
            }
            return `<span class="text-muted">${data}</span>`;
          }
        },
        {
          data: null,
          defaultContent: '',
          searchable: false,
          orderable: false,
          render: function (data, type, full) {
            if (type === 'export') {
              return '';
            }
            const publicToken = full.application_number ? btoa(full.application_number) : '';
            const publicLink = publicToken ? `${window.location.origin}/view-schedule/${publicToken}` : '#';
            const phoneNumber = full.client_phone && full.client_phone !== 'N/A' ? full.client_phone : '';
            const callBtn = phoneNumber
              ? `<a href="tel:${phoneNumber.replace(/\s/g, '')}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Client"><i class="icon-base ri ri-phone-line icon-20px"></i></a>`
              : '';
            const scheduleTarget = full.application_number
              ? `${baseUrl}emi/repayments/view/${full.application_number}`
              : `${baseUrl}loan/loan-account/${full.loan_account_id}`;

            return (
              '<div class="d-flex align-items-center gap-1 text-nowrap">' +
              `<a href="${scheduleTarget}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="View Full Schedule"><i class="icon-base ri ri-file-list-3-line icon-20px"></i></a>` +
              `<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-copy-public-link" data-link="${publicLink}" title="Copy Public Schedule Link">` +
              '<i class="icon-base ri ri-link icon-20px"></i>' +
              '</button>' +
              callBtn +
              '</div>'
            );
          }
        }
      ],
      order: [
        [8, 'desc']
      ],
 dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"lB>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      pageLength: 25,
      lengthMenu: [
        [25, 50, 100, 150, 300],
        [25, 50, 100, 150, 300]
      ],
      language: {
        sLengthMenu: 'Show _MENU_',
        search: '',
        searchPlaceholder: 'Search clients...',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
        }
      },
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-label-secondary dropdown-toggle me-4',
          text: '<i class="icon-base ri ri-download-line me-2"></i>Export',
          buttons: [
            {
              extend: 'print',
              text: '<i class="icon-base ri ri-printer-line me-2"></i>Print',
              className: 'dropdown-item',
              title: repaymentExportTitle,
              exportOptions: exportColumnOptions,
              customize: function (win) {
                $(win.document.body)
                  .css('font-size', '10pt')
                  .prepend('<h3 class="text-center">' + repaymentExportTitle() + '</h3>');

                $(win.document.body)
                  .find('table')
                  .addClass('compact')
                  .css('font-size', 'inherit');
              }
            },
            {
              extend: 'csv',
              text: '<i class="icon-base ri ri-file-text-line me-2"></i>CSV',
              className: 'dropdown-item',
              title: repaymentExportTitle,
              exportOptions: exportColumnOptions
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-2-line me-2"></i>Excel',
              className: 'dropdown-item',
              title: repaymentExportTitle,
              exportOptions: exportColumnOptions
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              title: repaymentExportTitle,
              orientation: 'landscape',
              pageSize: 'A4',
              exportOptions: exportColumnOptions,
              customize: function (doc) {
                const generatedDate = new Date().toLocaleString();
                const title = repaymentExportTitle();
                doc.pageMargins = [20, 40, 20, 40];
                doc.defaultStyle.fontSize = 9;
                doc.styles.tableHeader.fontSize = 10;
                doc.styles.tableHeader.fillColor = '#eef1f5';
                doc.styles.tableHeader.color = '#111111';
                doc.styles.tableHeader.alignment = 'left';

                if (doc.content?.length) {
                  doc.content[0].text = title;
                  doc.content[0].alignment = 'center';
                  doc.content[0].margin = [0, 0, 0, 12];
                }

                const tableContent = doc.content?.find(item => item.table);
                if (tableContent) {
                  const table = tableContent.table;
                  table.widths = ['6%', '14%', '16%', '12%', '12%', '10%', '18%', '12%'];
                  table.body.forEach((row, rowIndex) => {
                    row.forEach((cell, colIndex) => {
                      let text = '';
                      if (cell == null) {
                        text = '';
                      } else if (typeof cell === 'string' || typeof cell === 'number' || typeof cell === 'boolean') {
                        text = String(cell);
                      } else if (typeof cell === 'object' && (typeof cell.text === 'string' || typeof cell.text === 'number')) {
                        text = String(cell.text);
                      } else {
                        text = sanitizeExportValue(cell);
                      }
                      const cellObj = {
                        text: text,
                        margin: [4, 3, 4, 3],
                        alignment: colIndex === 7 ? 'right' : 'left'
                      };
                      if (rowIndex === 0) {
                        cellObj.fillColor = '#eef1f5';
                        cellObj.color = '#111111';
                        cellObj.bold = true;
                      }
                      row[colIndex] = cellObj;
                    });
                  });
                }

                doc.footer = function (currentPage, pageCount) {
                  return {
                    columns: [
                      {
                        text: 'Generated on ' + generatedDate,
                        alignment: 'left',
                        margin: [20, 0, 0, 0]
                      },
                      {
                        text: 'Page ' + currentPage + ' of ' + pageCount,
                        alignment: 'right',
                        margin: [0, 0, 20, 0]
                      }
                    ],
                    fontSize: 8
                  };
                };
              }
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
              className: 'dropdown-item',
              title: repaymentExportTitle,
              exportOptions: exportColumnOptions
            }
          ]
        }
      ],
      autoWidth: false,
    });

    // Expand/collapse client rows to show EMI details
    $('#repaymentsTable tbody').on('click', 'td.details-control', function () {
      const tr = $(this).closest('tr');
      const row = table.row(tr);

      if (row.child.isShown()) {
        row.child.hide();
        tr.removeClass('shown');
      } else {
        row.child(formatClientEmiDetails(row.data())).show();
        tr.addClass('shown');
        if (window.isAdmin && typeof updateBulkPaymentState === 'function') {
          syncExpandedRowCheckboxes(tr);
        }
      }
    });

    const syncExpandedRowCheckboxes = (parentTr) => {
      const childRow = parentTr.next('tr');
      childRow.find('.emi-select-checkbox').each(function () {
        const id = $(this).data('id');
        if (selectedEmis.has(id) || selectedEmis.has(String(id))) {
          $(this).prop('checked', true);
        }
      });
    };

    $(window).on('resize', function() {
      table.columns.adjust();
    });

    // Listen to Ajax completion and update statistics cards dynamically
    table.on('xhr.dt', function (e, settings, json, xhr) {
      if (json && json.stats) {
        const stats = json.stats;
        
        // Helper to format currency
        const formatCurrency = (val) => {
          return '₹' + parseFloat(val).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        };

        // Update Total EMIs
        $('#stat-total-emis').text(parseFloat(stats.total_emis).toLocaleString('en-IN'));
        
        // Update Paid EMIs
        $('#stat-paid-emis').text(parseFloat(stats.paid_emis).toLocaleString('en-IN'));
        $('#stat-total-collected').text(formatCurrency(stats.total_collected));
        
        // Update Pending EMIs
        $('#stat-pending-emis').text(parseFloat(stats.pending_emis).toLocaleString('en-IN'));
        $('#stat-total-pending').text(formatCurrency(stats.total_pending));
        
        // Update Overdue EMIs
        $('#stat-overdue-emis').text(parseFloat(stats.overdue_emis).toLocaleString('en-IN'));

        // Update tab badges
        $('#tab-count-overdue').text(parseFloat(stats.overdue_emis).toLocaleString('en-IN'));
        $('#tab-count-pending').text(parseFloat(stats.pending_emis).toLocaleString('en-IN'));
        $('#tab-count-upcoming').text(parseFloat(stats.upcoming_emis || 0).toLocaleString('en-IN'));
        $('#tab-count-partial').text(parseFloat(stats.partial_emis).toLocaleString('en-IN'));
        $('#tab-count-paid').text(parseFloat(stats.paid_emis).toLocaleString('en-IN'));

        // Dynamically update labels based on selected date filters
        if ($('#fromDateFilter').val() || $('#toDateFilter').val()) {
          $('#stat-total-emis-label').text('Selected date range');
        } else {
          $('#stat-total-emis-label').text('Overdue, Pending & Upcoming');
        }
      }
    });

    // Copy public link to clipboard
    $('#repaymentsTable').on('click', '.btn-copy-public-link', function () {
      const link = this.getAttribute('data-link');
      navigator.clipboard.writeText(link).then(() => {
        showAlert('success', 'Copied!', 'Public schedule link copied to clipboard.');
      }).catch(err => {
        console.error('Failed to copy link:', err);
        showAlert('danger', 'Error', 'Failed to copy link.');
      });
    });

    if (window.isAdmin) {
      selectedEmis = new Map(); 

      updateBulkPaymentState = function () {
        const bar = $('#bulkPayBar');
        if (!bar.length) return;

        if (selectedEmis.size === 0) {
          bar.addClass('d-none');
          return;
        }

        bar.removeClass('d-none');

        const activeStatus = $('#statusFilter').val();
        
        // Update bar titles and button visibilities based on tab
        if (activeStatus === 'paid') {
          $('#bulkBarTitle').text('EMIs Selected for Bulk Undo');
          $('#bulkTotalLabel').text('Total Paid:');
          $('#bulkPayBtn').addClass('d-none');
          $('#bulkUndoBtn').removeClass('d-none');
        } else {
          $('#bulkBarTitle').text('EMIs Selected for Bulk Payment');
          $('#bulkTotalLabel').text('Total Due:');
          $('#bulkPayBtn').removeClass('d-none');
          $('#bulkUndoBtn').addClass('d-none');
        }

        // Update count badges
        $('#bulkSelectedCount').text(selectedEmis.size);
        $('#bulkPayBtnCount').text(selectedEmis.size);

        // Compute sums and client-wise breakdown
        let totalSum = 0;
        const clientBreakdowns = {}; // clientName -> { sum: float, count: int }

        selectedEmis.forEach((info) => {
          totalSum += info.amount;
          if (!clientBreakdowns[info.clientName]) {
            clientBreakdowns[info.clientName] = { sum: 0, count: 0 };
          }
          clientBreakdowns[info.clientName].sum += info.amount;
          clientBreakdowns[info.clientName].count += 1;
        });

        // Format total sum
        $('#bulkTotalAmount').text('₹' + totalSum.toLocaleString('en-IN', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }));

        // Render client wise list
        let clientsHtml = '<div class="row g-2">';
        for (const [name, breakdown] of Object.entries(clientBreakdowns)) {
          clientsHtml += `
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded-3 border">
                <span class="fw-semibold text-body small text-truncate" style="max-width: 180px;">${name}</span>
                <div class="text-end">
                  <span class="badge bg-label-secondary small me-1">${breakdown.count} EMI${breakdown.count > 1 ? 's' : ''}</span>
                  <strong class="text-primary small">₹${breakdown.sum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                </div>
              </div>
            </div>
          `;
        }
        clientsHtml += '</div>';

        $('#bulkClientsContainer').html(clientsHtml);
      }

      // Handle Table Draw Event to collapse expanded rows and keep bulk selection bar
      table.on('draw', function () {
        table.rows().every(function () {
          if (this.child.isShown()) {
            this.child.hide();
            $(this.node()).removeClass('shown');
          }
        });
        if (typeof updateBulkPaymentState === 'function') {
          updateBulkPaymentState();
        }
      });

      // Individual Checkbox Click (delegated for expanded EMI rows)
      $('#repaymentsTable').on('change', '.emi-select-checkbox', function () {
        const id = $(this).data('id');
        const amount = parseFloat($(this).data('amount')) || 0;
        const clientName = $(this).data('client') || 'N/A';
        const accountNumber = $(this).data('account') || 'N/A';

        if (this.checked) {
          selectedEmis.set(id, { amount, clientName, accountNumber });
        } else {
          selectedEmis.delete(id);
        }

        updateBulkPaymentState();
      });

      // Cancel Selection
      $(document).on('click', '#bulkCancelBtn', function () {
        selectedEmis.clear();
        $('#repaymentsTable .emi-select-checkbox').prop('checked', false);
        updateBulkPaymentState();
      });

      // Select every EMI matching the active filters, not just the visible page
      $(document).on('click', '#selectAllMatchingEmis', function () {
        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Loading...');

        $.ajax({
          url: baseUrl + 'emi/repayments/all-ids',
          type: 'GET',
          data: {
            status: $('#statusFilter').val(),
            from_date: $('#fromDateFilter').val(),
            to_date: $('#toDateFilter').val(),
            location_id: $('#areaFilter').val(),
            account_number: $('#accountNumberFilter').val(),
            term_unit: $('#termUnitFilter').val(),
            loan_mode: $('#loanModeFilter').val(),
            loan_type_id: $('#loanTypeFilter').val(),
            search: table.search()
          }
        })
          .done(function (res) {
            if (!res || !res.success) {
              showAlert('danger', 'Error', (res && res.message) || 'Could not load matching EMIs.');
              return;
            }

            if (!res.data.length) {
              showAlert('info', 'Nothing to select', 'No EMIs match the current filters.');
              return;
            }

            res.data.forEach(item => {
              selectedEmis.set(item.id, {
                amount: parseFloat(item.amount) || 0,
                clientName: item.client,
                accountNumber: item.account
              });
            });

            $('#repaymentsTable .emi-select-checkbox').each(function () {
              if (selectedEmis.has($(this).data('id'))) {
                $(this).prop('checked', true);
              }
            });

            updateBulkPaymentState();
            showAlert('success', 'Selected', `${res.data.length} EMI(s) selected across all pages.`);
          })
          .fail(function () {
            showAlert('danger', 'Error', 'Could not load matching EMIs.');
          })
          .always(function () {
            btn.prop('disabled', false).html(originalHtml);
          });
      });

      window.syncSelectAllMatchingVisibility = function () {
        const activeStatus = $('#statusFilter').val();
        const canBulkAct = ['overdue', 'pending', 'partial', 'upcoming', 'paid'].includes(activeStatus);
        $('#selectAllMatchingEmis').toggleClass('d-none', !canBulkAct);
        $('#selectAllMatchingEmis').html(activeStatus === 'paid'
          ? '<i class="icon-base ri ri-checkbox-multiple-line me-1"></i>Select all matching paid EMIs'
          : '<i class="icon-base ri ri-checkbox-multiple-line me-1"></i>Select all matching EMIs (all pages)');
      };

      window.syncSelectAllMatchingVisibility();

      // Open Bulk Payment Modal
      $(document).on('click', '#bulkPayBtn', function () {
        if (selectedEmis.size === 0) return;

        let totalSum = 0;
        const summaryItems = [];
        selectedEmis.forEach((info) => {
          totalSum += info.amount;
          summaryItems.push(`<div><strong>${info.accountNumber}</strong> (${info.clientName}): ₹${info.amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`);
        });

        $('#bulkSelectedEmisSummary').html(summaryItems.join(''));
        $('#bulk_paid_amount').val(totalSum.toFixed(2)).data('full-total', totalSum);
        $('#bulkPayTypeFull').prop('checked', true);
        $('#bulk_paid_amount').prop('readonly', true);
        $('#bulkPaidAmountHelp').text('Full payment selected. Total due: ₹' + totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        
        if (window.BankPaymentFields) {
          window.BankPaymentFields.resetToInHand('bulk_payment_method');
        }

        $('#bulkPayBar').addClass('d-none');
        $('#bulkPayModal').modal('show');
      });

      // Handle Full vs Partial radio mode toggle in Bulk Pay Modal
      $(document).on('change', 'input[name="bulk_pay_type"]', function () {
        const mode = $(this).val();
        const fullTotal = parseFloat($('#bulk_paid_amount').data('full-total')) || 0;
        if (mode === 'full') {
          $('#bulk_paid_amount').val(fullTotal.toFixed(2)).prop('readonly', true);
          $('#bulkPaidAmountHelp').text('Full payment selected. Total due: ₹' + fullTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        } else {
          $('#bulk_paid_amount').prop('readonly', false).focus();
          $('#bulkPaidAmountHelp').text('Partial payment mode: enter custom amount to allocate across selected EMIs.');
        }
      });

      // Submit Bulk Payment Form
      $(document).on('submit', '#bulkPayForm', function (e) {
        e.preventDefault();
        if (selectedEmis.size === 0) return;

        const emiIds = Array.from(selectedEmis.keys());
        const methodSelect = document.getElementById('bulk_payment_method');
        const bankSelect = document.getElementById('repaymentBulkBankAccount');

        if (window.BankPaymentFields) {
          const bankValidationError = window.BankPaymentFields.validateBankPayment(methodSelect, bankSelect, this);
          if (bankValidationError) {
            Swal.fire({ icon: 'warning', title: 'Action Required', text: bankValidationError });
            return;
          }
        }

        let formData;
        if (window.BankPaymentFields) {
          formData = window.BankPaymentFields.prepareFormData(this, methodSelect, bankSelect);
        } else {
          formData = new FormData(this);
        }

        emiIds.forEach(id => formData.append('emi_ids[]', id));

        const btn = $('#btnSubmitRepaymentBulkPay');
        const spinner = btn.find('.spinner-border');
        btn.prop('disabled', true);
        spinner.removeClass('d-none');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`${baseUrl}emi/repayments/bulk-pay`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
        .then(response => {
          if (!response.ok) {
            return response.json().then(err => { throw new Error(err.message || 'Bulk payment failed.'); });
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            $('#bulkPayModal').modal('hide');
            Swal.fire({
              title: 'Bulk Payment Successful!',
              text: data.message || 'Selected payments successfully recorded.',
              icon: 'success',
              confirmButtonColor: '#7367f0'
            }).then(() => {
              selectedEmis.clear();
              updateBulkPaymentState();
              table.ajax.reload();
            });
          } else {
            Swal.fire('Error!', data.message || 'Bulk payment failed.', 'error');
          }
        })
        .catch(error => {
          Swal.fire('Error!', error.message || 'Bulk payment failed.', 'error');
        })
        .finally(() => {
          btn.prop('disabled', false);
          spinner.addClass('d-none');
        });
      });

      // Submit Bulk Undo Payment
      $(document).on('click', '#bulkUndoBtn', function () {
        if (selectedEmis.size === 0) return;

        const emiIds = Array.from(selectedEmis.keys());
        let totalSum = 0;
        selectedEmis.forEach(info => totalSum += info.amount);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        Swal.fire({
          title: 'Confirm Bulk Undo Payment',
          text: `You are about to undo payments for all ${selectedEmis.size} selected EMIs. Total amount to be undone is ₹${totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}. This will mark these EMIs as pending/overdue!`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, Undo All!',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#ea5455',
          showLoaderOnConfirm: true,
          preConfirm: () => {
            return fetch(`${baseUrl}emi/repayments/bulk-undo`, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify({ emi_ids: emiIds })
            })
            .then(response => {
              if (!response.ok) {
                return response.json().then(err => { throw new Error(err.message || 'Bulk undo failed.') });
              }
              return response.json();
            })
            .catch(error => {
              Swal.showValidationMessage(`Error: ${error.message}`);
            });
          },
          allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
          if (result.isConfirmed && result.value && result.value.success) {
            Swal.fire({
              title: 'Bulk Undo Successful!',
              text: result.value.message || 'Selected payments successfully undone.',
              icon: 'success',
              confirmButtonColor: '#7367f0'
            }).then(() => {
              selectedEmis.clear();
              updateBulkPaymentState();
              table.ajax.reload();
            });
          }
        });
      });
    }
  }

  // Handle tab clicks to filter by status
  $('#repaymentsTabs button').on('shown.bs.tab', function (e) {
    const status = $(this).attr('data-status');
    $('#statusFilter').val(status);
    
    // Update table title
    const titles = {
      'overdue': 'Overdue Clients',
      'pending': 'Pending Clients (This Month)',
      'upcoming': 'Upcoming Clients',
      'partial': 'Partial Paid Clients',
      'paid': 'Paid Clients'
    };
    $('#tableTitle').text(titles[status] || 'EMI Repayments List');

    if (window.isAdmin && selectedEmis) {
      selectedEmis.clear();
      if (typeof updateBulkPaymentState === 'function') {
        updateBulkPaymentState();
      }
    }

    if (typeof window.syncSelectAllMatchingVisibility === 'function') {
      window.syncSelectAllMatchingVisibility();
    }

    table.ajax.reload();
  });

  // Ensure toolbar filter dropdowns remain clean native selects
  ['#loanModeFilter', '#loanTypeFilter', '#areaFilter'].forEach(function (sel) {
    var $el = $(sel);
    if ($el.length) {
      $el.addClass('no-search');
      if ($el.data('select2')) {
        $el.select2('destroy');
      }
    }
  });

  // Status Filter Change Event
  $('#fromDateFilter, #toDateFilter, #areaFilter, #loanModeFilter, #loanTypeFilter').on('change', function () {
    table.ajax.reload();
  });

  $('#accountNumberFilter').on('keyup change', function () {
    table.ajax.reload();
  });

  $('#resetFilters').on('click', function () {
    $('#statusFilter').val('overdue');
    $('#fromDateFilter').val('');
    $('#toDateFilter').val('');
    $('#areaFilter').val('');
    $('#termUnitFilter').val('');
    $('#loanModeFilter').val('');
    $('#loanTypeFilter').val('');
    $('#accountNumberFilter').val('');
    
    // Reset active tab in UI
    $('#repaymentsTabs button').removeClass('active');
    $('#repaymentsTabs button[data-status="overdue"]').addClass('active');
    $('#tableTitle').text('Overdue Clients');
    
    table.ajax.reload();
  });

  // EMI detail modal setup
  emiDetailsModal = new bootstrap.Modal(document.getElementById('emiDetailsModal'));
  emiDetailsBody = document.getElementById('emiDetailsBody');
  emiDetailsTitle = document.getElementById('emiDetailsTitle');
  emiScheduleLink = document.getElementById('emiScheduleLink');
  emiPrintReceiptLink = document.getElementById('emiPrintReceiptLink');

  $('#repaymentsTable').on('click', '.btn-view-emi', function () {
    const emiId = this.getAttribute('data-emi-id');
    const applicationNumber = this.getAttribute('data-application-number');

    emiDetailsTitle.textContent = `EMI Details - ${applicationNumber}`;
    emiScheduleLink.href = `${baseUrl}emi/repayments/view/${applicationNumber}`;
    if (emiPrintReceiptLink) {
      emiPrintReceiptLink.href = `${baseUrl}emi/receipts/print/${emiId}`;
      emiPrintReceiptLink.classList.add('d-none');
    }
    emiDetailsBody.innerHTML = `
      <div class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
    `;

    emiDetailsModal.show();

    fetch(`${baseUrl}emi/repayments/emi/${emiId}`)
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          throw new Error('Failed to fetch EMI details');
        }

        const emi = data.emi;
        const loan = data.loan;

        if (emiPrintReceiptLink) {
          emiPrintReceiptLink.classList.toggle('d-none', emi.status !== 'paid');
        }

        const formatValue = (value, prefix = '') => (value ? `${prefix}${value}` : '-');
        const statusBadge = `<span class="badge bg-label-${emi.status_color} px-3 py-2">${emi.status_label}</span>`;

        const detailRows = [
          { label: 'Account Number', value: loan.account_number || 'N/A', valueClass: 'text-primary fw-bold' },
          { label: 'Client Name', value: loan.client_name || 'N/A' },
          { label: 'Loan Product', value: loan.product || 'N/A' },
          { label: 'Instalment No.', value: emi.instalment_number },
          { label: 'Due Date', value: emi.due_date || '-' },
          { label: 'EMI Amount', value: formatValue(emi.total_amount, '₹'), valueClass: 'text-primary fw-semibold' },
          { label: 'Status', value: statusBadge, isHtml: true },
          { label: 'Principal Amount', value: formatValue(emi.principal_amount, '₹') },
          { label: 'Interest Amount', value: formatValue(emi.interest_amount, '₹') },
          { label: 'Loan Amount', value: formatValue(loan.loan_amount, '₹') },
          { label: 'Paid Amount', value: emi.paid_amount ? formatValue(emi.paid_amount, '₹') : '-' },
          { label: 'Paid Date', value: emi.paid_date || '-' },
        ];

        if (emi.payment_method) {
          detailRows.push({ label: 'Payment Method', value: emi.payment_method });
        }

        if (emi.payment_reference) {
          detailRows.push({ label: 'Payment Reference', value: emi.payment_reference });
        }

        if (emi.remarks) {
          detailRows.push({ label: 'Remarks', value: emi.remarks });
        }

        const isOverdue = (emi.status || '').toLowerCase() === 'overdue';
        if (isOverdue && (emi.penalty_amount || emi.penalty_date)) {
          detailRows.push({
            label: 'Penalty Amount',
            value: emi.penalty_amount ? formatValue(emi.penalty_amount, '₹') : '-'
          });
          detailRows.push({
            label: 'Penalty Last Date',
            value: emi.penalty_date || '-'
          });
        }

        const tableRows = detailRows
          .map(({ label, value, valueClass = 'fw-semibold', isHtml = false }) => `
            <tr>
              <th scope="row" class="text-uppercase text-muted fw-semibold small" style="width: 45%; min-width: 140px;">${label}</th>
              <td class="${valueClass}">
                ${isHtml ? value : `<span class="text-body">${value}</span>`}
              </td>
            </tr>
          `)
          .join('');

        let undoBtnHtml = '';
        if (data.is_admin && ['paid', 'partial', 'overdue'].includes(emi.status) && emi.paid_amount) {
          const rawPaid = parseFloat(emi.paid_amount.replace(/[^\d.-]/g, ''));
          if (rawPaid > 0) {
            undoBtnHtml = `
              <div class="d-grid mt-4">
                <button type="button" class="btn btn-outline-danger btn-modal-undo-payment" data-emi-id="${emi.id}" data-instalment="${emi.instalment_number}">
                  <i class="icon-base ri ri-history-line me-1"></i> Undo Payment
                </button>
              </div>
            `;
          }
        }

        emiDetailsBody.innerHTML = `
          <div class="card shadow-none border">
            <div class="table-responsive">
              <table class="table table-sm table-borderless align-middle mb-0">
                <tbody>
                  ${tableRows}
                </tbody>
              </table>
            </div>
          </div>
          ${undoBtnHtml}
        `;
      })
      .catch(error => {
        console.error('Failed to load EMI details:', error);
        emiDetailsBody.innerHTML = `
          <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="icon-base ri ri-error-warning-line fs-4 me-2"></i>
            <div>Failed to load EMI details. Please try again.</div>
          </div>
        `;
      });
  });

  // EMI History Popup
  const historyModal = new bootstrap.Modal(document.getElementById('emiHistoryModal'));
  const historyTableBody = document.getElementById('historyTableBody');
  const historyEmiNumber = document.getElementById('historyEmiNumber');
  const historyTotalAmount = document.getElementById('historyTotalAmount');
  const historyPaidAmount = document.getElementById('historyPaidAmount');

  $('#repaymentsTable').on('click', '.emi-history-trigger, .fa-info-circle', function () {
    const emiRow = $(this).closest('tr');
    const emiId = emiRow.data('emi-id');
    const instalmentNumber = emiRow.data('instalment');
    const totalFormatted = emiRow.data('total');

    if (!emiId) return;

    historyEmiNumber.textContent = instalmentNumber || '';
    historyTotalAmount.textContent = totalFormatted || '₹0.00';
    historyPaidAmount.textContent = $(this).closest('td').text().trim() || '₹0.00';

    historyTableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></td></tr>';
    historyModal.show();

    fetch(`${baseUrl}emi/repayments/emi/${emiId}/history`)
      .then(response => response.json())
      .then(data => {
        if (!data.success) throw new Error('Failed to fetch history');
        
        historyPaidAmount.textContent = data.paid_amount;

        const actionHeader = document.querySelector('.history-action-header');
        if (data.is_admin) {
          actionHeader?.classList.remove('d-none');
        } else {
          actionHeader?.classList.add('d-none');
        }

        // Safely handle collections array (may be undefined or not an array)
        const collections = Array.isArray(data.collections) ? data.collections : [];
        
        if (collections.length === 0) {
          const colSpan = data.is_admin ? 6 : 5;
          historyTableBody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-3 text-muted">No payment history found.</td></tr>`;
          return;
        }

        historyTableBody.innerHTML = collections.map(item => {
          let actionCol = '';
          if (data.is_admin) {
            actionCol = `
              <td class="pe-4 text-end">
                <button type="button" class="btn btn-sm btn-icon btn-text-danger btn-delete-collection rounded-pill" data-collection-id="${item.id}" title="Delete Payment Entry">
                  <i class="icon-base ri ri-delete-bin-line icon-20px"></i>
                </button>
              </td>
            `;
          }
          return `
            <tr>
              <td class="ps-4">
                <div class="d-flex flex-column">
                  <span class="fw-medium text-nowrap">${item.date}</span>
                  <small class="text-muted">${item.agent}</small>
                </div>
              </td>
              <td class="fw-bold">${item.amount}</td>
              <td><small class="text-uppercase">${item.method}</small></td>
              <td><small class="text-muted">${item.reference}</small></td>
              <td><span class="badge bg-label-info small">${item.type}</span></td>
              ${actionCol}
            </tr>
          `;
        }).join('');
      })
      .catch(error => {
        console.error('History error:', error);
        historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Failed to load history.</td></tr>';
      });
  });
  
  // Handle Undo Payment from EMI details modal
  document.getElementById('emiDetailsBody').addEventListener('click', function (e) {
    const undoBtn = e.target.closest('.btn-modal-undo-payment');
    if (undoBtn) {
      e.preventDefault();
      
      const emiId = undoBtn.getAttribute('data-emi-id');
      const instalment = undoBtn.getAttribute('data-instalment') || '';

      // Hide the details modal first so it doesn't overlap
      emiDetailsModal.hide();

      Swal.fire({
        title: 'Are you sure?',
        text: `You are about to undo the payment for instalment/cycle #${instalment}. This will reverse all calculated values, restore the balance, and delete related collection entries!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, undo payment!',
        cancelButtonText: 'Cancel',
        input: 'text',
        inputPlaceholder: 'Enter reason to undo...',
        inputAttributes: {
          autocapitalize: 'off'
        },
        preConfirm: (reason) => {
          if (!reason) {
            Swal.showValidationMessage('Please enter a reason to undo the payment.');
            return false;
          }
          return reason;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: 'Undoing Payment...',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          const reason = result.value;

          fetch(`${baseUrl}emi/payment/${emiId}/undo`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reason: reason })
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire({
                  title: 'Success!',
                  text: data.message || 'Payment has been undone successfully.',
                  icon: 'success'
                }).then(() => {
                  window.location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'Failed to undo payment.', 'error');
              }
            })
            .catch(error => {
              console.error('Error undoing payment:', error);
              Swal.fire('Error!', 'An error occurred while undoing the payment.', 'error');
            });
        } else {
          // Re-show the details modal if cancelled
          emiDetailsModal.show();
        }
      });
    }
  });

  // Handle Delete Payment Collection from History modal
  document.getElementById('historyTableBody').addEventListener('click', function (e) {
    const deleteBtn = e.target.closest('.btn-delete-collection');
    if (deleteBtn) {
      e.preventDefault();
      
      const collectionId = deleteBtn.getAttribute('data-collection-id');

      // Hide history modal first
      historyModal.hide();

      Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete this specific payment collection entry. The paid amount on the EMI will be reduced, the loan totals will be recalculated, and the EMI status will be updated accordingly!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete payment entry!',
        cancelButtonText: 'Cancel',
        input: 'text',
        inputPlaceholder: 'Enter reason for deletion...',
        inputAttributes: {
          autocapitalize: 'off'
        },
        preConfirm: (reason) => {
          if (!reason) {
            Swal.showValidationMessage('Please enter a reason for deletion.');
            return false;
          }
          return reason;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: 'Deleting Payment Entry...',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          const reason = result.value;

          fetch(`${baseUrl}emi/collection/${collectionId}/delete`, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken,
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
              _method: 'DELETE',
              reason: reason
            })
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire({
                  title: 'Deleted!',
                  text: data.message || 'Payment entry has been deleted successfully.',
                  icon: 'success'
                }).then(() => {
                  window.location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'Failed to delete payment entry.', 'error');
              }
            })
            .catch(error => {
              console.error('Error deleting collection:', error);
              Swal.fire('Error!', 'An error occurred while deleting the payment entry.', 'error');
            });
        } else {
          // Re-show history modal if cancelled
          historyModal.show();
        }
      });
    }
  });

  // Toast notification function
  function showAlert(type, title, message = '') {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    const toastId = 'toast-' + Date.now();

    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'ri-checkbox-circle-line' : 'ri-error-warning-line';

    const toastHTML = `
      <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0 rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body d-flex align-items-center">
            <i class="icon-base ri ${icon} fs-4 me-2"></i>
            <div>
              <strong>${title}</strong>
              ${message ? `<div class="small">${message}</div>` : ''}
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
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
});
