/**
 * Shared period filter — same dropdown UI as the dashboard period filter.
 * From/To stay enabled (hidden unless Custom) so every page that reads those fields still works.
 */
(function (window, document) {
  'use strict';

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function toYmd(date) {
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  function normalizePreset(preset) {
    if (preset === 'week' || preset === 'this_week' || preset === 'month') return 'this_month';
    if (preset === 'year') return 'this_year';
    if (preset === 'all_time' || !preset) return 'all';
    return preset;
  }

  function rangeForPreset(preset) {
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    preset = normalizePreset(preset);

    if (preset === 'today') {
      var t = toYmd(today);
      return { from: t, to: t };
    }
    if (preset === 'yesterday') {
      var y = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
      var yd = toYmd(y);
      return { from: yd, to: yd };
    }
    if (preset === 'this_month') {
      return {
        from: toYmd(new Date(today.getFullYear(), today.getMonth(), 1)),
        to: toYmd(new Date(today.getFullYear(), today.getMonth() + 1, 0))
      };
    }
    if (preset === 'this_year') {
      return {
        from: toYmd(new Date(today.getFullYear(), 0, 1)),
        to: toYmd(new Date(today.getFullYear(), 11, 31))
      };
    }
    return { from: '', to: '' };
  }

  function ensureNativeSelect(select) {
    if (!select) return;
    select.classList.add('no-search', 'dashboard-period-filter', 'date-range-preset');
    if (window.jQuery) {
      var $el = window.jQuery(select);
      if ($el.data('select2')) {
        $el.select2('destroy');
      }
    }
  }

  function setCustomVisible(root, visible) {
    var custom = root.querySelector('[data-custom-range]');
    if (!custom) return;
    custom.classList.toggle('d-flex', visible);
    custom.classList.toggle('d-none', !visible);
    custom.style.display = visible ? 'flex' : 'none';
    custom.querySelectorAll('input[type="date"]').forEach(function (input) {
      input.disabled = false;
    });
  }

  function notifyChange(root, fromEl, toEl, presetEl) {
    try {
      root.dispatchEvent(new CustomEvent('date-range:change', {
        bubbles: true,
        detail: {
          preset: presetEl ? normalizePreset(presetEl.value) : 'all',
          from: fromEl ? fromEl.value : '',
          to: toEl ? toEl.value : ''
        }
      }));
    } catch (e) {
      var ev = document.createEvent('Event');
      ev.initEvent('date-range:change', true, true);
      root.dispatchEvent(ev);
    }
    if (presetEl) {
      presetEl.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  function maybeAutoSubmit(root) {
    if (root.getAttribute('data-auto-submit') !== '1') return;
    var form = root.closest('form');
    if (!form) return;
    if (form.requestSubmit) form.requestSubmit();
    else form.submit();
  }

  function applyPreset(root, preset) {
    preset = normalizePreset(preset);
    var presetEl = root.querySelector('.date-range-preset');
    var fromEl = root.querySelector('.date-range-from');
    var toEl = root.querySelector('.date-range-to');

    root.dataset.applyingPreset = '1';
    try {
      if (presetEl) presetEl.value = preset;
      if (preset === 'custom') {
        setCustomVisible(root, true);
        return;
      }
      var range = rangeForPreset(preset);
      if (fromEl) fromEl.value = range.from;
      if (toEl) toEl.value = range.to;
      setCustomVisible(root, false);
      if (fromEl) fromEl.dispatchEvent(new Event('change', { bubbles: true }));
      if (toEl) toEl.dispatchEvent(new Event('change', { bubbles: true }));
    } finally {
      delete root.dataset.applyingPreset;
    }
  }

  function initRoot(root) {
    if (!root || root.dataset.periodFilterReady === '1') return;
    root.dataset.periodFilterReady = '1';

    var presetEl = root.querySelector('.date-range-preset');
    var fromEl = root.querySelector('.date-range-from');
    var toEl = root.querySelector('.date-range-to');
    if (!presetEl) return;

    ensureNativeSelect(presetEl);
    applyPreset(root, presetEl.value || 'all');

    function onPeriodChange() {
      if (root.dataset.applyingPreset === '1') return;
      var preset = normalizePreset(presetEl.value || 'all');
      applyPreset(root, preset);
      notifyChange(root, fromEl, toEl, presetEl);
      if (preset !== 'custom') {
        maybeAutoSubmit(root);
      } else if (fromEl) {
        fromEl.focus();
      }
    }

    presetEl.addEventListener('change', onPeriodChange);
    if (window.jQuery) {
      window.jQuery(presetEl).off('change.dateRangeFilter').on('change.dateRangeFilter', onPeriodChange);
    }

    function onCustomDateChange() {
      if (root.dataset.applyingPreset === '1') return;
      if (normalizePreset(presetEl.value || '') !== 'custom') {
        applyPreset(root, 'custom');
      }
      notifyChange(root, fromEl, toEl, presetEl);
      maybeAutoSubmit(root);
    }

    if (fromEl) fromEl.addEventListener('change', onCustomDateChange);
    if (toEl) toEl.addEventListener('change', onCustomDateChange);
  }

  function initAll(scope) {
    (scope || document).querySelectorAll('[data-date-range-filter]').forEach(initRoot);
  }

  window.DateRangeFilter = {
    init: initAll,
    applyPreset: applyPreset,
    rangeForPreset: rangeForPreset
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(); });
  } else {
    initAll();
  }

  window.addEventListener('load', function () {
    document.querySelectorAll('[data-date-range-filter] .date-range-preset').forEach(ensureNativeSelect);
    initAll();
  });
})(window, document);
