/**
 * EMI Repayments DataTable and Interactions
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  let dataBaseUrl = document.documentElement.getAttribute('data-base-url');
  let baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl.replace(/\/+$/, '') + '/' : window.location.origin + '/');
  if (window.location.protocol === 'https:' && baseUrl) {
    baseUrl = baseUrl.replace(/^http:/, 'https:');
  }
  let table;
  let emiDetailsModal;
  let emiDetailsBody;
  let emiDetailsTitle;
  let emiScheduleLink;
  let emiPrintReceiptLink;
  let selectedEmis = new Map();
  let updateBulkPaymentState;

  const sanitizeExportValue = value => {
    if (value == null) return '';
    if (typeof value === 'string') {
      return value.replace(/<[^>]*>/g, '').trim();
    }
    return String(value);
  };

  const pdfTitle = document.title ? `EMI Repayments | ${document.title}` : 'EMI Repayments Report';

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
          d.loan_type_id = $('#loanTypeFilter').val();
          d.product_id = $('#productFilter').val();
          d.loan_mode = $('#loanModeFilter').val();
        }
      },
      columns: [
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: function (data, type, full) {
            if (!window.isAdmin) return '';
            const checkboxAmount = full.status === 'paid' ? (parseFloat(full.paid_amount_raw) || parseFloat(full.total_amount) || 0) : (parseFloat(full.total_amount) || 0);
            return `<input type="checkbox" class="form-check-input emi-select-checkbox align-middle" data-id="${full.id}" data-amount="${checkboxAmount}" data-client="${full.client_name}" data-account="${full.account_number}">`;
          }
        },
        { 
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        {
          data: 'account_number',
          render: function (data, type, full) {
            const accountNumber = data || 'N/A';
            if (!full.loan_account_id) {
              return `<span class="text-muted">${accountNumber}</span>`;
            }
            const scheduleTarget = full.application_number
              ? `${baseUrl}emi/repayments/view/${full.application_number}`
              : `${baseUrl}loan/loan-account/${full.loan_account_id}`;
            return `<a href="${scheduleTarget}" class="text-primary fw-semibold loan-account-link">${accountNumber}</a>`;
          }
        },
        { data: 'client_name' },
        { 
          data: 'agent_name',
          render: function (data) {
            return `<span class="badge bg-label-info">${data || 'Unassigned'}</span>`;
          }
        },
        { 
          data: 'client_phone',
          render: function (data, type, full) {
            if (!data || data === 'N/A') return data;
            return `<a href="tel:${data.replace(/\s/g, '')}" class="text-body">${data}</a>`;
          }
        },
        { 
          data: 'zone',
          render: function (data) {
            return '<span class="badge bg-label-secondary">' + (data || 'N/A') + '</span>';
          }
        },
        { data: 'due_date' },
        {
          data: 'total_amount_formatted',
          render: function (data, type, full) {
            const total = full.total_amount_formatted || '₹0.00';
            const interest = full.interest_amount_formatted || '₹0.00';
            const principal = full.principal_amount_formatted || '₹0.00';
            return `
              <div>
                <span class="fw-bold text-dark me-1">${total}</span>
                <div class="text-muted small" style="font-size: 11px;">
                  <span class="text-info">Int:</span> ${interest} &bull; <span class="text-success">Prin:</span> ${principal}
                </div>
              </div>
            `;
          }
        },
        {
          data: 'status_badge',
          orderable: false,
          searchable: false
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            const publicToken = full.application_number ? btoa(full.application_number) : '';
            const publicLink = publicToken ? `${window.location.origin}/view-schedule/${publicToken}` : '#';
            
            // Call Button
            const phoneNumber = full.client_phone && full.client_phone !== 'N/A' ? full.client_phone : '';
            const callBtn = phoneNumber 
              ? `<a href="tel:${phoneNumber}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Client"><i class="icon-base ri ri-phone-line icon-20px"></i></a>` 
              : '';

            // WhatsApp and SMS Buttons (For both PAID and PARTIAL status)
            let whatsappBtn = '';
            let smsBtn = '';
            const isPartialPayment = full.status === 'partial' || (parseFloat(full.paid_amount_raw) > 0 && full.status !== 'paid');
            if ((full.status === 'paid' || isPartialPayment) && phoneNumber) {
              let cleanPhone = phoneNumber.replace(/\D/g, '');
              if (cleanPhone.length === 10) {
                cleanPhone = '91' + cleanPhone;
              }
              const linkPart = publicLink !== '#' ? `\n\nPlease check your EMI Schedule here: ${publicLink}` : '';
              
              let waMessage = '';
              let smsMessage = '';

              if (isPartialPayment) {
                // WhatsApp Message for partial payment (supports bold using asterisks)
                waMessage = `Dear Customer, a partial payment of *Rs.${full.paid_amount_raw}* for your EMI on *Loan Account No: ${full.account_number}* has been successfully received on *${full.paid_date_formatted}*.${linkPart}\n\nThank you for choosing Shanmuga Finance. For queries, contact us at ${full.company_phone}.`;

                // SMS Message for partial payment (standard text without asterisks, without link)
                smsMessage = `Dear Customer, a partial payment of Rs.${full.paid_amount_raw} for your EMI on Loan Account No: ${full.account_number} has been successfully received on ${full.paid_date_formatted}.\n\nThank you for choosing Shanmuga Finance. For queries, contact us at ${full.company_phone}.`;
              } else {
                // WhatsApp Message for full payment (supports bold using asterisks)
                waMessage = `Dear Customer, your EMI for *Loan Account No: ${full.account_number}* of *Rs.${full.paid_amount_raw}* has been successfully paid on *${full.paid_date_formatted}*.${linkPart}\n\nThank you for choosing Shanmuga Finance. For queries, contact us at ${full.company_phone}.`;

                // SMS Message for full payment (standard text without asterisks, without link)
                smsMessage = `Dear Customer, your EMI for Loan Account No: ${full.account_number} of Rs.${full.paid_amount_raw} has been successfully paid on ${full.paid_date_formatted}.\n\nThank you for choosing Shanmuga Finance. For queries, contact us at ${full.company_phone}.`;
              }

              const encodedWaMsg = encodeURIComponent(waMessage);
              whatsappBtn = `<a href="https://wa.me/${cleanPhone}?text=${encodedWaMsg}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-success" title="Send WhatsApp Confirmation"><i class="icon-base ri ri-whatsapp-line icon-20px"></i></a>`;

              const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
              const smsSeparator = isIOS ? '&' : '?';

              const encodedSmsMsg = encodeURIComponent(smsMessage);
              smsBtn = `<a href="sms:+${cleanPhone}${smsSeparator}body=${encodedSmsMsg}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info" title="Send SMS Confirmation"><i class="icon-base ri ri-message-3-line icon-20px"></i></a>`;
            }

            return (
              '<div class="d-flex align-items-center gap-1 text-nowrap">' +
              `<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-view-emi" data-emi-id="${full.id}" data-application-number="${full.application_number}" title="View EMI Details">` +
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
          }
        }
      ],
      columnDefs: [
        {
          targets: 0,
          visible: window.isAdmin,
          orderable: false,
          searchable: false
        },
        {
          targets: 4, // Agent column (shifted from 3 to 4)
          visible: true
        }
      ],
      order: [
        [7, 'desc'] // Order by Due Date Descending (shifted from 6 to 7)
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
      pageLength: 20,
      lengthMenu: [20, 10, 25, 50, 100],
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search EMIs...',
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
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9],
                format: {
                  body: sanitizeExportValue
                }
              },
              customize: function (win) {
                $(win.document.body)
                  .css('font-size', '10pt')
                  .prepend('<h3 class="text-center">EMI Repayments Report</h3>');

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
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9],
                format: {
                  body: sanitizeExportValue
                }
              }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-2-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9],
                format: {
                  body: sanitizeExportValue
                }
              }
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              title: pdfTitle,
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9],
                format: {
                  body: sanitizeExportValue
                }
              },
              customize: function (doc) {
                const generatedDate = new Date().toLocaleString();
                doc.pageMargins = [20, 40, 20, 40];
                doc.defaultStyle.fontSize = 9;
                doc.styles.tableHeader.fontSize = 10;
                doc.styles.tableHeader.fillColor = '#eef1f5';
                doc.styles.tableHeader.color = '#111111';
                doc.styles.tableHeader.alignment = 'left';

                if (doc.content?.length) {
                  doc.content[0].text = pdfTitle;
                  doc.content[0].alignment = 'center';
                  doc.content[0].margin = [0, 0, 0, 12];
                }

                const tableContent = doc.content?.find(item => item.table);
                if (tableContent) {
                  const table = tableContent.table;
                  table.widths = ['5%', '12%', '15%', '12%', '12%', '8%', '11%', '15%', '10%'];
                  table.body.forEach((row, rowIndex) => {
                    row.forEach((cell, colIndex) => {
                      const cellObj = typeof cell === 'object' ? cell : { text: cell };
                      cellObj.margin = [4, 3, 4, 3];
                      // Keep serial left-aligned as required by PDF spec.
                      cellObj.alignment = colIndex === 0 ? 'left' : (colIndex === 7 ? 'right' : 'left');
                      
                      if (rowIndex === 0) {
                        cellObj.fillColor = '#eef1f5';
                        cellObj.color = '#111111';
                        cellObj.bold = true;
                        // Match header alignment to body
                        cellObj.alignment = colIndex === 0 ? 'left' : (colIndex === 7 ? 'right' : 'left');
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
              exportOptions: {
                columns: [1, 2, 3, 4, 5, 6, 7, 8, 9],
                format: {
                  body: sanitizeExportValue
                }
              }
            }
          ]
        }
      ],
      autoWidth: false,
    });

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
        $('#tab-count-all').text(parseFloat(stats.total_emis).toLocaleString('en-IN'));
        $('#tab-count-overdue').text(parseFloat(stats.overdue_emis).toLocaleString('en-IN'));
        $('#tab-count-pending').text(parseFloat(stats.pending_emis).toLocaleString('en-IN'));
        $('#tab-count-partial').text(parseFloat(stats.partial_emis).toLocaleString('en-IN'));
        $('#tab-count-paid').text(parseFloat(stats.paid_emis).toLocaleString('en-IN'));

        // Dynamically update labels based on selected date filters
        if ($('#fromDateFilter').val() || $('#toDateFilter').val()) {
          $('#stat-total-emis-label').text('Selected date range');
        } else {
          $('#stat-total-emis-label').text('Overdue & Upcoming');
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
          $('#bulkTotalLabel').text('Total Overdue:');
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

      // Handle Table Draw Event to check selected checkboxes
      table.on('draw', function () {
        let allChecked = true;
        let anyVisible = false;

        $('#repaymentsTable tbody .emi-select-checkbox').each(function () {
          anyVisible = true;
          const id = $(this).data('id');
          if (selectedEmis.has(id)) {
            $(this).prop('checked', true);
          } else {
            $(this).prop('checked', false);
            allChecked = false;
          }
        });

        $('#selectAllEmis').prop('checked', anyVisible && allChecked);
      });

      // Individual Checkbox Click
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

        // Sync select all state
        let allChecked = true;
        let anyVisible = false;
        $('#repaymentsTable tbody .emi-select-checkbox').each(function () {
          anyVisible = true;
          if (!this.checked) allChecked = false;
        });
        $('#selectAllEmis').prop('checked', anyVisible && allChecked);

        updateBulkPaymentState();
      });

      // Select All Checkbox Click
      $('#selectAllEmis').on('change', function () {
        const checked = this.checked;
        $('#repaymentsTable tbody .emi-select-checkbox').each(function () {
          $(this).prop('checked', checked);
          const id = $(this).data('id');
          const amount = parseFloat($(this).data('amount')) || 0;
          const clientName = $(this).data('client') || 'N/A';
          const accountNumber = $(this).data('account') || 'N/A';

          if (checked) {
            selectedEmis.set(id, { amount, clientName, accountNumber });
          } else {
            selectedEmis.delete(id);
          }
        });

        updateBulkPaymentState();
      });

      // Cancel Selection
      $(document).on('click', '#bulkCancelBtn', function () {
        selectedEmis.clear();
        $('#selectAllEmis').prop('checked', false);
        $('#repaymentsTable tbody .emi-select-checkbox').prop('checked', false);
        updateBulkPaymentState();
      });

      // Submit Bulk Payment
      $(document).on('click', '#bulkPayBtn', function () {
        if (selectedEmis.size === 0) return;

        const emiIds = Array.from(selectedEmis.keys());
        let totalSum = 0;
        selectedEmis.forEach(info => totalSum += info.amount);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        Swal.fire({
          title: 'Confirm Bulk Payment',
          text: `You are about to record full payments for all ${selectedEmis.size} selected EMIs. Total payment amount is ₹${totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}. This will immediately mark these EMIs as PAID!`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, Pay All Fully!',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#7367f0',
          showLoaderOnConfirm: true,
          preConfirm: () => {
            return fetch(`${baseUrl}emi/repayments/bulk-pay`, {
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
                return response.json().then(err => { throw new Error(err.message || 'Bulk payment failed.') });
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
              title: 'Bulk Payment Successful!',
              text: result.value.message || 'Selected payments successfully recorded.',
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
      'all': 'All EMI Repayments',
      'overdue': 'Overdue EMIs',
      'pending': 'Pending EMIs',
      'partial': 'Partial Paid EMIs',
      'paid': 'Paid EMIs'
    };
    $('#tableTitle').text(titles[status] || 'EMI Repayments List');

    // Dynamically show/hide checkbox column (index 0) based on active tab
    if (window.isAdmin) {
      if (status === 'all' || status === 'overdue' || status === 'paid') {
        table.column(0).visible(true);
      } else {
        table.column(0).visible(false);
      }
      
      // Clear bulk selections when switching tabs
      if (selectedEmis) {
        selectedEmis.clear();
        $('#selectAllEmis').prop('checked', false);
        if (typeof updateBulkPaymentState === 'function') {
          updateBulkPaymentState();
        }
      }
    }
    
    table.ajax.reload();
  });

  let fromPicker, toPicker;
  if (typeof flatpickr !== 'undefined') {
    const fromEl = document.getElementById('fromDateFilter');
    const toEl = document.getElementById('toDateFilter');

    if (fromEl) {
      fromPicker = flatpickr(fromEl, {
        dateFormat: 'd/m/Y',
        allowInput: true,
        onChange: function () {
          if (table) table.ajax.reload();
        }
      });
    }

    if (toEl) {
      toPicker = flatpickr(toEl, {
        dateFormat: 'd/m/Y',
        allowInput: true,
        onChange: function () {
          if (table) table.ajax.reload();
        }
      });
    }
  }

  // Dynamic filtering of Product dropdown when Loan Type changes
  $('#loanTypeFilter').on('change', function () {
    const selectedTypeId = $(this).val();
    $('#productFilter option').each(function () {
      const typeId = $(this).attr('data-loan-type-id');
      if (!selectedTypeId || !typeId || typeId === selectedTypeId) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });

    // Reset product selection if current selection is now hidden
    const selectedProduct = $('#productFilter option:selected');
    if (selectedProduct.length && selectedProduct.css('display') === 'none') {
      $('#productFilter').val('');
    }

    if (table) table.ajax.reload();
  });

  // Filter Change Event
  $('#fromDateFilter, #toDateFilter, #areaFilter, #productFilter, #loanModeFilter').on('change', function () {
    if (table) table.ajax.reload();
  });

  $('#resetFilters').on('click', function () {
    $('#statusFilter').val('all');
    if (fromPicker) fromPicker.clear(); else $('#fromDateFilter').val('');
    if (toPicker) toPicker.clear(); else $('#toDateFilter').val('');
    $('#areaFilter').val('');
    $('#loanTypeFilter').val('').trigger('change');
    $('#productFilter').val('');
    $('#loanModeFilter').val('all');
    
    // Reset active tab in UI
    $('#repaymentsTabs button').removeClass('active');
    $('#repaymentsTabs button[data-status="all"]').addClass('active');
    $('#tableTitle').text('All EMI Repayments');
    
    if (table) table.ajax.reload();
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
      const isPaid = this.closest('tr').querySelector('.badge.bg-label-success') !== null || (table.row($(this).closest('tr')).data().status === 'paid');
      emiPrintReceiptLink.classList.toggle('d-none', !isPaid);
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

        const formatValue = (value, prefix = '') => (value ? `${prefix}${value}` : '-');
        const statusBadge = `<span class="badge bg-label-${emi.status_color} px-3 py-2">${emi.status_label}</span>`;

        const detailRows = [
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

  $('#repaymentsTable').on('click', '.fa-info-circle', function () {
    const row = table.row($(this).closest('tr')).data();
    const emiId = row.id;

    historyEmiNumber.textContent = row.instalment_number;
    historyTotalAmount.textContent = row.total_amount_formatted;
    
    // Extract raw text from paid_amount HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = row.paid_amount;
    historyPaidAmount.textContent = tempDiv.textContent.trim();

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
