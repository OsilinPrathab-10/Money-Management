@php
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
  $user = auth()->user();
  $userRoles = $user ? $user->getRoleNames()->toArray() : [];

  // For Accounting menus only: JSON uses hard-coded roles, but we want staff access by permissions.
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
<!-- Horizontal Menu -->
<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal  menu flex-grow-0"
  @foreach ($configData['menuAttributes'] as $attribute => $value)
  {{ $attribute }}="{{ $value }}" @endforeach>
  <div class="{{ $containerNav }} d-flex h-100">
    <ul class="menu-inner">
      @foreach ($menuData[1]->menu as $menu)
        @if (isset($menu->roles) && !collect($menu->roles)->intersect($userRoles)->count())
          @php
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
        {{-- active menu method --}}
        @php
          $activeClass = null;
          $currentRouteName = Route::currentRouteName();

          if ($currentRouteName === $menu->slug) {
              $activeClass = 'active';
          } elseif (isset($menu->submenu)) {
              if (gettype($menu->slug) === 'array') {
                  foreach ($menu->slug as $slug) {
                      if (str_contains($currentRouteName, $slug) and strpos($currentRouteName, $slug) === 0) {
                          $activeClass = 'active';
                      }
                  }
              } else {
                  if (str_contains($currentRouteName, $menu->slug) and strpos($currentRouteName, $menu->slug) === 0) {
                      $activeClass = 'active';
                  }
              }
          }
        @endphp

        {{-- main menu --}}
        <li class="menu-item {{ $activeClass }}">
          <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}"
            class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}"
            @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
            @isset($menu->icon)
              <i class="{{ $menu->icon }}"></i>
            @endisset
            <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
          </a>

          {{-- submenu --}}
          @isset($menu->submenu)
            @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu])
          @endisset
        </li>
      @endforeach
    </ul>
  </div>
</aside>
<!--/ Horizontal Menu -->
