@extends('layouts/layoutMaster')

@section('title', 'Location Management')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/location-management.js'])
@endsection

@section('content')

<!-- Statistics Cards -->
<div class="row g-6 mb-6">
  <div class="col-sm-6 col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading fw-medium">Total Zones</span>
            <div class="d-flex align-items-center my-1">
              <h3 class="mb-0 me-2 fw-bold text-primary">{{ $totalLocations }}</h3>
            </div>
            <small class="text-muted">Stored mapping</small>
          </div>
          <div class="avatar bg-label-primary rounded p-2">
            <i class="ri-map-pin-2-line ri-24px"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading fw-medium">Local Districts</span>
            <div class="d-flex align-items-center my-1">
              <h3 class="mb-0 me-2 fw-bold text-success">{{ $totalDistricts ?? 0 }}</h3>
            </div>
            <small class="text-muted">Normalized data</small>
          </div>
          <div class="avatar bg-label-success rounded p-2">
            <i class="ri-building-line ri-24px"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading fw-medium">Local States</span>
            <div class="d-flex align-items-center my-1">
              <h3 class="mb-0 me-2 fw-bold text-warning">{{ $totalStates ?? 0 }}</h3>
            </div>
            <small class="text-muted">Root entities</small>
          </div>
          <div class="avatar bg-label-warning rounded p-2">
            <i class="ri-government-line ri-24px"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tabbed Location Management -->
<div class="nav-align-top mb-6">
  <ul class="nav nav-tabs nav-fill shadow-sm rounded-top" role="tablist">
    <li class="nav-item">
      <button type="button" class="nav-link active fw-bold py-3" role="tab" data-bs-toggle="tab" data-bs-target="#navs-villages" aria-controls="navs-villages" aria-selected="true">
        <i class="ri-map-pin-range-line me-1"></i> Zones / Areas
      </button>
    </li>
    <li class="nav-item">
      <button type="button" class="nav-link fw-bold py-3" role="tab" data-bs-toggle="tab" data-bs-target="#navs-districts" aria-controls="navs-districts" aria-selected="false">
        <i class="ri-community-line me-1"></i> Districts
      </button>
    </li>
    <li class="nav-item">
      <button type="button" class="nav-link fw-bold py-3" role="tab" data-bs-toggle="tab" data-bs-target="#navs-states" aria-controls="navs-states" aria-selected="false">
        <i class="ri-map-2-line me-1"></i> States
      </button>
    </li>
  </ul>
  <div class="tab-content border-0 p-0 mt-3 shadow-none">
    <!-- Villages Tab -->
    <div class="tab-pane fade show active" id="navs-villages" role="tabpanel">
        <div class="card shadow-sm border-0">
          <div class="card-header border-bottom bg-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                 <h5 class="card-title mb-0 fw-bold">Zone List</h5>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manualAddLocationModal">
                  <i class="ri-add-line me-1"></i> Add Zone
                </button>
                <!-- <button class="btn btn-primary shadow" data-bs-toggle="modal" data-bs-target="#fetchLocationModal">
                  <i class="ri-download-cloud-2-line me-1"></i> Fetch from API
                </button> -->
              </div>
            </div>
          </div>
          <div class="card-datatable table-responsive">
            <table class="datatables-locations table table-hover border-top">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Zone/Area</th>
                  <th>District</th>
                  <th>State</th>
                  <th>Pincode</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
    </div>

    <!-- Districts Tab -->
    <div class="tab-pane fade" id="navs-districts" role="tabpanel">
        <div class="card shadow-sm border-0">
          <div class="card-header border-bottom bg-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                 <h5 class="card-title mb-0 fw-bold">District Mapping</h5>
              </div>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDistrictModal">
                <i class="ri-add-line me-1"></i> Add District
              </button>
            </div>
          </div>
          <div class="card-datatable table-responsive">
            <table class="datatables-districts table table-hover border-top">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>District Name</th>
                  <th>State</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
    </div>

    <!-- States Tab -->
    <div class="tab-pane fade" id="navs-states" role="tabpanel">
        <div class="card shadow-sm border-0">
          <div class="card-header border-bottom bg-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                 <h5 class="card-title mb-0 fw-bold">Root States</h5>
              </div>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStateModal">
                <i class="ri-add-line me-1"></i> Add State
              </button>
            </div>
          </div>
          <div class="card-datatable table-responsive">
            <table class="datatables-states table table-hover border-top">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>State Name</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
    </div>
  </div>
