<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ApplicationInfo;
use App\Models\Appearance;
use App\Models\Client;
use App\Services\AppNotificationService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminBroadCastController extends Controller
{
    public function __construct(
        protected PushNotificationService $fcm,
        protected AppNotificationService $notifications
    ) {}

    public function create()
    {
        $appInfo = ApplicationInfo::first();
        $appearance = Appearance::where('type', 'app')->first();
        $clients = Client::query()
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'client_phone']);
        $agents = Agent::query()
            ->orderBy('agent_name')
            ->get(['id', 'agent_name', 'agent_phone', 'agent_code']);

        return view('admin.notifications.send', compact('appInfo', 'appearance', 'clients', 'agents'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'required|string',
            'type' => 'required|string',
            'target' => 'required|in:users,agents,all',
            'recipient_scope' => 'required|in:all,particular',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'integer|exists:clients,id',
            'agent_ids' => 'nullable|array',
            'agent_ids.*' => 'integer|exists:agents,id',
        ]);

        $target = $request->input('target');
        $scope = $request->input('recipient_scope');

        if ($scope === 'particular') {
            if ($target === 'users' && empty($request->input('client_ids'))) {
                throw ValidationException::withMessages([
                    'client_ids' => 'Select at least one client.',
                ]);
            }
            if ($target === 'agents' && empty($request->input('agent_ids'))) {
                throw ValidationException::withMessages([
                    'agent_ids' => 'Select at least one agent.',
                ]);
            }
            if ($target === 'all' && empty($request->input('client_ids')) && empty($request->input('agent_ids'))) {
                throw ValidationException::withMessages([
                    'client_ids' => 'Select at least one client or agent.',
                ]);
            }
        }

        $payload = match ($request->type) {
            'loan_product' => ['screen' => 'loanProductList'],
            'interest_update' => ['screen' => 'interestUpdate'],
            'offer' => ['screen' => 'offerList'],
            'disbursement' => ['screen' => 'disbursed'],
            default => ['screen' => 'home'],
        };
        $payload['broadcast'] = '1';
        $payload['notification_type'] = (string) $request->type;

        $clients = collect();
        $agents = collect();

        if (in_array($target, ['users', 'all'], true)) {
            $clients = $scope === 'particular' && $request->filled('client_ids')
                ? Client::whereIn('id', $request->input('client_ids'))->get()
                : ($scope === 'all' ? Client::query()->get() : collect());
        }

        if (in_array($target, ['agents', 'all'], true)) {
            $agents = $scope === 'particular' && $request->filled('agent_ids')
                ? Agent::whereIn('id', $request->input('agent_ids'))->get()
                : ($scope === 'all' ? Agent::query()->get() : collect());
        }

        if ($clients->isEmpty() && $agents->isEmpty()) {
            return back()->withInput()->with('error', 'No clients or agents found for the selected audience.');
        }

        $clientSaved = 0;
        $clientPush = 0;
        $clientNoDevice = [];
        $agentSuccess = 0;
        $agentFail = 0;

        foreach ($clients as $client) {
            $result = $this->notifications->notifyCustomer(
                $client,
                $request->title,
                $request->body,
                $request->type,
                $payload
            );
            if (! empty($result['saved'])) {
                $clientSaved++;
            }
            if (! empty($result['push'])) {
                $clientPush++;
            } else {
                $clientNoDevice[] = $client->client_name ?: ('Client #' . $client->id);
            }
        }

        foreach ($agents as $agent) {
            try {
                $this->notifications->notifyAgent(
                    $agent,
                    $request->title,
                    $request->body,
                    'broadcast',
                    $payload,
                    null,
                    'medium'
                );
                $agentSuccess++;
            } catch (\Throwable $e) {
                $agentFail++;
                Log::error('Broadcast agent notification failed: ' . $e->getMessage(), [
                    'agent_id' => $agent->id,
                ]);
            }
        }

        $audienceBits = [];
        if ($clients->isNotEmpty()) {
            $audienceBits[] = $clients->count() === 1
                ? ($clients->first()->client_name ?: '1 client')
                : $clients->count() . ' clients';
        }
        if ($agents->isNotEmpty()) {
            $audienceBits[] = $agents->count() === 1
                ? ($agents->first()->agent_name ?: '1 agent')
                : $agents->count() . ' agents';
        }
        $audience = $audienceBits !== [] ? implode(' and ', $audienceBits) : 'selected recipients';

        $adminRecord = $this->notifications->notifyAdmin(
            'broadcast',
            $request->title,
            $request->body,
            url('/admin/notifications'),
            null,
            'ri-megaphone-line'
        );

        Log::info('Admin broadcast notification sent', [
            'target' => $target,
            'scope' => $scope,
            'type' => $request->type,
            'clients' => $clients->count(),
            'agents' => $agents->count(),
            'client_saved' => $clientSaved,
            'client_push' => $clientPush,
            'agent_success' => $agentSuccess,
            'agent_fail' => $agentFail,
        ]);

        $parts = [];
        if ($clients->isNotEmpty()) {
            $parts[] = "Sent to {$audience}. Clients: {$clientSaved} saved in the app" . ($clientPush ? ", {$clientPush} push delivered" : '');
            if ($clientNoDevice !== []) {
                $names = implode(', ', array_slice($clientNoDevice, 0, 5));
                $extra = count($clientNoDevice) > 5 ? ' and others' : '';
                $parts[] = "Phone push was not delivered for {$names}{$extra} (no FCM token on the device yet)";
            }
        }
        if ($agents->isNotEmpty() && $clients->isEmpty()) {
            $parts[] = "Agents: {$agentSuccess} sent" . ($agentFail ? ", {$agentFail} failed" : '');
        } elseif ($agents->isNotEmpty()) {
            $parts[] = "Agents: {$agentSuccess} sent" . ($agentFail ? ", {$agentFail} failed" : '');
        }

        $flash = $clientSaved > 0 || $agentSuccess > 0 ? 'success' : 'error';

        $popup = null;
        if ($adminRecord) {
            $popup = [
                'id' => $adminRecord->id,
                'type' => $adminRecord->type,
                'title' => $adminRecord->title,
                'message' => $adminRecord->message,
                'link' => $adminRecord->link,
                'icon' => $adminRecord->icon_class,
                'badge_color' => $adminRecord->badge_color,
                'is_read' => false,
                'created_at' => 'just now',
                'created_at_formatted' => now()->format('d-m-Y h:i A'),
            ];
        }

        return back()
            ->with($flash, implode('. ', $parts) . '.')
            ->with('notification_popup', $popup);
    }
}
