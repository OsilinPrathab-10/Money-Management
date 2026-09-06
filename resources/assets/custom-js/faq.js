'use strict';

(function () {
  // Check for success/error messages
  const successMessage = document.querySelector('meta[name="success-message"]');
  const errorMessage = document.querySelector('meta[name="error-message"]');

  if (successMessage) {
    showAlert('success', successMessage.getAttribute('content'));
  }

  if (errorMessage) {
    showAlert('danger', errorMessage.getAttribute('content'));
  }

  // Delete FAQ functionality
  const deleteFaqBtns = document.querySelectorAll('.delete-faq');
  const deleteModal = document.getElementById('deleteModal');
  const deleteFaqQuestion = document.getElementById('deleteFaqQuestion');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  let currentFaqId = null;

  deleteFaqBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      currentFaqId = this.getAttribute('data-id');
      const question = this.getAttribute('data-question');
      
      deleteFaqQuestion.textContent = question;
      
      // Show modal
      const modal = new bootstrap.Modal(deleteModal);
      modal.show();
    });
  });

  // Confirm delete
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function() {
      if (!currentFaqId) return;

      // Disable button
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

      // Send delete request
      fetch(`/setup-configuration/faq/delete/${currentFaqId}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
      })
      .then(response => response.json())
      .then(data => {
        // Hide modal
        const modal = bootstrap.Modal.getInstance(deleteModal);
        modal.hide();

        if (data.success) {
          showAlert('success', data.message);
          // Reload page after short delay
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showAlert('danger', data.message);
          // Re-enable button
          this.disabled = false;
          this.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        // Hide modal
        const modal = bootstrap.Modal.getInstance(deleteModal);
        modal.hide();
        
        showAlert('danger', 'An error occurred. Please try again.');
        // Re-enable button
        this.disabled = false;
        this.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
      });
    });
  }

  /**
   * Show alert toast notification
   * @param {string} type - 'success' or 'danger'
   * @param {string} message - Toast message
   */
  function showAlert(type, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    let iconClass, bgClass;
    
    // Map alert types to toast styles
    if (type === 'success') {
      iconClass = 'ri-check-line';
      bgClass = 'bg-success';
    } else if (type === 'danger') {
      iconClass = 'ri-close-circle-line';
      bgClass = 'bg-danger';
    } else if (type === 'warning') {
      iconClass = 'ri-alert-line';
      bgClass = 'bg-warning';
    } else if (type === 'info') {
      iconClass = 'ri-information-line';
      bgClass = 'bg-info';
    } else {
      iconClass = 'ri-error-warning-line';
      bgClass = 'bg-danger';
    }
    
    // Create toast with rounded corners and shadow
    const toastHTML = `
      <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-5 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${message}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
      autohide: true,
      delay: 3000
    });
    
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
      toastElement.remove();
    });
  }

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }
})();
