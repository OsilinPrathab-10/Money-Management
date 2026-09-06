<!-- Edit User Modal -->
<div class="modal fade" id="editUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-user">
    <div class="modal-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-6">
          <h4 class="mb-2">Edit User Information</h4>
          <p class="mb-6">Updating user details will receive a privacy audit.</p>
        </div>
        <form id="editUserForm" class="row g-5" onsubmit="return false">
          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditMobileNumber" name="modalEditMobileNumber" class="form-control"
                value="+91 9876543210" placeholder="+91 9876543210" />
              <label for="modalEditMobileNumber">Mobile Number</label>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditAlternateMobile" name="modalEditAlternateMobile" class="form-control"
                value="+91 9876543211" placeholder="+91 9876543211" />
              <label for="modalEditAlternateMobile">Alternate Mobile</label>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditDOB" name="modalEditDOB" class="form-control"
                value="15 Jan 1990" placeholder="15 Jan 1990" />
              <label for="modalEditDOB">Date of Birth</label>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <select id="modalEditGender" name="modalEditGender" class="form-select">
                <option value="" disabled selected>Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
              <label for="modalEditGender">Gender</label>
            </div>
          </div>
          <div class="col-12">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditLeadBy" name="modalEditLeadBy" class="form-control"
                value="John Smith" placeholder="John Smith" />
              <label for="modalEditLeadBy">Lead By</label>
            </div>
          </div>
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-3">Submit</button>
            <button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="modal"
              aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit User Modal -->
