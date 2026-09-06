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

        $profile = Client::findOrFail($client->id);

        return response()->json([
          'status' => true,
          'message' => 'Client data fetched successfully',
          'profile' => new ClientResource($profile),
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

        $validated = $request->validate([
            'name'           => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:500',
            'profile_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
            $client->client_name = $validated['name'];
        }

        if (!empty($validated['address'])) {
            $client->address = $validated['address'];
        }

        if ($request->hasFile('profile_image')) {
            if ($client && $client->profile_image) {
                Storage::disk('public')->delete($client->profile_image);
            }

            $path = $request->file('profile_image')->store('profiles', 'public');

            $client->profile_image = $path;
        }

        $user->save();
        if ($client) {
            $client->save();
        }

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully',
            'user'    => $user,
            'client'  => [
                'name'           => $client->name ?? $user->name,
                'address'        => $client->address,
                'profile_image'  => $client->profile_image ? asset('storage/' . $client->profile_image) : null,
            ],
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
        'kycDetail' => new KycDetailResource($kycDetail),
        'nomineeDetail' => new NomineeDetailResource($nomineeDetail),
        'employmentInfo' => new EmploymentInformationResource($employmentInfo),
      ]);
    }
}
