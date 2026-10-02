<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\FixedDepositScheme;
use App\Models\GroupMember;
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
            'chit' => $this->bannerUrls($setting->chit_banner_images ?? []),
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
                    'message' => 'Invalid banner module. Use overview, loan, chit, or fd.',
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

    public function chitSchemes(): JsonResponse
    {
        $schemes = ChitScheme::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (ChitScheme $scheme) => $this->formatChitScheme($scheme, false));

        return response()->json([
            'success' => true,
            'message' => 'Chit schemes fetched successfully',
            'data' => $schemes,
        ]);
    }

    public function chitSchemeShow(Request $request, int $id): JsonResponse
    {
        $scheme = ChitScheme::where('status', 'active')->findOrFail($id);
        $withGroups = $request->has('include_groups') ? $request->boolean('include_groups') : true;

        return response()->json([
            'success' => true,
            'message' => 'Chit scheme fetched successfully',
            'data' => $this->formatChitScheme($scheme, $withGroups),
        ]);
    }

    public function schemeGroups(Request $request, int $schemeId): JsonResponse
    {
        $scheme = ChitScheme::where('status', 'active')->findOrFail($schemeId);
        $status = $request->filled('status') ? $request->input('status') : null;
        $groups = $this->availableGroups($status, $scheme->id);

        return response()->json([
            'success' => true,
            'message' => 'Scheme wise groups fetched successfully',
            'scheme' => [
                'id' => $scheme->id,
                'name' => $scheme->name,
                'scheme_code' => $scheme->scheme_code,
                'duration_months' => (int) $scheme->duration_months,
                'foreman_commission_month' => (int) ($scheme->foreman_commission_month ?? 1),
                'client_wise_foreman_commission' => $scheme->usesClientWiseForemanCommission() ? $scheme->clientWiseForemanAmount() : null,
                'client_wise_foreman_collection_month' => $scheme->usesClientWiseForemanCommission() ? $scheme->clientWiseForemanCollectionMonth() : null,
            ],
            'data' => $groups,
        ]);
    }

    public function chitGroups(Request $request): JsonResponse
    {
        $status = $request->filled('status') ? $request->input('status') : null;
        $schemeId = $request->filled('scheme_id') ? (int) $request->input('scheme_id') : null;
        $groups = $this->availableGroups($status, $schemeId);

        return response()->json([
            'success' => true,
            'message' => 'Available chit groups fetched successfully',
            'data' => $groups,
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

    public function formatChitScheme(ChitScheme $scheme, bool $withGroups = false): array
    {
        $data = [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'scheme_code' => $scheme->scheme_code,
            'chit_value' => (float) $scheme->chit_value,
            'installment_amount' => (float) $scheme->apiInstallmentAmount(),
            'total_members' => (int) $scheme->total_members,
            'duration_months' => (int) $scheme->duration_months,
            'scheme_type' => $scheme->scheme_type,
            'scheme_type_label' => $scheme->scheme_type_label,
            'installment_frequency' => $scheme->installment_frequency,
            'commission_pct' => (float) $scheme->commission_pct,
            'commission_amount' => (float) $scheme->commission_amount,
            'foreman_commission_month' => (int) ($scheme->foreman_commission_month ?? 1),
            'client_wise_foreman_commission' => $scheme->usesClientWiseForemanCommission() ? $scheme->clientWiseForemanAmount() : null,
            'client_wise_foreman_collection_month' => $scheme->usesClientWiseForemanCommission() ? $scheme->clientWiseForemanCollectionMonth() : null,
            'description' => $scheme->description,
            'flyer_url' => $scheme->flyerUrl(),
            'flyer_pdf_url' => $scheme->flyerPdfUrl(),
            'registration_type' => $scheme->registration_type,
            'registration_type_label' => $scheme->registration_type_label,
            'registration_number' => $scheme->registration_number,
            'payout_schedule' => $scheme->payout_schedule ?? [],
        ];

        if ($withGroups) {
            $data['groups'] = $this->availableGroups(null, $scheme->id)->values();
        }

        return $data;
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

    public function availableGroups(?string $status = null, ?int $schemeId = null)
    {
        $statusFilter = $status ? strtolower(trim($status)) : null;
        if ($statusFilter === 'reject') {
            $statusFilter = 'rejected';
        }

        $memberStatuses = ['applied', 'rejected', 'approved', 'active'];
        $filterByApplication = $statusFilter && in_array($statusFilter, $memberStatuses, true);

        $query = ChitGroup::with('scheme');

        if ($schemeId) {
            $query->where('scheme_id', $schemeId);
        }

        if ($filterByApplication || !$statusFilter) {
            $query->whereIn('status', ['forming', 'active']);
        } elseif ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $collection = $query->withCount(['members' => fn ($q) => $q->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])])
            ->orderByDesc('id')
            ->get();

        $client = $this->optionalAuthenticatedClient();
        $membersByGroup = collect();
        if ($client && $collection->isNotEmpty()) {
            $membersByGroup = GroupMember::with('group')
                ->where('client_id', $client->id)
                ->whereIn('group_id', $collection->pluck('id'))
                ->orderByDesc('id')
                ->get()
                ->unique('group_id')
                ->keyBy('group_id');
        }

        if (!$statusFilter) {
            $collection = $collection->filter(function (ChitGroup $g) use ($membersByGroup) {
                if ($membersByGroup->has($g->id)) {
                    return true;
                }

                return $g->members_count < $g->total_members;
            });
        }

        if ($filterByApplication) {
            $collection = $collection->filter(function (ChitGroup $g) use ($membersByGroup, $statusFilter) {
                $member = $membersByGroup->get($g->id);
                $applicationStatus = $member ? $member->customerFacingStatus() : null;

                return $applicationStatus === $statusFilter;
            });
        }

        return $collection->map(function (ChitGroup $g) use ($membersByGroup) {
            $member = $membersByGroup->get($g->id);
            $applicationStatus = $member ? $member->customerFacingStatus() : null;
            if ($applicationStatus === 'reject') {
                $applicationStatus = 'rejected';
            }

            return [
                'id' => $g->id,
                'group_code' => $g->group_code,
                'scheme_id' => $g->scheme_id,
                'scheme_name' => optional($g->scheme)->name,
                'status' => $applicationStatus ?: $g->status,
                'status_label' => $member ? $member->customerFacingStatusLabel() : ucfirst((string) $g->status),
                'status_badge' => $member ? $member->customerFacingStatusBadge() : ($g->status_badge ?? 'secondary'),
                'group_status' => $g->status,
                'application_status' => $applicationStatus,
                'is_applied' => $applicationStatus === 'applied',
                'is_rejected' => $applicationStatus === 'rejected',
                'chit_value' => (float) $g->chit_value,
                'installment_amount' => $g->apiInstallmentAmount(),
                'total_members' => (int) $g->total_members,
                'available_slots' => max(0, (int) $g->total_members - (int) $g->members_count),
                'total_months' => (int) $g->total_months,
                'current_month' => (int) ($g->current_month ?? 0),
                'month_number' => (int) ($g->current_month ?? 1),
                'month_label' => 'Month ' . ((int) ($g->current_month ?? 1)) . ' — ' . $g->periodCalendarLabel((int) ($g->current_month ?? 1)),
                'foreman_commission_month' => $g->foremanCommissionMonth(),
                'client_wise_foreman_commission' => $g->usesClientWiseForemanCommission() ? $g->scheme->clientWiseForemanAmount() : null,
                'client_wise_foreman_collection_month' => $g->usesClientWiseForemanCommission() ? $g->scheme->clientWiseForemanCollectionMonth() : null,
                'months_list' => collect(range(1, max(1, (int) $g->total_months)))->map(fn($i) => [
                    'month_number' => $i,
                    'month_label' => 'Month ' . $i . ' — ' . $g->periodCalendarLabel($i),
                ])->toArray(),
            ];
        })->values();
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
