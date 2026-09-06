<!-- BEGIN: Vendor JS-->

@vite(['resources/assets/vendor/libs/jquery/jquery.js', 'resources/assets/vendor/libs/popper/popper.js', 'resources/assets/vendor/js/bootstrap.js', 'resources/assets/vendor/libs/node-waves/node-waves.js', 'resources/assets/vendor/libs/@algolia/autocomplete-js.js'])

@if ($configData['hasCustomizer'])
  @vite('resources/assets/vendor/libs/pickr/pickr.js')
@endif

@vite(['resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js', 'resources/assets/vendor/libs/hammer/hammer.js', 'resources/assets/vendor/js/menu.js'])

@yield('vendor-script')
<!-- END: Page Vendor JS-->

<!-- BEGIN: Theme JS-->
@vite(['resources/assets/js/main.js'])
<!-- END: Theme JS-->

<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->

<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->

<!-- app JS -->
@vite(['resources/assets/custom-js/app.js'])
<!-- END: app JS-->

@if(auth()->check() && (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff')))
  <!-- Admin Notifications JS -->
  @vite(['resources/assets/js/admin-notifications.js'])
  <!-- END: Admin Notifications JS -->
@endif

@stack('footer-scripts')

{{-- ============================================================
     GLOBAL: SweetAlert2 — loads from CDN if not already available,
     then handles delete confirmations and session flash alerts.
     ============================================================ --}}
<script>
(function () {
    function initSwalHandlers() {

        // ── Delete confirmation ──────────────────────────────────────
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.swal-delete-btn');
            if (!btn) return;
            e.preventDefault();

            const formId  = btn.dataset.formId;
            const message = btn.dataset.confirm || 'This action cannot be undone.';
            const form    = document.getElementById(formId);
            if (!form) return;

            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                iconColor: '#ff3e1d',
                showCancelButton: true,
                confirmButtonColor: '#ff3e1d',
                cancelButtonColor: '#8592a3',
                confirmButtonText: '<i class="ri-delete-bin-6-line me-1"></i> Yes, Delete!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'swal2-delete-popup',
                    confirmButton: 'btn btn-danger px-4',
                    cancelButton: 'btn btn-secondary px-4 me-2'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    btn.disabled = true;
                    Swal.fire({
                        title: 'Deleting…',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    form.submit();
                }
            });
        });

        // ── Session flash via SweetAlert2 ───────────────────────────
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: {!! json_encode(session('success')) !!},
                timer: 2500,
                showConfirmButton: false,
                position: 'center'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: {!! json_encode(session('error')) !!},
                showConfirmButton: true,
                position: 'center'
            });
        @endif

        @if(isset($errors) && $errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Validation Failed',
                html: '<ul class="text-start mb-0 ps-3">' +
                    @foreach($errors->all() as $error)
                    '<li>{!! addslashes($error) !!}</li>' +
                    @endforeach
                '',
                showConfirmButton: true,
                confirmButtonColor: '#ff3e1d',
                position: 'center'
            });
        @endif
    }

    // Load SweetAlert2 from CDN if not already loaded by the page
    function loadSwalThenInit() {
        if (typeof Swal !== 'undefined') {
            initSwalHandlers();
            return;
        }
        // Inject CDN stylesheet
        var link = document.createElement('link');
        link.rel  = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
        document.head.appendChild(link);
        // Inject CDN script then initialise
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js';
        script.onload = initSwalHandlers;
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSwalThenInit);
    } else {
        loadSwalThenInit();
    }
})();
</script>

<script>
// Live Clock and Date Widget
(function() {
  function updateClock() {
    const now = new Date();

    // Format time (12-hour format with AM/PM)
    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    const hoursStr = String(hours).padStart(2, '0');
    const timeString = `${hoursStr}:${minutes}:${seconds} ${ampm}`;

    // Format date (DD Month YYYY) - without day name
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const dateString = now.toLocaleDateString('en-US', options);

    // Update DOM elements
    const timeElement = document.getElementById('live-time');
    const dateElement = document.getElementById('live-date');

    if (timeElement) {
      timeElement.textContent = timeString;
    }

    if (dateElement) {
      dateElement.textContent = dateString;
    }
  }

  // Update immediately when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateClock);
  } else {
    updateClock();
  }

  // Update every second
  setInterval(updateClock, 1000);
})();
</script>

