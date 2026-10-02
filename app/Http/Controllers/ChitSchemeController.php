<?php

namespace App\Http\Controllers;

use App\Models\ChitScheme;
use App\Models\ChitGroup;
use App\Models\Branch;
use App\Services\ReportBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChitSchemeController extends Controller
{
    public function index(Request $request)
    {
        $isTrash = $request->trash === 'true';

        if ($isTrash) {
            $query = ChitScheme::onlyTrashed()->with('branch')->withCount(['chitGroups' => fn ($q) => $q->withTrashed()])->latest('deleted_at');
        } else {
            $query = ChitScheme::with('branch')->withCount(['chitGroups' => fn ($q) => $q->withTrashed()])->latest();
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('scheme_code', 'like', "%$s%");
            });
        }

        if ($request->filled('status') && !$isTrash) {
            $query->where('status', $request->status);
        }

        if ($request->filled('scheme_type')) {
            $query->where('scheme_type', $request->scheme_type);
        }

        $schemes  = $query->paginate(15)->withQueryString();
        $branches = Branch::all();
        $mode     = $isTrash ? 'trash' : 'index';

        return view('admin.chit.schemes.index', compact('schemes', 'branches', 'mode'));
    }

    public function create()
    {
        $branches    = Branch::all();
        $schemeTypes = ChitScheme::schemeTypes();
        $frequencies = ChitScheme::frequencies();
        $mode        = 'create';
        return view('admin.chit.schemes.index', compact('branches', 'schemeTypes', 'frequencies', 'mode'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateScheme($request);
        $validated = $this->normalizeSchemePayload($validated);

        $validated['scheme_code']        = ChitScheme::generateCode();
        $validated['created_by']         = Auth::id();
        // Registration is managed at the Group level, not the Scheme level
        $validated['registration_type']  = ChitScheme::REGISTRATION_NON_REGISTERED;

        ChitScheme::create($validated);

        return redirect()->route('chit.schemes.index')->with('success', 'Chit scheme created successfully!');
    }

    /**
     * Printable payout-schedule template for the scheme being created or edited.
     * Works off the values currently in the form, so it is available before the
     * scheme is saved; blank cells stay blank to be filled in by hand.
     */
    public function payoutTemplatePdf(Request $request)
    {
        $data = $request->validate([
            'name'                     => 'nullable|string|max:100',
            'scheme_type'              => 'nullable|string|max:30',
            'chit_value'               => 'nullable|numeric|min:0',
            'total_members'            => 'nullable|integer|min:0|max:100',
            'duration_months'          => 'required|integer|min:1|max:120',
            'installment_frequency'    => 'nullable|in:monthly,weekly,daily',
            'commission_pct'           => 'nullable|numeric|min:0|max:100',
            'installment_amount'       => 'nullable|numeric|min:0',
            'foreman_commission_month' => 'nullable|integer|min:0|max:120',
            'payout_schedule'          => 'nullable|array',
            'payout_schedule.*.installment_no'     => 'nullable|integer|min:1',
            'payout_schedule.*.installment_amount' => 'nullable|numeric|min:0',
            'payout_schedule.*.payout_amount'      => 'nullable|string|max:100',
        ]);

        $duration     = (int) $data['duration_months'];
        $foremanMonth = (int) ($data['foreman_commission_month'] ?? 0);

        $submitted = collect($data['payout_schedule'] ?? [])
            ->keyBy(fn ($row, $index) => (int) ($row['installment_no'] ?? ($index + 1)));

        $rows = [];
        for ($month = 1; $month <= $duration; $month++) {
            $row        = $submitted->get($month, []);
            $isForeman  = $foremanMonth > 0 && $month === $foremanMonth;
            $installment = $row['installment_amount'] ?? null;
            $payout      = $row['payout_amount'] ?? null;

            $rows[] = [
                'month'       => $month,
                'installment' => ($installment === null || trim((string) $installment) === '')
                    ? null
                    : (float) $installment,
                // DejaVu (dompdf's font) has no Tamil glyphs, so keep the label English-only.
                'payout'      => $isForeman ? 'Foreman Commission' : $payout,
                'is_foreman'  => $isForeman,
            ];
        }

        $filename = 'payout-schedule-template';
        if (! empty($data['name'])) {
            $filename .= '-' . \Illuminate\Support\Str::slug($data['name']);
        }

        return Pdf::loadView('admin.chit.schemes.payout-template-pdf', [
            'scheme'         => $data,
            'rows'           => $rows,
            'generatedAt'    => now(),
            'reportBranding' => app(ReportBrandingService::class)->get(),
        ])->setPaper('a4', 'landscape')->download($filename . '.pdf');
    }

    public function show(ChitScheme $scheme)
    {
        $scheme->load(['branch', 'chitGroups']);
        $scheme->loadCount(['chitGroups' => fn ($q) => $q->withTrashed()]);
        $mode = 'show';
        return view('admin.chit.schemes.index', compact('scheme', 'mode'));
    }

    public function flyer($scheme)
    {
        $scheme = $this->resolveFlyerScheme($scheme);

        if (empty($scheme->payout_schedule) && request()->routeIs('chit.schemes.flyer')) {
            return redirect()
                ->route('chit.schemes.show', $scheme)
                ->with('error', 'This scheme has no payout schedule to generate a flyer.');
        }

        $rowCount = count($scheme->payout_schedule ?? []);
        $compact = $rowCount > 15;

        if (request()->routeIs('public.schemes.flyer.pdf') || request()->boolean('download')) {
            return $this->streamOfficialFlyerPdf($scheme);
        }

        return view('admin.chit.schemes.flyer-print', compact('scheme', 'compact', 'rowCount'));
    }

    public function flyerPdf($scheme)
    {
        return $this->streamOfficialFlyerPdf($this->resolveFlyerScheme($scheme));
    }

    protected function resolveFlyerScheme($scheme): ChitScheme
    {
        if ($scheme instanceof ChitScheme) {
            return $scheme;
        }

        $value = preg_replace('/\.pdf$/i', '', (string) $scheme);
        $id = \App\Support\HashId::decode((string) $value);
        if ($id === null && ctype_digit((string) $value)) {
            $id = (int) $value;
        }

        abort_if(! $id, 404, 'Scheme flyer not found.');

        return ChitScheme::query()->findOrFail($id);
    }

    protected function streamOfficialFlyerPdf(ChitScheme $scheme)
    {
        $foremanMonth = $scheme->foremanCommissionMonth();
        $installmentAmount = (float) $scheme->installment_amount;
        $chitValue = (float) $scheme->chit_value;
        $schedule = $scheme->payout_schedule ?? [];
        $rows = [];

        if ($schedule) {
            foreach (array_values($schedule) as $index => $row) {
                $month = (int) ($row['installment_no'] ?? ($index + 1));
                $isForeman = $foremanMonth > 0 && $month === $foremanMonth;
                $installment = $row['installment_amount'] ?? $installmentAmount;
                $installment = ($installment === null || trim((string) $installment) === '')
                    ? $installmentAmount
                    : (float) $installment;

                if ($isForeman && $installmentAmount > 0 && $chitValue > 0 && $installment > $chitValue * 0.9) {
                    $installment = $installmentAmount;
                }

                $payout = $row['payout_amount'] ?? null;
                $rows[] = [
                    'month' => $month,
                    'installment' => $installment > 0 ? $installment : null,
                    'payout' => $isForeman ? 'Foreman Commission' : $payout,
                    'is_foreman' => $isForeman,
                ];
            }
        } else {
            $duration = max(1, (int) $scheme->duration_months);
            for ($month = 1; $month <= $duration; $month++) {
                $isForeman = $foremanMonth > 0 && $month === $foremanMonth;
                $rows[] = [
                    'month' => $month,
                    'installment' => $installmentAmount > 0 ? $installmentAmount : null,
                    'payout' => $isForeman ? 'Foreman Commission' : null,
                    'is_foreman' => $isForeman,
                ];
            }
        }

        $filename = 'official-scheme-flyer-' . \Illuminate\Support\Str::slug((string) $scheme->name) . '.pdf';

        return Pdf::loadView('admin.chit.schemes.official-flyer-pdf', [
            'scheme' => [
                'name' => $scheme->name,
                'scheme_code' => $scheme->scheme_code,
                'chit_value' => $scheme->chit_value,
                'total_members' => $scheme->total_members,
                'duration_months' => $scheme->duration_months,
                'installment_frequency' => $scheme->installment_frequency ?? 'monthly',
                'commission_pct' => $scheme->commission_pct,
                'installment_amount' => $scheme->installment_amount,
                'foreman_commission_month' => $foremanMonth,
            ],
            'rows' => $rows,
            'generatedAt' => now(),
            'reportBranding' => app(ReportBrandingService::class)->get(),
        ])->setPaper('a4', 'portrait')->stream($filename, ['Attachment' => false]);
    }

    public function edit(ChitScheme $scheme)
    {
        $branches    = Branch::all();
        $schemeTypes = ChitScheme::schemeTypes();
        $frequencies = ChitScheme::frequencies();
        $mode        = 'edit';
        return view('admin.chit.schemes.index', compact('scheme', 'branches', 'schemeTypes', 'frequencies', 'mode'));
    }

    public function update(Request $request, ChitScheme $scheme)
    {
        $validated = $this->validateScheme($request, updating: true);
        $validated = $this->normalizeSchemePayload($validated);

        $scheme->update($validated);

        if ($scheme->wasChanged([
            'foreman_commission_month',
            'client_wise_foreman_commission',
            'client_wise_foreman_collection_month',
        ])) {
            $scheme->chitGroups()->each(function (ChitGroup $group) {
                $group->resyncUnpaidInstallmentAmounts();
            });
        }

        return redirect()->route('chit.schemes.index')->with('success', 'Scheme updated successfully!');
    }

    public function destroy(ChitScheme $scheme)
    {
        if ($scheme->hasDependentGroups()) {
            return back()->with('error', 'Cannot delete this scheme because dependent group records exist.');
        }

        $scheme->delete();
        return redirect()->route('chit.schemes.index')->with('success', 'Scheme moved to recycle bin. You can restore it from the Recycle Bin.');
    }

    public function restore($id)
    {
        $scheme = ChitScheme::onlyTrashed()->findOrFail($id);
        $scheme->restore();

        return redirect()->route('chit.schemes.index', ['trash' => 'true'])->with('success', 'Scheme restored successfully.');
    }

    public function forceDelete($id)
    {
        $scheme = ChitScheme::onlyTrashed()->findOrFail($id);

        if ($scheme->chitGroups()->withTrashed()->exists()) {
            return back()->with('error', 'Cannot permanently delete scheme because linked groups exist.');
        }

        if ($scheme->registration_certificate_path && Storage::disk('public')->exists($scheme->registration_certificate_path)) {
            Storage::disk('public')->delete($scheme->registration_certificate_path);
        }

        $scheme->forceDelete();

        return redirect()->route('chit.schemes.index', ['trash' => 'true'])->with('success', 'Scheme permanently deleted.');
    }
    public function toggleStatus(ChitScheme $scheme)
    {
        $scheme->status = $scheme->status === 'active' ? 'inactive' : 'active';
        $scheme->save();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Scheme status updated successfully!',
                'status'  => $scheme->status,
            ]);
        }

        return back()->with('success', 'Scheme status updated successfully!');
    }


    private function validateScheme(Request $request, bool $updating = false): array
    {
        $duration = (int) $request->input('duration_months', 1);
        $isAuction = $request->input('scheme_type') === 'auction';

        $rules = [
            'name'                 => 'required|string|max:100',
            'scheme_type'          => 'required|in:fixed,auction,flexible,fixed_return,daily_weekly,group_based',
            'chit_value'           => 'required|numeric|min:1000',
            // Blank is allowed: it is resolved from the payout schedule when normalising.
            'installment_amount'   => 'nullable|numeric|min:0',
            'total_members'        => 'required|integer|min:2|max:100',
            'duration_months'      => 'required|integer|min:1|max:120',
            'foreman_commission_month' => ['required', 'integer', 'min:0', 'max:' . max(1, $duration)],
            'client_wise_foreman_commission' => [
                Rule::requiredIf(fn () => (int) $request->input('foreman_commission_month') === 0),
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'client_wise_foreman_collection_month' => [
                Rule::requiredIf(fn () => (int) $request->input('foreman_commission_month') === 0),
                'nullable',
                'integer',
                'min:1',
                'max:' . max(1, $duration),
            ],
            'commission_pct'       => 'required|numeric|min:0|max:30',
            'referral_commission_pct' => 'nullable|numeric|min:0|max:100',
            'auction_type'         => ($isAuction ? 'required' : 'nullable') . '|in:open,closed',
            'installment_frequency'=> 'required|in:monthly,weekly,daily',
            'fixed_return_amount'  => 'nullable|numeric|min:0',
            'post_payout_installment_adjustment' => 'nullable|numeric|min:0',
            'is_private'           => 'boolean',
            'description'          => 'nullable|string',
            'branch_id'            => 'nullable|exists:branches,id',
            'payout_schedule'      => 'nullable|array',
            'payout_schedule.*.installment_no' => 'required|integer|min:1',
            // Left blank means "use the scheme installment" (filled in when normalising).
            'payout_schedule.*.installment_amount' => 'nullable|numeric|min:0',
            'payout_schedule.*.payout_amount' => 'nullable|string|max:100',
        ];

        if ($updating) {
            $rules['status'] = 'required|in:active,inactive';
        }

        return $request->validate($rules);
    }

    /**
     * Round amounts, clear auction type for non-auction schemes, and force the
     * foreman-commission month to collect the normal per-client installment
     * (never the chit value) with a Foreman Commission payout label.
     */
    private function normalizeSchemePayload(array $validated): array
    {
        $validated['installment_amount'] = round($this->resolveSchemeInstallment($validated), 2);
        $validated['commission_amount']  = round((float) $validated['chit_value'] * (float) $validated['commission_pct'] / 100, 2);
        $validated['is_private']         = (bool) ($validated['is_private'] ?? false);
        $validated['foreman_commission_month'] = (int) ($validated['foreman_commission_month'] ?? 1);
        if ($validated['foreman_commission_month'] === 0) {
            $validated['client_wise_foreman_commission'] = round((float) ($validated['client_wise_foreman_commission'] ?? 0), 2);
            $validated['client_wise_foreman_collection_month'] = (int) ($validated['client_wise_foreman_collection_month'] ?? 0);
        } else {
            $validated['client_wise_foreman_commission'] = null;
            $validated['client_wise_foreman_collection_month'] = null;
        }
        $validated['post_payout_installment_adjustment'] = (isset($validated['post_payout_installment_adjustment']) && trim((string) $validated['post_payout_installment_adjustment']) !== '')
            ? round((float) $validated['post_payout_installment_adjustment'], 2)
            : null;

        if (! in_array($validated['scheme_type'], ['auction', 'flexible'], true)) {
            $validated['auction_type'] = 'open';
        } else {
            $validated['auction_type'] = $validated['auction_type'] ?? 'open';
        }

        if (! empty($validated['payout_schedule']) && is_array($validated['payout_schedule'])) {
            $validated['payout_schedule'] = $this->normalizeForemanSchedule(
                $validated['payout_schedule'],
                (int) $validated['foreman_commission_month'],
                (float) $validated['installment_amount'],
                (float) $validated['chit_value']
            );
        }

        return $validated;
    }

    /**
     * The create form collects installments month-by-month in the payout schedule,
     * so a blank scheme-level amount falls back to the first filled month and then
     * to an even split of the chit value across the term.
     */
    private function resolveSchemeInstallment(array $validated): float
    {
        $amount = $validated['installment_amount'] ?? null;
        if ($amount !== null && trim((string) $amount) !== '' && (float) $amount > 0) {
            return (float) $amount;
        }

        foreach ($validated['payout_schedule'] ?? [] as $row) {
            $rowAmount = $row['installment_amount'] ?? null;
            if ($rowAmount !== null && trim((string) $rowAmount) !== '' && (float) $rowAmount > 0) {
                return (float) $rowAmount;
            }
        }

        $duration = max(1, (int) ($validated['duration_months'] ?? 1));
        $periods = match ($validated['installment_frequency'] ?? 'monthly') {
            'daily' => $duration * 30,
            default => $duration,
        };

        return (float) $validated['chit_value'] / max(1, $periods);
    }

    private function normalizeForemanSchedule(
        array $schedule,
        int $foremanMonth,
        float $installmentAmount,
        float $chitValue
    ): array {
        $normalized = [];

        foreach (array_values($schedule) as $index => $row) {
            $monthNo = (int) ($row['installment_no'] ?? ($index + 1));

            // A blank cell falls back to the scheme's installment amount.
            $rawInstallment = $row['installment_amount'] ?? null;
            $rowInstallment = ($rawInstallment === null || trim((string) $rawInstallment) === '')
                ? round($installmentAmount, 2)
                : round((float) $rawInstallment, 2);

            // Foreman month: clients pay the normal installment; collection is company profit.
            if ($monthNo === $foremanMonth) {
                if ($rowInstallment <= 0
                    || abs($rowInstallment - $chitValue) < 0.01
                    || ($chitValue > 0 && $rowInstallment > $chitValue * 0.9)
                ) {
                    $rowInstallment = $installmentAmount;
                }

                $row['installment_amount'] = $rowInstallment;
                $row['payout_amount'] = ChitScheme::FOREMAN_COMMISSION_LABEL;
            } else {
                $row['installment_amount'] = $rowInstallment;
            }

            $row['installment_no'] = $monthNo;
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function storeRegistrationCertificate($file, ?string $oldPath = null): string
    {
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $directory = 'chit/registration_certificates';
        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $filename = time() . '_certificate_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());

        return $file->storeAs($directory, $filename, 'public');
    }

}
