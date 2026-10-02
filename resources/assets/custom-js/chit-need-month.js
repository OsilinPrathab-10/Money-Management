'use strict';

document.addEventListener('DOMContentLoaded', function () {
  function updateNeedMonthPreview(block) {
    const select = block.querySelector('.chit-need-month-select');
    const preview = block.querySelector('.chit-need-periods-preview');
    const textEl = block.querySelector('.chit-need-periods-text');
    if (!select || !preview || !textEl) return;

    const month = parseInt(select.value, 10);
    const groupStart = block.getAttribute('data-group-start');
    const totalMonths = parseInt(block.getAttribute('data-total-months') || '0', 10);

    if (!month || !groupStart || !totalMonths) {
      preview.classList.add('d-none');
      return;
    }

    const start = new Date(groupStart + 'T00:00:00');
    const periods = [];
    for (let m = 1; m <= totalMonths; m++) {
      const d = new Date(start.getFullYear(), start.getMonth() + (m - 1), 1);
      if (d.getMonth() + 1 === month) {
        periods.push(m);
      }
    }

    if (!periods.length) {
      textEl.textContent = 'No matching periods for this group start date.';
      preview.classList.remove('d-none');
      return;
    }

    const monthName = select.options[select.selectedIndex]?.text || '';
    textEl.textContent = monthName + ' → Chit periods: ' + periods.join(', ');
    preview.classList.remove('d-none');
  }

  function bindBlock(block) {
    if (block._chitNeedBound) return;
    block._chitNeedBound = true;
    const select = block.querySelector('.chit-need-month-select');
    select?.addEventListener('change', function () {
      updateNeedMonthPreview(block);
    });
    updateNeedMonthPreview(block);
  }

  function refreshFromGroupSelect(form) {
    const groupSelect = form?.querySelector('select[name="group_id"]');
    const block = form?.querySelector('.chit-need-month-block');
    if (!groupSelect || !block) return;

    const opt = groupSelect.options[groupSelect.selectedIndex];
    const start = opt?.getAttribute('data-start-date') || '';
    const total = opt?.getAttribute('data-total-months') || '';
    block.setAttribute('data-group-start', start);
    block.setAttribute('data-total-months', total);
    updateNeedMonthPreview(block);
  }

  document.querySelectorAll('.chit-need-month-block').forEach(bindBlock);

  document.addEventListener('change', function (e) {
    if (e.target.matches('select[name="group_id"], #chit_group_id')) {
      refreshFromGroupSelect(e.target.closest('form'));
    }
  });

  document.addEventListener('shown.bs.modal', function () {
    document.querySelectorAll('.chit-need-month-block').forEach(bindBlock);
  });
});
