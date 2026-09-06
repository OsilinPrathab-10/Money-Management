<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserDevice;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsController extends Controller
{
    /**
     * Display activity logs page
     */
    public function activityLogs(Request $request)
    {
        $search = trim((string) $request->input('search'));

        // Get latest activity for each client from user_devices
        $activities = UserDevice::with('user')
            ->where('user_type', 'client')
            ->whereNotNull('login_at')
            ->whereHas('user')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('user', function (Builder $clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhereHas('client', function (Builder $clientDetailsQuery) use ($search) {
                                $clientDetailsQuery->where('client_name', 'like', "%{$search}%")
                                    ->orWhere('client_phone', 'like', "%{$search}%");
                            });
                    });
                });
            })
            ->orderBy('login_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.audit-logs.activity-logs', compact('activities', 'search'));
    }

    /**
     * Get location details for a specific activity
     */
    public function getLocationDetails($id)
    {
        try {
            $activity = UserDevice::with(['user.client'])->findOrFail($id);

            $client = $activity->user?->client;
            $clientName = $client?->client_name ?? $activity->user?->name ?? 'Unknown Client';
            $clientPhone = $client?->client_phone ?? $activity->user?->phone ?? 'N/A';

            return response()->json([
                'success' => true,
                'data' => [
                    'client_name' => $clientName,
                    'phone' => $clientPhone,
                    'latitude' => $activity->latitude,
                    'longitude' => $activity->longitude,
                    'ip_address' => $activity->ip_address,
                    'device_name' => $activity->device_name,
                    'device_model' => $activity->device_model,
                    'login_at' => $activity->login_at ? Carbon::parse($activity->login_at)->format('d-m-Y h:i A') : 'N/A',
                    'logout_at' => $activity->logout_at ? Carbon::parse($activity->logout_at)->format('d-m-Y h:i A') : 'Still Active',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View location on separate page with Google Maps
     */
    public function viewLocation($id)
    {
        try {
            $activity = UserDevice::with(['user.client'])->findOrFail($id);

            $client = $activity->user?->client;
            $clientName = $client?->client_name ?? $activity->user?->name ?? 'Unknown Client';
            $clientPhone = $client?->client_phone ?? $activity->user?->phone ?? 'N/A';

            $locationData = [
                'client_name' => $clientName,
                'phone' => $clientPhone,
                'latitude' => $activity->latitude,
                'longitude' => $activity->longitude,
                'ip_address' => $activity->ip_address,
                'device_name' => $activity->device_name,
                'device_model' => $activity->device_model,
                'login_at' => $activity->login_at ? Carbon::parse($activity->login_at)->format('d-m-Y h:i A') : 'N/A',
                'logout_at' => $activity->logout_at ? Carbon::parse($activity->logout_at)->format('d-m-Y h:i A') : 'Still Active',
            ];

            // Get Google Maps API key from env
            $googleMapsApiKey = env('GOOGLE_MAPS_API_KEY', '');

            return view('admin.audit-logs.view-location', compact('locationData', 'googleMapsApiKey'));
        } catch (\Exception $e) {
            return redirect()->route('audit-logs-activity-logs')
                ->with('error', 'Failed to load location: ' . $e->getMessage());
        }
    }

    /**
     * Display login/logout history page
     */
    public function loginLogoutHistory(Request $request)
    {
        // This will be implemented later
        return view('admin.audit-logs.login-logout-history');
    }
}
