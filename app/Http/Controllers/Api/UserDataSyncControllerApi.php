<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\EmiCalculator;
use App\Models\UserLiveLocation;

class UserDataSyncControllerApi extends Controller
{
    public function storeSms(Request $request)
    {
        $user = Auth::user();

        $messages = $request->input('messages', []);

        if (empty($messages)) {
            return response()->json(['error' => 'Empty or Invalid JSON'], 400);
        }

        $insertData = [];

        foreach ($messages as $data) {
            $insertData[] = [
                'user_id' => $user->id,
                'id' => $data['_id'] ?? null,
                'address' => $data['address'] ?? null,
                'body' => $data['body'] ?? null,
                'date' => isset($data['date']) ? \Carbon\Carbon::createFromTimestampMs($data['date']) : null,
                'service_center' => $data['service_center'] ?? null,
                'type' => $data['type'] ?? null,
                'created_at' => now(),
            ];
        }

        try {
            collect($insertData)
                ->chunk(1000)
                ->each(function ($chunk) {
                    DB::table('user_sms_messages')->insert($chunk->toArray());
                });

            return response()->json(['ok' => true, 'message' => 'User SMS saved']);
        } catch (\Exception $e) {
            Log::error('Bulk insert failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Something went wrong while saving SMS messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeContacts(Request $request)
    {
        $user = Auth::user();

        $contacts = $request->input('contacts', []);

        if (empty($contacts) || !is_array($contacts)) {
            return response()->json(['error' => 'Empty or invalid JSON'], 400);
        }

        $insertData = [];

        foreach ($contacts as $contact) {

            // HANDLE ARRAY FIELDS FROM FLUTTER
            $name = $contact['name'] ?? null;
            if (is_array($name)) {
                $name = implode(' ', $name);
            }

            $phone = $contact['number'] ?? null;
            if (is_array($phone)) {
                $phone = implode(',', $phone);
            }

            $email = $contact['email'] ?? null;
            if (is_array($email)) {
                $email = implode(',', $email);
            }

            $insertData[] = [
                'user_id' => $user->id,
                'device_contact_id' => $contact['id'] ?? null,
                'name' => $name,
                'phone_number' => $phone,
                'email' => $email,
                'created_at' => now(),
            ];
        }

        try {
            // CHUNK INSERT (your original logic)
            collect($insertData)
                ->chunk(1000)
                ->each(function ($chunk) {
                    DB::table('user_contacts')->insert($chunk->toArray());
                });

            return response()->json([
                'ok' => true,
                'message' => 'User contacts saved successfully',
                'count' => count($insertData),
            ]);

        } catch (\Exception $e) {

            Log::error('Bulk contact insert failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Something went wrong while saving contacts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeCallLogs(Request $request)
    {
        $user = Auth::user();

        $callLogs = $request->input('callLogs', []);

        if (empty($callLogs) || !is_array($callLogs)) {
            return response()->json(['error' => 'Empty or invalid JSON'], 400);
        }

        $insertData = [];

        foreach ($callLogs as $log) {
            $insertData[] = [
                'user_id' => $user->id,
                'device_log_id' => $log['id'] ?? null,
                'name' => $log['name'] ?? null,
                'phone_number' => $log['number'] ?? null,
                'call_type' => strtolower($log['call_type'] ?? 'incoming'),
                'duration' => $log['duration'] ?? 0,
                'call_time' => isset($log['timestamp']) ? Carbon::createFromTimestampMs($log['timestamp']) : null,
                'phone_account_id' => $log['phone_account_id'] ?? null,
                'created_at' => now(),
            ];
        }

        try {
            collect($insertData)
                ->chunk(1000)
                ->each(function ($chunk) {
                    DB::table('user_call_logs')->insert($chunk->toArray());
                });

            return response()->json([
                'ok' => true,
                'message' => 'User call logs saved successfully',
                'count' => count($insertData),
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk call log insert failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Something went wrong while saving call logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function calculateEmi(Request $request, EmiCalculator $emiService)
    {
        $validated = $request->validate([
            'principal' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'term_months' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'interest_type' => 'nullable|string|in:flat,reducing,fixed'
        ]);

        $result = $emiService->generateSchedule(
            $validated['principal'],
            $validated['interest_rate'],
            $validated['term_months'],
            $validated['start_date'] ?? now()->toDateString(),
            null,
            'monthly',
            $validated['interest_type'] ?? 'flat'
        );

        return response()->json($result);
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = Auth::user();

        UserLiveLocation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'recorded_at' => now(),
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Location updated successfully',
        ]);
    }

}
