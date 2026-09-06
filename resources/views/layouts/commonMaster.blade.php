<!DOCTYPE html>
@php
  use Illuminate\Support\Str;
  use App\Helpers\Helpers;
  use App\Helpers\SettingsHelper;
  use App\Helpers\AppearanceHelper;

  $menuFixed =
      $configData['layout'] === 'vertical'
          ? $menuFixed ?? ''
          : ($configData['layout'] === 'front'
              ? ''
              : $configData['headerType']);
  $navbarType =
      $configData['layout'] === 'vertical'
          ? $configData['navbarType']
          : ($configData['layout'] === 'front'
              ? 'layout-navbar-fixed'
              : '');
  $isFront = ($isFront ?? '') == true ? 'Front' : '';
  $contentLayout = isset($container) ? ($container === 'container-xxl' ? 'layout-compact' : 'layout-wide') : '';

  // Get skin name from configData - only applies to admin layouts
  $isAdminLayout = !Str::contains($configData['layout'] ?? '', 'front');
  $skinName = $isAdminLayout ? $configData['skinName'] ?? 'default' : 'default';

  // Get semiDark value from configData - only applies to admin layouts
  $semiDarkEnabled = $isAdminLayout && filter_var($configData['semiDark'] ?? false, FILTER_VALIDATE_BOOLEAN);

  // Get appearance settings from config-based helper
  $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName', 'Loan App'));
  $adminSubtitle = SettingsHelper::get('admin_subtitle', config('variables.templateSuffix', 'Loan Management System'));
  $adminFavicon = SettingsHelper::get('admin_favicon');
  $primaryColor = SettingsHelper::get('primary_color', '#696cff');
  $secondaryColor = SettingsHelper::get('secondary_color', '#8592a3');
  $themeMode = AppearanceHelper::get('theme_mode', $configData['theme'] ?? 'light');

  // Generate primary and secondary color CSS from appearance settings
  $primaryColorCSS = '';
  if ($primaryColor) {
      $r = hexdec(substr($primaryColor, 1, 2));
      $g = hexdec(substr($primaryColor, 3, 2));
      $b = hexdec(substr($primaryColor, 5, 2));
      
      // Calculate contrast color based on YIQ formula
      $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
      $contrastColor = ($yiq >= 150) ? '#000' : '#fff';
      
      $primaryColorCSS = "
      :root, [data-bs-theme=light], [data-bs-theme=dark] {
        --bs-primary: {$primaryColor};
        --bs-primary-rgb: {$r}, {$g}, {$b};
        --bs-primary-bg-subtle: rgba({$r}, {$g}, {$b}, 0.1);
        --bs-primary-border-subtle: rgba({$r}, {$g}, {$b}, 0.3);
        --bs-primary-contrast: {$contrastColor};";
      
      // Add secondary color if available
      if ($secondaryColor) {
          $sr = hexdec(substr($secondaryColor, 1, 2));
          $sg = hexdec(substr($secondaryColor, 3, 2));
          $sb = hexdec(substr($secondaryColor, 5, 2));
          
          $primaryColorCSS .= "
        --bs-secondary: {$secondaryColor};
        --bs-secondary-rgb: {$sr}, {$sg}, {$sb};
        --bs-secondary-bg-subtle: rgba({$sr}, {$sg}, {$sb}, 0.1);
        --bs-secondary-border-subtle: rgba({$sr}, {$sg}, {$sb}, 0.3);";
      }
      
      $primaryColorCSS .= "
      }";
  }

@endphp

<html lang="{{ session()->get('locale') ?? app()->getLocale() }}"
  class="{{ $navbarType ?? '' }} {{ $contentLayout ?? '' }} {{ $menuFixed ?? '' }} {{ $menuCollapsed ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}"
  dir="{{ $configData['textDirection'] }}" data-skin="{{ $skinName }}" data-assets-path="{{ asset('/assets') . '/' }}"
  data-base-url="{{ url('/') }}" data-framework="laravel" data-template="{{ $configData['layout'] }}-menu-template"
  data-bs-theme="{{ $themeMode }}" @if ($isAdminLayout && $semiDarkEnabled) data-semidark-menu="true" @endif>

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>@hasSection('title') @yield('title') | @endif {{ $adminTitle }} - {{ $adminSubtitle }}</title>

  <!-- SEO Meta Tags -->
  <meta name="description" content="{{ $adminTitle }} - {{ $adminSubtitle }}. Quick and easy loan solutions with transparent processing." />
  <meta name="keywords" content="loan, easy cash, finance, quick loan, credit, {{ $adminTitle }}" />
  <meta name="author" content="{{ $adminTitle }}" />
  <meta name="robots" content="index, follow" />
  
  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}" />

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url()->current() }}" />
  <meta property="og:title" content="@yield('title') | {{ $adminTitle }}" />
  <meta property="og:description" content="Get quick and easy loans with {{ $adminTitle }}. Professional loan management system." />
  <meta property="og:image" content="{{ $adminLogo ? asset('storage/' . $adminLogo) : asset('assets/img/og-image.png') }}" />

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image" />
  <meta property="twitter:url" content="{{ url()->current() }}" />
  <meta property="twitter:title" content="@yield('title') | {{ $adminTitle }}" />
  <meta property="twitter:description" content="Get quick and easy loans with {{ $adminTitle }}." />
  <meta property="twitter:image" content="{{ $adminLogo ? asset('storage/' . $adminLogo) : asset('assets/img/og-image.png') }}" />

  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ url()->current() }}" />

  <!-- Favicon -->
  @php
    $faviconExists = $adminFavicon && \Illuminate\Support\Facades\Storage::disk('public')->exists($adminFavicon);
    $logoExists = SettingsHelper::get('admin_logo') && \Illuminate\Support\Facades\Storage::disk('public')->exists(SettingsHelper::get('admin_logo'));
  @endphp
  @if($faviconExists)
    <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . $adminFavicon) }}" />
    <link rel="apple-touch-icon" href="{{ asset('storage/' . $adminFavicon) }}" />
    <link rel="shortcut icon" href="{{ asset('storage/' . $adminFavicon) }}" />
  @else
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    <link rel="apple-touch-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    <link rel="shortcut icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
  @endif

  <!-- Include Styles -->
  <!-- $isFront is used to append the front layout styles only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/styles' . $isFront)

  @if ($primaryColorCSS)
    <!-- Primary Color Style -->
    <style id="primary-color-style">
      {!! $primaryColorCSS !!}
    </style>
  @endif

  <!-- Include Scripts for customizer, helper, analytics, config -->
  <!-- $isFront is used to append the front layout scriptsIncludes only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scriptsIncludes' . $isFront)
