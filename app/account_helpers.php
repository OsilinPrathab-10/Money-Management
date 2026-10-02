<?php

/**
 * ERP-style helper used by the integrated Account module (WorkDo-compatible).
 */
if (! function_exists('creatorId')) {
    function creatorId(): ?int
    {
        if (auth()->check()) {
            // If the user is an Admin, they are their own creator/owner
            if (auth()->user()->hasRole('Admin')) {
                return auth()->id();
            }
            
            // For staff or other roles, fall back to the first Admin user (Company Owner)
            // This ensures all employees share the same Chart of Accounts scope.
            $admin = \App\Models\User::role('Admin')->first();
            if ($admin) {
                return $admin->id;
            }
        }
        return auth()->id();
    }
}

if (! function_exists('get_setting')) {
    /**
     * Get a setting value by key with optional fallback to CompanyDetail or default value.
     */
    function get_setting($key, $default = null)
    {
        try {
            // 1. Try AdminSetting model first
            try {
                if (class_exists('\App\Models\AdminSetting')) {
                    $value = \App\Models\AdminSetting::get($key);
                    if ($value !== null) {
                        return $value;
                    }
                }
            } catch (\Exception $e) {
                // Ignore missing table or other errors for AdminSetting and proceed to fallbacks
            }

            // 2. Fallback for company-specific details from CompanyDetail model
            $companyFields = [
                'company_email', 'company_name', 'company_phone', 
                'support_email', 'support_mobile', 'company_address'
            ];
            
            if (in_array($key, $companyFields) && class_exists('\App\Models\CompanyDetail')) {
                $company = \App\Models\CompanyDetail::first();
                if ($company) {
                    $fieldMap = [
                        'company_email' => 'company_email',
                        'company_name'  => 'company_name',
                        'company_phone' => 'company_mobile',
                        'support_email' => 'support_email',
                        'support_mobile' => 'support_mobile',
                        'company_address' => 'address_line1'
                    ];
                    $field = $fieldMap[$key] ?? $key;
                    if (isset($company->$field) && $company->$field !== null && $company->$field !== '') {
                        return $company->$field;
                    }
                }
            }
            
            // 3. Fallback to Appearance settings if applicable
            if (class_exists('\App\Helpers\SettingsHelper')) {
                $appearanceValue = \App\Helpers\SettingsHelper::get($key);
                if ($appearanceValue !== null && $appearanceValue !== config("variables.{$key}")) {
                    return $appearanceValue;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error in get_setting helper for key '{$key}': " . $e->getMessage());
        }

        return $default;
    }
}
