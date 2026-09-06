<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-simple">
    <div class="modal-content">
      <div class="modal-body p-0">

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

        <div class="text-center mb-4">
          <h4 class="role-title">Add New Role</h4>
          <p>Assign permissions to this role</p>
        </div>

        <!-- Add Role Form -->
        <form id="addRoleForm" data-url="{{ route('roles.store') }}">
          @csrf
          <div class="mb-4">
            <div class="form-floating form-floating-outline">
              <input type="text" id="roleName" name="name" class="form-control"
                     placeholder="Enter role name" required>
              <label for="roleName">Role Name</label>
              <small class="text-danger d-none" id="error-name"></small>
            </div>
          </div>

          <h5 class="mb-3">Role Permissions</h5>

          <!-- Select All -->
          <div class="mb-3 d-flex justify-content-between border-bottom pb-2">
            <strong>Administrator Access</strong>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="selectAllPermissions">
              <label for="selectAllPermissions" class="form-check-label">Select All</label>
            </div>
          </div>

          <!-- Dynamic Permission Groups -->
          <div class="table-responsive">
            <table class="table table-flush-spacing">
              <tbody>

                <!-- Example For Loop -->
                <!-- Replace with dynamic permissions from backend -->
                @foreach ($permissionGroups as $groupName => $permissions)
                  <tr>
                    <td class="fw-medium text-nowrap">
                      {{ ucfirst($groupName) }}
                    </td>
                    <td>
                      <div class="d-flex justify-content-end gap-4">

                        @foreach ($permissions as $permission)
                          <div class="form-check">
                            <input class="form-check-input permission-checkbox"
                                   type="checkbox"
                                   name="permissions[]"
                                   value="{{ $permission->name }}"
                                   data-group="{{ $groupName }}">
                            <label class="form-check-label">
                              {{ ucfirst(str_replace("$groupName.", "", $permission->name)) }}
                            </label>
                          </div>
                        @endforeach

                      </div>
                    </td>
                  </tr>
                @endforeach

              </tbody>
            </table>
          </div>

          <div class="text-center mt-4 pb-3">
            <button type="submit" class="btn btn-primary me-2">Submit</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              Cancel
            </button>
          </div>
        </form>
        <!-- / Add Role Form -->

      </div>
    </div>
  </div>
</div>
<!-- / Add Role Modal -->

<!-- Modal + Validation + AJAX -->
<script>
document.addEventListener("DOMContentLoaded", function(){

    // Select All
    $("#selectAllPermissions").on("change", function () {
        $(".permission-checkbox").prop("checked", $(this).is(":checked"));
    });

    // Uncheck Select All if one unchecked
    $(document).on("change", ".permission-checkbox", function () {
        if (!$(this).is(":checked")) {
            $("#selectAllPermissions").prop("checked", false);
        }
    });

    // AJAX Submit
    $("#addRoleForm").on("submit", function (e) {
        e.preventDefault();

        let formData = $(this).serialize();
        let url = $(this).data("url");

        $("#roleName").removeClass("is-invalid");
        $("#error-name").text("").addClass("d-none");

        $.ajax({
            url: url,
            method: "POST",
            data: formData,
            success: function (response) {
                if (response.success) {

                    $("#addRoleModal").modal("hide");
                    $("#addRoleForm")[0].reset();

                    // Redirect to SAME page with success message
                    let redirectUrl = window.location.pathname + "?success=" + encodeURIComponent(response.message);
                    window.location.href = redirectUrl;
                }
            },
            error: function (xhr) {
                // Clear old errors
                $("#error-name").text("").addClass("d-none");

                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;

                    if (errors.name) {
                        $("#error-name").text(errors.name[0]).removeClass("d-none");
                        $("#roleName").addClass("is-invalid");
                    }

                    toastr.error("Please fix the validation errors.");
                } else {
                    toastr.error("Something went wrong");
                }
            }
        });
    });

    $(document).ready(function() {
        $('#addRoleModal').on('hidden.bs.modal', function () {
            $('#addRoleForm')[0].reset();
            $('.permission-checkbox').prop('checked', false);
            $('#selectAllPermissions').prop('checked', false);
        });

    })

});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.location.search.includes("success=")) {
        const cleanUrl = window.location.origin + window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }
});
</script>
