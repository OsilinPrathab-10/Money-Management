<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $brand['name'] ?? 'Star Finance' }} — Portal Login</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', system-ui, sans-serif; min-height: 100vh; background: #f0f4ff; }

    .portal-shell { display: flex; min-height: 100vh; }

    /* ── LEFT HERO PANEL ── */
    .portal-hero {
      flex: 0 0 50%;
      background: linear-gradient(145deg, #071526 0%, #0b1f3a 30%, #1535a0 65%, #1e4fd6 100%);
      color: #fff;
      padding: 3rem 3.5rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }
    .portal-hero::before {
      content: '';
      position: absolute;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: rgba(37,99,235,.25);
      top: -120px; right: -120px;
      filter: blur(60px);
    }
    .portal-hero::after {
      content: '';
      position: absolute;
      width: 300px; height: 300px;
      border-radius: 50%;
      background: rgba(244,185,66,.12);
      bottom: -80px; left: 20%;
      filter: blur(50px);
    }
    .portal-hero-content { position: relative; z-index: 1; max-width: 420px; }
    .hero-brand { display: flex; align-items: center; gap: .75rem; margin-bottom: 3rem; text-decoration: none; }
    .hero-brand-icon { width: 44px; height: 44px; border-radius: .75rem; background: linear-gradient(135deg,#f4b942,#e8a300); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
    .hero-brand-name { font-size: 1.3rem; font-weight: 800; color: #fff; }
    .hero-title { font-size: clamp(1.6rem, 3.5vw, 2.4rem); font-weight: 900; line-height: 1.15; margin-bottom: 1rem; }
    .hero-title span { color: #f4b942; }
    .hero-sub { font-size: .95rem; opacity: .8; line-height: 1.7; margin-bottom: 2.5rem; }
    .feature-pill {
      display: inline-flex; align-items: center; gap: .5rem;
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.18);
      padding: .4rem 1rem; border-radius: 999px;
      font-size: .82rem; font-weight: 500;
      margin: .2rem .3rem .2rem 0;
      backdrop-filter: blur(8px);
    }
    .feature-pill i { color: #f4b942; }
    .hero-back { position: absolute; top: 1.5rem; left: 1.5rem; display: flex; align-items: center; gap: .4rem; color: rgba(255,255,255,.65); font-size: .82rem; text-decoration: none; transition: color .2s; }
    .hero-back:hover { color: #fff; }

    /* ── RIGHT FORM PANEL ── */
    .portal-panel {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.5rem;
      background: #fff;
    }
    .portal-card { width: 100%; max-width: 440px; }

    /* ── Tabs ── */
    .sf-tabs { display: flex; background: #f0f4ff; border-radius: 999px; padding: .3rem; margin-bottom: 2rem; }
    .sf-tab {
      flex: 1; text-align: center; padding: .55rem 1rem; border-radius: 999px;
      text-decoration: none; font-weight: 600; font-size: .88rem;
      color: #6b7280; transition: all .2s;
    }
    .sf-tab.active { background: #fff; color: #1e4fd6; box-shadow: 0 2px 10px rgba(30,79,214,.15); }

    /* ── Form ── */
    .sf-input-group { position: relative; }
    .sf-input-group .inp-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1rem; pointer-events: none; }
    .sf-input-group input { padding-left: 2.75rem; border-radius: .6rem; border: 1.5px solid #e5e7eb; height: 48px; font-size: .92rem; transition: border-color .2s, box-shadow .2s; }
    .sf-input-group input:focus { border-color: #1e4fd6; box-shadow: 0 0 0 3px rgba(30,79,214,.12); outline: none; }
    .sf-input-group .eye-btn { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0; }
    .btn-sf-submit {
      width: 100%; background: linear-gradient(135deg,#1e4fd6,#2563eb); border: none;
      color: #fff; font-weight: 700; height: 50px; border-radius: .75rem; font-size: .95rem;
      transition: all .2s; letter-spacing: .01em;
    }
    .btn-sf-submit:hover { background: linear-gradient(135deg,#163fb0,#1e4fd6); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(30,79,214,.4); }
    .btn-sf-submit:active { transform: none; }
    .form-divider { display: flex; align-items: center; gap: 1rem; color: #d1d5db; font-size: .82rem; margin: 1.25rem 0; }
    .form-divider::before, .form-divider::after { content:''; flex:1; height:1px; background:#e5e7eb; }
    .sf-link { color: #1e4fd6; text-decoration: none; font-weight: 600; font-size: .85rem; }
    .sf-link:hover { text-decoration: underline; }
    .alert-sf { border-radius: .75rem; border: none; font-size: .88rem; padding: .75rem 1rem; }

    /* ── Responsive ── */
    @media (max-width: 991px) {
      .portal-shell { flex-direction: column; }
      .portal-hero { flex: none; padding: 2.5rem 1.5rem; min-height: 220px; }
      .hero-title { font-size: 1.5rem; }
      .hero-back { position: static; margin-bottom: 1.5rem; display: inline-flex; }
      .portal-panel { padding: 2.5rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="portal-shell">

  {{-- ── LEFT HERO ── --}}
  <section class="portal-hero">
    <a href="{{ route('website.home') }}" class="hero-back">
      <i class="ri-arrow-left-line"></i> Back to website
    </a>
    <div class="portal-hero-content">
      <a href="{{ route('website.home') }}" class="hero-brand">
        <div class="hero-brand-icon"><i class="ri-star-fill text-dark"></i></div>
        <span class="hero-brand-name">{{ $brand['name'] ?? 'Star Finance' }}</span>
      </a>
      <h1 class="hero-title">Your loans,<br><span>at your fingertips</span></h1>
      <p class="hero-sub">Log in to your customer account to view your loans, track EMI payments, and manage your financial profile — all in one place.</p>
      <div>
        <span class="feature-pill"><i class="ri-bank-card-2-line"></i> View Loan Accounts</span>
        <span class="feature-pill"><i class="ri-calendar-check-line"></i> Track EMI Payments</span>
        <span class="feature-pill"><i class="ri-file-list-3-line"></i> Application Status</span>
        <span class="feature-pill"><i class="ri-shield-check-line"></i> Secure & Private</span>
      </div>
    </div>
  </section>

  {{-- ── RIGHT FORM PANEL ── --}}
  <div class="portal-panel">
    <div class="portal-card">

      {{-- Tab switcher --}}
      <div class="sf-tabs">
        <a href="#" class="sf-tab {{ $tab === 'customer' ? 'active' : '' }}" id="tab-customer" onclick="switchTab('customer');return false;">
          <i class="ri-user-line me-1"></i>Customer
        </a>
        <a href="#" class="sf-tab {{ $tab === 'staff' ? 'active' : '' }}" id="tab-staff" onclick="switchTab('staff');return false;">
          <i class="ri-shield-user-line me-1"></i>Staff
        </a>
      </div>

      {{-- Alerts --}}
      @if(session('success'))
        <div class="alert alert-success alert-sf mb-4"><i class="ri-checkbox-circle-line me-2"></i>{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-sf mb-4"><i class="ri-error-warning-line me-2"></i>{{ session('error') }}</div>
      @endif

      {{-- ── CUSTOMER LOGIN FORM ── --}}
      <div id="pane-customer" class="{{ $tab !== 'customer' ? 'd-none' : '' }}">
        <div class="mb-4">
          <h4 class="fw-800 mb-1">Welcome back</h4>
          <p class="text-muted small mb-0">Sign in to your customer account</p>
        </div>

        @if(isset($errors) && ($errors->hasBag('customer') || ($tab === 'customer' && $errors->any())))
          <div class="alert alert-danger alert-sf mb-3">
            <ul class="mb-0 ps-3 small">@foreach($errors->any() ? $errors->all() : [] as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif

        <form method="POST" action="{{ route('client.login.post') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-600 small mb-1">Email Address</label>
            <div class="sf-input-group">
              <i class="ri-mail-line inp-icon"></i>
              <input type="email" name="email" placeholder="your@email.com" value="{{ old('email') }}" required autocomplete="email">
            </div>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label fw-600 small mb-0">Password</label>
              <a href="{{ route('password.request') }}" class="sf-link">Forgot password?</a>
            </div>
            <div class="sf-input-group">
              <i class="ri-lock-line inp-icon"></i>
              <input type="password" name="password" id="cPwd" placeholder="Enter your password" required autocomplete="current-password">
              <button type="button" class="eye-btn" onclick="togglePwd('cPwd',this)"><i class="ri-eye-off-line"></i></button>
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" name="remember" id="remember">
              <label class="form-check-label small text-muted" for="remember">Keep me signed in</label>
            </div>
          </div>
          <button type="submit" class="btn-sf-submit">Sign In <i class="ri-arrow-right-line ms-1"></i></button>
        </form>

        <div class="form-divider">or</div>
        <div class="text-center">
          <span class="text-muted small">New customer? </span>
          <a href="#" class="sf-link" onclick="switchTab('register');return false;">Create an account</a>
        </div>
      </div>

      {{-- ── CUSTOMER REGISTER FORM ── --}}
      <div id="pane-register" class="{{ $tab !== 'register' ? 'd-none' : '' }}">
        <div class="mb-4">
          <h4 class="fw-800 mb-1">Create Account</h4>
          <p class="text-muted small mb-0">Register to access your loan portal</p>
        </div>

        <div class="p-3 rounded-3 mb-4" style="background:#fff4e5;border:1px solid #fed7aa">
          <div class="d-flex gap-2">
            <i class="ri-information-line text-warning mt-1"></i>
            <div class="small text-warning-emphasis"><strong>Default password</strong> is your 10-digit mobile number. Please change it after first login.</div>
          </div>
        </div>

        <form method="POST" action="{{ route('client.register.post') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-600 small mb-1">Full Name <span class="text-danger">*</span></label>
            <div class="sf-input-group">
              <i class="ri-user-line inp-icon"></i>
              <input type="text" name="name" placeholder="Your full name" value="{{ old('name') }}" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600 small mb-1">Email Address <span class="text-danger">*</span></label>
            <div class="sf-input-group">
              <i class="ri-mail-line inp-icon"></i>
              <input type="email" name="email" placeholder="your@email.com" value="{{ old('email') }}" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600 small mb-1">Mobile Number <span class="text-danger">*</span></label>
            <div class="sf-input-group">
              <i class="ri-smartphone-line inp-icon"></i>
              <input type="tel" name="mobile_number" placeholder="10-digit mobile number" maxlength="10" pattern="[6-9][0-9]{9}" value="{{ old('mobile_number') }}" required>
            </div>
            <div class="form-text small text-muted mt-1"><i class="ri-information-line"></i> This will be your default password</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-600 small mb-1">Password <span class="text-danger">*</span></label>
            <div class="sf-input-group">
              <i class="ri-lock-line inp-icon"></i>
              <input type="password" name="password" id="rPwd" placeholder="Create a password (min 8 chars)" required>
              <button type="button" class="eye-btn" onclick="togglePwd('rPwd',this)"><i class="ri-eye-off-line"></i></button>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-600 small mb-1">Confirm Password <span class="text-danger">*</span></label>
            <div class="sf-input-group">
              <i class="ri-lock-2-line inp-icon"></i>
              <input type="password" name="password_confirmation" id="rPwdC" placeholder="Re-enter password" required>
            </div>
          </div>
          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" required id="terms">
            <label class="form-check-label small text-muted" for="terms">
              I agree to the <a href="{{ route('public.terms-and-conditions') }}" target="_blank" class="sf-link">Terms & Conditions</a> and <a href="{{ route('public.privacy-policy') }}" target="_blank" class="sf-link">Privacy Policy</a>
            </label>
          </div>
          <button type="submit" class="btn-sf-submit">Create Account <i class="ri-arrow-right-line ms-1"></i></button>
        </form>
        <div class="form-divider">or</div>
        <div class="text-center">
          <span class="text-muted small">Already have an account? </span>
          <a href="#" class="sf-link" onclick="switchTab('customer');return false;">Sign in</a>
        </div>
      </div>

      {{-- ── STAFF LOGIN FORM ── --}}
      <div id="pane-staff" class="{{ $tab !== 'staff' ? 'd-none' : '' }}">
        <div class="mb-4">
          <h4 class="fw-800 mb-1">Staff Login</h4>
          <p class="text-muted small mb-0">Admin, Manager & Staff access</p>
        </div>
        <form method="POST" action="{{ route('login') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label fw-600 small mb-1">Email Address</label>
            <div class="sf-input-group">
              <i class="ri-mail-line inp-icon"></i>
              <input type="email" name="email" placeholder="staff@example.com" required autocomplete="email">
            </div>
          </div>
          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label fw-600 small mb-0">Password</label>
              <a href="{{ route('password.request') }}" class="sf-link">Forgot?</a>
            </div>
            <div class="sf-input-group">
              <i class="ri-lock-line inp-icon"></i>
              <input type="password" name="password" id="sPwd" required autocomplete="current-password">
              <button type="button" class="eye-btn" onclick="togglePwd('sPwd',this)"><i class="ri-eye-off-line"></i></button>
            </div>
          </div>
          <button type="submit" class="btn-sf-submit">Sign In <i class="ri-arrow-right-line ms-1"></i></button>
        </form>
        <div class="form-divider">or</div>
        <div class="text-center">
          <span class="text-muted small">Customer? </span>
          <a href="#" class="sf-link" onclick="switchTab('customer');return false;">Customer login</a>
        </div>
      </div>

    </div>{{-- /.portal-card --}}
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(tab) {
  ['customer','staff','register'].forEach(function(t) {
    var pane = document.getElementById('pane-' + t);
    var tabEl = document.getElementById('tab-' + t);
    if (pane) pane.classList.toggle('d-none', t !== tab);
    if (tabEl) {
      tabEl.classList.toggle('active', t === tab);
      if (t === tab && tab !== 'register') tabEl.classList.add('active');
    }
  });
  var cTab = document.getElementById('tab-customer');
  var sTab = document.getElementById('tab-staff');
  if (tab === 'register') { cTab.classList.add('active'); sTab.classList.remove('active'); }
}
function togglePwd(id, btn) {
  var inp = document.getElementById(id);
  var show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.innerHTML = show ? '<i class="ri-eye-line"></i>' : '<i class="ri-eye-off-line"></i>';
}
</script>
</body>
</html>
