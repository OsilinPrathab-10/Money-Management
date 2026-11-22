<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EligibilityController extends Controller
{
    public function show(WebsiteService $website): View
    {
        $products = $website->websiteProductsQuery(
            LoanProduct::query()->with('loanType')
        )->get();

        return view('website.eligibility', [
            'brand' => $website->brand(),
            'products' => $products,
            'result' => null,
        ]);
    }

    public function check(Request $request, WebsiteService $website): View
    {
        $data = $request->validate([
            'loan_product_id' => 'required|exists:loan_products,id',
            'age' => 'required|integer|min:18|max:80',
            'monthly_income' => 'required|integer|min:0',
            'employment_type' => 'nullable|string|max:64',
        ]);

        $product = LoanProduct::with('loanType')->findOrFail($data['loan_product_id']);
        $reasons = [];
        $eligible = true;

        if ($product->min_age && $data['age'] < $product->min_age) {
            $eligible = false;
            $reasons[] = __('Minimum age is :age years.', ['age' => $product->min_age]);
        }
        if ($product->max_age && $data['age'] > $product->max_age) {
            $eligible = false;
            $reasons[] = __('Maximum age is :age years.', ['age' => $product->max_age]);
        }
        if ($product->min_monthly_income && $data['monthly_income'] < $product->min_monthly_income) {
            $eligible = false;
            $reasons[] = __('Minimum monthly income is ₹:amount.', ['amount' => number_format($product->min_monthly_income)]);
        }

        $products = $website->websiteProductsQuery(
            LoanProduct::query()->with('loanType')
        )->get();

        return view('website.eligibility', [
            'brand' => $website->brand(),
            'products' => $products,
            'result' => [
                'eligible' => $eligible,
                'reasons' => $reasons,
                'product' => $product,
                'input' => $data,
            ],
        ]);
    }
}