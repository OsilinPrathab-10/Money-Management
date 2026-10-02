<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ChitCollection;
use App\Models\ChitFamily;
use App\Models\ChitFamilyMember;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\InstallmentSharePayment;
use App\Models\Payout;
use App\Services\ChitPayoutService;
use App\Support\CollectedDocuments;
use App\Support\BulkPaymentGroup;
use App\Support\CustomerSettlementLog;
use App\Support\HashId;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChitControllerApi extends Controller
{
    public function __construct(
        protected CatalogControllerApi $catalog,
        protected ChitPayoutService $payoutService
    ) {}

    protected function authenticatedClient(): Client
    {
        $user = Auth::user();
        if (! $user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401));
        }

        $client = $user->client
            ?? Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();

        if (! $client) {
            abort(response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404));
        }

        return $client;
    }

    protected function assertKycVerified(Client $client): void
    {
        if (optional($client->kycDetail)->status !== 'verified') {
            abort(response()->json([
                'success' => false,
                'message' => 'KYC must be verified before applying.',
            ], 422));
        }
    }

    public function chitDropdowns(): JsonResponse
    {
        $schemes = ChitScheme::where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (ChitScheme $s) => $this->catalog->formatChitScheme($s));

        $availableGroups = $this->catalog->availableGroups();

        return response()->json([
            'success' => true,
            'message' => 'Chit application dropdowns fetched successfully',
            'data' => [
                'schemes' => $schemes,
                'groups' => $availableGroups,
                'frequencies' => [
                    ['value' => 'monthly', 'label' => 'Monthly'],
                    ['value' => 'weekly', 'label' => 'Weekly'],
                    ['value' => 'daily', 'label' => 'Daily'],
                ],
            ],
        ]);
    }

    public function applyChit(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        $this->assertKycVerified($client);

        $validated = $request->validate([
            'group_id' => 'required|exists:chit_groups,id',
            'chit_need_month' => 'nullable|integer|min:1',
            'collection_frequency' => 'nullable|in:daily,weekly,monthly',
            'remarks' => 'nullable|string|max:500',
        ]);

        $group = ChitGroup::findOrFail($validated['group_id']);

        $needPeriod = isset($validated['chit_need_month']) ? (int) $validated['chit_need_month'] : null;
        if ($needPeriod && $needPeriod > (int) $group->total_months) {
            return response()->json([
                'success' => false,
                'message' => 'Selected chit need month is not valid for this group.',
            ], 422);
        }

        if (! in_array($group->status, ['forming', 'active'])) {
            return response()->json([
                'success' => false,
                'message' => 'Selected group is not open for enrollment.',
            ], 422);
        }

        $existing = GroupMember::where('group_id', $group->id)
            ->where('client_id', $client->id)
            ->whereIn('status', ['applied', 'approved', 'active'])
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You are already enrolled or applied for this chit group.',
            ], 422);
        }

        $occupiedSlots = GroupMember::where('group_id', $group->id)
            ->whereIn('status', ['approved', 'active'])
            ->count();

        if ($occupiedSlots >= (int) $group->total_members) {
            return response()->json([
                'success' => false,
                'message' => 'This chit group is full.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $nextMemberNumber = $group->getNextAvailableMemberNumber();

            $needFields = GroupMember::resolveChitNeedFromPeriod($needPeriod, $group);

            $member = GroupMember::create([
                'group_id' => $group->id,
                'client_id' => $client->id,
                'member_number' => $nextMemberNumber,
                'collection_frequency' => $validated['collection_frequency'] ?? 'monthly',
                'chit_need_month' => $needFields['chit_need_month'],
                'chit_need_date' => $needFields['chit_need_date'],
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'applied',
                'joined_date' => now(),
            ]);

            // Auto-generate installments for newly applied chit so schedule is available immediately
            $startDate = $group->start_date
                ? Carbon::parse($group->start_date)
                : now();
            $group->generateInstallmentsForMember($member, $startDate);

            DB::commit();

            event(new \App\Events\NewChitApplicationEvent($member->fresh(['client', 'group.scheme']), 'customer'));

            return response()->json([
                'success' => true,
                'message' => 'Chit application submitted successfully. Pending approval.',
                'data' => $this->formatChitApplication($member->fresh(['group.scheme', 'installments.collections']), true, $client),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Customer chit application failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Chit application failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function chitApplications(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();

        if ($id) {
            $member = GroupMember::with([
                'group.scheme',
                'client',
                'installments.collections',
                'payouts',
                'referrerAgent',
            ])->involvingClient($client->id)->findOrFail($id);

            $this->ensureInstallmentsExist($member);

            return response()->json([
                'success' => true,
                'message' => 'Chit application fetched successfully',
                'data' => $this->formatChitApplication($member, true, $client),
            ]);
        }

        $query = GroupMember::with(['group.scheme', 'installments.collections'])
            ->involvingClient($client->id);

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->whereCustomerFacingStatus($status);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%")
                  ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                  ->orWhereHas('group.scheme', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $items->getCollection()->transform(function (GroupMember $m) use ($client) {
            $this->ensureInstallmentsExist($m);

            return $this->formatChitApplication($m, false, $client);
        });

        return response()->json([
            'success' => true,
            'message' => 'Chit applications fetched successfully',
            'data' => $items,
        ]);
    }

    public function applicationsWithAccounts(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();

        $query = GroupMember::with([
            'group.scheme',
            'group.auctions.winner.client',
            'client',
            'installments.collections',
            'payouts',
            'referrerAgent',
        ])->involvingClient($client->id);

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->whereCustomerFacingStatus($status);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%")
                  ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                  ->orWhereHas('group.scheme', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));

        $items->getCollection()->transform(function (GroupMember $m) use ($client) {
            $this->ensureInstallmentsExist($m);
            $facing = $m->persistCustomerFacingStatus();

            return [
                'application' => $this->formatChitApplication($m, true, $client),
                'account_details' => in_array($facing, ['active', 'approved', 'completed', 'closed', 'defaulted'], true)
                    ? $this->formatChitAccountAdminView($m, $client)
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Chit applications with accounts fetched successfully',
            'data' => $items,
        ]);
    }

    public function chitAccounts(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();

        if ($id) {
            $member = GroupMember::with([
                'group.scheme',
                'group.auctions.winner.client',
                'client',
                'installments.collections',
                'payouts',
                'referrerAgent',
            ])->involvingClient($client->id)->findOrFail($id);

            $this->ensureInstallmentsExist($member);

            return response()->json([
                'success' => true,
                'message' => 'Chit account details fetched successfully',
                'data' => $this->formatChitAccountAdminView($member, $client),
            ]);
        }

        $query = GroupMember::with(['group.scheme', 'installments.collections'])
            ->involvingClient($client->id);

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->whereCustomerFacingStatus($status);
        } else {
            $query->where(function ($q) {
                $q->whereIn('status', ['active', 'approved', 'completed', 'closed', 'defaulted'])
                    ->orWhereHas('group', fn ($g) => $g->whereIn('status', ['completed', 'closed', 'terminated']));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('member_number', 'like', "%{$search}%")
                  ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                  ->orWhereHas('group.scheme', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));

        $totalChitValue = 0.0;
        $totalPaidAmount = 0.0;
        $totalDueAmount = 0.0;
        $totalDividend = 0.0;

        $formattedItems = $items->getCollection()->map(function (GroupMember $m) use ($client, &$totalChitValue, &$totalPaidAmount, &$totalDueAmount, &$totalDividend) {
            $this->ensureInstallmentsExist($m);
            $group = $m->group;
            $installments = $m->installments ?? collect();

            $ownershipPct = (float) $m->ownershipPercentageFor($client->id);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($m->effective_share_percentage ?? $m->share_percentage ?? 100);
            }

            $fullChitValue = (float) ($group->chit_value ?? 0);
            $chitValueShare = $m->is_shared
                ? (float) $m->amountForClient($fullChitValue, $client->id)
                : round($fullChitValue * ($ownershipPct / 100.0), 2);

            $paid = (float) $installments->sum(fn ($inst) => $inst->clientPaidShare($client->id));
            $due = (float) $installments->whereNotIn('status', ['paid', 'waived'])->sum(fn ($inst) => $inst->clientBalanceShare($client->id));
            $dividend = (float) $installments->sum(fn ($inst) => $m->amountForClient((float) $inst->dividend_amount, $client->id));

            $totalChitValue += $chitValueShare;
            $totalPaidAmount += $paid;
            $totalDueAmount += $due;
            $totalDividend += $dividend;

            return $this->formatChitApplication($m, false, $client);
        });

        return response()->json([
            'success' => true,
            'message' => 'Chit accounts fetched successfully',
            'summary' => [
                'total_accounts_count' => $items->total(),
                'total_chit_value' => $totalChitValue,
                'total_paid_amount' => $totalPaidAmount,
                'total_due_amount' => $totalDueAmount,
                'total_dividend_earned' => $totalDividend,
            ],
            'data' => $formattedItems,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * GET /api/customer/chits/payment-history
     * Optional: member_id, group_id, installment_id, account id in path.
     */
    public function paymentHistory(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $clientId = (int) $client->id;

        $memberId = $request->input('member_id')
            ?? $request->input('chit_account_id')
            ?? $request->input('account_id')
            ?? $id;

        $memberIds = GroupMember::involvingClient($clientId)->pluck('id');

        if ($memberId) {
            $member = GroupMember::involvingClient($clientId)->find($memberId);
            if (! $member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chit account not found.',
                ], 404);
            }
            $memberIds = collect([(int) $member->id]);
        }

        $collections = collect();
        if (Schema::hasTable('chit_collections') && $memberIds->isNotEmpty()) {
            $collections = ChitCollection::with(['installment', 'group', 'member', 'agent'])
                ->where(function ($q) use ($clientId, $memberIds) {
                    $q->where('client_id', $clientId)
                        ->orWhereIn('member_id', $memberIds);
                })
                ->whereIn('member_id', $memberIds)
                ->whereIn('status', ['in_progress', 'verified', 'completed', 'paid'])
                ->when($request->filled('group_id'), fn ($q) => $q->where('group_id', $request->input('group_id')))
                ->when($request->filled('installment_id'), fn ($q) => $q->where('installment_id', $request->input('installment_id')))
                ->orderByDesc('collected_at')
                ->orderByDesc('id')
                ->get();
        }

        $history = BulkPaymentGroup::groupChitCollections($collections)->map(function (array $group) {
            $col = $group['lead'];
            $items = $group['items'];
            $isBulk = $group['is_bulk'];
            $status = $col->status === 'paid' ? 'verified' : $col->status;
            $paidAt = $col->collected_at ?: $col->created_at;
            $amount = round((float) $items->sum('amount'), 2);
            $months = $items
                ->map(fn (ChitCollection $row) => $row->installment?->month_number)
                ->filter()
                ->unique()
                ->sort()
                ->values();

            $colId = $col->id;
            $instId = $col->installment_id;
            $encodedId = \App\Support\HashId::encode($instId ?: $colId);
            $receiptNum = $col->receipt_no
                ?: ($col->payment_reference
                ?: ('RCP-C-' . str_pad((string) $colId, 6, '0', STR_PAD_LEFT)));
            $receiptUrl = url('/payment-receipts/chit/' . $encodedId);

            return [
                'id' => $col->id,
                'source' => 'collection',
                'installment_id' => $col->installment_id,
                'member_id' => $col->member_id,
                'group_id' => $col->group_id,
                'group_code' => $col->group?->group_code,
                'month_number' => (int) ($col->installment?->month_number ?? 0),
                'amount' => $amount,
                'amount_formatted' => '₹' . number_format($amount, 2),
                'status' => $isBulk ? BulkPaymentGroup::combinedStatus($items) : $status,
                'status_label' => match ($status) {
                    'verified', 'completed' => $isBulk ? 'Bulk Paid' : 'Paid',
                    'in_progress' => 'Pending verification',
                    default => ucfirst(str_replace('_', ' ', (string) $status)),
                },
                'status_badge' => match ($status) {
                    'verified', 'completed' => 'success',
                    'in_progress' => 'warning',
                    default => 'info',
                },
                'is_paid' => in_array($status, ['verified', 'completed'], true),
                'is_bulk' => $isBulk,
                'installment_split' => $isBulk && $months->isNotEmpty()
                    ? ('Inst #' . $months->implode(', #'))
                    : null,
                'payment_method' => $col->payment_method,
                'payment_mode' => $this->formatChitPaymentMode($col->payment_method),
                'payment_type' => $isBulk ? BulkPaymentGroup::combinedPaymentType($items) : $col->payment_type,
                'payment_reference' => $col->payment_reference,
                'receipt_number' => $receiptNum,
                'receipt_url' => $receiptUrl,
                'receipt_view_url' => $receiptUrl,
                'receipt_download_url' => $receiptUrl,
                'receipt_print_url' => url('/payment-receipts/chit/' . $encodedId . '/print'),
                'paid_date' => optional($paidAt)?->format('Y-m-d'),
                'collected_at' => optional($paidAt)?->format('Y-m-d H:i:s'),
                'collected_at_formatted' => optional($paidAt)?->format('d-m-Y h:i A'),
                'remarks' => $col->remarks,
            ];
        });

        $collectionInstallmentIds = $collections->pluck('installment_id')->filter()->unique();

        $sharePayments = collect();
        if (Schema::hasTable('installment_share_payments') && $memberIds->isNotEmpty()) {
            $sharePayments = InstallmentSharePayment::with(['installment.group', 'member'])
                ->where('client_id', $clientId)
                ->whereIn('group_member_id', $memberIds)
                ->when($request->filled('group_id'), function ($q) use ($request) {
                    $q->whereHas('installment', fn ($iq) => $iq->where('group_id', $request->input('group_id')));
                })
                ->when($request->filled('installment_id'), fn ($q) => $q->where('installment_id', $request->input('installment_id')))
                ->when($collectionInstallmentIds->isNotEmpty(), fn ($q) => $q->whereNotIn('installment_id', $collectionInstallmentIds))
                ->orderByDesc('paid_date')
                ->orderByDesc('id')
                ->get();
        }

        $shareHistory = collect();
        $usedShareIds = [];
        foreach ($sharePayments as $pay) {
            if (isset($usedShareIds[$pay->id])) {
                continue;
            }

            $explicit = BulkPaymentGroup::extractExplicitKey($pay->remarks, $pay->reference_no);
            $isBulk = BulkPaymentGroup::isBulkPayload($pay->remarks, $pay->reference_no);
            $scopeKey = ($explicit ?: trim((string) ($pay->reference_no ?? ''))) . ':m' . $pay->group_member_id;
            $siblings = collect([$pay]);

            if ($isBulk && $scopeKey !== ':m' . $pay->group_member_id) {
                $siblings = $sharePayments->filter(function (InstallmentSharePayment $other) use ($scopeKey) {
                    $otherKey = (BulkPaymentGroup::extractExplicitKey($other->remarks, $other->reference_no)
                        ?: trim((string) ($other->reference_no ?? ''))) . ':m' . $other->group_member_id;

                    return $otherKey === $scopeKey && BulkPaymentGroup::isBulkPayload($other->remarks, $other->reference_no);
                })->values();
            }

            foreach ($siblings as $sibling) {
                $usedShareIds[$sibling->id] = true;
            }

            $isGrouped = $siblings->count() > 1;
            $lead = $siblings->first();
            $paidAt = $lead->paid_date ?: $lead->created_at;
            $amount = round((float) $siblings->sum('amount'), 2);
            $months = $siblings
                ->map(fn (InstallmentSharePayment $row) => $row->installment?->month_number)
                ->filter()
                ->unique()
                ->sort()
                ->values();

            $leadId = $lead->id;
            $instId = $lead->installment_id;
            $encodedId = \App\Support\HashId::encode($instId ?: $leadId);
            $receiptNum = $lead->reference_no
                ?: ('RCP-S-' . str_pad((string) $leadId, 6, '0', STR_PAD_LEFT));
            $receiptUrl = url('/payment-receipts/chit/' . $encodedId);

            $shareHistory->push([
                'id' => $lead->id,
                'source' => 'share_payment',
                'installment_id' => $lead->installment_id,
                'member_id' => $lead->group_member_id,
                'group_id' => $lead->installment?->group_id,
                'group_code' => $lead->installment?->group?->group_code,
                'month_number' => (int) ($lead->installment?->month_number ?? 0),
                'amount' => $amount,
                'amount_formatted' => '₹' . number_format($amount, 2),
                'status' => 'verified',
                'status_label' => $isGrouped ? 'Bulk Paid' : 'Paid',
                'status_badge' => 'success',
                'is_paid' => true,
                'is_bulk' => $isGrouped,
                'installment_split' => $isGrouped && $months->isNotEmpty()
                    ? ('Inst #' . $months->implode(', #'))
                    : null,
                'payment_method' => $lead->payment_mode,
                'payment_mode' => $this->formatChitPaymentMode($lead->payment_mode),
                'payment_type' => 'full',
                'payment_reference' => $lead->reference_no,
                'receipt_number' => $receiptNum,
                'receipt_url' => $receiptUrl,
                'receipt_view_url' => $receiptUrl,
                'receipt_download_url' => $receiptUrl,
                'receipt_print_url' => url('/payment-receipts/chit/' . $encodedId . '/print'),
                'paid_date' => optional($paidAt)?->format('Y-m-d'),
                'collected_at' => optional($paidAt)?->format('Y-m-d H:i:s'),
                'collected_at_formatted' => optional($paidAt)?->format('d-m-Y h:i A'),
                'remarks' => $lead->remarks,
            ]);
        }

        $shareHistory = $shareHistory->values();

        $coveredInstallmentIds = $collectionInstallmentIds
            ->merge($sharePayments->pluck('installment_id'))
            ->filter()
            ->unique();

        $legacyInstallments = collect();
        if ($memberIds->isNotEmpty()) {
            $legacyInstallments = Installment::withTrashed()
                ->with(['group', 'member'])
                ->whereIn('member_id', $memberIds)
                ->where('paid_amount', '>', 0)
                ->when($coveredInstallmentIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $coveredInstallmentIds))
                ->when($request->filled('group_id'), fn ($q) => $q->where('group_id', $request->input('group_id')))
                ->when($request->filled('installment_id'), fn ($q) => $q->where('id', $request->input('installment_id')))
                ->orderByDesc('paid_date')
                ->get();
        }

        $legacyHistory = $legacyInstallments->map(function (Installment $inst) use ($clientId) {
            $paidAmt = (float) $inst->clientPaidShare($clientId);
            if ($paidAmt <= 0) {
                $paidAmt = (float) $inst->paid_amount;
            }
            $paidAt = $inst->paid_date ?: $inst->updated_at;

            $instId = $inst->id;
            $encodedId = \App\Support\HashId::encode($instId);
            $receiptNum = $inst->reference_no
                ?: ('RCP-' . str_pad((string) $instId, 6, '0', STR_PAD_LEFT));
            $receiptUrl = url('/payment-receipts/chit/' . $encodedId);

            return [
                'id' => $inst->id,
                'source' => 'installment',
                'installment_id' => $inst->id,
                'member_id' => $inst->member_id,
                'group_id' => $inst->group_id,
                'group_code' => $inst->group?->group_code,
                'month_number' => (int) $inst->month_number,
                'amount' => $paidAmt,
                'amount_formatted' => '₹' . number_format($paidAmt, 2),
                'status' => $inst->status === 'paid' ? 'verified' : $inst->status,
                'status_label' => $inst->status === 'paid' ? 'Paid' : ucfirst((string) $inst->status),
                'status_badge' => $inst->status === 'paid' ? 'success' : 'warning',
                'is_paid' => $inst->status === 'paid',
                'payment_method' => $inst->payment_mode,
                'payment_mode' => $this->formatChitPaymentMode($inst->payment_mode),
                'payment_type' => $inst->status === 'partial' ? 'partial' : 'full',
                'payment_reference' => $inst->reference_no,
                'receipt_number' => $receiptNum,
                'receipt_url' => $receiptUrl,
                'receipt_view_url' => $receiptUrl,
                'receipt_download_url' => $receiptUrl,
                'receipt_print_url' => url('/payment-receipts/chit/' . $encodedId . '/print'),
                'paid_date' => optional($paidAt)?->format('Y-m-d'),
                'collected_at' => optional($paidAt)?->format('Y-m-d H:i:s'),
                'collected_at_formatted' => optional($paidAt)?->format('d-m-Y h:i A'),
                'remarks' => $inst->remarks,
            ];
        })->filter(fn ($row) => (float) $row['amount'] > 0)->values();

        $data = $history
            ->concat($shareHistory)
            ->concat($legacyHistory)
            ->sortByDesc(fn ($row) => $row['collected_at'] ?? '')
            ->values();

        $totalPaid = (float) $data
            ->filter(fn ($row) => in_array($row['status'], ['verified', 'completed', 'paid', 'partial'], true))
            ->sum('amount');

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Chit payment history fetched successfully',
            'summary' => [
                'total_count' => $data->count(),
                'total_paid' => round($totalPaid, 2),
                'total_paid_formatted' => '₹' . number_format($totalPaid, 2),
            ],
            'count' => $data->count(),
            'data' => $data,
        ]);
    }

    protected function formatChitPaymentMode(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'in_hand', 'cash' => 'Cash',
            'direct' => 'Online Payment',
            'payment_link' => 'Payment Link',
            'upi' => 'UPI',
            'bank_transfer', 'neft', 'imps', 'rtgs' => 'Bank Transfer',
            '', '—' => '—',
            default => $method ? ucfirst(str_replace('_', ' ', $method)) : '—',
        };
    }

    protected function getEligibleChitsForClient(\App\Models\Client $client): \Illuminate\Support\Collection
    {
        $clientId = (int) $client->id;
        $enrolledMembers = GroupMember::with(['group.scheme', 'group.payouts', 'installments', 'shares'])
            ->involvingClient($clientId)
            ->whereIn('status', ['active', 'approved', 'transferred', 'withdrawn', 'cancelled'])
            ->get();

        $fmtCurrency = function ($amount) {
            return '₹' . preg_replace("/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i", "$1,", (string) round((float)$amount));
        };

        return $enrolledMembers->map(function (GroupMember $member) use ($clientId, $fmtCurrency) {
            $group = $member->group;
            if (! $group) {
                return null;
            }

            $scheme = $group->scheme;
            $isEligible = $this->payoutService->isMemberEligible($group, $member);
            $settleInfo = $member->settlement_status_info;
            $totalMonths = max(1, (int) $group->total_months);
            $installments = $member->installments ?? collect();
            $paidCount = $installments->where('status', 'paid')->count();
            $totalPaid = (float) $installments->sum(fn ($inst) => $inst->clientPaidShare($clientId));

            $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
            }

            $fullChitValue = (float) ($group->chit_value ?? 0);
            $chitValueShare = $member->is_shared
                ? (float) $member->amountForClient($fullChitValue, $clientId)
                : round($fullChitValue * ($ownershipPct / 100.0), 2);

            $installmentAmountShare = (float) $member->apiInstallmentAmountForClient($clientId, $group);

            $memberHasActivePayout = $group->payouts->contains(fn ($p) =>
                (int) $p->winner_member_id === $member->id && in_array($p->status, ['pending', 'processing', 'paid'])
            );
            $latestMemberPayout = $group->payouts
                ->where('winner_member_id', $member->id)
                ->sortByDesc('id')
                ->first();
            $latestWasRejected = $latestMemberPayout
                && in_array($latestMemberPayout->status, ['cancelled', 'failed'], true)
                && ! $memberHasActivePayout;

            $preferredNeedPeriod = $member->preferredChitNeedPeriod();
            $lockToNeedMonth = $preferredNeedPeriod && ! $latestWasRejected;

            $availableMonths = [];
            for ($m = 1; $m <= $totalMonths; $m++) {
                $isForemanMonth = $group->isForemanCommissionMonth($m);
                $existingMemberPayout = $group->payouts
                    ->where('winner_member_id', $member->id)
                    ->where('month_number', $m)
                    ->filter(fn ($p) => in_array($p->status, ['pending', 'processing', 'paid'], true))
                    ->sortByDesc('id')
                    ->first();
                $isSettled = $existingMemberPayout && $existingMemberPayout->status === 'paid';
                $isApplied = $existingMemberPayout && in_array($existingMemberPayout->status, ['pending', 'processing']);

                $monthAmount = 0.0;
                $isSelectable = false;
                $unselectableReason = null;

                if (! $isForemanMonth) {
                    try {
                        $calc = $this->payoutService->calculateAmounts($group, null, $m, $member);
                        $fullPayoutAmt = (float) $calc['payout_amount'];
                        $monthAmount = $member->is_shared
                            ? (float) $member->amountForClient($fullPayoutAmt, $clientId)
                            : round($fullPayoutAmt * ($ownershipPct / 100.0), 2);
                        
                        if ($isSettled) {
                            $unselectableReason = 'Already settled';
                        } elseif ($isApplied) {
                            $unselectableReason = 'Application pending';
                        } elseif ($memberHasActivePayout) {
                            $unselectableReason = 'You have another active settlement';
                        } elseif (!$isEligible) {
                            $unselectableReason = 'Not eligible';
                        } else {
                            // Check if someone else took the original payout for this month
                            $originalPayoutForMonth = $group->payouts->first(fn ($p) => 
                                (int) $p->month_number === $m 
                                && $p->payout_kind === \App\Models\Payout::KIND_ORIGINAL 
                                && in_array($p->status, ['pending', 'processing', 'paid'])
                            );

                            if ($originalPayoutForMonth) {
                                if ($group->allowsAdvancePayouts() && $originalPayoutForMonth->status === 'paid') {
                                    $isSelectable = true;
                                } else {
                                    $isSelectable = false;
                                    $unselectableReason = 'Month already taken';
                                }
                            } else {
                                $isSelectable = true;
                            }
                        }
                    } catch (Throwable) {
                        $monthAmount = $installmentAmountShare;
                    }
                }

                if ($isSelectable && $lockToNeedMonth && (int) $m !== (int) $preferredNeedPeriod) {
                    $originalPaid = $group->payouts->first(fn ($p) =>
                        (int) $p->month_number === $m
                        && $p->payout_kind === \App\Models\Payout::KIND_ORIGINAL
                        && $p->status === 'paid'
                    );
                    if (! ($group->allowsAdvancePayouts() && $originalPaid)) {
                        $isSelectable = false;
                        $unselectableReason = 'Locked to applied chit need month';
                    }
                }

                $availableMonths[] = [
                    'month_number' => $m,
                    'month_label' => 'Month ' . $m . ' — ' . $group->periodCalendarLabel($m),
                    'settlement_amount' => $monthAmount,
                    'settlement_amount_formatted' => $fmtCurrency($monthAmount),
                    'is_foreman_month' => $isForemanMonth,
                    'is_preferred_need_month' => (bool) ($preferredNeedPeriod && (int) $m === (int) $preferredNeedPeriod),
                    'is_settled' => $isSettled,
                    'is_applied' => $isApplied,
                    'is_selectable' => $isSelectable,
                    'unselectable_reason' => $unselectableReason,
                ];
            }

            $estimatedSettlementShare = $member->is_shared
                ? (float) $member->amountForClient((float) $settleInfo['amount'], $clientId)
                : round((float) $settleInfo['amount'] * ($ownershipPct / 100.0), 2);
            $settleFacing = $member->customerSettlementFacing();
            $displayNeedMonth = $member->preferredChitNeedPeriod();

            return [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
                'group_id' => $group->id,
                'group_code' => $group->group_code,
                'scheme_name' => $scheme?->name,
                'foreman_commission_month' => $group->foremanCommissionMonth(),
                'client_wise_foreman_commission' => $group->usesClientWiseForemanCommission() ? $group->scheme->clientWiseForemanAmount() : null,
                'client_wise_foreman_collection_month' => $group->usesClientWiseForemanCommission() ? $group->scheme->clientWiseForemanCollectionMonth() : null,
                'status' => $member->persistCustomerFacingStatus(),
                'status_label' => $member->customerFacingStatusLabel(),
                'status_badge' => $member->customerFacingStatusBadge(),
                'chit_value' => $chitValueShare,
                'chit_value_formatted' => $fmtCurrency($chitValueShare),
                'installment_amount' => $installmentAmountShare,
                'installment_amount_formatted' => $fmtCurrency($installmentAmountShare),
                'is_shared' => (bool) $member->is_shared,
                'ownership_percentage' => $ownershipPct,
                'share_percentage' => $ownershipPct,
                'share_percentage_formatted' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'share_label' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'is_eligible' => $isEligible || ! empty($settleFacing['can_reapply']),
                'settlement_status' => $settleFacing['status'],
                'settlement_status_label' => $settleFacing['label'],
                'settlement_status_badge' => $settleFacing['badge'],
                'can_reapply' => (bool) $settleFacing['can_reapply'],
                'last_application_status' => $settleFacing['last_application_status'] ?? null,
                'current_month' => (int) ($group->current_month ?? 1),
                'total_months' => $totalMonths,
                'duration_label' => 'Month ' . max(1, (int) $group->current_month) . ' / ' . $totalMonths,
                'paid_installments_count' => $paidCount,
                'total_paid_amount' => $totalPaid,
                'total_paid_formatted' => $fmtCurrency($totalPaid),
                'start_date_formatted' => $group->start_date ? \Carbon\Carbon::parse($group->start_date)->format('d-m-Y') : '—',
                'end_date_formatted' => $group->end_date ? \Carbon\Carbon::parse($group->end_date)->format('d-m-Y') : '—',
                'collection_frequency_label' => ucfirst($group->installment_frequency ?? 'Monthly'),
                'next_settlement_month' => $group->getNextSettlementMonth(),
                'chit_need_month' => $displayNeedMonth,
                'chit_need_month_label' => $member->chit_need_month_label,
                'preferred_chit_need_period' => $displayNeedMonth,
                'estimated_settlement_amount' => $estimatedSettlementShare,
                'estimated_settlement_formatted' => $fmtCurrency($estimatedSettlementShare),
                'available_months' => $availableMonths,
            ];
        })->filter()->values();
    }

    public function settlementMetadata(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        $kyc = $client->kycDetail;

        $eligibleChits = $this->getEligibleChitsForClient($client);

        return response()->json([
            'success' => true,
            'message' => 'Settlement metadata and eligible chits fetched successfully',
            'data' => [
                'eligible_chits' => $eligibleChits,
                'payout_kinds' => [
                    ['value' => 'original', 'label' => 'Original Settlement'],
                    ['value' => 'advance', 'label' => 'Advance Settlement'],
                ],
                'kyc_bank_details' => [
                    'bank_name' => $kyc?->bank_name,
                    'account_number' => $kyc?->account_number,
                    'ifsc_code' => $kyc?->ifsc_code,
                    'account_holder_name' => $kyc?->account_holder_name,
                    'branch_name' => $kyc?->branch_name,
                ],
            ],
        ]);
    }

    public function settlementPreview(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        $kyc = $client->kycDetail;

        $groupId = $request->input('group_id')
            ?? $request->input('chit_id')
            ?? $request->input('chit_group_id');

        $memberId = $request->input('member_id')
            ?? $request->input('group_member_id')
            ?? $request->input('ticket_id');

        if (! $groupId && $memberId) {
            $memberRecord = GroupMember::find($memberId);
            if ($memberRecord) {
                $groupId = $memberRecord->group_id;
            }
        }

        if ($groupId && ! $memberId) {
            $memberRecord = GroupMember::where('group_id', $groupId)
                ->involvingClient($client->id)
                ->first();
            if ($memberRecord) {
                $memberId = $memberRecord->id;
            }
        }

        if (! $groupId || ! $memberId) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a valid chit group and member membership.',
            ], 422);
        }

        $group = ChitGroup::with('scheme')->find($groupId);
        if (! $group) {
            return response()->json([
                'success' => false,
                'message' => 'Chit group not found.',
            ], 404);
        }

        $member = GroupMember::where('group_id', $group->id)->find($memberId);
        if (! $member || ! $member->involvesClient($client->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this chit group.',
            ], 403);
        }

        $monthInput = $request->input('month_number')
            ?? $request->input('month')
            ?? $request->input('payout_month');

        $monthNumber = ! empty($monthInput)
            ? (int) $monthInput
            : $group->getNextSettlementMonth();

        $payoutKind = $request->input('payout_kind') ?? Payout::KIND_ORIGINAL;
        if (! in_array($payoutKind, [Payout::KIND_ORIGINAL, Payout::KIND_ADVANCE], true)) {
            $payoutKind = Payout::KIND_ORIGINAL;
        }

        try {
            $amounts = $this->payoutService->calculateAmounts($group, null, $monthNumber, $member);
            $isEligible = $this->payoutService->isMemberEligible($group, $member);
            $clientId = (int) $client->id;

            $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
            }

            $fullChitValue = (float) ($group->chit_value ?? 0);
            $chitValueShare = $member->is_shared
                ? (float) $member->amountForClient($fullChitValue, $clientId)
                : round($fullChitValue * ($ownershipPct / 100.0), 2);

            $fullCommission = (float) $amounts['commission'];
            $commissionShare = $member->is_shared
                ? (float) $member->amountForClient($fullCommission, $clientId)
                : round($fullCommission * ($ownershipPct / 100.0), 2);

            $fullSettlement = (float) $amounts['payout_amount'];
            $settlementShare = $member->is_shared
                ? (float) $member->amountForClient($fullSettlement, $clientId)
                : round($fullSettlement * ($ownershipPct / 100.0), 2);

            return response()->json([
                'success' => true,
                'message' => 'Settlement preview calculated successfully',
                'data' => [
                    'group_id' => $group->id,
                    'group_code' => $group->group_code,
                    'scheme_name' => optional($group->scheme)->name,
                    'member_id' => $member->id,
                    'member_number' => $member->member_number,
                    'month_number' => $monthNumber,
                    'month_label' => 'Month ' . $monthNumber . ' — ' . $group->periodCalendarLabel($monthNumber),
                    'payout_kind' => $payoutKind,
                    'chit_value' => $chitValueShare,
                    'commission_amount' => $commissionShare,
                    'settlement_amount' => $settlementShare,
                    'is_shared' => (bool) $member->is_shared,
                    'ownership_percentage' => $ownershipPct,
                    'is_eligible' => $isEligible,
                    'kyc_bank_details' => [
                        'bank_name' => $kyc?->bank_name,
                        'account_number' => $kyc?->account_number,
                        'ifsc_code' => $kyc?->ifsc_code,
                        'account_holder_name' => $kyc?->account_holder_name,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot calculate settlement preview: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function settlementApplications(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $query = Payout::with(['group.scheme', 'winner.client', 'winner.shares'])
            ->whereHas('winner', fn ($m) => $m->involvingClient($client->id));

        if ($id) {
            $payout = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Settlement application fetched successfully',
                'data' => $this->formatSettlementApplication($payout, $client),
            ]);
        }
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->input('group_id'));
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $items->getCollection()->transform(fn ($p) => $this->formatSettlementApplication($p, $client));

        return response()->json([
            'success' => true,
            'message' => 'Settlement applications fetched successfully',
            'data' => $items,
            'eligible_groups' => $this->getEligibleChitsForClient($client),
        ]);
    }

    public function applySettlement(Request $request): JsonResponse
    {
        $user = Auth::user();
        CustomerSettlementLog::write('APPLY RECEIVED', [
            'login' => $user?->name,
            'phone' => $user?->phone,
            'user_id' => $user?->id,
            'path' => $request->path(),
            'payload' => $request->except(['password', 'token', 'otp', 'authorization']),
        ]);

        if (! $user) {
            CustomerSettlementLog::write('APPLY REJECTED — not logged in', [
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $client = $user->client
            ?? Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();

        if (! $client) {
            CustomerSettlementLog::write('APPLY REJECTED — client profile not found', [
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404);
        }

        $kycStatus = optional($client->kycDetail)->status;
        if ($kycStatus !== 'verified') {
            CustomerSettlementLog::write('APPLY REJECTED — KYC not verified', [
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'client_name' => $client->client_name,
                'kyc_status' => $kycStatus ?: 'missing',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'KYC must be verified before applying.',
            ], 422);
        }

        $kyc = $client->kycDetail;

        $previousPayoutId = $this->resolveRecordId(
            $request->route('id')
            ?? $request->input('payout_id')
            ?? $request->input('settlement_id')
            ?? $request->input('application_id')
        );
        $previousPayout = $previousPayoutId ? Payout::find($previousPayoutId) : null;
        if ($previousPayout && $previousPayout->isActiveApplication()) {
            CustomerSettlementLog::write('APPLY REJECTED — already has active application', [
                'login' => $user->name,
                'phone' => $user->phone,
                'client_id' => $client->id,
                'payout_id' => $previousPayout->id,
                'payout_code' => $previousPayout->payout_code,
                'status' => $previousPayout->status,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This settlement application is already ' . $previousPayout->customerFacingStatusLabel() . '. Re-apply is allowed only after rejection.',
                'data' => $this->formatSettlementApplication($previousPayout, $client),
            ], 422);
        }

        // Flexible resolution of group_id & member_id (numeric or hashed)
        $groupId = $this->resolveRecordId(
            $request->input('group_id')
            ?? $request->input('chit_id')
            ?? $request->input('chit_group_id')
            ?? $request->input('groupId')
            ?? $previousPayout?->group_id
        );

        $memberId = $this->resolveRecordId(
            $request->input('member_id')
            ?? $request->input('group_member_id')
            ?? $request->input('ticket_id')
            ?? $request->input('memberId')
            ?? $request->input('enrollment_id')
            ?? $request->input('record_id')
            ?? $previousPayout?->winner_member_id
        );

        if (! $groupId && $memberId) {
            $memberRecord = GroupMember::find($memberId);
            if ($memberRecord) {
                $groupId = $memberRecord->group_id;
            }
        }

        if ($groupId && ! $memberId) {
            $memberRecord = GroupMember::where('group_id', $groupId)
                ->involvingClient($client->id)
                ->first();
            if ($memberRecord) {
                $memberId = $memberRecord->id;
            }
        }

        if (! $groupId || ! $memberId) {
            CustomerSettlementLog::write('APPLY REJECTED — missing group/member', [
                'login' => $user->name,
                'phone' => $user->phone,
                'client_id' => $client->id,
                'client_name' => $client->client_name,
                'group_id' => $groupId,
                'member_id' => $memberId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Please select a valid chit group and member membership.',
            ], 422);
        }

        $group = ChitGroup::find($groupId);
        if (! $group) {
            CustomerSettlementLog::write('APPLY REJECTED — group not found', [
                'login' => $user->name,
                'phone' => $user->phone,
                'client_id' => $client->id,
                'group_id' => $groupId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Chit group not found.',
            ], 404);
        }

        $member = GroupMember::where('group_id', $group->id)->find($memberId);
        if (! $member || ! $member->involvesClient($client->id)) {
            CustomerSettlementLog::write('APPLY REJECTED — not a member of group', [
                'login' => $user->name,
                'phone' => $user->phone,
                'client_id' => $client->id,
                'group_id' => $group->id,
                'member_id' => $memberId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this chit group.',
            ], 403);
        }

        $payoutKind = $request->input('payout_kind') ?? Payout::KIND_ORIGINAL;
        if (! in_array($payoutKind, [Payout::KIND_ORIGINAL, Payout::KIND_ADVANCE], true)) {
            $payoutKind = Payout::KIND_ORIGINAL;
        }

        $monthInput = $request->input('month_number')
            ?? $request->input('month')
            ?? $request->input('payout_month');

        $monthNumber = ! empty($monthInput) ? (int) $monthInput : null;
        if (! $monthNumber && $previousPayout && (int) $previousPayout->month_number > 0) {
            $monthNumber = (int) $previousPayout->month_number;
        }

        if (! $monthNumber) {
            $needPeriod = (int) ($member->chit_need_period ?? 0);
            if ($needPeriod > 0 && ! $group->isForemanCommissionMonth($needPeriod) && ! Payout::where('group_id', $group->id)->where('winner_member_id', $member->id)->where('month_number', $needPeriod)->whereIn('status', ['pending', 'processing', 'paid'])->exists()) {
                $monthNumber = $needPeriod;
            } else {
                $monthNumber = $group->getNextSettlementMonth();
            }
        }

        try {
            $payout = $this->payoutService->initiateSettlement(
                group: $group,
                member: $member,
                initiatedBy: Auth::id(),
                payoutKind: $payoutKind,
                monthNumber: $monthNumber,
                appliedSource: 'customer'
            );

            $bankName = $request->input('bank_name') ?: ($kyc?->bank_name ?? null);
            $accountNumber = $request->input('account_number') ?: ($kyc?->account_number ?? null);
            $ifscCode = $request->input('ifsc_code') ?: ($kyc?->ifsc_code ?? null);

            $loginLabel = $user?->name ?: ($user?->phone ?: ('User #' . ($user?->id ?? 'unknown')));
            $updateData = [
                'applied_source' => 'customer',
                'remarks' => $request->filled('remarks')
                    ? $request->input('remarks')
                    : ('Submitted from customer app by ' . $loginLabel),
            ];
            if (! empty($bankName)) {
                $updateData['bank_name'] = $bankName;
            }
            if (! empty($accountNumber)) {
                $updateData['account_number'] = $accountNumber;
            }
            if (! empty($ifscCode)) {
                $updateData['ifsc_code'] = $ifscCode;
            }
            $payout->update($updateData);

            CustomerSettlementLog::write('APPLY SUCCESS', [
                'login' => $user->name,
                'phone' => $user->phone,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'client_name' => $client->client_name,
                'payout_id' => $payout->id,
                'payout_code' => $payout->payout_code,
                'group_id' => $group->id,
                'group_code' => $group->group_code,
                'member_id' => $member->id,
                'month_number' => $payout->month_number,
                'status' => $payout->status,
                'source' => $payout->applied_source,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settlement application submitted successfully.',
                'data' => $this->formatSettlementApplication($payout->fresh(['group.scheme', 'winner.client', 'winner.shares']), $client),
            ], 201);
        } catch (ValidationException $e) {
            CustomerSettlementLog::write('APPLY REJECTED — validation', [
                'login' => $user?->name,
                'phone' => $user?->phone,
                'client_id' => $client->id,
                'group_id' => $group->id ?? $groupId,
                'member_id' => $member->id ?? $memberId,
                'message' => collect($e->errors())->flatten()->first(),
            ]);

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (Throwable $e) {
            CustomerSettlementLog::write('APPLY FAILED', [
                'login' => $user?->name,
                'phone' => $user?->phone,
                'client_id' => $client->id,
                'group_id' => $group->id ?? $groupId ?? null,
                'member_id' => $member->id ?? $memberId ?? null,
                'error' => $e->getMessage(),
            ]);
            Log::error('Customer settlement apply failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Settlement application failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function resolveRecordId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return HashId::decode((string) $value);
    }

    public function familyMembers(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();

        $familyMemberRecord = ChitFamilyMember::where('client_id', $client->id)->first();
        if (! $familyMemberRecord) {
            $family = ChitFamily::where('primary_client_id', $client->id)->first();
        } else {
            $family = $familyMemberRecord->family;
        }

        if (! $family) {
            return response()->json([
                'success' => true,
                'message' => 'No family group linked.',
                'data' => [
                    'family_id' => null,
                    'family_name' => null,
                    'primary_client' => [
                        'id' => $client->id,
                        'name' => $client->client_name,
                        'phone' => $client->client_phone,
                    ],
                    'members' => [],
                    'current_dues' => [],
                    'total_current_due' => 0.0,
                ],
            ]);
        }

        $family->load(['familyMembers.client', 'primaryClient']);
        $currentDues = $family->getCurrentDues()->map(fn ($due) => [
            'installment_id' => $due['installment_id'],
            'client_name' => optional($due['client'])->client_name,
            'client_phone' => optional($due['client'])->client_phone,
            'group_code' => optional($due['group'])->group_code,
            'scheme_name' => optional(optional($due['group'])->scheme)->name,
            'month_number' => $due['month_number'],
            'due_date' => optional($due['due_date'])->format('Y-m-d'),
            'balance' => (float) $due['balance'],
        ])->values();

        $members = $family->familyMembers->map(fn (ChitFamilyMember $fm) => [
            'id' => $fm->id,
            'client_id' => $fm->client_id,
            'client_name' => optional($fm->client)->client_name,
            'client_phone' => optional($fm->client)->client_phone,
            'relationship' => $fm->relationship,
            'is_primary' => (bool) $fm->is_primary,
            'active_chits_count' => GroupMember::where('client_id', $fm->client_id)->whereCustomerFacingStatus('active')->count(),
        ])->values();

        return response()->json([
            'success' => true,
            'message' => 'Family members fetched successfully',
            'data' => [
                'family_id' => $family->id,
                'family_name' => $family->name,
                'notes' => $family->notes,
                'primary_client' => [
                    'id' => optional($family->primaryClient)->id ?: $client->id,
                    'name' => optional($family->primaryClient)->client_name ?: $client->client_name,
                    'phone' => optional($family->primaryClient)->client_phone ?: $client->client_phone,
                ],
                'members' => $members,
                'current_dues' => $currentDues,
                'total_current_due' => (float) $family->total_current_due,
            ],
        ]);
    }

    public function addFamilyMember(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();

        $validated = $request->validate([
            'family_id' => 'nullable|exists:chit_families,id',
            'client_phone' => 'required_without:client_id|string',
            'client_id' => 'required_without:client_phone|exists:clients,id',
            'relationship' => 'required|string|max:100',
        ]);

        $targetClient = ! empty($validated['client_id'])
            ? Client::findOrFail($validated['client_id'])
            : Client::where('client_phone', $validated['client_phone'])->firstOrFail();

        $familyMemberRecord = ChitFamilyMember::where('client_id', $client->id)->first();
        $family = $familyMemberRecord ? $familyMemberRecord->family : ChitFamily::where('primary_client_id', $client->id)->first();

        if (! $family) {
            $family = ChitFamily::create([
                'name' => $client->client_name . "'s Family",
                'primary_client_id' => $client->id,
                'created_by' => Auth::id(),
            ]);

            ChitFamilyMember::create([
                'family_id' => $family->id,
                'client_id' => $client->id,
                'relationship' => 'Self',
                'is_primary' => true,
            ]);
        }

        $existing = ChitFamilyMember::where('client_id', $targetClient->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This family member is already linked to a family.',
            ], 422);
        }

        $member = ChitFamilyMember::create([
            'family_id' => $family->id,
            'client_id' => $targetClient->id,
            'relationship' => $validated['relationship'],
            'is_primary' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Family member added successfully.',
            'data' => [
                'id' => $member->id,
                'family_id' => $family->id,
                'client_id' => $targetClient->id,
                'client_name' => $targetClient->client_name,
                'relationship' => $member->relationship,
            ],
        ], 201);
    }

    protected function ensureInstallmentsExist(GroupMember $member): void
    {
        $member->ensureInstallmentsExist();
    }

    protected function buildMonthWiseInstallments(GroupMember $member, ?Client $client = null): array
    {
        return $member->buildMonthWiseInstallments($client);
    }

    protected function buildProjectedMonthWiseInstallments(GroupMember $member, ?Client $client = null): array
    {
        return $member->buildProjectedMonthWiseInstallments($client);
    }


    protected function formatChitApplication(GroupMember $member, bool $detailed = false, ?Client $client = null): array
    {
        $group = $member->group;
        $scheme = $group?->scheme;

        $clientId = $client ? (int) $client->id : (int) $member->client_id;
        $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
        if ($ownershipPct <= 0) {
            $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
        }

        $fullChitValue = (float) ($group->chit_value ?? 0);
        $chitValueShare = $member->is_shared
            ? (float) $member->amountForClient($fullChitValue, $clientId)
            : round($fullChitValue * ($ownershipPct / 100.0), 2);

        $installmentAmount = (float) $member->apiInstallmentAmountForClient($clientId, $group);

        $data = [
            'id' => $member->id,
            'member_number' => $member->member_number,
            'group_id' => $member->group_id,
            'group_code' => $group->group_code ?? null,
            'scheme_name' => $scheme?->name,
            'chit_value' => $chitValueShare,
            'installment_amount' => $installmentAmount,
            'is_shared' => (bool) $member->is_shared,
            'ownership_percentage' => $ownershipPct,
            'share_percentage' => $ownershipPct,
            'share_percentage_formatted' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
            'share_label' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
            'share_of_chit' => $chitValueShare,
            'collection_frequency' => $member->collection_frequency,
            'collection_frequency_label' => $member->collection_frequency_label ?? ucfirst($member->collection_frequency ?? 'monthly'),
            'chit_need_month' => $member->preferredChitNeedPeriod(),
            'chit_need_month_label' => $member->chit_need_month_label,
            'flyer_url' => $scheme?->flyerUrl(),
            'flyer_pdf_url' => $scheme?->flyerPdfUrl(),
            'status' => $member->persistCustomerFacingStatus(),
            'status_label' => $member->customerFacingStatusLabel(),
            'status_badge' => $member->customerFacingStatusBadge(),
            'applied_at' => optional($member->created_at)?->format('Y-m-d'),
        ];

        $monthWise = null;
        $allPeriods = [];

        if ($detailed || $member->relationLoaded('installments')) {
            $monthWise = $this->buildMonthWiseInstallments($member, $client);
            foreach ($monthWise as $m) {
                foreach (($m['periods'] ?? []) as $p) {
                    $p['month_number'] = $m['month_number'];
                    $p['installment_id'] = $m['id'];
                    $allPeriods[] = $p;
                }
            }
        }

        if ($detailed) {
            $installments = $member->installments ?? collect();
            $paidCount = $installments->where('status', 'paid')->count();
            $totalPaid = (float) $installments->sum(fn ($inst) => $inst->clientPaidShare($clientId));
            $totalDue = (float) $installments->whereNotIn('status', ['paid', 'waived'])->sum(fn ($inst) => $inst->clientBalanceShare($clientId));
            $nextInst = $installments->whereNotIn('status', ['paid', 'waived'])->sortBy('month_number')->first();

            $totalPeriodsCount = count($allPeriods);
            $paidPeriodsCount = count(array_filter($allPeriods, fn ($p) => ($p['status'] ?? '') === 'paid'));

            $data['client'] = [
                'id' => $member->client_id,
                'name' => optional($member->client)->client_name,
                'phone' => optional($member->client)->client_phone,
                'email' => optional($member->client)->client_email,
            ];
            $data['group'] = [
                'id' => $group?->id,
                'group_code' => $group?->group_code,
                'status' => $group?->status,
                'status_badge' => $group?->status_badge ?? 'secondary',
                'chit_value' => $chitValueShare,
                'installment_amount' => $installmentAmount,
                'total_months' => (int) ($group?->total_months ?? 0),
                'current_month' => (int) ($group?->current_month ?? 0),
                'start_date' => optional($group?->start_date)?->format('Y-m-d'),
                'end_date' => optional($group?->end_date)?->format('Y-m-d'),
            ];
            $data['scheme'] = [
                'id' => $scheme?->id,
                'name' => $scheme?->name,
                'scheme_code' => $scheme?->scheme_code,
                'scheme_type' => $scheme?->scheme_type,
                'scheme_type_label' => $scheme?->scheme_type_label,
                'payout_schedule' => $scheme?->payout_schedule ?? [],
                'flyer_url' => $scheme?->flyerUrl(),
                'flyer_pdf_url' => $scheme?->flyerPdfUrl(),
            ];
            $data['referred_by_agent'] = [
                'name' => optional($member->referrerAgent)->agent_name ?? '—',
                'phone' => optional($member->referrerAgent)->agent_phone ?? '—',
            ];
            $data['joined_date'] = optional($member->joined_date)?->format('Y-m-d');
            $data['remarks'] = $member->remarks ?? '';
            $data['installments_summary'] = [
                'total_installments' => (int) ($group?->total_months ?? 0),
                'total_months' => (int) ($group?->total_months ?? 0),
                'paid_installments_count' => $paidCount,
                'total_paid_amount' => $totalPaid,
                'total_due_amount' => $totalDue,
                'next_due_date' => optional($nextInst?->due_date)?->format('Y-m-d'),
                'collection_frequency' => $member->collection_frequency,
                'collection_frequency_label' => $member->collection_frequency_label ?? ucfirst($member->collection_frequency ?? 'monthly'),
                'total_periods' => $totalPeriodsCount,
                'periods_count' => $totalPeriodsCount,
                'paid_periods_count' => $paidPeriodsCount,
                'remaining_periods_count' => max(0, $totalPeriodsCount - $paidPeriodsCount),
            ];
            $settleFacing = $member->customerSettlementFacing();
            $data['settlement_info'] = [
                'status' => $settleFacing['status'],
                'status_label' => $settleFacing['label'],
                'status_badge' => $settleFacing['badge'],
                'amount' => $member->is_shared
                    ? (float) $member->amountForClient((float) $settleFacing['amount'], $clientId)
                    : round((float) $settleFacing['amount'] * ($ownershipPct / 100.0), 2),
                'is_eligible' => $settleFacing['is_eligible'],
                'can_reapply' => (bool) $settleFacing['can_reapply'],
                'last_application_status' => $settleFacing['last_application_status'] ?? null,
            ];
        }

        if ($monthWise !== null) {
            $data['month_wise_installments'] = $monthWise;
            $data['installments'] = $monthWise;
            $data['all_installments'] = $allPeriods;
        }

        return $data;
    }

    protected function formatSettlementApplication(Payout $payout, ?Client $client = null): array
    {
        $group = $payout->group;
        $winner = $payout->winner;
        $clientId = $client ? (int) $client->id : (int) ($winner?->client_id);

        $ownershipPct = 100.0;
        if ($winner && $clientId) {
            $ownershipPct = (float) $winner->ownershipPercentageFor($clientId);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($winner->effective_share_percentage ?? $winner->share_percentage ?? 100);
            }
        }

        $fullPayout = (float) $payout->payout_amount;
        $payoutAmountShare = ($winner && $winner->is_shared && $clientId)
            ? (float) $winner->amountForClient($fullPayout, $clientId)
            : round($fullPayout * ($ownershipPct / 100.0), 2);

        $documents = CollectedDocuments::forChitSettlement($payout);
        $settlementDoc = collect($documents)->firstWhere('type', 'settlement_document');
        $collateralDoc = collect($documents)->firstWhere('type', 'collateral_document');
        $otherDoc = collect($documents)->firstWhere('type', 'other_document');
        $netPayout = (float) ($payout->net_payout_amount ?? $payoutAmountShare);

        return [
            'id' => $payout->id,
            'payout_code' => $payout->payout_code,
            'member_id' => $payout->winner_member_id,
            'member_number' => $winner?->member_number,
            'group_id' => $payout->group_id,
            'group_code' => $group?->group_code,
            'scheme_name' => optional($group?->scheme)->name,
            'month_number' => (int) $payout->month_number,
            'payout_amount' => $payoutAmountShare,
            'payout_amount_formatted' => '₹' . number_format($payoutAmountShare, 2),
            'full_payout_amount' => $fullPayout,
            'is_shared' => (bool) ($winner?->is_shared),
            'ownership_percentage' => $ownershipPct,
            'payout_kind' => $payout->payout_kind,
            'payout_kind_label' => $payout->payout_kind_label ?? ucfirst($payout->payout_kind ?? 'original'),
            'status' => $payout->customerFacingStatus(),
            'status_label' => $payout->customerFacingStatusLabel(),
            'applied_source' => $payout->applied_source ?: 'customer',
            'source' => $payout->source,
            'source_label' => $payout->source_label,
            'status_badge' => $payout->customerFacingStatusBadge(),
            'can_reapply' => $payout->isRejectedApplication(),
            'payment_mode' => $payout->payment_mode,
            'payment_mode_label' => $payout->payment_mode_label ?? null,
            'processing_fee' => (float) ($payout->processing_fee ?? 0),
            'document_charges' => (float) ($payout->document_charges ?? 0),
            'other_charges' => (float) ($payout->other_charges ?? 0),
            'banking_charges' => (float) ($payout->banking_charges ?? 0),
            'net_payout_amount' => $netPayout,
            'bank_name' => $payout->bank_name,
            'account_number' => $payout->account_number,
            'ifsc_code' => $payout->ifsc_code,
            'upi_id' => $payout->upi_id,
            'reference_no' => $payout->reference_no,
            'remarks' => $payout->remarks ?? '',
            'created_at' => optional($payout->created_at)?->format('Y-m-d H:i:s'),
            'paid_date' => optional($payout->paid_date)?->format('Y-m-d'),
            'settlement_details' => [
                'payout_amount' => $payoutAmountShare,
                'net_payout_amount' => $netPayout,
                'payment_mode' => $payout->payment_mode,
                'payment_mode_label' => $payout->payment_mode_label ?? null,
                'bank_name' => $payout->bank_name,
                'account_number' => $payout->account_number,
                'ifsc_code' => $payout->ifsc_code,
                'upi_id' => $payout->upi_id,
                'reference_no' => $payout->reference_no,
                'paid_date' => optional($payout->paid_date)?->format('Y-m-d'),
                'charges' => [
                    'processing_fee' => (float) ($payout->processing_fee ?? 0),
                    'document_charges' => (float) ($payout->document_charges ?? 0),
                    'other_charges' => (float) ($payout->other_charges ?? 0),
                    'banking_charges' => (float) ($payout->banking_charges ?? 0),
                ],
            ],
            'settlement_document_url' => $settlementDoc['file_url'] ?? null,
            'collateral_document_url' => $collateralDoc['file_url'] ?? null,
            'other_document_url' => $otherDoc['file_url'] ?? null,
            'documents' => $documents,
            'settlement_documents' => $documents,
        ];
    }

    protected function formatChitAccountAdminView(GroupMember $member, Client $client): array
    {
        $group = $member->group;
        $scheme = $group?->scheme;
        $installments = $member->installments ?? collect();
        $clientId = (int) $client->id;

        $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
        if ($ownershipPct <= 0) {
            $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
        }

        $fullChitValue = (float) ($group?->chit_value ?? 0);
        $chitValueShare = $member->is_shared
            ? (float) $member->amountForClient($fullChitValue, $clientId)
            : round($fullChitValue * ($ownershipPct / 100.0), 2);

        $clientInstallment = (float) $member->apiInstallmentAmountForClient($clientId, $group);

        $totalInstallments = (int) ($group?->total_months ?? 0);
        $paidInstallments = $installments->where('status', 'paid')->count();
        $remainingInstallments = max(0, $totalInstallments - $paidInstallments);

        $totalPaidAmount = (float) $installments->sum(fn ($inst) => $inst->clientPaidShare($clientId));
        $totalDueAmount = (float) $installments->whereNotIn('status', ['paid', 'waived'])->sum(fn ($inst) => $inst->clientBalanceShare($clientId));
        $totalDividendEarned = (float) $installments->sum(fn ($inst) => $member->amountForClient((float) $inst->dividend_amount, $clientId));
        $totalPenaltyAmount = (float) $installments->sum(fn ($inst) => $member->penaltyAmountForClient((float) $inst->penalty_amount, $clientId));

        $monthWise = $this->buildMonthWiseInstallments($member, $client);
        $allPeriods = [];
        foreach ($monthWise as $m) {
            foreach (($m['periods'] ?? []) as $p) {
                $p['month_number'] = $m['month_number'];
                $p['installment_id'] = $m['id'];
                $allPeriods[] = $p;
            }
        }
        $totalPeriodsCount = count($allPeriods);
        $paidPeriodsCount = count(array_filter($allPeriods, fn ($p) => ($p['status'] ?? '') === 'paid'));

        $auctions = ($group?->auctions ?? collect())->sortBy('month_number')->values()->map(function ($auc) {
            return [
                'id' => $auc->id,
                'month_number' => (int) $auc->month_number,
                'auction_date' => optional($auc->auction_date)?->format('Y-m-d'),
                'winning_bid' => (float) ($auc->winning_bid ?? 0),
                'dividend_per_member' => (float) ($auc->dividend_per_member ?? 0),
                'status' => $auc->status,
                'winner_name' => optional(optional($auc->winner)->client)->client_name ?? '—',
            ];
        });

        $payouts = ($member->payouts ?? collect())->values()->map(fn (Payout $p) => $this->formatSettlementApplication($p, $client));
        $settleFacing = $member->customerSettlementFacing();

        return [
            'account_info' => [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'group_id' => $group?->id,
                'group_code' => $group?->group_code,
                'scheme_id' => $scheme?->id,
                'scheme_name' => $scheme?->name,
                'flyer_url' => $scheme?->flyerUrl(),
                'flyer_pdf_url' => $scheme?->flyerPdfUrl(),
                'status' => $member->persistCustomerFacingStatus(),
                'status_label' => $member->customerFacingStatusLabel(),
                'status_badge' => $member->customerFacingStatusBadge(),
                'chit_value' => $chitValueShare,
                'installment_amount' => $clientInstallment,
                'is_shared' => (bool) $member->is_shared,
                'ownership_percentage' => $ownershipPct,
                'share_percentage' => $ownershipPct,
                'share_percentage_formatted' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'share_label' => rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%',
                'share_of_chit' => $chitValueShare,
                'collection_frequency' => $member->collection_frequency,
                'joined_date' => optional($member->joined_date)?->format('Y-m-d'),
                'remarks' => $member->remarks ?? '',
            ],
            'financial_summary' => [
                'total_chit_value' => $chitValueShare,
                'total_installments' => $totalInstallments,
                'total_months' => $totalInstallments,
                'paid_installments_count' => $paidInstallments,
                'remaining_installments_count' => $remainingInstallments,
                'total_paid_amount' => $totalPaidAmount,
                'total_due_amount' => $totalDueAmount,
                'total_dividend_earned' => $totalDividendEarned,
                'total_penalty_amount' => $totalPenaltyAmount,
                'collection_frequency' => $member->collection_frequency,
                'collection_frequency_label' => $member->collection_frequency_label ?? ucfirst($member->collection_frequency ?? 'monthly'),
                'total_periods' => $totalPeriodsCount,
                'periods_count' => $totalPeriodsCount,
                'paid_periods_count' => $paidPeriodsCount,
                'remaining_periods_count' => max(0, $totalPeriodsCount - $paidPeriodsCount),
            ],
            'settlement_info' => [
                'status' => $settleFacing['status'],
                'status_label' => $settleFacing['label'],
                'status_badge' => $settleFacing['badge'],
                'amount' => $member->is_shared
                    ? (float) $member->amountForClient((float) $settleFacing['amount'], $clientId)
                    : round((float) $settleFacing['amount'] * ($ownershipPct / 100.0), 2),
                'is_eligible' => $settleFacing['is_eligible'],
                'can_reapply' => (bool) $settleFacing['can_reapply'],
                'last_application_status' => $settleFacing['last_application_status'] ?? null,
            ],
            'passbook_installments' => $monthWise,
            'month_wise_installments' => $monthWise,
            'all_installments' => $allPeriods,
            'group_auctions' => $auctions,
            'settlement_payouts' => $payouts,
        ];
    }
}
