<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyDetail;

class SupportControllerApi extends Controller
{
    public function index()
    {
        $company = CompanyDetail::first();

        // Prefer support contact info, fallback to company contact info
        $mobile = $company->support_mobile ?? $company->company_mobile ?? 'N/A';
        $email = $company->support_email ?? $company->company_email ?? 'N/A';

        return response()->json([
            'success' => true,
            'data' => [
                'contact' => [
                    [
                        'type' => 'call',
                        'title' => 'Call',
                        'value' => $mobile,
                        'subtitle' => 'Tap to call admin team',
                        'icon' => 'phone',
                        'action' => 'tel:' . $mobile,
                    ],
                    [
                        'type' => 'email',
                        'title' => 'Email',
                        'value' => $email,
                        'subtitle' => 'Send issue details',
                        'icon' => 'mail',
                        'action' => 'mailto:' . $email,
                    ]
                ],
                'working_hours' => [
                    'title' => '9:00 AM - 7:00 PM',
                    'subtitle' => 'Monday - Saturday',
                    'note' => 'Support is available during the above hours for calls and messages.'
                ]
            ]
        ]);
    }
}
