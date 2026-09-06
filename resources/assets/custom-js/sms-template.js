/**
 * SMS Template Management
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
  const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');

  // Toast notification function
  function showToast(type, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    let iconClass, bgClass;
    
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

  // Show flash messages from server-side sessions
  const successMessage = document.querySelector('meta[name="success-message"]');
  const errorMessage = document.querySelector('meta[name="error-message"]');

  if (successMessage) {
    showToast('success', successMessage.getAttribute('content'));
  }

  if (errorMessage) {
    showToast('danger', errorMessage.getAttribute('content'));
  }

  // Delete template functionality
  const deleteButtons = document.querySelectorAll('.delete-template');
  const deleteModal = document.getElementById('deleteModal');
  const deleteTemplateName = document.getElementById('deleteTemplateName');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  let templateToDelete = null;

  deleteButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      
      templateToDelete = this.getAttribute('data-id');
      const templateName = this.getAttribute('data-name');
      
      deleteTemplateName.textContent = templateName;
      
      const modal = new bootstrap.Modal(deleteModal);
      modal.show();
    });
  });

  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function() {
      if (!templateToDelete) return;

      // Show loading state
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deleting...';
      this.disabled = true;

      fetch(`${baseUrl}templates/sms/${templateToDelete}/delete`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/json'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('success', data.message);
          
          // Close modal
          const modal = bootstrap.Modal.getInstance(deleteModal);
          modal.hide();
          
          // Reload page after short delay
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          showToast('danger', data.message || 'Failed to delete template');
          
          // Reset button
          this.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
          this.disabled = false;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('danger', 'An error occurred while deleting the template');
        
        // Reset button
        this.innerHTML = '<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete';
        this.disabled = false;
      });
    });
  }

  // Auto-generate identifier from name
  const nameInput = document.getElementById('name');
  const identifierInput = document.getElementById('identifier');

  if (nameInput && identifierInput) {
    nameInput.addEventListener('input', function() {
      // Only auto-generate if identifier is empty (for new templates)
      if (!identifierInput.value.trim()) {
        const identifier = this.value
          .toLowerCase()
          .replace(/[^a-z0-9\s]/g, '') // Remove special characters
          .replace(/\s+/g, '_') // Replace spaces with underscores
          .replace(/_+/g, '_') // Replace multiple underscores with single
          .replace(/^_|_$/g, ''); // Remove leading/trailing underscores
        
        identifierInput.value = identifier;
      }
    });
  }

  // Character counter for SMS body
  const smsBodyTextarea = document.getElementById('sms_body');
  if (smsBodyTextarea) {
    const maxLength = 160; // Standard SMS length
    
    // Create character counter
    const counterDiv = document.createElement('div');
    counterDiv.className = 'text-end mt-1';
    counterDiv.innerHTML = `<small class="text-muted"><span id="charCount">0</span>/${maxLength} characters</small>`;
    smsBodyTextarea.parentNode.appendChild(counterDiv);
    
    const charCount = document.getElementById('charCount');
    
    function updateCharCount() {
      const length = smsBodyTextarea.value.length;
      charCount.textContent = length;
      
      if (length > maxLength) {
        charCount.parentElement.classList.remove('text-muted');
        charCount.parentElement.classList.add('text-warning');
      } else {
        charCount.parentElement.classList.remove('text-warning');
        charCount.parentElement.classList.add('text-muted');
      }
    }
    
    smsBodyTextarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
  }
});
