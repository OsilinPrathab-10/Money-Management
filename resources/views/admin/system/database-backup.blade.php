@extends('layouts/layoutMaster')

@section('title', 'Database Backup')

@section('page-style')
<style>
  .custom-option.custom-option-icon .custom-option-content {
    display: flex;
    align-items: center;
  }
  .custom-option.custom-option-icon .custom-option-body {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    flex-wrap: wrap;
    width: 100%;
    text-align: center;
  }
  .custom-option.custom-option-icon .custom-option-body i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    margin-bottom: 0 !important;
  }
</style>
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/database-backup.js'])
@endsection

@section('content')
<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-6">
  <div>
    <h4 class="mb-1">Database Backup</h4>
    <p class="text-muted mb-0">Create and manage database backups</p>
  </div>
  <button type="button" class="btn btn-primary" id="createBackupBtn">
    <i class="icon-base ri ri-add-line me-1"></i> Create New Backup
  </button>
</div>

<!-- Backup Information -->
<div class="row">
  <div class="col-12 col-lg-8 mb-5">
    <!-- Backup List Card -->
    <div class="card mb-5">
      <div class="card-header">
        <h5 class="mb-0">Backup History</h5>
      </div>
      <div class="card-body">
        @if(count($backups) > 0)
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Backup Name</th>
                  <th>Date</th>
                  <th>Size</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($backups as $backup)
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <i class="icon-base ri ri-database-2-line me-2 text-primary"></i>
                      <span class="fw-medium">{{ $backup['name'] }}</span>
                    </div>
                  </td>
                  <td>{{ $backup['date'] }}</td>
                  <td><span class="badge bg-label-info">{{ $backup['size'] }}</span></td>
                  <td>
                    <div class="d-flex gap-2">
                      <a href="{{ route('system-backup-download', $backup['name']) }}" class="btn btn-sm btn-icon btn-outline-primary" title="Download">
                        <i class="icon-base ri ri-download-line"></i>
                      </a>
                      <button type="button" class="btn btn-sm btn-icon btn-outline-danger delete-backup-btn" data-filename="{{ $backup['name'] }}" title="Delete">
                        <i class="icon-base ri ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="icon-base ri ri-database-2-line" style="font-size: 64px; color: #ddd;"></i>
            </div>
            <h6 class="mb-2">No Backups Found</h6>
            <p class="text-muted mb-3">Create your first database backup to get started</p>
            <button type="button" class="btn btn-primary" id="createFirstBackupBtn">
              <i class="icon-base ri ri-add-line me-1"></i> Create Backup
            </button>
          </div>
        @endif
      </div>
    </div>

    <!-- Auto Backup Configuration -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Auto Backup Configuration</h5>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="autoBackupStatus" {{ config('auto-backup.enabled') ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          <label class="form-check-label" for="autoBackupStatus"></label>
        </div>
      </div>
      <div class="card-body">
        <form id="autoBackupConfigForm">
          @csrf
          <input type="hidden" name="enabled" id="autoBackupEnabled" value="{{ config('auto-backup.enabled') ? '1' : '0' }}">
          
          <div class="mb-5">
            <label class="form-label fw-medium mb-3">Backup Frequency</label>
            <div class="row g-3">
              <!-- Daily -->
              <div class="col-md-4">
                <div class="form-check custom-option custom-option-icon {{ config('auto-backup.frequency') == 'daily' ? 'checked' : '' }}">
                  <label class="form-check-label custom-option-content" for="frequencyDaily">
                    <span class="custom-option-body">
                      <i class="ri-calendar-line ri-24px mb-2"></i>
                      <span class="custom-option-title h6 mb-1">Daily</span>
                      <small class="text-muted">Backup every day, keep latest only</small>
                    </span>
                    <input class="form-check-input" type="radio" name="frequency" id="frequencyDaily" value="daily" {{ config('auto-backup.frequency') == 'daily' ? 'checked' : '' }} />
                  </label>
                </div>
              </div>
              <!-- Weekly -->
              <div class="col-md-4">
                <div class="form-check custom-option custom-option-icon {{ config('auto-backup.frequency') == 'weekly' ? 'checked' : '' }}">
                  <label class="form-check-label custom-option-content" for="frequencyWeekly">
                    <span class="custom-option-body">
                      <i class="ri-calendar-week-line ri-24px mb-2"></i>
                      <span class="custom-option-title h6 mb-1">Weekly</span>
                      <small class="text-muted">Backup every Monday, keep latest only</small>
                    </span>
                    <input class="form-check-input" type="radio" name="frequency" id="frequencyWeekly" value="weekly" {{ config('auto-backup.frequency') == 'weekly' ? 'checked' : '' }} />
                  </label>
                </div>
              </div>
              <!-- Monthly -->
              <div class="col-md-4">
                <div class="form-check custom-option custom-option-icon {{ config('auto-backup.frequency') == 'monthly' ? 'checked' : '' }}">
                  <label class="form-check-label custom-option-content" for="frequencyMonthly">
                    <span class="custom-option-body">
                      <i class="ri-calendar-2-line ri-24px mb-2"></i>
                      <span class="custom-option-title h6 mb-1">Monthly</span>
                      <small class="text-muted">Backup on 1st of month, keep latest only</small>
                    </span>
                    <input class="form-check-input" type="radio" name="frequency" id="frequencyMonthly" value="monthly" {{ config('auto-backup.frequency') == 'monthly' ? 'checked' : '' }} />
                  </label>
                </div>
              </div>
            </div>
          </div>

          @if(config('auto-backup.last_backup_at'))
          <div class="alert alert-info mb-3">
            <small><strong>Last Auto Backup:</strong> {{ \Carbon\Carbon::parse(config('auto-backup.last_backup_at'))->format('d-m-Y h:i A') }}</small>
          </div>
          @endif

          <button type="submit" class="btn btn-primary w-100">
            <i class="ri-save-line me-1"></i> Save Configuration
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Backup Information Sidebar -->
  <div class="col-12 col-lg-4">
    <!-- Backup Info -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Backup Information</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <small class="text-muted d-block mb-1">Total Backups</small>
          <h5 class="mb-0">{{ count($backups) }}</h5>
        </div>
        <div class="mb-3">
          <small class="text-muted d-block mb-1">Database</small>
          <p class="mb-0 fw-medium">{{ config('database.connections.mysql.database') }}</p>
        </div>
        <div class="mb-0">
          <small class="text-muted d-block mb-1">Last Auto Backup</small>
          @if(config('auto-backup.last_backup_at'))
            <p class="mb-0 fw-medium">{{ \Carbon\Carbon::parse(config('auto-backup.last_backup_at'))->format('d-m-Y h:i A') }}</p>
          @else
            <p class="mb-0 text-muted">Never</p>
          @endif
        </div>
      </div>
    </div>

    <!-- Backup Guidelines -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Backup Guidelines</h5>
      </div>
      <div class="card-body">
        <div class="alert alert-warning mb-3" role="alert">
          <div class="d-flex align-items-start">
            <i class="ri-alert-line ri-20px me-2"></i>
            <div>
              <small><strong>Important:</strong> Always create a backup before making major changes to your database.</small>
            </div>
          </div>
        </div>

        <ul class="ps-3 mb-0 small">
          <li class="mb-2">Create regular backups to prevent data loss</li>
          <li class="mb-2">Store backups in a secure location</li>
          <li class="mb-2">Test backup restoration periodically</li>
          <li class="mb-2">Keep multiple backup versions</li>
          <li class="mb-0">Delete old backups to save space</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Backup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 rounded-circle" style="width: 60px; height: 60px;">
            <i class="icon-base ri ri-delete-bin-line text-danger" style="font-size: 32px;"></i>
          </div>
        </div>
        <h6 class="text-center mb-2">Are you sure?</h6>
        <p class="text-center text-muted mb-0">This will permanently delete the backup file. This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Backup</button>
      </div>
    </div>
  </div>
</div>

@endsection
