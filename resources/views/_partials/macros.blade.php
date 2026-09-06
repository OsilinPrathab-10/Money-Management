@php
  use App\Helpers\SettingsHelper;
  use Illuminate\Support\Facades\Storage;
  $width = $width ?? '120';
  $height = $height ?? '40';
  $adminLogo = SettingsHelper::get('admin_logo');
  $logoExists = $adminLogo && Storage::disk('public')->exists($adminLogo);
@endphp

@if($adminLogo && $logoExists)
  <img src="{{ asset('storage/' . $adminLogo) }}" alt="Logo" style="max-height: {{ $height }}px; max-width: {{ $width }}px;" class="img-fluid">
@else
  <span class="text-primary d-flex align-items-center">
    <svg width="40" height="40" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0px 4px 12px rgba(124, 58, 237, 0.35));">
      <circle cx="50" cy="50" r="48" fill="url(#financeGrad)" stroke="#4F46E5" stroke-width="2"/>
      <path d="M30 65L45 45L55 55L75 25" stroke="#FFFFFF" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="75" cy="25" r="8" fill="#FFD700" />
      <path d="M60 25H75V40" stroke="#FFD700" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
      <defs>
        <linearGradient id="financeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#7C3AED"/>
          <stop offset="50%" stop-color="#4F46E5"/>
          <stop offset="100%" stop-color="#2563EB"/>
        </linearGradient>
      </defs>
    </svg>
  </span>
@endif
