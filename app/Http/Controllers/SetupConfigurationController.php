<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeatureActivation;
use App\Models\Faq;
use App\Models\ApiConfiguration;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\LoanDocumentEmailService;
use App\Events\GenerateDocument;
use App\Models\LoanAccount;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Exception;

class SetupConfigurationController extends Controller
{
    /**
     * Display the feature activation page
     */

    public function togglePaymentMethod(Request $request)
    {
        $data = $request->validate([
            'method' => 'required|string|in:autopay_enach,manual_payment',
            'enabled' => 'required',
        ]);

        try {
            // Explicitly convert enabled to boolean
            $isEnabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
            
            $method = PaymentMethod::firstOrCreate(
                ['method' => $data['method']],
                [
                    'name' => $data['method'] === 'autopay_enach' ? 'Autopay (eNach)' : 'Manual Payment',
                    'is_enabled' => false,
                ]
            );

            // Update the is_enabled field
            $method->is_enabled = $isEnabled;
            $method->save();
            
            // Refresh the model to ensure we have the latest data
            $method->refresh();

            // If manual payment is disabled, disable all gateways
            if ($method->method === 'manual_payment' && ! $method->is_enabled) {
                PaymentGateway::where('gateway', 'razorpay')->update(['enabled' => false]);
                PaymentGateway::where('gateway', 'cashfree')->update(['enabled' => false]);
                PaymentGateway::where('gateway', 'payu')->update(['enabled' => false]);
            }

            return response()->json([
                'success' => true,
                'message' => ($method->is_enabled ? 'Enabled ' : 'Disabled ') . $method->name,
                'method' => $method->method,
                'is_enabled' => $method->is_enabled,
            ]);
        } catch (\Exception $e) {
            Log::error('Toggle payment method error: ' . $e->getMessage(), [
                'method' => $data['method'] ?? null,
                'enabled' => $data['enabled'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display API configuration page
     */
    public function apiConfiguration()
    {
        $configurations = ApiConfiguration::whereIn('service', ['whatsapp', 'google_maps', 'websmpp', 'firebase', 'cibil'])->get()->keyBy('service');

        return view('admin.setup-configuration.api-configuration.api-configuration', compact('configurations'));
    }

    /**
     * Persist credentials for a specific API service
     */
    public function saveApiConfiguration(Request $request, string $service)
    {
        $label = null;
        $rules = [
            'is_enabled' => 'nullable|boolean',
            'active_service' => 'nullable|string',
        ];
        $fields = [];
        $envMap = [];

        switch ($service) {
            case 'whatsapp':
                $label = 'WhatsApp Credential';
                $fields = ['provider', 'access_token', 'workspace_id', 'channel_id'];
                $rules['credentials.provider'] = 'required|string';
                $rules['credentials.access_token'] = 'required|string';
                $rules['credentials.workspace_id'] = 'required|string';
                $rules['credentials.channel_id'] = 'required|string';
                $envMap = [
                    'provider' => 'WHATSAPP_PROVIDER',
                    'access_token' => 'WHATSAPP_ACCESS_TOKEN',
                    'workspace_id' => 'WHATSAPP_WORKSPACE_ID',
                    'channel_id' => 'WHATSAPP_CHANNEL_ID',
                ];
                break;
            case 'google_maps':
                $label = 'Google Maps Credential';
                $fields = ['api_key'];
                $rules['credentials.api_key'] = 'required|string';
                $envMap = [
                    'api_key' => 'GOOGLE_MAPS_API_KEY',
                ];
                break;
            case 'websmpp':
                $label = 'WebSMPP Credential';
                $fields = ['user', 'password', 'sender_id', 'peid'];
                $rules['credentials.user'] = 'required|string';
                $rules['credentials.password'] = 'required|string';
                $rules['credentials.sender_id'] = 'required|string|max:11';
                $rules['credentials.peid'] = 'nullable|string';
                $envMap = [
                    'user' => 'WEBSMPP_USER',
                    'password' => 'WEBSMPP_PASSWORD',
                    'sender_id' => 'WEBSMPP_SENDERID',
                    'peid' => 'WEBSMPP_PEID',
                ];
                break;
            case 'firebase':
                $label = 'Firebase';
                $fields = ['project_key', 'sdk_path'];
                $rules['credentials.project_key'] = 'required|string';
                $rules['sdk_file'] = 'required|file|mimes:json|max:2048';
                
                $envMap = [
                    'project_key' => 'FIREBASE_PROJECT_KEY',
                ];
                break;
            case 'cibil':
                $label = 'CIBIL / Credit bureau API';
                $fields = ['base_url', 'endpoint', 'api_key', 'api_secret', 'auth_type', 'http_method', 'score_json_path'];
                $rules['credentials.base_url'] = 'required|url';
                $rules['credentials.endpoint'] = 'nullable|string|max:500';
                $rules['credentials.api_key'] = 'nullable|string';
                $rules['credentials.api_secret'] = 'nullable|string';
                $rules['credentials.auth_type'] = 'required|in:bearer,basic,headers';
                $rules['credentials.http_method'] = 'required|in:POST,GET';
                $rules['credentials.score_json_path'] = 'nullable|string|max:255';
                $envMap = [
                    'base_url' => 'CIBIL_API_BASE_URL',
                    'api_key' => 'CIBIL_API_KEY',
                    'api_secret' => 'CIBIL_API_SECRET',
                ];
                break;
            default:
                abort(404);
        }

        // Check if this is a toggle-only request (no credentials provided)
    if (! $request->has('credentials')) {
        $config = ApiConfiguration::where('service', $service)->first();
        $isEnabled = $request->boolean('is_enabled');

        // If trying to enable, validate that credentials exist
        if ($isEnabled) {
            if (! $config || empty($config->credentials)) {
                $message = 'Please save credentials before enabling ' . $label . '.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }
                return redirect()->back()->with('error', $message);
            }

            // Check if all required fields are present
            foreach ($fields as $field) {
                if (empty($config->credentials[$field])) {
                    $message = 'Incomplete credentials. Please update ' . $label . ' configuration.';
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $message], 422);
                    }
                    return redirect()->back()->with('error', $message);
                }
            }
        }

        // Update status
        if ($config) {
            $config->is_enabled = $isEnabled;
            $config->save();
        } else {
            // Should not happen if enabling (caught above), but if disabling non-existent config, just ignore
            if ($isEnabled) {
                return response()->json(['success' => false, 'message' => 'Configuration not found.'], 404);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $label . ' ' . ($isEnabled ? 'activated' : 'deactivated') . ' successfully.',
                'is_enabled' => $config ? $config->is_enabled : false,
            ]);
        }

        return redirect()->back()->with('success', $label . ' status updated.');
    }

    $request->validate($rules);


    $credentials = [];
    foreach ($fields as $field) {
        $credentials[$field] = $request->input("credentials.$field");
    }

    // Handle Firebase SDK file upload
    if ($service === 'firebase' && $request->hasFile('sdk_file')) {
        $file = $request->file('sdk_file');
        
        // Create firebase directory if it doesn't exist
        $firebaseDir = storage_path('app/firebase');
        if (!file_exists($firebaseDir)) {
            mkdir($firebaseDir, 0755, true);
        }

        // Store the file directly under storage/app/firebase
        $filename = $file->getClientOriginalName();
        $file->move($firebaseDir, $filename);
        $filePath = 'firebase/' . $filename;

        // Add the file path to credentials
        $credentials['sdk_path'] = $filePath;
    }


    try {
        $config = ApiConfiguration::firstOrNew(['service' => $service]);
        $config->label = $label;
        $config->credentials = $credentials;
        $config->is_enabled = $request->boolean('is_enabled');
        $config->save();

        if (! empty($envMap)) {
            $envPayload = [];
            foreach ($envMap as $fieldKey => $envKey) {
                $envPayload[$envKey] = $credentials[$fieldKey] ?? '';
            }

            $this->updateEnvValues($envPayload);
            Artisan::call('config:clear');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $label . ' saved successfully.',
                'is_enabled' => $config->is_enabled,
            ]);
        }

        return redirect()->route('setup-configuration-api-configuration')
            ->with('success', $label . ' saved successfully.')
            ->with('active_service', $service);
    } catch (\Throwable $exception) {
        Log::error('API configuration save failed', [
            'service' => $service,
            'message' => $exception->getMessage(),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save credentials: ' . $exception->getMessage(),
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Failed to save credentials: ' . $exception->getMessage())
            ->withInput($request->all() + ['active_service' => $service]);
    }
    }



    public function index()
    {
        $maintenanceMode = FeatureActivation::get('maintenance_mode', '0');

        return view('admin.setup-configuration.feature-activation.feature-activation', compact('maintenanceMode'));
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode(Request $request)
    {
        try {
            $currentValue = FeatureActivation::get('maintenance_mode', '0');
            $newValue = $currentValue === '1' ? '0' : '1';

            FeatureActivation::set('maintenance_mode', $newValue);

            $status = $newValue === '1' ? 'enabled' : 'disabled';

            return response()->json([
                'success' => true,
                'message' => "Maintenance mode has been {$status} successfully.",
                'value' => $newValue
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle maintenance mode. Please try again.'
            ], 500);
        }
    }

    /**
     * Display FAQ listing page
     */
    public function faqIndex()
    {
        $faqs = Faq::orderBy('order', 'asc')->orderBy('id', 'asc')->get();
        return view('admin.setup-configuration.faq.faq', compact('faqs'));
    }

    /**
     * Show create FAQ form
     */
    public function faqCreate()
    {
        return view('admin.setup-configuration.faq.faq-create');
    }

    /**
     * Store new FAQ
     */
    public function faqStore(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'order' => 'nullable|integer|min:0'
        ]);

        try {
            Faq::create([
                'question' => $request->question,
                'answer' => $request->answer,
                'order' => $request->order ?? 0
            ]);

            return redirect()->route('faq-index')->with('success', 'FAQ created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create FAQ. Please try again.');
        }
    }

