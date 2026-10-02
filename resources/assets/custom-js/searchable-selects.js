/**
 * Global searchable dropdowns (Select2) for all form selects.
 */
'use strict';

(function ($) {
  if (!$ || typeof $.fn.select2 !== 'function') {
    return;
  }

  const SKIP =
    '.no-search, .select2-ajax, .select2-modal, .select2-hidden-accessible, .date-range-preset, .dashboard-period-filter';

  function isPageLengthSelect($el) {
    const id = String($el.attr('id') || '');
    const name = String($el.attr('name') || '');

    if ($el.hasClass('no-search') || $el.hasClass('dt-length-select')) {
      return true;
    }

    if ($el.closest('.dt-length, .dataTables_length').length) {
      return true;
    }

    if (/(_length|perPageSelect)/i.test(id) || /(_length|per_page)/i.test(name)) {
      return true;
    }

    return false;
  }

  function dropdownParent($el) {
    const $modal = $el.closest('.modal');
    if ($modal.length) {
      return $modal;
    }
    const $offcanvas = $el.closest('.offcanvas');
    if ($offcanvas.length) {
      return $offcanvas;
    }
    return $el.parent();
  }

  function defaultPlaceholder($el) {
    const dataPlaceholder = $el.data('placeholder');
    if (dataPlaceholder) {
      return dataPlaceholder;
    }
    const emptyOption = $el.find('option[value=""]').first();
    if (emptyOption.length) {
      const text = emptyOption.text().trim();
      if (text) {
        return text;
      }
    }
    return 'Search and select…';
  }

  function initSearchableSelects(context) {
    const $scope = context ? $(context) : $(document);

    $scope.find('select.form-select').not(SKIP).each(function () {
      const $el = $(this);

      // DataTables / page-length menus must stay native — Select2 blocks change + search inside the dropdown.
      if (isPageLengthSelect($el)) {
        if ($el.data('select2')) {
          $el.select2('destroy');
        }
        return;
      }

      if ($el.data('select2')) {
        return;
      }

      // Shared date-range / dashboard period presets must stay native (auto-submit + no search UI).
      if ($el.closest('[data-date-range-filter]').length) {
        return;
      }
      if ($el.closest('#dashboardPeriodForm, #chitDashboardPeriodForm, #fdDashboardPeriodForm, #standaloneChitPeriodForm').length) {
        return;
      }

      if (!$el.parent().hasClass('position-relative')) {
        $el.wrap('<div class="position-relative"></div>');
      }

      const placeholder = defaultPlaceholder($el);
      const hasEmpty = $el.find('option[value=""]').length > 0;

      $el.select2({
        placeholder,
        allowClear: hasEmpty && !$el.prop('required'),
        width: '100%',
        dropdownParent: dropdownParent($el),
        minimumResultsForSearch: 0,
        language: {
          noResults: () => 'No results found',
          searching: () => 'Searching…',
          inputTooShort: () => 'Type to search…',
        },
      });

      if (typeof window.select2Focus === 'function') {
        window.select2Focus($el);
      }
    });
  }

  $(function () {
    initSearchableSelects();
  });

  $(document).on('shown.bs.modal shown.bs.offcanvas', function (e) {
    initSearchableSelects(e.target);
  });

  window.initSearchableSelects = initSearchableSelects;
})(window.jQuery);
