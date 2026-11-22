<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use App\Models\LoanType;
use App\Models\WebsiteBanner;
use App\Models\WebsiteFaq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StarFinanceWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        WebsiteBanner::firstOrCreate(
            ['placement' => 'home_hero', 'title' => 'Finance that moves with your ambitions'],
            [
                'subtitle' => 'Apply online for personal, business and specialty loans with Star Finance.',
                'cta_text' => 'Apply Now',
                'cta_url' => '/apply',
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        WebsiteFaq::firstOrCreate(
            ['question' => 'How do I apply for a loan?'],
            ['answer' => 'Choose a product under Loans, click Apply, or register as a customer and submit from your dashboard.', 'category' => 'general', 'sort_order' => 1]
        );

        $categories = [
            'Personal Finance' => 'personal-finance',
            'Business Finance' => 'business-finance',
            'Vehicle Finance' => 'vehicle-finance',
        ];

        foreach ($categories as $name => $slug) {
            $type = LoanType::firstOrCreate(
                ['name' => $name],
                ['description' => $name . ' solutions from Star Finance.', 'status' => 1]
            );
            $type->update([
                'slug' => $slug,
                'show_on_website' => true,
                'sort_order' => $type->id,
            ]);
        }

        LoanProduct::query()->where('status', 'active')->each(function (LoanProduct $p) {
            $p->update([
                'show_on_website' => $p->loan_code !== LoanProduct::MANUAL_OPEN_LOAN_CODE,
                'web_slug' => $p->web_slug ?: Str::slug($p->loan_name),
                'short_description' => $p->short_description ?: Str::limit($p->description ?? $p->loan_name, 120),
                'is_featured' => true,
            ]);
        });
    }
}
