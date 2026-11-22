<?php

namespace App\Http\Controllers;

use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalAuthController extends Controller
{
    public function show(Request $request, WebsiteService $website): View
    {
        $tab = $request->get('tab', 'customer') === 'staff' ? 'staff' : 'customer';

        return view('portal.login', [
            'tab' => $tab,
            'brand' => $website->brand(),
        ]);
    }
}