@php
  $brandName = \App\Helpers\SettingsHelper::get('admin_title', 'Star Finance');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'My account') — {{ $brandName }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    :root { --sf-navy:#0b1f3a; --sf-blue:#1e4fd6; }
    body { background:#f4f6fb; }
    .cp-top { background:var(--sf-navy); color:#fff; }
    .cp-nav a { color:rgba(255,255,255,.85); text-decoration:none; font-weight:500; padding:.5rem .75rem; border-radius:.5rem; }
    .cp-nav a:hover, .cp-nav a.active { color:#fff; background:rgba(255,255,255,.1); }
    .cp-main { max-width:1200px; margin:0 auto; padding:1.5rem 1rem 3rem; }
  </style>
  @stack('styles')
</head>
<body>
  <header class="cp-top py-3">
    <div class="cp-main py-0 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <a href="{{ route('client.dashboard') }}" class="text-white fw-bold text-decoration-none d-flex align-items-center gap-2">
        <i class="ri-star-fill text-warning"></i> {{ $brandName }}
      </a>
      <nav class="cp-nav d-flex flex-wrap gap-1">
        <a href="{{ route('client.dashboard') }}" class="@if(request()->routeIs('client.dashboard')) active @endif"><i class="ri-dashboard-line me-1"></i>{{ __('Dashboard') }}</a>
        <a href="{{ route('client.profile') }}" class="@if(request()->routeIs('client.profile')) active @endif"><i class="ri-user-line me-1"></i>{{ __('Profile') }}</a>
        <a href="{{ route('website.apply') }}"><i class="ri-file-add-line me-1"></i>{{ __('Apply') }}</a>
        <a href="{{ route('website.home') }}"><i class="ri-global-line me-1"></i>{{ __('Website') }}</a>
      </nav>
      <form method="POST" action="{{ route('client.logout') }}" class="mb-0">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-light">{{ __('Logout') }}</button>
      </form>
    </div>
  </header>

  @if(session('success'))
    <div class="cp-main pt-3 pb-0"><div class="alert alert-success mb-0">{{ session('success') }}</div></div>
  @endif
  @if(session('info'))
    <div class="cp-main pt-3 pb-0"><div class="alert alert-info mb-0">{{ session('info') }}</div></div>
  @endif
  @if($errors->any())
    <div class="cp-main pt-3 pb-0"><div class="alert alert-danger mb-0">{{ $errors->first() }}</div></div>
  @endif

  <main class="cp-main">
    @yield('content')
  </main>
  @stack('scripts')
</body>
</html>