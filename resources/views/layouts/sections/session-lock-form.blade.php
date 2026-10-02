  <h2 id="sds-lock-title" class="sds-welcome">Welcome Back <span class="sds-wave">👋</span></h2>
  <p class="sds-welcome-sub">Login to your admin portal and continue managing your operations.</p>

  <form id="sds-lock-form" autocomplete="off" data-skip-single-submit="1">
    <div class="sds-field form-control-validation">
      <label for="sds-lock-email" class="sds-label">Email Address</label>
      <div class="sds-input">
        <span class="sds-input-icon"><i class="ri-mail-line"></i></span>
        <input type="email" class="form-control" id="sds-lock-email"
          value="{{ optional(auth()->user())->email }}" placeholder="Enter your Email"
          readonly tabindex="-1" autocomplete="username" />
      </div>
    </div>

    <div class="sds-field form-control-validation">
      <label for="sds-lock-password" class="sds-label">Password</label>
      <div class="sds-input form-password-toggle">
        <span class="sds-input-icon"><i class="ri-lock-2-line"></i></span>
        <input type="password" class="form-control" id="sds-lock-password" name="password"
          placeholder="Enter your password" autocomplete="current-password" required />
        <span class="sds-eye cursor-pointer no-single-click" id="sds-lock-eye" role="button" tabindex="0">
          <i class="icon-base ri ri-eye-off-line"></i>
        </span>
      </div>
      <span id="sds-lock-error" class="sds-invalid" role="alert"></span>
    </div>

    <div class="sds-row">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="sds-lock-remember" tabindex="-1" />
        <label class="form-check-label" for="sds-lock-remember">Remember me</label>
      </div>
      <button type="submit" form="sds-lock-logout" class="sds-link no-single-click">Sign in again</button>
    </div>

    <button type="submit" class="btn sds-btn-signin no-single-click" id="sds-lock-submit">
      <i class="ri-arrow-right-line"></i><span>Sign In</span>
    </button>
  </form>

  <form id="sds-lock-logout" method="POST" action="{{ route('logout') }}" data-skip-single-submit="1">
    @csrf
  </form>
