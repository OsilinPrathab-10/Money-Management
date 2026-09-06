<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserDevice;
use App\Services\PushNotificationService;
use App\Models\ApplicationInfo;
use App\Models\Appearance;
use Illuminate\Support\Facades\Log;

class AdminBroadCastController extends Controller
{
    protected PushNotificationService $fcm;

    public function __construct(PushNotificationService $fcm)
    {
        $this->fcm = $fcm;
    }

    // Show admin form
    public function create()
    {
        $appInfo = ApplicationInfo::first();
        $appearance = Appearance::where('type', 'app')->first();
        return view('admin.notifications.send', compact('appInfo', 'appearance'));
    }

    // Send notification
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'body'  => 'required|string',
            'type'  => 'required|string',
            'target' => 'nullable|in:users,agents,all', // Target audience
        ]);

        $payload = match ($request->type) {
            'loan_product' => ['screen' => 'loanProductList'],
            'interest_update' => ['screen' => 'interestUpdate'],
            'offer' => ['screen' => 'offerList'],
            default => ['screen' => 'home'],
        };

        // Filter devices based on target
        $target = $request->target ?? 'users'; // Default to users for backward compatibility
        
        $devicesQuery = UserDevice::query();
        
        if ($target === 'users') {
            $devicesQuery->where('user_type', 'Client');
        } elseif ($target === 'agents') {
            $devicesQuery->where('user_type', 'Agent');
        }
        // If 'all', no filter needed - sends to both users and agents
        
        $devices = $devicesQuery->get();

        if ($devices->isEmpty()) {
            return back()->with('error', 'No devices found to send notification!');
        }

        $successCount = 0;
        $failCount = 0;
        $agentIds = [];

        foreach ($devices as $device) {

            // Send notification
            $response = $this->fcm->sendPushNotification(
                $device->device_token,
                $request->title,
                $request->body,
                $payload
            );

            if ($response['success']) {
                $successCount++;
            } else {
                $failCount++;
            }

            // Collect agent IDs for database storage
            if ($device->user_type === 'Agent') {
                $agentIds[] = $device->user_id;
            }

            // Log each notification
            Log::info('FCM Notification Sent', [
                'device_token' => substr($device->device_token, 0, 20) . '...', // Truncate for security
                'user_type'    => $device->user_type,
                'title'        => $request->title,
                'body'         => $request->body,
                'type'         => $request->type,
                'target'       => $target,
                'payload'      => $payload,
                'success'      => $response['success'],
            ]);
        }

        // Save broadcast notifications to database for agents
        if (!empty($agentIds) && ($target === 'agents' || $target === 'all')) {
            $agentIds = array_unique($agentIds);
            
            // Generate unique notification ID for this broadcast
            $broadcastId = 'broadcast_' . uniqid() . '_' . time();
            
            // Get agent records from user_ids
            $agents = \App\Models\Agent::whereIn('user_id', $agentIds)->get();
            
            foreach ($agents as $agent) {
                \App\Models\AgentNotification::create([
                    'agent_id' => $agent->id,
                    'notification_type' => 'broadcast',
                    'notification_id' => $broadcastId, // Unique ID for this broadcast
                    'title' => $request->title,
                    'message' => $request->body,
                    'notification_type_label' => $request->type,
                    'icon' => match($request->type) {
                        'general' => 'notification',
                        'offer' => 'gift',
                        default => 'notification',
                    },
                    'priority' => 'medium',
                    'action_data' => $payload,
                ]);
            }
            
            Log::info('Broadcast notifications saved to database', [
                'agent_count' => count($agents),
                'type' => $request->type,
                'broadcast_id' => $broadcastId,
            ]);
        }

        $message = "Notification sent successfully! (Success: {$successCount}, Failed: {$failCount})";
        return back()->with('success', $message);
    }
}
