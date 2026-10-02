<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Client;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserOtp;
use App\Models\SmsOtpLog;
use App\Models\KycDetail;
use App\Models\Nominee;
use App\Models\Guarantor;
use App\Models\EmployeeInformation;
use App\Services\PushNotificationService;
use App\Utils\SMSUtility;
use App\Http\Resources\ClientResource;
use App\Support\CustomerAppAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Customer mobile auth APIs
 *
 * 1) Login with email OR mobile as username, default password = mobile number
 * 2) OTP for first-time / forgot-password (MSG91 basic_otp)
 * 3) After auth → MPIN set/verify → Bearer token
 * 4) Forgot MPIN → OTP to registered mobile → reset MPIN
 */
class AuthControllerApi extends Controller
{
    private const OTP_TTL_MINUTES = 5;
    private const SESSION_TTL_MINUTES = 10;
    private const TEST_PHONE = '9876543210';
    private const TEST_OTP = '123456';

    // ─────────────────────────────────────────────────────────────
    // Customer Registration (Mandatory fields match Admin Add Client)
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/customer/locations
     * GET /api/customer/auth/locations
     * Public list for register form. Use returned `id` as `location_id`.
     */
    public function locations(Request $request): JsonResponse
    {
        $query = Location::query()
            ->select('id', 'name', 'city', 'state', 'pincode')
            ->orderBy('name');

        if ($request->filled('city')) {
            $query->where('city', 'like', '%'.$request->input('city').'%');
        }
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('state', 'like', '%'.$search.'%')
                    ->orWhere('pincode', 'like', '%'.$search.'%');
            });
        }

        $locations = $query->get();

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Locations fetched successfully',
            'data' => $locations,
        ]);
    }

    /**
     * GET /api/customer/auth/register-dropdowns
     * Select options matching Admin Add Client.
     */
    public function registerDropdowns(): JsonResponse
    {
        $locations = Location::query()
            ->select('id', 'name', 'city', 'state', 'pincode')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Register dropdowns fetched successfully',
            'data' => [
                'locations' => $locations,
                'genders' => [
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
                'marital_statuses' => [
                    ['value' => 'single', 'label' => 'Single'],
                    ['value' => 'married', 'label' => 'Married'],
                    ['value' => 'divorced', 'label' => 'Divorced'],
                    ['value' => 'widowed', 'label' => 'Widowed'],
                ],
                'account_types' => [
                    ['value' => 'savings', 'label' => 'Savings Account'],
                    ['value' => 'current', 'label' => 'Current Account'],
                ],
                'nominee_relationships' => [
                    ['value' => 'husband', 'label' => 'Husband'],
                    ['value' => 'spouse', 'label' => 'Spouse'],
                    ['value' => 'father', 'label' => 'Father'],
                    ['value' => 'mother', 'label' => 'Mother'],
                    ['value' => 'son', 'label' => 'Son'],
                    ['value' => 'daughter', 'label' => 'Daughter'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
                'guarantor_relationships' => [
                    ['value' => 'Friend', 'label' => 'Friend'],
                    ['value' => 'Relative', 'label' => 'Relative'],
                    ['value' => 'Colleague', 'label' => 'Colleague'],
                    ['value' => 'Neighbor', 'label' => 'Neighbor'],
                    ['value' => 'Other', 'label' => 'Other'],
                ],
                'referral_relationships' => [
                    ['value' => 'Associate', 'label' => 'Associate'],
                    ['value' => 'Relative', 'label' => 'Relative'],
                    ['value' => 'Friend', 'label' => 'Friend'],
                    ['value' => 'Other', 'label' => 'Other'],
                ],
                'employment_types' => [
                    ['value' => 'salaried', 'label' => 'Salaried'],
                    ['value' => 'business', 'label' => 'Business / Self-employed'],
                ],
                'collection_days' => [
                    ['value' => 'Monday', 'label' => 'Monday'],
                    ['value' => 'Tuesday', 'label' => 'Tuesday'],
                    ['value' => 'Wednesday', 'label' => 'Wednesday'],
                    ['value' => 'Thursday', 'label' => 'Thursday'],
                    ['value' => 'Friday', 'label' => 'Friday'],
                    ['value' => 'Saturday', 'label' => 'Saturday'],
                    ['value' => 'Sunday', 'label' => 'Sunday'],
                ],
            ],
        ]);
    }

    /**
     * POST /api/customer/auth/register
     * Body (multipart/form-data or JSON) — same fields as Admin Add Client:
     * Required: name, phone, location_id, aadhar_number, pan_number,
     *           account_number, ifsc_code, branch_name,
     *           selfie_photo, aadhar_photo_front, aadhar_photo_back
     * Optional: email, alternate_phone, date_of_birth, gender, marital_status,
     *           address, city, state, pincode, collection_day,
     *           account_holder, bank_name, account_type,
     *           nominees, guarantor, referral, employment, pan_photo, bank_statement
     */
    public function register(Request $request): JsonResponse
    {
        // 1. Alias normalization
        if (! $request->has('name') && $request->has('client_name')) {
            $request->merge(['name' => $request->input('client_name')]);
        }
        if (! $request->has('phone')) {
            $phoneVal = $request->input('mobile') ?: $request->input('client_phone');
            if ($phoneVal) {
                $request->merge(['phone' => $phoneVal]);
            }
        }
        if (! $request->has('aadhar_number') && $request->has('aadhaar_number')) {
            $request->merge(['aadhar_number' => $request->input('aadhaar_number')]);
        }
        if (! $request->has('account_holder') && $request->has('account_holder_name')) {
            $request->merge(['account_holder' => $request->input('account_holder_name')]);
        }

        $camelAliases = [
            'guarantorName' => ['guarantor_name'],
            'guarantorPhone' => ['guarantor_phone'],
            'guarantorRelationship' => ['guarantor_relationship'],
            'referralName' => ['referral_name'],
            'referralPhone' => ['referral_phone'],
            'referralRelationship' => ['referral_relationship'],
        ];
        foreach ($camelAliases as $canonical => $aliases) {
            if ($request->filled($canonical)) {
                continue;
            }
            foreach ($aliases as $alias) {
                if ($request->filled($alias)) {
                    $request->merge([$canonical => $request->input($alias)]);
                    break;
                }
            }
        }
        if (! $request->filled('monthly_income') && $request->filled('monthly_turnover')) {
            $request->merge(['monthly_income' => $request->input('monthly_turnover')]);
        }

        // 2. Clean masked inputs before validation
        if ($request->has('phone')) {
            $request->merge(['phone' => preg_replace('/\s+/', '', (string) $request->phone)]);
        }
        if ($request->has('aadhar_number')) {
            $request->merge(['aadhar_number' => preg_replace('/\s+/', '', (string) $request->aadhar_number)]);
        }
        if ($request->has('pan_number')) {
            $request->merge(['pan_number' => strtoupper(preg_replace('/\s+/', '', (string) $request->pan_number))]);
        }
        if ($request->has('ifsc_code')) {
            $request->merge(['ifsc_code' => strtoupper(preg_replace('/\s+/', '', (string) $request->ifsc_code))]);
        }
        if ($request->has('account_number')) {
            $request->merge(['account_number' => preg_replace('/\s+/', '', (string) $request->account_number)]);
        }
        if ($request->exists('alternate_phone')) {
            $request->merge(['alternate_phone' => \App\Models\Client::normalizeOptionalPhone($request->input('alternate_phone'))]);
        }
        if ($request->has('pincode')) {
            $request->merge(['pincode' => preg_replace('/\s+/', '', (string) $request->pincode)]);
        }
        if ($request->has('nominee1_mobile')) {
            $request->merge(['nominee1_mobile' => preg_replace('/\s+/', '', (string) $request->nominee1_mobile)]);
        }
        if ($request->has('nominee2_mobile')) {
            $request->merge(['nominee2_mobile' => preg_replace('/\s+/', '', (string) $request->nominee2_mobile)]);
        }
        foreach (['guarantorPhone', 'referralPhone'] as $phoneField) {
            if ($request->filled($phoneField)) {
                $request->merge([$phoneField => preg_replace('/\s+/', '', (string) $request->input($phoneField))]);
            }
        }

        // 3. Validation Rules
        $cleanPhonePre = preg_replace('/\s+/', '', (string) ($request->input('phone') ?: $request->input('mobile') ?: $request->input('client_phone')));
        $existingClientStub = !empty($cleanPhonePre) ? Client::where('client_phone', $cleanPhonePre)->first() : null;
        $isRegistrationStub = $existingClientStub && (empty($existingClientStub->aadhaar_number) || empty($existingClientStub->location_id));
        $ignoreClientId = ($isRegistrationStub && $existingClientStub) ? $existingClientStub->id : null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('clients', 'client_email')->ignore($ignoreClientId)],
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/', Rule::unique('clients', 'client_phone')->ignore($ignoreClientId)],
            'password' => 'nullable|string|min:6|confirmed',
            'mpin' => 'nullable|digits_between:4,6',
            'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable',
            'gender' => 'nullable|string|in:male,female,other',
            'marital_status' => 'nullable|string|in:single,married,divorced,widowed',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => ['nullable', 'string', 'regex:/^[0-9]{6}$/'],
            'location_id' => 'required|exists:locations,id',

            // Mandatory KYC & Bank details
            'aadhar_number' => ['required', 'string', 'regex:/^[0-9]{12}$/', 'unique:clients,aadhaar_number'],
            'pan_number' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', 'unique:kyc_details,pan_number'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'unique:kyc_details,account_number'],
            'ifsc_code' => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['required', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'in:savings,current'],

            // Employment
            'employment_type' => 'nullable|in:salaried,business',
            'company_name' => 'nullable|string|max:255',
            'monthly_salary' => 'nullable|numeric',
            'business_name' => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric',

            // Nominees & Guarantors
            'nominee1_name' => 'nullable|string',
            'nominee1_relationship' => 'nullable|string',
            'nominee1_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'nominee2_name' => 'nullable|string',
            'nominee2_relationship' => 'nullable|string',
            'nominee2_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'guarantorName' => 'nullable|string|max:255',
            'guarantorPhone' => 'nullable|string|regex:/^[0-9]{10}$/',
            'guarantorRelationship' => 'nullable|string|max:255',
            'referralName' => 'nullable|string|max:255',
            'referralPhone' => 'nullable|string|regex:/^[0-9]{10}$/',
            'referralRelationship' => 'nullable|string|max:255',
            'collection_day' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'pan_photo' => 'nullable',
            'bank_statement' => 'nullable',
            'payslip' => 'nullable',
            'business_document' => 'nullable',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'name.required' => 'Client name is mandatory.',
            'phone.required' => 'Phone number is mandatory.',
            'phone.unique' => 'The phone number has already been registered.',
            'phone.regex' => 'Phone number must be exactly 10 digits.',
            'password.min' => 'Password must be at least 6 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'location_id.required' => 'Location selection is mandatory.',
            'location_id.exists' => 'Selected location does not exist.',
            'aadhar_number.required' => 'Aadhaar number is mandatory.',
            'aadhar_number.unique' => 'The Aadhaar number has already been registered.',
            'aadhar_number.regex' => 'Aadhaar number must be exactly 12 digits.',
            'pan_number.required' => 'PAN number is mandatory.',
            'pan_number.unique' => 'The PAN number has already been registered.',
            'pan_number.regex' => 'Invalid PAN format. It must be 10 characters (e.g., ABCDE1234F).',
            'account_number.required' => 'Bank account number is mandatory.',
            'account_number.unique' => 'The Bank Account number has already been registered.',
            'account_number.regex' => 'Bank account number must contain only numbers.',
            'ifsc_code.required' => 'IFSC code is mandatory.',
            'ifsc_code.regex' => 'Invalid IFSC format. It must be 11 characters (e.g., HDFC0001234).',
            'branch_name.required' => 'Branch name is mandatory.',
        ]);

        // Custom validation for required photos (supports uploaded files or base64 strings)
        $photoCheckMap = [
            'selfie_photo' => ['selfie_photo', 'selfie_image', 'selfie'],
            'aadhar_photo_front' => ['aadhar_photo_front', 'aadhaar_photo_front', 'aadhaar_front', 'aadhar_front'],
            'aadhar_photo_back' => ['aadhar_photo_back', 'aadhaar_photo_back', 'aadhaar_back', 'aadhar_back'],
        ];
        $photoMessages = [
            'selfie_photo' => 'Selfie photo is mandatory.',
            'aadhar_photo_front' => 'Aadhaar front photo is mandatory.',
            'aadhar_photo_back' => 'Aadhaar back photo is mandatory.',
        ];

        $missingPhotoErrors = [];
        foreach ($photoCheckMap as $key => $fields) {
            $found = false;
            foreach ($fields as $field) {
                if ($request->hasFile($field) || ($request->filled($field) && is_string($request->input($field)))) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missingPhotoErrors[$key] = [$photoMessages[$key]];
            }
        }

        if ($validator->fails() || ! empty($missingPhotoErrors)) {
            $allErrors = array_merge($validator->errors()->toArray(), $missingPhotoErrors);

            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $allErrors,
            ], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();
        try {
            $cleanPhone = preg_replace('/\s+/', '', $validated['phone']);
            $cleanAadhaar = preg_replace('/\s+/', '', $validated['aadhar_number']);

            // 1. Create or retrieve User account
            $userPassword = $request->filled('password')
                ? Hash::make((string) $request->input('password'))
                : Hash::make($cleanPhone);

            $enteredEmail = ! empty($validated['email']) ? trim($validated['email']) : null;

            $user = User::where('phone', $cleanPhone)->first();
            if (! $user && ! empty($enteredEmail)) {
                $user = User::where('email', $enteredEmail)->first();
            }

            $nickname = $request->filled('nickname') ? trim((string) $request->input('nickname')) : null;

            if (! $user) {
                $user = User::create([
                    'name' => $validated['name'],
                    'nickname' => $nickname,
                    'phone' => $cleanPhone,
                    'email' => $enteredEmail,
                    'password' => $userPassword,
                    'status' => 'active',
                ]);
            } else {
                if ($nickname !== null) {
                    $user->nickname = $nickname;
                }
                if ($enteredEmail) {
                    $user->email = $enteredEmail;
                } elseif ($this->isGeneratedDummyEmail($user->email)) {
                    $user->email = null;
                }
                if ($request->filled('password')) {
                    $user->password = $userPassword;
                }
                $user->save();
            }

            if (method_exists($user, 'hasRole') && ! $user->hasRole('Client')) {
                try {
                    $user->assignRole('Client');
                } catch (\Throwable $e) {
                    Log::warning('Unable to assign Client role during registration', ['user_id' => $user->id]);
                }
            }

            // 2. Parse Date of Birth if provided (Add Client uses DD-MM-YYYY)
            $dob = null;
            if (! empty($validated['date_of_birth'])) {
                $rawDob = str_replace('/', '-', (string) $validated['date_of_birth']);
                try {
                    $dob = Carbon::createFromFormat('d-m-Y', $rawDob)->format('Y-m-d');
                } catch (\Throwable $e) {
                    try {
                        $dob = Carbon::parse($rawDob)->format('Y-m-d');
                    } catch (\Throwable $e2) {
                        $dob = null;
                    }
                }
            }

            // 3. Create or update Client record
            $clientData = [
                'user_id' => $user->id,
                'client_name' => $validated['name'],
                'nickname' => $nickname,
                'client_email' => ! empty($validated['email']) ? $validated['email'] : null,
                'client_phone' => $cleanPhone,
                'alternate_phone' => $validated['alternate_phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'date_of_birth' => $dob,
                'gender' => $validated['gender'] ?? null,
                'marital_status' => $validated['marital_status'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'pincode' => $validated['pincode'] ?? null,
                'aadhaar_number' => $cleanAadhaar,
                'location_id' => $validated['location_id'],
                'collection_day' => isset($validated['collection_day']) ? ucfirst(strtolower($validated['collection_day'])) : null,
                'status' => 'pending',
            ];

            if ($request->filled('mpin')) {
                $mpinVal = (string) $request->input('mpin');
                $clientData['mpin'] = $mpinVal;
                $clientData['mpin_hash'] = Hash::make($mpinVal);
                $clientData['mpin_set_at'] = now();
            }

            if ($existingClientStub && $isRegistrationStub) {
                $existingClientStub->update($clientData);
                $client = $existingClientStub;
            } else {
                $client = Client::create($clientData);
            }

            // 4. Handle mandatory photo uploads (supports file and base64)
            $selfiePath = $this->saveUploadOrBase64($request, ['selfie_photo', 'selfie_image', 'selfie'], 'kyc/selfie', $client->id);
            $aadharFrontPath = $this->saveUploadOrBase64($request, ['aadhar_photo_front', 'aadhaar_photo_front', 'aadhaar_front', 'aadhar_front'], 'kyc/aadhar', $client->id);
            $aadharBackPath = $this->saveUploadOrBase64($request, ['aadhar_photo_back', 'aadhaar_photo_back', 'aadhaar_back', 'aadhar_back'], 'kyc/aadhar', $client->id);

            // Optional document uploads
            $panPath = $this->saveUploadOrBase64($request, ['pan_photo', 'pan_image'], 'kyc/pan', $client->id);
            $bankStatementPath = $this->saveUploadOrBase64($request, ['bank_statement'], 'kyc/bank_statement', $client->id);

            // 5. Create KycDetail record
            KycDetail::create([
                'client_id' => $client->id,
                'aadhaar_number' => $cleanAadhaar,
                'aadhaar_name' => $validated['name'],
                'aadhaar_image' => $aadharFrontPath,
                'aadhaar_image_back' => $aadharBackPath,
                'selfie_image' => $selfiePath,
                'pan_number' => $validated['pan_number'],
                'pan_name' => $validated['name'],
                'pan_image' => $panPath,
                'account_holder_name' => $validated['account_holder'] ?? $validated['name'],
                'account_number' => $validated['account_number'],
                'ifsc_code' => $validated['ifsc_code'],
                'bank_name' => $validated['bank_name'] ?? null,
                'branch_name' => $validated['branch_name'],
                'account_type' => $validated['account_type'] ?? null,
                'bank_statement' => $bankStatementPath,
                'status' => 'pending',
                'aadhaar_verified' => false,
                'pan_verified' => false,
                'bank_verified' => false,
            ]);

            // 6. Create Nominee Record
            Nominee::create([
                'client_id' => $client->id,
                'nominee1_name' => $validated['nominee1_name'] ?? 'N/A',
                'nominee1_relationship' => $validated['nominee1_relationship'] ?? 'N/A',
                'nominee1_mobile' => $validated['nominee1_mobile'] ?? 'N/A',
                'nominee2_name' => $request->nominee2_name ?? null,
                'nominee2_relationship' => $request->nominee2_relationship ?? null,
                'nominee2_mobile' => $request->nominee2_mobile ?? null,
            ]);

            // 7. Create Guarantor / Referral Records if provided
            if ($request->filled('guarantorName') || $request->filled('guarantorPhone') || $request->filled('guarantorRelationship')) {
                Guarantor::create([
                    'client_id' => $client->id,
                    'name' => $request->guarantorName ?? 'N/A',
                    'phone' => $request->guarantorPhone ?? 'N/A',
                    'relationship' => $request->guarantorRelationship ?? 'N/A',
                    'type' => 'guarantor',
                ]);
            }
            if ($request->filled('referralName') || $request->filled('referralPhone') || $request->filled('referralRelationship')) {
                Guarantor::create([
                    'client_id' => $client->id,
                    'name' => $request->referralName ?? 'N/A',
                    'phone' => $request->referralPhone ?? null,
                    'relationship' => $request->referralRelationship ?? 'Associate',
                    'type' => 'referral',
                ]);
            }

            // 8. Create EmployeeInformation (same as Admin Add Client)
            $empType = $validated['employment_type'] ?? 'salaried';
            $payslipPath = $this->saveUploadOrBase64($request, ['payslip'], 'kyc/payslip', $client->id);
            $businessDocPath = $this->saveUploadOrBase64($request, ['business_document'], 'kyc/business_proof', $client->id);

            $empData = [
                'client_id' => $client->id,
                'employment_type' => $empType === 'business' ? 'self_employed' : 'salaried',
            ];

            if ($empType === 'salaried') {
                $empData['company_name'] = $request->input('company_name');
                $empData['monthly_salary'] = $request->input('monthly_salary');
                if ($payslipPath) {
                    $empData['payslip_documents'] = [$payslipPath];
                }
            } else {
                $empData['business_name'] = $request->input('business_name');
                $empData['monthly_turnover'] = $request->input('monthly_income');
                if ($businessDocPath) {
                    $empData['business_proof_documents'] = [$businessDocPath];
                }
            }
            EmployeeInformation::create($empData);

            DB::commit();

            // Set pending authentication session for set_mpin / OTP flow
            $this->beginPendingAuth($client, $user, 'first_login', $request);

            return response()->json([
                'status' => true,
                'message' => 'Customer registered successfully. Registration is submitted for admin KYC verification.',
                'next_step' => $client->mpin_hash ? 'verify_mpin' : 'set_mpin',
                'client_id' => $client->id,
                'client' => $this->clientBrief($client),
                'credentials' => [
                    'email' => $this->sanitizeClientEmail($client->client_email) ?: $this->sanitizeClientEmail($user->email),
                    'mobile' => $cleanPhone,
                    'username_hint' => 'You can log in using either your registered email or mobile number as username.',
                    'password_created' => $request->filled('password'),
                ],
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Customer Registration Database Error: ' . $e->getMessage());
            $message = 'An unexpected database error occurred. Please try again.';
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062 Duplicate entry')) {
                $message = 'Registration failed: Duplicate record detected. Mobile number, Aadhaar, PAN, or Bank Account is already registered.';
            }

            return response()->json([
                'status' => false,
                'message' => $message,
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Customer Registration Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred during registration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/customer/auth/create-password
     * Create or set custom password after registration or during setup.
     * Body: { username|email|mobile|phone, password, password_confirmation }
     */
    public function createPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $username = trim((string) (
            $request->input('username')
            ?: $request->input('email')
            ?: $request->input('mobile')
            ?: $request->input('phone')
        ));

        $client = null;
        if ($username !== '') {
            $user = $this->findUserByUsername($username);
            if ($user && $user->client) {
                $client = $user->client;
            }
        }

        if (! $client) {
            $mobile = $this->resolveMobile($request);
            $pendingClient = $this->assertPendingAuth($mobile, ['first_login', 'login', 'register']);
            if ($pendingClient instanceof JsonResponse) {
                return response()->json([
                    'status' => false,
                    'message' => 'User profile not found. Please provide registered mobile number or email.',
                ], 404);
            }
            $client = $pendingClient;
        }

        $user = $client->user;
        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'User account not found.',
            ], 404);
        }

        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        $user->password = Hash::make((string) $request->input('password'));
        $user->save();

        if ($request->filled('mpin')) {
            $mpinVal = (string) $request->input('mpin');
            $client->mpin = $mpinVal;
            $client->mpin_hash = Hash::make($mpinVal);
            $client->mpin_set_at = now();
            $client->save();
        }

        $this->beginPendingAuth($client, $user, 'first_login', $request);

        return response()->json([
            'status' => true,
            'message' => 'Password created successfully. You can now log in using your registered email or mobile number.',
            'mpin' => (bool) $client->mpin_hash,
            'mpin_set' => (bool) $client->mpin_hash,
            'mpin_status' => (bool) $client->mpin_hash,
            'next_step' => $client->mpin_hash ? 'verify_mpin' : 'set_mpin',
            'credentials' => [
                'email' => $this->sanitizeClientEmail($client->client_email) ?: $this->sanitizeClientEmail($user->email),
                'mobile' => $client->client_phone ?: $user->phone,
                'username_hint' => 'Use either your email or mobile number as username with your new password.',
            ],
            'client' => $this->clientBrief($client),
        ]);
    }

    /**
     * Helper to store uploaded file or Base64 string image/document to public storage disk.
     *
     * @param  array<int, string>  $fieldNames
     */
    protected function saveUploadOrBase64(Request $request, array $fieldNames, string $directory, int $clientId): ?string
    {
        foreach ($fieldNames as $fieldName) {
            if ($request->hasFile($fieldName)) {
                return $request->file($fieldName)->store($directory . '/' . $clientId, 'public');
            }

            $base64Data = $request->input($fieldName);
            if (is_string($base64Data) && trim($base64Data) !== '') {
                $base64Data = trim($base64Data);
                $ext = 'jpg';

                if (preg_match('/^data:(?:image|application)\/(\w+);base64,/', $base64Data, $matches)) {
                    $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                    $ext = strtolower($matches[1]);
                    if ($ext === 'jpeg') {
                        $ext = 'jpg';
                    }
                }

                $decoded = base64_decode($base64Data, true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    $filename = $fieldName . '_' . uniqid() . '.' . $ext;
                    $relativePath = $directory . '/' . $clientId . '/' . $filename;
                    Storage::disk('public')->put($relativePath, $decoded);

                    return $relativePath;
                }
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Password login (email / mobile + password)
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/customer/auth/login
     * Body: { username|email|mobile|phone, password, device_* }
     * Username = email OR mobile. Default password = mobile number.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'mobile' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string',
        ] + $this->deviceFieldRules());

        $username = trim((string) (
            $request->input('username')
            ?: $request->input('email')
            ?: $request->input('mobile')
            ?: $request->input('phone')
        ));

        if ($username === '') {
            return response()->json([
                'status' => false,
                'message' => 'Email or mobile number is required as username',
            ], 422);
        }

        $user = $this->findUserByUsername($username);
        if (! $user) {
            return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
        }

        $client = $user->client;
        if (! $client) {
            return response()->json(['status' => false, 'message' => 'Client profile not found'], 404);
        }

        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        // Ensure default password = phone when never changed (legacy empty/random)
        $this->ensureDefaultPassword($user, $client);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($request->filled('mpin')) {
            $mpinVal = (string) $request->input('mpin');
            if (! $client->mpin_hash) {
                $client->mpin = $mpinVal;
                $client->mpin_hash = Hash::make($mpinVal);
                $client->mpin_set_at = now();
                $client->save();
            } elseif (! Hash::check($mpinVal, $client->mpin_hash)) {
                return response()->json(['status' => false, 'message' => 'Invalid MPIN'], 401);
            } elseif (empty($client->mpin)) {
                $client->mpin = $mpinVal;
                $client->save();
            }

            return $this->issueBearerToken($request, $client, 'Login successful');
        }

        $this->beginPendingAuth($client, $user, 'login', $request);

        return response()->json([
            'status' => true,
            'message' => 'Credentials verified. Complete MPIN to continue.',
            'is_active' => true,
            'client_status' => $client->status ?: 'pending',
            'mpin' => (bool) $client->mpin_hash,
            'mpin_set' => (bool) $client->mpin_hash,
            'mpin_status' => (bool) $client->mpin_hash,
            'next_step' => $client->mpin_hash ? 'verify_mpin' : 'set_mpin',
            'fcm_stored' => (bool) $this->resolveFcmToken($request),
            'client' => $this->clientBrief($client),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // OTP: first-time / forgot-password / forgot-mpin
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/customer/auth/send-otp
     * Body: { mobile|phone, purpose?: first_login|forgot_password|forgot_mpin }
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $mobile = $this->resolveMobile($request);
        $purpose = $this->resolvePurpose($request);

        $client = $this->findClientByMobile($mobile);
        $alreadyRegistered = $client && ($client->user || $this->isExistingCustomerRecord($client));

        if ($purpose === 'registration') {
            // For registration: if mobile number is ALREADY registered, block and ask to login
            if ($alreadyRegistered) {
                $client = $this->ensureClientHasUser($client, $mobile);

                if ($denied = $this->denyIfInactive($client)) {
                    return $denied;
                }

                return response()->json([
                    'status' => false,
                    'is_registered' => true,
                    'message' => 'This mobile number is already registered. Please login instead.',
                ], 422);
            }

            // Mobile is not registered yet: create/resolve client record to dispatch registration OTP
            $client = $this->resolveOrCreateClient($mobile);
            $user = $client->user;
        } else {
            // For login / first_login / forgot_password / forgot_mpin: require existing registered client
            if (! $client) {
                return response()->json([
                    'status' => false,
                    'is_registered' => false,
                    'message' => 'Customer not registered with this mobile number. Please register first.',
                ], 404);
            }

            $client = $this->ensureClientHasUser($client, $mobile);
            $user = $client->user;
            $this->ensureDefaultPassword($user, $client);

            if ($denied = $this->denyIfInactive($client)) {
                return $denied;
            }
        }

        return $this->dispatchOtp($user, $client, $mobile, $purpose);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        return $this->sendOtp($request);
    }

    /**
     * POST /api/customer/auth/verify-otp
     * Body: { mobile|phone, otp, purpose? }
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $mobile = $this->resolveMobile($request);
        $purpose = $this->resolvePurpose($request);

        $request->validate([
            'otp' => 'required|digits:6',
        ] + $this->deviceFieldRules());

        if ($purpose === 'forgot_mpin' && $request->filled('mpin')) {
            $request->validate([
                'mpin' => 'required|digits_between:4,6',
                'mpin_confirmation' => 'required|same:mpin',
            ] + $this->deviceFieldRules());
        }

        $client = $this->findClientByMobile($mobile);
        if (! $client) {
            return response()->json(['status' => false, 'message' => 'Client not found'], 404);
        }

        $client = $this->ensureClientHasUser($client, $mobile);

        if ($purpose !== 'registration' && ($denied = $this->denyIfInactive($client))) {
            return $denied;
        }

        $user = $client->user;
        if (! $this->isOtpValid($user, $mobile, (string) $request->input('otp'))) {
            return response()->json(['status' => false, 'message' => 'Invalid or expired OTP'], 422);
        }

        UserOtp::where('user_id', $user->id)->where('user_type', 'client')->delete();

        $this->beginPendingAuth($client, $user, $purpose === 'first_login' ? 'first_login' : $purpose, $request);

        if ($request->filled('mpin') && $purpose !== 'forgot_mpin') {
            $mpinVal = (string) $request->input('mpin');
            $client->mpin = $mpinVal;
            $client->mpin_hash = Hash::make($mpinVal);
            $client->mpin_set_at = now();
            $client->save();
        }

        $deviceId = $request->input('device_id') ?: ('customer-app-' . $client->id);
        $user->tokens()->where('name', $deviceId)->delete();
        $token = $user->createToken($deviceId)->plainTextToken;

        if ($purpose === 'forgot_password') {
            return response()->json([
                'status' => true,
                'message' => 'OTP verified. Set a new password.',
                'next_step' => 'reset_password',
                'token_type' => 'Bearer',
                'token' => $token,
                'bearer_token' => $token,
                'authorization' => 'Bearer ' . $token,
                'client' => $this->clientBrief($client),
            ]);
        }

        if ($purpose === 'forgot_mpin') {
            if ($request->filled('mpin')) {
                return $this->completeForgotMpinReset($request, $client, $mobile);
            }

            return response()->json([
                'status' => true,
                'message' => 'OTP verified. Set a new MPIN.',
                'next_step' => 'reset_mpin',
                'token_type' => 'Bearer',
                'token' => $token,
                'bearer_token' => $token,
                'authorization' => 'Bearer ' . $token,
                'client' => $this->clientBrief($client),
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully',
            'token_type' => 'Bearer',
            'token' => $token,
            'bearer_token' => $token,
            'authorization' => 'Bearer ' . $token,
            'otp_verified' => true,
            'mpin' => (bool) $client->mpin_hash,
            'mpin_set' => (bool) $client->mpin_hash,
            'mpin_status' => (bool) $client->mpin_hash,
            'next_step' => $client->mpin_hash ? 'verify_mpin' : 'set_mpin',
            'fcm_stored' => (bool) $this->resolveFcmToken($request),
            'client' => $this->clientBrief($client),
            'default_credentials' => [
                'username' => $this->sanitizeClientEmail($client->client_email) ?: $client->client_phone,
                'password_hint' => 'Default password is your registered mobile number',
            ],
        ]);
    }

    /**
     * POST /api/customer/auth/reset-password
     * After forgot_password OTP verify.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $mobile = $this->resolveMobile($request);
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $client = $this->assertPendingAuth($mobile, ['forgot_password']);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $user = $client->user;
        $user->password = Hash::make((string) $request->input('password'));
        $user->save();

        $this->beginPendingAuth($client, $user, 'login', $request);

        return response()->json([
            'status' => true,
            'message' => 'Password reset successfully. Complete MPIN to continue.',
            'mpin' => (bool) $client->mpin_hash,
            'mpin_set' => (bool) $client->mpin_hash,
            'mpin_status' => (bool) $client->mpin_hash,
            'next_step' => $client->mpin_hash ? 'verify_mpin' : 'set_mpin',
            'client' => $this->clientBrief($client),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // MPIN
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/customer/auth/set-mpin  (first time after login/OTP)
     */
    public function setMpin(Request $request): JsonResponse
    {
        $request->validate([
            'mpin' => 'required|digits_between:4,6',
            'mpin_confirmation' => 'nullable|same:mpin',
        ] + $this->deviceFieldRules());

        $plainToken = trim((string) ($request->bearerToken() ?: $request->input('token') ?: ''));
        $user = $plainToken !== '' ? $this->userFromBearer($request) : null;
        $mobile = $this->optionalMobile($request);

        if ($user) {
            $client = $user->client;
            if (! $client) {
                return response()->json(['status' => false, 'message' => 'Client profile not found'], 404);
            }
        } else {
            if (! $mobile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Send Authorization: Bearer {token} or registered mobile number.',
                    'next_step' => 'login',
                ], 401);
            }

            $client = $this->assertPendingAuth($mobile, ['login', 'first_login']);
            if ($client instanceof JsonResponse) {
                return $client;
            }
        }

        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        $mpinVal = (string) $request->input('mpin');
        $client->mpin = $mpinVal;
        $client->mpin_hash = Hash::make($mpinVal);
        $client->mpin_set_at = now();
        $client->save();

        if ($mobile) {
            $this->mergeDeviceFromSession($request, $mobile);
            Cache::forget($this->sessionCacheKey($mobile));
        }

        Log::info('set-mpin FCM received', [
            'client_id' => $client->id,
            'user_id' => $client->user_id,
            'has_fcm' => (bool) $this->resolveFcmToken($request),
            'fcm_length' => strlen((string) $this->resolveFcmToken($request)),
            'request_keys' => array_keys($request->all()),
        ]);

        return $this->issueBearerToken($request, $client, 'MPIN set successfully. Logged in.');
    }

    /**
     * POST /api/customer/auth/verify-mpin
     * Preferred: Authorization: Bearer {token} + { mpin }  (no mobile).
     * Fallback (first login, no token yet): pending login/OTP session + mobile + mpin.
     */
    public function verifyMpin(Request $request): JsonResponse
    {
        $request->validate([
            'mpin' => 'required|digits_between:4,6',
        ] + $this->deviceFieldRules());

        $plainToken = trim((string) ($request->bearerToken() ?: $request->input('token') ?: ''));
        $user = $plainToken !== '' ? $this->userFromBearer($request) : null;
        $fromToken = $user !== null;
        $mobile = $this->optionalMobile($request);

        if ($plainToken !== '' && ! $fromToken) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token. Please login again.',
                'next_step' => 'login',
            ], 401);
        }

        if ($fromToken) {
            $client = $user->client;
            if (! $client) {
                return response()->json(['status' => false, 'message' => 'Client profile not found'], 404);
            }
        } else {
            if (! $mobile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Send Authorization: Bearer {token}. Mobile is not required when the token is sent.',
                    'next_step' => 'login',
                ], 401);
            }

            $client = $this->assertPendingAuth($mobile, ['login', 'first_login']);
            if ($client instanceof JsonResponse) {
                return $client;
            }
        }

        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        if (! $client->mpin_hash) {
            return response()->json([
                'status' => false,
                'message' => 'MPIN not set. Please set MPIN first.',
                'next_step' => 'set_mpin',
            ], 422);
        }

        $mpinInput = (string) $request->input('mpin');
        if (! Hash::check($mpinInput, $client->mpin_hash)) {
            return response()->json(['status' => false, 'message' => 'Invalid MPIN'], 401);
        }

        if (empty($client->mpin)) {
            $client->mpin = $mpinInput;
            $client->save();
        }

        if ($fromToken) {
            return $this->issueBearerToken(
                $request,
                $client,
                'Login successful',
                $request->bearerToken() ?: (string) $request->input('token')
            );
        }

        $this->mergeDeviceFromSession($request, $mobile);
        Cache::forget($this->sessionCacheKey($mobile));

        Log::info('verify-mpin FCM received', [
            'client_id' => $client->id,
            'user_id' => $client->user_id,
            'has_fcm' => (bool) $this->resolveFcmToken($request),
            'fcm_length' => strlen((string) $this->resolveFcmToken($request)),
            'request_keys' => array_keys($request->all()),
        ]);

        return $this->issueBearerToken($request, $client, 'Login successful');
    }

    /**
     * POST /api/customer/auth/forgot-password/send-otp
     */
    public function forgotPasswordSendOtp(Request $request): JsonResponse
    {
        $request->merge(['purpose' => 'forgot_password']);

        return $this->sendOtp($request);
    }

    /**
     * POST /api/customer/auth/forgot-password/verify-otp
     */
    public function forgotPasswordVerifyOtp(Request $request): JsonResponse
    {
        $request->merge(['purpose' => 'forgot_password']);

        return $this->verifyOtp($request);
    }

    /**
     * POST /api/customer/auth/forgot-mpin/send-otp
     */
    public function forgotMpinSendOtp(Request $request): JsonResponse
    {
        $request->merge(['purpose' => 'forgot_mpin']);

        return $this->sendOtp($request);
    }

    /**
     * POST /api/customer/auth/forgot-mpin/verify-otp
     * OTP + new MPIN in one step. No separate reset API needed.
     */
    public function forgotMpinVerifyOtp(Request $request): JsonResponse
    {
        $mobile = $this->resolveMobile($request);
        $request->validate([
            'otp' => 'required|digits:6',
            'mpin' => 'required|digits_between:4,6',
            'mpin_confirmation' => 'required|same:mpin',
        ] + $this->deviceFieldRules());

        $client = $this->findClientByMobile($mobile);
        if (! $client) {
            return response()->json(['status' => false, 'message' => 'Client not found'], 404);
        }

        $client = $this->ensureClientHasUser($client, $mobile);

        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        $user = $client->user;
        if (! $this->isOtpValid($user, $mobile, (string) $request->input('otp'))) {
            return response()->json(['status' => false, 'message' => 'Invalid or expired OTP'], 422);
        }

        UserOtp::where('user_id', $user->id)->where('user_type', 'client')->delete();

        return $this->completeForgotMpinReset($request, $client, $mobile);
    }

    /**
     * POST /api/customer/auth/forgot-mpin/reset
     * Legacy: after OTP verify without mpin. Prefer verify-otp with mpin.
     */
    public function forgotMpinReset(Request $request): JsonResponse
    {
        $mobile = $this->resolveMobile($request);
        $request->validate([
            'mpin' => 'required|digits_between:4,6',
            'mpin_confirmation' => 'required|same:mpin',
        ] + $this->deviceFieldRules());

        $client = $this->assertPendingAuth($mobile, ['forgot_mpin']);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $this->mergeDeviceFromSession($request, $mobile);

        return $this->completeForgotMpinReset($request, $client, $mobile);
    }

    /**
     * POST /api/customer/auth/change-mpin (authenticated)
     */
    public function changeMpin(Request $request): JsonResponse
    {
        $request->validate([
            'current_mpin' => 'required|digits_between:4,6',
            'mpin' => 'required|digits_between:4,6|different:current_mpin',
            'mpin_confirmation' => 'required|same:mpin',
        ]);

        $user = $request->user();
        $client = $user?->client;
        if (! $client) {
            return response()->json(['status' => false, 'message' => 'Client profile not found'], 404);
        }

        if (! $client->mpin_hash || ! Hash::check((string) $request->input('current_mpin'), $client->mpin_hash)) {
            return response()->json(['status' => false, 'message' => 'Current MPIN is incorrect'], 401);
        }

        $mpinVal = (string) $request->input('mpin');
        $client->mpin = $mpinVal;
        $client->mpin_hash = Hash::make($mpinVal);
        $client->mpin_set_at = now();
        $client->save();

        return response()->json(['status' => true, 'message' => 'MPIN changed successfully']);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $request->user()->currentAccessToken()) {
            $this->expireFcmToken($user, $request);
            $request->user()->currentAccessToken()->delete();

            return response()->json(['status' => true, 'message' => 'Logged out successfully']);
        }

        return response()->json([
            'status' => false,
            'message' => 'Unable to logout. Token not found or invalid.',
        ], 401);
    }

    /**
     * POST /api/customer/auth/fcm-token
     * Body: { fcm_token|device_token, device_id?, device_name?, device_model? }
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $request->validate($this->deviceFieldRules());

        $user = $request->user();
        $client = $user?->client;
        if (! $user || ! $client) {
            return response()->json(['status' => false, 'message' => 'Client profile not found'], 404);
        }

        $token = $this->resolveFcmToken($request);
        if (! $token) {
            return response()->json([
                'status' => false,
                'message' => 'fcm_token is required.',
            ], 422);
        }

        if (! PushNotificationService::isLikelyFcmToken($token)) {
            return response()->json([
                'status' => false,
                'message' => 'That value is not an FCM device token. Send the Firebase token from the app, not the API bearer token.',
            ], 422);
        }

        $this->persistFcmToken($user, $client, $request);

        return response()->json([
            'status' => true,
            'message' => 'FCM device token saved successfully',
            'data' => [
                'user_id' => $user->id,
                'client_id' => $client->id,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated. Send Authorization: Bearer {token}.',
            ], 401);
        }

        $client = $user->client;
        $clientData = null;
        $scheduleDetails = [
            'has_account' => false,
            'public_url' => null,
            'view_schedule_url' => null,
            'message' => "You don't have an active loan, chit, or fixed deposit account.",
        ];

        if ($client) {
            $clientData = (new ClientResource($client))->resolve();
            if (method_exists($client, 'getPublicScheduleDetails')) {
                $scheduleDetails = $client->getPublicScheduleDetails();
            }
        }

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nickname' => $user->nickname ?? $client?->nickname,
                    'phone' => $user->phone,
                    'email' => $this->sanitizeClientEmail($user->email) ?: $this->sanitizeClientEmail($client?->client_email),
                    'type' => 'client',
                    'status' => $client?->status ?: 'pending',
                    'is_active' => $client ? $client->canAccessCustomerApp() : false,
                    'username_hint' => $this->sanitizeClientEmail($client?->client_email) ?: ($client?->client_phone ?: $user->phone),
                ],
                'is_active' => $client ? $client->canAccessCustomerApp() : false,
                'client_status' => $client?->status ?: 'pending',
                'view_schedule_url' => $scheduleDetails['view_schedule_url'],
                'public_schedule_url' => $scheduleDetails['public_url'],
                'schedule_info' => $scheduleDetails,
                'client' => $clientData,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    protected function deviceFieldRules(): array
    {
        return [
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_model' => 'nullable|string|max:255',
            'fcm_token' => 'nullable|string|max:4096',
            'device_token' => 'nullable|string|max:4096',
            'firebase_token' => 'nullable|string|max:4096',
            'push_token' => 'nullable|string|max:4096',
            'fcmToken' => 'nullable|string|max:4096',
            'deviceToken' => 'nullable|string|max:4096',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }

    protected function resolveFcmToken(Request $request): ?string
    {
        $candidates = [
            $request->input('fcm_token'),
            $request->input('device_token'),
            $request->input('firebase_token'),
            $request->input('push_token'),
            $request->input('fcmToken'),
            $request->input('deviceToken'),
            $request->input('firebaseToken'),
            $request->input('pushToken'),
            $request->input('data.fcm_token'),
            $request->input('data.device_token'),
            $request->input('data.fcmToken'),
            $request->header('X-FCM-Token'),
            $request->header('X-Device-Token'),
        ];

        foreach ($candidates as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                return $token;
            }
        }

        return null;
    }

    /**
     * Store FCM immediately, then keep a short pending-auth flag keyed by mobile
     * (no session_token is returned to the app).
     */
    protected function beginPendingAuth(Client $client, User $user, string $purpose, Request $request): void
    {
        if ($this->resolveFcmToken($request)) {
            $this->persistFcmToken($user, $client, $request);
        }
        $this->createAuthSession($client, $user, $purpose, $request);
    }

    protected function mergeDeviceFromSession(Request $request, string $mobile): void
    {
        $payload = Cache::get($this->sessionCacheKey($mobile));
        if (! is_array($payload)) {
            return;
        }

        foreach (['fcm_token', 'device_token', 'firebase_token', 'push_token', 'fcmToken', 'deviceToken', 'device_id', 'device_name', 'device_model'] as $field) {
            if (! $request->filled($field) && ! empty($payload[$field])) {
                $request->merge([$field => $payload[$field]]);
            }
        }
    }

    protected function completeForgotMpinReset(Request $request, Client $client, string $mobile): JsonResponse
    {
        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        $request->validate([
            'mpin' => 'required|digits_between:4,6',
            'mpin_confirmation' => 'required|same:mpin',
        ] + $this->deviceFieldRules());

        $mpinVal = (string) $request->input('mpin');
        $client->mpin = $mpinVal;
        $client->mpin_hash = Hash::make($mpinVal);
        $client->mpin_set_at = now();
        $client->save();

        Cache::forget($this->sessionCacheKey($mobile));

        return $this->issueBearerToken($request, $client, 'MPIN reset successfully. Logged in.');
    }

    protected function persistFcmToken(User $user, Client $client, Request $request): void
    {
        $deviceId = $request->input('device_id') ?: ('customer-app-' . $client->id);
        $deviceToken = $this->resolveFcmToken($request);
        if ($deviceToken && ! PushNotificationService::isLikelyFcmToken($deviceToken)) {
            Log::warning('Ignored non-FCM token on customer auth', [
                'user_id' => $user->id,
                'client_id' => $client->id,
                'token_length' => strlen($deviceToken),
                'token_prefix' => substr($deviceToken, 0, 12),
            ]);
            $deviceToken = null;
        }

        $deviceValues = [
            'user_type' => 'client',
            'device_name' => $request->input('device_name', 'Customer App'),
            'device_model' => $request->input('device_model', 'Mobile'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'ip_address' => $request->ip(),
            'login_at' => now(),
            'logout_at' => null,
        ];
        if ($deviceToken) {
            $deviceValues['device_token'] = $deviceToken;
        }

        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            $deviceValues
        );

        if (! $deviceToken) {
            return;
        }

        UserDevice::where('device_token', $deviceToken)
            ->where('user_id', '!=', $user->id)
            ->update(['device_token' => null]);

        $client->fcm_token = $deviceToken;
        $client->save();

        $user->fcm_token = $deviceToken;
        $user->save();

        Log::info('Customer FCM token stored', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_length' => strlen($deviceToken),
        ]);
    }

    /**
     * Logout: expire this device's FCM token so push stops. If another device
     * is still logged in, keep that token on users/clients.
     */
    protected function expireFcmToken(User $user, Request $request): void
    {
        $deviceId = $request->input('device_id');

        if ($deviceId) {
            UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->update([
                    'device_token' => null,
                    'logout_at' => Carbon::now(),
                ]);
        } else {
            $active = UserDevice::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first()
                ?: UserDevice::where('user_id', $user->id)->latest('login_at')->first();

            if ($active) {
                $active->device_token = null;
                $active->logout_at = Carbon::now();
                $active->save();
            }
        }

        $remaining = UserDevice::where('user_id', $user->id)
            ->whereNull('logout_at')
            ->whereNotNull('device_token')
            ->latest('login_at')
            ->value('device_token');

        $user->fcm_token = $remaining ?: null;
        $user->save();

        if ($user->client) {
            $user->client->fcm_token = $remaining ?: null;
            $user->client->save();
        }
    }

    protected function findUserByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $username)->first()
                ?? optional(Client::where('client_email', $username)->first())->user;
        }

        $mobile = $this->normalizeToTenDigitMobile($username);
        if (strlen($mobile) === 10) {
            $client = $this->findClientByMobile($mobile);
            if ($client) {
                return $this->ensureClientHasUser($client, $mobile)->user;
            }

            return User::where(function ($q) use ($mobile) {
                $q->whereIn('phone', $this->phoneVariants($mobile))
                    ->orWhere('phone', 'like', '%'.$mobile);
            })->first();
        }

        return User::where('email', $username)->first()
            ?? optional(Client::where('client_email', $username)->first())->user;
    }

    protected function ensureDefaultPassword(User $user, Client $client): void
    {
        $phone = preg_replace('/\D+/', '', (string) ($client->client_phone ?: $user->phone)) ?? '';
        if (strlen($phone) !== 10) {
            return;
        }

        // If password empty/null somehow, set to phone
        if (empty($user->password)) {
            $user->password = Hash::make($phone);
            $user->save();
        }
    }

    protected function userFromBearer(Request $request): ?User
    {
        $plain = trim((string) ($request->bearerToken() ?: $request->input('token') ?: ''));
        if ($plain === '') {
            return null;
        }

        if (! $request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer '.$plain);
        }

        $access = PersonalAccessToken::findToken($plain);
        if (! $access) {
            return null;
        }

        if ($access->expires_at && $access->expires_at->isPast()) {
            return null;
        }

        $user = $access->tokenable;

        return $user instanceof User ? $user : null;
    }

    protected function optionalMobile(Request $request): ?string
    {
        $mobile = $this->normalizeToTenDigitMobile((string) ($request->input('mobile') ?: $request->input('phone') ?: ''));

        if (strlen($mobile) !== 10) {
            $mobile = $this->normalizeToTenDigitMobile(trim((string) $request->input('username')));
        }

        return strlen($mobile) === 10 ? $mobile : null;
    }

    protected function resolveMobile(Request $request): string
    {
        $request->validate([
            'mobile' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'username' => 'nullable|string|max:255',
        ]);

        $mobile = $this->normalizeToTenDigitMobile((string) ($request->input('mobile') ?: $request->input('phone') ?: ''));

        if (strlen($mobile) !== 10) {
            $mobile = $this->normalizeToTenDigitMobile(trim((string) $request->input('username')));
        }

        if (strlen($mobile) !== 10) {
            throw ValidationException::withMessages([
                'mobile' => ['Valid 10-digit mobile number is required (mobile or phone)'],
            ]);
        }

        return $mobile;
    }

    protected function normalizeToTenDigitMobile(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 10) {
            return $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '091')) {
            return substr($digits, 3);
        }
        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    protected function phoneVariants(string $tenDigit): array
    {
        return array_values(array_unique([
            $tenDigit,
            '91'.$tenDigit,
            '+91'.$tenDigit,
            '0'.$tenDigit,
            '+91 '.$tenDigit,
            '+91-'.$tenDigit,
            '91-'.$tenDigit,
            '91 '.$tenDigit,
        ]));
    }

    protected function isExistingCustomerRecord(Client $client): bool
    {
        return filled($client->aadhaar_number)
            || filled($client->customer_id)
            || filled($client->mpin_hash)
            || filled($client->location_id)
            || in_array((string) $client->status, ['active', 'pending'], true);
    }

    protected function findClientByMobile(string $mobile): ?Client
    {
        $mobile = $this->normalizeToTenDigitMobile($mobile);
        if (strlen($mobile) !== 10) {
            return null;
        }

        $variants = $this->phoneVariants($mobile);

        $client = Client::withTrashed()
            ->with('user')
            ->where(function ($q) use ($mobile, $variants) {
                $q->whereIn('client_phone', $variants)
                    ->orWhereIn('alternate_phone', $variants)
                    ->orWhere('client_phone', 'like', '%'.$mobile)
                    ->orWhere('alternate_phone', 'like', '%'.$mobile)
                    ->orWhereHas('user', function ($u) use ($mobile, $variants) {
                        $u->whereIn('phone', $variants)
                            ->orWhere('phone', 'like', '%'.$mobile);
                    });
            })
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->first();

        if (! $client) {
            $user = User::where(function ($q) use ($mobile, $variants) {
                $q->whereIn('phone', $variants)
                    ->orWhere('phone', 'like', '%'.$mobile);
            })->first();

            if ($user) {
                $client = Client::withTrashed()
                    ->with('user')
                    ->where('user_id', $user->id)
                    ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if ($client && $client->trashed()) {
            $client->restore();
            $client->load('user');
        }

        return $client;
    }

    protected function ensureClientHasUser(Client $client, string $mobile): Client
    {
        $client->loadMissing('user');
        $mobile = $this->normalizeToTenDigitMobile($mobile) ?: (string) $client->client_phone;

        $realEmail = $this->sanitizeClientEmail($client->client_email);

        if ($client->user) {
            $user = $client->user;
            if ($this->isGeneratedDummyEmail($user->email)) {
                $user->email = $realEmail;
                $user->save();
            }
            if ($this->isGeneratedDummyEmail($client->client_email)) {
                $client->client_email = $realEmail;
                $client->save();
            }
            $this->ensureDefaultPassword($user, $client);

            return $client;
        }

        $user = User::where(function ($q) use ($mobile) {
            $q->whereIn('phone', $this->phoneVariants($mobile))
                ->orWhere('phone', 'like', '%'.$mobile);
        })->first();

        if (! $user && filled($realEmail)) {
            $user = User::where('email', $realEmail)->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $client->client_name ?: ('Client ' . $mobile),
                'phone' => $mobile,
                'email' => $realEmail,
                'password' => Hash::make($mobile),
                'status' => 'active',
            ]);
        } else {
            if ($this->isGeneratedDummyEmail($user->email)) {
                $user->email = $realEmail;
                $user->save();
            } elseif (empty($user->email) && $realEmail) {
                $user->email = $realEmail;
                $user->save();
            }
        }

        if (method_exists($user, 'hasRole') && ! $user->hasRole('Client')) {
            try {
                $user->assignRole('Client');
            } catch (\Throwable $e) {
                Log::warning('Unable to assign Client role', ['user_id' => $user->id, 'client_id' => $client->id]);
            }
        }

        $client->user_id = $user->id;
        if (empty($client->client_phone)) {
            $client->client_phone = $mobile;
        }
        if ($this->isGeneratedDummyEmail($client->client_email)) {
            $client->client_email = $realEmail;
        }
        $client->save();
        $this->ensureDefaultPassword($user, $client);

        return $client->fresh(['user']);
    }

    protected function resolvePurpose(Request $request): string
    {
        $purpose = strtolower((string) $request->input('purpose', 'first_login'));
        if (in_array($purpose, ['registration', 'register', 'signup'], true)) {
            return 'registration';
        }
        if (! in_array($purpose, ['first_login', 'login', 'forgot_password', 'forgot_mpin'], true)) {
            $purpose = 'first_login';
        }

        return $purpose;
    }

    protected function resolveOrCreateClient(string $mobile): Client
    {
        $client = $this->findClientByMobile($mobile);

        if (! $client) {
            $user = User::where(function ($q) use ($mobile) {
                $q->whereIn('phone', $this->phoneVariants($mobile))
                    ->orWhere('phone', 'like', '%'.$mobile);
            })->first();

            if (! $user) {
                $user = User::create([
                    'name' => 'Client ' . $mobile,
                    'phone' => $mobile,
                    'email' => null,
                    'password' => Hash::make($mobile), // default password = mobile
                    'status' => 'active',
                ]);
            } else {
                if ($this->isGeneratedDummyEmail($user->email)) {
                    $user->email = null;
                    $user->save();
                }
                $this->ensureDefaultPassword($user, new Client(['client_phone' => $mobile]));
            }

            if (method_exists($user, 'hasRole') && ! $user->hasRole('Client')) {
                try {
                    $user->assignRole('Client');
                } catch (\Throwable $e) {
                    Log::warning('Unable to assign Client role', ['user_id' => $user->id]);
                }
            }

            // New client stays pending; becomes active only after admin verifies KYC.
            $client = Client::create([
                'user_id' => $user->id,
                'client_phone' => $mobile,
                'client_name' => $user->name,
                'client_email' => $this->sanitizeClientEmail($user->email),
                'status' => 'pending',
            ]);

            return $client->fresh(['user']);
        }

        return $this->ensureClientHasUser($client, $mobile);
    }

    protected function dispatchOtp(User $user, Client $client, string $mobile, string $purpose): JsonResponse
    {
        UserOtp::where('user_id', $user->id)->where('user_type', 'client')->delete();

        $otpCode = random_int(100000, 999999);
        UserOtp::create([
            'user_id' => $user->id,
            'user_type' => 'client',
            'otp_code' => $otpCode,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        if ($mobile === self::TEST_PHONE) {
            SmsOtpLog::record([
                'purpose' => $purpose,
                'mobile' => $mobile,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'status' => SmsOtpLog::STATUS_TEST,
                'provider' => 'test',
                'provider_message' => 'OTP sent successfully (Test Mode)',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'OTP sent successfully (Test Mode)',
                'purpose' => $purpose,
                'mpin' => (bool) $client->mpin_hash,
                'mpin_set' => (bool) $client->mpin_hash,
                'mpin_status' => (bool) $client->mpin_hash,
                'expires_in' => self::OTP_TTL_MINUTES * 60,
            ]);
        }

        $result = SMSUtility::otp($mobile, $otpCode);
        $ok = is_array($result) ? (bool) ($result['status'] ?? false) : (bool) $result;
        $message = is_array($result)
            ? (string) ($result['message'] ?? ($ok ? 'OTP sent successfully' : 'Failed to send OTP'))
            : ($ok ? 'OTP sent successfully' : 'Failed to send OTP');

        SmsOtpLog::record([
            'purpose' => $purpose,
            'mobile' => $mobile,
            'user_id' => $user->id,
            'client_id' => $client->id,
            'status' => $ok ? SmsOtpLog::STATUS_SENT : SmsOtpLog::STATUS_FAILED,
            'provider' => 'msg91',
            'provider_message' => $message,
        ]);

        if (! $ok) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully to your mobile number',
            'purpose' => $purpose,
            'mpin' => (bool) $client->mpin_hash,
            'mpin_set' => (bool) $client->mpin_hash,
            'mpin_status' => (bool) $client->mpin_hash,
            'expires_in' => self::OTP_TTL_MINUTES * 60,
        ]);
    }

    protected function isOtpValid(User $user, string $mobile, string $otp): bool
    {
        if ($mobile === self::TEST_PHONE && $otp === self::TEST_OTP) {
            return true;
        }

        return UserOtp::where('user_id', $user->id)
            ->where('user_type', 'client')
            ->where('otp_code', $otp)
            ->where('expires_at', '>=', now())
            ->latest()
            ->exists();
    }

    protected function sessionCacheKey(string $mobile): string
    {
        return 'customer_auth_session_' . $mobile;
    }

    protected function createAuthSession(Client $client, User $user, string $purpose, ?Request $request = null): void
    {
        $mobile = (string) $client->client_phone;
        $payload = [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'mobile' => $mobile,
            'purpose' => $purpose,
            'verified_at' => now()->toDateTimeString(),
            'fcm_token' => $request ? $this->resolveFcmToken($request) : null,
            'device_id' => $request?->input('device_id'),
            'device_name' => $request?->input('device_name'),
            'device_model' => $request?->input('device_model'),
            'device_token' => $request?->input('device_token') ?: ($request ? $this->resolveFcmToken($request) : null),
        ];

        Cache::put($this->sessionCacheKey($mobile), $payload, now()->addMinutes(self::SESSION_TTL_MINUTES));
    }

    /**
     * @param  list<string>  $allowedPurposes
     * @return Client|JsonResponse
     */
    protected function assertPendingAuth(string $mobile, array $allowedPurposes = [])
    {
        $payload = Cache::get($this->sessionCacheKey($mobile));

        if (! $payload) {
            return response()->json([
                'status' => false,
                'message' => 'Please login or verify OTP first.',
                'next_step' => 'login',
            ], 401);
        }

        if ($allowedPurposes !== [] && ! in_array($payload['purpose'] ?? '', $allowedPurposes, true)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid step. Follow next_step from the previous response.',
                'next_step' => $payload['purpose'] ?? 'login',
            ], 422);
        }

        $client = Client::with('user')->find($payload['client_id'] ?? 0);
        if (! $client || ! $client->user) {
            return response()->json(['status' => false, 'message' => 'Client not found'], 404);
        }

        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        return $client;
    }

    protected function denyIfInactive(?Client $client): ?JsonResponse
    {
        if (CustomerAppAccess::isBlocked($client)) {
            return CustomerAppAccess::deniedResponse($client);
        }

        return null;
    }

    protected function clientBrief(Client $client): array
    {
        $status = $client->status ?: 'pending';

        return [
            'id' => $client->id,
            'customer_id' => $client->displayCustomerId(),
            'name' => $client->client_name,
            'mobile' => $client->client_phone,
            'email' => $this->sanitizeClientEmail($client->client_email),
            'mpin' => $client->mpin,
            'mpin_set' => (bool) $client->mpin_hash,
            'mpin_status' => (bool) $client->mpin_hash,
            'status' => $status,
            'kyc_status' => $client->kycStatus(),
            'is_active' => $client->canAccessCustomerApp(),
            'kyc_verified' => $client->isKycVerified(),
        ];
    }

    protected function issueBearerToken(Request $request, Client $client, string $message, ?string $reuseToken = null): JsonResponse
    {
        if ($denied = $this->denyIfInactive($client)) {
            return $denied;
        }

        $user = $client->user;
        $this->persistFcmToken($user, $client, $request);
        $user->refresh();
        $client->refresh();

        if ($reuseToken) {
            $token = $reuseToken;
        } else {
            $deviceId = $request->input('device_id') ?: ('customer-app-' . $client->id);
            $user->tokens()->where('name', $deviceId)->delete();
            $token = $user->createToken($deviceId)->plainTextToken;
        }

        $clientStatus = $client->status ?: 'pending';

        return response()->json([
            'status' => true,
            'message' => $message,
            'is_active' => $client->canAccessCustomerApp(),
            'client_status' => $clientStatus,
            'token_type' => 'Bearer',
            'token' => $token,
            'authorization' => 'Bearer '.$token,
            'fcm_stored' => (bool) $client->fcm_token,
            'user' => [
                'id' => $user->id,
                'name' => $client->client_name ?: $user->name,
                'mobile' => $client->client_phone,
                'phone' => $client->client_phone,
                'email' => $this->sanitizeClientEmail($client->client_email) ?: $this->sanitizeClientEmail($user->email),
                'type' => 'client',
                'status' => $clientStatus,
                'is_active' => $client->canAccessCustomerApp(),
                'kyc_status' => $client->kycStatus(),
            ],
            'client' => $this->clientBrief($client),
        ]);
    }

    protected function isGeneratedDummyEmail(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $trimmed = strtolower(trim($email));

        return str_ends_with($trimmed, '@client.app')
            || str_ends_with($trimmed, '@shanmugafinance.local')
            || str_ends_with($trimmed, '@example.com');
    }

    protected function sanitizeClientEmail(?string $email): ?string
    {
        if (empty($email) || $this->isGeneratedDummyEmail($email)) {
            return null;
        }

        return trim($email);
    }
}
