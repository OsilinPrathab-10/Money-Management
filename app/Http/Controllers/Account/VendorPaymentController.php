<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateVendorPayment;
use App\Events\Account\DestroyVendorPayment;
use App\Events\Account\UpdateVendorPaymentStatus;
use App\Http\Requests\Account\StoreVendorPaymentRequest;
use App\Models\Account\BankAccount;
use App\Models\Account\DebitNote;
use App\Models\Account\DebitNoteApplication;
use App\Models\Account\VendorPayment;
use App\Models\Account\VendorPaymentAllocation;
use App\Services\Account\AccountExportService;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\JournalService;
use App\Models\User;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class VendorPaymentController extends Controller
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-vendor-payments')){
            $query = VendorPayment::with(['vendor', 'bankAccount', 'allocations.invoice', 'debitNoteApplications.debitNote'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-vendor-payments')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-vendor-payments')) {
                        $q->where('creator_id', Auth::id())->orWhere('vendor_id',Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            // Apply filters
            if ($request->vendor_id) {
                $query->where('vendor_id', $request->vendor_id);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->search) {
                $query->where('payment_number', 'like', '%' . $request->search . '%');
            }
            if ($request->date_from) {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }
            if ($request->date_to) {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }
            if ($request->bank_account_id) {
                $query->where('bank_account_id', $request->bank_account_id);
            }

            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $payments = $query->paginate($request->get('per_page', 20));
            $vendors = User::query()->whereRaw('1 = 0')->get();

            $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Vendor payments'),
                'payload' => [
                    'payments' => $payments,
                    'vendors' => $vendors,
                    'bankAccounts' => $bankAccounts,
                    'filters' => $request->only(['vendor_id', 'status', 'search', 'date_from', 'date_to', 'bank_account_id', 'sort', 'direction']),
                ],
                'exportRoute' => 'account.vendor-payments.export',
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (!Auth::user()->can('manage-vendor-payments')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'vendor_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'bank_account_id' => 'nullable|integer',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = VendorPayment::with(['vendor', 'bankAccount'])
            ->where(function($q) {
                if(Auth::user()->can('manage-any-vendor-payments')) {
                    $q->where('created_by', creatorId());
                } elseif(Auth::user()->can('manage-own-vendor-payments')) {
                    $q->where('creator_id', Auth::id())->orWhere('vendor_id',Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($validated['vendor_id'] ?? null) {
            $query->where('vendor_id', (int) $validated['vendor_id']);
        }
        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['search'])) {
            $query->where('payment_number', 'like', '%' . $validated['search'] . '%');
        }
        if (!empty($validated['date_from'])) {
            $query->whereDate('payment_date', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('payment_date', '<=', $validated['date_to']);
        }
        if ($validated['bank_account_id'] ?? null) {
            $query->where('bank_account_id', (int) $validated['bank_account_id']);
        }

        $sortField = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $payments = $query->get();
        $totalAmount = (float) ($payments->sum('payment_amount') ?? 0);

        $rows = $payments->map(function ($p) {
            return [
                'number' => $p->payment_number,
                'date' => $p->payment_date?->format('Y-m-d') ?? '',
                'vendor' => $p->vendor?->company_name ?? $p->vendor?->name ?? '—',
                'bank' => $p->bankAccount?->account_name ?? '—',
                'reference' => $p->reference_number ?? '—',
                'amount' => '₹' . number_format((float) ($p->payment_amount ?? 0), 2),
                'status' => $p->status ?? '—',
            ];
        })->values()->all();

        $rows[] = [
            'number' => __('TOTAL'),
            'date' => '',
            'vendor' => '',
            'bank' => '',
            'reference' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
            'status' => '',
        ];

        $columns = [
            ['key' => 'number', 'label' => __('Payment #')],
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'vendor', 'label' => __('Vendor')],
            ['key' => 'bank', 'label' => __('Bank')],
            ['key' => 'reference', 'label' => __('Reference')],
            ['key' => 'amount', 'label' => __('Amount'), 'class' => 'text-end'],
            ['key' => 'status', 'label' => __('Status')],
        ];

        $subtitleParts = [];
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }
        if (!empty($validated['status'])) {
            $subtitleParts[] = 'Status: ' . $validated['status'];
        }
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $subtitleParts[] = $validated['date_from'] . ' → ' . $validated['date_to'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Vendor payments'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'vendor-payments-export'
        );
    }

    public function store(StoreVendorPaymentRequest $request)
    {
        if(Auth::user()->can('create-vendor-payments')){
            // Validate that at least one invoice allocation exists
            if (!$request->allocations || count($request->allocations) === 0) {
                return back()->with('error', __('At least one invoice allocation is required to create a payment.'));
            }

            // Validate debit note amount doesn't exceed invoice allocation amount
            if ($request->debit_notes) {
                $totalInvoiceAmount = collect($request->allocations)->sum('amount');
                $totalDebitNoteAmount = collect($request->debit_notes)->sum('amount');

                if ($totalDebitNoteAmount > $totalInvoiceAmount) {
                    return back()->with('error', __('Debit note amount cannot exceed the total invoice allocation amount.'));
                }
            }

            // Create payment
            $payment = new VendorPayment();
            $payment->payment_date = $request->payment_date;
            $payment->vendor_id = $request->vendor_id;
            $payment->bank_account_id = $request->bank_account_id;
            $payment->reference_number = $request->reference_number;
            $payment->payment_amount = $request->payment_amount;
            $payment->notes = $request->notes;
            $payment->creator_id = Auth::id();
            $payment->created_by = creatorId();
            $payment->save();

            // Create allocations if provided
            if ($request->allocations) {
                foreach ($request->allocations as $allocation) {
                    $paymentAllocation = new VendorPaymentAllocation();
                    $paymentAllocation->payment_id = $payment->id;
                    $paymentAllocation->invoice_id = $allocation['invoice_id'];
                    $paymentAllocation->allocated_amount = $allocation['amount'];
                    $paymentAllocation->save();
                }
            }

            // Handle debit notes if provided
            if ($request->debit_notes) {
                foreach ($request->debit_notes as $debitNote) {
                    $debitNoteModel = DebitNote::find($debitNote['debit_note_id']);
                    if (!$debitNoteModel) continue;

                    // Create debit note application entry
                    DebitNoteApplication::create([
                        'debit_note_id' => $debitNote['debit_note_id'],
                        'payment_id' => $payment->id,
                        'applied_amount' => $debitNote['amount'],
                        'application_date' => $request->payment_date,
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId()
                    ]);
                }
            }

            // Dispatch event
            CreateVendorPayment::dispatch($request, $payment);

            return redirect()->route('account.vendor-payments.index')->with('success', __('The vendor payment has been created successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function getOutstandingInvoices($vendorId)
    {
        $invoices = PurchaseInvoice::where('vendor_id', $vendorId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['posted', 'partial'])
            ->where('created_by', creatorId())
            ->get();

        $debitNotes = \App\Models\Account\DebitNote::where('vendor_id', $vendorId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['approved', 'partial'])
            ->where('created_by', creatorId())
            ->get(['id', 'debit_note_number', 'balance_amount', 'total_amount', 'status']);

        return response()->json([
            'invoices' => $invoices,
            'debitNotes' => $debitNotes
        ]);
    }

    public function updateStatus(Request $request, VendorPayment $vendorPayment)
    {
        if(Auth::user()->can('cleared-vendor-payments') && $vendorPayment->created_by == creatorId()){
            try {
                // Create journal entry and update invoices when payment is cleared
                if($request->status === 'cleared') {
                    if($vendorPayment->payment_amount > 0)
                    {
                        $this->journalService->createVendorPaymentJournal($vendorPayment);
                        $this->bankTransactionsService->createVendorPayment($vendorPayment);
                    }
                    // Update invoice balances
                    foreach ($vendorPayment->allocations as $allocation) {
                        $invoice = $allocation->invoice;
                        $invoice->paid_amount += $allocation->allocated_amount;
                        $invoice->balance_amount = $invoice->total_amount - $invoice->paid_amount;

                        if ($invoice->balance_amount == 0) {
                            $invoice->status = 'paid';
                        } elseif ($invoice->paid_amount > 0) {
                            $invoice->status = 'partial';
                        }
                        $invoice->save();
                    }
                }

                $debitNoteApplication = DebitNoteApplication::where('payment_id', $vendorPayment->id)->get();

                foreach ($debitNoteApplication as $debitNote) {
                    $debitNoteModel = DebitNote::find($debitNote['debit_note_id']);
                    $debitNoteModel->applied_amount += $debitNote['applied_amount'];
                    $debitNoteModel->balance_amount = $debitNoteModel->total_amount - $debitNoteModel->applied_amount;
                    $debitNoteModel->status = $debitNoteModel->balance_amount <= 0 ? 'applied' : 'partial';
                    $debitNoteModel->save();
                }

                $vendorPayment->update(['status' => $request->status]);

                // Dispatch event
                UpdateVendorPaymentStatus::dispatch($request, $vendorPayment);

                return back()->with('success', __('The payment status are updated successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(VendorPayment $vendorPayment)
    {
        if(Auth::user()->can('delete-vendor-payments') && $vendorPayment->created_by == creatorId() && $vendorPayment->status === 'pending'){

            // Dispatch event before deletion
            DestroyVendorPayment::dispatch($vendorPayment);

            $vendorPayment->delete();
            return back()->with('success', __('The vendor payment has been deleted.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}

