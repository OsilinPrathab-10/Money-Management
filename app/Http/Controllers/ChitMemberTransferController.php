<?php

namespace App\Http\Controllers;

use App\Models\ChitMemberTransfer;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Services\ChitMemberTransferService;
use App\Services\ChitPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChitMemberTransferController extends Controller
{
    public function __construct(
        protected ChitMemberTransferService $transferService,
        protected ChitPayoutService $payoutService
    ) {}

    protected function checkRole(): void
    {
        if (! Auth::check() || ! Auth::user()->hasAnyRole(['Admin', 'Staff', 'admin', 'staff'])) {
            abort(403, 'Only administrators and staff members are authorized to transfer chit members.');
        }
    }

    public function index(Request $request)
    {
        $this->checkRole();

        $query = ChitMemberTransfer::with([
            'group',
            'destinationGroup',
            'outgoingMember.client',
            'incomingMember.client',
            'processedBy',
        ])->latest('transfer_date');

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transfer_code', 'like', "%{$search}%")
                    ->orWhereHas('outgoingMember.client', fn ($c) => $c->where('client_name', 'like', "%{$search}%"))
                    ->orWhereHas('incomingMember.client', fn ($c) => $c->where('client_name', 'like', "%{$search}%"))
                    ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"));
            });
        }

        $transfers = $query->paginate(20)->withQueryString();
        $groups = \App\Models\ChitGroup::whereIn('status', ['active', 'completed'])->orderBy('id', 'desc')->get();

        return view('admin.chit.transfers.form', compact('transfers', 'groups') + ['mode' => 'index']);
    }

    public function create(GroupMember $member)
    {
        $this->checkRole();

        $member->load(['group.scheme', 'client', 'installments']);

        try {
            $preview = $this->transferService->preview($member);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('chit.groups.show', $member->group)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        $clients = Client::where('status', 'active')
            ->where('id', '!=', $member->client_id)
            ->orderBy('client_name')
            ->get();
        $agents = \App\Models\Agent::orderBy('agent_name')->get();

        $destinationGroups = \App\Models\ChitGroup::with(['scheme', 'members' => fn ($q) => $q->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)])
            ->where('status', 'active')
            ->where('id', '!=', $member->group_id)
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($g) => $g->remainingSeats() > 0.0001)
            ->map(function ($g) {
                $g->open_count = $g->occupiedSeats();
                return $g;
            })
            ->values();

        $bankAccounts = \App\Models\Account\BankAccount::query()
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();

        return view('admin.chit.transfers.form', compact(
            'member',
            'preview',
            'clients',
            'agents',
            'destinationGroups',
            'bankAccounts'
        ) + ['mode' => 'create']);
    }

    public function preview(Request $request, GroupMember $member)
    {
        $this->checkRole();

        try {
            $destinationGroupId = $request->filled('destination_group_id')
                ? (int) $request->input('destination_group_id')
                : null;
            $preview = $this->transferService->preview($member, $destinationGroupId);

            return response()->json(['success' => true, 'data' => $preview]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }
    }

    public function store(Request $request, GroupMember $member)
    {
        $this->checkRole();

        $validated = $request->validate([
            'transfer_type' => 'required|in:same_group,cross_group',
            'destination_group_id' => 'required_if:transfer_type,cross_group|nullable|exists:chit_groups,id',
            'incoming_client_id' => 'required_if:transfer_type,same_group|nullable|exists:clients,id',
            'chit_need_month' => 'nullable|integer|min:1|max:12',
            'referred_by' => 'nullable|string',
            'transfer_date' => 'nullable|date',
            'confirm_second_seat' => 'nullable|boolean',
            'incoming_payment_amount' => 'nullable|numeric|min:0',
            'incoming_payment_mode' => 'nullable|in:cash,in_hand,upi,bank_transfer,wallet',
            'incoming_payment_reference_no' => 'nullable|string|max:64',
            'internal_bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        // Outgoing settlement is applied later via Settlement Applications (paid − foreman).
        $validated['outgoing_settlement_mode'] = null;
        $validated['remarks'] = null;
        $validated['confirm_second_seat'] = $request->boolean('confirm_second_seat');

        // Prevent the hidden mode's field from affecting the other transfer path.
        if (($validated['transfer_type'] ?? '') === 'cross_group') {
            $validated['incoming_client_id'] = null;
            $validated['incoming_payment_amount'] = 0;
            $validated['incoming_payment_mode'] = null;
            $validated['internal_bank_account_id'] = null;
        } else {
            $validated['destination_group_id'] = null;
            $validated['confirm_second_seat'] = false;
            $validated['takeover_amount'] = (float) ($validated['incoming_payment_amount'] ?? 0);
            $validated['takeover_payment_mode'] = $validated['incoming_payment_mode'] ?? null;
            $validated['takeover_reference_no'] = $validated['incoming_payment_reference_no'] ?? null;
        }

        try {
            $transfer = $this->transferService->process($member, $validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $msg = $transfer->isCrossGroup()
            ? 'Member moved to another group successfully. Transfer code: ' . $transfer->transfer_code
            : 'Member transfer completed successfully. Transfer code: ' . $transfer->transfer_code;

        return redirect()
            ->route('chit.transfers.show', $transfer)
            ->with('success', $msg);
    }

    public function show(ChitMemberTransfer $transfer)
    {
        $this->checkRole();

        $transfer->load([
            'group.scheme',
            'destinationGroup.scheme',
            'outgoingMember.client',
            'incomingMember.installments',
            'incomingMember.client',
            'processedBy',
        ]);

        $incomingFullySettled = $transfer->incomingMember
            ? $this->transferService->incomingInstallmentsFullySettled($transfer->incomingMember)
            : false;

        $outgoingSettlementAmount = $transfer->outgoingMember
            ? $this->payoutService->contributionSettlementAmount($transfer->outgoingMember)
            : (float) ($transfer->outgoing_settlement_amount ?? 0);

        $outgoingHasPaidSettlement = $transfer->outgoingMember
            ? Payout::query()
                ->where('winner_member_id', $transfer->outgoing_member_id)
                ->where('status', 'paid')
                ->exists()
            : $transfer->isOutgoingSettlementPaid();

        $outgoingSettlementUrl = route('chit.settlement-applications.index', [
            'group_id' => $transfer->group_id,
        ]);

        return view('admin.chit.transfers.form', compact(
            'transfer',
            'incomingFullySettled',
            'outgoingSettlementAmount',
            'outgoingHasPaidSettlement',
            'outgoingSettlementUrl'
        ) + ['mode' => 'show']);
    }

    public function releaseOutgoingSettlement(ChitMemberTransfer $transfer)
    {
        return back()->with(
            'error',
            'Outgoing settlement is not released here. Open Settlement Applications and apply for paid months − foreman commission for the outgoing member.'
        );
    }
}
