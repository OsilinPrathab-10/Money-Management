<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Services\WebsiteService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(WebsiteService $website): View
    {
        return view('website.home', [
            'brand' => $website->brand(),
            'banners' => $website->heroBanners(),
            'featuredProducts' => $website->featuredProducts(),
            'categories' => $website->activeCategories(),
            'testimonials' => $website->testimonials(),
            'faqs' => $website->faqs()->take(6),
        ]);
    }
}
