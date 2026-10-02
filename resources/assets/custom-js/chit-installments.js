'use strict';

function initChitInstallments() {
  // Bind once per page load: a second pass would re-attach the payment handlers.
  if (window.chitInstallmentsInitialised) {
    return;
  }
  window.chitInstallmentsInitialised = true;

  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) {
    baseUrl += '/';
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const today = new Date().toISOString().split('T')[0];

  function formatMoney(value) {
    return '₹' + Number(value || 0).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function showAlert(type, title, message) {
    const icon = type === 'danger' ? 'error' : type;
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon,
        title,
        text: message,
        confirmButtonText: 'OK'
      });
    } else {
      alert(`${title}: ${message}`);
    }
  }

  function buildBankDetailsHtml(option) {
    if (!option) return '';
    const bankName = option.getAttribute('data-bank-name') || 'N/A';
    const accountName = option.getAttribute('data-account-name') || 'N/A';
    const accountNumber = option.getAttribute('data-account-number') || 'N/A';
    const branchName = option.getAttribute('data-branch-name') || 'N/A';
    const accountType = option.getAttribute('data-account-type') || '';
    const ifsc = option.getAttribute('data-ifsc') || '';
    const upiId = option.getAttribute('data-upi-id') || '';

    let html = `
      <p class="mb-1"><strong>Bank:</strong> ${bankName}</p>
      <p class="mb-1"><strong>Account Name:</strong> ${accountName}</p>
      <p class="mb-1"><strong>Account Number:</strong> ${accountNumber}</p>
      <p class="mb-1"><strong>Branch:</strong> ${branchName}</p>
    `;
    if (accountType) {
      html += `<p class="mb-1"><strong>Account Type:</strong> ${accountType.charAt(0).toUpperCase() + accountType.slice(1)}</p>`;
    }
    if (ifsc) {
      html += `<p class="mb-1"><strong>IFSC:</strong> ${ifsc}</p>`;
    }
    if (upiId) {
      html += `<p class="mb-1"><strong>UPI ID:</strong> ${upiId}</p>`;
    }
    html += '<p class="text-muted small mb-0 mt-2">Pay to this account. Collection will be credited to the selected bank.</p>';
    return html;
  }

  function autoSelectFirstBank(bankSelect) {
    if (!bankSelect) return '';
    if (bankSelect.value) return bankSelect.value;
    for (let i = 0; i < bankSelect.options.length; i++) {
      if (bankSelect.options[i].value) {
        bankSelect.value = bankSelect.options[i].value;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2
            && window.jQuery(bankSelect).hasClass('select2-hidden-accessible')) {
          window.jQuery(bankSelect).val(bankSelect.value).trigger('change.select2');
        }
        return bankSelect.value;
      }
    }
    return '';
  }

  function updateChitBankDisplay(method, option, detailsCard, qrBankName, qrUpiId, qrImage, bankTransferContent) {
    if (detailsCard) detailsCard.classList.add('d-none');
    if (qrImage) qrImage.innerHTML = '';
    if (bankTransferContent) bankTransferContent.innerHTML = '';

    if ((method !== 'upi' && method !== 'bank_transfer') || !option || !option.value) {
        return;
      }

        const bankName = option.getAttribute('data-bank-name') || '';
        const upiId = option.getAttribute('data-upi-id') || 'N/A';
        const qrCodeUrl = option.getAttribute('data-qr-code') || '';

    if (qrBankName) qrBankName.textContent = bankName || 'Bank Account';
    if (qrUpiId) qrUpiId.textContent = upiId || 'N/A';
    if (bankTransferContent) bankTransferContent.innerHTML = buildBankDetailsHtml(option);
        if (qrImage) {
          qrImage.innerHTML = qrCodeUrl
        ? `<img src="${qrCodeUrl}" alt="QR Code" class="img-fluid my-1" style="max-height: 220px; border: 1px solid #eee; padding: 8px; border-radius: 8px;">`
        : `<div class="alert alert-warning py-2 mb-0 mt-1 small">No QR Code uploaded for this bank. Add it in Bank Accounts.</div>`;
    }
    if (detailsCard) detailsCard.classList.remove('d-none');

    // Emphasize QR for UPI; keep full bank details for transfer.
    const qrPanel = detailsCard ? detailsCard.querySelector('[id$="QrContainer"]') : null;
    const transferPanel = detailsCard ? detailsCard.querySelector('[id$="BankTransferPanel"]') : null;
    if (qrPanel) {
      qrPanel.classList.toggle('d-none', method === 'bank_transfer' && !qrCodeUrl && !upiId);
    }
    if (transferPanel) {
      transferPanel.classList.remove('d-none');
    }
  }

  function toggleBankUi(methodSelect, bankContainer, detailsCard, bankSelect, qrBankName, qrUpiId, qrImage, bankTransferContent) {
    if (!methodSelect) return;

    const method = methodSelect.value;
    if (bankContainer && bankSelect) {
      if (method === 'upi' || method === 'bank_transfer') {
        bankContainer.classList.remove('d-none');
        bankSelect.required = true;
        autoSelectFirstBank(bankSelect);
      } else {
        bankContainer.classList.add('d-none');
        bankSelect.required = false;
        bankSelect.value = '';
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2
            && window.jQuery(bankSelect).hasClass('select2-hidden-accessible')) {
          window.jQuery(bankSelect).val('').trigger('change.select2');
        }
      }
    }

    const option = bankSelect && bankSelect.selectedIndex >= 0
      ? bankSelect.options[bankSelect.selectedIndex]
      : null;
    updateChitBankDisplay(method, option, detailsCard, qrBankName, qrUpiId, qrImage, bankTransferContent);
  }

  function bindBankSelect(methodSelect, bankSelect, detailsCard, qrBankName, qrUpiId, qrImage, bankTransferContent) {
    if (!bankSelect) return;

    bankSelect.addEventListener('change', function () {
      const method = methodSelect ? methodSelect.value : '';
      const option = this.options[this.selectedIndex];
      updateChitBankDisplay(method, option, detailsCard, qrBankName, qrUpiId, qrImage, bankTransferContent);
    });
  }

  function ensureCollectBankSelected(form) {
    const methodSelect = form.querySelector('select[name="payment_mode"]');
    const bankSelect = form.querySelector('select[name="internal_bank_account_id"]');
    const method = methodSelect ? methodSelect.value : '';
    if (method !== 'upi' && method !== 'bank_transfer') {
      return { ok: true, method: method };
    }
    if (!bankSelect) {
      return { ok: false, method: method, message: 'Collection bank field is missing on this form.' };
    }
    let value = bankSelect.value;
    if (!value) {
      value = autoSelectFirstBank(bankSelect);
    }
    if (!value) {
      const container = bankSelect.closest('.mb-3') || bankSelect.parentElement;
      if (container) container.classList.remove('d-none');
      bankSelect.focus();
      return {
        ok: false,
        method: method,
        message: 'Please select a collection bank account. Its UPI ID / QR will show below for payment.'
      };
    }
    return { ok: true, method: method, bankId: value };
  }

  const payMethod = document.getElementById('chitPayPaymentMethod');
  const payBankContainer = document.getElementById('chitPayBankAccountContainer');
  const payBankSelect = document.getElementById('chitPayBankAccount');
  const payDetailsCard = document.getElementById('chitPayBankDetailsCard');
  const payBankTransferContent = document.getElementById('chitPayBankTransferContent');

  if (payMethod) {
    payMethod.addEventListener('change', function () {
      toggleBankUi(
        payMethod,
        payBankContainer,
        payDetailsCard,
        payBankSelect,
        document.getElementById('chitPayQrBankName'),
        document.getElementById('chitPayQrUpiId'),
        document.getElementById('chitPayQrImage'),
        payBankTransferContent
      );
      const walletInfo = document.getElementById('chitPayWalletInfo');
      if (walletInfo) {
        walletInfo.classList.toggle('d-none', payMethod.value !== 'wallet');
      }
    });
  }

  const payModalEl = document.getElementById('chitPayModal');
  if (payModalEl) {
    payModalEl.addEventListener('shown.bs.modal', function () {
      if (payMethod) {
        payMethod.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  function loadWalletBalance(clientId, balanceEl, infoEl) {
    if (!clientId || !balanceEl) return;
    balanceEl.textContent = 'Loading...';
    if (infoEl) infoEl.classList.remove('d-none');
    fetch('/admin/fd/wallets/client/' + clientId + '/balance', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(data => {
        balanceEl.textContent = data.balance_formatted || ('₹' + Number(data.balance || 0).toFixed(2));
      })
      .catch(() => {
        balanceEl.textContent = 'Unavailable';
      });
  }

  bindBankSelect(
    payMethod,
    payBankSelect,
    payDetailsCard,
    document.getElementById('chitPayQrBankName'),
    document.getElementById('chitPayQrUpiId'),
    document.getElementById('chitPayQrImage'),
    payBankTransferContent
  );

  const partialMethod = document.getElementById('chitPartialPaymentMethod');
  const partialBankContainer = document.getElementById('chitPartialBankAccountContainer');
  const partialBankSelect = document.getElementById('chitPartialBankAccount');
  const partialDetailsCard = document.getElementById('chitPartialBankDetailsCard');
  const partialBankTransferContent = document.getElementById('chitPartialBankTransferContent');

  if (partialMethod) {
    partialMethod.addEventListener('change', function () {
      toggleBankUi(
        partialMethod,
        partialBankContainer,
        partialDetailsCard,
        partialBankSelect,
        document.getElementById('chitPartialQrBankName'),
        document.getElementById('chitPartialQrUpiId'),
        document.getElementById('chitPartialQrImage'),
        partialBankTransferContent
      );
      const walletInfo = document.getElementById('chitPartialWalletInfo');
      if (walletInfo) {
        walletInfo.classList.toggle('d-none', partialMethod.value !== 'wallet');
      }
    });
  }

  const partialModalEl = document.getElementById('chitPartialModal');
  if (partialModalEl) {
    partialModalEl.addEventListener('shown.bs.modal', function () {
      if (partialMethod) {
        partialMethod.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  bindBankSelect(
    partialMethod,
    partialBankSelect,
    partialDetailsCard,
    document.getElementById('chitPartialQrBankName'),
    document.getElementById('chitPartialQrUpiId'),
    document.getElementById('chitPartialQrImage'),
    partialBankTransferContent
  );

  function submitCollectForm(form, submitBtn, modalId) {
    const defaultSubmitHtml = submitBtn.dataset.defaultHtml || submitBtn.innerHTML;
    submitBtn.dataset.defaultHtml = defaultSubmitHtml;

    function resetSubmitBtn() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = submitBtn.dataset.defaultHtml || defaultSubmitHtml;
    }

    const modalEl = document.getElementById(modalId);
    if (modalEl) {
      modalEl.addEventListener('show.bs.modal', resetSubmitBtn);
      modalEl.addEventListener('hidden.bs.modal', resetSubmitBtn);
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      const bankCheck = ensureCollectBankSelected(form);
      if (!bankCheck.ok) {
        showAlert('warning', 'Bank Required', bankCheck.message);
        return;
      }

      const method = bankCheck.method || '';
      const formData = new FormData(form);
      if (method === 'in_hand' || method === 'cash' || method === 'wallet') {
        formData.delete('internal_bank_account_id');
      } else if (bankCheck.bankId) {
        formData.set('internal_bank_account_id', bankCheck.bankId);
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';

      fetch(form.action, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
        .then(async (response) => {
          const data = await response.json().catch(() => ({}));
          if (!response.ok) {
            let message = data.message || 'Payment failed';
            if (data.errors) {
              message = Object.values(data.errors).flat().join(' ');
            }
            throw new Error(message);
          }
          return data;
        })
        .then((data) => {
          if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
          }
          // Restore immediately so the next open is not stuck on "Processing..."
          resetSubmitBtn();

          if (data.sms_data) {
            const d = data.sms_data;
            const cleanMobile = d.mobile_no || '';
            const msgText = d.sms_message || '';
            const waMsgText = d.whatsapp_message || '';

            const waUrl = `https://wa.me/${cleanMobile}?text=${encodeURIComponent(waMsgText)}`;

            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            const smsSeparator = isIOS ? '&' : '?';
            const smsUrl = `sms:+${cleanMobile}${smsSeparator}body=${encodeURIComponent(msgText)}`;

            const titleText = data.status === 'partial' ? 'Partial Payment Successful!' : 'Payment Successful!';
            const badgeHtml = data.status === 'partial' 
              ? `<span class="badge bg-label-warning mb-3 fs-6 px-3 py-2"><i class="ri-alert-line me-1"></i>Partially Paid</span>` 
              : `<span class="badge bg-label-success mb-3 fs-6 px-3 py-2"><i class="ri-checkbox-circle-line me-1"></i>Fully Paid</span>`;

            Swal.fire({
              title: titleText,
              icon: 'success',
              html: `
                <div class="py-2 text-center">
                  ${badgeHtml}
                  <h6 class="text-success mb-3">${data.message || 'Payment collected successfully.'}</h6>
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
              try {
                const filters = {};
                document.querySelectorAll('select.member-inst-filter').forEach(function (sel, idx) {
                  filters[sel.getAttribute('data-table-id') || ('idx-' + idx)] = sel.value;
                });
                const periodSel = document.querySelector('.group-month-period-filter');
                const statusSel = document.querySelector('.group-month-status-filter');
                if (periodSel) filters.__period = periodSel.value;
                if (statusSel) filters.__status = statusSel.value;
                sessionStorage.setItem('chitInstFilterState', JSON.stringify(filters));
              } catch (e) { /* ignore */ }

              if (typeof window.reloadChitGroupView === 'function' && document.getElementById('group-show-ajax-root')) {
                window.reloadChitGroupView();
              } else {
                window.location.reload();
              }
            });
          } else {
            Swal.fire({
              icon: 'success',
              title: data.status === 'partial' ? 'Partially Paid' : 'Payment Successful',
              text: data.message || 'Payment collected successfully.',
              confirmButtonText: 'OK'
            }).then(() => {
              try {
                const filters = {};
                document.querySelectorAll('select.member-inst-filter').forEach(function (sel, idx) {
                  filters[sel.getAttribute('data-table-id') || ('idx-' + idx)] = sel.value;
                });
                const periodSel = document.querySelector('.group-month-period-filter');
                const statusSel = document.querySelector('.group-month-status-filter');
                if (periodSel) filters.__period = periodSel.value;
                if (statusSel) filters.__status = statusSel.value;
                sessionStorage.setItem('chitInstFilterState', JSON.stringify(filters));
              } catch (e) { /* ignore */ }

              if (typeof window.reloadChitGroupView === 'function' && document.getElementById('group-show-ajax-root')) {
                window.reloadChitGroupView();
              } else {
                window.location.reload();
              }
            });
          }
        })
        .catch((error) => {
          showAlert('danger', 'Error', error.message || 'Something went wrong.');
          resetSubmitBtn();
        });
    });
  }

  const payForm = document.getElementById('chitPayForm');
  const paySubmitBtn = document.getElementById('chitPaySubmitBtn');
  if (payForm && paySubmitBtn) {
    submitCollectForm(payForm, paySubmitBtn, 'chitPayModal');
  }

  const partialForm = document.getElementById('chitPartialForm');
  const partialSubmitBtn = document.getElementById('chitPartialSubmitBtn');
  const partialAmountInput = document.getElementById('chitPartialAmount');

  if (partialAmountInput) {
    partialAmountInput.addEventListener('input', function () {
      const amount = parseFloat(this.value);
      const min = parseFloat(this.getAttribute('min'));
      const max = parseFloat(this.getAttribute('max'));
      const errorDiv = document.getElementById('chitPartialAmountError');

      if (!errorDiv || isNaN(amount)) {
        this.classList.remove('is-invalid');
        return;
      }

      if (amount < min) {
        this.classList.add('is-invalid');
        errorDiv.textContent = `Amount must be at least ₹${min}`;
        errorDiv.style.display = 'block';
      } else if (amount > max) {
        this.classList.add('is-invalid');
        errorDiv.textContent = `Amount cannot exceed ₹${max}`;
        errorDiv.style.display = 'block';
      } else {
        this.classList.remove('is-invalid');
        errorDiv.style.display = 'none';
      }
    });
  }

  if (partialForm && partialSubmitBtn) {
    partialForm.addEventListener('submit', function (e) {
      const amount = parseFloat(partialAmountInput?.value || '0');
      const min = parseFloat(partialAmountInput?.getAttribute('min') || '0');
      const max = parseFloat(partialAmountInput?.getAttribute('max') || '0');

      if (amount < min || amount > max) {
        e.preventDefault();
        showAlert('danger', 'Invalid Amount', `Enter an amount between ₹${min} and ₹${max}.`);
        return;
      }

    });

    submitCollectForm(partialForm, partialSubmitBtn, 'chitPartialModal');
  }
  document.addEventListener('click', function (e) {
    const payBtn = e.target.closest('.chit-pay-btn');
    if (payBtn) {
      const isConsolidated = payBtn.dataset.isConsolidated === '1';
      const memberNumbers = payBtn.dataset.memberNumbers || '';
      const singleAmount = parseFloat(payBtn.dataset.singleAmount) || parseFloat(payBtn.dataset.amount) || 0;
      const cumulativeAmount = parseFloat(payBtn.dataset.cumulativeAmount) || parseFloat(payBtn.dataset.amount) || 0;
      const balance = parseFloat(payBtn.dataset.balance) || 0;
      const amount = parseFloat(payBtn.dataset.amount) || 0;
      const penalty = parseFloat(payBtn.dataset.penalty) || 0;

      const payFullAmount = balance > 0 ? balance : amount;
      const alreadyPaid = parseFloat(payBtn.dataset.paid) || 0;
      document.getElementById('chitPayPeriodLabel').textContent = '— ' + (payBtn.dataset.period || '');
      document.getElementById('chitPayClient').value = payBtn.dataset.client || '';
      // After a partial payment, show remaining balance — not the original full EMI.
      document.getElementById('chitPayInstallmentAmount').value = payFullAmount.toFixed(2);
      const installmentCardLabel = document.querySelector('#chitPayInstallmentAmount')?.closest('.card-body')?.querySelector('small');
      if (installmentCardLabel) {
        installmentCardLabel.textContent = (alreadyPaid > 0.009 || (balance > 0.009 && balance + 0.009 < amount))
          ? 'Balance Due'
          : 'Installment';
      }
      const payModalHint = document.querySelector('#chitPayModalLabel')?.parentElement?.querySelector('small');
      if (payModalHint) {
        payModalHint.textContent = alreadyPaid > 0.009
          ? 'Collect remaining balance after partial payment'
          : 'Full installment payment collection';
      }
      document.getElementById('chitPayPenaltyAmount').value = penalty.toFixed(2);
      document.getElementById('chitPayPaidAmount').value = payFullAmount.toFixed(2);
      document.getElementById('chitPayPaidAmount').setAttribute('readonly', 'readonly');
      document.getElementById('chitPayPaidDate').value = today;
      document.getElementById('chitPayReference').value = '';
      if (document.getElementById('chitPayClientId')) {
        document.getElementById('chitPayClientId').value = payBtn.dataset.clientId || '';
      }

      const seatOptionContainer = document.getElementById('chitPaySeatOptionContainer');
      const seatsListLabel = document.getElementById('chitPaySeatsListLabel');
      const cumulativeRadio = document.getElementById('chitPaySeatCumulative');
      const singleRadio = document.getElementById('chitPaySeatSingle');
      const forceSingleSeat = payBtn.dataset.singleSeatOnly === '1';

      if (isConsolidated && seatOptionContainer && !forceSingleSeat) {
        seatOptionContainer.classList.remove('d-none');
        if (seatsListLabel) seatsListLabel.textContent = memberNumbers ? '#' + memberNumbers : 'multiple seats';
        if (cumulativeRadio) cumulativeRadio.checked = true;

        const updatePayAmounts = () => {
          const isSingle = singleRadio && singleRadio.checked;
          const targetAmount = isSingle ? singleAmount : cumulativeAmount;
          const targetFullAmount = balance > 0 ? (isSingle ? Math.min(balance, singleAmount) : balance) : targetAmount;

          document.getElementById('chitPayInstallmentAmount').value = targetFullAmount.toFixed(2);
          document.getElementById('chitPayPaidAmount').value = targetFullAmount.toFixed(2);
          if (installmentCardLabel) {
            installmentCardLabel.textContent = (alreadyPaid > 0.009 || (balance > 0.009 && balance + 0.009 < amount))
              ? 'Balance Due'
              : 'Installment';
          }
        };

        updatePayAmounts();

        document.querySelectorAll('.chit-seat-mode-radio').forEach(r => {
          r.onchange = updatePayAmounts;
        });
      } else if (seatOptionContainer) {
        seatOptionContainer.classList.add('d-none');
        // Group-view / specific-seat pay must not spill into the client's other seats (e.g. Gokul A vs B).
        if (forceSingleSeat || !isConsolidated) {
          if (singleRadio) singleRadio.checked = true;
        } else if (cumulativeRadio) {
          cumulativeRadio.checked = true;
        }
      }

      payForm.action = payBtn.dataset.collectUrl;
      if (payMethod) {
        payMethod.value = 'in_hand';
        payMethod.dispatchEvent(new Event('change'));
      }

      const clientId = payBtn.dataset.clientId;
      loadWalletBalance(
        clientId,
        document.getElementById('chitPayWalletBalance'),
        document.getElementById('chitPayWalletInfo')
      );

      bootstrap.Modal.getOrCreateInstance(document.getElementById('chitPayModal')).show();
      return;
    }

    const partialBtn = e.target.closest('.chit-partial-btn');
    if (partialBtn) {
      e.preventDefault();
      const openPartialModal = (rules) => {
        if (!partialForm || !partialAmountInput) {
          showAlert('danger', 'Partial Payment', 'Partial payment form is not available on this page.');
          return;
        }

        const isConsolidated = partialBtn.dataset.isConsolidated === '1';
        const memberNumbers = partialBtn.dataset.memberNumbers || '';
        const singleAmount = parseFloat(partialBtn.dataset.singleAmount) || parseFloat(partialBtn.dataset.amount) || 0;
        const cumulativeAmount = parseFloat(partialBtn.dataset.cumulativeAmount) || parseFloat(partialBtn.dataset.amount) || 0;
        const partialSingleRadio = document.getElementById('chitPartialSeatSingle');
        const isSingleSelected = partialSingleRadio && partialSingleRadio.checked;

        const amount = isSingleSelected ? singleAmount : (parseFloat(partialBtn.dataset.amount) || 0);
        const penalty = parseFloat(partialBtn.dataset.penalty) || 0;
        const paid = parseFloat(partialBtn.dataset.paid) || 0;
        let balance = parseFloat(partialBtn.dataset.balance) || 0;
        if (isSingleSelected && balance > singleAmount) {
          balance = singleAmount;
        }
        const minPct = parseFloat(partialBtn.dataset.minPercentage) || 10;
        const penaltyMethod = partialBtn.dataset.penaltyMethod || 'emi_amount';

        if (rules && Object.prototype.hasOwnProperty.call(rules, 'allows_partial') && !rules.allows_partial
            && partialBtn.dataset.forceFrequencyPartial !== '1') {
          showAlert('warning', 'Partial payment not allowed', rules.timing_message || 'Partial payments are not allowed for this installment.');
          return;
        }

        let minimumAmount;
        let maxAmount;
        const forceFrequency = partialBtn.dataset.forceFrequencyPartial === '1';
        const suggested = parseFloat(partialBtn.dataset.suggestedAmount) || 0;

        if (rules && rules.is_active && !forceFrequency) {
          balance = rules.outstanding_due ?? balance;
          minimumAmount = parseFloat(rules.minimum_partial_amount) || 0;
          maxAmount = parseFloat(rules.maximum_partial_amount);
          if (Number.isNaN(maxAmount)) {
            maxAmount = Math.max(0, Math.round(balance));
          }
        } else {
          const minBase = penaltyMethod === 'emi_plus_partial_remaining' ? balance : amount;
          // Daily/weekly: any amount from ₹1 up to remaining month balance (partial toward installment).
          minimumAmount = forceFrequency
            ? 1
            : Math.ceil((minBase * minPct) / 100);
          maxAmount = Math.max(0, Math.round(balance * 100) / 100);
          if (minimumAmount > maxAmount) {
            minimumAmount = maxAmount;
          }
        }

        if (maxAmount < 1 || minimumAmount > maxAmount) {
          showAlert('warning', 'Partial payment not allowed', 'Remaining balance is too low for a partial payment. Please use full payment.');
          return;
        }

        const forceFreqInput = document.getElementById('chitPartialForceFrequency');
        if (forceFreqInput) {
          forceFreqInput.value = forceFrequency ? '1' : '0';
        }
        const partialHint = document.getElementById('chitPartialModalHint');
        if (partialHint) {
          partialHint.textContent = forceFrequency
            ? 'Partial toward this month’s installment (not full pay)'
            : 'Custom partial payment collection';
        }

        const partialSeatOptionContainer = document.getElementById('chitPartialSeatOptionContainer');
        const partialSeatsListLabel = document.getElementById('chitPartialSeatsListLabel');
        const partialCumulativeRadio = document.getElementById('chitPartialSeatCumulative');
        const partialSingleRadioBtn = document.getElementById('chitPartialSeatSingle');
        const forceSingleSeat = partialBtn.dataset.singleSeatOnly === '1';

        if (isConsolidated && partialSeatOptionContainer && !forceSingleSeat) {
          partialSeatOptionContainer.classList.remove('d-none');
          if (partialSeatsListLabel) partialSeatsListLabel.textContent = memberNumbers ? '#' + memberNumbers : 'multiple seats';

          document.querySelectorAll('.chit-partial-seat-mode-radio').forEach(r => {
            r.onchange = () => {
              openPartialModal(rules);
            };
          });
        } else if (partialSeatOptionContainer) {
          partialSeatOptionContainer.classList.add('d-none');
          if (forceSingleSeat || !isConsolidated) {
            if (partialSingleRadioBtn) partialSingleRadioBtn.checked = true;
          } else if (partialCumulativeRadio) {
            partialCumulativeRadio.checked = true;
          }
        }

        document.getElementById('chitPartialPeriodLabel').textContent = '— ' + (partialBtn.dataset.period || '');
        document.getElementById('chitPartialClient').value = partialBtn.dataset.client || '';
        document.getElementById('chitPartialInstallmentDisplay').textContent = formatMoney(amount);
        document.getElementById('chitPartialPaidDisplay').textContent = formatMoney(paid);
        document.getElementById('chitPartialRemainingDisplay').textContent = formatMoney(balance);
        document.getElementById('chitPartialPaidDate').value = today;
        document.getElementById('chitPartialReference').value = '';
        if (document.getElementById('chitPartialClientId')) {
          document.getElementById('chitPartialClientId').value = partialBtn.dataset.clientId || '';
        }
        partialForm.action = partialBtn.dataset.collectUrl;
        partialAmountInput.setAttribute('min', String(minimumAmount));
        partialAmountInput.setAttribute('max', String(maxAmount));

        const freq = (partialBtn.dataset.collectionFrequency || 'monthly').toLowerCase();
        const suggestedAmt = parseFloat(partialBtn.dataset.suggestedAmount) || 0;
        const splitCount = parseInt(partialBtn.dataset.splitCount || '1', 10) || 1;
        let defaultPartial = minimumAmount;
        if ((freq === 'daily' || freq === 'weekly' || forceFrequency) && suggestedAmt > 0) {
          defaultPartial = Math.min(Math.max(suggestedAmt, minimumAmount), maxAmount);
        }
        partialAmountInput.value = String(Math.round(defaultPartial * 100) / 100);
        partialAmountInput.classList.remove('is-invalid');

        const freqHelp = document.getElementById('chitPartialFrequencyHelp');
        if (freqHelp) {
          if (freq === 'daily' || freq === 'weekly' || forceFrequency) {
            const label = freq === 'daily' ? 'Daily' : (freq === 'weekly' ? 'Weekly' : 'Frequency');
            const partLabel = freq === 'weekly' ? 'week' : 'day';
            freqHelp.classList.remove('d-none');
            freqHelp.innerHTML = `<strong>${label} collection:</strong> this credits the <strong>monthly installment as a partial</strong>
              (does not mark the month fully paid). Month is split into ${splitCount} ${partLabel}s.
              Suggested ${partLabel}: <strong>${formatMoney(suggestedAmt || defaultPartial)}</strong>. Enter any amount up to the remaining month balance.`;
          } else {
            freqHelp.classList.add('d-none');
            freqHelp.innerHTML = '';
          }
        }

        const baseLabel = penaltyMethod === 'emi_plus_partial_remaining' ? 'outstanding balance' : 'installment amount';
        const minHelpEl = document.getElementById('chitPartialMinHelp');
        if (minHelpEl) {
          if (forceFrequency || freq === 'daily' || freq === 'weekly') {
            minHelpEl.textContent =
              `Partial toward this month: ₹${minimumAmount}–₹${maxAmount}. Suggested: ₹${Math.round((suggestedAmt || defaultPartial) * 100) / 100}. Month stays partial until fully paid.`;
          } else {
            minHelpEl.textContent =
          `Minimum: ₹${minimumAmount} (${minPct}% of ${baseLabel}). Max: ₹${maxAmount}`;
          }
        }

        if (partialMethod) {
          partialMethod.value = '';
          partialMethod.dispatchEvent(new Event('change'));
        }

        loadWalletBalance(
          partialBtn.dataset.clientId,
          document.getElementById('chitPartialWalletBalance'),
          document.getElementById('chitPartialWalletInfo')
        );

        bootstrap.Modal.getOrCreateInstance(document.getElementById('chitPartialModal')).show();
      };

      let rulesUrl = partialBtn.dataset.partialRulesUrl || '';
      const clientId = partialBtn.dataset.clientId || '';
      if (rulesUrl && clientId && !/[?&]client_id=/.test(rulesUrl)) {
        rulesUrl += (rulesUrl.includes('?') ? '&' : '?') + 'client_id=' + encodeURIComponent(clientId);
      }

      fetch(rulesUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => r.json())
        .then((rules) => openPartialModal(rules))
        .catch(() => openPartialModal(null));
    }
  });

  // ─── Index DataTable (EMI-style client view) ───────────────────────────────
  const statusSectionConfig = [
    { key: 'overdue', label: 'Overdue', color: 'danger' },
    { key: 'pending', label: 'Pending', color: 'warning' },
    { key: 'upcoming', label: 'Upcoming', color: 'secondary' },
    { key: 'partial', label: 'Partial Paid', color: 'info' },
    { key: 'paid', label: 'Paid', color: 'success' }
  ];

  function buildContactActionButtons(phoneNumber, inst, clientRow) {
    const phone = phoneNumber && phoneNumber !== 'N/A' ? phoneNumber : (inst?.client_phone || clientRow?.client_phone || '');
    const clientId = clientRow?.client_id || clientRow?.id || inst?.client_id;
    const publicToken = inst?.public_token || clientRow?.public_token;
    let clientPublicLink = inst?.public_link || clientRow?.public_link || '';

    if (!clientPublicLink && publicToken) {
      clientPublicLink = `${baseUrl}view-chit-schedule/${publicToken}`;
    } else if (!clientPublicLink && clientId) {
      clientPublicLink = `${baseUrl}view-chit-schedule/${clientId}`;
    }

    const copyLinkBtn = clientPublicLink
      ? `<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-copy-public-link" data-link="${clientPublicLink}" title="Copy Public Schedule Link"><i class="icon-base ri ri-link icon-20px"></i></button>`
      : '';

    const callBtn = phone && phone !== 'N/A'
      ? `<a href="tel:${String(phone).replace(/\s/g, '')}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Call Client"><i class="icon-base ri ri-phone-line icon-20px"></i></a>`
      : '';

    let whatsappBtn = '';
    let smsBtn = '';
    const status = inst?.status || '';
    const paidAmount = parseFloat(inst?.paid_amount || 0);
    const balance = parseFloat(inst?.balance || inst?.share_balance || 0);
    const amountDue = balance > 0 ? balance : parseFloat(inst?.amount || 0);
    const clientName = inst?.client_base_name || inst?.client_name || clientRow?.client_name || 'Client';
    const isPartialPayment = status === 'partial' || (paidAmount > 0 && status !== 'paid');

    if (phone && phone !== 'N/A') {
      let cleanPhone = String(phone).replace(/\D/g, '');
      if (cleanPhone.length === 10) {
        cleanPhone = '91' + cleanPhone;
      }

      const companySlogan = inst?.company_slogan || clientRow?.company_slogan || 'Codepluse Gen PVT Ltd';
      const companyPhone = inst?.company_phone || clientRow?.company_phone || '';
      const groupCode = inst?.group_code || clientRow?.group_code || '';
      const period = inst?.period_label || ('Month ' + (inst?.month_number || ''));
      const paidDate = inst?.paid_date_formatted || inst?.paid_date || '';
      const dueDate = inst?.due_date || '';
      const linkPart = clientPublicLink ? `\n\nPlease check your Chit Schedule here: ${clientPublicLink}` : '';

      let waMessage = '';
      let smsMessage = '';
      let waTitle = 'Send WhatsApp Message';
      let smsTitle = 'Send SMS Message';

      if (status === 'paid' || isPartialPayment) {
        if (isPartialPayment) {
          waTitle = 'Send WhatsApp Partial Payment Confirmation';
          smsTitle = 'Send SMS Partial Payment Confirmation';
          waMessage = `Dear Customer, a partial payment of *Rs.${paidAmount}* for your Chit Group *${groupCode}* (${period}) has been successfully received on *${paidDate}*.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
          smsMessage = `Dear Customer, a partial payment of Rs.${paidAmount} for your Chit Group ${groupCode} (${period}) has been successfully received on ${paidDate}.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
        } else {
          waTitle = 'Send WhatsApp Payment Confirmation';
          smsTitle = 'Send SMS Payment Confirmation';
          waMessage = `Dear Customer, your Chit installment for *Group ${groupCode}* (${period}) of *Rs.${paidAmount}* has been successfully paid on *${paidDate}*.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
          smsMessage = `Dear Customer, your Chit installment for Group ${groupCode} (${period}) of Rs.${paidAmount} has been successfully paid on ${paidDate}.${linkPart}\n\nThank you for choosing ${companySlogan}. For queries, contact us at ${companyPhone}.`;
        }
      } else if (status === 'overdue') {
        waTitle = 'Send Overdue Reminder via WhatsApp';
        smsTitle = 'Send Overdue Reminder via SMS';
        waMessage = `Dear *${clientName}*, your Chit installment for *Group ${groupCode}* (${period}) of *Rs.${amountDue}* was due on *${dueDate}*. Please pay at your earliest convenience to avoid additional penalty charges.${linkPart}\n\nThank you, ${companySlogan}. For queries: ${companyPhone}.`;
        smsMessage = `Dear ${clientName}, your Chit installment for Group ${groupCode} (${period}) of Rs.${amountDue} was due on ${dueDate}. Please pay at your earliest convenience to avoid additional penalty charges.${linkPart}\n\nThank you, ${companySlogan}.`;
      } else {
        waTitle = 'Send Pending Reminder via WhatsApp';
        smsTitle = 'Send Pending Reminder via SMS';
        waMessage = `Dear *${clientName}*, your upcoming Chit installment for *Group ${groupCode}* (${period}) of *Rs.${amountDue}* is due on *${dueDate}*. Kindly arrange payment on or before the due date.${linkPart}\n\nThank you, ${companySlogan}. For queries: ${companyPhone}.`;
        smsMessage = `Dear ${clientName}, your upcoming Chit installment for Group ${groupCode} (${period}) of Rs.${amountDue} is due on ${dueDate}. Kindly arrange payment on or before the due date.${linkPart}\n\nThank you, ${companySlogan}.`;
      }

      whatsappBtn = `<a href="https://wa.me/${cleanPhone}?text=${encodeURIComponent(waMessage)}" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-success" title="${waTitle}"><i class="icon-base ri ri-whatsapp-line icon-20px"></i></a>`;

      const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
      const smsSeparator = isIOS ? '&' : '?';
      smsBtn = `<a href="sms:+${cleanPhone}${smsSeparator}body=${encodeURIComponent(smsMessage)}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info" title="${smsTitle}"><i class="icon-base ri ri-message-3-line icon-20px"></i></a>`;
    }

    return { copyLinkBtn, callBtn, whatsappBtn, smsBtn };
  }

  function renderInstallmentActions(inst, clientRow) {
    const { copyLinkBtn, callBtn, whatsappBtn, smsBtn } = buildContactActionButtons(
      inst.client_phone || clientRow?.client_phone,
      inst,
      clientRow
    );

    let html = '<div class="d-flex justify-content-center align-items-center gap-1 text-nowrap">';
    let hasActions = false;
    let menuItems = '';

    if (inst.can_collect) {
      hasActions = true;
      const freq = (inst.collection_frequency || 'monthly').toLowerCase();
      const isFreq = freq === 'daily' || freq === 'weekly';
      const payLabel = isFreq
        ? `Pay Full ${inst.period_label || 'Month'}`
        : (inst.period_label ? `Pay ${inst.period_label}` : 'Pay');

      const payClientName = inst.client_base_name || inst.client_name || clientRow?.client_name || '';
      const remainingBalance = parseFloat(inst.balance || inst.share_balance || 0);
      const installmentAmount = parseFloat(inst.amount || 0);
      const nextPeriod = inst.next_period || null;
      const forceFreq = inst.force_frequency_partial ? '1' : '0';

      if (nextPeriod && nextPeriod.balance > 0.009) {
        menuItems += `<a href="javascript:void(0);" class="dropdown-item chit-partial-btn text-primary"
          data-installment-id="${inst.id}"
          data-collect-url="${inst.collect_url}"
          data-partial-rules-url="${inst.partial_rules_url}"
          data-client="${payClientName}"
          data-client-id="${inst.client_id || ''}"
          data-period="${inst.period_label} — ${nextPeriod.label}"
          data-amount="${installmentAmount}"
          data-penalty="${inst.penalty_amount}"
          data-paid="${inst.paid_amount}"
          data-balance="${remainingBalance}"
          data-is-consolidated="${inst.is_consolidated ? 1 : 0}"
          data-member-numbers="${inst.member_numbers || inst.member_number || ''}"
          data-single-amount="${inst.single_amount || remainingBalance || installmentAmount}"
          data-cumulative-amount="${inst.cumulative_amount || remainingBalance || installmentAmount}"
          data-single-seat-only="${inst.is_shared ? 1 : 0}"
          data-collection-frequency="${freq}"
          data-suggested-amount="${nextPeriod.balance}"
          data-split-amount="${nextPeriod.amount}"
          data-split-count="${inst.collection_split_count || 1}"
          data-force-frequency-partial="1"
          data-min-percentage="1"
          data-penalty-method="${inst.penalty_method || 'emi_amount'}">
          <i class="ri-calendar-check-line me-2 text-primary"></i>Pay ${nextPeriod.label} (₹${Number(nextPeriod.balance).toFixed(2)})
        </a>`;
      }

      menuItems += `<a href="javascript:void(0);" class="dropdown-item chit-pay-btn"
        data-installment-id="${inst.id}"
        data-collect-url="${inst.collect_url}"
        data-partial-rules-url="${inst.partial_rules_url}"
        data-client="${payClientName}"
        data-client-id="${inst.client_id || ''}"
        data-period="${inst.period_label}"
        data-amount="${installmentAmount}"
        data-penalty="${inst.penalty_amount}"
        data-paid="${inst.paid_amount}"
        data-balance="${remainingBalance}"
        data-is-consolidated="${inst.is_consolidated ? 1 : 0}"
        data-member-numbers="${inst.member_numbers || inst.member_number || ''}"
        data-single-amount="${inst.single_amount || remainingBalance || installmentAmount}"
        data-cumulative-amount="${inst.cumulative_amount || remainingBalance || installmentAmount}"
        data-single-seat-only="${inst.is_shared ? 1 : 0}"
        data-collection-frequency="${freq}"
        data-suggested-amount="${inst.suggested_collection_amount || remainingBalance || installmentAmount}"
        data-split-amount="${inst.collection_split_amount || installmentAmount}"
        data-split-count="${inst.collection_split_count || 1}">
        <i class="ri-money-dollar-circle-line me-2 text-success"></i>${payLabel}
      </a>`;

      if (inst.partial_enabled || isFreq) {
        menuItems += `<a href="javascript:void(0);" class="dropdown-item chit-partial-btn"
          data-installment-id="${inst.id}"
          data-collect-url="${inst.collect_url}"
          data-partial-rules-url="${inst.partial_rules_url}"
          data-client="${payClientName}"
          data-client-id="${inst.client_id || ''}"
          data-period="${inst.period_label}"
          data-amount="${installmentAmount}"
          data-penalty="${inst.penalty_amount}"
          data-paid="${inst.paid_amount}"
          data-balance="${remainingBalance}"
          data-is-consolidated="${inst.is_consolidated ? 1 : 0}"
          data-member-numbers="${inst.member_numbers || inst.member_number || ''}"
          data-single-amount="${inst.single_amount || remainingBalance || installmentAmount}"
          data-cumulative-amount="${inst.cumulative_amount || remainingBalance || installmentAmount}"
          data-single-seat-only="${inst.is_shared ? 1 : 0}"
          data-min-percentage="${isFreq ? 1 : (inst.min_percentage || 10)}"
          data-penalty-method="${inst.penalty_method}"
          data-collection-frequency="${freq}"
          data-suggested-amount="${inst.suggested_collection_amount || remainingBalance || installmentAmount}"
          data-split-amount="${inst.collection_split_amount || installmentAmount}"
          data-split-count="${inst.collection_split_count || 1}"
          data-force-frequency-partial="${forceFreq}">
          <i class="ri-percent-line me-2 text-info"></i>${isFreq ? 'Custom Partial' : 'Partially Pay'}
        </a>`;
      }
    } else if (inst.is_settled) {
      if (inst.status === 'paid') {
        html += `<small class="text-success me-1">Paid ${inst.paid_date || ''}</small>`;
      } else {
        html += '<small class="text-info me-1">Waived</small>';
      }
    } else {
      html += '<span class="badge bg-label-secondary me-1" title="Previous installment(s) pending"><i class="ri-lock-line me-1 text-danger"></i> Locked</span>';
    }

    if (inst.is_undoable) {
      hasActions = true;
      menuItems += `<a href="javascript:void(0);" class="dropdown-item text-danger" onclick="if(confirm('Undo this payment?')){ const f=document.createElement('form'); f.method='POST'; f.action='${inst.undo_url}'; f.innerHTML='<input type=hidden name=_token value=${csrfToken}><input type=hidden name=client_id value=${inst.client_id || ''}>'; document.body.appendChild(f); f.submit(); }">
        <i class="ri-history-line me-2 text-danger"></i>Undo Payment
      </a>`;
    }

    if (hasActions) {
      html += `<div class="dropdown">
        <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
          <i class="ri-more-2-fill fs-5"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm">
          ${menuItems}
        </div>
      </div>`;
    }

    html += copyLinkBtn + callBtn + whatsappBtn + smsBtn;
    html += '</div>';
    return html;
  }

  function renderClientRowActions(full) {
    const collectible = full.first_collectible;
    const latestPaid = full.latest_paid;
    const notifyInst = latestPaid || collectible;
    const { copyLinkBtn, callBtn, whatsappBtn, smsBtn } = buildContactActionButtons(full.client_phone, notifyInst, full);

    let html = '<div class="d-flex align-items-center gap-1 text-nowrap">';
    html += copyLinkBtn + callBtn + whatsappBtn + smsBtn;
    html += '</div>';
    return html;
  }

  // Copy public schedule link (vanilla — no jQuery required on group view)
  document.addEventListener('click', function (e) {
    const copyBtn = e.target.closest('.btn-copy-public-link');
    if (!copyBtn) return;
    e.preventDefault();
    const link = copyBtn.getAttribute('data-link');
    if (!link || link === '#') return;

      function showCopiedAlert() {
        showAlert('success', 'Copied!', 'Public schedule link copied to clipboard.');
      }
      function fallbackCopyText() {
        try {
          const input = document.createElement('input');
          input.value = link;
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          document.body.removeChild(input);
          showCopiedAlert();
        } catch (err) {
          prompt('Copy this link:', link);
        }
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(showCopiedAlert).catch(fallbackCopyText);
      } else {
        fallbackCopyText();
    }
  });

  function formatClientInstallmentDetails(clientRow) {
    const byGroup = clientRow.installments_by_group || [];
    const statusFilterEl = document.getElementById('statusFilter');
    const activeStatus = (statusFilterEl && statusFilterEl.value) || 'overdue';

    let html = '<div class="client-installment-details">';
    let renderedBlocks = 0;

    if (!byGroup.length) {
      const grouped = clientRow.installments_grouped || {};
      let hasAny = false;

      statusSectionConfig.forEach(({ key, label, color }) => {
        if (activeStatus !== 'all' && activeStatus !== key) return;
        const items = grouped[key] || [];
        if (!items.length) return;
        hasAny = true;
        html += renderStatusInstallmentTable(label, color, items, clientRow);
      });

      if (!hasAny) {
        html += '<p class="text-muted mb-0 p-2"><i class="ri-information-line me-1"></i>No installments for this tab.</p>';
      }
      html += '</div>';
      return html;
    }

    byGroup.forEach((groupBlock) => {
      const displayName = groupBlock.client_display_name || clientRow.client_name || '';
      const seatLetter = groupBlock.seat_letter || '';
      const groupCode = groupBlock.group_code || 'N/A';
      const memberNum = groupBlock.member_number || '';

      const seatBadgeHtml = seatLetter
        ? `<span class="badge bg-warning text-dark me-1">Seat ${seatLetter}</span>`
        : '';
      const memberBadgeHtml = memberNum
        ? `<small class="text-muted ms-2">Member #${memberNum}</small>`
        : '';

      let hasSection = false;
      let sectionHtml = '';
      let itemCount = 0;

      // Strict tab filter: Pending tab = pending only, Overdue tab = overdue only.
      statusSectionConfig.forEach(({ key, label, color }) => {
        if (activeStatus !== 'all' && activeStatus !== key) return;
        const items = groupBlock[key] || [];
        if (!items.length) return;
        hasSection = true;
        itemCount += items.length;
        sectionHtml += renderStatusInstallmentTable(label, color, items, clientRow);
      });

      if (!hasSection) return;

      renderedBlocks += 1;
      html += `<div class="border rounded mb-3 bg-white shadow-xs">
        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 bg-light">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-label-primary">Group ${groupCode}</span>
            ${seatBadgeHtml}
            <strong class="text-dark">${displayName}</strong>
            ${memberBadgeHtml}
          </div>
          <small class="text-muted">${itemCount} installment(s)</small>
        </div>
        <div class="p-3">`;

      html += sectionHtml;
      html += '</div></div>';
    });

    if (renderedBlocks === 0) {
      html += '<p class="text-muted mb-0 p-2"><i class="ri-information-line me-1"></i>No installments for this tab.</p>';
    }

    html += '</div>';
    return html;
  }

  function renderStatusInstallmentTable(label, color, items, clientRow) {
    const tableId = 'dt-inst-' + Math.random().toString(36).slice(2, 9);
    const hasFreq = items.some((inst) => {
      const freq = (inst.collection_frequency || 'monthly').toLowerCase();
      return (freq === 'daily' || freq === 'weekly') && (inst.collection_periods || []).length > 0;
    });

    let defaultMonth = 'all';
    if (hasFreq) {
      const firstUnpaid = items.find((inst) => inst.can_collect && parseFloat(inst.balance || 0) > 0.009);
      defaultMonth = firstUnpaid
        ? 'month-' + firstUnpaid.month_number
        : 'month-' + (items[0]?.month_number || 1);
    }

    let html = `<div class="inst-section-title text-${color} d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>${label} (${items.length})</span>`;

    if (hasFreq) {
      const freqLabel = ((items.find((i) => (i.collection_frequency || '') !== 'monthly') || {}).collection_frequency || 'daily') === 'weekly'
        ? 'Weekly'
        : 'Daily';
      html += `<div class="d-flex align-items-center gap-2">
        <label class="mb-0 small text-muted fw-semibold">${freqLabel} by Month:</label>
        <select class="form-select form-select-sm member-inst-filter no-search" style="min-width:190px;" data-table-id="${tableId}">
          <option value="all">All Months (${items.length})</option>
          <option value="unpaid">Pending &amp; Overdue</option>
          <option value="paid">Paid</option>`;
      items.forEach((inst) => {
        const days = (inst.collection_periods || []).length || inst.collection_split_count || '';
        const paidMark = inst.status === 'paid' || (parseFloat(inst.balance || 0) <= 0.009 && parseFloat(inst.paid_amount || 0) > 0.009)
          ? ' ✓ Paid'
          : '';
        const monthYear = inst.due_month_year ? ' — ' + inst.due_month_year : '';
        const optLabel = `Month ${inst.month_number}${monthYear}${days ? ' (' + days + (freqLabel === 'Daily' ? ' days' : ' weeks') + ')' : ''}${paidMark}`;
        const selected = defaultMonth === 'month-' + inst.month_number ? ' selected' : '';
        html += `<option value="month-${inst.month_number}"${selected}>${optLabel}</option>`;
      });
      html += '</select></div>';
    }

    html += '</div>';
    html += `<div class="table-responsive mb-3"><table class="table table-sm table-bordered align-middle mb-0" id="${tableId}">`;
    html += '<thead class="table-light"><tr>';
    html += '<th>Period</th><th>Client Seat</th><th>Group</th><th>Due Date</th><th>Amount</th><th>Penalty</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>';
    html += '</tr></thead><tbody>';

    items.forEach((inst) => {
      const seatName = inst.client_name || '—';
      const seatBadge = inst.seat_letter
        ? `<span class="badge bg-label-warning">${seatName}</span>`
        : `<small>${seatName}</small>`;
      const periods = inst.collection_periods || [];
      const monthKey = 'month-' + inst.month_number;
      const hidden = hasFreq && defaultMonth !== 'all' && defaultMonth !== monthKey;
      const hideStyle = hidden ? ' style="display:none"' : '';
      const periodNote = periods.length
        ? `<div class="text-muted small fw-normal" style="font-size:.65rem;">${periods.length} ${(inst.collection_frequency || '') === 'weekly' ? 'weeks' : 'days'}</div>`
        : '';

      html += `<tr class="inst-row" data-month="${monthKey}" data-status="${inst.status}"${hideStyle}>`;
      html += `<td class="fw-semibold">${inst.period_label}${periodNote}</td>`;
      html += `<td>${seatBadge}</td>`;
      html += `<td><small>${inst.group_code}</small></td>`;
      html += `<td>${inst.due_date}</td>`;
      html += `<td class="text-end">${inst.amount_formatted}</td>`;
      html += `<td class="text-end ${inst.penalty_amount > 0 ? 'text-danger' : 'text-muted'}">${inst.penalty_formatted}</td>`;
      html += `<td class="text-end ${inst.paid_amount > 0 ? 'text-success' : 'text-muted'}">${inst.paid_formatted}</td>`;
      html += `<td class="text-end fw-semibold ${inst.balance > 0.009 ? 'text-danger' : 'text-muted'}">${inst.balance > 0.009 ? inst.balance_formatted : '—'}</td>`;
      html += `<td class="text-center">${inst.status_badge}</td>`;
      html += `<td class="text-center">${renderInstallmentActions(inst, clientRow)}</td>`;
      html += '</tr>';

      periods.forEach((period) => {
        const pColor = period.status === 'paid' ? 'success'
          : (period.status === 'partial' ? 'info' : (period.status === 'overdue' ? 'danger' : 'warning'));
        let actionHtml = '<span class="text-muted">—</span>';
        if (inst.can_collect && period.balance > 0.009) {
          const payClientName = inst.client_base_name || inst.client_name || clientRow?.client_name || '';
          actionHtml = `<a href="javascript:void(0);" class="btn btn-xs btn-primary chit-partial-btn"
            data-installment-id="${inst.id}"
            data-collect-url="${inst.collect_url}"
            data-partial-rules-url="${inst.partial_rules_url}"
            data-client="${payClientName}"
            data-client-id="${inst.client_id || ''}"
            data-period="${inst.period_label} — ${period.label}"
            data-amount="${inst.amount}"
            data-penalty="0"
            data-paid="${inst.paid_amount}"
            data-balance="${inst.balance}"
            data-is-consolidated="0"
            data-member-numbers="${inst.member_numbers || inst.member_number || ''}"
            data-single-amount="${inst.amount}"
            data-cumulative-amount="${inst.amount}"
            data-single-seat-only="${inst.is_shared ? 1 : 0}"
            data-collection-frequency="${inst.collection_frequency || 'monthly'}"
            data-suggested-amount="${period.balance}"
            data-split-amount="${period.amount}"
            data-split-count="${periods.length}"
            data-force-frequency-partial="1"
            data-min-percentage="1"
            data-penalty-method="${inst.penalty_method || 'emi_amount'}">
            Pay ${period.label}
          </a>`;
        } else if (period.status === 'paid') {
          actionHtml = '<span class="badge bg-label-success" style="font-size:.65rem;">Paid</span>';
        }

        html += `<tr class="inst-row inst-freq-row bg-light" data-month="${monthKey}" data-status="${period.status}"${hideStyle}>`;
        html += `<td class="text-muted small ps-3"><i class="ri-corner-down-right-line me-1"></i>${period.label}</td>`;
        html += '<td class="small text-muted">—</td>';
        html += '<td class="small text-muted">—</td>';
        html += `<td class="small">${period.due_date}</td>`;
        html += `<td class="text-end small">${period.amount_formatted}</td>`;
        html += '<td class="text-end small text-muted">—</td>';
        html += `<td class="text-end text-success small">${period.paid_formatted}</td>`;
        html += `<td class="text-end fw-semibold small ${period.balance > 0.009 ? 'text-danger' : 'text-muted'}">${period.balance_formatted}</td>`;
        html += `<td class="text-center"><span class="badge bg-label-${pColor} text-capitalize" style="font-size:.65rem;">${period.status}</span></td>`;
        html += `<td class="text-center">${actionHtml}</td>`;
      html += '</tr>';
      });
    });

    html += '</tbody></table></div>';
    return html;
  }

  function applyMemberInstFilter(select) {
    if (!select) return;
    const tableId = select.getAttribute('data-table-id');
    let table = tableId ? document.getElementById(tableId) : null;
    if (!table) {
      table = select.closest('.card, .client-installment-details, .border')?.querySelector('table[id]')
        || select.closest('.card')?.querySelector('table');
    }
    if (!table) return;

    const filterVal = String(select.value || 'all');
    const rows = Array.from(table.querySelectorAll('tbody tr.inst-row, tbody tr.group-month-inst-row'));

    // Month-wise: decide on parent month rows, then show/hide all nested day/week parts with them.
    rows.forEach(function (row) {
      if (row.classList.contains('inst-freq-row')) return;

      const st = row.getAttribute('data-status') || '';
      const month = row.getAttribute('data-month') || '';
      let showParent = true;

      if (filterVal === 'all') {
        showParent = true;
      } else if (filterVal === 'unpaid') {
        showParent = st === 'pending' || st === 'overdue' || st === 'partial';
      } else if (filterVal === 'paid') {
        showParent = st === 'paid';
      } else if (filterVal.indexOf('month-') === 0) {
        showParent = month === filterVal;
      }

      row.style.display = showParent ? '' : 'none';

      // Nested day/week rows until the next parent month row
      let next = row.nextElementSibling;
      while (next && next.classList.contains('inst-freq-row')) {
        // Keep all parts visible with the month so payment progress updates are clear
        next.style.display = showParent ? '' : 'none';
        next = next.nextElementSibling;
      }
    });
  }

  document.addEventListener('change', function (e) {
    const select = e.target && e.target.closest
      ? e.target.closest('select.member-inst-filter')
      : null;
    if (select) applyMemberInstFilter(select);
  });

  document.addEventListener('DOMContentLoaded', function () {
    try {
      const raw = sessionStorage.getItem('chitInstFilterState');
      if (raw) {
        const filters = JSON.parse(raw);
        sessionStorage.removeItem('chitInstFilterState');
        document.querySelectorAll('select.member-inst-filter').forEach(function (sel, idx) {
          const key = sel.getAttribute('data-table-id') || ('idx-' + idx);
          if (filters[key] && Array.from(sel.options).some((o) => o.value === filters[key])) {
            sel.value = filters[key];
          }
        });
        const periodSel = document.querySelector('.group-month-period-filter');
        const statusSel = document.querySelector('.group-month-status-filter');
        if (periodSel && filters.__period) periodSel.value = filters.__period;
        if (statusSel && filters.__status) statusSel.value = filters.__status;
      }
    } catch (e) { /* ignore */ }

    document.querySelectorAll('select.member-inst-filter').forEach(applyMemberInstFilter);
    if (typeof window.applyGroupMonthTableFilters === 'function') {
      window.applyGroupMonthTableFilters();
    }
  });

  // Re-apply month filters when Bootstrap tabs are shown (Client View Chits)
  document.addEventListener('shown.bs.tab', function () {
    document.querySelectorAll('select.member-inst-filter').forEach(applyMemberInstFilter);
  });

  // After DataTables child row opens, apply default month filters
  // Index DataTable needs jQuery — skip entirely on group view / pages without it.
  const $ = window.jQuery || window.$;
  if (!$ || !document.getElementById('chitInstallmentsDataTable')) {
    return;
  }

  $(document).on('click', '#chitInstallmentsDataTable tbody td.details-control', function () {
    setTimeout(function () {
      document.querySelectorAll('.client-installment-details select.member-inst-filter').forEach(applyMemberInstFilter);
    }, 50);
  });

  if ($('#chitInstallmentsDataTable').length) {
    const dataUrl = window.chitInstallmentsDataUrl || (baseUrl + 'admin/chit/installments/data');

    const table = $('#chitInstallmentsDataTable').DataTable({
      processing: true,
      serverSide: true,
      scrollX: true,
      autoWidth: false,
      ajax: {
        url: dataUrl,
        type: 'GET',
        data: function (d) {
          d.status = $('#statusFilter').val();
          d.group_id = $('#groupFilter').val();
          d.family_id = $('#familyFilter').val();
          d.from_date = $('#fromDateFilter').val();
          d.to_date = $('#toDateFilter').val();
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
          orderable: false,
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        {
          data: 'client_name',
          render: function (data, type, full) {
            const name = data || 'N/A';
            const sharedBadge = full.is_shared_in_any_group
              ? `<span class="badge bg-label-info ms-1" style="font-size:.65rem;" title="${full.shared_groups_label ? ('Shared in: ' + full.shared_groups_label) : 'Shared membership in one or more groups'}"><i class="ri-user-shared-line me-1"></i>Shared</span>`
              : '';
            const sharedHint = full.is_shared_in_any_group
              ? `<small class="text-info">Shared in ${full.shared_groups_label || 'group(s)'}</small>`
              : '';
            if (!full.client_id) {
              return `<span class="fw-semibold">${name}</span>${sharedBadge}`;
            }
            const chitsUrl = (window.clientInstallmentsBaseUrl || (baseUrl + 'admin/chit/installments/client')) + '/' + full.client_id;
            return `<div class="d-flex flex-column">
                      <div class="d-flex align-items-center flex-wrap gap-1">
                        <a href="${chitsUrl}" class="text-primary fw-semibold" title="View all installments">${name}</a>
                        ${sharedBadge}
                      </div>
                      <small class="text-muted">${full.groups_subtitle || ((full.groups_count || 0) + ' chit group(s)')}</small>
                      ${sharedHint}
                    </div>`;
          }
        },
        {
          data: 'client_phone',
          render: function (data) {
            if (!data || data === 'N/A') return data || '—';
            return `<a href="tel:${String(data).replace(/\s/g, '')}" class="text-body">${data}</a>`;
          }
        },
        {
          data: 'groups_badge',
          orderable: false,
          searchable: false
        },
        {
          data: 'zone',
          render: function (data) {
            return `<span class="badge bg-label-secondary">${data || 'N/A'}</span>`;
          }
        },
        {
          data: 'installment_amount_formatted',
          orderable: false,
          render: function (data) {
            return `<span class="fw-semibold text-primary">${data || '₹0.00'}</span>`;
          }
        },
        {
          data: 'total_due_formatted',
          render: function (data, type, full) {
            if (full.total_due > 0.009) {
              const status = $('#statusFilter').val() || 'overdue';
              const colorClass = status === 'paid' ? 'text-success' : 'text-danger';
              return `<span class="fw-semibold ${colorClass}">${data}</span>`;
            }
            return `<span class="text-muted">—</span>`;
          }
        },
        {
          data: 'status_summary',
          orderable: false,
          searchable: false
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, full) {
            return renderClientRowActions(full);
          }
        }
      ],
      order: [[7, 'desc']],
      pageLength: 20,
      lengthMenu: [20, 10, 25, 50, 100],
      language: {
        sLengthMenu: '_MENU_',
        search: '',
        searchPlaceholder: 'Search clients...',
        info: 'Showing _START_ to _END_ of _TOTAL_ clients',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
        }
      },
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"l>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>'
    });

    $('#chitInstallmentsDataTable tbody').on('click', 'td.details-control', function () {
      const tr = $(this).closest('tr');
      const row = table.row(tr);

      if (row.child.isShown()) {
        row.child.hide();
        tr.removeClass('shown');
      } else {
        row.child(formatClientInstallmentDetails(row.data())).show();
        tr.addClass('shown');
      }
    });

    table.on('xhr.dt', function (e, settings, json) {
      if (!json || !json.stats) return;
      const stats = json.stats;
      const fmt = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      $('#stat-total-installments').text(parseFloat(stats.total_installments || 0).toLocaleString('en-IN'));
      $('#stat-paid-installments').text(parseFloat(stats.paid_installments || 0).toLocaleString('en-IN'));
      $('#stat-total-collected').text(fmt(stats.total_collected));
      $('#stat-pending-installments').text(parseFloat(stats.pending_installments || 0).toLocaleString('en-IN'));
      $('#stat-total-pending').text(fmt(stats.total_pending));
      $('#stat-overdue-installments').text(parseFloat(stats.overdue_installments || 0).toLocaleString('en-IN'));

      $('#tab-count-all').text(parseFloat(stats.total_installments || 0).toLocaleString('en-IN'));
      $('#tab-count-overdue').text(parseFloat(stats.overdue_installments || 0).toLocaleString('en-IN'));
      $('#tab-count-pending').text(parseFloat(stats.pending_installments || 0).toLocaleString('en-IN'));
      $('#tab-count-upcoming').text(parseFloat(stats.upcoming_installments || 0).toLocaleString('en-IN'));
      $('#tab-count-partial').text(parseFloat(stats.partial_installments || 0).toLocaleString('en-IN'));
      $('#tab-count-paid').text(parseFloat(stats.paid_installments || 0).toLocaleString('en-IN'));
    });

    table.on('draw', function () {
      table.rows().every(function () {
        if (this.child.isShown()) {
          this.child.hide();
          $(this.node()).removeClass('shown');
        }
      });
    });

    $('#installmentsTabs button').on('shown.bs.tab', function () {
      const status = $(this).attr('data-status');
      $('#statusFilter').val(status);
      const titles = {
        all: 'All Installment Clients',
        overdue: 'Overdue Clients',
        pending: 'Pending Clients',
        upcoming: 'Upcoming Clients',
        partial: 'Partial Paid Clients',
        paid: 'Paid Clients'
      };
      const dueHeaders = {
        all: 'Total Due',
        overdue: 'Overdue Due',
        pending: 'Pending Due',
        upcoming: 'Upcoming Due',
        partial: 'Partial Due',
        paid: 'Total Paid'
      };
      $('#tableTitle').html(`<i class="icon-base ri ri-file-list-3-line me-2 text-primary"></i>${titles[status] || 'Chit Installments'}`);
      $(table.column(7).header()).text(dueHeaders[status] || 'Total Due');
      table.ajax.reload();
    });

    // Stat Card click handling to trigger tab change
    $(document).on('click', '.stat-card', function (e) {
      e.preventDefault();
      const status = $(this).attr('data-status') || 'overdue';
      const targetBtn = document.querySelector(`#installmentsTabs button[data-status="${status}"]`);
      if (targetBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        const tabInst = bootstrap.Tab.getOrCreateInstance(targetBtn);
        tabInst.show();
      } else if (targetBtn) {
        $('#installmentsTabs button').removeClass('active');
        $(targetBtn).addClass('active');
        $('#statusFilter').val(status);
        table.ajax.reload();
      }
    });

    $('#groupFilter, #familyFilter, #fromDateFilter, #toDateFilter').on('change', function () {
      table.ajax.reload();
    });

    $('#resetFilters').on('click', function () {
      $('#statusFilter').val('overdue');

      // change.select2 repaints the Select2 widget without firing our own change
      // handler, so the table is reloaded just once at the end.
      $('#groupFilter, #familyFilter').val('').trigger('change.select2');
      $('#fromDateFilter').val('');
      $('#toDateFilter').val('');

      if (typeof table !== 'undefined' && table) {
        table.search('');
        const dtSearchInput = $('div.dataTables_filter input, input[type="search"]');
        if (dtSearchInput.length) {
          dtSearchInput.val('');
        }
      }

      const overdueTabBtn = document.querySelector('#installmentsTabs button[data-status="overdue"]');
      if (overdueTabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        const tabInstance = bootstrap.Tab.getOrCreateInstance(overdueTabBtn);
        tabInstance.show();
      } else {
        $('#installmentsTabs button').removeClass('active');
        $('#installmentsTabs button[data-status="overdue"]').addClass('active');
      }

      $('#tableTitle').text('Overdue Clients');
      if (typeof table !== 'undefined' && table && table.column(7)) {
        $(table.column(7).header()).text('Overdue Due');
      }

      if (typeof table !== 'undefined' && table) {
        table.ajax.reload(null, false);
      }
    });

    $(document).on('click', '.chit-undo-btn', function (e) {
      e.preventDefault();
      const formId = $(this).attr('data-form-id') || $(this).data('form-id');
      if (typeof window.confirmChitUndoPayment === 'function') {
        window.confirmChitUndoPayment(formId);
      }
    });

    $(window).on('resize', function () {
      table.columns.adjust();
    });
  }
}

// Vite serves this as an ES module, so it can execute after DOMContentLoaded has
// already fired (and again on every HMR update). Listening for that event alone
// would leave the page with no DataTable and no filter handlers at all.
function bootChitInstallments() {
  try {
    initChitInstallments();
  } catch (err) {
    console.error('initChitInstallments failed:', err);
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootChitInstallments);
} else {
  bootChitInstallments();
}

// ─── Bulk installment pay (Month X + Client View Chits) ───────────────────────
function initChitBulkPay() {
  if (window.chitBulkPayInitialised) return;
  window.chitBulkPayInitialised = true;

  const bar = document.getElementById('chitBulkPayBar');
  const modalEl = document.getElementById('chitBulkPayModal');
  const form = document.getElementById('chitBulkPayForm');
  // Keep EMI count + Pay Selected working even if modal markup is late/missing.
  const canOpenModal = !!(modalEl && form);

  let pageBaseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!pageBaseUrl.endsWith('/')) pageBaseUrl += '/';
  const pageCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const bulkUrl = window.chitBulkCollectUrl || (pageBaseUrl + 'admin/chit/installments/bulk-collect');
  const methodSelect = document.getElementById('chitBulkPaymentMethod');
  const bankContainer = document.getElementById('chitBulkBankAccountContainer');
  const bankSelect = document.getElementById('chitBulkBankAccount');
  const bulkDetailsCard = document.getElementById('chitBulkBankDetailsCard');
  const bulkBankTransferContent = document.getElementById('chitBulkBankTransferContent');

  function refreshBulkBankUi() {
    if (!methodSelect) return;
    const method = methodSelect.value || '';
    const needsBank = method === 'upi' || method === 'bank_transfer';
    const qrBankName = document.getElementById('chitBulkQrBankName');
    const qrUpiId = document.getElementById('chitBulkQrUpiId');
    const qrImage = document.getElementById('chitBulkQrImage');

    if (bankContainer) {
      bankContainer.classList.toggle('d-none', !needsBank);
    }
    if (bankSelect) {
      bankSelect.required = needsBank;
      if (needsBank && !bankSelect.value) {
        const first = bankSelect.querySelector('option[value]:not([value=""])');
        if (first) bankSelect.value = first.value;
      }
      if (!needsBank) {
        bankSelect.value = '';
      }
      if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2
          && window.jQuery(bankSelect).hasClass('select2-hidden-accessible')) {
        window.jQuery(bankSelect).val(bankSelect.value).trigger('change.select2');
      }
    }

    if (!needsBank) {
      if (bulkDetailsCard) bulkDetailsCard.classList.add('d-none');
      if (bulkBankTransferContent) bulkBankTransferContent.innerHTML = '';
      if (qrImage) qrImage.innerHTML = '';
      return;
    }

    const option = bankSelect && bankSelect.selectedIndex >= 0
      ? bankSelect.options[bankSelect.selectedIndex]
      : null;

    if (bulkDetailsCard) bulkDetailsCard.classList.add('d-none');
    if (qrImage) qrImage.innerHTML = '';
    if (bulkBankTransferContent) bulkBankTransferContent.innerHTML = '';

    if (!option || !option.value) {
      return;
    }

    const bankName = option.getAttribute('data-bank-name') || '';
    const accountName = option.getAttribute('data-account-name') || 'N/A';
    const accountNumber = option.getAttribute('data-account-number') || 'N/A';
    const branchName = option.getAttribute('data-branch-name') || 'N/A';
    const accountType = option.getAttribute('data-account-type') || '';
    const ifsc = option.getAttribute('data-ifsc') || '';
    const upiId = option.getAttribute('data-upi-id') || '';
    const qrCodeUrl = option.getAttribute('data-qr-code') || '';

    if (qrBankName) qrBankName.textContent = bankName || 'Bank Account';
    if (qrUpiId) qrUpiId.textContent = upiId || 'N/A';

    let detailsHtml = `
      <p class="mb-1"><strong>Bank:</strong> ${bankName || 'N/A'}</p>
      <p class="mb-1"><strong>Account Name:</strong> ${accountName}</p>
      <p class="mb-1"><strong>Account Number:</strong> ${accountNumber}</p>
      <p class="mb-1"><strong>Branch:</strong> ${branchName}</p>
    `;
    if (accountType) {
      detailsHtml += `<p class="mb-1"><strong>Account Type:</strong> ${accountType.charAt(0).toUpperCase() + accountType.slice(1)}</p>`;
    }
    if (ifsc) {
      detailsHtml += `<p class="mb-1"><strong>IFSC:</strong> ${ifsc}</p>`;
    }
    if (upiId) {
      detailsHtml += `<p class="mb-1"><strong>UPI ID:</strong> ${upiId}</p>`;
    }
    detailsHtml += '<p class="text-muted small mb-0 mt-2">Collection will be credited to this bank account.</p>';
    if (bulkBankTransferContent) bulkBankTransferContent.innerHTML = detailsHtml;

    if (qrImage) {
      qrImage.innerHTML = qrCodeUrl
        ? `<img src="${qrCodeUrl}" alt="QR Code" class="img-fluid my-1" style="max-height: 220px; border: 1px solid #eee; padding: 8px; border-radius: 8px;">`
        : `<div class="alert alert-warning py-2 mb-0 mt-1 small">No QR Code uploaded for this bank. Add it in Bank Accounts.</div>`;
    }
    if (bulkDetailsCard) bulkDetailsCard.classList.remove('d-none');
  }

  function formatMoney(value) {
    return '₹' + Number(value || 0).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function showBulkAlert(type, title, message) {
    const icon = type === 'danger' ? 'error' : type;
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon, title, text: message, confirmButtonText: 'OK' });
    } else {
      alert(`${title}: ${message}`);
    }
  }

  function isRowVisible(row) {
    if (!row) return false;
    if (row.style && row.style.display === 'none') return false;
    try {
      if (window.getComputedStyle(row).display === 'none') return false;
    } catch (e) { /* ignore */ }
    return true;
  }

  function readPeriodAmount(cb) {
    const raw = cb.getAttribute('data-period-amount')
      || cb.dataset.periodAmount
      || cb.getAttribute('data-suggested')
      || cb.dataset.suggested
      || '0';
    return parseFloat(raw) || 0;
  }

  function sortPeriodCheckboxes(cbs) {
    return cbs.slice().sort((a, b) => {
      const ma = parseInt(a.getAttribute('data-month-number') || a.dataset.monthNumber || '0', 10) || 0;
      const mb = parseInt(b.getAttribute('data-month-number') || b.dataset.monthNumber || '0', 10) || 0;
      if (ma !== mb) return ma - mb;
      const ia = parseInt(a.getAttribute('data-period-index') || a.dataset.periodIndex || '0', 10) || 0;
      const ib = parseInt(b.getAttribute('data-period-index') || b.dataset.periodIndex || '0', 10) || 0;
      if (ia !== ib) return ia - ib;
      return (parseInt(a.value, 10) || 0) - (parseInt(b.value, 10) || 0);
    });
  }

  function getVisibleBulkCheckboxes(tableId) {
    const scope = tableId ? document.getElementById(tableId) : document;
    if (!scope) return [];
    return sortPeriodCheckboxes(
      Array.from(scope.querySelectorAll('.chit-bulk-cb[data-is-freq="1"]')).filter((cb) => {
        const row = cb.closest('tr');
        return isRowVisible(row) && !cb.disabled;
      })
    );
  }

  function enforceSequentialSelection(cb) {
    const clientId = cb.dataset.clientId || '';
    const instId = String(cb.value);
    const siblings = sortPeriodCheckboxes(
      Array.from(document.querySelectorAll('.chit-bulk-cb[data-is-freq="1"]')).filter((other) => {
        if (String(other.value) !== instId) return false;
        if ((other.dataset.clientId || '') !== clientId) return false;
        const row = other.closest('tr');
        return isRowVisible(row) && !other.disabled;
      })
    );
    const pos = siblings.indexOf(cb);
    if (pos < 0) return;

    if (cb.checked) {
      // Checking Day/Week N also selects earlier unpaid parts of the same month.
      for (let i = 0; i < pos; i++) {
        siblings[i].checked = true;
      }
    } else {
      // Unchecking clears later parts (order-only selection).
      for (let i = pos + 1; i < siblings.length; i++) {
        siblings[i].checked = false;
      }
    }
  }

  function applySelectAllInOrder(tableId, checked) {
    const ordered = getVisibleBulkCheckboxes(tableId);
    if (!checked) {
      ordered.forEach((cb) => { cb.checked = false; });
      return;
    }
    // Select contiguous unpaid periods per month, always in Day/Week order.
    const groups = new Map();
    ordered.forEach((cb) => {
      const key = String(cb.value) + ':' + (cb.dataset.clientId || '');
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(cb);
    });
    groups.forEach((list) => {
      sortPeriodCheckboxes(list).forEach((cb) => { cb.checked = true; });
    });
  }

  function getSelectedItems(scopeEl) {
    const root = scopeEl || document;
    const items = [];
    root.querySelectorAll('.chit-bulk-cb[data-is-freq="1"]:checked').forEach((cb) => {
      const row = cb.closest('tr');
      if (!isRowVisible(row)) return;
      const periodAmount = readPeriodAmount(cb);
      const balance = parseFloat(cb.getAttribute('data-balance') || cb.dataset.balance || '0') || 0;
      const clientRaw = cb.getAttribute('data-client-id') || cb.dataset.clientId || '';
      const amount = periodAmount > 0
        ? (balance > 0 ? Math.min(periodAmount, balance) : periodAmount)
        : balance;
      items.push({
        installment_id: parseInt(cb.value, 10),
        client_id: clientRaw ? parseInt(clientRaw, 10) : null,
        balance,
        period_amount: amount,
        period_index: parseInt(cb.getAttribute('data-period-index') || cb.dataset.periodIndex || '0', 10) || null,
        period_label: cb.getAttribute('data-period-label') || cb.dataset.periodLabel || '',
        month_number: parseInt(cb.getAttribute('data-month-number') || cb.dataset.monthNumber || '0', 10) || null,
        is_freq: true
      });
    });
    return items;
  }

  function uniqueMonthBalanceTotal(items) {
    const seen = new Set();
    let total = 0;
    items.forEach((it) => {
      if (seen.has(it.installment_id)) return;
      seen.add(it.installment_id);
      total += it.balance;
    });
    return total;
  }

  let bulkPayScopeTableId = null;

  function updateLocalSummaries() {
    document.querySelectorAll('.chit-inst-bulk-summary').forEach((summary) => {
      const tableId = summary.getAttribute('data-table-id');
      const table = tableId ? document.getElementById(tableId) : null;
      const items = table ? getSelectedItems(table) : [];
      let sum = 0;
      items.forEach((it) => { sum += Number(it.period_amount) || 0; });
      const countEl = summary.querySelector('.chit-inst-bulk-count');
      const sumEl = summary.querySelector('.chit-inst-bulk-sum');
      if (countEl) countEl.textContent = String(items.length);
      if (sumEl) sumEl.textContent = formatMoney(sum);
      const hasSelection = items.length > 0;
      summary.classList.remove('d-none');
      summary.classList.add('d-flex');
      summary.querySelectorAll('.chit-inst-bulk-pay-btn, .chit-inst-bulk-clear-btn').forEach((btn) => {
        btn.disabled = !hasSelection;
      });
      if (tableId) {
        document.querySelectorAll('.chit-inst-bulk-pay-btn[data-table-id="' + tableId + '"]').forEach((btn) => {
          btn.disabled = !hasSelection;
        });
      }
    });
  }

  function clearBulkSelectionAndHideBar() {
    document.querySelectorAll('.chit-bulk-cb:checked').forEach((cb) => { cb.checked = false; });
    document.querySelectorAll('.chit-bulk-select-all').forEach((cb) => { cb.checked = false; });
    bulkPayScopeTableId = null;
    if (bar) {
      bar.classList.add('d-none');
      const countEl = document.getElementById('chitBulkSelectedCount');
      const sugEl = document.getElementById('chitBulkSuggestedTotal');
      const fullEl = document.getElementById('chitBulkFullTotal');
      if (countEl) countEl.textContent = '0';
      if (sugEl) sugEl.textContent = formatMoney(0);
      if (fullEl) fullEl.textContent = formatMoney(0);
    }
    updateLocalSummaries();
  }

  function updateBulkBar() {
    const items = getSelectedItems();
    const count = items.length;
    let periodTotal = 0;
    items.forEach((it) => {
      periodTotal += Number(it.period_amount) || 0;
    });
    const fullTotal = uniqueMonthBalanceTotal(items);

    const countEl = document.getElementById('chitBulkSelectedCount');
    const sugEl = document.getElementById('chitBulkSuggestedTotal');
    const fullEl = document.getElementById('chitBulkFullTotal');
    if (countEl) countEl.textContent = String(count);
    if (sugEl) sugEl.textContent = formatMoney(periodTotal);
    if (fullEl) fullEl.textContent = formatMoney(fullTotal);
    if (bar) bar.classList.toggle('d-none', count === 0);
    updateLocalSummaries();
  }

  window.updateChitBulkSelectionUI = updateBulkBar;
  window.clearChitBulkSelectionUI = clearBulkSelectionAndHideBar;

  function getBulkScopeRoot() {
    if (!bulkPayScopeTableId) return document;
    return document.getElementById(bulkPayScopeTableId) || document;
  }

  function syncModalAmount() {
    const items = getSelectedItems(getBulkScopeRoot());
    const type = (form.querySelector('input[name="collection_type"]:checked') || {}).value || 'period';
    let total = 0;
    if (type === 'full') {
      total = uniqueMonthBalanceTotal(items);
    } else {
      items.forEach((it) => { total += it.period_amount; });
    }
    const amountInput = document.getElementById('chitBulkModalAmount');
    const countEl = document.getElementById('chitBulkModalCount');
    if (amountInput) amountInput.value = total.toFixed(2);
    if (countEl) countEl.textContent = String(items.length);
  }

  function openBulkPayModal(tableId) {
    if (!canOpenModal) {
      showBulkAlert('danger', 'Error', 'Payment form is not available on this page.');
      return;
    }
    bulkPayScopeTableId = tableId || null;
    const items = getSelectedItems(getBulkScopeRoot());
    if (!items.length) {
      showBulkAlert('warning', 'No Selection', 'Select Day/Week parts in the Installment Schedule (Day 1, Day 2…) then click Pay Selected.');
      return;
    }
    syncModalAmount();
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('chit-bulk-cb')) {
      enforceSequentialSelection(e.target);
      updateBulkBar();
      return;
    }
    if (e.target.classList.contains('chit-bulk-select-all')) {
      const tableId = e.target.getAttribute('data-table-id');
      applySelectAllInOrder(tableId, e.target.checked);
      updateBulkBar();
    }
  });

  document.addEventListener('click', function (e) {
    const payBtn = e.target.closest('.chit-inst-bulk-pay-btn');
    if (payBtn) {
      e.preventDefault();
      if (payBtn.disabled) return;
      const tableId = payBtn.getAttribute('data-table-id')
        || payBtn.closest('.chit-inst-bulk-summary')?.getAttribute('data-table-id')
        || null;
      openBulkPayModal(tableId);
      return;
    }
    const clearBtn = e.target.closest('.chit-inst-bulk-clear-btn');
    if (clearBtn) {
      e.preventDefault();
      if (clearBtn.disabled) return;
      const summary = clearBtn.closest('.chit-inst-bulk-summary');
      const tableId = summary?.getAttribute('data-table-id');
      const table = tableId ? document.getElementById(tableId) : null;
      if (table) {
        table.querySelectorAll('.chit-bulk-cb:checked').forEach((cb) => { cb.checked = false; });
        table.querySelectorAll('.chit-bulk-select-all').forEach((cb) => { cb.checked = false; });
      }
      updateBulkBar();
    }
  });

  document.getElementById('chitBulkClearBtn')?.addEventListener('click', function () {
    document.querySelectorAll('.chit-bulk-cb:checked').forEach((cb) => { cb.checked = false; });
    document.querySelectorAll('.chit-bulk-select-all').forEach((cb) => { cb.checked = false; });
    updateBulkBar();
  });

  document.getElementById('chitBulkPayBtn')?.addEventListener('click', function () {
    openBulkPayModal(null);
  });

  if (methodSelect) {
    methodSelect.addEventListener('change', refreshBulkBankUi);
  }
  if (bankSelect) {
    bankSelect.addEventListener('change', refreshBulkBankUi);
  }
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', refreshBulkBankUi);
  }

  if (form) {
    form.querySelectorAll('input[name="collection_type"]').forEach((radio) => {
      radio.addEventListener('change', syncModalAmount);
    });
  }

  if (!form) {
    updateBulkBar();
    return;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const items = getSelectedItems(getBulkScopeRoot());
    if (!items.length) {
      showBulkAlert('warning', 'No Selection', 'Select at least one Day/Week (e.g. Day 1, Week 2) to pay.');
      return;
    }

    const method = methodSelect?.value || '';
    if (!method) {
      showBulkAlert('warning', 'Payment Method', 'Please select a payment method.');
      return;
    }
    if ((method === 'upi' || method === 'bank_transfer') && !(bankSelect?.value)) {
      showBulkAlert('warning', 'Bank Required', 'Please select a collection bank account.');
      return;
    }

    const submitBtn = document.getElementById('chitBulkSubmitBtn');
    const defaultHtml = submitBtn?.innerHTML;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
    }

    const collectionType = (form.querySelector('input[name="collection_type"]:checked') || {}).value || 'period';
    const payload = {
      items: items.map((it) => ({
        installment_id: it.installment_id,
        client_id: it.client_id || null,
        amount: it.period_amount,
        period_index: it.period_index,
        period_label: it.period_label || null
      })),
      payment_mode: method,
      paid_date: document.getElementById('chitBulkPaidDate')?.value,
      reference_no: document.getElementById('chitBulkReference')?.value || '',
      remarks: document.getElementById('chitBulkRemarks')?.value || '',
      collection_type: collectionType === 'suggested' ? 'period' : collectionType,
      internal_bank_account_id: (method === 'upi' || method === 'bank_transfer') ? (bankSelect?.value || null) : null
    };

    fetch(bulkUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': pageCsrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          let message = data.message || 'Bulk payment failed';
          if (data.errors) {
            message = Array.isArray(data.errors)
              ? data.errors.join(' ')
              : Object.values(data.errors).flat().join(' ');
          }
          throw new Error(message);
        }
        return data;
      })
      .then((data) => {
        bootstrap.Modal.getInstance(modalEl)?.hide();
        // Hide sticky "Selected Day/Week EMI" bar immediately after payment.
        clearBulkSelectionAndHideBar();
        try {
          const filters = {};
          document.querySelectorAll('select.member-inst-filter').forEach(function (sel, idx) {
            filters[sel.getAttribute('data-table-id') || ('idx-' + idx)] = sel.value;
          });
          const periodSel = document.querySelector('.group-month-period-filter');
          const statusSel = document.querySelector('.group-month-status-filter');
          if (periodSel) filters.__period = periodSel.value;
          if (statusSel) filters.__status = statusSel.value;
          sessionStorage.setItem('chitInstFilterState', JSON.stringify(filters));
        } catch (e) { /* ignore */ }

        Swal.fire({
          icon: 'success',
          title: 'Bulk Payment Successful',
          text: data.message || 'Payments collected successfully.',
          confirmButtonText: 'OK'
        }).then(() => {
          clearBulkSelectionAndHideBar();
          if (typeof window.reloadChitGroupView === 'function' && document.getElementById('group-show-ajax-root')) {
            window.reloadChitGroupView().then(function () {
              if (typeof window.clearChitBulkSelectionUI === 'function') {
                window.clearChitBulkSelectionUI();
              }
            }).catch(function () {
              clearBulkSelectionAndHideBar();
            });
          } else {
            window.location.reload();
          }
        });
      })
      .catch((error) => {
        showBulkAlert('danger', 'Error', error.message || 'Something went wrong.');
      })
      .finally(() => {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = defaultHtml || 'Collect Payment';
        }
      });
  });

  document.addEventListener('chit-group-view:reloaded', function () {
    clearBulkSelectionAndHideBar();
  });

  updateBulkBar();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initChitBulkPay);
} else {
  initChitBulkPay();
}
