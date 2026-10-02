<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\ClientResource;
use App\Http\Resources\KycDetailResource;
use App\Http\Resources\NomineeDetailResource;
use App\Http\Resources\EmploymentInformationResource;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\kycDetail;
use App\Models\Nominee;
use App\Models\EmployeeInformation;
use Illuminate\Support\Facades\Storage;

class ClientControllerApi extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $client = $user->client;

        if (! $client) {
            return response()->json([
                'status' => false,
                'message' => 'Client profile not found',
            ], 404);
        }

        $profile = Client::findOrFail($client->id);
        $customerId = $profile->displayCustomerId();
        $profileData = (new ClientResource($profile))->resolve();
        $profileData['customer_id'] = $customerId;

        return response()->json([
            'status' => true,
            'message' => 'Client data fetched successfully',
            'customer_id' => $customerId,
            'profile' => $profileData,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            return response()->json([
                'status' => false,
                'message' => 'Client profile not found',
            ], 404);
        }

        $validated = $request->validate([
            'name'                  => 'nullable|string|max:255',
            'client_name'           => 'nullable|string|max:255',
            'full_name'             => 'nullable|string|max:255',
            'nickname'              => 'nullable|string|max:255',
            'email'                 => 'nullable|email|max:255',
            'client_email'          => 'nullable|email|max:255',
            'phone'                 => 'nullable|string|max:20',
            'client_phone'          => 'nullable|string|max:20',
            'alternate_phone'       => 'nullable|string|max:20',
            'address'               => 'nullable|string|max:500',
            'care_of'               => 'nullable|string|max:255',
            'date_of_birth'         => 'nullable|date',
            'gender'                => 'nullable|string|max:20',
            'marital_status'        => 'nullable|string|max:50',
            'pincode'               => 'nullable|string|max:10',
            'state'                 => 'nullable|string|max:100',
            'city'                  => 'nullable|string|max:100',
            'profile_image'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'avatar'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            // Nominee Details
            'nominee_name'          => 'nullable|string|max:255',
            'nominee_relationship'  => 'nullable|string|max:100',
            'relation'              => 'nullable|string|max:100',
            'nominee_mobile'        => 'nullable|string|max:20',
            'nominee_phone'         => 'nullable|string|max:20',
            'nominee1_name'         => 'nullable|string|max:255',
            'nominee1_relationship' => 'nullable|string|max:100',
            'nominee1_mobile'       => 'nullable|string|max:20',
            'nominee2_name'         => 'nullable|string|max:255',
            'nominee2_relationship' => 'nullable|string|max:100',
            'nominee2_mobile'       => 'nullable|string|max:20',

            // Document Details & Images
            'aadhaar_number'        => 'nullable|string|max:20',
            'pan_number'            => 'nullable|string|max:20',
            'aadhaar_image'         => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
            'aadhaar_front_image'   => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
            'aadhaar_image_back'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
            'aadhaar_back_image'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
            'pan_image'             => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:5120',
            'selfie_image'          => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Update User & Basic Client Details
        $name = $request->input('name') ?? $request->input('client_name') ?? $request->input('full_name');
        if ($name) {
            $user->name = $name;
            $client->client_name = $name;
        }

        if ($request->has('nickname')) {
            $nicknameVal = $request->input('nickname');
            $cleanNickname = ($nicknameVal !== null && trim((string) $nicknameVal) !== '') ? trim((string) $nicknameVal) : null;
            $client->nickname = $cleanNickname;
            if ($user) {
                $user->nickname = $cleanNickname;
            }
        }

        $email = $request->input('email') ?? $request->input('client_email');
        if ($email) {
            $user->email = $email;
            $client->client_email = $email;
        }

        $phone = $request->input('phone') ?? $request->input('client_phone');
        if ($phone) {
            $user->phone = $phone;
            $client->client_phone = $phone;
        }

        if ($request->exists('alternate_phone')) {
            $client->alternate_phone = Client::normalizeOptionalPhone($request->input('alternate_phone'));
        }
        if ($request->has('address')) $client->address = $request->address;
        if ($request->has('care_of')) $client->care_of = $request->care_of;
        if ($request->has('date_of_birth')) $client->date_of_birth = $request->date_of_birth;
        if ($request->has('gender')) $client->gender = $request->gender;
        if ($request->has('marital_status')) $client->marital_status = $request->marital_status;
        if ($request->has('pincode')) $client->pincode = $request->pincode;
        if ($request->has('state')) $client->state = $request->state;
        if ($request->has('city')) $client->city = $request->city;
        if ($request->has('aadhaar_number')) $client->aadhaar_number = $request->aadhaar_number;

        // Profile Image file upload
        $imageFile = $request->file('profile_image') ?: ($request->file('image') ?: $request->file('avatar'));
        if ($imageFile && $imageFile->isValid()) {
            if ($client->profile_image) {
                Storage::disk('public')->delete($client->profile_image);
            }
            $client->profile_image = $imageFile->store('profiles', 'public');
        }

        // Profile Image Base64 handling
        $b64Image = $request->input('profile_image_base64') ?? $request->input('image_base64') ?? $request->input('avatar_base64');
        if ($b64Image && is_string($b64Image)) {
            try {
                if (preg_match('/^data:([^;]+);base64,(.*)$/', $b64Image, $matches)) {
                    $mimeType = $matches[1];
                    $data = base64_decode($matches[2]);
                } else {
                    $mimeType = 'image/jpeg';
                    $data = base64_decode($b64Image);
                }

                if ($data) {
                    $ext = explode('/', $mimeType)[1] ?? 'jpg';
                    $fileName = 'profile_' . time() . '_' . \Illuminate\Support\Str::random(5) . '.' . $ext;
                    $path = 'profiles/' . $fileName;
                    Storage::disk('public')->put($path, $data);
                    if ($client->profile_image) {
                        Storage::disk('public')->delete($client->profile_image);
                    }
                    $client->profile_image = $path;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Profile base64 image error: ' . $e->getMessage());
            }
        }

        $user->save();
        $client->save();

        // Update / Save KYC Document Details & Images
        $kycData = [];
        if ($request->has('aadhaar_number')) $kycData['aadhaar_number'] = $request->aadhaar_number;
        if ($request->has('pan_number')) $kycData['pan_number'] = strtoupper((string) $request->pan_number);

        if ($request->hasFile('aadhaar_image') || $request->hasFile('aadhaar_front_image')) {
            $file = $request->file('aadhaar_image') ?: $request->file('aadhaar_front_image');
            $path = $file->store('kyc/documents', 'public');
            $kycData['aadhaar_image'] = $path;
            $client->aadhaar_photo_path = $path;
            $client->save();
        }

        if ($request->hasFile('aadhaar_image_back') || $request->hasFile('aadhaar_back_image')) {
            $file = $request->file('aadhaar_image_back') ?: $request->file('aadhaar_back_image');
            $path = $file->store('kyc/documents', 'public');
            $kycData['aadhaar_image_back'] = $path;
        }

        if ($request->hasFile('pan_image')) {
            $path = $request->file('pan_image')->store('kyc/documents', 'public');
            $kycData['pan_image'] = $path;
        }

        if ($request->hasFile('selfie_image')) {
            $path = $request->file('selfie_image')->store('kyc/documents', 'public');
            $kycData['selfie_image'] = $path;
        }

        if (!empty($kycData)) {
            kycDetail::updateOrCreate(
                ['client_id' => $client->id],
                $kycData
            );
        }

        // Update / Save Nominee Details
        $nominee1Name = $request->input('nominee1_name') ?? $request->input('nominee_name');
        $nominee1Rel  = $request->input('nominee1_relationship') ?? $request->input('nominee_relationship') ?? $request->input('relation');
        $nominee1Mob  = $request->input('nominee1_mobile') ?? $request->input('nominee_mobile') ?? $request->input('nominee_phone');
        $nominee2Name = $request->input('nominee2_name');
        $nominee2Rel  = $request->input('nominee2_relationship');
        $nominee2Mob  = $request->input('nominee2_mobile');

        if ($nominee1Name !== null || $nominee1Rel !== null || $nominee1Mob !== null || $nominee2Name !== null || $nominee2Rel !== null || $nominee2Mob !== null) {
            $nomineeData = [];
            if ($nominee1Name !== null) $nomineeData['nominee1_name'] = $nominee1Name;
            if ($nominee1Rel !== null) $nomineeData['nominee1_relationship'] = $nominee1Rel;
            if ($nominee1Mob !== null) $nomineeData['nominee1_mobile'] = $nominee1Mob;
            if ($nominee2Name !== null) $nomineeData['nominee2_name'] = $nominee2Name;
            if ($nominee2Rel !== null) $nomineeData['nominee2_relationship'] = $nominee2Rel;
            if ($nominee2Mob !== null) $nomineeData['nominee2_mobile'] = $nominee2Mob;

            Nominee::updateOrCreate(
                ['client_id' => $client->id],
                $nomineeData
            );
        }

        $client->refresh();
        $client->load(['kycDetail', 'nominee', 'employeeInformation']);

        $customerId = $client->displayCustomerId();
        $profileData = (new ClientResource($client))->resolve();
        $profileData['customer_id'] = $customerId;

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully',
            'customer_id' => $customerId,
            'profile' => $profileData,
            'user'    => $user,
            'client'  => $profileData,
        ]);
    }

    /**
     * Check if client details are filled
     */
    public function checkClientDetails()
    {
        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 404);
        }

        $isDetailsFilled = [
            'name' => !empty($client->client_name),
            'phone' => !empty($client->client_phone),
            'address' => !empty($client->address),
        ];

        $allFilled = collect($isDetailsFilled)->every(fn($filled) => $filled === true);

        $missingFields = collect($isDetailsFilled)
            ->filter(fn($filled) => !$filled)
            ->keys()
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => $allFilled ? 'All details are filled' : 'Some details are missing',
            'customer_id' => $client->displayCustomerId(),
            'all_filled' => $allFilled,
            'details' => $isDetailsFilled,
            'missing_fields' => $missingFields,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function kycDetails()
    {
      $user = Auth::user();
      $client = $user->client;

      $kycDetail = kycDetail::where('client_id', $client->id)->first();
      $nomineeDetail = Nominee::where('client_id', $client->id)->first();
      $employmentInfo = EmployeeInformation::where('client_id', $client->id)->first();

      return response()->json([
        'status' => true,
        'message' => 'Kyc Details fetched successfully',
        'customer_id' => $client->displayCustomerId(),
        'kycDetail' => new KycDetailResource($kycDetail),
        'nomineeDetail' => new NomineeDetailResource($nomineeDetail),
        'employmentInfo' => new EmploymentInformationResource($employmentInfo),
      ]);
    }
}
