@php
  use App\Helpers\SettingsHelper;
  $isLockOverlay = true;
  $adminTitle = SettingsHelper::get('admin_title', config('variables.templateName'));
  $adminSubtitle = SettingsHelper::get('admin_subtitle', 'Fintech Portal');
@endphp
@include('layouts.sections.sds-auth-styles')
@include('layouts.sections.sds-auth-shell')
<script>
(function () {
  'use strict';

  if (window.__sdsSessionLockInstalled) {
    return;
  }
  window.__sdsSessionLockInstalled = true;

  var ACTIVITY_KEY = 'sds.session.lastActivity';
  var LOCKED_KEY = 'sds.session.locked';
  var cfg = window.SDS_SESSION || {};
  var idleMs = Number(cfg.idleMs) > 0 ? Number(cfg.idleMs) : 30 * 60 * 1000;
  var lockEnabled = !!cfg.lockEnabled;
  var redirecting = false;
  var locked = false;
  var lastWrite = 0;

  function isAuthPage() {
    var path = (window.location.pathname || '').replace(/\/+$/, '');
    return /(^|\/)(login|forgot-password|reset-password)/i.test(path);
  }

  function loginUrl(reason) {
    var url = cfg.loginUrl || '/login';
    var sep = url.indexOf('?') >= 0 ? '&' : '?';
    return url + sep + (reason || 'expired') + '=1';
  }

  function clearLockStorage() {
    try {
      localStorage.removeItem(ACTIVITY_KEY);
      localStorage.removeItem(LOCKED_KEY);
    } catch (e) { /* ignore */ }
  }

  function goLogin(reason) {
    if (redirecting || isAuthPage()) {
      return;
    }
    var dest = loginUrl(reason);
    try {
      var next = new URL(dest, window.location.origin);
      if (window.location.pathname.replace(/\/+$/, '') === next.pathname.replace(/\/+$/, '')) {
        return;
      }
    } catch (e) { /* ignore */ }
    redirecting = true;
    locked = false;
    clearLockStorage();
    window.location.replace(dest);
  }

  window.SDSSessionLock = {
    onAuthFailure: function () { goLogin('expired'); },
    goLogin: goLogin
  };

  function looksLikeLoginHtml(xhr) {
    if (!xhr) return false;
    var text = xhr.responseText || '';
    if (!text || text.charAt(0) === '{' || text.charAt(0) === '[') return false;
    return text.indexOf('id="formAuthentication"') !== -1
      || (text.indexOf('sds-welcome') !== -1 && text.indexOf('Sign In') !== -1);
  }

  function isSessionDead(xhr) {
    if (!xhr) return false;
    if (xhr.status === 401 || xhr.status === 419) return true;
    return looksLikeLoginHtml(xhr);
  }

  function installDtErrMode() {
    var $ = window.jQuery;
    if (!$ || !$.fn) return false;
    var dt = $.fn.dataTable || $.fn.DataTable;
    if (!dt || !dt.ext) return false;
    dt.ext.errMode = function (settings, tn, msg) {
      var xhr = settings && (settings.jqXHR || settings.jqXhr || null);
      if (isSessionDead(xhr)) {
        goLogin('expired');
        return;
      }
      if (typeof console !== 'undefined' && console.error) console.error(msg);
    };
    return true;
  }

  function bindJqueryAjax() {
    var $ = window.jQuery;
    if (!$ || $.__sdsLockAjax) return !!$;
    $.__sdsLockAjax = true;
    $(document).ajaxError(function (event, xhr) {
      if (isSessionDead(xhr)) goLogin('expired');
    });
    $(document).ajaxSuccess(function (event, xhr) {
      if (looksLikeLoginHtml(xhr)) goLogin('expired');
    });
    return true;
  }

  function hookAxios() {
    if (!window.axios || !window.axios.interceptors || window.axios.__sdsLockHooked) return !!window.axios;
    window.axios.__sdsLockHooked = true;
    window.axios.interceptors.response.use(function (res) { return res; }, function (err) {
      var status = err && err.response && err.response.status;
      if (status === 401 || status === 419) goLogin('expired');
      return Promise.reject(err);
    });
    return true;
  }

  function guardDatatablesAlert() {
    if (window.__sdsAlertWrapped) return;
    window.__sdsAlertWrapped = true;
    var nativeAlert = window.alert;
    window.alert = function (msg) {
      if (typeof msg === 'string' && /DataTables warning/i.test(msg) && /Ajax error/i.test(msg)) {
        goLogin('expired');
        return;
      }
      return nativeAlert.apply(window, arguments);
    };
  }

  var lockChromeTimer = null;

  function startLockChrome() {
    var root = overlay();
    if (!root || root.__sdsChrome) return;
    root.__sdsChrome = true;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var phone = root.querySelector('.sds-phone');
    var circle = root.querySelector('.sds-dark-circle');
    if (!reduceMotion) {
      root.addEventListener('mousemove', function (e) {
        var x = (e.clientX / window.innerWidth - 0.5) * 2;
        var y = (e.clientY / window.innerHeight - 0.5) * 2;
        if (phone) phone.style.transform = 'translate(' + (x * 7) + 'px, ' + (y * 6) + 'px)';
        if (circle) circle.style.transform = 'translate(' + (x * -9) + 'px, ' + (y * -7) + 'px)';
      });
    }
    var views = Array.from(root.querySelectorAll('.sds-app-view'));
    var tabs = Array.from(root.querySelectorAll('.sds-app-tab'));
    var payBtn = root.querySelector('.sds-pay-btn');
    var chitAction = root.querySelector('[data-app-action="chit"]');
    if (!views.length || reduceMotion) return;
    var tabFor = [0, 1, 1];
    var step = 0;
    function go(next) {
      var prev = views[step];
      step = next;
      views.forEach(function (view, idx) {
        view.classList.toggle('is-leave', idx !== step && view.classList.contains('is-active'));
        view.classList.toggle('is-active', idx === step);
      });
      tabs.forEach(function (tab, idx) {
        tab.classList.toggle('is-active', idx === tabFor[step]);
      });
      if (payBtn) payBtn.classList.remove('is-paying');
      if (chitAction) chitAction.classList.remove('is-hit');
      if (step === 1) {
        if (chitAction) chitAction.classList.add('is-hit');
        setTimeout(function () { if (payBtn) payBtn.classList.add('is-paying'); }, 900);
      }
      if (prev) setTimeout(function () { prev.classList.remove('is-leave'); }, 450);
    }
    lockChromeTimer = setInterval(function () { go((step + 1) % views.length); }, 3400);
  }

  function overlay() {
    return document.getElementById('sds-session-lock');
  }

  function mountOverlay(el) {
    if (el && el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
    return el;
  }

  function setLockedUi(on) {
    locked = on;
    var el = mountOverlay(overlay());
    if (!el) return;
    if (on) {
      el.hidden = false;
      el.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('sds-session-locked');
      try { localStorage.setItem(LOCKED_KEY, '1'); } catch (e) {}
      startLockChrome();
      var input = document.getElementById('sds-lock-password');
      if (input) {
        input.value = '';
        setTimeout(function () { input.focus(); }, 50);
      }
      var err = document.getElementById('sds-lock-error');
      if (err) err.textContent = '';
      var btn = document.getElementById('sds-lock-submit');
      if (btn) {
        btn.disabled = false;
        delete btn.dataset.busy;
      }
    } else {
      el.hidden = true;
      el.setAttribute('aria-hidden', 'true');
      document.documentElement.classList.remove('sds-session-locked');
      try { localStorage.removeItem(LOCKED_KEY); } catch (e) {}
    }
  }

  function readActivity() {
    try {
      var n = parseInt(localStorage.getItem(ACTIVITY_KEY) || '0', 10);
      return n > 0 ? n : 0;
    } catch (e) { return 0; }
  }

  function touchActivity(force) {
    if (locked || !lockEnabled) return;
    var now = Date.now();
    if (!force && now - lastWrite < 4000) return;
    lastWrite = now;
    try { localStorage.setItem(ACTIVITY_KEY, String(now)); } catch (e) {}
  }

  function isIdle() {
    var last = readActivity();
    if (!last) return false;
    return Date.now() - last >= idleMs;
  }

  function wasLocked() {
    try { return localStorage.getItem(LOCKED_KEY) === '1'; } catch (e) { return false; }
  }

  function ping() {
    if (!cfg.pingUrl) return Promise.resolve(false);
    return fetch(cfg.pingUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (res) { return res.ok; }).catch(function () { return false; });
  }

  function enterLockOrLogin() {
    if (!lockEnabled || redirecting || isAuthPage()) return;
    ping().then(function (alive) {
      if (!alive) { goLogin('expired'); return; }
      setLockedUi(true);
    });
  }

  function checkIdle() {
    if (!lockEnabled || locked || redirecting) return;
    if (wasLocked() || isIdle()) enterLockOrLogin();
  }

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return (meta && meta.getAttribute('content')) || cfg.csrf || '';
  }

  function updateCsrf(token) {
    if (!token) return;
    cfg.csrf = token;
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', token);
    var logoutForm = document.getElementById('sds-lock-logout');
    if (logoutForm) {
      var input = logoutForm.querySelector('input[name="_token"]');
      if (input) input.value = token;
    }
    if (window.jQuery) window.jQuery.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
    if (window.axios && window.axios.defaults) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
  }

  function bindLockForm() {
    var form = document.getElementById('sds-lock-form');
    if (!form || form.__sdsBound) return;
    form.__sdsBound = true;

    var eye = document.getElementById('sds-lock-eye');
    if (eye) {
      eye.addEventListener('click', function () {
        var input = document.getElementById('sds-lock-password');
        var icon = eye.querySelector('i');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        if (icon) {
          icon.classList.toggle('ri-eye-line', show);
          icon.classList.toggle('ri-eye-off-line', !show);
        }
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = document.getElementById('sds-lock-password');
      var err = document.getElementById('sds-lock-error');
      var btn = document.getElementById('sds-lock-submit');
      var password = input ? input.value : '';
      if (!password) {
        if (err) err.textContent = 'Enter your password to unlock.';
        return;
      }
      if (btn) btn.disabled = true;
      if (err) err.textContent = '';

      fetch(cfg.unlockUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ password: password })
      }).then(function (res) {
        return res.json().then(function (body) {
          return { res: res, body: body };
        }).catch(function () {
          return { res: res, body: {} };
        });
      }).then(function (pack) {
        if (pack.res.status === 401 || pack.res.status === 419) {
          goLogin('expired');
          return;
        }
        if (!pack.res.ok) {
          var msg = (pack.body && pack.body.message)
            || (pack.body && pack.body.errors && pack.body.errors.password && pack.body.errors.password[0])
            || 'The password you entered is incorrect.';
          if (err) err.textContent = msg;
          if (btn) {
            btn.disabled = false;
            delete btn.dataset.busy;
          }
          return;
        }
        if (pack.body && pack.body.csrf_token) updateCsrf(pack.body.csrf_token);
        setLockedUi(false);
        touchActivity(true);
        if (btn) {
          btn.disabled = false;
          delete btn.dataset.busy;
        }
      }).catch(function () {
        if (err) err.textContent = 'Could not unlock. Please sign in again.';
        if (btn) {
          btn.disabled = false;
          delete btn.dataset.busy;
        }
      });
    });
  }

  function bindActivity() {
    ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click', 'wheel'].forEach(function (evt) {
      document.addEventListener(evt, function () { touchActivity(false); }, { passive: true, capture: true });
    });
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') checkIdle();
    });
    window.addEventListener('pageshow', function () { checkIdle(); });
    window.addEventListener('storage', function (e) {
      if (e.key === LOCKED_KEY && e.newValue === '1') enterLockOrLogin();
      if (e.key === LOCKED_KEY && !e.newValue && locked) {
        setLockedUi(false);
        touchActivity(true);
      }
    });
  }

  function start() {
    if (isAuthPage() || !lockEnabled) {
      clearLockStorage();
      return;
    }
    mountOverlay(overlay());
    var emailInput = document.getElementById('sds-lock-email');
    if (emailInput && cfg.email && !emailInput.value) emailInput.value = cfg.email;
    bindLockForm();
    bindActivity();
    if (!readActivity()) touchActivity(true);
    if (wasLocked() || isIdle()) enterLockOrLogin();
    setInterval(checkIdle, 10000);
  }

  guardDatatablesAlert();

  var tries = 0;
  var boot = setInterval(function () {
    bindJqueryAjax();
    installDtErrMode();
    hookAxios();
    tries += 1;
    if (tries > 80) clearInterval(boot);
  }, 50);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
</script>
