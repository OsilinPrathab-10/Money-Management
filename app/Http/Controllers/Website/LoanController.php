<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\LoanType;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(WebsiteService $website): View
    {
        $categories = $website->activeCategories();

        return view('website.loans.index', [
            'brand' => $website->brand(),
            'categories' => $categories,
        ]);
    }

    public function category(string $slug, WebsiteService $website): View
    {
        $category = LoanType::query()
            ->where('status', 1)
            ->where('show_on_website', true)
            ->get()
            ->first(fn (LoanType $t) => $website->categorySlug($t) === $slug);

        abort_unless($category, 404);

        $products = $website->websiteProductsQuery($category->products())->get();

        return view('website.loans.category', compact('category', 'products', 'website') + [
            'brand' => $website->brand(),
        ]);
    }

    public function show(string $slug, WebsiteService $website): View
    {
        $product = $website->productBySlug($slug);
        abort_unless($product, 404);

        $related = $website->websiteProductsQuery(
            $product->loanType?->products()->where('id', '!=', $product->id) ?? $product->newQuery()->whereRaw('0=1')
        )->limit(4)->get();

        return view('website.loans.show', [
            'brand' => $website->brand(),
            'product' => $product,
            'website' => $website,
            'related' => $related,
            'faqs' => $website->faqs('loans'),
        ]);
    }
}
