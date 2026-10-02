<?php

namespace App\Http\Controllers\Account;

use App\Models\Account\AccountingConfiguration;
use App\Models\CompanyDetail;
use App\Services\Account\AccountingConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AccountingConfigurationController extends Controller
{
    public function gst(AccountingConfigurationService $configurationService)
    {
        if (! Auth::user()->hasRole('Admin')) {
            abort(403, __('Permission denied'));
        }

        $company = CompanyDetail::query()->first();

        return view('admin.account.configuration.gst', [
            'gstEnabled' => $configurationService->isGstEnabled(),
            'gstPercentage' => $configurationService->gstPercentage(),
            'companyGstNumber' => $company?->gst_number,
            'websiteSetupUrl' => route('website-homepage'),
        ]);
    }

    public function updateGst(Request $request)
    {
        if (! Auth::user()->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission denied'),
            ], 403);
        }

        $enabled = $request->boolean('gst_enabled');

        $rules = [
            'gst_enabled' => 'nullable|in:0,1',
        ];

        if ($enabled) {
            $rules['gst_percentage'] = 'required|numeric|min:0|max:100';
        } else {
            $rules['gst_percentage'] = 'nullable|numeric|min:0|max:100';
        }

        $validated = $request->validate($rules);

        AccountingConfiguration::set('gst_enabled', $enabled ? '1' : '0');

        if ($enabled) {
            AccountingConfiguration::set(
                'gst_percentage',
                number_format((float) $validated['gst_percentage'], 2, '.', '')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'GST configuration saved successfully.',
            'gst_enabled' => $enabled,
            'gst_percentage' => AccountingConfiguration::get('gst_percentage', '18.00'),
        ]);
    }
}
