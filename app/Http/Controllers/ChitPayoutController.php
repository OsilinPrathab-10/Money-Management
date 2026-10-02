<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\ChitGroup;
use App\Models\GroupMember;
use App\Services\ChitPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChitPayoutController extends Controller
{
    public function __construct(
        protected ChitPayoutService $payoutService
    ) {}

    protected function checkRole(): void
    {
        if (!Auth::check() || !Auth::user()->hasAnyRole(['Admin', 'Staff', 'admin', 'staff'])) {
            abort(403, 'Only administrators and staff members are authorized to access the Chit Settlement module.');
        }
    }

    public function index(Request $request)
    {
        return redirect()->route('chit.settlement-applications.index');
    }

    public function show(Payout $payout)
    {
        $this->checkRole();

        $payout->load(['group.scheme', 'winner.client', 'auction', 'processedBy', 'initiatedBy']);
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->get();
        $mode = 'show';
        return view('admin.chit.payouts.index', compact('payout', 'mode', 'bankAccounts'));
    }

    public function store(Request $request)
    {
        $this->checkRole();

        $request->validate([
            'group_id'  => 'required|exists:chit_groups,id',
            'member_id' => 'required|exists:group_members,id',
        ]);

        $group = ChitGroup::findOrFail($request->group_id);
        $member = GroupMember::where('group_id', $group->id)->findOrFail($request->member_id);

        try {
            $payout = $this->payoutService->initiateSettlement($group, $member, Auth::id());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('chit.payouts.show', $payout)
            ->with('success', 'Chit settlement initiated for month ' . $payout->month_number . '. Please process the payment below.');
    }

    public function process(Request $request, Payout $payout)
    {
        $this->checkRole();

        $validated = $request->validate([
            'payment_mode'   => 'required|in:cash,bank_transfer,upi,other',
            'internal_bank_account_id' => 'nullable|exists:bank_accounts,id',
            'processing_fee' => 'nullable|numeric|min:0',
            'document_charges' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'paid_date'      => 'nullable|date',
            'bank_name'      => 'nullable|string',
            'account_number' => 'nullable|string',
            'ifsc_code'      => 'nullable|string',
            'upi_id'         => 'nullable|string',
            'reference_no'   => 'nullable|string',
            'remarks'        => 'nullable|string',
        ]);

        try {
            $this->payoutService->processSettlement($payout, $validated, Auth::id());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Settlement processed successfully!');
    }
}
