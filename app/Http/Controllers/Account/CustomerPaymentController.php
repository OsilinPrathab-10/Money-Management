<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateCustomerPayment;
use App\Events\Account\DestroyCustomerPayment;
use App\Events\Account\UpdateCustomerPaymentStatus;
use App\Events\Account\UpdateCustomerPaymentStatusListener;
use App\Http\Requests\Account\StoreCustomerPaymentRequest;
use App\Models\Account\BankAccount;
use App\Models\Account\CreditNote;
use App\Models\Account\CreditNoteApplication;
use App\Models\Account\CustomerPayment;
use App\Models\Account\CustomerPaymentAllocation;
use App\Services\Account\AccountExportService;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\JournalService;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CustomerPaymentController extends Controller
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
        if(Auth::user()->can('manage-customer-payments')){
            $query = CustomerPayment::with(['customer', 'bankAccount', 'allocations.invoice', 'creditNoteApplications.creditNote'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-customer-payments')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-customer-payments')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id',Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            // Apply filters
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
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
            $customers = User::query()->whereRaw('1 = 0')->get();

            $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

            return view('admin.account.shared.resource', [
                'pageTitle' => __('Customer payments'),
                'payload' => [
                    'payments' => $payments,
                    'customers' => $customers,
                    'bankAccounts' => $bankAccounts,
                    'filters' => $request->only(['customer_id', 'status', 'search', 'date_from', 'date_to', 'bank_account_id', 'sort', 'direction']),
                ],
                'exportRoute' => 'account.customer-payments.export',
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (!Auth::user()->can('manage-customer-payments')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'customer_id' => 'nullable|integer',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'bank_account_id' => 'nullable|integer',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = CustomerPayment::with(['customer', 'bankAccount'])
            ->where(function($q) {
                if(Auth::user()->can('manage-any-customer-payments')) {
                    $q->where('created_by', creatorId());
                } elseif(Auth::user()->can('manage-own-customer-payments')) {
                    $q->where('creator_id', Auth::id())->orWhere('customer_id',Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($validated['customer_id'] ?? null) {
            $query->where('customer_id', (int) $validated['customer_id']);
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
                'customer' => $p->customer?->company_name ?? $p->customer?->name ?? '—',
                'bank' => $p->bankAccount?->account_name ?? '—',
                'reference' => $p->reference_number ?? '—',
                'amount' => '₹' . number_format((float) ($p->payment_amount ?? 0), 2),
                'status' => $p->status ?? '—',
            ];
        })->values()->all();

        $rows[] = [
            'number' => __('TOTAL'),
            'date' => '',
            'customer' => '',
            'bank' => '',
            'reference' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
            'status' => '',
        ];

        $columns = [
            ['key' => 'number', 'label' => __('Payment #')],
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'customer', 'label' => __('Customer')],
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
                'pageTitle' => __('Customer payments'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'customer-payments-export'
        );
    }

    public function store(StoreCustomerPaymentRequest $request)
    {
        if(Auth::user()->can('create-customer-payments')){
            // Validate that at least one invoice allocation exists
            if (!$request->allocations || count($request->allocations) === 0) {
                return back()->with('error', __('At least one invoice allocation is required to create a payment.'));
            }

            // Validate credit note amount doesn't exceed invoice allocation amount
            if ($request->credit_notes) {
                $totalInvoiceAmount = collect($request->allocations)->sum('amount');
                $totalCreditNoteAmount = collect($request->credit_notes)->sum('amount');

                if ($totalCreditNoteAmount > $totalInvoiceAmount) {
                    return back()->with('error', __('Credit note amount cannot exceed the total invoice allocation amount.'));
                }
            }

            // Create payment
            $payment = new CustomerPayment();
            $payment->payment_date = $request->payment_date;
            $payment->customer_id = $request->customer_id;
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
                    $paymentAllocation = new CustomerPaymentAllocation();
                    $paymentAllocation->payment_id = $payment->id;
                    $paymentAllocation->invoice_id = $allocation['invoice_id'];
                    $paymentAllocation->allocated_amount = $allocation['amount'];
                    $paymentAllocation->save();
                }
            }

            // Handle credit notes if provided
            if ($request->credit_notes) {
                foreach ($request->credit_notes as $creditNote) {
                    $creditNoteModel = CreditNote::find($creditNote['credit_note_id']);
                    if (!$creditNoteModel) continue;

                    // Create credit note application entry
                    CreditNoteApplication::create([
                        'credit_note_id' => $creditNote['credit_note_id'],
                        'payment_id' => $payment->id,
                        'applied_amount' => $creditNote['amount'],
                        'application_date' => $request->payment_date,
                        'creator_id' => Auth::id(),
                        'created_by' => creatorId()
                    ]);
                }
            }

            // Dispatch event
            CreateCustomerPayment::dispatch($request, $payment);

            return redirect()->route('account.customer-payments.index')->with('success', __('The customer payment has been created successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function getOutstandingInvoices($customerId)
    {
        $invoices = SalesInvoice::where('customer_id', $customerId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['posted', 'partial'])
            ->where('created_by', creatorId())
            ->get();

        $creditNotes = CreditNote::where('customer_id', $customerId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['approved', 'partial'])
            ->where('created_by', creatorId())
            ->get(['id', 'credit_note_number', 'balance_amount', 'total_amount', 'status']);

        return response()->json([
            'invoices' => $invoices,
            'creditNotes' => $creditNotes
        ]);
    }

    public function updateStatus(Request $request, CustomerPayment $customerPayment)
    {
        if(Auth::user()->can('cleared-customer-payments') && $customerPayment->created_by == creatorId()){
            try {
                // Create journal entry and update invoices when payment is cleared
                if($request->status === 'cleared') {
                    if($customerPayment->payment_amount > 0)
                    {
                        $this->journalService->createCustomerPaymentJournal($customerPayment);
                        $this->bankTransactionsService->createCustomerPayment($customerPayment);
                    }
                    // Update invoice balances
                    foreach ($customerPayment->allocations as $allocation) {
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

                $creditNoteApplication = CreditNoteApplication::where('payment_id', $customerPayment->id)->get();

                foreach ($creditNoteApplication as $creditNote) {
                    $creditNoteModel = CreditNote::find($creditNote['credit_note_id']);
                    $creditNoteModel->applied_amount += $creditNote['applied_amount'];
                    $creditNoteModel->balance_amount = $creditNoteModel->total_amount - $creditNoteModel->applied_amount;
                    $creditNoteModel->status = $creditNoteModel->balance_amount <= 0 ? 'applied' : 'partial';
                    $creditNoteModel->save();
                }

                $customerPayment->update(['status' => $request->status]);

                 // Dispatch event
                 UpdateCustomerPaymentStatus::dispatch($request, $customerPayment);

                return back()->with('success', __('The payment status are updated successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(CustomerPayment $customerPayment)
    {
        if(Auth::user()->can('delete-customer-payments') && $customerPayment->created_by == creatorId() && $customerPayment->status === 'pending'){

            // Dispatch event before deletion
            DestroyCustomerPayment::dispatch($customerPayment);

            $customerPayment->delete();
            return back()->with('success', __('The customer payment has been deleted.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}