</head>

<body>
  <!-- Page Loader (favicon/logo or animation) -->
  @php
    $loaderAnimation = SettingsHelper::get('loader_animation', 'loader1');
    $preloaderImageUrl = $faviconExists
        ? asset('storage/' . $adminFavicon)
        : ($logoExists ? asset('storage/' . SettingsHelper::get('admin_logo')) : null);

    // If the animation requires an image but none exists, gracefully fallback to the default CSS loader
    if (in_array($loaderAnimation, ['loader_favicon', 'favicon']) && !$preloaderImageUrl) {
        $loaderAnimation = 'loader1';
    }
  @endphp
  
  <div class="page-loader" id="main-page-loader">
    @if($loaderAnimation === 'loader_favicon' || $loaderAnimation === 'favicon')
      <!-- Favicon / Logo as preloader -->
      <img src="{{ $preloaderImageUrl }}" alt="" class="page-loader-favicon" width="64" height="64">
    @elseif($loaderAnimation === 'loader2')
      <!-- Loader 2: Animated Worm SVG -->
      <svg xmlns="http://www.w3.org/2000/svg" height="128px" width="128px" viewBox="0 0 128 128" class="pl">
        <defs>
          <linearGradient y2="1" x2="0" y1="0" x1="0" id="pl-grad">
            <stop stop-color="{{ $primaryColor }}" offset="0%"></stop>
            <stop stop-color="{{ $primaryColor }}" offset="100%"></stop>
          </linearGradient>
        </defs>
        <circle stroke-linecap="round" stroke-width="16" stroke="hsla(0,10%,10%,0.1)" fill="none" cy="64" cx="64" r="56" class="pl__ring"></circle>
        <path stroke-dashoffset="10" stroke-dasharray="44 1111" stroke-linejoin="round" stroke-linecap="round" stroke-width="16" stroke="url(#pl-grad)" fill="none" d="M92,15.492S78.194,4.967,66.743,16.887c-17.231,17.938-28.26,96.974-28.26,96.974L119.85,59.892l-99-31.588,57.528,89.832L97.8,19.349,13.636,88.51l89.012,16.015S81.908,38.332,66.1,22.337C50.114,6.156,36,15.492,36,15.492a56,56,0,1,0,56,0Z" class="pl__worm"></path>
      </svg>
    @elseif($loaderAnimation === 'loader3')
      <!-- Loader 3: Dual Rotating Dots -->
      <div class="loader-dots"></div>
    @else
      <!-- Loader 1: Jumping Box (Default) -->
      <div class="loader"></div>
    @endif
  </div>
  <script>
    // Inline script to synchronously prevent preloader flicker on sidebar transition
    (function() {
      try {
        if (sessionStorage.getItem('menuTransition') === 'true') {
          var loader = document.getElementById('main-page-loader');
          if (loader) {
            loader.classList.add('transition-loading');
          }
        }
      } catch (e) {}
    })();
  </script>
  <style>
    /* Page Loader Styles */
    .page-loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #fff; /* Solid white to hide menu during full page reload */
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999; /* Higher z-index to hide sidebar menu on full reload */
      transition: opacity 0.3s ease-out;
    }

    [data-bs-theme="dark"] .page-loader {
      background: #1e1e2e; /* Solid dark color to hide menu during full page reload */
    }

    /* Transition loader style (when switching menus) - translucent with blur */
    .page-loader.transition-loading {
      background: rgba(255, 255, 255, 0.45) !important;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      z-index: 999; /* Below the sidebar overlay but above main content */
    }

    [data-bs-theme="dark"] .page-loader.transition-loading {
      background: rgba(30, 30, 46, 0.45) !important;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }

    /* Exclude the sidebar/sidemenu from being covered/blurred on desktop during transition */
    @media (min-width: 1200px) {
      html:not(.layout-without-menu) .page-loader.transition-loading {
        left: 260px;
        left: var(--bs-menu-width, 260px);
        width: calc(100% - var(--bs-menu-width, 260px));
      }
      html.layout-menu-collapsed:not(.layout-without-menu) .page-loader.transition-loading {
        left: 80px;
        left: var(--bs-menu-collapsed-width, 80px);
        width: calc(100% - var(--bs-menu-collapsed-width, 80px));
      }
    }

    .page-loader.fade-out {
      opacity: 0;
      pointer-events: none;
    }

    /* Favicon / Logo preloader */
    .page-loader-favicon {
      animation: page-loader-pulse 1.2s ease-in-out infinite;
      object-fit: contain;
    }
    @keyframes page-loader-pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.7; transform: scale(1.05); }
    }

    /* Loader 1 - Jumping Box (Original) */
    .loader {
      width: 48px;
      height: 48px;
      margin: auto;
      position: relative;
    }

    .loader:before {
      content: '';
      width: 48px;
      height: 5px;
      background: #999;
      position: absolute;
      top: 60px;
      left: 0;
      border-radius: 50%;
      animation: shadow324 0.5s linear infinite;
    }

    .loader:after {
      content: '';
      width: 100%;
      height: 100%;
      background: {{ $primaryColor }};
      position: absolute;
      top: 0;
      left: 0;
      border-radius: 4px;
      animation: jump7456 0.5s linear infinite;
    }

    @keyframes jump7456 {
      15% {
        border-bottom-right-radius: 3px;
      }
      25% {
        transform: translateY(9px) rotate(22.5deg);
      }
      50% {
        transform: translateY(18px) scale(1, .9) rotate(45deg);
        border-bottom-right-radius: 40px;
      }
      75% {
        transform: translateY(9px) rotate(67.5deg);
      }
      100% {
        transform: translateY(0) rotate(90deg);
      }
    }

    @keyframes shadow324 {
      0%, 100% {
        transform: scale(1, 1);
      }
      50% {
        transform: scale(1.2, 1);
      }
    }

    /* Loader 2 - Animated Worm SVG */
    .pl,
    .pl__worm {
      animation-duration: 4s;
      animation-iteration-count: infinite;
    }

    .pl {
      animation-name: bump5;
      animation-timing-function: linear;
      width: 5em;
      height: 5em;
    }

    .pl__ring {
      stroke: hsla(var(--hue),10%,10%,0.1);
      transition: stroke 0.3s;
    }

    .pl__worm {
      animation-name: worm5;
      animation-timing-function: cubic-bezier(0.42,0.17,0.75,0.83);
    }

    /* Loader 3 - Dual Rotating Dots */
    .loader-dots {
      height: 15px;
      aspect-ratio: 4;
      --_g: no-repeat radial-gradient(farthest-side, {{ $primaryColor }} 90%, {{ $primaryColor }});
      background:
        var(--_g) left,
        var(--_g) right;
      background-size: 25% 100%;
      display: grid;
    }
    .loader-dots:before,
    .loader-dots:after {
      content: "";
      height: inherit;
      aspect-ratio: 1;
      grid-area: 1/1;
      margin: auto;
      border-radius: 50%;
      transform-origin: -100% 50%;
      background: {{ $primaryColor }};
      animation: l49 1s infinite linear;
    }
    .loader-dots:after {
      transform-origin: 200% 50%;
      --s: -1;
      animation-delay: -0.5s;
    }

    /* Animations */
    @keyframes bump5 {
      from, 42%, 46%, 51%, 55%, 59%, 63%, 67%, 71%, 74%, 78%, 81%, 85%, 88%, 92%, to {
        transform: translate(0,0);
      }
      44% {
        transform: translate(1.33%,6.75%);
      }
      53% {
        transform: translate(-16.67%,-0.54%);
      }
      61% {
        transform: translate(3.66%,-2.46%);
      }
      69% {
        transform: translate(-0.59%,15.27%);
      }
      76% {
        transform: translate(-1.92%,-4.68%);
      }
      83% {
        transform: translate(9.38%,0.96%);
      }
      90% {
        transform: translate(-4.55%,1.98%);
      }
    }

    @keyframes worm5 {
      from {
        stroke-dashoffset: 10;
      }
      25% {
        stroke-dashoffset: 295;
      }
      to {
        stroke-dashoffset: 1165;
      }
    }

    @keyframes l49 {
      58%, 100% {
        transform: rotate(calc(var(--s, 1) * 1turn));
      }
    }
  </style>
  <!-- / Page Loader -->

  <!-- Layout Content -->
  @yield('layoutContent')
  <!--/ Layout Content -->

  @stack('modals')

  <!-- Include Scripts -->
  <!-- $isFront is used to append the front layout scripts only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scripts' . $isFront)
</body>

</html>
