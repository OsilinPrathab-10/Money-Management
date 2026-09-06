@php
  use Illuminate\Support\Facades\Route;
  use App\Helpers\SettingsHelper;
  $configData = Helper::appClasses();
  $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName'));
  $user = auth()->user();
  $userRoles = $user->getRoleNames()->toArray();
  $accountPermissionCandidatesFromSlug = function ($slug): array {
      if (!is_string($slug) || !str_starts_with($slug, 'account.')) {
          return [];
      }

      $base = substr($slug, strlen('account.'));
      $candidates = [];

      switch ($base) {
          case 'index':
              $candidates = ['manage-account-dashboard'];
              break;
          case 'loan-accounts':
              $candidates = ['view-account-loan-accounts'];
              break;
          case 'emis':
              $candidates = ['view-account-emis'];
              break;
          case 'bank-accounts':
              $candidates = [
                  'manage-bank-accounts',
                  'manage-any-bank-accounts',
                  'manage-own-bank-accounts',
                  'create-bank-accounts',
                  'edit-bank-accounts',
                  'delete-bank-accounts',
              ];
              break;
          case 'chart-of-accounts':
              $candidates = [
                  'manage-chart-of-accounts',
                  'manage-any-chart-of-accounts',
                  'manage-own-chart-of-accounts',
                  'create-chart-of-accounts',
                  'edit-chart-of-accounts',
                  'view-chart-of-accounts',
                  'delete-chart-of-accounts',
              ];
              break;
          case 'revenues':
              $candidates = [
                  'manage-revenues',
                  'manage-any-revenues',
                  'manage-own-revenues',
                  'create-revenues',
                  'edit-revenues',
                  'delete-revenues',
                  'approve-revenues',
                  'post-revenues',
              ];
              break;
          case 'expenses':
              $candidates = [
                  'manage-expenses',
                  'manage-any-expenses',
                  'manage-own-expenses',
                  'create-expenses',
                  'edit-expenses',
                  'delete-expenses',
                  'approve-expenses',
                  'post-expenses',
              ];
              break;
          case 'reports':
              $candidates = [
                  'manage-account-reports',
                  'print-invoice-aging',
                  'print-bill-aging',
                  'print-tax-summary',
                  'print-customer-balance',
                  'print-vendor-balance',
                  'view-customer-detail-report',
                  'view-vendor-detail-report',
                  'print-customer-detail-report',
                  'print-vendor-detail-report',
              ];
              break;
          case 'day-book':
              $candidates = ['manage-account-day-book'];
              break;
          case 'ledger':
              $candidates = ['manage-account-ledger'];
              break;
          case 'profit-loss':
              $candidates = ['manage-account-profit-loss'];
              break;
          case 'bank-transactions':
              $candidates = [
                  'manage-bank-transactions',
                  'reconcile-bank-transactions',
              ];
              break;
          case 'bank-transfers':
              $candidates = [
                  'manage-bank-transfers',
                  'process-bank-transfers',
              ];
              break;
          case 'customers':
              $candidates = [
                  'manage-customers',
                  'manage-any-customers',
                  'manage-own-customers',
              ];
              break;
          case 'customer-payments':
              $candidates = [
                  'manage-customer-payments',
                  'manage-any-customer-payments',
                  'manage-own-customer-payments',
              ];
              break;
          case 'debit-notes':
              $candidates = [
                  'manage-debit-notes',
                  'manage-any-debit-notes',
                  'manage-own-debit-notes',
              ];
              break;
          case 'credit-notes':
              $candidates = [
                  'manage-credit-notes',
                  'manage-any-credit-notes',
                  'manage-own-credit-notes',
              ];
              break;
          case 'account-types':
              $candidates = [
                  'manage-account-types',
                  'manage-any-account-types',
                  'manage-own-account-types',
              ];
              break;
          case 'revenue-categories':
              $candidates = [
                  'manage-revenue-categories',
                  'create-revenue-categories',
                  'edit-revenue-categories',
                  'delete-revenue-categories',
              ];
              break;
          case 'expense-categories':
              $candidates = [
                  'manage-expense-categories',
                  'create-expense-categories',
                  'edit-expense-categories',
                  'delete-expense-categories',
              ];
              break;
      }

      return array_values(array_unique($candidates));
  };
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu"
  @foreach ($configData['menuAttributes'] as $attribute => $value)
  {{ $attribute }}="{{ $value }}" @endforeach>

  <!-- ! Hide app brand if navbar-full -->
  @if (!isset($navbarFull))
    <div class="app-brand demo" style="height: 80px;">
      <a href="{{ auth()->user()->hasRole('CreditVerifier') ? route('verification-credit-score-history') : url('/dashboard') }}" class="app-brand-link gap-xl-0 gap-2">
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
    @foreach ($menuData[0]->menu as $menu)
      @if (isset($menu->roles) && !collect($menu->roles)->intersect($userRoles)->count())
          @php
            // If roles don't match, fall back to permission-based access for Accounting menus.
            // This lets Admin assign permissions to Staff without depending on hard-coded role names in JSON.
            $permissionAllowed = false;

            $slugsToCheck = [];
            if (isset($menu->slug)) {
              if (is_string($menu->slug)) {
                $slugsToCheck[] = $menu->slug;
              } elseif (is_array($menu->slug)) {
                foreach ($menu->slug as $s) {
                  if (is_string($s)) $slugsToCheck[] = $s;
                }
              }
            }

            if (isset($menu->submenu) && is_array($menu->submenu)) {
              foreach ($menu->submenu as $sub) {
                if (isset($sub->slug)) {
                  if (is_string($sub->slug)) {
                    $slugsToCheck[] = $sub->slug;
                  } elseif (is_array($sub->slug)) {
                    foreach ($sub->slug as $ss) {
                      if (is_string($ss)) $slugsToCheck[] = $ss;
                    }
                  }
                }
              }
            }

            foreach ($slugsToCheck as $slug) {
              $candidates = $accountPermissionCandidatesFromSlug($slug);
              if (!empty($candidates) && $user?->hasAnyPermission($candidates)) {
                $permissionAllowed = true;
                break;
              }
            }
          @endphp

          @if (!$permissionAllowed)
            @continue
          @endif
      @endif
      {{-- CreditVerifier: only show menu entries that explicitly list CreditVerifier (or have roles that include this user) --}}
      @if ($user->hasRole('CreditVerifier') && !isset($menu->roles) && !isset($menu->menuHeader))
          @continue
      @endif
      {{-- adding active and open class if child is active --}}

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
            @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu, 'userRoles' => $userRoles])
          @endisset
        </li>
      @endif
    @endforeach
  </ul>

</aside>
@include('admin.account.shared.modal-add-bank')
@include('admin.account.shared.modal-add-revenue-category')
@include('admin.account.shared.modal-add-expense-category')
@include('admin.account.shared.modal-add-account-type')
@include('admin.account.shared.modal-add-revenue-draft')
@include('admin.account.shared.modal-add-expense-draft')
@include('admin.account.shared.modal-add-chart-of-account')

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
