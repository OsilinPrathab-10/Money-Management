/**
 * FD Applications list, apply modal, and view actions
 */

'use strict';

$(function () {
  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) {
    baseUrl += '/';
  }

  const csrfToken = $('meta[name="csrf-token"]').attr('content');
  const formatRupee = (amount) => {
    const value = parseFloat(amount) || 0;
    return '₹' + value.toLocaleString('en-IN', { maximumFractionDigits: 2 });
  };

  const toYmd = (value) => {
    if (!value) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
    const parts = value.split(/[-/]/);
    if (parts.length === 3 && parts[2].length === 4) {
      return parts[2] + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
    }
    return value;
  };

  const dtTable = $('.datatables-fd-applications');
  const urlParamsEarly = new URLSearchParams(window.location.search);
  const urlStatus = urlParamsEarly.get('status');
  if (urlStatus && ['pending', 'approved', 'booked', 'rejected'].includes(urlStatus)) {
    $('#statusFilter').val(urlStatus);
  }

  if (dtTable.length) {
    const dtFdApplications = dtTable.DataTable({
      processing: true,
      serverSide: true,
      scrollX: true,
      autoWidth: false,
      ajax: {
        url: `${baseUrl}admin/fd/applications/data`,
        dataSrc: 'data',
        data: function (d) {
          d.from_date = $('#fromDate').val();
          d.to_date = $('#toDate').val();
          d.status = $('#statusFilter').val();
        }
      },
      columns: [
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        {
          data: 'application_number',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'client_name',
          render: function (data) {
            return '<span class="fw-medium text-heading">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'client_phone',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'zone',
          render: function (data) {
            return '<span class="badge bg-label-secondary">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'scheme_name',
          render: function (data) {
            return '<span class="fw-medium text-heading">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'deposit_amount',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'status',
          render: function (data, type, full) {
            return '<span class="badge rounded-pill bg-label-' + (full.status_color || 'secondary') + '">' + (full.status_label || data) + '</span>';
          }
        },
        {
          data: 'applied_at',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'id',
          orderable: false,
          searchable: false,
          render: function (data, type, full) {
            let html = '<div class="d-flex align-items-center gap-3">' +
              `<a href="${baseUrl}admin/fd/applications/${full.id}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Application"><i class="icon-base ri ri-eye-line icon-22px"></i></a>`;
            if (full.status === 'booked' && full.fixed_deposit_id) {
              html += `<a href="${baseUrl}admin/fd/deposits/${full.fixed_deposit_id}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View FD"><i class="icon-base ri ri-safe-2-line icon-22px"></i></a>`;
            }
            html += '</div>';
            return html;
          }
        }
      ],
      order: [[1, 'desc']],
      language: {
        search: '',
        searchPlaceholder: 'Search Applications',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      buttons: [],
      columnDefs: [{ targets: '_all', className: 'text-nowrap' }]
    });

    $('#statusFilter, #fromDate, #toDate').on('change', function () {
      dtFdApplications.ajax.reload();
    });

    $('#card-total-applications').on('click', function () { $('#statusFilter').val('').trigger('change'); });
    $('#card-pending-applications').on('click', function () { $('#statusFilter').val('pending').trigger('change'); });
    $('#card-approved-applications').on('click', function () { $('#statusFilter').val('approved').trigger('change'); });
    $('#card-booked-applications').on('click', function () { $('#statusFilter').val('booked').trigger('change'); });
    $('#card-rejected-applications').on('click', function () { $('#statusFilter').val('rejected').trigger('change'); });

    $(window).on('resize', function () {
      dtFdApplications.columns.adjust();
    });
  }

  if (typeof flatpickr !== 'undefined') {
    $('.form-apply-fd .flatpickr-date').each(function () {
      if (!this._flatpickr) {
        flatpickr(this, { dateFormat: 'd-m-Y', allowInput: true });
      }
    });
  }

  if ($.fn.select2) {
    $('.form-apply-fd .select2').each(function () {
      const $el = $(this);
      if ($el.hasClass('select2-hidden-accessible')) return;
      $el.select2({
        dropdownParent: $el.closest('.modal'),
        placeholder: $el.data('placeholder') || 'Select',
        allowClear: true,
        width: '100%'
      });
    });
  }

  const bindFdForm = ($form) => {
    const $scheme = $form.find('.fd-scheme-select');
    const $amountInput = $form.find('.fd-amount-input');
    const $amountSlider = $form.find('.fd-amount-slider');
    const $tenureInput = $form.find('.fd-tenure-input');
    const $tenureSlider = $form.find('.fd-tenure-slider');
    let calcTimer = null;

    const applySchemeLimits = () => {
      const $opt = $scheme.find('option:selected');
      if (!$opt.val()) return;
      const minAmount = parseFloat($opt.data('min-amount')) || 0;
      const maxAmount = parseFloat($opt.data('max-amount')) || 0;
      const minTenure = parseInt($opt.data('min-tenure'), 10) || 1;
      const maxTenure = parseInt($opt.data('max-tenure'), 10) || 1;
      const tenureType = $opt.data('tenure-type') || 'months';
      const rate = parseFloat($opt.data('rate')) || 0;
      const depositType = $opt.data('deposit-type') || '—';
      const frequencyLabel = $opt.data('frequency-label') || '—';

      $form.find('.fd-info-rate').text(rate.toFixed(2) + '%');
      $form.find('.fd-info-type').text(depositType);
      $form.find('.fd-info-frequency').text(frequencyLabel);
      $form.find('.fd-preview-frequency').text(frequencyLabel);

      $form.find('.fd-amount-range-info').text(`Min ₹${minAmount.toLocaleString('en-IN')} · Max ₹${maxAmount.toLocaleString('en-IN')}`);
      $form.find('.fd-min-amount-label').text('Min: ₹' + minAmount.toLocaleString('en-IN'));
      $form.find('.fd-max-amount-label').text('Max: ₹' + maxAmount.toLocaleString('en-IN'));
      $amountInput.attr({ min: minAmount, max: maxAmount });
      $amountSlider.attr({ min: minAmount, max: maxAmount, step: 1000 });
      if (!$amountInput.val()) {
        $amountInput.val(minAmount);
        $amountSlider.val(minAmount);
      }

      $form.find('.fd-tenure-unit').text(tenureType);
      $form.find('.fd-min-tenure-label').text(minTenure + ' ' + tenureType);
      $form.find('.fd-max-tenure-label').text(maxTenure + ' ' + tenureType);
      $tenureInput.attr({ min: minTenure, max: maxTenure });
      $tenureSlider.attr({ min: minTenure, max: maxTenure });
      if (!$tenureInput.val()) {
        $tenureInput.val(minTenure);
        $tenureSlider.val(minTenure);
      }
      $form.find('.fd-display-tenure').text(($tenureInput.val() || minTenure) + ' ' + tenureType);

      previewCalc();
    };

    const previewCalc = () => {
      clearTimeout(calcTimer);
      calcTimer = setTimeout(function () {
        const schemeId = $scheme.val();
        const amount = $amountInput.val();
        const tenure = $tenureInput.val();
        const startDate = toYmd($form.find('.fd-start-date').val());
        if (!schemeId || !amount || !tenure || !startDate) return;

        $.get(`${baseUrl}admin/fd/deposits/calculate`, {
          scheme_id: schemeId,
          deposit_amount: amount,
          tenure: tenure,
          start_date: startDate
        }).done(function (res) {
          $form.find('.fd-preview-interest').text(formatRupee(res.interest_amount));
          $form.find('.fd-preview-maturity').text(formatRupee(res.maturity_amount));
          $form.find('.fd-preview-date').text(res.maturity_date_formatted || res.maturity_date || '—');
          if (res.interest_frequency_label) {
            $form.find('.fd-preview-frequency').text(res.interest_frequency_label);
            $form.find('.fd-info-frequency').text(res.interest_frequency_label);
          }
        });
      }, 250);
    };

    $scheme.on('change', applySchemeLimits);
    $amountInput.on('input change', function () {
      $amountSlider.val($(this).val());
      previewCalc();
    });
    $amountSlider.on('input', function () {
      $amountInput.val($(this).val());
      previewCalc();
    });
    $tenureInput.on('input change', function () {
      $tenureSlider.val($(this).val());
      const tenureType = $scheme.find('option:selected').data('tenure-type') || 'months';
      $form.find('.fd-display-tenure').text(($(this).val() || '-') + ' ' + tenureType);
      previewCalc();
    });
    $tenureSlider.on('input', function () {
      $tenureInput.val($(this).val()).trigger('change');
    });
    $form.find('.fd-start-date').on('change', previewCalc);

    $form.on('submit', function (e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        this.reportValidity();
        return;
      }
      const btn = $form.find('button[type="submit"]');
      const orig = btn.html();
      btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

      $.ajax({
        url: `${baseUrl}admin/fd/applications`,
        type: 'POST',
        data: $form.serialize(),
        success: function (res) {
          if (res.success) {
            Swal.fire({
              title: 'Success!',
              text: res.message,
              icon: 'success',
              customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
              window.location.href = res.redirect || window.location.href;
            });
          } else {
            Swal.fire({ icon: 'error', title: 'Error!', text: res.message || 'Something went wrong' });
            btn.prop('disabled', false).html(orig);
          }
        },
        error: function (xhr) {
          btn.prop('disabled', false).html(orig);
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: xhr.responseJSON?.message || 'Something went wrong'
          });
        }
      });
    });
  };

  $('.form-apply-fd').each(function () {
    bindFdForm($(this));
  });

  $(document).on('click', '#btnOpenApplyFdModal', function (e) {
    if (e) e.preventDefault();
    const modalEl = document.getElementById('modalApplyFdGeneric') || document.getElementById('modalApplyFd');
    if (modalEl) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  });

  const requireAdmin = () => {
    if (window.isAdmin) return true;
    Swal.fire({ icon: 'warning', title: 'Not allowed', text: 'Only administrators and staff can perform this action.' });
    return false;
  };

  $(document).on('click', '.btn-approve-fd-app', function () {
    if (!requireAdmin()) return;
    const $btn = $(this);
    if ($btn.data('busy')) return;
    $btn.data('busy', 1).prop('disabled', true);
    const id = $btn.data('id');
    const name = $btn.data('name') || 'this client';
    Swal.fire({
      title: 'Approve FD Application?',
      html: `Approve <strong>${name}</strong>'s fixed deposit application?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Approve',
      customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-outline-secondary' }
    }).then((result) => {
      if (!result.isConfirmed) {
        $btn.data('busy', 0).prop('disabled', false);
        return;
      }
      $.post(`${baseUrl}admin/fd/applications/${id}/approve`, { _token: csrfToken })
        .done(function (res) {
          Swal.fire({ icon: 'success', title: 'Approved', text: res.message }).then(() => window.location.reload());
        })
        .fail(function (xhr) {
          $btn.data('busy', 0).prop('disabled', false);
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to approve.' });
        });
    });
  });

  $(document).on('click', '.btn-reject-fd-app', function () {
    if (!requireAdmin()) return;
    const $btn = $(this);
    if ($btn.data('busy')) return;
    $btn.data('busy', 1).prop('disabled', true);
    const id = $(this).data('id');
    const name = $(this).data('name') || 'this client';
    Swal.fire({
      title: 'Reject FD Application?',
      html: `Reject <strong>${name}</strong>'s application?`,
      input: 'textarea',
      inputPlaceholder: 'Reason (optional)',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Reject',
      customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-outline-secondary' }
    }).then((result) => {
      if (!result.isConfirmed) {
        $btn.data('busy', 0).prop('disabled', false);
        return;
      }
      $.post(`${baseUrl}admin/fd/applications/${id}/reject`, { _token: csrfToken, reason: result.value || '' })
        .done(function (res) {
          Swal.fire({ icon: 'success', title: 'Rejected', text: res.message }).then(() => window.location.reload());
        })
        .fail(function (xhr) {
          $btn.data('busy', 0).prop('disabled', false);
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to reject.' });
        });
    });
  });

  $(document).on('click', '.btn-book-fd-app', function () {
    if (!requireAdmin()) return;
    const $btn = $(this);
    if ($btn.data('busy')) return;
    $btn.data('busy', 1).prop('disabled', true);
    const id = $btn.data('id');
    const name = $btn.data('name') || 'this client';
    Swal.fire({
      title: 'Book Fixed Deposit?',
      html: `This will create a live FD for <strong>${name}</strong>.<br><br>
        <label class="form-label text-start d-block">Payment Mode</label>
        <select id="fdBookPaymentMode" class="form-select">
          <option value="cash">Cash</option>
          <option value="upi">UPI</option>
          <option value="bank_transfer">Bank Transfer</option>
        </select>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Book FD',
      customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-outline-secondary' },
      preConfirm: () => document.getElementById('fdBookPaymentMode').value
    }).then((result) => {
      if (!result.isConfirmed) {
        $btn.data('busy', 0).prop('disabled', false);
        return;
      }
      $.post(`${baseUrl}admin/fd/applications/${id}/book`, { _token: csrfToken, payment_mode: result.value || 'cash' })
        .done(function (res) {
          Swal.fire({ icon: 'success', title: 'Booked', text: res.message }).then(() => {
            window.location.href = res.redirect || window.location.href;
          });
        })
        .fail(function (xhr) {
          $btn.data('busy', 0).prop('disabled', false);
          Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to book FD.' });
        });
    });
  });
});
