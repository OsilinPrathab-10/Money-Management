<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanType;
use App\Models\LoanProduct;

class LoanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $loanTypes = [
            'home_loan' => [
                'name' => 'Home Loan',
                'description' => 'Long-term financing for residential property purchase or construction.',
                'loan_type_icon' => 'app/public/loan-products/loan-icons/home-loan.png',
                'loan_type_image' => 'app/public/loan-products/loan-images/home-loan.png',
                'loan_type_banner' => 'app/public/loan-products/loan-banners/Home-loan-banner.png',
                'status' => true,
            ],
            'personal_loan' => [
                'name' => 'Personal Loan',
                'description' => 'Unsecured loan for personal expenses like travel, medical, or gadgets.',
                'loan_type_icon' => 'app/public/loan-products/loan-icons/personal-loan.png',
                'loan_type_image' => 'app/public/loan-products/loan-images/personal-loan.png',
                'loan_type_banner' => 'app/public/loan-products/loan-banner/Personal-loan.png',
                'status' => true,
            ],
            'vehicle_loan' => [
                'name' => 'Vehicle Loan',
                'description' => 'Financing solutions for new or pre-owned vehicles.',
                'loan_type_icon' => 'app/public/loan-products/loan-icons/vehicle-loan.png',
                'loan_type_image' => 'app/public/loan-products/loan-images/vehicle-loan.png',
                'loan_type_banner' => 'app/public/loan-products/loan-banner/vehicle-loan.png',
                'status' => true,
            ],
            'business_loan' => [
                'name' => 'Business Loan',
                'description' => 'Working capital and expansion funding for small and medium enterprises.',
                'loan_type_icon' => 'app/public/loan-products/loan-icons/business-loan.png',
                'loan_type_image' => 'app/public/loan-products/loan-images/business-loan.png',
                'loan_type_banner' => 'app/public/loan-products/loan-banner/Business-loan.png',
                'status' => true,
            ],
            'educational_loan' => [
                'name' => 'Educational Loan',
                'description' => 'Loan products that support higher education in India or abroad.',
                'loan_type_icon' => 'app/public/loan-products/loan-icons/education-loan.png',
                'loan_type_image' => 'app/public/loan-products/loan-images/educational-loan.png',
                'loan_type_banner' => 'app/public/loan-products/loan-banner/Educational-Banner.png',
                'status' => true,
            ],
        ];

        $loanTypeIds = [];
        foreach ($loanTypes as $key => $payload) {
            $loanTypeIds[$key] = LoanType::updateOrCreate(
                ['name' => $payload['name']],
                $payload
            )->id;
        }

        $loanProducts = [
            [
                'loan_type_key' => 'home_loan',
                'loan_name' => 'Home Loan',
                'loan_code' => 'HLN-001',
                'loan_amount_min' => 1000,
                'loan_amount_max' => 5000000,
                'interest_rate' => 8.25,
                'interest_type' => 'reducing',
                'term_unit' => 'months',
                'min_tenture' => 12,
                'max_tenture' => 60,
                'processing_fee' => 0.00,
                'require_collateral' => true,
                'default_term' => 24,
                'description' => 'The Borrower agrees that the Home Loan shall be used for the purchase, construction, or renovation of the residential property identified herein. The property shall serve as collateral until the loan is fully repaid. The Borrower agrees to maintain property insurance, pay all applicable taxes, and keep the property in good condition. Any failure to make timely payments may result in foreclosure proceedings in accordance with applicable laws and the terms of this Agreement.',
                'status' => 'active',
            ],
            [
                'loan_type_key' => 'personal_loan',
                'loan_name' => 'Personal Loan',
                'loan_code' => 'PLN-002',
                'loan_amount_min' => 1000,
                'loan_amount_max' => 2000000,
                'interest_rate' => 13.5,
                'interest_type' => 'flat',
                'term_unit' => 'months',
                'min_tenture' => 12,
                'max_tenture' => 60,
                'processing_fee' => 0.00,
                'require_collateral' => false,
                'default_term' => 36,
                'description' => 'The Borrower agrees to repay the Personal Loan in the amounts and on the dates specified in this Agreement. The funds provided shall be used solely for personal, non-commercial purposes. The Borrower acknowledges that failure to make timely payments may result in additional fees and penalties as specified herein. The Borrower warrants that all information provided in connection with this loan is accurate and agrees to notify the Lender of any material changes to their financial situation.',
                'status' => 'active',
            ],
            [
                'loan_type_key' => 'vehicle_loan',
                'loan_name' => 'Vehicle Loan',
                'loan_code' => 'VLN-003',
                'loan_amount_min' => 1000,
                'loan_amount_max' => 2500000,
                'interest_rate' => 9.75,
                'interest_type' => 'reducing',
                'term_unit' => 'months',
                'min_tenture' => 12,
                'max_tenture' => 60,
                'processing_fee' => 0.00,
                'require_collateral' => true,
                'default_term' => 60,
                'description' => 'The Borrower agrees that the Vehicle Loan shall be used exclusively for the purchase of the vehicle described in this Agreement. The vehicle shall remain the collateral for the duration of the loan, and the Borrower agrees to maintain proper insurance coverage and keep the vehicle in good working condition. Title to the vehicle shall remain encumbered until full repayment of the loan. Any default in payment may result in repossession as permitted by applicable law.',
                'status' => 'active',
            ],
            [
                'loan_type_key' => 'business_loan',
                'loan_name' => 'Business Loan',
                'loan_code' => 'BLN-004',
                'loan_amount_min' => 1000,
                'loan_amount_max' => 10000000,
                'interest_rate' => 11.25,
                'interest_type' => 'reducing',
                'term_unit' => 'months',
                'min_tenture' => 12,
                'max_tenture' => 60,
                'processing_fee' => 0.00,
                'require_collateral' => true,
                'default_term' => 60,
                'description' => 'The Borrower acknowledges receipt of the Business Loan and agrees to utilize the funds solely for legitimate business purposes, including capital expansion, operational expenses, and other activities directly related to the Borrower’s business operations. The Borrower agrees to repay the principal amount along with applicable interest in accordance with the repayment schedule outlined herein. The Borrower further agrees to provide financial statements or other documentation upon request and to maintain compliance with all applicable laws and regulations throughout the loan term.',
                'status' => 'active',
            ],
            [
                'loan_type_key' => 'educational_loan',
                'loan_name' => 'Education Loan',
                'loan_code' => 'ELN-005',
                'loan_amount_min' => 1000,
                'loan_amount_max' => 4000000,
                'interest_rate' => 10.5,
                'interest_type' => 'reducing',
                'term_unit' => 'months',
                'min_tenture' => 12,
                'max_tenture' => 60,
                'processing_fee' => 0.00,
                'require_collateral' => false,
                'default_term' => 60,
                'description' => 'The Borrower acknowledges that the Education Loan is intended for tuition, educational fees, books, accommodation, and other academic-related expenses. The Borrower agrees to repay the loan following the repayment schedule, which may include a grace period as defined in this Agreement. The Borrower also agrees to provide enrollment verification and notify the Lender of any changes in academic status. Failure to comply with repayment obligations may result in interest capitalization or other actions as allowed by law.',
                'status' => 'active',
            ],
        ];

        foreach ($loanProducts as $product) {
            $loanTypeId = $loanTypeIds[$product['loan_type_key']] ?? null;

            if (! $loanTypeId) {
                continue;
            }

            LoanProduct::updateOrCreate(
                ['loan_code' => $product['loan_code']],
                [
                    'loan_name' => $product['loan_name'],
                    'loan_type_id' => $loanTypeId,
                    'loan_amount_min' => $product['loan_amount_min'],
                    'loan_amount_max' => $product['loan_amount_max'],
                    'interest_rate' => $product['interest_rate'],
                    'interest_type' => $product['interest_type'],
                    'term_unit' => $product['term_unit'],
                    'min_tenture' => $product['min_tenture'],
                    'max_tenture' => $product['max_tenture'],
                    'processing_fee' => $product['processing_fee'],
                    'require_collateral' => $product['require_collateral'],
                    'default_term' => $product['default_term'],
                    'description' => $product['description'],
                    'status' => $product['status'],
                ]
            );
        }
    }
}
