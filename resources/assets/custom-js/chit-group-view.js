'use strict';

/**
 * Group show page — AJAX reload for cards, progress, members, auctions.
 * Collect/pay modals stay outside #group-show-ajax-root.
 */
(function () {
  function getRoot() {
    return document.getElementById('group-show-ajax-root');
  }

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function showToast(icon, title, text) {
    if (typeof Swal === 'undefined') {
      if (icon === 'error') alert(text || title);
      return Promise.resolve();
    }
    return Swal.fire({
      icon: icon || 'success',
      title: title || (icon === 'error' ? 'Error' : 'Success'),
      text: text || '',
      timer: icon === 'error' ? undefined : 2000,
      showConfirmButton: icon === 'error',
      confirmButtonText: 'OK',
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    });
  }

  async function reloadChitGroupView(url) {
    const root = getRoot();
    if (!root) {
      window.location.reload();
      return;
    }

    const targetUrl = url || root.dataset.reloadUrl || window.location.href;
    root.classList.add('opacity-50', 'pe-none');

    try {
      const response = await fetch(targetUrl, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'text/html'
        },
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error('Failed to refresh group view');
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const next = doc.getElementById('group-show-ajax-root');

      if (!next) {
        window.location.href = targetUrl;
        return;
      }

      root.innerHTML = next.innerHTML;
      root.dataset.reloadUrl = targetUrl.split('#')[0];

      if (url && window.history && window.history.replaceState) {
        window.history.replaceState({}, '', targetUrl);
      }

      document.dispatchEvent(new CustomEvent('chit-group-view:reloaded', { detail: { url: targetUrl } }));
    } catch (err) {
      console.error(err);
      window.location.reload();
    } finally {
      root.classList.remove('opacity-50', 'pe-none');
    }
  }

  window.reloadChitGroupView = reloadChitGroupView;

  async function submitGroupAjaxForm(form) {
    const confirmMsg = form.getAttribute('data-confirm');
    if (confirmMsg) {
      if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
          title: 'Are you sure?',
          text: confirmMsg,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, continue',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-label-secondary'
          },
          buttonsStyling: false
        });
        if (!result.isConfirmed) return;
      } else if (!window.confirm(confirmMsg)) {
        return;
      }
    }

    const methodInput = form.querySelector('input[name="_method"]');
    const method = (methodInput?.value || form.method || 'POST').toUpperCase();
    const formData = new FormData(form);

    const response = await fetch(form.action, {
      method: method === 'GET' ? 'GET' : 'POST',
      body: method === 'GET' ? undefined : formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken()
      },
      credentials: 'same-origin'
    });

    const contentType = response.headers.get('content-type') || '';
    let data = {};
    if (contentType.includes('application/json')) {
      data = await response.json().catch(() => ({}));
    }

    if (!response.ok || data.success === false) {
      const message =
        data.message ||
        (data.errors ? Object.values(data.errors).flat().join(' ') : null) ||
        'Action failed.';
      throw new Error(message);
    }

    await reloadChitGroupView();
    await showToast(
      'success',
      form.getAttribute('data-success-title') || 'Done',
      data.message || 'Updated successfully.'
    );
  }

  document.addEventListener('change', function (e) {
    const select = e.target.closest('.group-progress-month-select');
    if (!select) return;
    const form = select.closest('.group-progress-month-form');
    if (!form) return;

    if (getRoot()) {
      const params = new URLSearchParams(new FormData(form));
      const url = form.action + (params.toString() ? '?' + params.toString() : '');
      reloadChitGroupView(url);
    } else {
      form.submit();
    }
  });

  document.addEventListener('submit', function (e) {
    const form = e.target.closest('.group-ajax-form');
    if (!form || !getRoot()) return;

    e.preventDefault();
    submitGroupAjaxForm(form).catch(function (err) {
      showToast('error', 'Error', err.message || 'Something went wrong.');
    });
  });

  // Activate / close / cancel-termination — AJAX after Swal confirm
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#btn-activate-group, #btn-close-group, #btn-cancel-termination');
    if (!btn || !getRoot()) return;

    e.preventDefault();

    let formId = null;
    let title = 'Confirm?';
    let html = '';
    let confirmColor = '#7367f0';
    let confirmText = 'Yes';

    if (btn.id === 'btn-activate-group') {
      formId = 'activate-form';
      title = 'Activate Group?';
      html = 'This will start the group and generate installment schedules for all members.';
      confirmColor = '#03C95A';
      confirmText = 'Yes, Activate it!';
    } else if (btn.id === 'btn-close-group') {
      if (btn.disabled) return;
      formId = 'close-group-form';
      const fullySettled = btn.getAttribute('data-fully-settled') === '1';
      const progress = btn.getAttribute('data-progress') || '';
      title = fullySettled ? 'Close / Complete Group?' : 'Close Group Early?';
      html = fullySettled
        ? 'This will mark the group as <strong>Completed / Closed</strong>.'
        : 'This group is still running (<strong>' + progress + '</strong>).<br>Closing now will <strong>terminate</strong> the group. Continue?';
      confirmColor = fullySettled ? '#7367f0' : '#ea5455';
      confirmText = fullySettled ? 'Yes, Close Group' : 'Yes, Close Early';
    } else if (btn.id === 'btn-cancel-termination') {
      formId = 'cancel-termination-form';
      title = 'Cancel Termination?';
      html = 'Cancel termination and <strong>un-freeze all member accounts</strong>?';
      confirmColor = '#03C95A';
      confirmText = 'Yes, Un-freeze Accounts';
    }

    const form = formId ? document.getElementById(formId) : null;
    if (!form) return;

    const run = function () {
      form.classList.add('group-ajax-form');
      submitGroupAjaxForm(form).catch(function (err) {
        showToast('error', 'Error', err.message || 'Something went wrong.');
      });
    };

    if (typeof Swal === 'undefined') {
      if (window.confirm(title)) run();
      return;
    }

    Swal.fire({
      title: title,
      html: html,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: confirmColor,
      cancelButtonColor: '#6c757d',
      confirmButtonText: confirmText
    }).then(function (result) {
      if (result.isConfirmed) run();
    });
  });

  // Re-bind EMI expand chevrons after AJAX replace
  document.addEventListener('chit-group-view:reloaded', function () {
    document.querySelectorAll('.member-emi-toggle').forEach(function (btn) {
      const targetSelector = btn.getAttribute('data-bs-target');
      const panel = targetSelector ? document.querySelector(targetSelector) : null;
      if (!panel) return;

      panel.addEventListener('show.bs.collapse', function () {
        btn.setAttribute('aria-expanded', 'true');
        btn.querySelector('i')?.classList.replace('ri-arrow-down-s-line', 'ri-arrow-up-s-line');
      });
      panel.addEventListener('hide.bs.collapse', function () {
        btn.setAttribute('aria-expanded', 'false');
        btn.querySelector('i')?.classList.replace('ri-arrow-up-s-line', 'ri-arrow-down-s-line');
      });
    });
  });
})();
