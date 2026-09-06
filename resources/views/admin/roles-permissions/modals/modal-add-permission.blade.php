<!-- Add Permission Modal -->
<div class="modal fade" id="addPermissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-simple">
    <div class="modal-content p-4 p-md-12">
      <div class="modal-body p-md-0">

        <!-- Close button -->
        <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Close"></button>

        <!-- Title -->
        <div class="text-center mb-6">
          <h3 class="mb-2 pb-1">Add New Permission</h3>
          <p>Permissions you may use and assign to your users.</p>
        </div>

        <!-- Add Permission Form -->
        <form id="addPermission" data-url="{{ route('permissions.store') }}" novalidate>
          @csrf

          <!-- Permission Field -->
          <div class="col-12 form-control-validation mb-4">
            <div class="form-floating form-floating-outline">
              <input
                  type="text"
                  id="permissionName"
                  name="name"
                  class="form-control"
                  placeholder="Permission Name"
                  autofocus>
              <label for="permissionName">Permission Name</label>

              <small id="permission-error" class="text-danger d-none"></small>
            </div>
          </div>

          <div class="col-12 text-center demo-vertical-spacing">
            <button type="submit" class="btn btn-primary me-sm-4 me-1">Create Permission</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Discard</button>
          </div>
        </form>
        <!-- / Add Permission Form -->

      </div>
    </div>
  </div>
</div>
<!-- / Add Permission Modal -->

<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
// Wait for jQuery to be available
function initPermissionModal() {
    if (typeof $ === 'undefined') {
        console.warn('jQuery not loaded yet. Retrying in 100ms...');
        setTimeout(initPermissionModal, 100);
        return;
    }

    $(document).ready(function () {

        // RESET MODAL WHEN CLOSED
        $("#addPermissionModal").on("hidden.bs.modal", function () {
            $("#addPermission")[0].reset();
            $("#permission-error").addClass("d-none").text("");
            $("#permissionName").removeClass("is-invalid");
        });

        // AJAX SUBMIT (using delegated binding for reliability)
        $(document).on("submit", "#addPermission", function (e) {
            e.preventDefault();
            e.stopPropagation();

            let $form = $(this);
            let url = $form.data("url");
            let token = $('meta[name="csrf-token"]').attr("content");

            let name = $("#permissionName").val();

            // RESET ERRORS
            $("#permission-error").addClass("d-none").text("");
            $("#permissionName").removeClass("is-invalid");

            $.ajax({
                url: url,
                method: "POST",
                data: {
                    name: name,
                    _token: token
                },
                success: function (response) {
                    // Show toast only if toastr is available
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message);
                    }

                    $("#addPermissionModal").modal("hide");
                    $("#addPermission")[0].reset();

                    // Redirect to the URL provided by the server
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 500);
                    }
                },
                error: function (xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;

                        if (errors.name) {
                            $("#permission-error").text(errors.name[0]).removeClass("d-none");
                            $("#permissionName").addClass("is-invalid");
                        }

                        if (typeof toastr !== 'undefined') {
                            toastr.error("Please fix the errors.");
                        }
                    } else {
                        if (typeof toastr !== 'undefined') {
                            toastr.error("Something went wrong!");
                        }
                    }
                }
            });

            return false;
        });

    });
}

// Initialize when jQuery is ready
initPermissionModal();
</script>
