/**
 * Loan Applications List
 */

'use strict';

// Datatable (jquery)
$(function () {
  let borderColor, bodyBg, headingColor;

  // Use the global baseUrl defined in the layout, or fallback
  let baseUrl = document.documentElement.getAttribute('data-base-url') || window.location.origin;
  if (!baseUrl.endsWith('/')) {
    baseUrl += '/';
  }

  const isDark = (typeof isDarkStyle !== 'undefined' && isDarkStyle) || (window.isDarkStyle || false);
  if (isDark && config.colors_dark) {
    borderColor = config.colors_dark.borderColor;
    bodyBg = config.colors_dark.bodyBg;
    headingColor = config.colors_dark.headingColor;
  } else {
    borderColor = config.colors.borderColor;
    bodyBg = config.colors.bodyBg;
    headingColor = config.colors.headingColor;
  }

  // Variable declaration for table
  var dt_loan_applications_table = $('.datatables-loan-applications');

  // Apply dashboard / deep-link status filter before DataTable loads
  const urlParamsEarly = new URLSearchParams(window.location.search);
  const urlStatus = urlParamsEarly.get('status');
  if (urlStatus && ['pending', 'approved', 'process', 'rejected', 'disbursed'].includes(urlStatus)) {
    $('#statusFilter').val(urlStatus);
  }

  // Loan Applications datatable
  if (dt_loan_applications_table.length) {
    var dt_loan_applications = dt_loan_applications_table.DataTable({
      processing: true,
      serverSide: true,
      scrollX: true,
      autoWidth: false,
      ajax: {
        url: `${baseUrl}loan/loan-applications/data`,
        dataSrc: 'data',
        data: function (d) {
          d.from_date = $('#fromDate').val();
          d.to_date = $('#toDate').val();
          d.status = $('#statusFilter').val();
          d.loan_mode = $('#loanModeFilter').val();
          d.loan_type_id = $('#loanTypeFilter').val();
        }
      },
      columns: [
        // S.No column
        {
          data: null,
          title: 'S.No',
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            return meta.settings._iDisplayStart + meta.row + 1;
          }
        },
        {
          data: 'application_number',
          title: 'Application Number',
          render: function (data, type, full, meta) {
            var $application_number = full['application_number'];
            return '<span class="fw-medium">' + $application_number + '</span>';
          }
        },
        {
          data: 'borrower_name',
          title: 'Borrower Name',
          render: function (data) {
            return '<span class="fw-medium text-heading">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'borrower_phone',
          title: 'Phone Number',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'zone',
          title: 'Zone',
          render: function (data) {
            return '<span class="badge bg-label-secondary">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'loan_name',
          title: 'Loan Name',
          render: function (data, type, full, meta) {
            var $loan_name = full['loan_name'];
            return '<span class="fw-medium text-heading">' + $loan_name + '</span>';
          }
        },
        {
          data: 'loan_amount',
          title: 'Loan Amount',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'status',
          title: 'Status',
          render: function (data, type, full, meta) {
            var $status_label = full['status_label'];
            var $status_color = full['status_color'];
            return '<span class="badge rounded-pill bg-label-' + $status_color + '">' + $status_label + '</span>';
          }
        },
        {
          data: 'applied_at',
          title: 'Applied On',
          render: function (data) {
            return '<span class="fw-medium">' + (data || 'N/A') + '</span>';
          }
        },
        {
          data: 'id',
          title: 'Actions',
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            var $status = (full['status'] || '').toLowerCase();
            var actionHtml = '<div class="d-flex align-items-center gap-3">' +
              `<a href="${baseUrl}loan-application/view/${full.id}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Application"><i class="icon-base ri ri-eye-line icon-22px"></i></a>`;
            
            if ($status === 'disbursed' || $status === 'active' || $status === 'closed') {
              actionHtml += `<a href="${baseUrl}emi/receipts?application_number=${full.application_number}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Receipts"><i class="icon-base ri ri-file-list-3-line icon-22px"></i></a>`;
              if (full.loan_account_id) {
                actionHtml += `<a href="${baseUrl}loan/loan-account/${full.loan_account_id}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Account"><i class="icon-base ri ri-bank-card-line icon-22px"></i></a>`;
              }
            }

            if (window.isAdmin) {
              actionHtml += `<button class="btn btn-icon btn-text-danger btn-sm rounded-pill delete-loan-application" data-id="${full.id}" title="Delete Application"><i class="icon-base ri ri-delete-bin-7-line icon-22px"></i></button>`;
            }
            
            actionHtml += '</div>';
            return actionHtml;
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"' +
        '<"me-5 ms-n2"f>' +
        '<"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-4"B>>' +
        '>t' +
        '<"row mx-1"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      language: {
        search: '',
        searchPlaceholder: 'Search Applications',
        paginate: {
          next: '<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',
          previous: '<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',
          first: '<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',
          last: '<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'
        }
      },
      buttons: [],
      scrollX: true,
      autoWidth: false,
      columnDefs: [
        {
          targets: '_all',
          className: 'text-nowrap'
        },
        {
          targets: [1], // Application Number
          width: '180px'
        },
        {
          targets: [2], // Borrower Name
          width: '220px'
        },
        {
          targets: [5], // Loan Name
          width: '200px'
        }
      ]
    });

    // Connect status/date/mode/type filters to DataTable
    $('#statusFilter, #loanModeFilter, #loanTypeFilter, #fromDate, #toDate').on('change', function () {
      dt_loan_applications.ajax.reload();
    });

    // Dashboard Card Filtering
    $('#card-total-applications').on('click', function() { $('#statusFilter').val('').trigger('change'); });
    $('#card-pending-applications').on('click', function() { $('#statusFilter').val('pending').trigger('change'); });
    $('#card-disbursed-applications').on('click', function() { $('#statusFilter').val('disbursed').trigger('change'); });
    $('#card-rejected-applications').on('click', function() { $('#statusFilter').val('rejected').trigger('change'); });
    $('#card-process-applications').on('click', function() { $('#statusFilter').val('process').trigger('change'); });

    // Fixed header alignment on resize
    $(window).on('resize', function() {
      dt_loan_applications.columns.adjust();
    });
  }

  // View Record
  document.addEventListener('click', function (e) {
    if (e.target.closest('.view-record')) {
      const btn = e.target.closest('.view-record');
      const application_id = btn.dataset.id;

      // Navigate to loan application view page
      window.location.href = `${baseUrl}loan-application/view/${application_id}`;
    }
  });

  // Pre-select client if client_id is in URL
  const urlParams = new URLSearchParams(window.location.search);
  const clientIdParam = urlParams.get('client_id');
  const targetModalId = $('#modalApplyLoanGeneric').length ? 'modalApplyLoanGeneric' : 'modalApplyLoan';

  const formatRupeeLabel = (amount) => {
    const value = Math.round(parseFloat(amount) || 0);
    return `₹${value.toLocaleString('en-IN', { maximumFractionDigits: 0 })}`;
  };

  const showApplyLoanModal = (modalEl, onShown) => {
    if (!modalEl || modalEl.classList.contains('show')) {
      return;
    }
    const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
    if (typeof onShown === 'function') {
      $(modalEl).off('shown.bs.modal.applyLoanOpen').one('shown.bs.modal.applyLoanOpen', onShown);
    }
    instance.show();
  };

  if (clientIdParam && document.getElementById(targetModalId)) {
    const modalEl = document.getElementById(targetModalId);
    showApplyLoanModal(modalEl, function () {
      const select = modalEl.querySelector('#apply_client_id');
      if (select) {
        $(select).val(clientIdParam).trigger('change');
      }
    });
  }

  $(document).on('click', '#btnOpenApplyLoanModal', function (e) {
    e.preventDefault();
    const modalEl = document.getElementById('modalApplyLoanGeneric') || document.getElementById('modalApplyLoan');
    showApplyLoanModal(modalEl);
  });

  // =========================================================================
  // QUICK APPLY LOAN MODAL LOGIC
  // =========================================================================
  const modalApplyLoan = $('#modalApplyLoan, #modalApplyLoanGeneric');
  const formApplyLoan = $('#formApplyLoan, #formApplyLoanGeneric');

  // Function to initialize Select2 for elements in the modal
  function initModalSelect2(targetModal) {
    const parent = $(targetModal);
    $('.select2', parent).each(function() {
      const $this = $(this);
      
      if ($this.hasClass('select2-hidden-accessible')) {
        $this.select2('destroy');
      }

      let placeholder = $this.attr('placeholder') || $this.data('placeholder') || 'Select Option';
      if ($this.attr('id') === 'apply_client_id') placeholder = 'Select Verified Client';
      if ($this.attr('id') === 'loan_type_filter' || $this.hasClass('loan-type-filter')) placeholder = 'All Loan Types';
      if ($this.attr('id') === 'loan_product') placeholder = 'Select Loan Product';
      if ($this.attr('id') === 'payment_gateway') placeholder = 'Select Gateway';
      
      $this.select2({
        dropdownParent: parent.find('.modal-content'),
        width: '100%',
        placeholder: placeholder,
        allowClear: true,
        closeOnSelect: true
      }).on('select2:select', function () {
        $(this).select2('close');
      }).on('select2:clear', function () {
        $(this).trigger('change');
      });
    });

    // Initialize Flatpickr for date fields
    const flatpickrInstances = {};
    if (typeof flatpickr !== 'undefined') {
      $('.flatpickr-date', parent).each(function() {
        const $el = $(this);
        const instance = flatpickr(this, {
          monthSelectorType: 'static',
          dateFormat: 'd-m-Y',
          onChange: function(selectedDates, dateStr, instance) {
            if ($el.attr('id') === 'emi_start_date' && selectedDates.length > 0) {
              updateEmiDayFromDate(selectedDates[0], parent.find('form'));
            }
          }
        });
        if ($el.attr('id')) {
          flatpickrInstances[$el.attr('id')] = instance;
        }
      });
    }

    // Store instances in the modal/target for later retrieval
    targetModal.data('flatpickrInstances', flatpickrInstances);
    parent.data('flatpickrInstances', flatpickrInstances);
  }

  if (modalApplyLoan.length) {
    function normalizeTermUnit(rawTermUnit) {
      const v = String(rawTermUnit ?? '').toLowerCase();
      if (['week', 'weeks', 'weekly'].includes(v)) return 'weeks';
      if (['day', 'days', 'daily'].includes(v)) return 'days';
      if (['month', 'months', 'monthly'].includes(v)) return 'months';
      return 'months';
    }

    // Eligibility Check on Client Selection
    $(document).on('change', '#apply_client_id', function () {
      const clientId = $(this).val();
      const currentForm = $(this).closest('form');
      const submitBtn = currentForm.find('button[type="submit"]');
      const originalSubmitHtml = submitBtn.data('orig-html') || submitBtn.html();
      submitBtn.data('orig-html', originalSubmitHtml);

      // Remove any previous eligibility alert
      currentForm.find('#eligibilityAlert').remove();
      currentForm.find('#chitDetailsPanel').hide();
      submitBtn.prop('disabled', false).html(originalSubmitHtml).removeAttr('title');

      if (!clientId) return;

      $.ajax({
        url: `${baseUrl}loan-application/check-eligibility`,
        type: 'POST',
        data: {
          client_id: clientId,
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (res) {
          if (!res.eligible) {
            const alertHtml = `<div id="eligibilityAlert" class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-0 mt-3" role="alert">
              <i class="ri-error-warning-line ri-20px"></i>
              <span>${res.message}</span>
            </div>`;
            currentForm.find('#apply_client_id').closest('.col-12').append(alertHtml);

            submitBtn.prop('disabled', true)
              .html('<i class="ri-lock-line me-1"></i> Cannot Apply')
              .attr('title', res.message);
          } else {
            submitBtn.prop('disabled', false).html(originalSubmitHtml).removeAttr('title');
          }

          if (res.chit_details && res.chit_details.has_chit) {
            renderChitDetails(res.chit_details);
          }
        },
        error: function () {
          // Silently fail
        }
      });
    });

    function renderChitDetails(chit) {
      const fmt = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

      let html = '<div><strong>Client has ' + chit.total_groups + ' chit ' + (chit.total_groups === 1 ? 'group' : 'groups') + '</strong>';
      html += ' — Total: <strong class="text-primary">' + fmt(chit.total_chit_value) + '</strong>';
      html += ', Monthly: <strong>' + fmt(chit.total_monthly_installment) + '</strong>';
      if (chit.total_overdue > 0) {
        html += ', Overdue: <strong class="text-danger">' + fmt(chit.total_overdue) + '</strong>';
      }
      html += '</div>';

      if (chit.groups && chit.groups.length) {
        html += '<div class="mt-1" style="font-size: 0.82rem;">';
        chit.groups.forEach(function (g, i) {
          if (i > 0) html += ' &nbsp;|&nbsp; ';
          html += '<span class="fw-semibold">' + g.group_code + '</span> <span class="text-muted">(' + fmt(g.chit_value) + ')</span>';
        });
        html += '</div>';
      }

      $('#chitSummaryText').html(html);
      $('#chitDetailsPanel').slideDown(200);
    }
    
    const productSelect = $('#loan_product');
    const amountInput = $('#loan_amount_input');
    const amountSlider = $('#loan_amount_slider');
    const tenureInput = $('#tenure_input');
    const tenureSlider = $('#tenure_slider');
    const emiPreview = $('#preview_emi');
    const interestPreview = $('#preview_interest');
    const totalPreview = $('#preview_total');
    const tenureDisplay = $('#display_tenure');

    // Amount Sync - Scoped to form
    $(document).on('input', '#loan_amount_slider', function() {
      const form = $(this).closest('form');
      form.find('#loan_amount_input').val($(this).val());
      updateEmiPreview(form);
    });
    $(document).on('input', '#loan_amount_input', function() {
      const form = $(this).closest('form');
      form.find('#loan_amount_slider').val($(this).val());
      updateEmiPreview(form);
    });

    // Tenure Sync - Scoped to form
    const syncTenureDisplay = (form) => {
      const val = form.find('#tenure_input').val();
      const unit = form.find('.input-group-text.small').first().text().trim() || 'months';
      const tenureDisplayLocal = form.find('#display_tenure');
      if (tenureDisplayLocal.length) {
        tenureDisplayLocal.text(`${val} ${unit}`);
      }
    };

    $(document).on('input', '#tenure_slider', function() {
      const form = $(this).closest('form');
      form.find('#tenure_input').val($(this).val());
      syncTenureDisplay(form);
      updateEmiPreview(form);
    });
    $(document).on('input', '#tenure_input', function() {
      const form = $(this).closest('form');
      form.find('#tenure_slider').val($(this).val());
      syncTenureDisplay(form);
      updateEmiPreview(form);
    });

    // Handle Loan Type Filter Change - Dynamically filters loan products
    $(document).on('change', '.loan-type-filter, #loan_type_filter', function () {
      const form = $(this).closest('form');
      const selectedTypeId = String($(this).val() || '').trim();
      const productSelect = form.find('#loan_product');
      if (!productSelect.length) return;

      // Cache all original options on first interaction
      let allOptions = productSelect.data('all-product-options');
      if (!allOptions || !allOptions.length) {
        allOptions = productSelect.find('option').clone();
        productSelect.data('all-product-options', allOptions);
      }

      const currentVal = productSelect.val();
      productSelect.empty();
      // Add empty placeholder
      productSelect.append($('<option></option>'));

      let matchingVal = null;
      let matchingCount = 0;
      let firstMatchingVal = null;

      allOptions.each(function () {
        const opt = $(this);
        const optVal = opt.val();
        if (!optVal) return;

        const optTypeId = String(opt.data('loan-type-id') ?? '').trim();
        if (!selectedTypeId || selectedTypeId === 'all' || optTypeId === selectedTypeId) {
          productSelect.append(opt.clone());
          matchingCount++;
          if (!firstMatchingVal) {
            firstMatchingVal = optVal;
          }
          if (optVal === currentVal) {
            matchingVal = currentVal;
          }
        }
      });

      // Keep previous value if still valid; otherwise reset
      if (matchingVal) {
        productSelect.val(matchingVal);
      } else {
        productSelect.val('');
        productSelect.trigger('change');
      }

      if (productSelect.hasClass('select2-hidden-accessible')) {
        productSelect.trigger('change.select2');
      }
    });

    // Handle Product Change - Scoped to form
    $(document).on('change', '#loan_product', function () {
      const form = $(this).closest('form');
      const selected = $(this).find(':selected');

      if (!selected.val()) {
        form.find('#loan_amount_input').val('');
        form.find('#loan_amount_slider').val(0);
        form.find('#amount_range_info').text('Select a product first');
        form.find('#min_amount_label').text('Min: ₹0');
        form.find('#max_amount_label').text('Max: ₹0');
        form.find('#preview_emi').text('₹0.00');
        form.find('#preview_interest').text('₹0.00');
        form.find('#preview_total').text('₹0.00');
        return;
      }

      // Sync Loan Type filter if a product is picked directly
      const productTypeId = selected.data('loan-type-id');
      if (productTypeId) {
        const typeFilter = form.find('.loan-type-filter, #loan_type_filter');
        if (typeFilter.length && String(typeFilter.val() || '') !== String(productTypeId)) {
          typeFilter.val(String(productTypeId));
          if (typeFilter.hasClass('select2-hidden-accessible')) {
            typeFilter.trigger('change.select2');
          }
        }
      }

      const interestType = selected.data('interest-type') || 'flat';
      const loanModeContainer = form.find('#loan_mode_container');
      const loanModeToggle = form.find('.loan-mode-toggle');
      
      if (interestType === 'reducing' || interestType === 'declining_balance') {
        loanModeContainer.hide();
        loanModeToggle.prop('checked', false).trigger('change');
      } else {
        loanModeContainer.show();
      }

      const minAmt = Math.round(parseFloat(selected.data('min-amount')) || 0);
      const maxAmt = Math.round(parseFloat(selected.data('max-amount')) || 0);
      const minTen = parseInt(selected.data('min-tenure')) || 1;
      const maxTen = parseInt(selected.data('max-tenure')) || 60;
      const termUnitRaw = selected.data('term-unit') || 'months';
      const termUnit = normalizeTermUnit(termUnitRaw);

      // Update Slider/Input limits
      const amtInput = form.find('#loan_amount_input');
      const amtSlider = form.find('#loan_amount_slider');
      amtInput.attr({ min: minAmt, max: maxAmt }).val(minAmt);
      amtSlider.attr({ min: minAmt, max: maxAmt }).val(minAmt);
      
      form.find('#min_amount_label').text(`Min: ${formatRupeeLabel(minAmt)}`);
      form.find('#max_amount_label').text(`Max: ${formatRupeeLabel(maxAmt)}`);
      form.find('#amount_range_info').text(`Allowed: ${formatRupeeLabel(minAmt)} - ${formatRupeeLabel(maxAmt)}`);

      const tenInput = form.find('#tenure_input');
      const tenSlider = form.find('#tenure_slider');
      const safeMinTen = Math.max(1, minTen);
      const safeMaxTen = Math.max(safeMinTen, maxTen);
      tenInput.attr({ min: safeMinTen, max: safeMaxTen }).val(safeMinTen);
      tenSlider.attr({ min: safeMinTen, max: safeMaxTen }).val(safeMinTen);
      form.find('#min_tenure_label').text(`${safeMinTen} ${termUnit === 'weeks' ? 'w' : (termUnit === 'days' ? 'd' : 'm')}`);
      form.find('#max_tenure_label').text(`${safeMaxTen} ${termUnit === 'weeks' ? 'w' : (termUnit === 'days' ? 'd' : 'm')}`);
      
      // Update unit labels
      form.find('.input-group-text.small').text(termUnit);
      
      syncTenureDisplay(form);
      updateEmiPreview(form);
      
      // Sync frequency dropdown with product's default
      const freqValue = termUnit === 'days' ? 'daily' : (termUnit === 'weeks' ? 'weekly' : 'monthly');
      form.find('#repayment_frequency').val(freqValue).trigger('change');

      // Set initial slider range based on product, ensuring min never exceeds max
      let finalMaxTen = safeMaxTen;
      if (freqValue === 'monthly' && maxTen > 60) {
        finalMaxTen = Math.max(safeMinTen, 60);
      }
      finalMaxTen = Math.max(safeMinTen, finalMaxTen);
      
      tenSlider.attr({ min: safeMinTen, max: finalMaxTen });
      tenInput.attr({ min: safeMinTen, max: finalMaxTen });

      // Update UI Info
      form.find('#amount_range_info').text(`Min: ${formatRupeeLabel(minAmt)} | Max: ${formatRupeeLabel(maxAmt)}`).show();
      form.find('#min_amount_label').text(`Min: ${formatRupeeLabel(minAmt)}`).show();
      form.find('#max_amount_label').text(`Max: ${formatRupeeLabel(maxAmt)}`).show();
      form.find('#min_tenure_label').text(`${minTen} ${termUnit}`).show();
      form.find('#max_tenure_label').text(`${finalMaxTen} ${termUnit}`).show();
      
      // Update Tenure Labels
      form.find('label[for="tenure"]').text(`Tenure (${termUnit.charAt(0).toUpperCase() + termUnit.slice(1)})`);
      form.find('.input-group-text.small').text(termUnit);
      form.find('#display_tenure').text(`${minTen} ${termUnit.charAt(0).toUpperCase() + termUnit.slice(1)}`);
      
      // Update Preview Labels
      let previewLabel = 'Monthly EMI';
      if (termUnit === 'weeks') previewLabel = 'Weekly EMI';
      else if (termUnit === 'days') previewLabel = 'Daily EMI';
      form.find('.preview-label').first().text(previewLabel);
      updateEmiPreview(form);
    });

    // New listener for Repayment Frequency
    $(document).on('change', '#repayment_frequency', function() {
      const form = $(this).closest('form');
      const frequency = $(this).val();
      
      // Calculate target start date based on frequency (1 day, 1 week, or 1 month after today)
      const modal = form.closest('.modal');
      const instances = modal.data('flatpickrInstances');
      if (instances && instances.emi_start_date) {
        let targetDate = new Date();
        if (frequency === 'daily') {
          targetDate.setDate(targetDate.getDate() + 1);
        } else if (frequency === 'weekly') {
          targetDate.setDate(targetDate.getDate() + 7);
        } else {
          targetDate.setMonth(targetDate.getMonth() + 1);
        }
        instances.emi_start_date.setDate(targetDate, false);
      }
      
      updateEmiDayOptions(frequency, form);
      
      // After updating options, sync back from current date if one is selected
      if (instances && instances.emi_start_date && instances.emi_start_date.selectedDates.length > 0) {
        updateEmiDayFromDate(instances.emi_start_date.selectedDates[0], form);
      }
      
      // Update labels and UI based on frequency
      let unitLabel = 'Months';
      let tenureLabel = 'Total Months';
      let previewLabel = 'Monthly EMI';
      let tenurePlaceholder = 'Enter number of months';
      
      if (frequency === 'daily') {
        unitLabel = 'Days';
        tenureLabel = 'Total Days';
        previewLabel = 'Daily EMI';
        tenurePlaceholder = 'Enter number of days';
      } else if (frequency === 'weekly') {
        unitLabel = 'Weeks';
        tenureLabel = 'Total Weeks';
        previewLabel = 'Weekly EMI';
        tenurePlaceholder = 'Enter number of weeks';
      }

      // Update Tenure Section Slider Range
      let maxLimit = 60; // Monthly default
      if (frequency === 'daily') maxLimit = 365;
      else if (frequency === 'weekly') maxLimit = 104;
      
      // If product has a specific min/max, respect it and guarantee min <= max
      const productSelected = form.find('#loan_product :selected');
      const productMin = parseInt(productSelected.data('min-tenure')) || 1;
      const productMax = parseInt(productSelected.data('max-tenure')) || 0;
      
      let finalMin = Math.max(1, productMin);
      let finalMax = productMax > 0 ? productMax : maxLimit;
      if (finalMax < finalMin) {
        finalMax = finalMin;
      }

      const tenureSliderLocal = form.find('#tenure_slider');
      const tenureInputLocal = form.find('#tenure_input');

      tenureSliderLocal.attr({ min: finalMin, max: finalMax });
      tenureInputLocal.attr({ min: finalMin, max: finalMax });

      // Clamp current value to [finalMin, finalMax]
      let currentVal = parseInt(tenureInputLocal.val(), 10);
      if (isNaN(currentVal) || currentVal < finalMin) {
        currentVal = finalMin;
      } else if (currentVal > finalMax) {
        currentVal = finalMax;
      }
      tenureInputLocal.val(currentVal);
      tenureSliderLocal.val(currentVal);
      
      form.find('label[for="tenure"]').html(`${tenureLabel} <span class="text-danger">*</span>`);
      form.find('.input-group-text.small').text(unitLabel.toLowerCase());
      tenureInputLocal.attr('placeholder', tenurePlaceholder);
      
      // Update Range Info Labels to match new unit
      form.find('#min_tenure_label').text(`${finalMin} ${unitLabel.charAt(0).toLowerCase()}`);
      form.find('#max_tenure_label').text(`${finalMax} ${unitLabel.charAt(0).toLowerCase()}`);
      
      syncTenureDisplay(form);

      // Update Preview Section
      form.find('.preview-label').first().text(previewLabel);
      
      updateEmiPreview(form);
    });

    function parseFormDate(value) {
      if (!value) return null;
      const raw = String(value).trim();
      const dmy = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
      let parsed = null;
      if (dmy) {
        parsed = new Date(parseInt(dmy[3], 10), parseInt(dmy[2], 10) - 1, parseInt(dmy[1], 10));
      } else {
        parsed = new Date(raw);
      }
      return isNaN(parsed.getTime()) ? null : parsed;
    }

    function daysInMonthForForm(form, dateOverride) {
      const date = dateOverride || parseFormDate(form.find('#emi_start_date').val()) || new Date();
      return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    }

    // Refactor EmiDay options to be reusable
    function updateEmiDayOptions(frequency, form) {
      if (!form || !form.length) return;
      const emiDaySelect = form.find('#emi_day');
      const emiDayWrapper = form.find('#emi_day_wrapper');
      const emiDayLabel = form.find('#emi_day_label');
      const previousValue = emiDaySelect.val();

      // If Select2 is already applied, temporarily destroy
      const emiDayHadSelect2 = emiDaySelect.hasClass('select2-hidden-accessible');
      if (emiDayHadSelect2) {
        try { emiDaySelect.select2('destroy'); } catch (e) {}
      }

      // Re-enable emiDaySelect to allow manual preference setting, but it will stay in sync with the calendar
      emiDaySelect.prop('disabled', false).css('pointer-events', 'auto');
      
      if (frequency === 'weekly') {
        emiDayWrapper.show();
        if (emiDayLabel.length) emiDayLabel.html('Collection Day <span class="text-danger">*</span>');
        emiDaySelect.empty();
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        emiDaySelect.append('<option value="" disabled selected>Select Day</option>');
        days.forEach((day, index) => {
          emiDaySelect.append(`<option value="${index + 1}">${day}</option>`);
        });
        if (previousValue && parseInt(previousValue, 10) >= 1 && parseInt(previousValue, 10) <= 7) {
          emiDaySelect.val(String(parseInt(previousValue, 10)));
        }
      } else if (frequency === 'daily') {
        emiDayWrapper.hide();
        emiDaySelect.prop('required', false);
        // Default to 1 but hidden
        emiDaySelect.empty();
        emiDaySelect.append('<option value="1" selected>Everyday</option>');
      } else {
        emiDayWrapper.show();
        if (emiDayLabel.length) emiDayLabel.html('EMI Date <span class="text-danger">*</span>');
        const maxDay = daysInMonthForForm(form);
        emiDaySelect.empty();
        emiDaySelect.append('<option value="" disabled selected>Select Date</option>');
        for (let i = 1; i <= maxDay; i++) {
          emiDaySelect.append(`<option value="${i}">${i}${getOrdinal(i)} of month</option>`);
        }
        if (previousValue && parseInt(previousValue, 10) >= 1 && parseInt(previousValue, 10) <= maxDay) {
          emiDaySelect.val(String(parseInt(previousValue, 10)));
        }
      }

      // Re-init Select2
      if (emiDayHadSelect2) {
        emiDaySelect.select2({
          dropdownParent: emiDaySelect.closest('.modal-content'),
          width: '100%',
          placeholder: frequency === 'weekly' ? 'Select Day' : 'Select Date',
          allowClear: true,
          closeOnSelect: true
        }).on('select2:select', function () {
          $(this).select2('close');
        });
      }

      // Sync the date picker with the new frequency/day
      syncEmiStartDatePicker(frequency, form);
    }

    function syncEmiStartDatePicker(frequency, form) {
      if (!form || !form.length) return;
      const emiDay = form.find('#emi_day').val();
      const modal = form.closest('.modal');
      const instances = modal.data('flatpickrInstances');
      if (!instances || !instances.emi_start_date) return;

      const picker = instances.emi_start_date;
      
      if (!frequency) frequency = form.find('#repayment_frequency').val();

      // Always keep all dates clickable
      picker.set('enable', [() => true]);

      if (frequency === 'weekly' && emiDay) {
        const targetWeekday = parseInt(emiDay) === 7 ? 0 : parseInt(emiDay); // 0 = Sun, 1 = Mon...
        const current = picker.selectedDates[0];
        if (current && current.getDay() === targetWeekday) return; // Already in sync!
        
        let targetDate = current ? new Date(current.getTime()) : new Date();
        const currentWeekday = targetDate.getDay();
        let diff = targetWeekday - currentWeekday;
        if (diff < 0) diff += 7;
        targetDate.setDate(targetDate.getDate() + diff);
        picker.setDate(targetDate, true);
      } else if (frequency === 'monthly' && emiDay) {
        const targetDay = parseInt(emiDay);
        const current = picker.selectedDates[0];
        if (current && current.getDate() === targetDay) return; // Already in sync!

        let targetDate = current ? new Date(current.getTime()) : new Date();
        const maxForMonth = new Date(targetDate.getFullYear(), targetDate.getMonth() + 1, 0).getDate();
        targetDate.setDate(Math.min(targetDay, maxForMonth));
        if (targetDate < new Date()) {
          targetDate.setMonth(targetDate.getMonth() + 1);
          const nextMax = new Date(targetDate.getFullYear(), targetDate.getMonth() + 1, 0).getDate();
          targetDate.setDate(Math.min(targetDay, nextMax));
        }
        picker.setDate(targetDate, true);
      }
    }

    function updateEmiDayFromDate(date, form) {
      if (!date || !form || !form.length) return;
      const frequency = form.find('#repayment_frequency').val();
      const emiDaySelect = form.find('#emi_day');
      const weekdayDisplay = form.find('#emi_weekday_display');

      if (frequency === 'monthly' || !frequency) {
        const maxDay = daysInMonthForForm(form, date);
        const currentVal = parseInt(emiDaySelect.val(), 10);
        const optionCount = emiDaySelect.find('option').filter(function () {
          return this.value && !this.disabled;
        }).length;
        if (optionCount !== maxDay) {
          const emiDayHadSelect2 = emiDaySelect.hasClass('select2-hidden-accessible');
          if (emiDayHadSelect2) {
            try { emiDaySelect.select2('destroy'); } catch (e) {}
          }
          emiDaySelect.empty();
          emiDaySelect.append('<option value="" disabled>Select Date</option>');
          for (let i = 1; i <= maxDay; i++) {
            emiDaySelect.append(`<option value="${i}">${i}${getOrdinal(i)} of month</option>`);
          }
          if (emiDayHadSelect2) {
            emiDaySelect.select2({
              dropdownParent: emiDaySelect.closest('.modal-content'),
              width: '100%',
              placeholder: 'Select Date',
              allowClear: true,
              closeOnSelect: true
            });
          }
          if (currentVal && currentVal <= maxDay) {
            emiDaySelect.val(String(currentVal));
          }
        }
      }
      
      // Update weekday display
      const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      const weekdayName = days[date.getDay()];
      if (weekdayDisplay.length) {
        weekdayDisplay.text(`Weekday: ${weekdayName}`);
      }

      if (frequency === 'weekly') {
        // JS getDay: 0 (Sun) to 6 (Sat)
        // Our emiDay: 1 (Mon) to 7 (Sun)
        let targetValue = date.getDay();
        if (targetValue === 0) targetValue = 7;
        
        if (emiDaySelect.val() != targetValue) {
          emiDaySelect.val(targetValue).trigger('change');
        }
      } else if (frequency === 'monthly') {
        const targetValue = date.getDate();
        if (emiDaySelect.val() != targetValue) {
          emiDaySelect.val(targetValue).trigger('change');
        }
      }
    }

    // Listener for EMI day change to sync picker
    $(document).off('change', '#emi_day').on('change', '#emi_day', function(e) {
      // Sync the date picker restriction when the day changes
      const form = $(this).closest('form');
      syncEmiStartDatePicker(null, form);
    });

    function getOrdinal(n) {
      let s = ["th", "st", "nd", "rd"],
          v = n % 100;
      return (s[(v - 20) % 10] || s[v] || s[0]);
    }



    // Payment Method Toggle
    function toggleGatewayField(paymentMethod) {
      const gatewayEl = $('#payment_gateway');
      if (!gatewayEl.length) return;

      if (paymentMethod === 'e-nach') {
        $('#gateway_wrapper').slideDown();
        gatewayEl.prop('required', true);

        if (gatewayEl.is('select')) {
          const hasGateway = gatewayEl.find('option').filter(function () {
            return this.value && !this.disabled;
          }).length > 0;

          if (!hasGateway) {
            gatewayEl.prop('disabled', true);
            Swal.fire({
              title: 'No Gateway Available',
              text: 'No active payment gateway is configured for E-NACH.',
              icon: 'warning',
              customClass: { confirmButton: 'btn btn-primary' }
            });
          } else {
            gatewayEl.prop('disabled', false);
          }
        } else {
          // Generic quick-loan modal uses text input for bank/gateway.
          gatewayEl.prop('disabled', false);
        }
      } else {
        $('#gateway_wrapper').slideUp();
        gatewayEl.prop('required', false).prop('disabled', false).val('');
      }
    }

    $('#payment_method').on('change', function () {
      toggleGatewayField($(this).val());
    });

    $(document).on('change', '.loan-mode-toggle', function () {
      const form = $(this).closest('form');
      const isInterestOnly = $(this).is(':checked');
      const tenureWrapper = form.find('#tenure_input').closest('.col-md-4');
      const emiDayLabel = form.find('label[for="emi_day"]');
      const emiStartDateLabel = form.find('label[for="emi_start_date"]');
      const emiHelpText = form.find('.alert-info');
      const modeDescription = form.find('#mode_description');
      const emiLabelText = form.find('#emi_label_text');
      const kandhuvattiLabelText = form.find('#kandhuvatti_label_text');
      
      const previewLabels = form.find('.preview-label');

      if (isInterestOnly) {
        // Kandhuvatti Mode UI
        tenureWrapper.fadeOut();
        form.find('#tenure_input, #tenure_slider').prop('disabled', true);
        modeDescription.text('Interest Only (Kandhuvatti) - Open Loan');
        emiLabelText.removeClass('text-primary').addClass('text-muted');
        kandhuvattiLabelText.removeClass('text-muted').addClass('text-danger');
        
        // Update Labels
        emiDayLabel.text('Interest Collection Day');
        emiStartDateLabel.text('Interest Start Date');
        emiHelpText.html('<i class="ri-information-line me-1"></i> First interest cycle begins on this date.');
        
        // Hide Total Payable in preview by setting text to "Open Loan"
        form.find('#preview_total').closest('.col-4').fadeOut();
        form.find('#preview_interest').closest('.col-4').removeClass('border-end');
      } else {
        // EMI Mode UI
        tenureWrapper.fadeIn();
        form.find('#tenure_input, #tenure_slider').prop('disabled', false);
        modeDescription.text('Standard EMI (Principal + Interest)');
        emiLabelText.removeClass('text-muted').addClass('text-primary');
        kandhuvattiLabelText.removeClass('text-danger').addClass('text-muted');
        
        // Restore Labels
        const frequency = form.find('#repayment_frequency').val();
        emiDayLabel.text(frequency === 'weekly' ? 'Weekly EMI Day' : (frequency === 'daily' ? 'Collection Type' : 'Monthly EMI Date'));
        emiStartDateLabel.text('EMI Start Date');
        emiHelpText.html('<i class="ri-information-line me-1"></i> The first EMI will land exactly on this date.');
        
        // Show Total Payable
        form.find('#preview_total').closest('.col-4').fadeIn();
        form.find('#preview_interest').closest('.col-4').addClass('border-end');
      }
      updateEmiPreview(form);
    });

    function updateEmiPreview(form = null) {
      if (!form) form = $('.modal.show form');
      if (!form.length) return;

      const amountInputLocal = form.find('input[name="loan_amount"]');
      const tenureInputLocal = form.find('input[name="tenure"]');
      const productSelectLocal = form.find('select[name="loan_code"]');
      const emiPreviewLocal = form.find('#preview_emi');
      const interestPreviewLocal = form.find('#preview_interest');
      const totalPreviewLocal = form.find('#preview_total');
      const previewLabels = form.find('.preview-label');

      const amount = parseFloat(amountInputLocal.val()) || 0;
      const tenure = parseInt(tenureInputLocal.val()) || 0;
      const selected = productSelectLocal.find(':selected');
      const rate = parseFloat(selected.data('rate')) || 0;
      const isInterestOnly = form.find('.loan-mode-toggle').is(':checked');
      const frequency = form.find('#repayment_frequency').val();

      if (isInterestOnly) {
        // Kandhuvatti Labels
        $(previewLabels[0]).text(frequency === 'weekly' ? 'WEEKLY INTEREST' : (frequency === 'daily' ? 'DAILY INTEREST' : 'MONTHLY INTEREST'));
        $(previewLabels[1]).text('INTEREST RATE');
        $(previewLabels[2]).text('LOAN STATUS');

        if (amount <= 0) {
          emiPreviewLocal.text('₹0.00');
          interestPreviewLocal.text(`${rate}%`);
          totalPreviewLocal.text('OPEN');
          return;
        }
        
        const interestCycle = amount * (rate / 100);
        emiPreviewLocal.text(`₹${interestCycle.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        interestPreviewLocal.text(`${rate}%`);
        totalPreviewLocal.text('OPEN LOAN');
      } else {
        // EMI Labels
        $(previewLabels[0]).text(frequency === 'weekly' ? 'WEEKLY EMI' : (frequency === 'daily' ? 'DAILY EMI' : 'MONTHLY EMI'));
        $(previewLabels[1]).text('TOTAL INTEREST');
        $(previewLabels[2]).text('TOTAL PAYABLE');

        const interestType = selected.data('interest-type') || 'flat';

        if (amount <= 0 || tenure <= 0) {
          emiPreviewLocal.text('₹0.00');
          interestPreviewLocal.text('₹0.00');
          totalPreviewLocal.text('₹0.00');
          return;
        }

        let emi = 0;
        let totalInterest = 0;
        let totalPayable = 0;

        if (interestType === 'reducing' || interestType === 'declining_balance') {
          let ratePerPeriod = 0;
          if (frequency === 'daily') {
            ratePerPeriod = (rate / 100) / 365;
          } else if (frequency === 'weekly') {
            ratePerPeriod = (rate / 100) / 52;
          } else {
            ratePerPeriod = (rate / 100) / 12;
          }

          if (ratePerPeriod > 0) {
            emi = amount * (ratePerPeriod * Math.pow(1 + ratePerPeriod, tenure)) / (Math.pow(1 + ratePerPeriod, tenure) - 1);
            emi = Math.round(emi);
          } else {
            emi = Math.round(amount / tenure);
          }

          let currentBalance = amount;
          for (let i = 1; i <= tenure; i++) {
            let currentInterest = Math.round(currentBalance * ratePerPeriod);
            let currentPrincipal = emi - currentInterest;
            if (i === tenure || currentPrincipal > currentBalance) {
              currentPrincipal = currentBalance;
              currentInterest = Math.round(currentBalance * ratePerPeriod);
            }
            currentBalance = Math.round(currentBalance - currentPrincipal);
            totalInterest += currentInterest;
          }
          totalPayable = amount + totalInterest;
        } else {
          totalInterest = Math.round(amount * (rate / 100));
          totalPayable = amount + totalInterest;
          emi = Math.round(totalPayable / tenure);
        }

        emiPreviewLocal.text(`₹${emi.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        interestPreviewLocal.text(`₹${totalInterest.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        totalPreviewLocal.text(`₹${totalPayable.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
      }
    }

    // Remove duplicate backdrops if modal was opened more than once (Bootstrap instance issue).
    $(document).on('hidden.bs.modal', '#modalApplyLoan, #modalApplyLoanGeneric', function () {
      const currentModal = $(this);
      const form = currentModal.find('form');
      const productSelect = form.find('#loan_product');
      const allOptions = productSelect.data('all-product-options');
      if (productSelect.length && allOptions && allOptions.length) {
        productSelect.empty().append(allOptions.clone()).val('');
      }
      form.find('.loan-type-filter, #loan_type_filter').val('');

      const backdrops = document.querySelectorAll('.modal-backdrop');
      if (backdrops.length > 1) {
        backdrops.forEach((el, index) => {
          if (index > 0) {
            el.remove();
          }
        });
      }
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('padding-right');
    });

    // Recalculate whenever modal is opened/reset to avoid stale ₹0.00 previews.
    $(document).on('shown.bs.modal', '#modalApplyLoan, #modalApplyLoanGeneric', function () {
      const currentModal = $(this);
      
      // Use a small timeout to ensure DOM and Select2 are fully ready
      setTimeout(() => {
        const productSelect = currentModal.find('#loan_product');
        if (productSelect.length && !productSelect.data('all-product-options')) {
          productSelect.data('all-product-options', productSelect.find('option').clone());
        }

        initModalSelect2(currentModal);

        // Explicitly trigger frequency change to populate day/date options
        const freqSelect = currentModal.find('#repayment_frequency');
        if (freqSelect.val()) {
          freqSelect.trigger('change');
          
          // Force sync from current date after frequency options are populated
          const instances = currentModal.data('flatpickrInstances');
          if (instances && instances.emi_start_date && instances.emi_start_date.selectedDates.length > 0) {
            updateEmiDayFromDate(instances.emi_start_date.selectedDates[0], currentModal.find('form'));
          }
        }

        const selectedProduct = currentModal.find('#loan_product').find(':selected');
        if (selectedProduct.val()) {
          currentModal.find('#loan_product').trigger('change');
        }
        updateEmiPreview(currentModal.find('form'));
        toggleGatewayField(currentModal.find('#payment_method').val());

        // Auto-load chit details & eligibility for specific client modal (pre-filled client_id)
        const hiddenClientId = currentModal.find('#apply_client_id_hidden').val();
        if (hiddenClientId) {
          const form = currentModal.find('form');
          const submitBtn = form.find('button[type="submit"]');
          const originalSubmitHtml = submitBtn.data('orig-html') || submitBtn.html();
          submitBtn.data('orig-html', originalSubmitHtml);
          form.find('#eligibilityAlert').remove();

          $.ajax({
            url: `${baseUrl}loan-application/check-eligibility`,
            type: 'POST',
            data: {
              client_id: hiddenClientId,
              _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (res) {
              if (!res.eligible) {
                const alertHtml = `<div id="eligibilityAlert" class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert">
                  <i class="ri-error-warning-line ri-20px"></i>
                  <span>${res.message}</span>
                </div>`;
                form.find('#chitDetailsPanel').before(alertHtml);

                submitBtn.prop('disabled', true)
                  .html('<i class="ri-lock-line me-1"></i> Cannot Apply')
                  .attr('title', res.message);
              } else {
                submitBtn.prop('disabled', false).html(originalSubmitHtml).removeAttr('title');
              }
              if (res.chit_details && res.chit_details.has_chit) {
                renderChitDetails(res.chit_details);
              }
            }
          });
        }
      }, 50);
    });

    // Ensure forms have novalidate to bypass any unwanted native HTML5 constraint blocking
    $('#formApplyLoan, #formApplyLoanGeneric').attr('novalidate', 'novalidate');
    $(document).on('click', '#formApplyLoan button[type="submit"], #formApplyLoanGeneric button[type="submit"]', function () {
      $(this).closest('form').attr('novalidate', 'novalidate');
      $(this).attr('formnovalidate', 'formnovalidate');
    });

    // Delegated submit handler for Quick Apply Loan modals
    $(document).on('submit', '#formApplyLoan, #formApplyLoanGeneric', function (e) {
      e.preventDefault();
      const currentForm = this;
      const $form = $(currentForm);
      const btn = $form.find('button[type="submit"]');
      const origBtnHtml = btn.html();

      // Reset previous validation state
      $form.find('.is-invalid').removeClass('is-invalid');
      $form.find('.select2-selection').removeClass('border-danger');

      const isInterestOnly = $form.find('.loan-mode-toggle').is(':checked');

      // 1. Validate Client
      const clientId = $form.find('[name="client_id"]').val();
      if (!clientId) {
        const clientSelect = $form.find('#apply_client_id');
        if (clientSelect.length) {
          clientSelect.next('.select2-container').find('.select2-selection').addClass('border-danger');
        }
        Swal.fire({
          title: 'Validation Error',
          text: 'Please select a verified client.',
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      // 2. Validate Loan Product
      const productSelect = $form.find('#loan_product');
      const loanCode = productSelect.val();
      if (!loanCode) {
        productSelect.next('.select2-container').find('.select2-selection').addClass('border-danger');
        Swal.fire({
          title: 'Validation Error',
          text: 'Please select a loan product.',
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      // 3. Validate Loan Amount
      const amtInput = $form.find('#loan_amount_input');
      const loanAmount = parseFloat(amtInput.val()) || 0;
      const minAmt = parseFloat(amtInput.attr('min')) || 0;
      const maxAmt = parseFloat(amtInput.attr('max')) || 0;

      if (!loanAmount || loanAmount <= 0) {
        amtInput.addClass('is-invalid').focus();
        Swal.fire({
          title: 'Validation Error',
          text: 'Please enter a valid loan amount.',
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      if (minAmt > 0 && loanAmount < minAmt) {
        amtInput.addClass('is-invalid').focus();
        Swal.fire({
          title: 'Validation Error',
          text: `Loan amount cannot be less than ₹${minAmt.toLocaleString('en-IN')}.`,
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      if (maxAmt > 0 && loanAmount > maxAmt) {
        amtInput.addClass('is-invalid').focus();
        Swal.fire({
          title: 'Validation Error',
          text: `Loan amount cannot exceed ₹${maxAmt.toLocaleString('en-IN')}.`,
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      // 4. Validate Repayment Frequency
      const freqSelect = $form.find('#repayment_frequency');
      const frequency = freqSelect.val() || 'monthly';
      if (!frequency) {
        freqSelect.next('.select2-container').find('.select2-selection').addClass('border-danger');
        Swal.fire({
          title: 'Validation Error',
          text: 'Please select a repayment frequency.',
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      // 5. Validate Tenure (only required if standard EMI mode)
      const tenureInput = $form.find('#tenure_input');
      const tenureSlider = $form.find('#tenure_slider');

      if (isInterestOnly) {
        tenureInput.prop('disabled', true).removeAttr('required');
        tenureSlider.prop('disabled', true);
      } else {
        tenureInput.prop('disabled', false);
        tenureSlider.prop('disabled', false);

        let tenVal = parseInt(tenureInput.val(), 10);
        let tenMin = parseInt(tenureInput.attr('min'), 10) || 1;
        let tenMax = parseInt(tenureInput.attr('max'), 10) || 1000;

        if (tenMin > tenMax) {
          const temp = tenMin;
          tenMin = tenMax;
          tenMax = temp;
        }

        if (isNaN(tenVal) || tenVal < tenMin || tenVal > tenMax) {
          tenureInput.addClass('is-invalid').focus();
          Swal.fire({
            title: 'Validation Error',
            text: `Tenure must be between ${tenMin} and ${tenMax}.`,
            icon: 'warning',
            customClass: { confirmButton: 'btn btn-primary' }
          });
          return;
        }
      }

      // 6. Validate EMI Day / Date
      const emiDaySelect = $form.find('#emi_day');
      let emiDayVal = emiDaySelect.val();

      if (frequency === 'daily') {
        emiDayVal = '1';
      } else if (!emiDayVal) {
        emiDaySelect.next('.select2-container').find('.select2-selection').addClass('border-danger');
        Swal.fire({
          title: 'Validation Error',
          text: frequency === 'weekly' ? 'Please select an EMI collection day.' : 'Please select a monthly EMI date.',
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      // 7. Validate EMI Start Date
      const startDateInput = $form.find('#emi_start_date');
      const emiStartDate = startDateInput.val();
      if (!emiStartDate) {
        startDateInput.addClass('is-invalid').focus();
        Swal.fire({
          title: 'Validation Error',
          text: 'Please select an EMI start date.',
          icon: 'warning',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      // Build payload data cleanly
      let formArray = $form.serializeArray();

      // Ensure loan_mode is always explicitly present
      formArray = formArray.filter(item => item.name !== 'loan_mode');
      formArray.push({ name: 'loan_mode', value: isInterestOnly ? 'interest_only' : 'emi' });

      // Ensure emi_day is present
      formArray = formArray.filter(item => item.name !== 'emi_day');
      formArray.push({ name: 'emi_day', value: emiDayVal });

      btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

      $.ajax({
        url: `${baseUrl}loan-application/quick-apply`,
        type: 'POST',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: $.param(formArray),
        success: function (res) {
          if (res.success) {
            Swal.fire({
              title: 'Success!',
              text: res.message || 'Loan application submitted successfully!',
              icon: 'success',
              customClass: { confirmButton: 'btn btn-primary' }
            }).then(() => {
              if (res.redirect_path) {
                const cleanPath = String(res.redirect_path).replace(/^\/+/, '');
                window.location.href = `${baseUrl}${cleanPath}`;
              } else if (res.redirect) {
                try {
                  const targetUrl = new URL(res.redirect, window.location.origin);
                  if (targetUrl.origin !== window.location.origin) {
                    const cleanPath = targetUrl.pathname.replace(/^\/+/, '');
                    window.location.href = `${baseUrl}${cleanPath}${targetUrl.search}${targetUrl.hash}`;
                  } else {
                    window.location.href = res.redirect;
                  }
                } catch (e) {
                  window.location.href = res.redirect;
                }
              } else {
                window.location.reload();
              }
            });
          } else {
            Swal.fire({
              title: 'Error!',
              text: res.message || 'Something went wrong',
              icon: 'error',
              customClass: { confirmButton: 'btn btn-primary' }
            });
            btn.prop('disabled', false).html(origBtnHtml);
          }
        },
        error: function (xhr) {
          btn.prop('disabled', false).html(origBtnHtml);

          let errMsg = 'Something went wrong while submitting the application.';
          if (xhr.responseJSON?.message) {
            errMsg = xhr.responseJSON.message;
          }
          if (xhr.responseJSON?.errors) {
            const errList = Object.values(xhr.responseJSON.errors).flat();
            if (errList.length) {
              errMsg = errList.join('<br>');
            }
          }

          Swal.fire({
            icon: 'error',
            title: 'Application Error',
            html: errMsg,
            customClass: { confirmButton: 'btn btn-primary' }
          });
        }
      });
    });

    // Delete Record
    $(document).on('click', '.delete-loan-application', function () {
      const applicationId = $(this).data('id');
      
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        customClass: {
          confirmButton: 'btn btn-danger me-3 waves-effect waves-light',
          cancelButton: 'btn btn-outline-secondary waves-effect'
        },
        buttonsStyling: false
      }).then(function (result) {
        if (result.value) {
          $.ajax({
            url: `${baseUrl}loan/loan-applications/${applicationId}`,
            type: 'POST',
            data: {
              _method: 'DELETE',
              _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
              if (response.success) {
                // Reload datatable
                $('.datatables-loan-applications').DataTable().ajax.reload(null, false);
                
                Swal.fire({
                  icon: 'success',
                  title: 'Deleted!',
                  text: 'Loan application has been deleted.',
                  customClass: {
                    confirmButton: 'btn btn-success waves-effect'
                  }
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Error!',
                  text: response.message || 'Failed to delete application',
                  customClass: {
                    confirmButton: 'btn btn-danger waves-effect'
                  }
                });
              }
            },
            error: function (xhr) {
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: xhr.responseJSON?.message || 'Something went wrong',
                customClass: {
                  confirmButton: 'btn btn-danger waves-effect'
                }
              });
            }
          });
        }
      });
    });
  }
});
