/**
 * EMI Details DataTable with Export Functionality
 */

'use strict';

(function () {
  // Initialize DataTable
  if ($('#emiScheduleTable').length) {
    $('#emiScheduleTable').DataTable({
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
      lengthMenu: [10, 25, 50, 100],
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
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                format: {
                  body: function (data, row, column, node) {
                    // Strip HTML tags for export
                    return data.replace(/<[^>]*>/g, '');
                  }
                }
              },
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
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                format: {
                  body: function (data, row, column, node) {
                    return data.replace(/<[^>]*>/g, '');
                  }
                }
              }
            },
            {
              extend: 'excel',
              text: '<i class="icon-base ri ri-file-excel-2-line me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                format: {
                  body: function (data, row, column, node) {
                    return data.replace(/<[^>]*>/g, '');
                  }
                }
              }
            },
            {
              extend: 'pdf',
              text: '<i class="icon-base ri ri-file-pdf-line me-2"></i>PDF',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                format: {
                  body: function (data, row, column, node) {
                    return data.replace(/<[^>]*>/g, '');
                  }
                }
              }
            },
            {
              extend: 'copy',
              text: '<i class="icon-base ri ri-file-copy-line me-2"></i>Copy',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                format: {
                  body: function (data, row, column, node) {
                    return data.replace(/<[^>]*>/g, '');
                  }
                }
              }
            }
          ]
        }
      ],
      scrollX: true,
      autoWidth: false
    });
  }

  // Pay Now Button Click
  $(document).on('click', '.btn-pay-now', function () {
    const id = $(this).data('id');
    const emiNo = $(this).data('emi-no');
    const amount = $(this).data('amount');
    const isKandhuvatti = $(this).data('is-kandhuvatti') === true || $(this).data('is-kandhuvatti') === 'true';
    const outstandingPrincipal = parseFloat($(this).data('outstanding-principal')) || 0;

    $('#modalEmiId').val(id);
    $('#modalEmiNo').text(emiNo);
    $('#paid_amount').val(amount);
    
    // Kandhuvatti Logic
    if (isKandhuvatti) {
      $('#principalAmountGroup').removeClass('d-none');
      $('#principalBalanceGroup').removeClass('d-none');
      $('#modalPrincipalBalanceDisplay').text('₹' + outstandingPrincipal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
      $('#paid_amount').prop('readonly', false);
      $('#paidAmountHelp').text('Enter total amount (Interest + Principal)');
      $('#principal_amount').val('');
    } else {
      $('#principalAmountGroup').addClass('d-none');
      $('#principalBalanceGroup').addClass('d-none');
      $('#paid_amount').prop('readonly', true);
      $('#paidAmountHelp').text('Enter total EMI amount');
    }
    
    $('#payEmiModal').modal('show');
  });

  // Handle Form Submission
  $('#payEmiForm').on('submit', function (e) {
    e.preventDefault();

    const form = $(this);
    const submitBtn = $('#btnSubmitPayment');

    // Show loading
    submitBtn.prop('disabled', true);
    submitBtn.find('.spinner-border').removeClass('d-none');

    $.ajax({
      url: baseUrl + 'emi/receipts/create',
      type: 'POST',
      data: form.serialize(),
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
            <h6 class="text-success mb-3">${msg}</h6>
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
})();