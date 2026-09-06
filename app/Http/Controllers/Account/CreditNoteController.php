<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\ApproveCreditNote;
use App\Events\Account\DestroyCreditNote;
use App\Models\Account\CreditNote;
use App\Services\Account\AccountExportService;
use App\Services\Account\JournalService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CreditNoteController extends Controller
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    private function checkCreditNoteAccess(CreditNote $creditNote)
    {
        if(Auth::user()->can('manage-any-credit-notes')) {
            return true;
        } elseif(Auth::user()->can('manage-own-credit-notes')) {
            if($creditNote->creator_id != Auth::id() && $creditNote->customer_id != Auth::id()) {
                return false;
            }
            if($creditNote->creator_id != Auth::id() && Auth::user()->type == 'client' && $creditNote->status == 'draft') {
                return false;
            }
            return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-credit-notes')){
            $query = CreditNote::with(['customer', 'salesReturn', 'approvedBy'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-credit-notes')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-credit-notes')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                        if(Auth::user()->type == 'client') {
                            $q->where('status','!=', 'draft');
                        }
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where('credit_note_number', 'like', '%' . $request->search . '%');
            }

            if ($request->sales_return_id) {
                $query->where('return_id', $request->sales_return_id);
            }

            if ($request->sort) {
                $query->orderBy($request->sort, $request->direction ?? 'asc');
            } else {
                $query->orderBy('credit_note_date', 'desc');
            }

            $creditNotes = $query->paginate($request->per_page ?? 20)->withQueryString();

            $customers = User::query()->whereRaw('1 = 0')->get(['id', 'name']);
            $salesReturns = class_exists(\App\Models\SalesInvoiceReturn::class)
                ? \App\Models\SalesInvoiceReturn::where('created_by', creatorId())->get(['id', 'return_number'])
                : collect();

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Credit notes'),
                'payload' => [
                    'creditNotes' => $creditNotes,
                    'customers' => $customers,
                    'salesReturns' => $salesReturns,
                    'filters' => $request->only(['customer_id', 'status', 'search', 'sales_return_id', 'sort', 'direction']),
                ],
                'exportRoute' => 'account.credit-notes.export',
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (!Auth::user()->can('manage-credit-notes')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'customer_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'sales_return_id' => 'nullable|integer',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = CreditNote::with(['customer'])
            ->where(function($q) {
                if(Auth::user()->can('manage-any-credit-notes')) {
                    $q->where('created_by', creatorId());
                } elseif(Auth::user()->can('manage-own-credit-notes')) {
                    $q->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                    if(Auth::user()->type == 'client') {
                        $q->where('status','!=', 'draft');
                    }
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if (!empty($validated['customer_id'])) {
            $query->where('customer_id', (int) $validated['customer_id']);
        }
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['search'])) {
            $query->where('credit_note_number', 'like', '%' . $validated['search'] . '%');
        }
        if (!empty($validated['sales_return_id'])) {
            $query->where('return_id', (int) $validated['sales_return_id']);
        }

        if (!empty($validated['sort'])) {
            $query->orderBy($validated['sort'], $validated['direction'] ?? 'asc');
        } else {
            $query->orderBy('credit_note_date', 'desc');
        }

        $creditNotes = $query->get();
        $totalAmount = (float) ($creditNotes->sum('total_amount') ?? 0);

        $rows = $creditNotes->map(function ($n) {
            return [
                'number' => $n->credit_note_number,
                'date' => $n->credit_note_date?->format('Y-m-d') ?? '',
                'customer' => $n->customer?->company_name ?? $n->customer?->name ?? '—',
                'status' => $n->status ?? '—',
                'amount' => '₹' . number_format((float) ($n->total_amount ?? 0), 2),
            ];
        })->values()->all();

        $rows[] = [
            'number' => __('TOTAL'),
            'date' => '',
            'customer' => '',
            'status' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
        ];

        $columns = [
            ['key' => 'number', 'label' => __('Number')],
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'customer', 'label' => __('Customer')],
            ['key' => 'status', 'label' => __('Status')],
            ['key' => 'amount', 'label' => __('Amount'), 'class' => 'text-end'],
        ];

        $subtitleParts = [];
        if (!empty($validated['customer_id'])) {
            $subtitleParts[] = 'Customer ID: ' . $validated['customer_id'];
        }
        if (!empty($validated['status'])) {
            $subtitleParts[] = 'Status: ' . $validated['status'];
        }
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Credit notes'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'credit-notes-export'
        );
    }

    public function show(CreditNote $creditNote)
    {
        if(Auth::user()->can('view-credit-notes') && $creditNote->created_by == creatorId()){
            if(!$this->checkCreditNoteAccess($creditNote)) {
                return redirect()->route('account.credit-notes.index')->with('error', __('Permission denied'));
            }

            $creditNote->load(['customer', 'items.taxes', 'salesReturn', 'applications.payment']);

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Credit note'),
                'payload' => ['creditNote' => $creditNote],
            ]);
        }
        else{
            return redirect()->route('account.credit-notes.index')->with('error', __('Permission denied'));
        }
    }

    public function approve(CreditNote $creditNote)
    {
        if(Auth::user()->can('approve-credit-notes')){
            if ($creditNote->status !== 'draft') {
                return back()->with('error', __('Only draft credit notes can be approved.'));
            }
            try {
                // Create journal entries
                $this->journalService->createCreditNoteJournal($creditNote);
                $this->journalService->createCreditNoteCOGSJournal($creditNote);

                $creditNote->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id()
                ]);
                ApproveCreditNote::dispatch($creditNote);

                return back()->with('success', __('Credit note approved successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(CreditNote $creditNote)
    {
        if(Auth::user()->can('delete-credit-notes')){
            if ($creditNote->status !== 'draft') {
                return back()->with('error', __('Only draft credit notes can be deleted.'));
            }

            DestroyCreditNote::dispatch($creditNote);

            $creditNote->delete();
            return back()->with('success', __('Credit note deleted successfully.'));
        }
        else {
            return back()->with('error', __('Permission denied'));
        }
    }
}

