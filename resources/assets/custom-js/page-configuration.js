/**
 * Policy Pages Management
 */

'use strict';

// Toast notification function (same as loan-types) - Global scope
window.showAlert = function(type, title, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    let iconClass, bgClass;
    
    if (type === 'success') {
      iconClass = 'ri-check-line';
      bgClass = 'bg-success';
    } else if (type === 'danger') {
      iconClass = 'ri-close-circle-line';
      bgClass = 'bg-danger';
    } else {
      iconClass = 'ri-error-warning-line';
      bgClass = 'bg-danger';
    }
    
    const toastHTML = message ? `
      <div id="${toastId}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-top-5 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body rounded-bottom-5">
          ${message}
        </div>
      </div>
    ` : `
      <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-5 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
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

$(function () {
  // Check for session messages and show alerts (only once)
  const successMessage = document.querySelector('meta[name="success-message"]');
  if (successMessage) {
    const message = successMessage.getAttribute('content');
    if (message && typeof window.showAlert === 'function') {
      window.showAlert('success', message);
      // Remove meta tag to prevent duplicate alerts
      successMessage.remove();
    }
  }
  
  const errorMessage = document.querySelector('meta[name="error-message"]');
  if (errorMessage) {
    const message = errorMessage.getAttribute('content');
    if (message && typeof window.showAlert === 'function') {
      window.showAlert('danger', message);
      // Remove meta tag to prevent duplicate alerts
      errorMessage.remove();
    }
  }
  
  // Handle delete button clicks
  const deleteButtons = document.querySelectorAll('.delete-policy');
  const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  let currentPageId = null;
  let currentRow = null;
  
  deleteButtons.forEach(button => {
    button.addEventListener('click', function() {
      const pageId = this.getAttribute('data-id');
      const pageName = this.getAttribute('data-name');
      
      // Store current page info
      currentPageId = pageId;
      currentRow = this.closest('tr');
      
      // Update modal content
      document.getElementById('deletePolicyName').textContent = pageName;
      
      // Show modal
      deleteModal.show();
    });
  });
  
  // Handle confirm delete
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function() {
      if (currentPageId && currentRow) {
        // Disable button to prevent double clicks
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        
        // Submit to backend
        fetch(baseUrl + `setup-configuration/page-configuration/delete/${currentPageId}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Remove the row from table
            currentRow.remove();
            
            // Hide delete modal
            deleteModal.hide();
            
            // Show success toast
            window.showAlert('success', data.message || 'Policy page deleted successfully');
            
            // Reset button
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
            
            // Reset
            currentPageId = null;
            currentRow = null;
          } else {
            throw new Error(data.message || 'Failed to delete policy page');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          
          // Hide delete modal
          deleteModal.hide();
          
          // Show error toast
          window.showAlert('danger', error.message || 'Failed to delete policy page');
          
          // Reset button
          confirmDeleteBtn.disabled = false;
          confirmDeleteBtn.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
        });
      }
    });
  }
});
