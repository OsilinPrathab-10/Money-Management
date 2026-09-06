<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Nominee;
use App\Models\KycDetail;
use App\Models\EmployeeInformation;

class KycControllerApi extends Controller
{
    Public function addNominee(Request $request)
    {
        $user = Auth::user();
        $client = \App\Models\Client::where('user_id', $user->id)->first();

        if (!$client) {
            return response()->json(['message' => 'Client not found for this user.'], 404);
        }

        $validatedData = $request->validate([
            'nominee1_name' => 'required|string|max:255',
            'nominee1_relationship' => 'required|string|max:100',
            'nominee1_mobile' => 'required|string|max:15',
            'nominee2_name' => 'required|string|max:255',
            'nominee2_relationship' => 'required|string|max:100',
            'nominee2_mobile' => 'required|string|max:15',
        ]);

        $nominee = \App\Models\Nominee::updateOrCreate(
            ['client_id' => $client->id],
            [
                'nominee1_name' => $validatedData['nominee1_name'],
                'nominee1_relationship' => $validatedData['nominee1_relationship'],
                'nominee1_mobile' => $validatedData['nominee1_mobile'],
                'nominee2_name' => $validatedData['nominee2_name'],
                'nominee2_relationship' => $validatedData['nominee2_relationship'],
                'nominee2_mobile' => $validatedData['nominee2_mobile'],
            ]
        );

        return response()->json([
            'message' => 'Nominee details added successfully.',
            'nominee' => $nominee
        ], 200);
    }

    public function addImage(Request $request)
    {
        $user = Auth::user();
        $client = \App\Models\Client::where('user_id', $user->id)->first();

        if (!$client) {
            return response()->json(['message' => 'Client not found for this user.'], 404);
        }

        $validatedData = $request->validate([
            'selfie_image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // Get the uploaded file
        $file = $request->file('selfie_image');

        // Read file contents and encode to Base64
        $imageData = file_get_contents($file->getRealPath());
        $base64Image = base64_encode($imageData);

        // Get mime type for proper decoding later
        $mimeType = $file->getMimeType();

        // Store as data URL format: data:image/jpeg;base64,{encoded_data}
        $encodedImage = 'data:' . $mimeType . ';base64,' . $base64Image;

        $kycDetail = $client->kycDetail()->updateOrCreate(
            ['client_id' => $client->id],
            ['selfie_image' => $encodedImage]
        );

        return response()->json([
            'message' => 'Image uploaded successfully.',
        ], 200);
    }

    public function addEmployeeInformation(Request $request)
    {
        $validated = $request->validate([
            'employment_type' => 'required|in:salaried,self_employed',

            // Salaried
            'company_name' => 'nullable|string|max:255',
            'job_type' => 'nullable|string',
            'monthly_salary' => 'nullable|numeric',
            'work_experience' => 'nullable|numeric',
            'salary_credit_bank' => 'nullable|string',
            'payslips' => 'nullable|array|max:3',
            'payslips.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',

            // Self-employed
            'business_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'business_category' => 'nullable|string|max:255',
            'years_in_business' => 'nullable|integer|min:0',
            'monthly_turnover' => 'nullable|numeric',
            'business_address' => 'nullable|string',
            'proofs' => 'nullable|array|max:2',
            'proofs.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = Auth::user();
        $client = $user->client;

        // Handle payslips
        // Salaried
        if ($request->employment_type === 'salaried' && $request->hasFile('payslips')) {
            $paths = [];

            foreach ($request->file('payslips') as $file) {
                $paths[] = $file->store('employment/payslips', 'public');
            }

            $validated['payslip_documents'] = $paths;
            $validated['business_proof_documents'] = null;
        }

        // Self-employed
        if ($request->employment_type === 'self_employed' && $request->hasFile('proofs')) {
            $paths = [];

            foreach ($request->file('proofs') as $file) {
                $paths[] = $file->store('employment/proofs', 'public');
            }

            $validated['business_proof_documents'] = $paths;
            $validated['payslip_documents'] = null;
        }

        $validated['client_id'] = $client->id;

        $client->employeeInformation()->updateOrCreate(
            ['client_id' => $client->id],
            $validated
        );

        return response()->json([
            'status' => true,
            'message' => 'Employment details saved successfully',
        ]);
    }


}
