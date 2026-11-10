<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\FixedDepositScheme;
use App\Models\LoanProduct;
use App\Models\LoanType;
use App\Models\MobileAppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatalogControllerApi extends Controller
{
    public function banners(?string $module = null): JsonResponse
    {
        $setting = MobileAppSetting::getOrCreate('customer');
        $all = [
            'overview' => $this->bannerUrls($setting->banner_images ?? []),
            'loan' => $this->bannerUrls($setting->loan_banner_images ?? []),
            'fd' => $this->bannerUrls($setting->fd_banner_images ?? []),
        ];

        if ($module) {
            $key = strtolower($module);
            if ($key === 'fixed_deposit') {
                $key = 'fd';
            }
            if (! array_key_exists($key, $all)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid banner module. Use overview, loan, or fd.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => ucfirst($key) . ' banners fetched successfully',
                'data' => $all[$key],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Banners fetched successfully',
            'data' => $all,
        ]);
    }

    public function loanTypes(): JsonResponse
    {
        $loanModes = [
            ['value' => 'emi', 'label' => 'EMI', 'name' => 'Standard EMI', 'description' => 'Standard EMI (Principal + Interest)'],
            ['value' => 'interest_only', 'label' => 'Open Loan', 'name' => 'Open Loan', 'description' => 'Open Loan / Kandhuvatti (Monthly Interest Only)'],
        ];

        $types = LoanType::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhere('status', 1)->orWhere('status', 'active');
            })
            ->with(['products' => function ($q) {
                $q->where(function ($p) {
                    $p->where('status', 'active')->orWhere('status', 1);
                })->orderBy('loan_name');
            }])
            ->orderBy('name')
            ->get()
            ->map(fn (LoanType $type) => $this->formatLoanType($type));

        return response()->json([
            'success' => true,
            'message' => 'Loan types fetched successfully',
            'data' => $types,
            'loan_modes' => $loanModes,
        ]);
    }

    public function loanTypeShow(int $id): JsonResponse
    {
        $type = LoanType::with(['products' => function ($q) {
            $q->where(function ($p) {
                $p->where('status', 'active')->orWhere('status', 1);
            });
        }])->findOrFail($id);

        $loanModes = [
            ['value' => 'emi', 'label' => 'EMI', 'name' => 'Standard EMI', 'description' => 'Standard EMI (Principal + Interest)'],
            ['value' => 'interest_only', 'label' => 'Open Loan', 'name' => 'Open Loan', 'description' => 'Open Loan / Kandhuvatti (Monthly Interest Only)'],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Loan type fetched successfully',
            'data' => $this->formatLoanType($type),
            'loan_modes' => $loanModes,
        ]);
    }

    public function fdSchemes(): JsonResponse
    {
        $schemes = FixedDepositScheme::active()
            ->orderBy('name')
            ->get()
            ->map(fn (FixedDepositScheme $scheme) => $this->formatFdScheme($scheme));

        return response()->json([
            'success' => true,
            'message' => 'FD schemes fetched successfully',
            'data' => $schemes,
        ]);
    }

    public function fdSchemeShow(int $id): JsonResponse
    {
        $scheme = FixedDepositScheme::active()->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'FD scheme fetched successfully',
            'data' => $this->formatFdScheme($scheme),
        ]);
    }

    protected function formatLoanType(LoanType $type): array
    {
        $loanModes = [
            ['value' => 'emi', 'label' => 'EMI', 'name' => 'Standard EMI', 'description' => 'Standard EMI (Principal + Interest)'],
            ['value' => 'interest_only', 'label' => 'Open Loan', 'name' => 'Open Loan', 'description' => 'Open Loan / Kandhuvatti (Monthly Interest Only)'],
        ];

        return [
            'id' => $type->id,
            'name' => $type->name,
            'description' => $type->description,
            'icon' => $type->loan_type_icon_url,
            'image' => $type->loan_type_image_url,
            'banner' => $type->loan_type_banner_url,
            'loan_type_icon' => $type->loan_type_icon_url,
            'loan_type_image' => $type->loan_type_image_url,
            'loan_type_banner' => $type->loan_type_banner_url,
            'loan_modes' => $loanModes,
            'products' => $type->products->map(fn (LoanProduct $p) => $this->formatLoanProduct($p))->values(),
            'loan_products' => $type->products->map(fn (LoanProduct $p) => $this->formatLoanProduct($p))->values(),
        ];
    }

    protected function formatLoanProduct(LoanProduct $p): array
    {
        $loanModes = [
            ['value' => 'emi', 'label' => 'EMI', 'name' => 'Standard EMI', 'description' => 'Standard EMI (Principal + Interest)'],
            ['value' => 'interest_only', 'label' => 'Open Loan', 'name' => 'Open Loan', 'description' => 'Open Loan / Kandhuvatti (Monthly Interest Only)'],
        ];

        return [
            'id' => $p->id,
            'loan_name' => $p->loan_name,
            'loan_code' => $p->loan_code,
            'loan_type_id' => $p->loan_type_id,
            'loan_type_name' => optional($p->loanType)->name,
            'icon' => $p->loan_type_icon_url,
            'image' => $p->loan_type_image_url,
            'banner' => $p->loan_type_banner_url,
            'loan_type_icon' => $p->loan_type_icon_url,
            'loan_type_image' => $p->loan_type_image_url,
            'loan_type_banner' => $p->loan_type_banner_url,
            'loan_amount_min' => (float) $p->loan_amount_min,
            'loan_amount_max' => (float) $p->loan_amount_max,
            'interest_rate' => (float) $p->interest_rate,
            'interest_type' => $p->interest_type,
            'term_unit' => $p->term_unit,
            'min_tenure' => (int) ($p->min_tenture ?? 0),
            'max_tenure' => (int) ($p->max_tenture ?? 0),
            'processing_fee' => (float) ($p->processing_fee ?? 0),
            'description' => $p->description,
            'supported_loan_modes' => $loanModes,
            'loan_modes' => $loanModes,
        ];
    }

    protected function formatFdScheme(FixedDepositScheme $scheme): array
    {
        return [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'scheme_code' => $scheme->scheme_code,
            'deposit_type' => $scheme->deposit_type,
            'deposit_type_label' => $scheme->deposit_type_label,
            'interest_rate' => (float) $scheme->interest_rate,
            'interest_frequency' => $scheme->interest_frequency,
            'interest_frequency_label' => $scheme->interest_frequency_label,
            'min_deposit_amount' => (float) $scheme->min_deposit_amount,
            'max_deposit_amount' => (float) $scheme->max_deposit_amount,
            'min_tenure' => (int) $scheme->min_tenure,
            'max_tenure' => (int) $scheme->max_tenure,
            'tenure_type' => $scheme->tenure_type,
            'default_payout_option' => $scheme->default_payout_option,
            'description' => $scheme->description,
        ];
    }

    protected function optionalAuthenticatedClient(): ?Client
    {
        $request = request();
        $user = $request->user('sanctum')
            ?? $request->user()
            ?? Auth::guard('sanctum')->user()
            ?? Auth::user();
        if (! $user) {
            return null;
        }

        return $user->client
            ?? Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();
    }

    protected function bannerUrls($paths): array
    {
        return collect($paths ?? [])
            ->filter()
            ->map(fn ($path) => LoanType::formatImageUrl((string) $path))
            ->filter()
            ->values()
            ->all();
    }
}
