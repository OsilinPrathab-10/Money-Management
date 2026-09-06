<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoanConfiguration;
use App\Services\PartialPaymentConfigService;

class LoanConfigurationController extends Controller
{
    /**
     * Display the loan configuration page
     */
    public function index()
    {
        $foreclosureConfig = LoanConfiguration::getForeclosureConfig();
        $prepaymentConfig = LoanConfiguration::getPrepaymentConfig();
        $partialPaymentConfig = LoanConfiguration::getPartialPaymentConfig();
        $penaltyConfig = LoanConfiguration::getPenaltyConfig();
        
        return view('admin.loan-management.loan-configuration.loan-configuration', compact('foreclosureConfig', 'prepaymentConfig', 'partialPaymentConfig', 'penaltyConfig'));
    }

    /**
     * JSON: current partial payment settings (for admin/agent UIs).
     */
    public function getPartialPaymentSettings()
    {
        return response()->json([
            'success' => true,
            'data' => app(PartialPaymentConfigService::class)->getGlobalSettings(),
        ]);
    }

    /**
     * Save foreclosure configuration
     */
    public function saveForeclosureConfig(Request $request)
    {
        // Check if this is a toggle switch update or full form submission
        $isToggleOnly = $request->has('is_active') && !$request->has('eligibility_months') && !$request->has('charges_percentage');
        
        $validated = $request->validate([
            'eligibility_months' => 'nullable|integer|min:0',
            'eligibility_weeks' => 'nullable|integer|min:0',
            'eligibility_days' => 'nullable|integer|min:0',
            'charges_percentage' => 'nullable|numeric|min:0|max:100',
            'charges_percentage_weekly' => 'nullable|numeric|min:0|max:100',
            'charges_percentage_daily' => 'nullable|numeric|min:0|max:100',
            'extra_charge' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        try {
            // If only is_active is being updated (toggle switch), preserve other values
            if ($isToggleOnly) {
                $config = LoanConfiguration::where('type', 'foreclosure')->first();
                if ($config) {
                    $config->update(['is_active' => $validated['is_active'] ?? true]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please configure foreclosure settings before enabling'
                    ], 400);
                }
            } else {
                $isActive = $validated['is_active'] ?? true;
                if ($isActive) {
                    $hasEligibility = ($validated['eligibility_months'] !== null && $validated['eligibility_months'] !== '') ||
                                      ($validated['eligibility_weeks'] !== null && $validated['eligibility_weeks'] !== '') ||
                                      ($validated['eligibility_days'] !== null && $validated['eligibility_days'] !== '');
                    $hasCharges = ($validated['charges_percentage'] !== null && $validated['charges_percentage'] !== '') ||
                                  ($validated['charges_percentage_weekly'] !== null && $validated['charges_percentage_weekly'] !== '') ||
                                  ($validated['charges_percentage_daily'] !== null && $validated['charges_percentage_daily'] !== '');
                    
                    if (!$hasEligibility || !$hasCharges) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Please configure at least one eligibility duration and one charge percentage.'
                        ], 422);
                    }
                }

                // Full form submission - update all fields
                LoanConfiguration::updateConfig('foreclosure', [
                    'eligibility_months' => $validated['eligibility_months'] ?? null,
                    'eligibility_weeks' => $validated['eligibility_weeks'] ?? null,
                    'eligibility_days' => $validated['eligibility_days'] ?? null,
                    'charges_percentage' => $validated['charges_percentage'] ?? 0,
                    'charges_percentage_weekly' => $validated['charges_percentage_weekly'] ?? null,
                    'charges_percentage_daily' => $validated['charges_percentage_daily'] ?? null,
                    'extra_charge' => $validated['extra_charge'] ?? 0,
                    'is_active' => $isActive,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Foreclosure configuration saved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    public function savePrepaymentConfig(Request $request)
    {
        $validated = $request->validate([
            'eligibility_months' => 'nullable|integer|min:0',
            'eligibility_weeks' => 'nullable|integer|min:0',
            'eligibility_days' => 'nullable|integer|min:0',
            'charge_type' => 'nullable|in:percentage,flat',
            'charge_value' => 'nullable|numeric|min:0',
            'charge_value_weekly' => 'nullable|numeric|min:0',
            'charge_value_daily' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        try {
            // If only is_active is being updated (toggle switch), preserve other values
            if ($request->has('is_active') && !$request->has('eligibility_months') && !$request->has('charge_value')) {
                $config = LoanConfiguration::where('type', 'prepayment')->first();
                if ($config) {
                    $config->update(['is_active' => $validated['is_active'] ?? false]);
                } else {
                    // Create with default values
                    LoanConfiguration::create([
                        'type' => 'prepayment',
                        'is_active' => $validated['is_active'] ?? false,
                        'eligibility_months' => 0,
                        'charge_type' => 'percentage',
                        'charge_value' => 0,
                    ]);
                }
            } else {
                $isActive = $validated['is_active'] ?? false;
                if ($isActive) {
                    $hasEligibility = ($validated['eligibility_months'] !== null && $validated['eligibility_months'] !== '') ||
                                      ($validated['eligibility_weeks'] !== null && $validated['eligibility_weeks'] !== '') ||
                                      ($validated['eligibility_days'] !== null && $validated['eligibility_days'] !== '');
                    $hasCharges = ($validated['charge_value'] !== null && $validated['charge_value'] !== '') ||
                                  ($validated['charge_value_weekly'] !== null && $validated['charge_value_weekly'] !== '') ||
                                  ($validated['charge_value_daily'] !== null && $validated['charge_value_daily'] !== '');
                    
                    if (!$hasEligibility || !$hasCharges) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Please configure at least one eligibility duration and one prepayment charge value.'
                        ], 422);
                    }
                }

                // Full form submission - update all fields
                LoanConfiguration::updateConfig('prepayment', [
                    'eligibility_months' => $validated['eligibility_months'] ?? 0,
                    'eligibility_weeks' => $validated['eligibility_weeks'] ?? null,
                    'eligibility_days' => $validated['eligibility_days'] ?? null,
                    'charge_type' => $validated['charge_type'] ?? 'percentage',
                    'charge_value' => $validated['charge_value'] ?? 0,
                    'charge_value_weekly' => $validated['charge_value_weekly'] ?? 0,
                    'charge_value_daily' => $validated['charge_value_daily'] ?? 0,
                    'is_active' => $isActive,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Prepayment configuration saved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save partial payment configuration
     */
    public function savePartialPaymentConfig(Request $request)
    {
        $validated = $request->validate([
            'minimum_partial_percentage' => 'nullable|numeric|min:0|max:100',
            'partial_payment_timing' => 'nullable|in:anytime,before_due,after_due',
            'penalty_calculation_method' => 'nullable|in:emi_amount,emi_plus_partial_remaining',
            'is_active' => 'nullable|boolean'
        ]);

        try {
            // If only is_active is being updated (toggle switch), check if config exists
            if ($request->has('is_active') && !$request->has('minimum_partial_percentage')) {
                $config = LoanConfiguration::where('type', 'partial_payment')->first();
                
                // If trying to enable without existing configuration, return error
                if (!$config && ($validated['is_active'] ?? false)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please configure partial payment settings before enabling'
                    ], 400);
                }
                
                if ($config) {
                    $updates = ['is_active' => (bool) ($validated['is_active'] ?? false)];
                    if ($updates['is_active'] && $config->minimum_partial_percentage === null) {
                        $updates['minimum_partial_percentage'] = PartialPaymentConfigService::DEFAULT_MINIMUM_PARTIAL_PERCENTAGE;
                    }
                    $config->update($updates);
                }
            } else {
                // Full form submission - update all fields
                $isActive = filter_var(
                    $request->input('is_active', false),
                    FILTER_VALIDATE_BOOLEAN
                );
                LoanConfiguration::updateConfig('partial_payment', [
                    'minimum_partial_percentage' => $validated['minimum_partial_percentage']
                        ?? PartialPaymentConfigService::DEFAULT_MINIMUM_PARTIAL_PERCENTAGE,
                    'partial_payment_timing' => $validated['partial_payment_timing'] ?? 'anytime',
                    'penalty_calculation_method' => $validated['penalty_calculation_method'] ?? 'emi_amount',
                    'is_active' => $isActive,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Partial payment configuration saved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save penalty configuration
     */
    public function savePenaltyConfig(Request $request)
    {
        // Check if this is a toggle switch update or full form submission
        $isToggleOnly = $request->has('is_active') && !$request->has('charge_value') && !$request->has('eligibility_days');
        
        $validated = $request->validate([
            'charge_value' => 'nullable|numeric|min:0',
            'eligibility_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        try {
            // If only is_active is being updated (toggle switch), preserve other values
            if ($isToggleOnly) {
                $config = LoanConfiguration::where('type', 'penalty')->first();
                if ($config) {
                    $config->update(['is_active' => $validated['is_active'] ?? true]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please configure penalty settings before enabling'
                    ], 400);
                }
            } else {
                $isActive = $validated['is_active'] ?? true;
                
                // Full form submission - update all fields
                LoanConfiguration::updateConfig('penalty', [
                    'charge_value' => $validated['charge_value'] ?? 0.00,
                    'eligibility_days' => $validated['eligibility_days'] ?? 0,
                    'is_active' => $isActive,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Penalty configuration saved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage()
            ], 500);
        }
    }
}
