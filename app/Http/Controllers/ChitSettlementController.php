<?php

namespace App\Http\Controllers;

use App\Models\ChitGroup;
use App\Models\CompanyDetail;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Services\ChitPayoutService;
use App\Services\ReportBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChitSettlementController extends Controller
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

    public function manage(Request $request)
    {
        $this->checkRole();

        // Settlement Management page removed — use Settlement Applications.
        return redirect()->route('chit.settlement-applications.index', array_filter([
            'group_id' => $request->query('group_id'),
            'client_id' => $request->query('client_id'),
            'search' => $request->query('search'),
        ]));
    }

    public function history(Request $request)
    {
        $this->checkRole();

        $filters = $request->only(['status', 'group_id', 'client_id', 'month_number', 'search', 'date_from', 'date_to', 'payout_kind']);
        
        $baseQuery = $this->payoutService->historyQuery($filters);
        $settlements = (clone $baseQuery)->paginate(20)->withQueryString();
        
        // Summary stats for filtered history query (e.g. filtered by group_id)
        $stats = [
            'total_count' => (clone $baseQuery)->count(),
            'paid_count' => (clone $baseQuery)->where('status', 'paid')->count(),
            'advance_count' => (clone $baseQuery)->where('payout_kind', 'advance')->count(),
            'total_net_paid' => (float) (clone $baseQuery)->where('status', 'paid')->get()->sum(function ($p) {
                // Banking charges are company bank cost — not deducted from member net.
                return (float) ($p->net_payout_amount ?? max(0, $p->payout_amount - ((float)$p->processing_fee + (float)$p->document_charges + (float)$p->other_charges)));
            }),
        ];

        $groups = ChitGroup::whereIn('status', ['active', 'completed'])->orderBy('id', 'desc')->get();
        $selectedGroup = !empty($filters['group_id']) ? ChitGroup::with('scheme')->find($filters['group_id']) : null;

        return view('admin.chit.settlements.history', compact('settlements', 'groups', 'filters', 'stats', 'selectedGroup'));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->checkRole();

        $filters = $request->only(['status', 'group_id', 'month_number', 'search', 'date_from', 'date_to', 'payout_kind']);
        $settlements = $this->payoutService->historyQuery($filters)->get();

        $fileName = 'chit_settlement_history_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($settlements) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Settlement Code', 'Settlement Type', 'Chit Group', 'Chit Month', 'Member Name', 'Member Phone',
                'Settlement Amount', 'Processing Fee', 'Document Charges', 'Other Charges', 'Banking Charges', 'Net Payable',
                'Payment Date', 'Payment Mode', 'Transaction Reference',
                'Status', 'Processed By', 'Remarks',
            ]);

            foreach ($settlements as $settlement) {
                fputcsv($handle, [
                    $settlement->payout_code,
                    $settlement->payout_kind_label,
                    $settlement->group->group_code ?? '',
                    'Month ' . $settlement->month_number,
                    $settlement->winner?->client->client_name ?? '',
                    $settlement->winner?->client->client_phone ?? '',
                    $settlement->payout_amount,
                    $settlement->processing_fee,
                    $settlement->document_charges,
                    $settlement->other_charges,
                    $settlement->banking_charges ?? 0,
                    $settlement->net_payout_amount ?? $settlement->payout_amount,
                    $settlement->paid_date?->format('Y-m-d') ?? '',
                    $settlement->payment_mode,
                    $settlement->reference_no ?? '',
                    ucfirst($settlement->status),
                    $settlement->processedBy?->name ?? '',
                    $settlement->remarks ?? '',
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    public function confirm(ChitGroup $group, GroupMember $member, Request $request)
    {
        $this->checkRole();

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        $group->load(['scheme', 'members.client.kycDetail', 'members.shares.client.kycDetail']);
        $member->load(['client.kycDetail', 'shares.client.kycDetail']);
        $clientKyc = $member->client?->kycDetail ?? $member->shares->first()?->client?->kycDetail;

        $payoutKind = $request->query('kind') === Payout::KIND_ADVANCE ? Payout::KIND_ADVANCE : Payout::KIND_ORIGINAL;
        $overview = $this->payoutService->getGroupOverview($group);
        $requestedPayoutId = $request->filled('payout_id') ? (int) $request->query('payout_id') : null;

        // Prefer the specific application being released (e.g. Month 7), not only the group's "next" month.
        $pendingSettlement = null;
            $pendingQuery = Payout::query()
                ->where('group_id', $group->id)
                ->where('winner_member_id', $member->id)
                ->whereIn('status', ['pending', 'processing']);

            if ($requestedPayoutId) {
                $pendingSettlement = (clone $pendingQuery)->whereKey($requestedPayoutId)->first();
            }

            if (!$pendingSettlement) {
            $pendingSettlement = (clone $pendingQuery)
                ->where('payout_kind', $payoutKind)
                ->latest('id')
                ->first();
        }

        if (!$pendingSettlement && $payoutKind === Payout::KIND_ORIGINAL) {
                $pendingSettlement = $pendingQuery->latest('id')->first();
        }

        if ($pendingSettlement) {
            $payoutKind = $pendingSettlement->payout_kind === Payout::KIND_ADVANCE
                ? Payout::KIND_ADVANCE
                : Payout::KIND_ORIGINAL;
            $context = [
                'kind' => $payoutKind,
                'month_number' => (int) $pendingSettlement->month_number,
                'original_payout' => $pendingSettlement->originalPayout,
            ];
            $amounts = $this->payoutService->calculateAmounts($group, null, $context['month_number'], $member);
        } else {
            try {
                $context = $this->payoutService->resolveSettlementContext($group, $member, $payoutKind);
            } catch (ValidationException $e) {
                return redirect()->route('chit.settlement-applications.index')
                    ->with('error', collect($e->errors())->flatten()->first());
            }

            $amounts = $this->payoutService->calculateAmounts($group, null, $context['month_number'], $member);
            $payoutKind = $context['kind'];

            if ($payoutKind === Payout::KIND_ORIGINAL) {
                if (!$this->payoutService->isMemberEligible($group, $member)) {
                    return redirect()->route('chit.settlement-applications.index')
                        ->with('error', 'This member is not eligible for settlement.');
                }
            } elseif (!$this->payoutService->canInitiateAdvanceSettlement($group, $member, $context['month_number'])) {
                return redirect()->route('chit.settlement-applications.index')
                    ->with('error', 'This member is not eligible for an advance payout.');
            }
        }

        if ($group->isForemanCommissionMonth($context['month_number'])) {
            return redirect()->route('chit.settlement-applications.index')
                ->with('error', 'Member settlements are not allowed for Month ' . $context['month_number'] . ' (Foreman Commission month).');
        }

        if ($this->payoutService->memberHasCompletedSettlement($group, $member)) {
            return redirect()->route('chit.settlement-applications.index')
                ->with('error', 'This member has already received a settlement.');
        }

        $defaultFees = $this->payoutService->defaultSettlementFees();
        $grossPayout = $pendingSettlement
            ? (float) $pendingSettlement->payout_amount
            : (float) $amounts['payout_amount'];
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->get();

        return view('admin.chit.settlements.confirm', compact(
            'group',
            'member',
            'overview',
            'amounts',
            'pendingSettlement',
            'payoutKind',
            'context',
            'defaultFees',
            'grossPayout',
            'bankAccounts',
            'clientKyc'
        ));
    }

    public function process(Request $request, ChitGroup $group, GroupMember $member)
    {
        $this->checkRole();

        if ((int) $member->group_id !== (int) $group->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_mode'   => 'required|in:cash,bank_transfer,upi,other',
            'internal_bank_account_id' => 'nullable|exists:bank_accounts,id',
            'paid_date'      => 'required|date',
            'bank_name'      => 'nullable|string',
            'account_number' => 'nullable|string',
            'ifsc_code'      => 'nullable|string',
            'upi_id'         => 'nullable|string',
            'reference_no'   => 'nullable|string',
            'remarks'        => 'nullable|string',
            'pending_id'     => 'nullable|exists:payouts,id',
            'payout_kind'    => 'nullable|in:original,advance',
            'processing_fee' => 'nullable|numeric|min:0',
            'document_charges' => 'nullable|numeric|min:0',
            'other_charges'  => 'nullable|numeric|min:0',
            'banking_charges' => 'nullable|numeric|min:0',
            'settlement_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'other_document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        if ($request->hasFile('settlement_document')) {
            $path = $request->file('settlement_document')->store('uploads/settlements', 'public');
            $validated['settlement_document'] = $path;
        }

        if ($request->hasFile('other_document')) {
            $path = $request->file('other_document')->store('uploads/settlements', 'public');
            $validated['other_document'] = $path;
        }

        $payoutKind = $validated['payout_kind'] ?? Payout::KIND_ORIGINAL;

        try {
            if (!empty($validated['pending_id'])) {
                $payout = Payout::where('group_id', $group->id)
                    ->where('winner_member_id', $member->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->findOrFail($validated['pending_id']);

                $payout = $this->payoutService->processSettlement($payout, $validated, Auth::id());
            } else {
                $payout = $this->payoutService->settleAndPay($group, $member, $validated, Auth::id(), $payoutKind);
            }
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->with('error', $msg);
        }

        $successLabel = $payoutKind === Payout::KIND_ADVANCE ? 'Advance Amount payout' : 'Settlement';
        $message = $successLabel . ' completed successfully for ' . ($member->client->client_name ?? 'member') . '.';
        $redirectUrl = route('chit.settlements.history', ['group_id' => $group->id]);
        $promissoryNoteUrl = route('chit.settlements.promissory-note', $payout);
        $promissoryPdfUrl = route('chit.settlements.promissory-note.pdf', $payout);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect_url' => $redirectUrl,
                'payout_id' => $payout->id,
                'promissory_note_url' => $promissoryNoteUrl,
                'promissory_pdf_url' => $promissoryPdfUrl,
                'member_id' => $member->id,
                'group_id' => $group->id,
                'settlement_month' => $payout->month_number,
                'next_month' => $payout->month_number + 1,
                'total_months' => $group->total_months,
                'current_installment' => (float) ($member->custom_installment_amount ?: $group->installment_amount),
            ]);
        }

        return redirect()->to($promissoryNoteUrl)->with('success', $message);
    }

    /**
     * Printable settlement consent & promissory note (browser print).
     */
    public function promissoryNote(Payout $payout)
    {
        $this->checkRole();

        return view('admin.chit.settlements.promissory-note', $this->promissoryNoteData($payout, 'print'));
    }

    /**
     * Download settlement consent & promissory note as PDF.
     */
    public function promissoryNotePdf(Payout $payout)
    {
        $this->checkRole();

        $data = $this->promissoryNoteData($payout, 'pdf');
        $filename = 'settlement-promissory-note-' . ($payout->payout_code ?: $payout->id) . '.pdf';

        return Pdf::loadView('admin.chit.settlements.promissory-note', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function promissoryNoteData(Payout $payout, string $mode = 'pdf'): array
    {
        $payout->loadMissing([
            'group.scheme',
            'group.branch',
            'winner.client.kycDetail',
            'winner.installments',
            'processedBy',
        ]);

        $member = $payout->winner;
        $client = $member?->client;
        $group = $payout->group;
        $scheme = $group?->scheme;
        $company = CompanyDetail::query()->first();
        $branding = app(ReportBrandingService::class)->get();

        // Banking charges are company bank cost — not deducted from member settlement amount.
        $fees = (float) ($payout->processing_fee ?? 0)
            + (float) ($payout->document_charges ?? 0)
            + (float) ($payout->other_charges ?? 0);
        $settlementAmount = (float) ($payout->net_payout_amount ?? max(0, (float) $payout->payout_amount - $fees));
        $amountReceived = $payout->status === 'paid' ? $settlementAmount : 0.0;
        $outstandingAmount = (float) ($member?->outstanding_balance ?? 0);

        $schemeName = $scheme->name ?? $scheme->scheme_name ?? 'Chit Scheme';
        $groupLabel = trim(($schemeName ? $schemeName . ' / ' : '') . ($group->group_code ?? ''));
        if ($groupLabel === '') {
            $groupLabel = 'N/A';
        }

        $memberAddress = collect([
            $client?->address,
            $client?->flat,
            $client?->street,
            $client?->landmark,
            $client?->city,
            $client?->district,
            $client?->state,
            $client?->pincode,
        ])->filter(fn ($v) => filled($v))->unique()->implode(', ') ?: 'N/A';

        $aadhaar = $client?->aadhaar_number;
        $pan = $client?->kycDetail?->pan_number;
        $idProof = collect([
            $aadhaar ? ('Aadhaar: ' . $aadhaar) : null,
            $pan ? ('PAN: ' . $pan) : null,
        ])->filter()->implode(' / ') ?: 'N/A';

        $startDate = $group?->start_date
            ? \Carbon\Carbon::parse($group->start_date)
            : ($group?->created_at ? \Carbon\Carbon::parse($group->created_at) : null);
        $monthLabel = $payout->month_number
            ? ('Month ' . $payout->month_number . ($startDate
                ? ' — ' . $startDate->copy()->addMonths((int) $payout->month_number - 1)->format('M Y')
                : ''))
            : 'N/A';

        $place = $company?->city
            ?: ($group?->branch?->city ?? $group?->branch?->name)
            ?: '________________';

        $authorizedUser = $payout->processedBy ?: Auth::user();

        return [
            'mode' => $mode,
            'reportBranding' => $branding,
            'companyName' => $branding['name'] ?? ($company?->company_name ?: config('app.name')),
            'noteDate' => optional($payout->paid_date)->format('d M Y') ?: now()->format('d M Y'),
            'place' => $place,
            'payoutCode' => $payout->payout_code ?: ('#' . $payout->id),
            'monthLabel' => $monthLabel,
            'memberName' => $client?->client_name ?: 'N/A',
            'customerId' => $client?->displayCustomerId() ?? 'N/A',
            'groupLabel' => $groupLabel,
            'chitValue' => (float) ($payout->chit_value ?: $group?->chit_value ?: 0),
            'ticketNumber' => $member?->member_number
                ? ((string) $member->member_number . ($member->account_number ? ' (' . $member->account_number . ')' : ''))
                : 'N/A',
            'memberAddress' => $memberAddress,
            'settlementAmount' => $settlementAmount,
            'amountReceived' => $amountReceived,
            'outstandingAmount' => $outstandingAmount,
            'outstandingAmountInWords' => $this->amountInWords($outstandingAmount),
            'memberMobile' => $client?->client_phone ?: 'N/A',
            'idProof' => $idProof,
            'authorizedName' => $authorizedUser?->name ?: '________________',
            'authorizedDesignation' => $authorizedUser?->roles?->first()?->name ?: 'Authorized Signatory',
            'generatedAt' => now()->format('d M Y, h:i A'),
            'pdfUrl' => route('chit.settlements.promissory-note.pdf', $payout),
            'backUrl' => route('chit.settlements.history', array_filter(['group_id' => $group?->id])),
        ];
    }

    protected function amountInWords(float $amount): string
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            return 'Zero';
        }

        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = $this->integerToWords($rupees);
        if ($paise > 0) {
            $words .= ' and ' . $this->integerToWords($paise) . ' Paise';
        }

        return $words;
    }

    protected function integerToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = [
            '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen',
        ];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $convert = function (int $n) use (&$convert, $ones, $tens): string {
            if ($n < 20) {
                return $ones[$n];
            }
            if ($n < 100) {
                return trim($tens[(int) floor($n / 10)] . ' ' . $ones[$n % 10]);
            }
            if ($n < 1000) {
                return trim($ones[(int) floor($n / 100)] . ' Hundred ' . $convert($n % 100));
            }
            if ($n < 100000) {
                return trim($convert((int) floor($n / 1000)) . ' Thousand ' . $convert($n % 1000));
            }
            if ($n < 10000000) {
                return trim($convert((int) floor($n / 100000)) . ' Lakh ' . $convert($n % 100000));
            }

            return trim($convert((int) floor($n / 10000000)) . ' Crore ' . $convert($n % 10000000));
        };

        return trim($convert($number));
    }

    public function cancel(Payout $payout, Request $request)
    {
        $this->checkRole();

        try {
            $this->payoutService->cancelSettlement($payout, Auth::id(), 'Rejected/Cancelled by ' . Auth::user()->name);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Settlement application rejected successfully.']);
        }

        return back()->with('success', 'Settlement cancelled.');
    }

    public function apply(Request $request)
    {
        $this->checkRole();

        $validated = $request->validate([
            'group_id' => 'required|exists:chit_groups,id',
            'member_id' => 'required|exists:group_members,id',
            'payout_kind' => 'nullable|in:original,advance',
            'month_number' => 'nullable|integer|min:1',
            'remarks' => 'nullable|string',
        ]);

        $group = ChitGroup::findOrFail($validated['group_id']);
        $member = GroupMember::withTrashed()
            ->where('group_id', $group->id)
            ->whereKey($validated['member_id'])
            ->firstOrFail();

        $payoutKind = $validated['payout_kind'] ?? Payout::KIND_ORIGINAL;
        $monthNumber = !empty($validated['month_number']) ? (int) $validated['month_number'] : null;

        // Contribution settlements (outgoing transfer / cancelled) always use original kind.
        if ($this->payoutService->usesContributionSettlement($member)) {
            $payoutKind = Payout::KIND_ORIGINAL;
            $monthNumber = null; // resolved from paid − foreman months
        }

        try {
            $payout = $this->payoutService->initiateSettlement($group, $member, Auth::id(), $payoutKind, $monthNumber);
            if (!empty($validated['remarks'])) {
                $payout->update(['remarks' => $validated['remarks']]);
            }
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first();
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->with('error', $msg);
        }

        $message = ($payout->payout_kind === Payout::KIND_ADVANCE)
            ? 'Advance Amount application submitted for ' . ($member->client->client_name ?? 'client') . ' (same month as original settlement).'
            : ($payout->isOutgoingRelease()
                ? 'Outgoing Release application submitted for ' . ($member->client->client_name ?? 'client') . ' (paid months − foreman).'
                : ($payout->isContributionSettlement()
                    ? 'Cancel Settlement application submitted for ' . ($member->client->client_name ?? 'client') . ' (paid months − foreman).'
                    : 'Settlement application submitted successfully for ' . ($member->client->client_name ?? 'client') . '.'));

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'payout_id' => $payout->id,
                'payout_kind' => $payout->payout_kind,
                'payout_kind_label' => $payout->payout_kind_label,
                'redirect_url' => route('chit.settlements.confirm', [$group, $member]) . ($payout->payout_kind === Payout::KIND_ADVANCE ? '?kind=advance' : ''),
            ]);
        }

        $confirmUrl = route('chit.settlements.confirm', [$group, $member]);
        if ($payout->payout_kind === Payout::KIND_ADVANCE) {
            $confirmUrl .= '?kind=advance';
        }

        return redirect($confirmUrl)->with('success', $message);
    }

    public function updateFutureInstallment(Request $request, ChitGroup $group, GroupMember $member)
    {
        $this->checkRole();

        $validated = $request->validate([
            'new_installment_amount' => 'required|numeric|min:1',
            'effective_from_month'   => 'required|integer|min:1',
        ]);

        $newAmount = floatval($validated['new_installment_amount']);
        $startMonth = intval($validated['effective_from_month']);

        $member->updateFutureInstallmentAmount($newAmount, $startMonth);

        $msg = 'Future monthly installment updated to ₹' . number_format($newAmount, 2) . ' starting from Month ' . $startMonth . ' onwards for ' . ($member->client->client_name ?? 'member') . '.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }
}
