@extends('layouts/contentNavbarLayout')

@section('title', 'My Profile')

@section('content')
<div class="row">
  <div class="col-12">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">
        <i class="icon-base ri ri-user-3-line me-2"></i>My Profile
      </h4>
    </div>

    <!-- Success Message -->
    @if(session('success'))
      <div class="alert alert-success alert-dismissible d-flex align-items-center mb-4" role="alert">
        <i class="ri ri-checkbox-circle-line me-2" style="font-size: 1.25rem;"></i>
        <div class="flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    @endif

    <div class="row g-4">
      <!-- Profile Information -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center">
            <i class="icon-base ri ri-user-settings-line me-2 text-primary"></i>
            <h5 class="mb-0">Profile Information</h5>
          </div>
          <div class="card-body d-flex flex-column">
            <form action="{{ route('profile.update') }}" method="POST" class="d-flex flex-column h-100">
              @csrf
              @method('PUT')

              <!-- Username -->
              <div class="mb-4">
                <label for="name" class="form-label">Username</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri ri-user-line"></i></span>
                  <input type="text" class="form-control @error('name') is-invalid @enderror" 
                         id="name" name="name" value="{{ old('name', $user->name) }}" 
                         placeholder="Enter your name" required>
                  @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <!-- Email -->
              <div class="mb-4">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri ri-mail-line"></i></span>
                  <input type="email" class="form-control @error('email') is-invalid @enderror" 
                         id="email" name="email" value="{{ old('email', $user->email) }}" 
                         placeholder="Enter your email" required>
                  @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <!-- Role (Read-only) -->
              <div class="mb-4">
                <label class="form-label">Role</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri ri-shield-user-line"></i></span>
                  <input type="text" class="form-control" value="Admin" readonly>
                </div>
              </div>

              <!-- Submit Button -->
              <div class="mt-auto">
                <button type="submit" class="btn btn-primary w-100">
                  <i class="icon-base ri ri-save-line me-1"></i>
                  Update Profile
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Change Password -->
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center">
            <i class="icon-base ri ri-lock-password-line me-2 text-warning"></i>
            <h5 class="mb-0">Change Password</h5>
          </div>
          <div class="card-body d-flex flex-column">
            <form action="{{ route('profile.password.update') }}" method="POST" class="d-flex flex-column h-100">
              @csrf
              @method('PUT')

              <!-- Current Password -->
              <div class="mb-4">
                <label for="current_password" class="form-label">Current Password</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri ri-lock-line"></i></span>
                  <input type="password" class="form-control @error('current_password') is-invalid @enderror" 
                         id="current_password" name="current_password" 
                         placeholder="Enter current password" required>
                  @error('current_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <!-- New Password -->
              <div class="mb-4">
                <label for="new_password" class="form-label">New Password</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri ri-lock-unlock-line"></i></span>
                  <input type="password" class="form-control @error('new_password') is-invalid @enderror" 
                         id="new_password" name="new_password" 
                         placeholder="Enter new password" required>
                  @error('new_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <small class="text-muted">Minimum 8 characters</small>
              </div>

              <!-- Confirm New Password -->
              <div class="mb-4">
                <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                <div class="input-group input-group-merge">
                  <span class="input-group-text"><i class="ri ri-lock-unlock-line"></i></span>
                  <input type="password" class="form-control" 
                         id="new_password_confirmation" name="new_password_confirmation" 
                         placeholder="Confirm new password" required>
                </div>
              </div>

              <!-- Submit Button -->
              <div class="mt-auto">
                <button type="submit" class="btn btn-warning w-100">
                  <i class="icon-base ri ri-lock-unlock-line me-1"></i>
                  Change Password
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Account Information -->
    <div class="row mt-4">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <i class="icon-base ri ri-information-line me-2 text-info"></i>
            <h5 class="mb-0">Account Information</h5>
          </div>
          <div class="card-body">
            <div class="row g-4">
              <div class="col-md-4">
                <div class="d-flex align-items-center">
                  <div class="avatar flex-shrink-0 me-3">
                    <div class="avatar-initial bg-label-primary rounded">
                      <i class="icon-base ri ri-calendar-line icon-22px"></i>
                    </div>
                  </div>
                  <div>
                    <small class="text-muted d-block mb-1">Account Created</small>
                    <h6 class="mb-0">{{ $user->created_at ? $user->created_at->format('d-m-Y') : 'N/A' }}</h6>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-center">
                  <div class="avatar flex-shrink-0 me-3">
                    <div class="avatar-initial bg-label-info rounded">
                      <i class="icon-base ri ri-time-line icon-22px"></i>
                    </div>
                  </div>
                  <div>
                    <small class="text-muted d-block mb-1">Last Updated</small>
                    <h6 class="mb-0">{{ $user->updated_at ? $user->updated_at->format('d-m-Y') : 'N/A' }}</h6>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="d-flex align-items-center">
                  <div class="avatar flex-shrink-0 me-3">
                    <div class="avatar-initial bg-label-success rounded">
                      <i class="icon-base ri ri-checkbox-circle-line icon-22px"></i>
                    </div>
                  </div>
                  <div>
                    <small class="text-muted d-block mb-1">Account Status</small>
                    <h6 class="mb-0">
                      <span class="badge bg-label-success">Active</span>
                    </h6>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
