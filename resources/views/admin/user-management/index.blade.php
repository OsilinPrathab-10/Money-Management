@extends('layouts/layoutMaster')

@section('title', 'User Management')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')
  <script>
    window.authEmail = "{{ auth()->user()->email }}";
  </script>
  @vite(['resources/assets/custom-js/user-management.js'])
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible shadow-sm border-0 bg-label-success" role="alert">
      <div class="d-flex">
        <i class="ri-checkbox-circle-line me-2"></i>
        <div>{{ session('success') }}</div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <!-- Statistics Cards -->
  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Total Users</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1">{{ $totalUsers }}</h4>
              </div>
              <small class="mb-0">All registered users</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <div class="icon-base ri ri-group-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Active Users</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1">{{ $activeUsers }}</h4>
              </div>
              <small class="mb-0">Currently active</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded-3">
                <div class="icon-base ri ri-user-follow-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Inactive Users</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1">{{ $inactiveUsers }}</h4>
              </div>
              <small class="mb-0">Not active now</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-warning rounded-3">
                <div class="icon-base ri ri-user-unfollow-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Users List Table -->
  <div class="card">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0">Staff Information</h5>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
          <i class="icon-base ri ri-add-line me-1"></i>
          Add Staff
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAgentModal">
          <i class="icon-base ri ri-add-line me-1"></i>
          Add Agent
        </button>
      </div>
    </div>
    <div class="card-datatable table-responsive">
      <table class="datatables-users table">
        <thead>
          <tr>
            <th></th>
            <th>S.No</th>
            <th>Name</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>

  <!-- Modal: Add Staff -->
  <div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-2">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold text-primary">Add New Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="{{ route('admin.staff.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf
            <div class="col-md-6">
              <div class="p-3 bg-label-primary rounded mb-3">
                <h6 class="fw-bold mb-2">Assignment Details</h6>
                <label class="form-label small">Branch Office <span class="text-danger">*</span></label>
                <select name="branch_id" class="form-select mb-3 shadow-sm" required>
                  <option value="" selected>Select Branch</option>
                  <option value="0">Main Office</option>
                  @foreach($branches as $branch) <option value="{{ $branch->id }}">{{ $branch->name }}</option> @endforeach
                </select>
                <label class="form-label small">System Role (Permissions) <span class="text-danger">*</span></label>
                <select name="role" class="form-select shadow-sm" required>
                  <option value="" selected>Select Role</option>
                  <option value="0">No Login Rights</option>
                  @foreach($roles as $role)
                    @if($role->name !== 'Agent' && $role->name !== 'Client')
                      <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endif
                  @endforeach
                </select>
              </div>
              <label class="form-label small fw-bold">Profile Picture</label>
              <input type="file" name="profile_photo" class="form-control form-control-sm mb-3 shadow-sm" accept="image/*" />
            </div>
            <div class="col-md-6 border-start ps-4">
              <h6 class="fw-bold mb-3 border-bottom pb-2">Personal Information</h6>
              <div class="mb-3">
                <label class="form-label small">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control shadow-sm" required pattern="[A-Za-z\s]+" title="Only alphabets and spaces are allowed" oninput="this.value=this.value.replace(/[^A-Za-z\s]/g,'')" />
              </div>
              <div class="mb-4">
                <label class="form-label" for="phone">Mobile Number <span class="text-danger">*</span></label>
                <input type="text" id="phone" class="form-control" name="phone" placeholder="10 Digit Mobile Number" required minlength="10" maxlength="10" pattern="[6-9]\d{9}" title="Please enter a valid 10-digit mobile number starting with 6-9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
              </div>
              <div class="mb-3">
                <label class="form-label small">Email Address</label>
                <input type="email" name="email" class="form-control shadow-sm" />
              </div>
              <div class="mb-3">
                <label class="form-label small">Base Monthly Salary <span class="text-danger">*</span></label>
                <div class="input-group input-group-merge shadow-sm">
                  <span class="input-group-text">₹</span>
                  <input type="number" name="salary_amount" class="form-control" required />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label small">Login Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control shadow-sm" placeholder="Enter password" required minlength="8" />
              </div>
              <div class="mb-3">
                <label class="form-label small">Confirm Login Password <span class="text-danger">*</span></label>
                <input type="password" name="password_confirmation" class="form-control shadow-sm" placeholder="Confirm password" required minlength="8" />
              </div>
            </div>
            <div class="col-12 text-end pt-3 border-top">
              <button type="reset" class="btn btn-label-secondary me-2">Clear</button>
              <button type="submit" class="btn btn-primary px-5 shadow">Save Profile</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Add Agent -->
  <div class="modal fade" id="addAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold text-success" id="modalTitle">Add New Agent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="agentUserForm">
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentUserName" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentUserName" name="name" placeholder="Enter agent name" required pattern="[A-Za-z\s]+" title="Only alphabets and spaces are allowed" oninput="this.value=this.value.replace(/[^A-Za-z\s]/g,'')">
                <div class="invalid-feedback" id="agentNameError"></div>
              </div>
              
              <div class="col-md-6 mb-3">
                <label for="agentUserEmail" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="agentUserEmail" name="email" placeholder="Enter email" required>
                <div class="invalid-feedback" id="agentEmailError"></div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentUserPhone" class="form-label">Phone <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentUserPhone" name="phone" placeholder="Enter phone number" maxlength="10" oninput="this.value=this.value.replace(/[^0-9]/g,'')" pattern="[0-9]{10}" inputmode="numeric" required>
                <div class="invalid-feedback" id="agentPhoneError"></div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="agentUserPincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentUserPincode" name="pincode" placeholder="Enter pincode" maxlength="6" pattern="[0-9]*" inputmode="numeric" required>
                <div class="invalid-feedback" id="agentPincodeError"></div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-12 mb-3">
                <label for="agentUserLocation" class="form-label">Location <span class="text-danger">*</span></label>
                <select class="form-select" id="agentUserLocation" name="location_id" required>
                  <option value="" selected disabled>Select location</option>
                  @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" data-city="{{ $loc->city }}" data-state="{{ $loc->state }}" data-pincode="{{ $loc->pincode }}">{{ $loc->name }}, {{ $loc->city }}, {{ $loc->state }}</option>
                  @endforeach
                </select>
                <div class="invalid-feedback" id="agentLocationError"></div>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="agentUserAddress" class="form-label">Address <span class="text-danger">*</span></label>
              <textarea class="form-control" id="agentUserAddress" name="address" rows="2" placeholder="Enter address" required></textarea>
              <div class="invalid-feedback" id="agentAddressError"></div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentUserCity" class="form-label">City <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentUserCity" name="city" placeholder="Enter city" oninput="this.value=this.value.replace(/[^a-zA-Z\s.]/g,'')" required>
                <div class="invalid-feedback" id="agentCityError"></div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="agentUserState" class="form-label">State <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="agentUserState" name="state" placeholder="Enter state" oninput="this.value=this.value.replace(/[^a-zA-Z\s.]/g,'')" required>
                <div class="invalid-feedback" id="agentStateError"></div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="agentUserPassword" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="agentUserPassword" name="password" placeholder="Enter password" required minlength="8">
                <div class="invalid-feedback" id="agentPasswordError"></div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="agentUserConfirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="agentUserConfirmPassword" name="confirm_password" placeholder="Confirm password" required minlength="8">
                <div class="invalid-feedback" id="agentConfirmPasswordError"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top pt-3">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success px-5 shadow" id="agentSubmitBtn">Save Agent</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit User Modal (Used for editing existing users) -->
  <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold text-primary" id="modalTitle">Edit User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="userForm">
          <div class="modal-body">
            <input type="hidden" id="userId" name="user_id">
            
            <div class="mb-3">
              <label for="userName" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="userName" name="name" placeholder="Enter name" required>
              <div class="invalid-feedback" id="nameError"></div>
            </div>
            
            <div class="mb-3">
              <label for="userEmail" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="userEmail" name="email" placeholder="Enter email" required>
              <div class="invalid-feedback" id="emailError"></div>
            </div>
            
            <div class="mb-3">
              <label for="userPhone" class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="userPhone" name="phone" placeholder="Enter phone number" required>
              <div class="invalid-feedback" id="phoneError"></div>
            </div>

            <div class="mb-3" id="passwordField">
              <label for="userPassword" class="form-label">New Password <small class="text-muted">(Leave empty to keep current)</small></label>
              <input type="password" class="form-control" id="userPassword" name="password" placeholder="Enter new password">
              <div class="invalid-feedback" id="passwordError"></div>
            </div>
            
            <div class="mb-3" id="confirmPasswordField">
              <label for="userConfirmPassword" class="form-label">Confirm New Password</label>
              <input type="password" class="form-control" id="userConfirmPassword" name="confirm_password" placeholder="Confirm new password">
              <div class="invalid-feedback" id="confirmPasswordError"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="submitBtn">
              <i class="icon-base ri ri-save-line me-1"></i>Update User
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="icon-base ri ri-error-warning-line me-2 text-danger"></i>Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
          <p class="text-muted mb-0 mt-2"><small>This action cannot be undone.</small></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
            <i class="icon-base ri ri-delete-bin-line me-1"></i>Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Assign Role Modal -->
  <div class="modal fade" id="assignRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Role</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="assignRoleForm">
          <div class="modal-body">
            <input type="hidden" id="assignRoleUserId" name="user_id">
            <div class="mb-4">
              <label class="form-label" for="roleSelect">Select Role <span class="text-danger">*</span></label>
              <select id="roleSelect" name="role" class="form-select" required>
                <option value="" disabled selected>Select a role</option>
                @foreach($roles as $role)
                  <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                @endforeach
              </select>
            </div>
            <p class="text-muted small">The user will have permissions based on the selected role.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="assignRoleSubmitBtn">
              <i class="icon-base ri ri-save-line me-1"></i>Save Role
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
