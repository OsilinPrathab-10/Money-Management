@php
  use Illuminate\Support\Facades\Route;
  $user = auth()->user();
  $userRoles = $userRoles ?? (auth()->check() ? auth()->user()->getRoleNames()->toArray() : []);

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

<ul class="menu-sub">
  @if (isset($menu))
    @foreach ($menu as $submenu)
      @if (isset($submenu->roles) && !collect($submenu->roles)->intersect($userRoles)->count())
        @php
          $permissionAllowed = false;
          $slugsToCheck = [];

          if (isset($submenu->slug)) {
            if (is_string($submenu->slug)) {
              $slugsToCheck[] = $submenu->slug;
            } elseif (is_array($submenu->slug)) {
              foreach ($submenu->slug as $s) {
                if (is_string($s)) $slugsToCheck[] = $s;
              }
            }
          }

          if (isset($submenu->submenu) && is_array($submenu->submenu)) {
            foreach ($submenu->submenu as $child) {
              if (isset($child->slug)) {
                if (is_string($child->slug)) {
                  $slugsToCheck[] = $child->slug;
                } elseif (is_array($child->slug)) {
                  foreach ($child->slug as $ss) {
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
        $active = $configData['layout'] === 'vertical' ? 'active open' : 'active';
        $currentRouteName = Route::currentRouteName() ?? '';
        $submenuUrl = isset($submenu->url) ? trim($submenu->url, '/') : null;
        // Avoid matching "account" to every /account/* page (e.g. loan-accounts) — dashboard is exact path only
        $isUrlMatch = false;
        if ($submenuUrl) {
            if ($submenuUrl === 'account' || $submenuUrl === 'app/agents') {
                $isUrlMatch = request()->is($submenuUrl);
            } else {
                $isUrlMatch = request()->is($submenuUrl) || request()->is($submenuUrl . '/*');
            }
        }
        $slugValue = $submenu->slug ?? null;
        // Exact route, or nested route names with "." or "-" (not loose str_contains — avoids wrong highlights)
        $routeMatchesSlug = function (?string $route, string $slug): bool {
            if ($route === '' || $slug === '') {
                return false;
            }
            if ($route === $slug) {
                return true;
            }
            return str_starts_with($route, $slug . '.') || str_starts_with($route, $slug . '-');
        };
        $isRouteActive = false;
        if (is_string($slugValue)) {
            $isRouteActive = $routeMatchesSlug($currentRouteName, $slugValue);
        } elseif (is_array($slugValue)) {
            foreach ($slugValue as $slug) {
                if (is_string($slug) && $routeMatchesSlug($currentRouteName, $slug)) {
                    $isRouteActive = true;
                    break;
                }
            }
        }
        if ($isRouteActive || $isUrlMatch) {
            $activeClass = isset($submenu->submenu) ? $active : 'active';
        }
      @endphp

      <li class="menu-item {{ $activeClass }}">
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}"
          class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}"
          @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif
          @if (isset($submenu->modal)) data-bs-toggle="modal" data-bs-target="{{ $submenu->modal }}" @endif>
          @if (isset($submenu->icon))
            <i class="{{ $submenu->icon }}"></i>
          @endif
          <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>
          @isset($submenu->badge)
            <div class="badge bg-{{ $submenu->badge[0] }} rounded-pill ms-auto">{{ $submenu->badge[1] }}</div>
          @endisset
        </a>

        {{-- submenu --}}
        @if (isset($submenu->submenu))
          @include('layouts.sections.menu.submenu', ['menu' => $submenu->submenu, 'userRoles' => $userRoles])
        @endif
      </li>
    @endforeach
  @endif
</ul>
