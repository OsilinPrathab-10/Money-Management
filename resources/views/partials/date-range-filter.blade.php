{{--
  Shared period filter — same dropdown UI as the dashboard.
  Today / Yesterday / This Month / This Year / Custom / All Time
--}}
@php
    $fromId = $fromId ?? 'fromDate';
    $toId = $toId ?? 'toDate';
    $presetId = $presetId ?? 'datePreset';
    $fromName = $fromName ?? 'from_date';
    $toName = $toName ?? 'to_date';
    $presetName = $presetName ?? 'date_preset';
    $fromValue = $fromValue ?? '';
    $toValue = $toValue ?? '';
    $presetValue = $presetValue ?? request($presetName, '');
    $presetValue = match ((string) $presetValue) {
        'week', 'this_week' => 'this_month',
        'month' => 'this_month',
        'year' => 'this_year',
        'all_time', '' => 'all',
        default => $presetValue,
    };
    $isCustom = $presetValue === 'custom';
    if ($presetValue === 'all') {
        $fromValue = '';
        $toValue = '';
    } elseif (! $isCustom && $presetValue !== '') {
        $today = now()->startOfDay();
        [$fromValue, $toValue] = match ($presetValue) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
            'this_month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            'this_year' => [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString()],
            default => [$fromValue, $toValue],
        };
    }
    $size = $size ?? 'sm';
    $showAll = $showAll ?? true;
    $autoSubmit = !empty($autoSubmit);
    $dataAutoSubmit = !empty($dataAutoSubmit);
    $wrapperClass = $wrapperClass ?? '';
    $selectClass = 'form-select date-range-preset no-search dashboard-period-filter' . ($size === 'sm' ? ' form-select-sm' : '');
    $inputClass = 'form-control date-range-from' . ($size === 'sm' ? ' form-control-sm' : '');
    $toInputClass = 'form-control date-range-to' . ($size === 'sm' ? ' form-control-sm' : '');
    $presets = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_month' => 'This Month',
        'this_year' => 'This Year',
        'custom' => 'Custom',
    ];
    if ($showAll) {
        $presets['all'] = 'All Time';
    }
@endphp

@once
<style>
  .date-range-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }
  .date-range-filter .date-range-preset {
    min-width: 160px;
    width: auto;
  }
  .date-range-custom input[type="date"] {
    min-width: 145px;
  }
</style>
@endonce
<div class="date-range-filter {{ $wrapperClass }}"
     data-date-range-filter
     data-from-id="{{ $fromId }}"
     data-to-id="{{ $toId }}"
     data-preset-id="{{ $presetId }}"
     @if($autoSubmit) data-auto-submit="1" @endif>
  <label for="{{ $presetId }}" class="form-label mb-0 text-nowrap small fw-medium">Period</label>
  <select id="{{ $presetId }}"
          name="{{ $presetName }}"
          class="{{ $selectClass }}"
          autocomplete="off"
          @if($dataAutoSubmit) data-auto-submit="true" @endif>
    @foreach($presets as $value => $label)
      <option value="{{ $value }}" @selected($presetValue === $value || ($presetValue === '' && $value === 'all'))>{{ $label }}</option>
    @endforeach
  </select>

  <div class="date-range-custom align-items-center gap-2 {{ $isCustom ? 'd-flex' : 'd-none' }}"
       data-custom-range
       @if(! $isCustom) style="display:none;" @endif>
    <input type="date"
           id="{{ $fromId }}"
           name="{{ $fromName }}"
           class="{{ $inputClass }}"
           value="{{ $fromValue }}"
           @if($dataAutoSubmit) data-auto-submit="true" @endif>
    <span class="small text-muted">to</span>
    <input type="date"
           id="{{ $toId }}"
           name="{{ $toName }}"
           class="{{ $toInputClass }}"
           value="{{ $toValue }}"
           @if($dataAutoSubmit) data-auto-submit="true" @endif>
  </div>
