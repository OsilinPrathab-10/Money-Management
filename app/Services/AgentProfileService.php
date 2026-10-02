<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AgentProfileService
{
    /**
     * Ensure a user with Agent role has an operational agents row.
     * Restores a soft-deleted profile when present.
     */
    public function ensureAgentProfile(User $user, ?Staff $staff = null): Agent
    {
        $agent = Agent::withTrashed()->where('user_id', $user->id)->first();

        if (!$agent && $user->phone) {
            $agent = Agent::withTrashed()->where('agent_phone', $user->phone)->first();
        }

        $name = $staff->name ?? $user->name;
        $email = $staff->email ?? $user->email;
        $phone = $staff->phone ?? $user->phone;
        $salary = $staff->salary_amount ?? null;
        $salaryDetails = $staff->salary_details ?? null;
        $status = ($staff && $staff->status === 'inactive') ? 'inactive' : 'active';

        if ($agent) {
            if ($agent->trashed()) {
                $agent->restore();
            }

            // Avoid unique phone collision with a different agent
            if ($phone && $agent->agent_phone !== $phone) {
                $phoneTaken = Agent::where('agent_phone', $phone)
                    ->where('id', '!=', $agent->id)
                    ->exists();
                if ($phoneTaken) {
                    throw new InvalidArgumentException('This phone number is already used by another agent.');
                }
            }

            $agent->fill([
                'user_id' => $user->id,
                'agent_name' => $name,
                'agent_email' => $email,
                'agent_phone' => $phone,
                'salary_amount' => $salary ?? $agent->salary_amount,
                'salary_details' => $salaryDetails ?? $agent->salary_details,
                'status' => $status,
            ]);

            if (empty($agent->agent_code)) {
                $agent->agent_code = $this->generateAgentCode();
            }

            $agent->save();

            return $agent->fresh();
        }

        if ($phone) {
            $phoneTaken = Agent::where('agent_phone', $phone)->exists();
            if ($phoneTaken) {
                throw new InvalidArgumentException('This phone number is already used by another agent.');
            }
        }

        return Agent::create([
            'user_id' => $user->id,
            'agent_name' => $name,
            'agent_email' => $email,
            'agent_phone' => $phone,
            'agent_code' => $this->generateAgentCode(),
            'salary_amount' => $salary ?? 0,
            'salary_details' => $salaryDetails,
            'status' => $status,
        ]);
    }

    public function generateAgentCode(): string
    {
        $lastAgent = Agent::withTrashed()->orderBy('id', 'desc')->first();
        $nextNumber = 1;
        if ($lastAgent && $lastAgent->agent_code) {
            $lastNumber = (int) substr($lastAgent->agent_code, 3);
            $nextNumber = $lastNumber + 1;
        }

        return 'ALM' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function assignedClientCount(Agent $agent): int
    {
        return Client::where('assigned_to', $agent->id)->count();
    }

    /**
     * Move all clients and unpaid EMI assignments from one agent to another.
     */
    public function handoverClients(Agent $fromAgent, Agent $toAgent, ?string $remarks = null): int
    {
        if ($fromAgent->id === $toAgent->id) {
            throw new InvalidArgumentException('Handover agent must be different from the current agent.');
        }

        if ($toAgent->status !== 'active') {
            throw new InvalidArgumentException('Handover agent must be active.');
        }

        $remarks = $remarks ?: 'Client handover due to agent role change';
        $count = 0;

        DB::transaction(function () use ($fromAgent, $toAgent, $remarks, &$count) {
            $clients = Client::where('assigned_to', $fromAgent->id)->get();

            foreach ($clients as $client) {
                $client->update(['assigned_to' => $toAgent->id]);
                event(new \App\Events\ClientAssignedToAgentEvent($client->fresh(), $toAgent));

                $activeEmis = Emi::whereHas('loanAccount', function ($q) use ($client) {
                    $q->where('client_id', $client->id);
                })->where('status', '!=', 'paid')->get();

                foreach ($activeEmis as $emi) {
                    EmiAgentAssignment::updateOrCreate(
                        ['emi_id' => $emi->id],
                        [
                            'agent_id' => $toAgent->id,
                            'status' => 'assigned',
                            'assigned_at' => now(),
                            'remarks' => $remarks,
                        ]
                    );
                }

                $count++;
            }

            // Any remaining EMI assignments still pointing at the old agent
            EmiAgentAssignment::where('agent_id', $fromAgent->id)
                ->whereIn('status', ['assigned', 'visited', 'pending'])
                ->update([
                    'agent_id' => $toAgent->id,
                    'assigned_at' => now(),
                    'remarks' => $remarks,
                ]);
        });

        return $count;
    }

    public function deactivateAgentProfile(Agent $agent): void
    {
        $agent->status = 'inactive';
        $agent->save();
    }

    /**
     * Create missing agent profiles for users who already have the Agent role.
     */
    public function backfillMissingProfiles(): int
    {
        $created = 0;
        $staffWithAgentRole = Staff::with('user')
            ->whereHas('user', function ($q) {
                $q->whereHas('roles', function ($rq) {
                    $rq->whereIn('name', ['Agent', 'agent']);
                });
            })
            ->get();

        foreach ($staffWithAgentRole as $staff) {
            if (!$staff->user) {
                continue;
            }
            $exists = Agent::withTrashed()->where('user_id', $staff->user_id)->exists();
            if (!$exists) {
                $this->ensureAgentProfile($staff->user, $staff);
                $created++;
            } else {
                // Restore / reactivate if role is Agent but profile was soft-deleted or inactive from a prior bug
                $agent = Agent::withTrashed()->where('user_id', $staff->user_id)->first();
                if ($agent && $agent->trashed()) {
                    $agent->restore();
                    $agent->status = $staff->status === 'active' ? 'active' : 'inactive';
                    if (empty($agent->agent_code)) {
                        $agent->agent_code = $this->generateAgentCode();
                    }
                    $agent->save();
                    $created++;
                }
            }
        }

        return $created;
    }
}
