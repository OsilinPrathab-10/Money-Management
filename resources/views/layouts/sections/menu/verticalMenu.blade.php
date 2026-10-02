@php
  use Illuminate\Support\Facades\Route;
  use App\Helpers\SettingsHelper;
  $configData = Helper::appClasses();
  $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName'));
  $user = auth()->user();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu"
  @foreach ($configData['menuAttributes'] as $attribute => $value)
  {{ $attribute }}="{{ $value }}" @endforeach>

  <!-- ! Hide app brand if navbar-full -->
  @if (!isset($navbarFull))
    <div class="app-brand demo" style="height: 80px;">
      <a href="{{ auth()->user()->hasRole('CreditVerifier') ? route('verification-credit-score-history') : (auth()->user()->hasRole('Agent') && !auth()->user()->hasRole('Admin') ? route('agent-dashboard') : url('/dashboard')) }}" class="app-brand-link gap-xl-0 gap-2">
        <span class="app-brand-logo demo">@include('_partials.macros', ['width' => '200', 'height' => '65'])</span>

        <span class="app-brand-text demo menu-text fw-semibold ms-3" style="font-size: 1.4rem;">{{ $adminTitle }}</span>
      </a>

      <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M8.47365 11.7183C8.11707 12.0749 8.11707 12.6531 8.47365 13.0097L12.071 16.607C12.4615 16.9975 12.4615 17.6305 12.071 18.021C11.6805 18.4115 11.0475 18.4115 10.657 18.021L5.83009 13.1941C5.37164 12.7356 5.37164 11.9924 5.83009 11.5339L10.657 6.707C11.0475 6.31653 11.6805 6.31653 12.071 6.707C12.4615 7.09747 12.4615 7.73053 12.071 8.121L8.47365 11.7183Z"
            fill-opacity="0.9" />
          <path
            d="M14.3584 11.8336C14.0654 12.1266 14.0654 12.6014 14.3584 12.8944L18.071 16.607C18.4615 16.9975 18.4615 17.6305 18.071 18.021C17.6805 18.4115 17.0475 18.4115 16.657 18.021L11.6819 13.0459C11.3053 12.6693 11.3053 12.0587 11.6819 11.6821L16.657 6.707C17.0475 6.31653 17.6805 6.31653 18.071 6.707C18.4615 7.09747 18.4615 7.73053 18.071 8.121L14.3584 11.8336Z"
            fill-opacity="0.4" />
        </svg>
      </a>
    </div>
  @endif

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    @foreach (($menuData[0]->menu ?? []) as $menu)
      {{-- Menu tree is pre-filtered by MenuAccessService (role menus + optional user override). --}}

      {{-- menu headers --}}
      @if (isset($menu->menuHeader))
        <li class="menu-header small mt-5">
          <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
        </li>
      @else
        {{-- active menu method --}}
        @php
          $activeClass = null;
          $currentRouteName = Route::currentRouteName() ?? '';
          $menuUrl = isset($menu->url) ? trim($menu->url, '/') : null;
          $isUrlMatch = false;
          if ($menuUrl) {
              if ($menuUrl === 'account' || $menuUrl === 'app/agents') {
                  $isUrlMatch = request()->is($menuUrl);
              } else {
                  $isUrlMatch = request()->is($menuUrl) || request()->is($menuUrl . '/*');
              }
          }
          $slugValue = $menu->slug ?? null;
          $parentRouteMatches = function (?string $route, string $slug): bool {
              if ($route === '' || $slug === '') {
                  return false;
              }
              if ($route === $slug) {
                  return true;
              }
              return str_starts_with($route, $slug . '.') || str_starts_with($route, $slug . '-');
          };
          $slugActive = false;
          if (is_string($slugValue)) {
              $slugActive = $parentRouteMatches($currentRouteName, $slugValue);
          } elseif (is_array($slugValue)) {
              foreach ($slugValue as $slug) {
                  if (is_string($slug) && $parentRouteMatches($currentRouteName, $slug)) {
                      $slugActive = true;
                      break;
                  }
              }
          }
          if ($slugActive || $isUrlMatch) {
              $activeClass = isset($menu->submenu) ? 'active open' : 'active';
          }
        @endphp

        {{-- main menu --}}
        <li class="menu-item {{ $activeClass }}">
          <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}"
            class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}"
            @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif
            @if (isset($menu->modal)) data-bs-toggle="modal" data-bs-target="{{ $menu->modal }}" @endif>
            @isset($menu->icon)
              <i class="{{ $menu->icon }}"></i>
            @endisset
            <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
            @isset($menu->badge)
              <div class="badge bg-{{ $menu->badge[0] }} rounded-pill ms-auto">{{ $menu->badge[1] }}</div>
            @endisset
          </a>

          {{-- submenu --}}
          @isset($menu->submenu)
            @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu])
          @endisset
        </li>
      @endif
    @endforeach
  </ul>

</aside>
@include('admin.account.shared.modal-add-bank')
@include('admin.account.shared.modal-add-revenue-draft')
@include('admin.account.shared.modal-add-expense-draft')
@include('admin.account.shared.modal-add-revenue-category')
@include('admin.account.shared.modal-add-expense-category')

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
