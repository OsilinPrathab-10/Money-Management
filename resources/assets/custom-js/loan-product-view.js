/**
 * Loan Product View Page
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // Edit Product Form Submit
  const editProductForm = document.getElementById('editProductForm');
  const editSubmitBtn = document.getElementById('editProductSubmitBtn');
  
  if (editProductForm && editSubmitBtn) {
    editProductForm.addEventListener('submit', function (e) {
      e.preventDefault();
      
      const formData = new FormData(editProductForm);
      const actionUrl = editProductForm.getAttribute('action');
      const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

      const originalBtnHtml = editSubmitBtn.innerHTML;
      editSubmitBtn.disabled = true;
      editSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating...';

      fetch(actionUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: formData
      })
        .then(async response => {
          const data = await response.json().catch(() => ({}));
          if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to update loan product');
          }
          return data;
        })
        .then(data => {
          const editModal = bootstrap.Modal.getInstance(document.getElementById('editProductModal'));
          if (editModal) {
            editModal.hide();
          }

          const successModal = new bootstrap.Modal(document.getElementById('successModal'));
          successModal.show();

          document.getElementById('successModal').addEventListener('hidden.bs.modal', function () {
            window.location.reload();
          }, { once: true });
        })
        .catch(error => {
          console.error('Loan product update failed:', error);
          Swal.fire({
            icon: 'error',
            title: 'Update Failed',
            text: error.message || 'Unexpected error occurred while updating loan product'
          });
        })
        .finally(() => {
          editSubmitBtn.disabled = false;
          editSubmitBtn.innerHTML = originalBtnHtml;
        });
    });
  }
});
