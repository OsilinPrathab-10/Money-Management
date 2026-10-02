@php
use App\Helpers\SettingsHelper;

$configData = Helper::appClasses();
$customizerHidden = 'customizer-hide';
$adminTitle = SettingsHelper::get('admin_title', config('variables.templateName'));
$adminSubtitle = SettingsHelper::get('admin_subtitle', 'Fintech Portal');
@endphp

@extends('layouts/blankLayout')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('page-style')
@vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@include('layouts.sections.sds-auth-styles')
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js'
])
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const wrapper = document.querySelector('.authentication-wrapper');
  const phone = document.querySelector('.sds-phone');
  const circle = document.querySelector('.sds-dark-circle');

  if (wrapper && !reduceMotion) {
  wrapper.addEventListener('mousemove', function (e) {
    const x = (e.clientX / window.innerWidth - 0.5) * 2;
    const y = (e.clientY / window.innerHeight - 0.5) * 2;
    if (phone) phone.style.transform = 'translate(' + (x * 7) + 'px, ' + (y * 6) + 'px)';
    if (circle) circle.style.transform = 'translate(' + (x * -9) + 'px, ' + (y * -7) + 'px)';
  });
  wrapper.addEventListener('mouseleave', function () {
    if (phone) phone.style.transform = '';
    if (circle) circle.style.transform = '';
  });
  }

  const views = Array.from(document.querySelectorAll('.sds-app-view'));
  const tabs = Array.from(document.querySelectorAll('.sds-app-tab'));
  const payBtn = document.querySelector('.sds-pay-btn');
  const chitAction = document.querySelector('[data-app-action="chit"]');
  if (!views.length || reduceMotion) return;

  const tabFor = [0, 1, 1];
  let step = 0;
  let timer = null;

  function go(next) {
    const prev = views[step];
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

  function tick() {
    go((step + 1) % views.length);
  }

  timer = setInterval(tick, 3400);
});
</script>
@endsection


@section('content')
@include('layouts.sections.sds-auth-shell')
@endsection