</div>

<!-- Modal: Add Village (Manual) -->
<div class="modal fade" id="manualAddLocationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="manualAddLocationForm">
        <div class="modal-header">
          <h5 class="modal-title fw-bold text-primary">Add New Zone</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Zone Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="Enter Zone or area name" required pattern="^[A-Za-z\s\0-9\.-]+$" title="Use alphabets/alphanumeric characters only">
            </div>
            <div class="col-md-6">
              <label class="form-label">State <span class="text-danger">*</span></label>
              <select name="state_id" id="manualStateSelect" class="form-select select2-local" required>
                <option value="" disabled selected>Select State</option>
                @foreach($states as $state)
                  <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between">
                <label class="form-label">District <span class="text-danger">*</span></label>
                <a href="javascript:void(0);" class="small fw-medium" data-bs-toggle="modal" data-bs-target="#addDistrictModal">Not listed?</a>
              </div>
              <select name="district_id" id="manualDistrictSelect" class="form-select select2-local" required disabled>
                <option value="" disabled selected>Select State First</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Pincode</label>
              <input type="text" name="pincode" class="form-control" maxlength="6" placeholder="6-digit pincode">
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4" id="btnManualSave">Save Zone</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Add District -->
<div class="modal fade" id="addDistrictModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addDistrictForm">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add New District</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">District Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Coimbatore" required>
            </div>
            <div class="col-12">
              <label class="form-label">State <span class="text-danger">*</span></label>
              <select name="state_id" class="form-select select2-local" required>
                <option value="" disabled selected>Select State</option>
                @foreach($states as $state)
                  <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnDistrictSave">Save District</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit District -->
<div class="modal fade" id="editDistrictModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editDistrictForm">
        <input type="hidden" name="id" id="editDistrictId">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Edit District</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">District Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="editDistrictName" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">State <span class="text-danger">*</span></label>
              <select name="state_id" id="editDistrictStateId" class="form-select select2-local" required>
                <option value="" disabled>Select State</option>
                @foreach($states as $state)
                  <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnDistrictUpdate">Update District</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal: Add State -->
<div class="modal fade" id="addStateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addStateForm">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add New State</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="col-12">
            <label class="form-label">State Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. TAMIL NADU">
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4" id="btnStateSave">Save State</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit State -->
<div class="modal fade" id="editStateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editStateForm">
        <input type="hidden" name="id" id="editStateId">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Edit State</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="col-12">
            <label class="form-label">State Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="editStateName" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4" id="btnStateUpdate">Update State</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Edit Village Modal -->
<div class="modal fade" id="editVillageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form id="editVillageForm">
        <input type="hidden" name="id" id="editVillageId">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold">Edit Zone</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">State <span class="text-danger">*</span></label>
              <select name="state_id" id="editVillageState" class="form-select" required>
                @foreach (\App\Models\State::orderBy('name')->get() as $state)
                  <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">District <span class="text-danger">*</span></label>
              <select name="district_id" id="editVillageDistrict" class="form-select" required>
                 <!-- Loaded via JS -->
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Zone Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="editVillageName" class="form-control" required placeholder="Enter village name" pattern="^[A-Za-z0-9\s.-]+$" title="Use alphabets/alphanumeric characters only">
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary px-4" id="btnVillageUpdate">Update Zone</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Fetch API Modal -->
<div class="modal fade" id="fetchLocationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="fetchLocationForm">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold text-dark">Sync with Central DB</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
             <div class="col-12">
                <label class="form-label">State Name (API) <span class="text-danger">*</span></label>
                <select id="fetchState" name="state" class="form-select select2-api" required>
                    <option value="" disabled selected>Loading states...</option>
                </select>
             </div>
             <div class="col-12">
                <label class="form-label">District Name (API) <span class="text-danger">*</span></label>
                <select id="fetchDistrict" name="district" class="form-select select2-api" required disabled>
                    <option value="" disabled selected>Select State first</option>
                </select>
             </div>
          </div>
          <div class="alert alert-info mt-4 mb-0" role="alert">
            <div class="d-flex">
              <i class="ri-information-line me-2"></i>
              <span>Fetched data will automatically create local States and Districts if they don't exist.</span>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary px-4" id="btnFetch">Start Sync</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLocationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <b id="deleteLocationName"></b>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

@endsection