</div>
@once
<script>
(function (window, document) {
  function pad(n) { return String(n).padStart(2, '0'); }
  function toYmd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
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
    if (preset === 'today') { var t = toYmd(today); return { from: t, to: t }; }
    if (preset === 'yesterday') { var y = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1); var yd = toYmd(y); return { from: yd, to: yd }; }
    if (preset === 'this_month') return { from: toYmd(new Date(today.getFullYear(), today.getMonth(), 1)), to: toYmd(new Date(today.getFullYear(), today.getMonth() + 1, 0)) };
    if (preset === 'this_year') return { from: toYmd(new Date(today.getFullYear(), 0, 1)), to: toYmd(new Date(today.getFullYear(), 11, 31)) };
    return { from: '', to: '' };
  }
  function ensureNativeSelect(select) {
    if (!select) return;
    select.classList.add('no-search', 'dashboard-period-filter', 'date-range-preset');
    if (window.jQuery && window.jQuery(select).data('select2')) window.jQuery(select).select2('destroy');
  }
  function setCustomVisible(root, visible) {
    var custom = root.querySelector('[data-custom-range]');
    if (!custom) return;
    custom.classList.toggle('d-flex', visible);
    custom.classList.toggle('d-none', !visible);
    custom.style.display = visible ? 'flex' : 'none';
    custom.querySelectorAll('input[type="date"]').forEach(function (input) { input.disabled = false; });
  }
  function notify(root, fromEl, toEl, presetEl) {
    try {
      root.dispatchEvent(new CustomEvent('date-range:change', { bubbles: true, detail: { preset: presetEl ? presetEl.value : 'all', from: fromEl ? fromEl.value : '', to: toEl ? toEl.value : '' } }));
    } catch (e) {
      var ev = document.createEvent('Event');
      ev.initEvent('date-range:change', true, true);
      root.dispatchEvent(ev);
    }
    if (presetEl) presetEl.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function submitRoot(root) {
    if (root.getAttribute('data-auto-submit') !== '1') return;
    var form = root.closest('form');
    if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
  }
  function applyPreset(root, preset) {
    preset = normalizePreset(preset);
    var presetEl = root.querySelector('.date-range-preset');
    var fromEl = root.querySelector('.date-range-from');
    var toEl = root.querySelector('.date-range-to');
    root.dataset.applyingPreset = '1';
    try {
      if (presetEl) presetEl.value = preset;
      if (preset === 'custom') { setCustomVisible(root, true); return; }
      var range = rangeForPreset(preset);
      if (fromEl) fromEl.value = range.from;
      if (toEl) toEl.value = range.to;
      setCustomVisible(root, false);
      if (fromEl) fromEl.dispatchEvent(new Event('change', { bubbles: true }));
      if (toEl) toEl.dispatchEvent(new Event('change', { bubbles: true }));
    } finally { delete root.dataset.applyingPreset; }
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
    presetEl.addEventListener('change', function () {
      if (root.dataset.applyingPreset === '1') return;
      var preset = normalizePreset(presetEl.value || 'all');
      applyPreset(root, preset);
      notify(root, fromEl, toEl, presetEl);
      if (preset !== 'custom') submitRoot(root);
      else if (fromEl) fromEl.focus();
    });
    function onCustomDateChange() {
      if (root.dataset.applyingPreset === '1') return;
      if (normalizePreset(presetEl.value || '') !== 'custom') applyPreset(root, 'custom');
      notify(root, fromEl, toEl, presetEl);
      submitRoot(root);
    }
    if (fromEl) fromEl.addEventListener('change', onCustomDateChange);
    if (toEl) toEl.addEventListener('change', onCustomDateChange);
  }
  function initAll() { document.querySelectorAll('[data-date-range-filter]').forEach(initRoot); }
  window.DateRangeFilter = { init: initAll, applyPreset: applyPreset, rangeForPreset: rangeForPreset };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
  window.addEventListener('load', function () {
    document.querySelectorAll('[data-date-range-filter] .date-range-preset').forEach(ensureNativeSelect);
    initAll();
  });
})(window, document);
</script>
@endonce
