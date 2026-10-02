'use strict';

document.addEventListener('DOMContentLoaded', function () {
  if (typeof $ !== 'undefined' && $.fn.select2) {
    $('.select2').select2({
      dropdownParent: $('.modal.show').length ? $('.modal.show') : $(document.body),
      width: '100%'
    });

    $('.modal').on('shown.bs.modal', function () {
      $(this).find('.select2').select2({
        dropdownParent: $(this),
        width: '100%'
      });
    });
  }

  const config = window.chitFamilyConfig || {};
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const relationshipOptions = ['Husband','Father', 'Mother', 'Son', 'Daughter', 'Spouse', 'Brother', 'Sister', 'Other'];

  function buildRelationshipFields() {
    const select = document.getElementById('familyMembersSelect');
    const wrap = document.getElementById('memberRelationshipsWrap');
    const container = document.getElementById('memberRelationships');
    if (!select || !wrap || !container) return;

    let labels = {};
    try {
      labels = JSON.parse(select.dataset.clientLabels || '{}');
    } catch (e) {
      labels = {};
    }

    const selected = Array.from(select.selectedOptions).map((o) => o.value);
    container.innerHTML = '';

    if (!selected.length) {
      wrap.style.display = 'none';
      return;
    }

    wrap.style.display = '';
    selected.forEach((clientId) => {
      const name = labels[clientId] || `Client #${clientId}`;
      const col = document.createElement('div');
      col.className = 'col-md-6';
      col.innerHTML = `
        <label class="form-label small mb-1">${name}</label>
        <select name="relationships[${clientId}]" class="form-select form-select-sm">
          <option value="">— None —</option>
          ${relationshipOptions.map((r) => `<option value="${r}">${r}</option>`).join('')}
        </select>
      `;
      container.appendChild(col);
    });
  }

  const familyMembersSelect = document.getElementById('familyMembersSelect');
  if (familyMembersSelect) {
    familyMembersSelect.addEventListener('change', buildRelationshipFields);
    if (typeof $ !== 'undefined') {
      $(familyMembersSelect).on('select2:select select2:unselect', buildRelationshipFields);
    }
    buildRelationshipFields();
  }

  function formatMoney(value) {
    return '₹' + Number(value || 0).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function getSelectedDues() {
    const checkboxes = document.querySelectorAll('.due-checkbox:checked');
    let total = 0;
    const ids = [];
    checkboxes.forEach((cb) => {
      ids.push(cb.value);
      total += parseFloat(cb.dataset.balance || 0);
    });
    return { ids, total, count: ids.length };
  }

  function updateSelectionUi() {
    const { total, count } = getSelectedDues();
    const countEls = document.querySelectorAll('#selectedCount');
    const totalEl = document.getElementById('selectedTotal');
    const barTotal = document.getElementById('barTotalAmount');
    const bulkBar = document.getElementById('bulkPayBar');
    const btnBulk = document.getElementById('btnBulkPayAll');

    countEls.forEach((el) => { el.textContent = count; });
    if (totalEl) totalEl.textContent = formatMoney(total);
    if (barTotal) barTotal.textContent = formatMoney(total);
    if (btnBulk) btnBulk.disabled = count === 0;
    if (bulkBar) {
      bulkBar.classList.toggle('d-none', count === 0);
    }
  }

  const selectAll = document.getElementById('selectAllDues');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.due-checkbox').forEach((cb) => {
        cb.checked = selectAll.checked;
      });
      updateSelectionUi();
    });
  }

  document.querySelectorAll('.due-checkbox').forEach((cb) => {
    cb.addEventListener('change', updateSelectionUi);
  });

  if (document.querySelectorAll('.due-checkbox').length) {
    updateSelectionUi();
  }

  // Toggle Amount to Pay input based on Payment Type
  const payTypeFull = document.getElementById('payTypeFull');
  const payTypePartial = document.getElementById('payTypePartial');
  const totalAmountPaidWrapper = document.getElementById('totalAmountPaidWrapper');
  const totalAmountPaidInput = document.getElementById('totalAmountPaidInput');
  const totalAmountLabel = document.getElementById('totalAmountLabel');

  function togglePaymentTypeFields() {
    if (payTypePartial?.checked) {
      if (totalAmountLabel) totalAmountLabel.textContent = 'Selected Total';
      if (totalAmountPaidWrapper) totalAmountPaidWrapper.style.display = 'block';
      if (totalAmountPaidInput) {
        totalAmountPaidInput.required = true;
        const { total } = getSelectedDues();
        if (!totalAmountPaidInput.value) {
          totalAmountPaidInput.value = total.toFixed(2);
        }
      }
    } else {
      if (totalAmountLabel) totalAmountLabel.textContent = 'Total Amount';
      if (totalAmountPaidWrapper) totalAmountPaidWrapper.style.display = 'none';
      if (totalAmountPaidInput) {
        totalAmountPaidInput.required = false;
      }
    }
  }

  payTypeFull?.addEventListener('change', togglePaymentTypeFields);
  payTypePartial?.addEventListener('change', togglePaymentTypeFields);

  function openBulkPayModal() {
    const { total, count } = getSelectedDues();
    if (!count) {
      Swal.fire({ icon: 'warning', title: 'No Selection', text: 'Please select at least one installment to pay.' });
      return;
    }

    document.getElementById('bulkPayCount').textContent = count;
    document.getElementById('bulkPayAmount').value = total.toFixed(2);

    if (payTypeFull) {
      payTypeFull.checked = true;
    }
    if (totalAmountPaidInput) {
      totalAmountPaidInput.value = total.toFixed(2);
    }
    togglePaymentTypeFields();

    if (window.BankPaymentFields) {
      window.BankPaymentFields.resetToInHand('bulkPayMethod');
    }

    const modal = new bootstrap.Modal(document.getElementById('bulkPayModal'));
    modal.show();
  }

  document.getElementById('btnBulkPayAll')?.addEventListener('click', openBulkPayModal);
  document.getElementById('btnOpenBulkPayModal')?.addEventListener('click', openBulkPayModal);

  const bulkPayForm = document.getElementById('bulkPayForm');
  if (bulkPayForm && config.bulkCollectUrl) {
    bulkPayForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const { ids, count } = getSelectedDues();
      if (!count) return;

      const methodSelect = document.getElementById('bulkPayMethod');
      const bankSelect = document.getElementById('familyBulkBankAccount');

      if (window.BankPaymentFields) {
        const bankValidationError = window.BankPaymentFields.validateBankPayment(methodSelect, bankSelect, bulkPayForm);
        if (bankValidationError) {
          Swal.fire({ icon: 'warning', title: 'Action Required', text: bankValidationError });
          return;
        }
      }

      const formData = new FormData(bulkPayForm);
      const paymentMode = formData.get('payment_mode');
      const bankAccountId = formData.get('internal_bank_account_id');

      if ((paymentMode === 'upi' || paymentMode === 'bank_transfer') && !bankAccountId) {
        Swal.fire({
          icon: 'warning',
          title: 'Bank Account Required',
          text: 'Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer.'
        });
        return;
      }

      const submitBtn = document.getElementById('bulkPaySubmitBtn');
      const spinner = submitBtn.querySelector('.spinner-border');
      submitBtn.disabled = true;
      if (spinner) spinner.classList.remove('d-none');

      const payload = {
        installment_ids: ids,
        payment_mode: paymentMode,
        paid_date: formData.get('paid_date'),
        reference_no: formData.get('reference_no') || null,
        remarks: formData.get('remarks') || null,
        collection_type: formData.get('collection_type') || 'full',
        total_amount_paid: formData.get('collection_type') === 'partial' ? parseFloat(formData.get('total_amount_paid')) : null,
        internal_bank_account_id: (paymentMode === 'upi' || paymentMode === 'bank_transfer') ? bankAccountId : null,
      };

      fetch(config.bulkCollectUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          submitBtn.disabled = false;
          if (spinner) spinner.classList.add('d-none');

          if (ok && data.success) {
            bootstrap.Modal.getInstance(document.getElementById('bulkPayModal'))?.hide();

            let actionHtml = `<div class="alert alert-success py-2 px-3 mb-3 text-start small">${data.message}</div>`;

            if (data.collected_members && data.collected_members.length > 0) {
              actionHtml += `
                <div class="text-start mb-2">
                  <span class="fw-semibold text-heading small">Send Separate Notifications to Paid Members:</span>
                </div>
                <div class="table-responsive text-start border rounded mb-3" style="max-height: 220px;">
                  <table class="table table-sm text-nowrap mb-0 align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Member</th>
                        <th>Amount Paid</th>
                        <th class="text-center">Send Separate</th>
                      </tr>
                    </thead>
                    <tbody>
              `;

              data.collected_members.forEach((m) => {
                const amountFormatted = '₹' + Number(m.amount_paid || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                let actions = '';
                if (m.whatsapp_url) {
                  actions += `<a href="${m.whatsapp_url}" target="_blank" class="btn btn-xs btn-icon btn-text-secondary rounded-pill text-success me-1" title="Send WhatsApp to ${m.client_name}">
                    <i class="icon-base ri ri-whatsapp-line icon-18px"></i>
                  </a>`;
                }
                if (m.sms_url) {
                  actions += `<a href="${m.sms_url}" class="btn btn-xs btn-icon btn-text-secondary rounded-pill text-info me-1" title="Send SMS to ${m.client_name}">
                    <i class="icon-base ri ri-message-3-line icon-18px"></i>
                  </a>`;
                }
                if (m.client_phone) {
                  actions += `<a href="tel:${m.client_phone.replace(/\s+/g, '')}" class="btn btn-xs btn-icon btn-text-secondary rounded-pill" title="Call ${m.client_name}">
                    <i class="icon-base ri ri-phone-line icon-18px"></i>
                  </a>`;
                }
                if (!actions) {
                  actions = `<span class="text-muted small">No Phone</span>`;
                }

                actionHtml += `
                  <tr>
                    <td>
                      <div class="fw-semibold small">${m.client_name}</div>
                      <small class="text-muted">${m.client_phone || ''}</small>
                    </td>
                    <td class="fw-semibold text-success small">${amountFormatted}</td>
                    <td class="text-center">${actions}</td>
                  </tr>
                `;
              });

              actionHtml += `
                    </tbody>
                  </table>
                </div>
              `;
            }

            if (data.whatsapp_url || data.sms_url) {
              actionHtml += `
                <div class="text-start mb-2">
                  <span class="fw-semibold text-heading small">Send Primary / Family Summary Confirmation:</span>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
              `;
              if (data.whatsapp_url) {
                actionHtml += `<a href="${data.whatsapp_url}" target="_blank" class="btn btn-sm btn-success"><i class="icon-base ri ri-whatsapp-line me-1"></i> WhatsApp Summary</a>`;
              }
              if (data.sms_url) {
                actionHtml += `<a href="${data.sms_url}" class="btn btn-sm btn-info"><i class="icon-base ri ri-message-3-line me-1"></i> SMS Summary</a>`;
              }
              actionHtml += `</div>`;
            }

            Swal.fire({
              icon: 'success',
              title: 'Family Payment Completed!',
              html: actionHtml,
              confirmButtonText: 'Done / Close',
              customClass: {
                confirmButton: 'btn btn-secondary mt-3'
              },
              buttonsStyling: false
            }).then(() => window.location.reload());
          } else {
            const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Payment failed.');
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
          }
        })
        .catch(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
          Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
        });
    });
  }
});
