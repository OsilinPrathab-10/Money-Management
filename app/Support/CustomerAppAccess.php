<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CompanyDetail;
use Illuminate\Http\JsonResponse;

class CustomerAppAccess
{
    /**
     * Admin-inactivated (and similar) clients cannot use the customer app.
     */
    public static function isBlocked(?Client $client): bool
    {
        return $client !== null && ! $client->canAccessCustomerApp();
    }

    public static function companyContactNumber(): string
    {
        try {
            $company = CompanyDetail::query()->first();
            $phone = trim((string) (
                $company?->company_mobile
                ?: $company?->support_mobile
                ?: $company?->alternate_mobile
                ?: ''
            ));
            if ($phone !== '') {
                return $phone;
            }
        } catch (\Throwable) {
            // company_details may be missing in some environments
        }

        if (function_exists('get_setting')) {
            $phone = trim((string) get_setting('company_phone', get_setting('support_mobile', '')));
            if ($phone !== '') {
                return $phone;
            }
        }

        return '';
    }

    public static function deniedMessage(?string $phone = null): string
    {
        $phone = $phone ?? self::companyContactNumber();

        if ($phone !== '') {
            return 'Your account is inactive. Please contact the company at ' . $phone . '.';
        }

        return 'Your account is inactive. Please contact the company.';
    }

    public static function deniedResponse(?Client $client = null): JsonResponse
    {
        $phone = self::companyContactNumber();
        $clientStatus = strtolower((string) ($client?->status ?: 'inactive'));

        return response()->json([
            'status' => false,
            'is_active' => false,
            'client_status' => $clientStatus,
            'message' => self::deniedMessage($phone),
            'company_phone' => $phone,
            'contact_number' => $phone,
            'code' => 'CLIENT_INACTIVE',
        ], 403);
    }
}
