/**
 * Shared bank collection UI: account details + QR for UPI/GPay and bank transfer.
 */
'use strict';

(function () {
  const registeredGroupsMap = new Map();

  const DEFAULT_GROUPS = [
    {
      methodSelectId: 'bulk_payment_method',
      bankSelectId: 'repaymentBulkBankAccount',
      bankContainerId: 'repaymentBulkBankWrap',
      bankDetailsCardId: 'repaymentBulkBankDetailsCard',
      qrContainerId: 'repaymentBulkQrContainer',
      qrBankNameId: 'repaymentBulkQrBankName',
      qrUpiIdId: 'repaymentBulkQrUpiId',
      qrImageWrapperId: 'repaymentBulkQrImageWrapper',
      bankTransferContainerId: 'repaymentBulkBankTransferContainer',
      bankTransferContentId: 'repaymentBulkBankTransferContent'
    },
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
    },
    {
      methodSelectId: 'chitPayPaymentMethod',
      bankSelectId: 'chitPayBankAccount',
      bankContainerId: 'chitPayBankAccountContainer',
      bankDetailsCardId: 'chitPayBankDetailsCard',
      qrContainerId: 'chitPayQrContainer',
      qrBankNameId: 'chitPayQrBankName',
      qrUpiIdId: 'chitPayQrUpiId',
      qrImageWrapperId: 'chitPayQrImage',
      bankTransferContainerId: 'chitPayBankTransferContainer',
      bankTransferContentId: 'chitPayBankTransferContent'
    },
    {
      methodSelectId: 'chitPartialPaymentMethod',
      bankSelectId: 'chitPartialBankAccount',
      bankContainerId: 'chitPartialBankAccountContainer',
      bankDetailsCardId: 'chitPartialBankDetailsCard',
      qrContainerId: 'chitPartialQrContainer',
      qrBankNameId: 'chitPartialQrBankName',
      qrUpiIdId: 'chitPartialQrUpiId',
      qrImageWrapperId: 'chitPartialQrImage',
      bankTransferContainerId: 'chitPartialBankTransferContainer',
      bankTransferContentId: 'chitPartialBankTransferContent'
    },
    {
      methodSelectId: 'chitBulkPaymentMethod',
      bankSelectId: 'chitBulkBankAccount',
      bankContainerId: 'chitBulkBankAccountContainer',
      bankDetailsCardId: 'chitBulkBankDetailsCard',
      qrContainerId: 'chitBulkQrContainer',
      qrBankNameId: 'chitBulkQrBankName',
      qrUpiIdId: 'chitBulkQrUpiId',
      qrImageWrapperId: 'chitBulkQrImage',
      bankTransferContainerId: 'chitBulkBankTransferContainer',
      bankTransferContentId: 'chitBulkBankTransferContent'
    },
    {
      methodSelectId: 'bulkPayMethod',
      bankSelectId: 'familyBulkBankAccount',
      bankContainerId: 'familyBulkBankWrap',
      bankDetailsCardId: 'familyBulkBankDetailsCard',
      qrContainerId: 'familyBulkQrContainer',
      qrBankNameId: 'familyBulkQrBankName',
      qrUpiIdId: 'familyBulkQrUpiId',
      qrImageWrapperId: 'familyBulkQrImageWrapper',
      bankTransferContainerId: 'familyBulkBankTransferContainer',
      bankTransferContentId: 'familyBulkBankTransferContent'
    }
  ];

  function registerGroup(group) {
    if (!group || !group.methodSelectId) return;
    registeredGroupsMap.set(group.methodSelectId, group);
  }

  DEFAULT_GROUPS.forEach(registerGroup);

  function getSelectedBankOption(selectElement) {
    if (!selectElement || selectElement.selectedIndex < 0) {
      return null;
    }
    return selectElement.options[selectElement.selectedIndex];
  }

  function autoSelectBank(selectElement) {
    if (!selectElement) {
      return false;
    }
    const bankOptions = Array.from(selectElement.options).filter(option => option.value);
    if (bankOptions.length === 1) {
      selectElement.value = bankOptions[0].value;
      return true;
    }
    return false;
  }

  function buildBankDetailsHtml(option) {
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

  function renderQr(option, qrImageWrapper) {
    if (!qrImageWrapper) {
      return;
    }
    const qrCodeUrl = option.getAttribute('data-qr-code') || '';
    qrImageWrapper.innerHTML = '';
    if (qrCodeUrl) {
      qrImageWrapper.innerHTML = `<img src="${qrCodeUrl}" alt="QR Code" class="img-fluid my-1" style="max-height: 220px; border: 1px solid #eee; padding: 8px; border-radius: 8px;">`;
    } else {
      qrImageWrapper.innerHTML = '<div class="alert alert-warning py-2 mb-0 mt-1 small">No QR Code uploaded for this bank. Add it in Bank Accounts.</div>';
    }
  }

  function updateBankPaymentDisplay(paymentMethod, option, config) {
    const {
      qrContainer,
      qrBankNameEl,
      qrUpiIdEl,
      qrImageWrapper,
      bankTransferContainer,
      bankTransferContentEl,
      bankDetailsCard
    } = config;

    const hideAll = () => {
      if (bankDetailsCard) {
        bankDetailsCard.classList.add('d-none');
      }
      if (qrContainer && qrContainer !== bankDetailsCard) {
        qrContainer.classList.add('d-none');
      }
      if (bankTransferContainer) {
        bankTransferContainer.classList.add('d-none');
      }
      if (qrImageWrapper) {
        qrImageWrapper.innerHTML = '';
      }
      if (bankTransferContentEl) {
        bankTransferContentEl.innerHTML = '';
      }
    };

    const needsBank = paymentMethod === 'upi' || paymentMethod === 'bank_transfer';
    if (!needsBank || !option || !option.value) {
      hideAll();
      return;
    }

    const bankName = option.getAttribute('data-bank-name') || '';
    const upiId = option.getAttribute('data-upi-id') || 'N/A';

    if (qrBankNameEl) {
      qrBankNameEl.textContent = bankName || 'Bank Account';
    }
    if (qrUpiIdEl) {
      qrUpiIdEl.textContent = upiId;
    }
    if (bankTransferContentEl) {
      bankTransferContentEl.innerHTML = buildBankDetailsHtml(option);
    }
    renderQr(option, qrImageWrapper);

    if (bankDetailsCard) {
      bankDetailsCard.classList.remove('d-none');
    }
    if (qrContainer) {
      qrContainer.classList.remove('d-none');
    }
    if (!bankDetailsCard && bankTransferContainer) {
      bankTransferContainer.classList.remove('d-none');
    }
  }

  function getGroupConfig(group) {
    return {
      qrContainer: document.getElementById(group.qrContainerId),
      qrBankNameEl: document.getElementById(group.qrBankNameId),
      qrUpiIdEl: document.getElementById(group.qrUpiIdId),
      qrImageWrapper: document.getElementById(group.qrImageWrapperId),
      bankTransferContainer: document.getElementById(group.bankTransferContainerId),
      bankTransferContentEl: document.getElementById(group.bankTransferContentId),
      bankDetailsCard: document.getElementById(
        group.bankDetailsCardId || ((group.bankContainerId || 'bankAccountContainer') + 'DetailsCard')
      )
    };
  }

  function handleMethodChange(group) {
    const methodSelect = document.getElementById(group.methodSelectId);
    const bankSelect = document.getElementById(group.bankSelectId);
    const bankContainer = document.getElementById(group.bankContainerId);
    if (!methodSelect || !bankSelect) {
      return;
    }

    const method = methodSelect.value;
    const config = getGroupConfig(group);

    if (method === 'upi' || method === 'bank_transfer') {
      if (bankContainer) {
        bankContainer.classList.remove('d-none');
      }
      bankSelect.required = true;
      autoSelectBank(bankSelect);
      updateBankPaymentDisplay(method, getSelectedBankOption(bankSelect), config);
    } else {
      if (bankContainer) {
        bankContainer.classList.add('d-none');
      }
      bankSelect.required = false;
      bankSelect.value = '';
      updateBankPaymentDisplay(method, null, config);
    }
  }

  function handleBankChange(group) {
    const methodSelect = document.getElementById(group.methodSelectId);
    const bankSelect = document.getElementById(group.bankSelectId);
    if (!methodSelect || !bankSelect) {
      return;
    }
    updateBankPaymentDisplay(methodSelect.value, getSelectedBankOption(bankSelect), getGroupConfig(group));
  }

  function initGroup(group) {
    if (!group) return;
    registerGroup(group);

    const methodSelect = document.getElementById(group.methodSelectId);
    const bankSelect = document.getElementById(group.bankSelectId);
    if (!methodSelect || !bankSelect) {
      return;
    }

    methodSelect.addEventListener('change', () => handleMethodChange(group));
    bankSelect.addEventListener('change', () => handleBankChange(group));

    if (methodSelect.value === 'upi' || methodSelect.value === 'bank_transfer') {
      handleMethodChange(group);
    }
  }

  function bootPendingGroups() {
    if (Array.isArray(window._bankPaymentGroupsQueue) && window._bankPaymentGroupsQueue.length) {
      window._bankPaymentGroupsQueue.forEach(group => initGroup(group));
      window._bankPaymentGroupsQueue = [];
    }
    registeredGroupsMap.forEach(group => {
      const methodSelect = document.getElementById(group.methodSelectId);
      if (methodSelect && (methodSelect.value === 'upi' || methodSelect.value === 'bank_transfer')) {
        handleMethodChange(group);
      }
    });
  }

  function resetToInHand(methodSelectId) {
    const methodSelect = document.getElementById(methodSelectId);
    if (!methodSelect) {
      return;
    }
    methodSelect.value = 'in_hand';
    const group = registeredGroupsMap.get(methodSelectId);
    if (group) {
      handleMethodChange(group);
    } else {
      methodSelect.dispatchEvent(new Event('change'));
    }
  }

  function resolveMethodSelect(form, methodSelect) {
    if (methodSelect && (!form || methodSelect.form === form)) {
      return methodSelect;
    }
    if (!form) {
      return methodSelect || null;
    }
    return form.querySelector('select[name="payment_method"], select[name="payment_mode"]');
  }

  function resolveBankSelect(form, bankSelect) {
    if (bankSelect && (!form || bankSelect.form === form)) {
      return bankSelect;
    }
    if (!form) {
      return bankSelect || null;
    }
    return form.querySelector('select[name="internal_bank_account_id"], select[name="bank_account_id"]');
  }

  function ensureBankSelected(method, bankSelect) {
    if (!bankSelect || method === 'in_hand' || method === 'cash' || method === 'wallet') {
      return bankSelect ? bankSelect.value : '';
    }
    return bankSelect ? (bankSelect.value || '') : '';
  }

  function prepareFormData(form, methodSelect, bankSelect) {
    const formData = new FormData(form);
    const resolvedMethodSelect = resolveMethodSelect(form, methodSelect);
    const resolvedBankSelect = resolveBankSelect(form, bankSelect);
    const paymentMethod = resolvedMethodSelect
      ? resolvedMethodSelect.value
      : (formData.get('payment_method') || formData.get('payment_mode') || '');

    if (!paymentMethod) {
      throw new Error('Please select a payment method.');
    }

    let bankAccountId = ensureBankSelected(paymentMethod, resolvedBankSelect);
    if (!bankAccountId) {
      bankAccountId = formData.get('internal_bank_account_id') || formData.get('bank_account_id') || '';
    }

    if ((paymentMethod === 'upi' || paymentMethod === 'bank_transfer') && !bankAccountId) {
      throw new Error('Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer.');
    }

    if (paymentMethod === 'in_hand' || paymentMethod === 'cash' || paymentMethod === 'wallet') {
      formData.delete('internal_bank_account_id');
      formData.delete('bank_account_id');
    } else if (bankAccountId) {
      formData.set('internal_bank_account_id', bankAccountId);
    }

    return formData;
  }

  function validateBankPayment(methodSelect, bankSelect, form) {
    const resolvedMethodSelect = resolveMethodSelect(form || null, methodSelect);
    const resolvedBankSelect = resolveBankSelect(form || null, bankSelect);
    const paymentMethod = resolvedMethodSelect ? resolvedMethodSelect.value : '';
    const bankAccountId = ensureBankSelected(paymentMethod, resolvedBankSelect);

    if (!paymentMethod) {
      return 'Please select a payment method.';
    }

    if ((paymentMethod === 'upi' || paymentMethod === 'bank_transfer') && !bankAccountId) {
      return 'Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer.';
    }

    return null;
  }

  function handleElementChange(target) {
    if (!target) return;
    const targetId = target.id;

    if (targetId) {
      for (const group of registeredGroupsMap.values()) {
        if (group.methodSelectId === targetId) {
          handleMethodChange(group);
          return;
        }
        if (group.bankSelectId === targetId) {
          handleBankChange(group);
          return;
        }
      }
    }

    const name = target.getAttribute ? target.getAttribute('name') : null;
    if (name === 'payment_method' || name === 'payment_mode') {
      const form = target.closest ? (target.closest('form') || target.closest('.modal') || target.closest('.card')) : null;
      if (form) {
        const bankSelect = form.querySelector('select[name="internal_bank_account_id"], select[name="bank_account_id"]');
        if (bankSelect) {
          const bankContainer = bankSelect.closest('.mb-3, .mb-2, div');
          const method = target.value;
          if (method === 'upi' || method === 'bank_transfer') {
            if (bankContainer) bankContainer.classList.remove('d-none');
            bankSelect.required = true;
          } else {
            if (bankContainer) bankContainer.classList.add('d-none');
            bankSelect.required = false;
            bankSelect.value = '';
          }
        }
      }
    }
  }

  document.addEventListener('change', function (e) {
    handleElementChange(e.target);
  });

  if (window.jQuery) {
    window.jQuery(document).on('change', 'select', function () {
      handleElementChange(this);
    });
  }

  window.BankPaymentFields = {
    initGroup,
    initGroups(groups) {
      groups.forEach(group => initGroup(group));
    },
    queueGroups(groups) {
      window._bankPaymentGroupsQueue = groups;
      bootPendingGroups();
    },
    resetToInHand,
    prepareFormData,
    validateBankPayment,
    updateBankPaymentDisplay,
    handleMethodChange,
    handleBankChange,
    getGroupConfig,
    getSelectedBankOption,
    autoSelectBank,
    buildBankDetailsHtml
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPendingGroups);
  } else {
    bootPendingGroups();
  }
})();
