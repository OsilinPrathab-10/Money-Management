<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\ApproveDebitNote;
use App\Events\Account\DestroyDebitNote;
use App\Models\Account\DebitNote;
use App\Services\Account\AccountExportService;
use App\Services\Account\JournalService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DebitNoteController extends Controller
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    private function checkDebitNoteAccess(DebitNote $debitNote)
    {
        if(Auth::user()->can('manage-any-debit-notes')) {
            return true;
        } elseif(Auth::user()->can('manage-own-debit-notes')) {
            if($debitNote->creator_id != Auth::id() && $debitNote->vendor_id != Auth::id()) {
                return false;
            }
            return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-debit-notes')){
            $query = DebitNote::with(['vendor', 'purchaseReturn', 'approvedBy'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-debit-notes')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-debit-notes')) {
                        $q->where('creator_id', Auth::id())->orWhere('vendor_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            if ($request->vendor_id) {
                $query->where('vendor_id', $request->vendor_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where('debit_note_number', 'like', '%' . $request->search . '%');
            }

            if ($request->purchase_return_id) {
                $query->where('return_id', $request->purchase_return_id);
            }

            if ($request->sort) {
                $query->orderBy($request->sort, $request->direction ?? 'asc');
            } else {
                $query->orderBy('debit_note_date', 'desc');
            }

            $debitNotes = $query->paginate($request->per_page ?? 20)->withQueryString();

            $vendors = User::query()->whereRaw('1 = 0')->get(['id', 'name']);
            $purchaseReturns = class_exists(\App\Models\PurchaseReturn::class)
                ? \App\Models\PurchaseReturn::where('created_by', creatorId())->get(['id', 'return_number'])
                : collect();

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Debit notes'),
                'payload' => [
                    'debitNotes' => $debitNotes,
                    'vendors' => $vendors,
                    'purchaseReturns' => $purchaseReturns,
                    'filters' => $request->only(['vendor_id', 'status', 'search', 'purchase_return_id', 'sort', 'direction']),
                ],
                'exportRoute' => 'account.debit-notes.export',
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (!Auth::user()->can('manage-debit-notes')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'vendor_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'purchase_return_id' => 'nullable|integer',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = DebitNote::with(['vendor'])
            ->where(function($q) {
                if(Auth::user()->can('manage-any-debit-notes')) {
                    $q->where('created_by', creatorId());
                } elseif(Auth::user()->can('manage-own-debit-notes')) {
                    $q->where('creator_id', Auth::id())->orWhere('vendor_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if (!empty($validated['vendor_id'])) {
            $query->where('vendor_id', (int) $validated['vendor_id']);
        }
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['search'])) {
            $query->where('debit_note_number', 'like', '%' . $validated['search'] . '%');
        }
        if (!empty($validated['purchase_return_id'])) {
            $query->where('return_id', (int) $validated['purchase_return_id']);
        }

        if (!empty($validated['sort'])) {
            $query->orderBy($validated['sort'], $validated['direction'] ?? 'asc');
        } else {
            $query->orderBy('debit_note_date', 'desc');
        }

        $debitNotes = $query->get();
        $totalAmount = (float) ($debitNotes->sum('total_amount') ?? 0);

        $rows = $debitNotes->map(function ($n) {
            return [
                'number' => $n->debit_note_number,
                'date' => $n->debit_note_date?->format('Y-m-d') ?? '',
                'vendor' => $n->vendor?->company_name ?? $n->vendor?->name ?? '—',
                'status' => $n->status ?? '—',
                'amount' => '₹' . number_format((float) ($n->total_amount ?? 0), 2),
            ];
        })->values()->all();

        $rows[] = [
            'number' => __('TOTAL'),
            'date' => '',
            'vendor' => '',
            'status' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
        ];

        $columns = [
            ['key' => 'number', 'label' => __('Number')],
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'vendor', 'label' => __('Vendor')],
            ['key' => 'status', 'label' => __('Status')],
            ['key' => 'amount', 'label' => __('Amount'), 'class' => 'text-end'],
        ];

        $subtitleParts = [];
        if (!empty($validated['vendor_id'])) {
            $subtitleParts[] = 'Vendor ID: ' . $validated['vendor_id'];
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
                'pageTitle' => __('Debit notes'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'debit-notes-export'
        );
    }

    public function show(DebitNote $debitNote)
    {
        if(Auth::user()->can('view-debit-notes') &&
           (Auth::user()->type == 'vendor' ? $debitNote->vendor_id == Auth::id() : $debitNote->created_by == creatorId())){
            if(!$this->checkDebitNoteAccess($debitNote)) {
                return redirect()->route('account.debit-notes.index')->with('error', __('Permission denied'));
            }

            $debitNote->load(['vendor', 'items.taxes', 'purchaseReturn', 'applications.payment']);

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Debit note'),
                'payload' => ['debitNote' => $debitNote],
            ]);
        }
        else{
            return redirect()->route('account.debit-notes.index')->with('error', __('Permission denied'));
        }
    }

    public function approve(DebitNote $debitNote)
    {
        if(Auth::user()->can('approve-debit-notes')){
            if ($debitNote->status !== 'draft') {
                return back()->with('error', __('Only draft debit notes can be approved.'));
            }
            try {
                // Create journal entries
                $this->journalService->createDebitNoteJournal($debitNote);

                $debitNote->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id()
                ]);
                ApproveDebitNote::dispatch($debitNote);

                return back()->with('success', __('Debit note approved successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(DebitNote $debitNote)
    {
        if(Auth::user()->can('delete-debit-notes')){
            if ($debitNote->status !== 'draft') {
                return back()->with('error', __('Only draft debit notes can be deleted.'));
            }

            DestroyDebitNote::dispatch($debitNote);

            $debitNote->delete();
            return back()->with('success', __('Debit note deleted successfully.'));
        }
        else {
            return back()->with('error', __('Permission denied'));
        }
    }
}

