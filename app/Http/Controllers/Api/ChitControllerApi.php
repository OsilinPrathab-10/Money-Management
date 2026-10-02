<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Services\ChitPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChitControllerApi extends Controller
{
    public function __construct(
        protected ChitPayoutService $payoutService
    ) {}

    protected function resolveClient(Request $request): ?Client
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        return Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();
    }

    public function getMyChits(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $enrollments = GroupMember::with(['group.scheme', 'group.branch', 'shares.client', 'payouts'])
            ->involvingClient($client->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (GroupMember $member) use ($client) {
                $ownPct = $member->ownershipPercentageFor($client->id);
                $settleFacing = $member->customerSettlementFacing();

                return [
                    'member_id' => $member->id,
                    'group_id' => $member->group_id,
                    'group_code' => $member->group->group_code ?? '—',
                    'scheme_name' => $member->group->scheme->name ?? '—',
                    'member_number' => $member->member_number,
                    'status' => $member->persistCustomerFacingStatus(),
                    'status_label' => $member->customerFacingStatusLabel(),
                    'status_badge' => $member->customerFacingStatusBadge(),
                    'chit_value' => (float) ($member->group->chit_value ?? 0),
                    'ownership_percentage' => $ownPct,
                    'share_of_chit' => $member->amountForClient((float) ($member->group->chit_value ?? 0), $client->id),
                    'settlement_amount' => $settleFacing['amount'],
                    'settlement_status' => $settleFacing['status'],
                    'settlement_status_label' => $settleFacing['label'],
                    'settlement_status_badge' => $settleFacing['badge'],
                    'is_eligible_for_settlement' => $settleFacing['is_eligible'],
                    'can_reapply' => (bool) $settleFacing['can_reapply'],
                    'last_application_status' => $settleFacing['last_application_status'] ?? null,
                    'chit_need_month' => $member->preferredChitNeedPeriod(),
                    'joined_date' => optional($member->joined_date)->format('Y-m-d'),
                ];
            });

        return response()->json([
            'success' => true,
            'client_id' => $client->id,
            'client_name' => $client->client_name,
            'data' => $enrollments,
        ]);
    }

    public function requestSettlement(Request $request): JsonResponse
    {
        \App\Support\CustomerSettlementLog::write('APPLY RECEIVED (request-settlement)', [
            'path' => $request->path(),
            'login' => Auth::user()?->name,
            'phone' => Auth::user()?->phone,
            'payload' => $request->except(['password', 'token', 'otp', 'authorization']),
        ]);

        $client = $this->resolveClient($request);
        if (!$client && !Auth::user()->hasAnyRole(['Admin', 'Staff', 'admin', 'staff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or client profile not found.',
            ], 403);
        }

        return app(\App\Http\Controllers\Customer\ChitControllerApi::class)->applySettlement($request);
    }
}
