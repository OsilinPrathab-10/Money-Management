/**
 * Client Recycle Bin
 * Lists soft deleted clients and restores them together with their archived accounts.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
  const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const tableEl = document.getElementById('recycleBinTable');

  if (!tableEl) {
    return;
  }

  const isAdmin = window.userRole === 'Admin';

  const renderAccounts = accounts => {
    if (!accounts) return '<span class="text-muted">None</span>';

    const badges = [
      { label: 'Loan A/Cs', value: accounts.loan_accounts, color: 'primary' },
      { label: 'Applications', value: accounts.loan_applications, color: 'info' },
      { label: 'Chits', value: accounts.chit_memberships, color: 'warning' },
      { label: 'FDs', value: accounts.fixed_deposits, color: 'success' }
    ].filter(item => Number(item.value) > 0);

    if (!badges.length) {
      return '<span class="text-muted">No linked accounts</span>';
    }

    return badges
      .map(item => `<span class="badge bg-label-${item.color} me-1">${item.label}: ${item.value}</span>`)
      .join('');
  };

  const dt = new DataTable(tableEl, {
    processing: true,
    serverSide: true,
    ajax: {
      url: window.recycleBinDataUrl || baseUrl + 'client-management/recycle-bin',
      type: 'GET'
    },
    columns: [
      { data: null, orderable: false, searchable: false },
      { data: 'client_name' },
      { data: 'client_phone' },
      { data: 'client_email' },
      { data: 'accounts', orderable: false, searchable: false },
      { data: 'deleted_at' },
      { data: null, orderable: false, searchable: false }
    ],
    columnDefs: [
      {
        targets: 0,
        render: (data, type, full, meta) => meta.row + meta.settings._iDisplayStart + 1
      },
      {
        targets: 1,
        render: (data, type, full) => `<span class="fw-medium">${full.client_name || 'N/A'}</span>`
      },
      {
        targets: 4,
        render: (data, type, full) => renderAccounts(full.accounts)
      },
      {
        targets: -1,
        className: 'text-center text-nowrap',
        render: (data, type, full) => {
          let actions = `<button type="button" class="btn btn-sm btn-label-success me-1 btn-restore-client"
              data-url="${full.restore_url}" data-name="${full.client_name}">
              <i class="icon-base ri ri-arrow-go-back-line me-1"></i>Restore
            </button>`;

          if (isAdmin) {
            actions += `<button type="button" class="btn btn-sm btn-label-danger btn-force-delete-client"
              data-url="${full.force_delete_url}" data-name="${full.client_name}">
              <i class="icon-base ri ri-delete-bin-line me-1"></i>Delete Forever
            </button>`;
          }

          return actions;
        }
      }
    ],
    order: [[5, 'desc']],
    pageLength: 20,
    language: {
      emptyTable: 'The recycle bin is empty.',
      search: '',
      searchPlaceholder: 'Search deleted clients...'
    }
  });

  const refresh = () => {
    dt.ajax.reload(null, false);
  };

  const send = (url, method) =>
    fetch(url, {
      method: method,
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(res => res.json());

  document.addEventListener('click', function (e) {
    const restoreBtn = e.target.closest('.btn-restore-client');
    if (restoreBtn) {
      Swal.fire({
        title: 'Restore this client?',
        text: `${restoreBtn.dataset.name} will be restored along with the loans, chits and fixed deposits archived with them.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, restore',
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false,
        showLoaderOnConfirm: true,
        preConfirm: () =>
          send(restoreBtn.dataset.url, 'POST').then(data => {
            if (!data.success) throw new Error(data.message || 'Restore failed.');
            return data;
          }).catch(err => Swal.showValidationMessage(err.message)),
        allowOutsideClick: () => !Swal.isLoading()
      }).then(result => {
        if (result.isConfirmed && result.value?.success) {
          Swal.fire({
            icon: 'success',
            title: 'Restored',
            text: result.value.message,
            customClass: { confirmButton: 'btn btn-success' },
            buttonsStyling: false
          }).then(refresh);
        }
      });
      return;
    }

    const forceBtn = e.target.closest('.btn-force-delete-client');
    if (forceBtn) {
      Swal.fire({
        title: 'Delete permanently?',
        html: `<strong>${forceBtn.dataset.name}</strong> and every related loan, EMI, collection, chit and fixed deposit record will be erased. <br><br>This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete forever',
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false,
        showLoaderOnConfirm: true,
        preConfirm: () =>
          send(forceBtn.dataset.url, 'DELETE').then(data => {
            if (!data.success) throw new Error(data.message || 'Permanent deletion failed.');
            return data;
          }).catch(err => Swal.showValidationMessage(err.message)),
        allowOutsideClick: () => !Swal.isLoading()
      }).then(result => {
        if (result.isConfirmed && result.value?.success) {
          Swal.fire({
            icon: 'success',
            title: 'Deleted',
            text: result.value.message,
            customClass: { confirmButton: 'btn btn-success' },
            buttonsStyling: false
          }).then(refresh);
        }
      });
    }
  });

  tableEl.addEventListener('draw.dt', function () {
    const info = dt.page.info();
    const badge = document.getElementById('trashedCount');
    if (badge) {
      badge.textContent = info.recordsTotal;
    }
  });
});
