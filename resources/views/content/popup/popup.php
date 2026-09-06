<!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCenterTitle">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-delete-bin-6-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center ">Do you really want to delete "<strong id="deletePolicyName"></strong>"?</p>
         
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
            <i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-body text-center p-4">
          <div class="mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle" style="width: 50px; height: 50px;">
              <i class="icon-base ri ri-check-line text-success" style="font-size: 100px;"></i>
            </div>
          </div>
          <h5 class="mb-2">Deleted Successfully!</h5>
          <p class="text-muted mb-0" id="successMessage">Policy page has been deleted successfully.</p>
        </div>
        <div class="modal-footer justify-content-center border-0 pt-0">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>