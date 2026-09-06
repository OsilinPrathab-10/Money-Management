@extends('layouts/layoutMaster')

@section('title', 'Send Notifications')

@section('page-style')
<style>
  /* Mobile Phone Mockup Styles */
  .phone-mockup {
    position: relative;
    width: 300px;
    height: 600px;
    margin: 0 auto;
    background: #1a1a1a;
    border-radius: 40px;
    padding: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  }

  .phone-screen {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 30px;
    position: relative;
    overflow: hidden;
  }

  .phone-notch {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 150px;
    height: 25px;
    background: #1a1a1a;
    border-radius: 0 0 20px 20px;
    z-index: 10;
  }

  .phone-time {
    position: absolute;
    top: 40px;
    left: 0;
    right: 0;
    text-align: center;
    color: white;
    font-size: 48px;
    font-weight: 300;
    z-index: 5;
  }

  .phone-date {
    position: absolute;
    top: 95px;
    left: 0;
    right: 0;
    text-align: center;
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    z-index: 5;
  }

  .notification-card {
    position: absolute;
    top: 140px;
    left: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    z-index: 20;
    animation: slideDown 0.3s ease-out;
  }

  @keyframes slideDown {
    from {
      transform: translateY(-20px);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  .notification-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
  }

  .notification-icon {
    width: 24px;
    height: 24px;
    background: #696cff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    flex-shrink: 0;
    overflow: hidden;
  }

  .notification-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .notification-icon i {
    color: white;
    font-size: 14px;
  }

  .notification-app-name {
    font-size: 12px;
    font-weight: 600;
    color: #333;
    flex-grow: 1;
  }

  .notification-time-badge {
    font-size: 10px;
    color: #999;
  }

  .notification-title {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 4px;
    line-height: 1.3;
  }

  .notification-body {
    font-size: 13px;
    color: #666;
    line-height: 1.4;
    max-height: 60px;
    overflow: hidden;
  }

  .phone-bottom-bar {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 120px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
  }

  .sticky-preview {
    position: sticky;
    top: 100px;
  }
</style>
@endsection

@section('content')

<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<div class="row">
  <!-- Left Column - Notification Form -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">
          <i class="ri-notification-line me-2"></i>Send Notification
        </h5>
        <p class="text-muted mb-0">Broadcast notifications to users, agents, or all</p>
      </div>
      <div class="card-body">
        <form id="notificationForm" action="{{ url('/admin/notification/send') }}" method="POST">
          @csrf
          
          <!-- Notification Title -->
          <div class="mb-4">
            <label for="notificationTitle" class="form-label fw-semibold">
              Notification Title <span class="text-danger">*</span>
            </label>
            <input 
              type="text" 
              id="notificationTitle"
              name="title" 
              class="form-control" 
              placeholder="Enter notification title"
              required>
          </div>

          <!-- Notification Body -->
          <div class="mb-4">
            <label for="notificationBody" class="form-label fw-semibold">
              Notification Message <span class="text-danger">*</span>
            </label>
            <textarea 
              id="notificationBody"
              name="body" 
              class="form-control" 
              rows="4"
              placeholder="Enter notification message"
              required></textarea>
          </div>


          <!-- Target Audience -->
          <div class="mb-4">
            <label for="notificationTarget" class="form-label fw-semibold">
              Target Audience <span class="text-danger">*</span>
            </label>
            <select 
              id="notificationTarget"
              name="target" 
              class="form-select" 
              required>
              <option value="users">Clients</option>
              <option value="agents">Agents</option>
              <option value="all">All</option>
            </select>
            <div class="form-text">
              <i class="ri-information-line me-1"></i>
              Select who should receive this notification
            </div>
          </div>

          <!-- Notification Type -->
          <div class="mb-4">
            <label for="notificationType" class="form-label fw-semibold">
              Notification Type <span class="text-danger">*</span>
            </label>
            <select 
              id="notificationType"
              name="type" 
              class="form-select" 
              required>
              <option value="">-- Select Notification Type --</option>
              <option value="general" data-targets="users,agents,all">General Announcement</option>
              <option value="loan_product" data-targets="users">New Loan Product</option>
              <option value="interest_update" data-targets="users">Loan Interest Update</option>
              <option value="disbursement" data-targets="users">Application Disbursed</option>
              <option value="offer" data-targets="users,agents,all">Offer / Promotion</option>
            </select>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="ri-send-plane-line me-1"></i> Send Notification
            </button>
            <button type="reset" class="btn btn-outline-secondary">
              <i class="ri-refresh-line me-1"></i> Reset Form
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Column - Mobile Preview -->
  <div class="col-lg-5">
    <div class="sticky-preview">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="ri-smartphone-line me-2"></i>Notification Preview
          </h5>
          <p class="text-muted mb-0">Preview how your notification will appear</p>
        </div>
        <div class="card-body d-flex justify-content-center py-5">
          <!-- Mobile Phone Mockup -->
          <div class="phone-mockup">
            <div class="phone-notch"></div>
            <div class="phone-screen">
              <!-- Time Display -->
              <div class="phone-time" id="phoneTime">10:24</div>
              <div class="phone-date" id="phoneDate">Tuesday, September 13</div>
              
              @php
                $notificationAppName = \App\Helpers\SettingsHelper::get('admin_title')
                    ?? ($appearance->title ?? ($appInfo->app_name ?? config('app.name', 'Finova')));
                $adminLogo = \App\Helpers\SettingsHelper::get('admin_logo');
              @endphp

              <!-- Notification Card -->
              <div class="notification-card">
                <div class="notification-header">
                  <div class="notification-icon" id="notificationIcon">
                    @if($adminLogo)
                      <img src="{{ asset('storage/' . $adminLogo) }}" alt="App Logo">
                    @elseif(isset($appearance) && $appearance && $appearance->logo)
                      <img src="{{ asset('storage/' . $appearance->logo) }}" alt="App Logo">
                    @else
                      <i class="ri-notification-3-fill"></i>
                    @endif
                  </div>
                  <div class="notification-app-name">{{ $notificationAppName }}</div>
                  <div class="notification-time-badge">now</div>
                </div>
                <div class="notification-title" id="previewTitle">
                  Notification Title
                </div>
                <div class="notification-body" id="previewBody">
                  Your notification message will appear here...
                </div>
              </div>

              <!-- Bottom Bar -->
              <div class="phone-bottom-bar"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
  // Live preview functionality
  document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('notificationTitle');
    const bodyInput = document.getElementById('notificationBody');
    const typeSelect = document.getElementById('notificationType');
    const targetSelect = document.getElementById('notificationTarget');
    
    const previewTitle = document.getElementById('previewTitle');
    const previewBody = document.getElementById('previewBody');

    // Dynamic notification type filtering based on target
    function filterNotificationTypes() {
      const selectedTarget = targetSelect.value;
      const typeOptions = typeSelect.querySelectorAll('option');
      
      // Reset selection if current selection is not valid for new target
      const currentValue = typeSelect.value;
      let currentOptionValid = false;
      
      typeOptions.forEach(option => {
        if (option.value === '') {
          // Always show the placeholder option
          option.style.display = '';
          return;
        }
        
        const targets = option.dataset.targets || '';
        const targetList = targets.split(',');
        
        if (targetList.includes(selectedTarget)) {
          option.style.display = '';
          if (option.value === currentValue) {
            currentOptionValid = true;
          }
        } else {
          option.style.display = 'none';
        }
      });
      
      // Reset to placeholder if current selection is not valid
      if (!currentOptionValid && currentValue !== '') {
        typeSelect.value = '';
      }
    }
    
    // Filter on page load
    filterNotificationTypes();
    
    // Filter when target changes
    targetSelect.addEventListener('change', filterNotificationTypes);

    // Update time and date
    function updateTime() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false 
      });
      const dateString = now.toLocaleDateString('en-US', { 
        weekday: 'long', 
        month: 'long', 
        day: 'numeric' 
      });
      
      document.getElementById('phoneTime').textContent = timeString;
      document.getElementById('phoneDate').textContent = dateString;
    }
    
    updateTime();
    setInterval(updateTime, 1000);

    // Update preview on input
    titleInput.addEventListener('input', function() {
      previewTitle.textContent = this.value || 'Notification Title';
    });

    bodyInput.addEventListener('input', function() {
      previewBody.textContent = this.value || 'Your notification message will appear here...';
    });

    // Handle alerts from session
    const alertContainer = document.querySelector('.alert-container');
    if (alertContainer) {
      const success = alertContainer.dataset.success;
      const error = alertContainer.dataset.error;
      const warning = alertContainer.dataset.warning;
      const info = alertContainer.dataset.info;

      if (success) {
        showAlert('success', success);
      }
      if (error) {
        showAlert('error', error);
      }
      if (warning) {
        showAlert('warning', warning);
      }
      if (info) {
        showAlert('info', info);
      }
    }
  });

  function showAlert(type, message) {
    const alertTypes = {
      success: { class: 'alert-success', icon: 'ri-checkbox-circle-line' },
      error: { class: 'alert-danger', icon: 'ri-error-warning-line' },
      warning: { class: 'alert-warning', icon: 'ri-alert-line' },
      info: { class: 'alert-info', icon: 'ri-information-line' }
    };

    const config = alertTypes[type] || alertTypes.info;
    
    const alertHtml = `
      <div class="alert ${config.class} alert-dismissible fade show" role="alert">
        <i class="${config.icon} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    `;

    const container = document.querySelector('.alert-container');
    container.innerHTML = alertHtml;
  }
</script>
@endsection
