@php
  $containerFooter =
      isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact'
          ? 'container-xxl'
          : 'container-fluid';

  $footerText = \App\Helpers\SettingsHelper::get('footer_text');
  $footerLinkText = \App\Helpers\SettingsHelper::get('footer_link_text');
  $footerLinkUrl = \App\Helpers\SettingsHelper::get('footer_link_url');
  $footerCustomNote = \App\Helpers\SettingsHelper::get('footer_custom_note');

  if ($footerText) {
      $footerText = str_ireplace(['{year}', '{{year}}'], date('Y'), $footerText);
  }
  if ($footerCustomNote) {
      $footerCustomNote = str_ireplace(['{year}', '{{year}}'], date('Y'), $footerCustomNote);
  }

  $hasDynamicFooter = !empty(trim($footerText ?? '')) || !empty(trim($footerLinkText ?? '')) || !empty(trim($footerCustomNote ?? ''));
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ $containerFooter }}">
    <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
      <div class="mb-2 mb-md-0">
        @if($hasDynamicFooter)
          <span>{!! !empty(trim($footerText ?? '')) ? $footerText : ('&#169; ' . date('Y')) !!}</span>
          @if(!empty(trim($footerLinkText ?? '')))
            <a href="{{ !empty(trim($footerLinkUrl ?? '')) ? $footerLinkUrl : 'javascript:void(0);' }}"
               @if(!empty(trim($footerLinkUrl ?? '')) && !str_starts_with($footerLinkUrl, 'javascript')) target="_blank" @endif
               class="footer-link fw-medium ms-1">{{ $footerLinkText }}</a>
          @endif
          @if(!empty(trim($footerCustomNote ?? '')))
            <span class="ms-1">{{ $footerCustomNote }}</span>
          @endif
        @else
          &#169;
          <script>
            document.write(new Date().getFullYear());
          </script>
          <a href="https://codepluse.com/"
            target="_blank"
            class="footer-link fw-medium">Codepluse Gen pvt Ltd</a>
            <span> - All rights reserved.</span>
        @endif
      </div>
    </div>
  </div>
</footer>
<!-- / Footer -->
