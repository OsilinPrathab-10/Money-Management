@php
  $containerFooter =
      isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact'
          ? 'container-xxl'
          : 'container-fluid';
  $companyName = get_setting('company_name', get_setting('footer_company_name', config('app.name', 'Company')));
  $companyWebsite = get_setting('company_website', url('/'));
@endphp

<!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ $containerFooter }}">
    <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
      <div class="mb-2 mb-md-0 text-nowrap">
        &#169;
        <script>
          document.write(new Date().getFullYear());
        </script>
        <a href="{{ $companyWebsite }}"
          target="_blank"
          class="footer-link fw-medium">{{ $companyName }}</a>
          <span> - All rights reserved.</span>
      </div>
    </div>
  </div>
</footer>
<!-- / Footer -->
