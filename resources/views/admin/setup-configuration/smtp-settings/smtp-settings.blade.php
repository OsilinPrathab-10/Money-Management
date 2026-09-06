@extends('layouts/layoutMaster')

@section('title', 'SMTP Settings')

@section('page-script')
@vite(['resources/assets/custom-js/smtp-settings.js'])
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
    <h4 class="mb-1">SMTP Settings</h4>
    <p class="text-muted mb-0">Configure email server settings for sending emails</p>
  </div>
</div>

<!-- SMTP Configuration Card -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Email Configuration</h5>
  </div>
  <div class="card-body">
    <form action="{{ route('smtp-settings-update') }}" method="POST" id="smtpSettingsForm">
      @csrf
      
      <!-- Mail Driver -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_mailer">Mail Driver <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <select id="mail_mailer" name="mail_mailer" class="form-select @error('mail_mailer') is-invalid @enderror" required>
            <option value="smtp" {{ $smtpSettings['mail_mailer'] == 'smtp' ? 'selected' : '' }}>SMTP</option>
            <option value="sendmail" {{ $smtpSettings['mail_mailer'] == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
            <option value="mailgun" {{ $smtpSettings['mail_mailer'] == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
            <option value="ses" {{ $smtpSettings['mail_mailer'] == 'ses' ? 'selected' : '' }}>Amazon SES</option>
          </select>
          @error('mail_mailer')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Select the mail driver to use for sending emails</small>
        </div>
      </div>

      <!-- SMTP Host -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_host">SMTP Host <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <input type="text" id="mail_host" name="mail_host" class="form-control @error('mail_host') is-invalid @enderror" 
            placeholder="smtp.gmail.com" value="{{ old('mail_host', $smtpSettings['mail_host']) }}" required />
          @error('mail_host')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">SMTP server hostname (e.g., smtp.gmail.com, smtp.mailtrap.io)</small>
        </div>
      </div>

      <!-- SMTP Port -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_port">SMTP Port <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <input type="number" id="mail_port" name="mail_port" class="form-control @error('mail_port') is-invalid @enderror" 
            placeholder="587" value="{{ old('mail_port', $smtpSettings['mail_port']) }}" required />
          @error('mail_port')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Common ports: 587 (TLS), 465 (SSL), 25 (Non-encrypted)</small>
        </div>
      </div>

      <!-- SMTP Username -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_username">SMTP Username <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <input type="text" id="mail_username" name="mail_username" class="form-control @error('mail_username') is-invalid @enderror" 
            placeholder="your-email@example.com" value="{{ old('mail_username', $smtpSettings['mail_username']) }}" required />
          @error('mail_username')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Your SMTP account username or email address</small>
        </div>
      </div>

      <!-- SMTP Password -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_password">SMTP Password</label>
        <div class="col-sm-9">
          <div class="input-group input-group-merge">
            <input type="password" id="mail_password" name="mail_password" class="form-control @error('mail_password') is-invalid @enderror" 
              placeholder="••••••••" value="{{ old('mail_password') }}" />
            <span class="input-group-text cursor-pointer" id="togglePassword" style="z-index: 5;">
              <i class="ri-eye-off-line" id="togglePasswordIcon"></i>
            </span>
          </div>
          @error('mail_password')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
          <small class="text-muted">Leave blank to keep current password</small>
        </div>
      </div>

      <!-- SMTP Encryption -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_encryption">Encryption <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <select id="mail_encryption" name="mail_encryption" class="form-select @error('mail_encryption') is-invalid @enderror" required>
            <option value="tls" {{ $smtpSettings['mail_encryption'] == 'tls' ? 'selected' : '' }}>TLS</option>
            <option value="ssl" {{ $smtpSettings['mail_encryption'] == 'ssl' ? 'selected' : '' }}>SSL</option>
            <option value="none" {{ $smtpSettings['mail_encryption'] == 'none' ? 'selected' : '' }}>None</option>
          </select>
          @error('mail_encryption')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Recommended: TLS for port 587, SSL for port 465</small>
        </div>
      </div>

      <!-- From Email Address -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_from_address">From Email <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <input type="email" id="mail_from_address" name="mail_from_address" class="form-control @error('mail_from_address') is-invalid @enderror" 
            placeholder="noreply@example.com" value="{{ old('mail_from_address', $smtpSettings['mail_from_address']) }}" required />
          @error('mail_from_address')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Email address that will appear as sender</small>
        </div>
      </div>

      <!-- From Name -->
      <div class="row mb-5">
        <label class="col-sm-3 col-form-label" for="mail_from_name">From Name <span class="text-danger">*</span></label>
        <div class="col-sm-9">
          <input type="text" id="mail_from_name" name="mail_from_name" class="form-control @error('mail_from_name') is-invalid @enderror" 
            placeholder="{{ config('app.name') }}" value="{{ old('mail_from_name', $smtpSettings['mail_from_name']) }}" required />
          @error('mail_from_name')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          <small class="text-muted">Name that will appear as sender</small>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="row">
        <div class="col-sm-9 offset-sm-3">
          <button type="submit" class="btn btn-primary me-3">
            <i class="ri-save-line me-1"></i> Save Settings
          </button>
          <button type="button" class="btn btn-outline-secondary" id="testConnectionBtn">
            <i class="ri-mail-send-line me-1"></i> Test Connection
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Test SMTP Connection</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-4">Enter an email address to send a test email and verify your SMTP configuration.</p>
        <form id="testEmailForm">
          <div class="mb-4">
            <label class="form-label" for="test_email">Test Email Address <span class="text-danger">*</span></label>
            <input type="email" id="test_email" name="test_email" class="form-control" placeholder="test@example.com" required />
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="sendTestEmailBtn">
              <i class="ri-mail-send-line me-1"></i> Send Test Email
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
