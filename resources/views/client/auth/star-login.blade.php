<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Login — Star Finance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; background: linear-gradient(160deg,#0b1f3a,#1e4fd6); display:flex; align-items:center; justify-content:center; }
    .login-card { max-width:420px; width:100%; border-radius:1.25rem; box-shadow:0 20px 50px rgba(0,0,0,.25); }
  </style>
</head>
<body class="p-3">
  <div class="card login-card border-0 p-4">
    <div class="text-center mb-4">
      <h3 class="fw-bold text-primary">Star Finance</h3>
      <p class="text-muted small mb-0">Customer portal</p>
    </div>
    <p class="small text-muted">Username: <strong>email</strong> · Default password: <strong>mobile number</strong></p>
    <form id="clientLoginForm">
      @csrf
      <div class="mb-3"><label class="form-label">Email or mobile</label><input name="login" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100" type="submit">Sign in</button>
    </form>
  <div id="loginErr" class="alert alert-danger mt-3 d-none small"></div>
    <div class="d-flex justify-content-between mt-4 small">
      <a href="{{ route('client.register') }}">Create account</a>
      <a href="{{ route('website.home') }}">Website</a>
      <a href="{{ route('login') }}">Staff login</a>
    </div>
  </div>
  <script>
  document.getElementById('clientLoginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const res = await fetch(@json(route('client.login.post')), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    if (data.success) { window.location = data.redirect; return; }
    const err = document.getElementById('loginErr');
    err.textContent = data.message || 'Login failed';
    err.classList.remove('d-none');
  });
  </script>
</body>
</html>
