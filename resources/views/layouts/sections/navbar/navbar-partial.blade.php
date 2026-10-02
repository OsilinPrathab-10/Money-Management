@php
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Facades\Route;

  $isClientUser = Auth::check() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('Client');
  $showStaffNotifications = Auth::check() && method_exists(Auth::user(), 'hasRole') && (
    Auth::user()->hasRole('Admin') || Auth::user()->hasRole('Staff') || Auth::user()->hasRole('Agent')
  );
  $showLayoutCustomizer = ($configData['hasCustomizer'] ?? false) && Auth::check() && !$isClientUser;
@endphp

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
@if (isset($navbarFull))
  <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-6">
    <a href="{{ url('/') }}" class="app-brand-link gap-2">
      <span class="app-brand-logo demo">@include('_partials.macros')</span>
      <span class="app-brand-text demo menu-text fw-semibold ms-1">{{ config('variables.templateName') }}</span>
    </a>

    <!-- Display menu close icon only for horizontal-menu with navbar-full -->
    @if (isset($menuHorizontal))
      <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
        <i class="icon-base ri ri-close-line icon-sm"></i>
      </a>
    @endif
  </div>
@endif

<!-- ! Not required for layout-without-menu -->
@if (!isset($navbarHideToggle))
  <div
    class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 {{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
      <i class="icon-base ri ri-menu-line icon-md"></i>
    </a>
  </div>
@endif

<div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">

  @if (!isset($menuHorizontal) && !$isClientUser)
    <!-- Search -->
    <div class="navbar-nav align-items-center">
      <div class="nav-item navbar-search-wrapper mb-0">
        <a class="nav-item nav-link search-toggler px-0" href="javascript:void(0);">
          <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
        </a>
      </div>
    </div>
    <!-- /Search -->
  @endif

  <ul class="navbar-nav flex-row align-items-center ms-md-auto">
    @if (isset($menuHorizontal))
      <!-- Search -->
      <li class="nav-item navbar-search-wrapper mb-0">
        <a class="nav-item nav-link search-toggler px-0" href="javascript:void(0);">
          <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
        </a>
      </li>
      <!-- /Search -->
    @endif


    @auth
      <!-- Theme / scheme settings -->
      <li class="nav-item dropdown me-sm-2 me-xl-0">
        <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
          id="nav-theme"
          href="javascript:void(0);"
          data-bs-toggle="dropdown"
          data-bs-auto-close="outside"
          title="{{ __('Theme & Settings') }}"
          aria-label="{{ __('Theme & Settings') }}">
          <i class="icon-base ri ri-settings-3-line icon-22px"></i>
          <i class="d-none theme-icon-active ri ri-sun-line"></i>
          <span class="d-none ms-2" id="nav-theme-text">{{ __('Theme') }}</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text" style="min-width: 220px;">
          <li class="dropdown-menu-header border-bottom mb-1">
            <div class="dropdown-header py-2">
              <h6 class="mb-0"><i class="ri-palette-line me-1"></i> {{ __('Color Scheme') }}</h6>
              <small class="text-muted">{{ __('Choose your display mode') }}</small>
            </div>
          </li>
          <li>
            <button type="button" class="dropdown-item align-items-center active" data-bs-theme-value="light" aria-pressed="false">
              <span><i class="icon-base ri ri-sun-line icon-22px me-3" data-icon="sun-line"></i>{{ __('Light') }}</span>
            </button>
          </li>
          <li>
            <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="dark" aria-pressed="true">
              <span><i class="icon-base ri ri-moon-clear-line icon-22px me-3" data-icon="moon-clear-line"></i>{{ __('Dark') }}</span>
            </button>
          </li>
          <li>
            <button type="button" class="dropdown-item align-items-center" data-bs-theme-value="system" aria-pressed="false">
              <span><i class="icon-base ri ri-computer-line icon-22px me-3" data-icon="computer-line"></i>{{ __('System') }}</span>
            </button>
          </li>
          @if ($showLayoutCustomizer)
          <li><hr class="dropdown-divider my-1"></li>
          <li class="dropdown-menu-header border-bottom mb-1">
            <div class="dropdown-header py-2">
              <h6 class="mb-0"><i class="ri-layout-grid-line me-1"></i> {{ __('Layout & Menu') }}</h6>
              <small class="text-muted">{{ __('Sidebar, navbar, and content layout') }}</small>
            </div>
          </li>
          <li>
            <button type="button" class="dropdown-item align-items-center" id="btnOpenLayoutCustomizerFromMenu">
              <span><i class="icon-base ri ri-equalizer-line icon-22px me-3"></i>{{ __('Customize Layout') }}</span>
            </button>
          </li>
          @endif
          @if (Auth::user()->hasRole('Admin'))
          <li><hr class="dropdown-divider my-1"></li>
          <li>
            <a class="dropdown-item" href="{{ route('website-appearance') }}">
              <i class="icon-base ri ri-brush-2-line icon-22px me-3"></i>{{ __('Appearance Settings') }}
            </a>
          </li>
          @endif
        </ul>
      </li>
    @endauth

    @if ($showLayoutCustomizer)
    <!-- Layout / menu customizer -->
    <li class="nav-item me-sm-2 me-xl-0">
      <button type="button"
        class="nav-link btn btn-icon btn-text-secondary rounded-pill border-0 bg-transparent"
        id="btnOpenLayoutCustomizer"
        title="{{ __('Layout & Menu') }}"
        aria-label="{{ __('Layout & Menu') }}">
        <i class="icon-base ri ri-layout-grid-line icon-22px"></i>
      </button>
    </li>
    @endif

    {{-- Clock and Date Widget --}}
    <li class="nav-item me-3 d-none d-lg-flex align-items-center">
      <div class="d-flex flex-column text-end" style="line-height: 1.3;">
        <span id="live-time" class="fw-semibold text-primary" style="font-size: 1rem; font-family: 'Courier New', monospace; letter-spacing: 0.5px;">00:00:00 AM</span>
        <span id="live-date" class="text-muted" style="font-size: 0.75rem;">Loading...</span>
      </div>
    </li>

    @if (Auth::check() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('Admin'))
    <!-- Clear application cache -->
    <li class="nav-item me-2 me-xl-1 d-none d-md-block">
      <button type="button" class="nav-link btn btn-icon btn-text-secondary rounded-pill border-0 bg-transparent" id="btnOpenCacheClearModal" title="{{ __('Clear cache') }}" aria-label="{{ __('Clear cache') }}" aria-haspopup="dialog">
        <i class="icon-base ri ri-brush-line icon-22px"></i>
      </button>
    </li>
    @endif

    <!-- Quick links  -->
    @unless(Auth::check() && (Auth::user()->hasRole('CreditVerifier') || Auth::user()->hasRole('Client')))
    <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-sm-2 me-xl-0">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
        href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="icon-base ri ri-star-smile-line icon-22px"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-end p-0">
        <div class="dropdown-menu-header border-bottom">
          <div class="dropdown-header d-flex align-items-center py-3">
            <h6 class="mb-0 me-auto">Shortcuts</h6>
          </div>
        </div>
        <div class="dropdown-shortcuts-list scrollable-container">
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ri ri-home-smile-line icon-26px text-heading"></i>
              </span>
              <a href="{{ url('dashboard') }}" class="stretched-link">Dashboard</a>
              <small>Overview</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ri ri-file-list-3-line icon-26px text-heading"></i>
              </span>
              <a href="{{ url('loan/loan-applications') }}" class="stretched-link">Loan Applications</a>
              <small>Manage Applications</small>
            </div>
          </div>
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ri ri-hand-coin-line icon-26px text-heading"></i>
              </span>
              <a href="{{ url('loan/loan-products') }}" class="stretched-link">Loan Products</a>
              <small>Product Catalog</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ri ri-settings-5-line icon-26px text-heading"></i>
              </span>
              <a href="{{ url('loan/loan-types') }}" class="stretched-link">Loan Types</a>
              <small>Configuration</small>
            </div>
          </div>
          <div class="row row-bordered overflow-visible g-0">
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ri ri-group-line icon-26px text-heading"></i>
              </span>
              <a href="{{ url('client-management') }}" class="stretched-link">Client Management</a>
              <small>Manage Clients</small>
            </div>
            <div class="dropdown-shortcuts-item col">
              <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                <i class="icon-base ri ri-shield-user-line icon-26px text-heading"></i>
              </span>
              <a href="{{ url('roles') }}" class="stretched-link">Roles & Permissions</a>
              <small>Access Control</small>
            </div>
          </div>
        </div>
      </div>
    </li>
    @endunless
    <!-- Quick links -->

    <!-- Notification -->
    <style>
      @keyframes pulse {
        0% {
          transform: scale(1);
          opacity: 0.9;
        }
        70% {
          transform: scale(1.8);
          opacity: 0;
        }
        100% {
          transform: scale(1.8);
          opacity: 0;
        }
      }
      .notification-bell {
        position: relative;
        padding: 0.5rem;
      }
      .notification-badge {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 10px;
        height: 10px;
        background-color: var(--bs-primary);
        border-radius: 50%;
        border: 2px solid var(--bs-body-bg);
      }
      .notification-pulse {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 10px;
        height: 10px;
        background-color: var(--bs-primary);
        border-radius: 50%;
        border: 2px solid var(--bs-body-bg);
        animation: pulse 1.5s infinite;
        opacity: 0;
      }
    </style>
    @if ($showStaffNotifications)
    <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-4 me-xl-1">
      <a class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill notification-bell"
        href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="notificationDropdown">
        <i class="icon-base ri ri-notification-2-line icon-22px"></i>
        <span class="notification-badge" id="notificationBadge" style="display: none;"></span>
        <span class="notification-pulse" id="notificationPulse" style="display: none;"></span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end py-0" style="max-width: 380px; min-width: 320px;">
        <li class="dropdown-menu-header border-bottom py-50">
          <div class="dropdown-header d-flex align-items-center justify-content-between py-2">
            <h6 class="mb-0 me-auto">Notifications</h6>
            <span class="badge rounded-pill bg-label-primary" id="notificationCount">0</span>
            <a href="javascript:void(0);" class="dropdown-notifications-all text-body ms-2" id="markAllRead" data-bs-toggle="tooltip" title="Mark all as read">
              <i class="icon-base ri ri-check-double-line icon-20px"></i>
            </a>
            <a href="javascript:void(0);" class="dropdown-notifications-all text-body ms-2" id="clearAllNotifications" data-bs-toggle="tooltip" title="Clear all notifications">
              <i class="icon-base ri ri-delete-bin-line icon-20px text-danger"></i>
            </a>
          </div>
        </li>
        <li>
          <div class="scrollable-container" style="max-height: 400px; overflow-y: auto;" id="notificationList">
            <div class="py-5 px-5 text-center text-body-secondary">
              <span class="d-flex justify-content-center align-items-center mb-3">
                <span class="avatar-initial rounded-circle bg-label-secondary"><i class="icon-base ri ri-notification-off-line icon-22px"></i></span>
              </span>
              <p class="mb-0">No notifications yet</p>
            </div>
          </div>
        </li>
        <li class="border-top">
          <div class="d-grid p-3">
            <a class="btn btn-sm btn-primary d-flex align-items-center justify-content-center" href="{{ route('admin-notifications') }}">
              <span>View All Notifications</span>
              <i class="icon-base ri ri-arrow-right-line icon-16px ms-1"></i>
            </a>
          </div>
        </li>
      </ul>
    </li>
    @endif
    <!--/ Notification -->
    
    <!-- User -->
    <li class="nav-item navbar-dropdown dropdown-user dropdown">
      <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
        <div class="avatar avatar-online">
          <img src="{{ Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/1.png') }}"
            alt="avatar" class="rounded-circle" />
        </div>
      </a>
      <ul class="dropdown-menu dropdown-menu-end mt-3 py-2">
        <li>
          <a class="dropdown-item"
            href="{{ Route::has('profile.index') ? route('profile.index') : (Route::has('profile.show') ? route('profile.show') : url('/profile')) }}">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0 me-2">
                <div class="avatar avatar-online">
                  <img src="{{ Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/1.png') }}"
                    alt="alt" class="w-px-40 h-auto rounded-circle" />
                </div>
              </div>
              <div class="flex-grow-1">
                <h6 class="mb-0 small">
                  @if (Auth::check())
                    {{ Auth::user()->name }}
                  @else
                    John Doe
                  @endif
                </h6>
                <small class="text-body-secondary">Admin</small>
              </div>
            </div>
          </a>
        </li>
        <li>
          <div class="dropdown-divider"></div>
        </li>
        @if (Auth::check())
          <li>
            <a class="dropdown-item" href="{{ Auth::user()->hasRole('Client') ? route('client.profile') : route('profile.index') }}">
              <i class="icon-base ri ri-user-3-line icon-22px me-2"></i>
              <span class="align-middle">My Profile</span>
            </a>
          </li>
        @endif
        <li>
          <div class="dropdown-divider my-1"></div>
        </li>
        @if (Auth::check())
          <li>
            <div class="d-grid px-4 pt-2 pb-1">
              @php
                $logoutRoute = auth()->user()->hasRole('Client') ? route('client.logout') : route('logout');
              @endphp
              <a class="btn btn-danger d-flex" href="{{ $logoutRoute }}"
                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <small class=" align-middle">Logout</small>
                <i class="icon-base ri ri-logout-box-r-line ms-2 icon-16px"></i>
              </a>
            </div>
          </li>
          <form method="POST" id="logout-form" action="{{ $logoutRoute }}">
            @csrf
          </form>
        @else
          <li>
            <div class="d-grid px-4 pt-2 pb-1">
              <a class="btn btn-danger d-flex"
                href="{{ Route::has('login') ? route('login') : url('auth/login-basic') }}">
                <small class="align-middle">Login</small>
                <i class="icon-base ri ri-logout-box-r-line ms-2 icon-16px"></i>
              </a>
            </div>
          </li>
        @endif
      </ul>
    </li>
    <!--/ User -->
  </ul>
</div>

@if (Auth::check() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('Admin'))
@push('modals')
<div class="modal fade" id="cacheClearModal" tabindex="-1" aria-hidden="true" aria-labelledby="cacheClearModalLabel" data-bs-backdrop="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cacheClearModalLabel">{{ __('Clear application cache') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">{{ __('This clears application caches the same way as :cmd (cache, config, route, view, event). Continue?', ['cmd' => 'php artisan optimize:clear']) }}</p>
        <div id="cacheClearResult" class="alert d-none mt-3 mb-0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-primary" id="btnRunCacheClear">{{ __('Clear now') }}</button>
      </div>
    </div>
  </div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11050">
  <div id="cacheClearToast" class="toast align-items-center border-0" role="alert" aria-live="polite" aria-atomic="true" data-bs-delay="6500">
    <div class="d-flex">
      <div class="toast-body" id="cacheClearToastBody"></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
@endpush
@endif

@if ($showStaffNotifications)
@push('modals')
{{-- Global Notification Detail Modal (shared across all pages) --}}
<div class="modal fade" id="notificationDetailModal" tabindex="-1" aria-labelledby="notificationDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" id="notifModalHeader" style="border-bottom: 3px solid #696cff;">
        <div class="d-flex align-items-center gap-3">
          <span class="avatar-initial rounded-circle" id="notifModalIcon" style="width:42px;height:42px;font-size:1.2rem;"></span>
          <div>
            <h6 class="modal-title mb-0" id="notificationDetailModalLabel">Notification Detail</h6>
            <small class="text-muted" id="notifModalType"></small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 py-4">
        <h5 class="fw-bold mb-2" id="notifModalTitle"></h5>
        <p class="text-body mb-3" id="notifModalMessage" style="white-space: pre-wrap; line-height: 1.7;"></p>
        <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-2">
          <small class="text-muted"><i class="ri-time-line me-1"></i><span id="notifModalTime"></span></small>
          <small class="text-muted" id="notifModalExactTime"></small>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endpush
@endif

@if ($showLayoutCustomizer)
@push('footer-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  function openLayoutCustomizer(e) {
    if (e) e.preventDefault();
    var openBtn = document.querySelector('.template-customizer-open-btn');
    if (openBtn) {
      openBtn.click();
      return;
    }
    var panel = document.getElementById('template-customizer');
    if (panel) {
      panel.classList.add('template-customizer-open');
    }
  }

  document.getElementById('btnOpenLayoutCustomizer')?.addEventListener('click', openLayoutCustomizer);
  document.getElementById('btnOpenLayoutCustomizerFromMenu')?.addEventListener('click', openLayoutCustomizer);
});
</script>
@endpush
@endif

@if (Auth::check() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('Admin'))
@push('footer-scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var BS = window.bootstrap;
  if (!BS) return;

  var modalEl = document.getElementById('cacheClearModal');
  var toastWrap = document.getElementById('cacheClearToast')?.closest('.toast-container');
  if (modalEl && modalEl.parentElement !== document.body) {
    document.body.appendChild(modalEl);
  }
  if (toastWrap && toastWrap.parentElement !== document.body) {
    document.body.appendChild(toastWrap);
  }

  var openBtn = document.getElementById('btnOpenCacheClearModal');
  if (openBtn && modalEl) {
    openBtn.addEventListener('click', function (e) {
      e.preventDefault();
      BS.Modal.getOrCreateInstance(modalEl).show();
    });
  }

  var btn = document.getElementById('btnRunCacheClear');
  if (!btn) return;

  var clearUrl = @json(route('cache-clear'));
  var showCacheToast = function (message, ok) {
    var toastEl = document.getElementById('cacheClearToast');
    var body = document.getElementById('cacheClearToastBody');
    if (!toastEl || !body) return;
    toastEl.classList.remove('text-bg-success', 'text-bg-danger');
    toastEl.classList.add(ok ? 'text-bg-success' : 'text-bg-danger');
    body.textContent = message;
    var t = BS.Toast.getOrCreateInstance(toastEl, { delay: 6500 });
    t.show();
  };

  btn.addEventListener('click', async function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    var box = document.getElementById('cacheClearResult');
    btn.disabled = true;
    if (box) {
      box.classList.add('d-none');
      box.classList.remove('alert', 'alert-success', 'alert-danger');
    }
    try {
      var res = await fetch(clearUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf || '',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
      });
      var text = await res.text();
      var data = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch (parseErr) {
        throw new Error(text.slice(0, 200) || ('HTTP ' + res.status));
      }
      if (!data.success) {
        var errMsg = data.message || ('HTTP ' + res.status);
        if (box) {
          box.classList.remove('d-none');
          box.classList.add('alert', 'alert-danger');
          box.textContent = errMsg;
        }
        showCacheToast(errMsg, false);
        return;
      }
      if (box) {
        box.classList.remove('d-none');
        box.classList.add('alert', 'alert-success');
        box.textContent = data.message || 'OK';
      }
      var extra = data.detail ? '\n' + data.detail : '';
      showCacheToast((data.message || 'OK') + extra, true);
      if (modalEl) {
        BS.Modal.getInstance(modalEl)?.hide();
      }
    } catch (e) {
      if (box) {
        box.classList.remove('d-none');
        box.classList.add('alert', 'alert-danger');
        box.textContent = e.message || 'Request failed';
      }
      showCacheToast(e.message || 'Request failed', false);
    } finally {
      btn.disabled = false;
    }
  });
});
</script>
@endpush
@endif
