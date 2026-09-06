/**
 * Activity Logs
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  let map = null;
  let marker = null;

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

  // Flash alerts from server-side sessions
  const alertContainer = document.querySelector('.alert-container');
  if (alertContainer) {
    const successMessage = alertContainer.getAttribute('data-success');
    const errorMessage = alertContainer.getAttribute('data-error');
    const warningMessage = alertContainer.getAttribute('data-warning');
    const infoMessage = alertContainer.getAttribute('data-info');

    if (successMessage) {
      showToast('success', successMessage);
    }

    if (errorMessage) {
      showToast('danger', errorMessage);
    }

    if (warningMessage) {
      showToast('warning', warningMessage);
    }

    if (infoMessage) {
      showToast('info', infoMessage);
    }
  }

  // Initialize modal
  const locationMapModal = new bootstrap.Modal(document.getElementById('locationMapModal'));
  const viewLocationBtns = document.querySelectorAll('.view-location-btn');

  // View Location
  viewLocationBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const activityId = this.getAttribute('data-activity-id');
      loadLocationDetails(activityId);
    });
  });

  function loadLocationDetails(activityId) {
    // Show modal
    locationMapModal.show();

    // Reset map container
    const mapContainer = document.getElementById('locationMap');
    mapContainer.innerHTML = `
      <div class="d-flex align-items-center justify-content-center h-100">
        <div class="text-center">
          <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-muted">Loading location data...</p>
        </div>
      </div>
    `;

    // Fetch location details
    fetch(baseUrl + 'audit-logs/activity-logs/location/' + activityId, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const locationData = data.data;

        // Update modal info
        document.getElementById('modalClientName').textContent = locationData.client_name;
        document.getElementById('modalPhone').textContent = locationData.phone;
        document.getElementById('modalDevice').textContent = locationData.device_name || locationData.device_model || 'Unknown';
        document.getElementById('modalIpAddress').textContent = locationData.ip_address || 'N/A';
        document.getElementById('modalLoginAt').textContent = locationData.login_at;
        document.getElementById('modalLogoutAt').textContent = locationData.logout_at;

        // Check if coordinates are available
        if (locationData.latitude && locationData.longitude) {
          const lat = parseFloat(locationData.latitude);
          const lng = parseFloat(locationData.longitude);

          document.getElementById('modalCoordinates').textContent = `Latitude: ${lat}, Longitude: ${lng}`;

          // Initialize map
          initializeMap(lat, lng);
        } else {
          mapContainer.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100">
              <div class="text-center">
                <i class="icon-base ri ri-map-pin-off-line text-muted" style="font-size: 48px;"></i>
                <p class="text-muted mt-3 mb-0">Location data not available</p>
              </div>
            </div>
          `;
          document.getElementById('modalCoordinates').textContent = 'Location not recorded';
        }
      } else {
        showToast('danger', data.message || 'Failed to load location details');
        locationMapModal.hide();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('danger', 'Failed to load location details. Please try again.');
      locationMapModal.hide();
    });
  }

  function initializeMap(lat, lng) {
    const mapContainer = document.getElementById('locationMap');
    
    // Clear previous map
    mapContainer.innerHTML = '';

    // Check if Leaflet is available
    if (typeof L !== 'undefined') {
      // Initialize Leaflet map
      map = L.map('locationMap').setView([lat, lng], 15);

      // Add OpenStreetMap tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
      }).addTo(map);

      // Add marker
      marker = L.marker([lat, lng]).addTo(map);
      marker.bindPopup('<b>Last Login Location</b>').openPopup();
    } else {
      // Fallback to Google Maps static image
      mapContainer.innerHTML = `
        <img src="https://maps.googleapis.com/maps/api/staticmap?center=${lat},${lng}&zoom=15&size=600x400&markers=color:red%7C${lat},${lng}&key=YOUR_GOOGLE_MAPS_API_KEY" 
          alt="Location Map" 
          class="w-100 h-100" 
          style="object-fit: cover;">
      `;
    }
  }

  // Clean up map when modal is hidden
  document.getElementById('locationMapModal').addEventListener('hidden.bs.modal', function () {
    if (map) {
      map.remove();
      map = null;
      marker = null;
    }
  });
});
