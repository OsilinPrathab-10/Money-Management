<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register — Star Finance</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    body { min-height:100vh; background:linear-gradient(160deg,#0b1f3a,#1e4fd6); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .reg-card { max-width:460px; width:100%; border-radius:1.25rem; box-shadow:0 20px 50px rgba(0,0,0,.25); }
  </style>
</head>
<body>
  <div class="card reg-card border-0 p-4 p-md-5">
    <div class="text-center mb-4">
      <h3 class="fw-bold text-primary mb-0">Star Finance</h3>
      <p class="text-muted small">Customer registration</p>
    </div>
    <p class="small text-muted text-center">After signup, login with <strong>email</strong> and password <strong>mobile number</strong>, then change your password in Profile.</p>
    <form method="POST" action="{{ route('client.register.post') }}">
      @csrf
      <div class="mb-3"><label class="form-label">Full name</label><input name="client_name" class="form-control" value="{{ old('client_name') }}" required>@error('client_name')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
      <div class="mb-3"><label class="form-label">Email (username)</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required>@error('email')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
      <div class="mb-3"><label class="form-label">Mobile (temporary password)</label><input name="phone" class="form-control" maxlength="10" pattern="[6-9][0-9]{9}" value="{{ old('phone') }}" required>@error('phone')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="consent" id="c" value="1" required><label class="form-check-label" for="c">I accept the terms & privacy policy</label></div>
      <button class="btn btn-primary w-100" type="submit">Create account</button>
    </form>
    <p class="small mt-4 mb-0 text-center">
      <a href="{{ route('login') }}">Already registered? Sign in</a>
      · <a href="{{ route('website.home') }}">Website</a>
    </p>
  </div>
</body>
</html>