    /**
     * Show edit FAQ form
     */
    public function faqEdit($id)
    {
        $faq = Faq::findOrFail($id);
        return view('admin.setup-configuration.faq.faq-edit', compact('faq'));
    }

    /**
     * Update FAQ
     */
    public function faqUpdate(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'order' => 'nullable|integer|min:0'
        ]);

        try {
            $faq = Faq::findOrFail($id);
            $faq->update([
                'question' => $request->question,
                'answer' => $request->answer,
                'order' => $request->order ?? 0
            ]);

            return redirect()->route('faq-index')->with('success', 'FAQ updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update FAQ. Please try again.');
        }
    }

    /**
     * Delete FAQ
     */
    public function faqDestroy($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->delete();

            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FAQ. Please try again.'
            ], 500);
        }
    }

    /**
     * Display SMTP settings page
     */
    public function smtpSettings()
    {
        $smtpSettings = [
            'mail_mailer' => env('MAIL_MAILER', 'smtp'),
            'mail_host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'mail_port' => env('MAIL_PORT', '587'),
            'mail_username' => env('MAIL_USERNAME', ''),
            'mail_password' => env('MAIL_PASSWORD', ''),
            'mail_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'mail_from_name' => env('MAIL_FROM_NAME', config('app.name')),
        ];

        return view('admin.setup-configuration.smtp-settings.smtp-settings', compact('smtpSettings'));
    }

    /**
     * Update SMTP settings
     */
    public function updateSmtpSettings(Request $request)
    {
        $request->validate([
            'mail_mailer' => 'required|string',
            'mail_host' => 'required|string',
            'mail_port' => 'required|integer',
            'mail_username' => 'required|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'required|string|in:tls,ssl,none',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
        ]);

        try {
            $envPath = base_path('.env');

            if (!File::exists($envPath)) {
                return redirect()->back()->with('error', '.env file not found');
            }

            $envContent = File::get($envPath);

            // Update or add each SMTP setting
            $settings = [
                'MAIL_MAILER' => $request->mail_mailer,
                'MAIL_HOST' => $request->mail_host,
                'MAIL_PORT' => $request->mail_port,
                'MAIL_USERNAME' => $request->mail_username,
                'MAIL_ENCRYPTION' => $request->mail_encryption,
                'MAIL_FROM_ADDRESS' => $request->mail_from_address,
                'MAIL_FROM_NAME' => $request->mail_from_name,
            ];

            // Only update password if provided
            if ($request->filled('mail_password')) {
                $settings['MAIL_PASSWORD'] = $request->mail_password;
            }

            foreach ($settings as $key => $value) {
                // Escape special characters for regex
                $escapedKey = preg_quote($key, '/');

                // Wrap value in quotes if it contains spaces or special characters
                $formattedValue = $this->formatEnvValue($value);

                // Check if key exists in .env
                if (preg_match("/^{$escapedKey}=/m", $envContent)) {
                    // Update existing key
                    $envContent = preg_replace(
                        "/^{$escapedKey}=.*$/m",
                        "{$key}={$formattedValue}",
                        $envContent
                    );
                } else {
                    // Add new key at the end
                    $envContent .= "\n{$key}={$formattedValue}";
                }
            }

            File::put($envPath, $envContent);

            // Clear config cache
            Artisan::call('config:clear');

            return redirect()->back()->with('success', 'SMTP settings updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update SMTP settings: ' . $e->getMessage());
        }
    }

    /**
     * Test SMTP connection
     */
    public function testSmtpConnection(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string',
            'mail_from_address' => 'nullable|email',
            'mail_from_name' => 'nullable|string',
        ]);

        try {
            // If settings are provided in request, use them for testing
            if ($request->has('mail_host')) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $request->mail_host,
                    'mail.mailers.smtp.port' => $request->mail_port,
                    'mail.mailers.smtp.username' => $request->mail_username,
                    'mail.mailers.smtp.encryption' => $request->mail_encryption === 'none' ? null : $request->mail_encryption,
                    'mail.from.address' => $request->mail_from_address,
                    'mail.from.name' => $request->mail_from_name,
                ]);

                if ($request->filled('mail_password')) {
                    config(['mail.mailers.smtp.password' => $request->mail_password]);
                }
            }

            Mail::raw('This is a test email from your SMTP configuration.', function ($message) use ($request) {
                $message->to($request->test_email)
                    ->subject('SMTP Test Email - ' . config('app.name'));
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $request->test_email
            ]);
        } catch (\Exception $e) {
            Log::error('SMTP Test Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display payment methods page
     */
    /**
     * Display payment methods page
     */
    public function paymentMethods()
    {
        $autopayMethod = PaymentMethod::firstOrCreate(
            ['method' => 'autopay_enach'],
            [
                'name' => 'Autopay (eNach)',
                'is_enabled' => false,
            ]
        );

        $manualMethod = PaymentMethod::firstOrCreate(
            ['method' => 'manual_payment'],
            [
                'name' => 'Manual Payment',
                'is_enabled' => false,
            ]
        );

        // Razorpay
        $razorpayGateway = PaymentGateway::firstOrCreate(
            ['gateway' => 'razorpay'],
            [
                'name' => 'Razorpay',
                'enabled' => false,
                'api_key' => null,
                'api_secret' => null,
                'metadata' => null,
            ]
        );

        $razorpaySettings = [
            'enabled' => $razorpayGateway->enabled,
            'razor_key' => $razorpayGateway->api_key ?? '',
            'razor_secret' => $razorpayGateway->api_secret ?? '',
        ];

        // Cashfree
        $cashfreeGateway = PaymentGateway::firstOrCreate(
            ['gateway' => 'cashfree'],
            [
                'name' => 'Cashfree',
                'enabled' => false,
                'api_key' => null,
                'api_secret' => null,
                'metadata' => null,
            ]
        );

        $cashfreeSettings = [
            'enabled' => $cashfreeGateway->enabled,
            'app_id' => $cashfreeGateway->api_key ?? '',
            'secret_key' => $cashfreeGateway->api_secret ?? '',
        ];

        // PayU
        $payuGateway = PaymentGateway::firstOrCreate(
            ['gateway' => 'payu'],
            [
                'name' => 'PayU',
                'enabled' => false,
                'api_key' => null,
                'api_secret' => null,
                'metadata' => null,
            ]
        );

        $payuSettings = [
            'enabled' => $payuGateway->enabled,
            'payu_key' => $payuGateway->api_key ?? '',
            'payu_salt' => $payuGateway->api_secret ?? '',
        ];

        return view('admin.setup-configuration.payment-methods.payment-methods', compact('autopayMethod', 'manualMethod', 'razorpaySettings', 'cashfreeSettings', 'payuSettings'));
    }

    /**
     * Update payment methods
     */
    public function updatePaymentMethods(Request $request)
    {
        $manualMethod = PaymentMethod::firstOrCreate(
            ['method' => 'manual_payment'],
            [
                'name' => 'Manual Payment',
                'is_enabled' => false,
            ]
        );

        if (! $manualMethod->is_enabled) {
            return redirect()->back()->with('error', 'Enable Manual Payment to configure payment gateway credentials.');
        }

        $request->validate([
            'gateway' => 'nullable|string|in:razorpay,cashfree,payu',
            'enabled' => 'required|boolean',
            'method' => 'nullable|string|in:manual_payment,autopay_enach',
        ]);

        // 1. Handle Payment Method Toggle (Manual/Autopay)
        if ($request->has('method')) {
            $methodName = $request->method;
            $method = PaymentMethod::where('method', $methodName)->firstOrFail();
            $method->is_enabled = $request->boolean('enabled');
            $method->save();

            $statusText = $method->is_enabled ? 'activated' : 'deactivated';
            return redirect()->back()->with('success', $method->name . ' ' . $statusText . ' successfully');
        }

        // 2. Handle Payment Gateway Credentials
        $gatewayName = $request->gateway;
        $apiKey = '';
        $apiSecret = '';
        
        // Check if this is a toggle action or save action
        $isToggleAction = $request->boolean('is_toggle_action', false);

        if ($gatewayName === 'razorpay') {
            $request->validate([
                'razor_key' => 'required|string',
                'razor_secret' => 'required|string',
            ]);
            $apiKey = $request->razor_key;
            $apiSecret = $request->razor_secret;
        } elseif ($gatewayName === 'cashfree') {
            $request->validate([
                'app_id' => 'required|string',
                'secret_key' => 'required|string',
            ]);
            $apiKey = $request->app_id;
            $apiSecret = $request->secret_key;
        } elseif ($gatewayName === 'payu') {
            $request->validate([
                'payu_key' => 'required|string',
                'payu_salt' => 'required|string',
            ]);
            $apiKey = $request->payu_key;
            $apiSecret = $request->payu_salt;
        }

        try {
            $gateway = PaymentGateway::firstOrCreate(
                ['gateway' => $gatewayName],
                ['name' => ucfirst($gatewayName)]
            );

            $gateway->update([
                'enabled' => $request->boolean('enabled'),
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ]);

            // Different messages for toggle vs save
            if ($isToggleAction) {
                $statusText = $request->boolean('enabled') ? 'activated' : 'deactivated';
                $message = ucfirst($gatewayName) . ' ' . $statusText . ' successfully';
            } else {
                $message = ucfirst($gatewayName) . ' credentials updated successfully';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update payment methods: ' . $e->getMessage());
        }
    }

    /**
     * Display file system & cache configuration page
     */
    public function fileSystemCache()
    {
        return view('admin.setup-configuration.file-system-cache.cache-clear');
    }

    /**
     * Clear cache based on type
     */
    public function clearCache(Request $request)
    {
        // `optimize:clear` can fail on some Windows/XAMPP setups; run the same steps explicitly.
        $commands = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'event:clear',
        ];

        $details = [];

        try {
            foreach ($commands as $cmd) {
                Artisan::call($cmd);
                $out = trim(Artisan::output());
                if ($out !== '') {
                    $details[] = $out;
                }
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Cache clear failed: :msg', ['msg' => $e->getMessage()]),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Caches cleared successfully (equivalent to php artisan optimize:clear).'),
            // 'commands' => $commands,
            // 'detail' => implode("\n", $details),
        ]);
    }

    /**
     * Format environment variable value
     */
    private function formatEnvValue($value)
    {
        // If value contains spaces or special characters, wrap in quotes
        if (preg_match('/[\s#]/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }

    /**
     * Update or append environment variables
     */
    private function updateEnvValues(array $values): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            $escapedKey = preg_quote($key, '/');
            $formattedValue = $this->formatEnvValue((string) $value);

            if (preg_match("/^{$escapedKey}=/m", $envContent)) {
                $envContent = preg_replace(
                    "/^{$escapedKey}=.*$/m",
                    "{$key}={$formattedValue}",
                    $envContent
                );
            } else {
                $envContent .= "\n{$key}={$formattedValue}";
            }
        }

        File::put($envPath, $envContent);
    }

    // Get S3 configuration
    public function getS3Config()
    {
        $config = \App\Models\FileSystemCredential::getS3Config();
        
        return response()->json([
            'enabled' => $config->is_enabled ?? false,
            'access_key_id' => $config->access_key_id ? '***' . substr($config->access_key_id, -4) : '',
            'secret_access_key' => $config->secret_access_key ? '***' : '',
            'region' => $config->region ?? '',
            'bucket' => $config->bucket ?? '',
            'url' => $config->url ?? '',
            'configured' => !empty($config->access_key_id) && !empty($config->secret_access_key)
        ]);
    }

    // Update S3 configuration
    public function updateS3Config(Request $request)
    {
        $validated = $request->validate([
            'access_key_id' => 'required|string',
            'secret_access_key' => 'required|string',
            'region' => 'required|string',
            'bucket' => 'required|string',
            'url' => 'nullable|string'
        ]);

        $config = \App\Models\FileSystemCredential::getS3Config();
        $config->fill($validated);
        $config->save();

        return response()->json(['message' => 'S3 credentials updated successfully']);
    }

    // Toggle S3 status
    public function toggleS3Status(Request $request)
    {
        $enabled = $request->input('enabled');
        $config = \App\Models\FileSystemCredential::getS3Config();

        // Check if credentials are configured
        if ($enabled && (empty($config->access_key_id) || empty($config->secret_access_key))) {
            return response()->json([
                'error' => 'Please configure S3 credentials before enabling'
            ], 422);
        }

        $config->is_enabled = $enabled;
        $config->save();

        return response()->json(['message' => 'S3 status updated successfully']);
    }

    // Test S3 connection
    public function testS3Connection()
    {
        try {
            $config = \App\Models\FileSystemCredential::getS3Config();
            
            if (empty($config->access_key_id) || empty($config->secret_access_key)) {
                return response()->json(['error' => 'S3 credentials not configured'], 422);
            }

            // Configure S3 temporarily for testing
            config([
                'filesystems.disks.s3.key' => $config->access_key_id,
                'filesystems.disks.s3.secret' => $config->secret_access_key,
                'filesystems.disks.s3.region' => $config->region,
                'filesystems.disks.s3.bucket' => $config->bucket,
            ]);

            // Test connection by listing files
            \Storage::disk('s3')->files();

            return response()->json(['message' => 'S3 connection successful']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'S3 connection failed: ' . $e->getMessage()], 422);
        }
    }
}
