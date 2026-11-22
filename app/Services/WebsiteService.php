<?php

namespace App\Services;

use App\Helpers\SettingsHelper;
use App\Models\Appearance;
use App\Models\CompanyDetail;
use App\Models\LoanProduct;
use App\Models\LoanType;
use App\Models\WebsiteBanner;
use App\Models\WebsiteFaq;
use App\Models\WebsiteTestimonial;
use Illuminate\Support\Str;

class WebsiteService
{
    public function brand(): array
    {
        $appearance = Appearance::where('type', 'web')->first() ?: Appearance::first();
        $company = CompanyDetail::first();

        return [
            'name' => $company?->company_name
                ?: SettingsHelper::get('admin_title', 'Star Finance'),
            'tagline' => $company?->company_slogan
                ?: SettingsHelper::get('admin_subtitle', 'Trusted finance for every dream'),
            'email' => $company?->company_email ?? $company?->support_email ?? 'info@finora.in',
            'phone' => $company?->company_mobile ?? $company?->support_mobile ?? '+91 98765 43210',
            'logo' => $appearance ? LoanType::formatImageUrl($appearance->logo ?? null) : null,
            'logo_dark' => $appearance ? LoanType::formatImageUrl($appearance->logo_dark ?? null) : null,
            'favicon' => $appearance ? LoanType::formatImageUrl($appearance->favicon ?? null) : null,
            'footer_text' => $appearance?->footer_text ?? 'All rights reserved',
            'address' => trim(implode(', ', array_filter([
                $company?->address_line1,
                $company?->city,
                $company?->state,
                $company?->pincode,
            ]))),
        ];
    }

    /**
     * Contextual curated high-res photo or dynamic banner for each loan product / category.
     */
    public function productImage(LoanProduct $product): string
    {
        if (!empty($product->loanType?->loan_type_banner_url)) {
            return $product->loanType->loan_type_banner_url;
        }

        if (!empty($product->loanType?->loan_type_image_url)) {
            return $product->loanType->loan_type_image_url;
        }

        $haystack = strtolower($product->loan_name . ' ' . ($product->loanType?->name ?? ''));

        if (str_contains($haystack, 'home') || str_contains($haystack, 'property') || str_contains($haystack, 'house')) {
            return 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'car') || str_contains($haystack, 'vehicle') || str_contains($haystack, 'auto') || str_contains($haystack, 'bike')) {
            return 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'business') || str_contains($haystack, 'msme') || str_contains($haystack, 'working capital') || str_contains($haystack, 'commercial') || str_contains($haystack, 'enterprise')) {
            return 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'gold') || str_contains($haystack, 'jewel')) {
            return 'https://images.unsplash.com/photo-1610375461246-83df859d849d?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'education') || str_contains($haystack, 'student') || str_contains($haystack, 'study') || str_contains($haystack, 'college')) {
            return 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80';
        }
        if (str_contains($haystack, 'agri') || str_contains($haystack, 'farm') || str_contains($haystack, 'crop')) {
            return 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?auto=format&fit=crop&w=800&q=80';
        }

        return 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=800&q=80';
    }

    public function activeCategories()
    {
        return LoanType::query()
            ->where('status', 1)
            ->where('show_on_website', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['products' => fn($q) => $this->websiteProductsQuery($q)])
            ->get();
    }

    public function websiteProductsQuery($query)
    {
        return $query
            ->where('status', 'active')
            ->where('show_on_website', true)
            ->where('loan_code', '!=', LoanProduct::MANUAL_OPEN_LOAN_CODE)
            ->orderBy('sort_order')
            ->orderBy('loan_name');
    }

    public function featuredProducts(int $limit = 8)
    {
        $base = LoanProduct::query()
            ->where('status', 'active')
            ->where('show_on_website', true)
            ->where('loan_code', '!=', LoanProduct::MANUAL_OPEN_LOAN_CODE)
            ->with('loanType')
            ->orderBy('sort_order')
            ->orderBy('loan_name');

        $featured = (clone $base)->where('is_featured', true)->limit($limit)->get();

        if ($featured->isNotEmpty()) {
            return $featured;
        }

        return $base->limit($limit)->get();
    }

    /** Active products visible on the public website (loans listing, sliders, etc.). */
    public function websiteProducts(int $limit = 0)
    {
        $query = $this->websiteProductsQuery(
            LoanProduct::query()->with('loanType')
        );

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function productBySlug(string $slug): ?LoanProduct
    {
        $product = LoanProduct::query()
            ->where('web_slug', $slug)
            ->where('show_on_website', true)
            ->where('status', 'active')
            ->with('loanType')
            ->first();

        if ($product) {
            return $product;
        }

        return LoanProduct::query()
            ->where('show_on_website', true)
            ->where('status', 'active')
            ->with('loanType')
            ->get()
            ->first(fn(LoanProduct $p) => $this->productSlug($p) === $slug);
    }

    public function productSlug(LoanProduct $product): string
    {
        if (filled($product->web_slug)) {
            return $product->web_slug;
        }

        $base = Str::slug($product->loan_name);
        if ($base === '') {
            return 'product-' . $product->id;
        }

        return $base;
    }

    public function categorySlug(LoanType $type): string
    {
        if (filled($type->slug)) {
            return $type->slug;
        }

        return Str::slug($type->name) ?: 'category-' . $type->id;
    }

    public function heroBanners()
    {
        return WebsiteBanner::query()
            ->where('placement', 'home_hero')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function faqs(?string $category = null)
    {
        $q = WebsiteFaq::query()->where('is_active', true)->orderBy('sort_order');
        if ($category) {
            $q->where('category', $category);
        }

        return $q->get();
    }

    public function testimonials(int $limit = 6)
    {
        return WebsiteTestimonial::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }
}
