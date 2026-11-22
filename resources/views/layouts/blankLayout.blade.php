@isset($pageConfigs)
  {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

@php
  $customizerHidden = 'customizer-hide';
  $configData = Helper::appClasses();
  $configData['hasCustomizer'] = false;
@endphp

@extends('layouts/commonMaster')

@section('layoutContent')
  <!-- Content -->
  @yield('content')
  <!--/ Content -->
@endsection
