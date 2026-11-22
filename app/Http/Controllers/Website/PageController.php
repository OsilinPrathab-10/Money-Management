<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Services\WebsiteService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(WebsiteService $website): View
    {
        return view('website.pages.about', ['brand' => $website->brand()]);
    }

    public function faq(WebsiteService $website): View
    {
        return view('website.pages.faq', [
            'brand' => $website->brand(),
            'faqs' => $website->faqs(),
        ]);
    }

    public function services(WebsiteService $website): View
    {
        return view('website.pages.services', [
            'brand' => $website->brand(),
            'categories' => $website->activeCategories(),
        ]);
    }
}
