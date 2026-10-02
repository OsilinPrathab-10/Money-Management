/**
 * EMI Details DataTable with Export Functionality
 */

'use strict';

(function () {
  const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
  const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const loanOutstanding = parseFloat($('#emiScheduleCard').data('loan-outstanding')) || 0;
  let isSyncingSelectAll = false;
  const selectedEmiMap = new Map();
  let emiScheduleDataTable = null;
  const storageKey = 'emiDetailsSelected_' + (window.location.pathname.replace(/\D/g, '') || 'default');

  function saveSelectionToStorage() {
    try {
      const arr = Array.from(selectedEmiMap.entries());
      sessionStorage.setItem(storageKey, JSON.stringify(arr));
    } catch (err) {}
  }

  function loadSelectionFromStorage() {
    try {
      const saved = sessionStorage.getItem(storageKey);
      if (saved) {
        const arr = JSON.parse(saved);
        if (Array.isArray(arr)) {
          arr.forEach(([id, val]) => selectedEmiMap.set(id, val));
        }
      }
    } catch (err) {}
  }

  function clearSelectionStorage() {
    try {
      sessionStorage.removeItem(storageKey);
    } catch (err) {}
  }

  loadSelectionFromStorage();

  const EMI_BANK_PAYMENT_GROUPS = [
    {
      methodSelectId: 'payment_method',
      bankSelectId: 'internal_bank_account_id',
      bankContainerId: 'bankAccountContainer',
      qrContainerId: 'qrCodeDisplayContainer',
      qrBankNameId: 'qrBankName',
      qrUpiIdId: 'qrUpiId',
      qrImageWrapperId: 'qrCodeImageWrapper',
      bankTransferContainerId: 'bankTransferDetailsContainer',
      bankTransferContentId: 'bankTransferDetailsContent'
    },
    {
      methodSelectId: 'partialPaymentMethod',
      bankSelectId: 'partial_internal_bank_account_id',
      bankContainerId: 'partialBankAccountContainer',
      qrContainerId: 'partialQrCodeDisplayContainer',
      qrBankNameId: 'partialQrBankName',
      qrUpiIdId: 'partialQrUpiId',
      qrImageWrapperId: 'partialQrCodeImageWrapper',
      bankTransferContainerId: 'partialBankTransferDetailsContainer',
      bankTransferContentId: 'partialBankTransferDetailsContent'
    },
    {
      methodSelectId: 'selected_payment_method',
      bankSelectId: 'selected_internal_bank_account_id',
      bankContainerId: 'selectedBankAccountContainer',
      qrContainerId: 'selectedQrCodeDisplayContainer',
      qrBankNameId: 'selectedQrBankName',
      qrUpiIdId: 'selectedQrUpiId',
      qrImageWrapperId: 'selectedQrCodeImageWrapper',
      bankTransferContainerId: 'selectedBankTransferDetailsContainer',
      bankTransferContentId: 'selectedBankTransferDetailsContent'
    }
  ];

  function bootEmiBankPaymentFields() {
    if (window.BankPaymentFields) {
      window.BankPaymentFields.initGroups(EMI_BANK_PAYMENT_GROUPS);
      return;
    }
    window._bankPaymentGroupsQueue = EMI_BANK_PAYMENT_GROUPS;
  }

  $(function () {
    bootEmiBankPaymentFields();
  });

  function getEmiScheduleTable() {
    if (emiScheduleDataTable) {
      return emiScheduleDataTable;
    }
    if ($.fn.dataTable.isDataTable('#emiScheduleTable')) {
      emiScheduleDataTable = $('#emiScheduleTable').DataTable();
    }
    return emiScheduleDataTable;
  }

  function getEmiPayCheckboxes() {
    const table = getEmiScheduleTable();
    if (table) {
      return table.$('.emi-pay-checkbox');
    }
    return $('#emiScheduleTable tbody .emi-pay-checkbox');
  }

  function getPayableEmiCheckboxes() {
    return getEmiPayCheckboxes().filter(function () {
      return $(this).data('payable') === 1 || $(this).data('payable') === '1';
    });
  }

  function getSelectedEmiData(onlyPayable) {
    const items = [];
    selectedEmiMap.forEach(item => {
      if (onlyPayable && item.status === 'paid') {
        return;
      }
      items.push(item);
    });
    return items;
  }

  function syncSelectAllControls(checked) {
    $('.js-select-all-emis').prop('checked', checked);
  }

  function openPaySelectedModal() {
    const selectedData = getSelectedEmiData(true);
    if (!selectedData.length) {
      Swal.fire({
        icon: 'info',
        title: 'No Unpaid EMIs Selected',
        text: 'Select pending, overdue, or partial EMIs to pay.'
      });
      return;
    }

    const emiLabels = selectedData.map(item =>
      `EMI #${item.no} (₹${item.remaining.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`
    );
    const total = selectedData.reduce((sum, item) => sum + item.remaining, 0);

    $('#selectedEmisSummary').html(emiLabels.join('<br>'));
    $('#selected_paid_amount').val(total.toFixed(2)).data('full-total', total).prop('readonly', true);
    $('input[name="selected_pay_type"][value="full"]').prop('checked', true);
    $('#selected_paid_amount').attr('max', loanOutstanding.toFixed(2));
    $('#selectedPaidAmountHelp').text(
      `Full payment selected. Total due: ₹${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`
    );
    $('#paySelectedEmisModal').modal('show');
    $('#emiScheduleBulkPayBar').addClass('d-none');
    if (window.BankPaymentFields) {
      window.BankPaymentFields.resetToInHand('selected_payment_method');
    }
  }

  $(document).on('change', 'input[name="selected_pay_type"]', function () {
    const mode = $(this).val();
    const fullTotal = parseFloat($('#selected_paid_amount').data('full-total')) || 0;
    if (mode === 'full') {
      $('#selected_paid_amount').val(fullTotal.toFixed(2)).prop('readonly', true);
      $('#selectedPaidAmountHelp').text(`Full payment selected. Total due: ₹${fullTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`);
    } else {
      $('#selected_paid_amount').prop('readonly', false).focus();
      $('#selectedPaidAmountHelp').text('Partial payment mode: enter custom amount to allocate across selected EMIs.');
    }
  });

  function restoreCheckboxStatesFromSelection() {
    isSyncingSelectAll = true;
    getEmiPayCheckboxes().each(function () {
      const id = String($(this).data('emi-id'));
      $(this).prop('checked', selectedEmiMap.has(id));
    });
    isSyncingSelectAll = false;
  }

  window.syncEmiSelectAllState = function syncEmiSelectAllState() {
    saveSelectionToStorage();
    const allCheckboxes = getEmiPayCheckboxes();
    const selectedData = getSelectedEmiData();
    const total = selectedData.reduce((sum, item) => sum + item.remaining, 0);

    $('#btnPaySelectedEmis').prop('disabled', selectedData.length === 0);
    if (selectedData.filter(item => item.status !== 'paid').length > 0) {
      $('#btnPaySelectedEmis').show();
    } else {
      $('#btnPaySelectedEmis').hide();
    }
    $('#btnPaySelectedEmis').data('selected-total', total);

    if (!isSyncingSelectAll) {
      const payableBoxes = [];
      getPayableEmiCheckboxes().each(function () {
        if (!this.disabled) {
          payableBoxes.push(this);
        }
      });
      const allChecked = payableBoxes.length > 0 && payableBoxes.every(box => selectedEmiMap.has(String($(box).data('emi-id'))));
      syncSelectAllControls(allChecked);
    }

    // Sync Floating Bulk Pay Bar
    const bar = $('#emiScheduleBulkPayBar');
    if (bar.length) {
      if (selectedData.length === 0) {
        bar.addClass('d-none');
        allCheckboxes.prop('disabled', false);
      } else {
        bar.removeClass('d-none');
        $('#emiScheduleBulkSelectedCount').text(selectedData.length);
        $('#emiScheduleBulkTotalAmount').text('₹' + total.toLocaleString('en-IN', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }));

        let hasPaid = false;
        let hasUnpaid = false;
        selectedData.forEach(item => {
          if (item.status === 'paid') {
            hasPaid = true;
          } else {
            hasUnpaid = true;
          }
        });

        if (hasPaid) {
          $('#emiScheduleBulkBarTitle').text('EMIs Selected for Bulk Undo');
          $('#emiScheduleBulkTotalLabel').text('Total Paid:');
          $('#emiScheduleBulkPayBtn').addClass('d-none');
          $('#emiScheduleBulkUndoBtn').removeClass('d-none');
          bar.css('border-top', '4px solid #ea5455');
        } else {
          $('#emiScheduleBulkBarTitle').text('EMIs Selected for Bulk Payment');
          $('#emiScheduleBulkTotalLabel').text('Total Overdue:');
          $('#emiScheduleBulkPayBtn').removeClass('d-none');
          $('#emiScheduleBulkUndoBtn').addClass('d-none');
          bar.css('border-top', '4px solid #28c76f');
        }

        allCheckboxes.each(function() {
          const $box = $(this);
          const id = String($box.data('emi-id'));
          if (!selectedEmiMap.has(id)) {
            const isPaid = $box.data('status') === 'paid';
            if (hasPaid && !isPaid) {
              this.disabled = true;
            } else if (hasUnpaid && isPaid) {
              this.disabled = true;
            } else {
              this.disabled = false;
            }
          }
        });

        // Render selected EMIs inside the container
        let emisHtml = '<div class="row g-2">';
        selectedData.forEach(item => {
          const priceClass = item.status === 'paid' ? 'text-danger' : 'text-success';
          emisHtml += `
            <div class="col-md-4">
              <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded-3 border">
                <span class="fw-semibold text-body small">EMI #${item.no}</span>
                <strong class="${priceClass} small">₹${item.remaining.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
              </div>
            </div>
          `;
        });
        emisHtml += '</div>';
        $('#emiScheduleBulkListContainer').html(emisHtml);
      }
    }
  };

  // Handle floating bar buttons
  $(document).on('click', '#emiScheduleBulkCancelBtn', function () {
    selectedEmiMap.clear();
    clearSelectionStorage();
    $('.js-select-all-emis').prop('checked', false);
    getEmiPayCheckboxes().prop('checked', false).prop('disabled', false);
    updatePaySelectedState();
  });

  $(document).on('click', '#emiScheduleBulkPayBtn', function () {
    openPaySelectedModal();
  });

  $(document).on('click', '#emiScheduleBulkUndoBtn', function () {
    if (selectedEmiMap.size === 0) return;

    const emiIds = Array.from(selectedEmiMap.keys());
    const selectedData = getSelectedEmiData();
    let totalSum = selectedData.reduce((sum, item) => sum + item.remaining, 0);

    Swal.fire({
      title: 'Confirm Bulk Undo Payment',
      text: `You are about to undo payments for all ${selectedEmiMap.size} selected EMIs. Total amount to be undone is ₹${totalSum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}. This will mark these EMIs as pending/overdue!`,
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
          confirmButtonColor: '#28c76f'
        }).then(() => {
          selectedEmiMap.clear();
          updatePaySelectedState();
          window.location.reload();
        });
      }
    });
  });

  function updatePaySelectedState() {
    window.syncEmiSelectAllState();
  }

  // Initialize DataTable
  if ($('#emiScheduleTable').length) {
    const sanitizeScheduleExport = function (data, row, column, node) {
      let text = '';
      if (node && node.querySelector) {
        const clone = node.cloneNode(true);
        clone.querySelectorAll('input, button, a.btn, .dropdown').forEach(function (el) {
          el.remove();
        });
        text = (clone.textContent || '').replace(/\s+/g, ' ').trim();
      }
      if (!text) {
        text = data == null ? '' : String(data);
        text = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      }
      return text.replace(/₹/g, 'Rs. ');
    };
    const scheduleExportOptions = {
      columns: ':not(:last-child)',
      stripHtml: true,
      format: { body: sanitizeScheduleExport }
    };

    emiScheduleDataTable = $('#emiScheduleTable').DataTable({
      order: [[0, 'asc']], // Order by EMI number
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"lB>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      lengthMenu: [
        [10, 20, 25, 50, 100, 200, -1],
        [10, 20, 25, 50, 100, 200, 'All']
      ],
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
              exportOptions: scheduleExportOptions,
              customize: function (win) {
                $(win.document.body)
                  .css('font-size', '10pt')
                  .prepend('<h3 class="text-center">EMI Payment Schedule</h3>');

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
              exportOptions: scheduleExportOptions
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-2-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: scheduleExportOptions
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              title: 'EMI Payment Schedule',
              orientation: 'landscape',
              pageSize: 'A4',
              exportOptions: scheduleExportOptions
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
              className: 'dropdown-item',
              exportOptions: scheduleExportOptions
            }
          ]
        }
      ],
      scrollX: true,
      autoWidth: false,
      columnDefs: [
        { orderable: false, targets: 1 }
      ],
      drawCallback: function () {
        restoreCheckboxStatesFromSelection();
        if (typeof window.syncEmiSelectAllState === 'function') {
          window.syncEmiSelectAllState();
        }
      }
    });
  }

  $(document).on('change', '#emiScheduleTable .emi-pay-checkbox', function () {
    if (isSyncingSelectAll) {
      return;
    }

    const id = String($(this).data('emi-id'));
    if (this.checked) {
      selectedEmiMap.set(id, {
        id: id,
        no: $(this).data('emi-no'),
        status: String($(this).data('status') || ''),
        remaining: parseFloat($(this).data('remaining') || 0)
      });
    } else {
      selectedEmiMap.delete(id);
    }
    updatePaySelectedState();
  });

  $(document).on('change', '.js-select-all-emis', function (e) {
    if (isSyncingSelectAll) {
      return;
    }

    const checked = e.target.checked;
    isSyncingSelectAll = true;

    getPayableEmiCheckboxes().each(function () {
      if (!this.disabled) {
        const id = String($(this).data('emi-id'));
        $(this).prop('checked', checked);
        if (checked) {
          selectedEmiMap.set(id, {
            id: id,
            no: $(this).data('emi-no'),
            status: String($(this).data('status') || ''),
            remaining: parseFloat($(this).data('remaining') || 0)
          });
        } else {
          selectedEmiMap.delete(id);
        }
      }
    });

    syncSelectAllControls(checked);
    isSyncingSelectAll = false;
    updatePaySelectedState();
  });

  $(document).on('click', '.js-select-all-emis', function (e) {
    e.stopPropagation();
  });

  $('#btnPaySelectedEmis').on('click', function () {
    openPaySelectedModal();
  });

  // Pay Now Button Click
  $(document).on('click', '.btn-pay-now', function () {
    const id = $(this).data('id');
    const emiNo = $(this).data('emi-no');
    const amount = $(this).data('amount');
    const isKandhuvatti = $(this).data('is-kandhuvatti') === true || $(this).data('is-kandhuvatti') === 'true';
    const outstandingPrincipal = parseFloat($(this).data('outstanding-principal')) || 0;

    $('#modalEmiId').val(id);
    $('#modalEmiNo').text(emiNo);
    $('#paid_amount').val(amount).data('base-amount', amount);
    
    // Kandhuvatti Logic
    if (isKandhuvatti) {
      $('#principalAmountGroup').removeClass('d-none');
      $('#principalBalanceGroup').removeClass('d-none');
      $('#modalPrincipalBalanceDisplay').text('₹' + outstandingPrincipal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      $('#paid_amount').prop('readonly', false);
      $('#principal_amount').val('');
      renderKandhuvattiHelp(amount, 0, amount);
    } else {
      $('#principalAmountGroup').addClass('d-none');
      $('#principalBalanceGroup').addClass('d-none');
      $('#paid_amount').prop('readonly', false);
      $('#paidAmountHelp').text('Enter payment amount. Extra amount will close upcoming EMIs automatically, up to the outstanding loan amount.');
    }
    
    // Reset payment method and bank account
    if (window.BankPaymentFields) {
      window.BankPaymentFields.resetToInHand('payment_method');
    } else {
      $('#payment_method').val('in_hand').trigger('change');
    }
    
    $('#payEmiModal').modal('show');
  });

  function renderKandhuvattiHelp(baseInterest, principal, totalVal) {
    if (principal > 0) {
      if (totalVal >= (baseInterest + principal - 0.01)) {
        $('#paidAmountHelp').html(
          `<span class="text-success fw-semibold"><i class="ri-check-line me-1"></i>₹${baseInterest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Cycle Interest + ₹${principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Principal = ₹${totalVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Total Payment. Full cycle will be cleared!</span>`
        );
      } else if (totalVal < principal) {
        $('#paidAmountHelp').html(
          `<span class="text-danger fw-semibold"><i class="ri-alert-line me-1"></i>Total Payment (₹${totalVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}) cannot be less than Principal Repayment (₹${principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}).</span>`
        );
      } else {
        const interestPart = Math.max(0, totalVal - principal);
        const unpaidInterest = Math.max(0, baseInterest - interestPart);
        $('#paidAmountHelp').html(
          `<span class="text-warning fw-semibold"><i class="ri-information-line me-1"></i>Total ₹${totalVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}: ₹${principal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Principal + ₹${interestPart.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Interest. (₹${unpaidInterest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Cycle Interest will remain unpaid).</span>`
        );
      }
    } else {
      $('#paidAmountHelp').html('Enter total amount (Cycle Interest Due: <strong class="text-dark">₹' + baseInterest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</strong>)');
    }
  }

  $('#principal_amount').on('input', function () {
    const isKandhuvatti = !$('#principalAmountGroup').hasClass('d-none');
    if (isKandhuvatti) {
      const principal = parseFloat($(this).val()) || 0;
      const baseAmount = parseFloat($('#paid_amount').data('base-amount')) || 0;
      const total = baseAmount + principal;
      $('#paid_amount').val(total > 0 ? total.toFixed(2) : '');
      renderKandhuvattiHelp(baseAmount, principal, total);
    }
  });

  $('#paid_amount').on('input', function () {
    const val = parseFloat($(this).val()) || 0;
    const emiNo = $('#modalEmiNo').text();
    const baseAmount = parseFloat($(this).data('base-amount')) || 0;
    const isKandhuvatti = !$('#principalAmountGroup').hasClass('d-none');

    if (isKandhuvatti) {
      const principal = parseFloat($('#principal_amount').val()) || 0;
      renderKandhuvattiHelp(baseAmount, principal, val);
    } else if (baseAmount > 0 && val > baseAmount) {
      const advance = val - baseAmount;
      $('#paidAmountHelp').html(
        `<span class="text-success fw-semibold">₹${baseAmount.toFixed(2)} for EMI #${emiNo} + ₹${advance.toFixed(2)} advance for upcoming EMIs.</span>`
      );
    } else {
      $('#paidAmountHelp').text('Enter payment amount. Extra amount will close upcoming EMIs automatically, up to the outstanding loan amount.');
    }
  });

  // Handle Form Submission
  $('#payEmiForm').on('submit', function (e) {
    e.preventDefault();

    const form = $(this);
    const submitBtn = $('#btnSubmitPayment');
    const formEl = this;
    const methodSelectEl = formEl.querySelector('select[name="payment_method"]');
    const bankSelectEl = formEl.querySelector('select[name="internal_bank_account_id"]');
    const bankValidationError = window.BankPaymentFields
      ? window.BankPaymentFields.validateBankPayment(methodSelectEl, bankSelectEl, formEl)
      : null;

    if (bankValidationError) {
      Swal.fire({ icon: 'error', title: 'Bank Account Required', text: bankValidationError });
      return;
    }

    const paymentMethod = methodSelectEl ? methodSelectEl.value : '';
    const bankAccountId = bankSelectEl ? bankSelectEl.value : '';

    // Show loading
    submitBtn.prop('disabled', true);
    submitBtn.find('.spinner-border').removeClass('d-none');

    const payload = form.serializeArray().filter(item => {
      if (item.name === 'internal_bank_account_id' && paymentMethod === 'in_hand') {
        return false;
      }
      return true;
    });
    if ((paymentMethod === 'upi' || paymentMethod === 'bank_transfer') && bankAccountId) {
      const bankField = payload.find(item => item.name === 'internal_bank_account_id');
      if (bankField) {
        bankField.value = bankAccountId;
      } else {
        payload.push({ name: 'internal_bank_account_id', value: bankAccountId });
      }
    }

    $.ajax({
      url: baseUrl + 'emi/receipts/create',
      type: 'POST',
      data: $.param(payload),
      success: function (response) {
        submitBtn.prop('disabled', false);
        submitBtn.find('.spinner-border').addClass('d-none');

        if (response.success) {
          $('#payEmiModal').modal('hide');
          handlePaymentSuccess(response, 'Payment recorded successfully.');
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: response.message || 'Something went wrong',
            customClass: {
              confirmButton: 'btn btn-primary'
            }
          });
        }
      },
      error: function (xhr) {
        submitBtn.prop('disabled', false);
        submitBtn.find('.spinner-border').addClass('d-none');

        let errorMsg = 'An error occurred while processing the payment.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        }

        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: errorMsg,
          customClass: {
            confirmButton: 'btn btn-primary'
          }
        });
      }
    });
  });

  /**
   * Handle Payment Success with WhatsApp and SMS Redirects
   */
  function handlePaymentSuccess(data, fallbackMsg) {
    if (!data.success) {
      Swal.fire({
        title: 'Error!',
        text: data.message || 'Payment failed',
        icon: 'error',
        customClass: { confirmButton: 'btn btn-primary' }
      });
      return;
    }

    const msg = data.message || fallbackMsg || 'Payment processed successfully.';

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
            <h6 class="text-success mb-3">${msg}</h6>
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
        window.location.reload();
      });
    } else {
      Swal.fire({
        title: 'Success!',
        text: msg,
        icon: 'success',
        customClass: { confirmButton: 'btn btn-success' }
      }).then(() => {
        window.location.reload();
      });
    }
  }

  $('#paySelectedEmisForm').on('submit', function (e) {
    e.preventDefault();
    const selectedData = getSelectedEmiData(true);
    if (!selectedData.length) {
      Swal.fire({ icon: 'info', title: 'No Unpaid EMIs', text: 'Select pending, overdue, or partial EMIs to pay.' });
      return;
    }

    const amount = parseFloat($('#selected_paid_amount').val());
    if (isNaN(amount) || amount <= 0) {
      Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Please enter a valid payment amount.' });
      return;
    }
    if (amount > loanOutstanding + 0.01) {
      Swal.fire({
        icon: 'error',
        title: 'Amount Too High',
        text: `Payment cannot exceed loan outstanding of ₹${loanOutstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}.`
      });
      return;
    }

    const paySelectedFormEl = document.getElementById('paySelectedEmisForm');
    const methodSelectEl = document.getElementById('selected_payment_method');
    const bankSelectEl = document.getElementById('selected_internal_bank_account_id');
    const bankValidationError = window.BankPaymentFields
      ? window.BankPaymentFields.validateBankPayment(methodSelectEl, bankSelectEl, paySelectedFormEl)
      : null;
    if (bankValidationError) {
      Swal.fire({ icon: 'error', title: 'Bank Account Required', text: bankValidationError });
      return;
    }

    const paymentMethod = methodSelectEl ? methodSelectEl.value : '';
    const bankAccountId = bankSelectEl ? bankSelectEl.value : '';

    const submitBtn = $('#btnSubmitSelectedPayment');
    submitBtn.prop('disabled', true);
    submitBtn.find('.spinner-border').removeClass('d-none');

    const emiIds = selectedData.map(item => item.id);

    $.ajax({
      url: baseUrl + 'emi/receipts/pay-selected',
      type: 'POST',
      data: {
        _token: csrfToken,
        emi_ids: emiIds,
        paid_amount: amount,
        paid_date: $('#selected_paid_date').val(),
        payment_method: paymentMethod,
        internal_bank_account_id: paymentMethod === 'in_hand' ? null : bankAccountId,
        remarks: $('#selected_remarks').val()
      },
      success: function (response) {
        submitBtn.prop('disabled', false);
        submitBtn.find('.spinner-border').addClass('d-none');
        if (response.success) {
          $('#paySelectedEmisModal').modal('hide');
          handlePaymentSuccess(response, 'Selected EMIs paid successfully.');
        } else {
          Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Payment failed.' });
        }
      },
      error: function (xhr) {
        submitBtn.prop('disabled', false);
        submitBtn.find('.spinner-border').addClass('d-none');
        const errorMsg = xhr.responseJSON?.message || 'An error occurred while processing the payment.';
        Swal.fire({ icon: 'error', title: 'Error!', text: errorMsg });
      }
    });
  });

  $(document).on('click', '.partial-payment-btn', function () {
    const btn = $(this);
    const emiId = btn.data('emi-id');
    const emiNumber = btn.data('emi-number');
    const totalAmount = parseFloat(btn.data('total-amount')) || 0;
    const paidAmount = parseFloat(btn.data('paid-amount')) || 0;
    const previousBalance = parseFloat(btn.data('previous-balance')) || 0;
    const penaltyAmount = parseFloat(btn.data('penalty-amount')) || 0;
    const minPercentage = parseFloat(btn.data('min-percentage')) || 10;
    const isKandhuvatti = btn.data('is-kandhuvatti') === true || btn.data('is-kandhuvatti') === 'true';
    const penaltyMethod = btn.data('penalty-method') || 'emi_amount';

    const openPartialModal = function (rules) {
      if (rules && !rules.allows_partial) {
        Swal.fire({ icon: 'warning', title: 'Not Allowed', text: rules.timing_message || 'Partial payments are not allowed for this EMI.' });
        return;
      }

      let totalDue = Math.max(0, previousBalance + totalAmount + penaltyAmount - paidAmount);
      let minimumAmount;
      if (rules && rules.is_active) {
        totalDue = rules.outstanding_due ?? totalDue;
        minimumAmount = rules.minimum_partial_amount || 0;
      } else {
        const minBase = penaltyMethod === 'emi_plus_partial_remaining' ? totalDue : (totalAmount + previousBalance);
        minimumAmount = Math.ceil((minBase * minPercentage) / 100);
      }

      $('#partialEmiId').val(emiId);
      $('#partialEmiNumber').text(emiNumber);
      $('#partialTotalEmi').val(totalAmount);
      $('#partialTotalEmiDisplay').text('₹' + totalAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      $('#partialPaidAmount').val(paidAmount);
      $('#partialPaidAmountDisplay').text('₹' + paidAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      $('#partialTotalDue').val(totalDue);
      $('#partialTotalDueDisplay').text('₹' + totalDue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      $('#partialPaymentAmount').attr('min', minimumAmount).attr('max', totalDue).val('');
      $('#partialMinAmountHelp').text('Minimum: ₹' + minimumAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      
      if (isKandhuvatti) {
        $('#partialPaymentAmount').closest('.mb-3').addClass('d-none');
        $('#partialPaymentAmount').removeAttr('required').val('');
        $('#partialPrincipalAmountGroup').removeClass('d-none');
        $('#partialPrincipalAmount').attr('required', 'required').val('');
      } else {
        $('#partialPaymentAmount').closest('.mb-3').removeClass('d-none');
        $('#partialPaymentAmount').attr('required', 'required');
        $('#partialPrincipalAmountGroup').addClass('d-none');
        $('#partialPrincipalAmount').removeAttr('required').val('');
      }

      if (window.BankPaymentFields) {
        window.BankPaymentFields.resetToInHand('partialPaymentMethod');
      }
      $('#partialPaymentModal').modal('show');
    };

    $.get(baseUrl + 'emi/' + emiId + '/partial-payment-rules', function (rules) {
      openPartialModal(rules);
    }).fail(function () {
      openPartialModal(null);
    });
  });

  $('#partialPaymentForm').on('submit', function (e) {
    e.preventDefault();
    
    // Check if it's a Kandhuvatti (Open Loan)
    const isKandhuvatti = !$('#partialPaymentAmount').prop('required');
    
    if (isKandhuvatti) {
      const principal = parseFloat($('#partialPrincipalAmount').val());
      if (isNaN(principal) || principal <= 0) {
        Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Enter a valid principal amount.' });
        return;
      }
    } else {
      const amount = parseFloat($('#partialPaymentAmount').val());
      const min = parseFloat($('#partialPaymentAmount').attr('min'));
      const max = parseFloat($('#partialPaymentAmount').attr('max'));
      if (isNaN(amount) || amount < min || amount > max) {
        Swal.fire({ icon: 'error', title: 'Invalid Amount', text: `Enter an amount between ₹${min} and ₹${max}.` });
        return;
      }
    }

    const formEl = this;
    const methodSelectEl = formEl.querySelector('select[name="payment_method"]');
    const bankSelectEl = formEl.querySelector('select[name="internal_bank_account_id"]');
    const bankValidationError = window.BankPaymentFields
      ? window.BankPaymentFields.validateBankPayment(methodSelectEl, bankSelectEl, formEl)
      : null;
    if (bankValidationError) {
      Swal.fire({ icon: 'error', title: 'Bank Account Required', text: bankValidationError });
      return;
    }

    const paymentMethod = methodSelectEl ? methodSelectEl.value : '';
    const bankAccountId = bankSelectEl ? bankSelectEl.value : '';

    const submitBtn = $('#submitPartialPaymentBtn');
    submitBtn.prop('disabled', true);

    const payload = $(this).serializeArray().filter(item => {
      if (item.name === 'internal_bank_account_id' && paymentMethod === 'in_hand') {
        return false;
      }
      return true;
    });
    if ((paymentMethod === 'upi' || paymentMethod === 'bank_transfer') && bankAccountId) {
      const bankField = payload.find(item => item.name === 'internal_bank_account_id');
      if (bankField) {
        bankField.value = bankAccountId;
      } else {
        payload.push({ name: 'internal_bank_account_id', value: bankAccountId });
      }
    }

    $.ajax({
      url: baseUrl + 'emi/partial-payment',
      type: 'POST',
      data: $.param(payload) + '&_token=' + encodeURIComponent(csrfToken),
      success: function (response) {
        submitBtn.prop('disabled', false);
        if (response.success) {
          $('#partialPaymentModal').modal('hide');
          handlePaymentSuccess(response, 'Partial payment recorded successfully.');
        } else {
          Swal.fire({ icon: 'error', title: 'Error!', text: response.message || 'Partial payment failed.' });
        }
      },
      error: function (xhr) {
        submitBtn.prop('disabled', false);
        Swal.fire({ icon: 'error', title: 'Error!', text: xhr.responseJSON?.message || 'Partial payment failed.' });
      }
    });
  });

  // Undo payment (Admin only)
  $(document).on('click', '.btn-undo-payment', function (e) {
    e.preventDefault();
    const emiId = $(this).data('emi-id');
    const instalment = $(this).data('instalment') || '';

    Swal.fire({
      title: 'Are you sure?',
      text: `Undo payment for instalment #${instalment}? This reverses balances and deletes related collections.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, undo payment!',
      cancelButtonText: 'Cancel',
      input: 'text',
      inputPlaceholder: 'Enter reason to undo...',
      preConfirm: (reason) => {
        if (!reason) {
          Swal.showValidationMessage('Please enter a reason to undo the payment.');
          return false;
        }
        return reason;
      }
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({ title: 'Undoing Payment...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

      fetch(baseUrl + 'emi/payment/' + emiId + '/undo', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ reason: result.value })
      })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            Swal.fire({ title: 'Success!', text: data.message || 'Payment undone.', icon: 'success' })
              .then(() => window.location.reload());
          } else {
            Swal.fire('Error!', data.message || 'Failed to undo payment.', 'error');
          }
        })
        .catch(() => Swal.fire('Error!', 'Failed to undo payment.', 'error'));
    });
  });

  // Payment collection history (EMI schedule)
  $(document).on('click', '.btn-view-history', function (e) {
    e.preventDefault();
    const emiId = $(this).data('id');
    const emiNo = $(this).data('emi-no') || '';
    if (!emiId) return;

    const historyModalEl = document.getElementById('emiHistoryModal');
    if (!historyModalEl) return;
    const historyModal = bootstrap.Modal.getOrCreateInstance(historyModalEl);
    const historyTableBody = document.getElementById('historyTableBody');
    const historyEmiNumber = document.getElementById('historyEmiNumber');
    const historyTotalAmount = document.getElementById('historyTotalAmount');
    const historyPaidAmount = document.getElementById('historyPaidAmount');

    if (historyEmiNumber) historyEmiNumber.textContent = emiNo;
    if (historyTotalAmount) historyTotalAmount.textContent = '₹0.00';
    if (historyPaidAmount) historyPaidAmount.textContent = '₹0.00';
    if (historyTableBody) {
      historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></td></tr>';
    }
    historyModal.show();

    fetch(baseUrl + 'emi/repayments/emi/' + emiId + '/history', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(data => {
        if (!data.success) throw new Error('Failed to fetch history');

        if (historyTotalAmount) historyTotalAmount.textContent = data.total_amount || '₹0.00';
        if (historyPaidAmount) historyPaidAmount.textContent = data.original_paid_amount || data.paid_amount || '₹0.00';

        const actionHeader = document.querySelector('.history-action-header');
        if (data.is_admin) {
          actionHeader?.classList.remove('d-none');
        } else {
          actionHeader?.classList.add('d-none');
        }

        const collections = Array.isArray(data.collections) ? data.collections : [];
        if (!historyTableBody) return;

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
          let amountHtml = `<div class="fw-bold">${item.amount}</div>`;
          if (item.raw_principal_paid && parseFloat(item.raw_principal_paid) > 0.01) {
            amountHtml += `
              <div class="small mt-1 text-nowrap" style="font-size: 0.78rem;">
                <span class="text-info d-block">Interest: ₹${item.interest_paid}</span>
                <span class="text-success d-block">Principal: ₹${item.principal_paid}</span>
              </div>
            `;
          }
          return `
            <tr>
              <td class="ps-4">
                <div class="d-flex flex-column">
                  <span class="fw-medium text-nowrap">${item.date}</span>
                  <small class="text-muted">${item.agent || ''}</small>
                </div>
              </td>
              <td>${amountHtml}</td>
              <td><small class="text-uppercase">${item.method}</small></td>
              <td><small class="text-muted">${item.reference}</small></td>
              <td><span class="badge bg-label-info small">${item.status || item.type}</span></td>
              ${actionCol}
            </tr>
          `;
        }).join('');
      })
      .catch(err => {
        console.error('History error:', err);
        if (historyTableBody) {
          historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Failed to load history.</td></tr>';
        }
      });
  });

  // Handle Open Loan Interest Cycles Generation Form Submit
  const formGenerateCycles = document.getElementById('formGenerateOpenLoanCycles');
  if (formGenerateCycles) {
    formGenerateCycles.addEventListener('submit', function (e) {
      e.preventDefault();

      const countInput = document.getElementById('generate_cycle_count');
      const count = parseInt(countInput ? countInput.value : 0, 10);
      if (!count || count < 1) {
        Swal.fire({
          icon: 'warning',
          title: 'Invalid Input',
          text: 'Please enter a valid number of cycles greater than zero.'
        });
        return;
      }

      const submitBtn = document.getElementById('btnSubmitGenerateCycles');
      const origText = submitBtn ? submitBtn.innerHTML : 'Generate Cycles';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Generating...';
      }

      const url = this.getAttribute('action');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ cycle_count: count })
      })
      .then(async response => {
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.message || 'Failed to generate cycles.');
        }
        return data;
      })
      .then(data => {
        const modalEl = document.getElementById('generateCyclesModal');
        if (modalEl) {
          const bsModal = bootstrap.Modal.getInstance(modalEl);
          if (bsModal) {
            bsModal.hide();
          }
        }

        Swal.fire({
          icon: 'success',
          title: 'Cycles Generated!',
          text: data.message || 'Interest cycles have been generated successfully.',
          timer: 1600,
          showConfirmButton: false
        }).then(() => {
          window.location.reload();
        });
      })
      .catch(error => {
        console.error('Error generating cycles:', error);
        Swal.fire({
          icon: 'error',
          title: 'Generation Failed',
          text: error.message || 'An error occurred while generating cycles.'
        });
      })
      .finally(() => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origText;
        }
      });
    });
  }
})();