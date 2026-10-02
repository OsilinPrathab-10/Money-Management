@php
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
@endphp

<ul class="menu-sub">
  @if (isset($menu))
    @foreach ($menu as $submenu)
      {{-- Submenus are pre-filtered by MenuAccessService. --}}
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
            } elseif (isset($submenu->submenu)) {
                $isUrlMatch = request()->is($submenuUrl) || request()->is($submenuUrl . '/*');
            } else {
                // Leaf items: exact path only (e.g. /deposits must not match /deposits/create)
                $isUrlMatch = request()->is($submenuUrl);
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
          @include('layouts.sections.menu.submenu', ['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
