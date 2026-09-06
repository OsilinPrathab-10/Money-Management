<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ErrorPageController extends Controller
{
  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.errors pages.401', ['pageConfigs' => $pageConfigs]);
  }

  public function miscUnderMaintenance()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.errors pages.503', ['pageConfigs' => $pageConfigs]);
  }

  public function miscComingSoon()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.errors pages.404', ['pageConfigs' => $pageConfigs]);
  }

  public function miscNotAuthorized()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.errors pages.403', ['pageConfigs' => $pageConfigs]);
  }

  public function miscServerError()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.errors pages.500', ['pageConfigs' => $pageConfigs]);
  }
}
