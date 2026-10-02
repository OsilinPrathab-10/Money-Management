<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PublicAccountDeletionController extends Controller
{
    public function show(): View
    {
        $supportEmail = function_exists('get_setting')
            ? (get_setting('company_email') ?: 'support@example.com')
            : 'support@example.com';

        return view('public.account-deletion', compact('supportEmail'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:20',
            'reason' => 'nullable|string|max:2000',
        ]);

        $email = strtolower(trim($validated['email']));
        $mobile = AccountDeletionRequest::normalizeMobile($validated['mobile']);

        if (strlen($mobile) !== 10) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid 10-digit mobile number.',
            ], 422);
        }

        $existingPending = AccountDeletionRequest::query()
            ->whereIn('status', ['pending', 'under_review', 'approved'])
            ->where(function ($q) use ($email, $mobile) {
                $q->where('email', $email)->orWhere('mobile', $mobile);
            })
            ->exists();

        if ($existingPending) {
            return response()->json([
                'success' => false,
                'message' => 'A deletion request for this email or mobile number is already being processed.',
            ], 422);
        }

        try {
            $client = AccountDeletionRequest::findMatchingClient($email, $mobile);

            $deletionRequest = AccountDeletionRequest::create([
                'request_number' => AccountDeletionRequest::generateRequestNumber(),
                'full_name' => trim($validated['full_name']),
                'email' => $email,
                'mobile' => $mobile,
                'reason' => $validated['reason'] ?? null,
                'client_id' => $client?->id,
                'status' => 'pending',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your account deletion request has been submitted successfully.',
                'request_number' => $deletionRequest->request_number,
            ]);
        } catch (\Throwable $e) {
            Log::error('Account deletion request failed', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit your request right now. Please try again later.',
            ], 500);
        }
    }

    /**
     * Authenticated mobile app endpoint.
     */
    public function storeForClient(Request $request): JsonResponse
    {
        $user = $request->user();
        $client = $user?->client;

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found for this account.',
            ], 404);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        $email = strtolower(trim((string) ($client->client_email ?: $user->email)));
        $mobile = AccountDeletionRequest::normalizeMobile((string) $client->client_phone);

        if ($email === '' || strlen($mobile) !== 10) {
            return response()->json([
                'success' => false,
                'message' => 'Your profile must have a valid email and mobile number before requesting deletion.',
            ], 422);
        }

        $existingPending = AccountDeletionRequest::query()
            ->whereIn('status', ['pending', 'under_review', 'approved'])
            ->where(function ($q) use ($client, $email, $mobile) {
                $q->where('client_id', $client->id)
                    ->orWhere('email', $email)
                    ->orWhere('mobile', $mobile);
            })
            ->exists();

        if ($existingPending) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active deletion request under review.',
            ], 422);
        }

        try {
            $deletionRequest = AccountDeletionRequest::create([
                'request_number' => AccountDeletionRequest::generateRequestNumber(),
                'full_name' => $client->client_name ?: $user->name,
                'email' => $email,
                'mobile' => $mobile,
                'reason' => $validated['reason'] ?? null,
                'client_id' => $client->id,
                'status' => 'pending',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account deletion request submitted. Our team will review it within 24–48 hours.',
                'request_number' => $deletionRequest->request_number,
            ]);
        } catch (\Throwable $e) {
            Log::error('App account deletion request failed', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit your request. Please try again later.',
            ], 500);
        }
    }
}
