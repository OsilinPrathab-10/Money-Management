<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateBankTransfer;
use App\Events\Account\DestroyBankTransfer;
use App\Events\Account\ProcessBankTransfer;
use App\Events\Account\UpdateBankTransfer;
use App\Http\Requests\Account\StoreBankTransferRequest;
use App\Http\Requests\Account\UpdateBankTransferRequest;
use App\Models\Account\BankAccount;
use App\Models\Account\BankTransfer;
use App\Services\Account\AccountExportService;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\JournalService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BankTransferController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-bank-transfers')){
            $banktransfers = BankTransfer::query()
                ->with(['fromAccount', 'toAccount'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-bank-transfers')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-bank-transfers')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('transfer_number'), function($q) {
                    $q->where(function($query) {
                        $query->where('transfer_number', 'like', '%' . request('transfer_number') . '%')
                              ->orWhere('reference_number', 'like', '%' . request('transfer_number') . '%');
                    });
                })
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))
                ->when(request('from_account_id'), fn($q) => $q->where('from_account_id', request('from_account_id')))
                ->when(request('to_account_id'), fn($q) => $q->where('to_account_id', request('to_account_id')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 20))
                ->withQueryString();

            $bankaccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->select('id', 'account_name', 'current_balance')->get();

            return view('admin.account.bank-transfers.index', [
                'banktransfers' => $banktransfers,
                'bankaccounts' => $bankaccounts,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreBankTransferRequest $request)
    {
        if(Auth::user()->can('create-bank-transfers')){
            $validated = $request->validated();

            // Validate sufficient balance
            $fromAccount = BankAccount::find($validated['from_account_id']);
            $totalAmount = $validated['transfer_amount'] + ($validated['transfer_charges'] ?? 0);

            if ($fromAccount->current_balance < $totalAmount) {
                return back()->with('error', __('Insufficient balance in source account'));
            }

            $banktransfer = new BankTransfer();
            $banktransfer->transfer_number = BankTransfer::generateTransferNumber();
            $banktransfer->transfer_date = $validated['transfer_date'];
            $banktransfer->from_account_id = $validated['from_account_id'];
            $banktransfer->to_account_id = $validated['to_account_id'];
            $banktransfer->transfer_amount = $validated['transfer_amount'];
            $banktransfer->transfer_charges = $validated['transfer_charges'] ?? 0;
            $banktransfer->reference_number = $validated['reference_number'];
            $banktransfer->description = $validated['description'];
            $banktransfer->status = 'pending';
            $banktransfer->creator_id = Auth::id();
            $banktransfer->created_by = creatorId();
            $banktransfer->save();

            CreateBankTransfer::dispatch($request, $banktransfer);

            return redirect()->route('account.bank-transfers.index')->with('success', __('The bank transfer has been created successfully.'));
        }
        else{
            return redirect()->route('account.bank-transfers.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateBankTransferRequest $request, BankTransfer $banktransfer)
    {
        if(Auth::user()->can('edit-bank-transfers')){
            if($banktransfer->status !== 'pending') {
                return back()->with('error', __('Only pending transfers can be edited'));
            }

            $validated = $request->validated();

            // Validate sufficient balance
            $fromAccount = BankAccount::find($validated['from_account_id']);
            $totalAmount = $validated['transfer_amount'] + ($validated['transfer_charges'] ?? 0);

            if ($fromAccount->current_balance < $totalAmount) {
                return back()->with('error', __('Insufficient balance in source account'));
            }

            $banktransfer->transfer_date = $validated['transfer_date'];
            $banktransfer->from_account_id = $validated['from_account_id'];
            $banktransfer->to_account_id = $validated['to_account_id'];
            $banktransfer->transfer_amount = $validated['transfer_amount'];
            $banktransfer->transfer_charges = $validated['transfer_charges'] ?? 0;
            $banktransfer->reference_number = $validated['reference_number'];
            $banktransfer->description = $validated['description'];
            $banktransfer->save();

            UpdateBankTransfer::dispatch($request, $banktransfer);

            return redirect()->back()->with('success', __('The bank transfer details are updated successfully.'));
        }
        else{
            return redirect()->route('account.bank-transfers.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(BankTransfer $banktransfer)
    {
        if(Auth::user()->can('delete-bank-transfers')){
            if($banktransfer->status !== 'pending') {
                return back()->with('error', __('Only pending transfers can be deleted'));
            }

            DestroyBankTransfer::dispatch($banktransfer);
            $banktransfer->delete();

            return redirect()->back()->with('success', __('The bank transfer has been deleted.'));
        }
        else{
            return redirect()->route('account.bank-transfers.index')->with('error', __('Permission denied'));
        }
    }

    public function process(BankTransfer $banktransfer)
    {
        if(Auth::user()->can('process-bank-transfers')){
            if($banktransfer->status !== 'pending') {
                return back()->with('error', __('Transfer is not in pending status'));
            }

            try {

                // Create dual bank transactions
                $bankTransactionsService = new BankTransactionsService();
                $bankTransactionsService->createTransferBankTransactions($banktransfer);

                // Create journal entries
                $journalService = new JournalService();
                $journalEntry = $journalService->createBankTransferJournal($banktransfer);

                // Update bank account balances
                $bankTransactionsService->updateBankBalance($banktransfer->from_account_id, -$banktransfer->total_debit);
                $bankTransactionsService->updateBankBalance($banktransfer->to_account_id, $banktransfer->transfer_amount);

                // Update status to completed
                $banktransfer->status = 'completed';
                $banktransfer->journal_entry_id = $journalEntry->id;
                $banktransfer->save();

                ProcessBankTransfer::dispatch($banktransfer);

                return back()->with('success', __('Bank transfer processed successfully'));
            } catch (\Exception $e) {
                // Update status to failed
                $banktransfer->status = 'failed';
                $banktransfer->save();

                return back()->with('error', __('Error processing transfer: ') . $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-bank-transfers')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'transfer_number' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'from_account_id' => 'nullable|integer',
            'to_account_id' => 'nullable|integer',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = BankTransfer::query()
            ->with(['fromAccount', 'toAccount'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-bank-transfers')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-bank-transfers')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($validated['transfer_number']), function ($q) use ($validated) {
                $term = (string) $validated['transfer_number'];
                $q->where(function ($qq) use ($term) {
                    $qq->where('transfer_number', 'like', '%' . $term . '%')
                        ->orWhere('reference_number', 'like', '%' . $term . '%');
                });
            })
            ->when(isset($validated['status']) && $validated['status'] !== '', fn($q) => $q->where('status', $validated['status']))
            ->when(!empty($validated['from_account_id']), fn($q) => $q->where('from_account_id', (int) $validated['from_account_id']))
            ->when(!empty($validated['to_account_id']), fn($q) => $q->where('to_account_id', (int) $validated['to_account_id']));

        $sortField = $validated['sort'] ?? 'id';
        $sortDirection = $validated['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $banktransfers = $query->get();
        $totalAmount = (float) ($banktransfers->sum('transfer_amount') ?? 0);

        $rows = $banktransfers->map(function ($tr) {
            return [
                'number' => $tr->transfer_number,
                'date' => $tr->transfer_date?->format('Y-m-d') ?? '',
                'from' => $tr->fromAccount?->account_name ?? '—',
                'to' => $tr->toAccount?->account_name ?? '—',
                'amount' => '₹' . number_format((float) ($tr->transfer_amount ?? 0), 2),
                'status' => $tr->status ?? '—',
            ];
        })->values()->all();

        $rows[] = [
            'number' => __('TOTAL'),
            'date' => '',
            'from' => '',
            'to' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
            'status' => '',
        ];

        $columns = [
            ['key' => 'number', 'label' => __('Number')],
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'from', 'label' => __('From')],
            ['key' => 'to', 'label' => __('To')],
            ['key' => 'amount', 'label' => __('Amount'), 'class' => 'text-end'],
            ['key' => 'status', 'label' => __('Status')],
        ];

        $subtitleParts = [];
        if (!empty($validated['transfer_number'])) {
            $subtitleParts[] = 'Search: ' . $validated['transfer_number'];
        }
        if (!empty($validated['status'])) {
            $subtitleParts[] = 'Status: ' . $validated['status'];
        }
        if (!empty($validated['from_account_id'])) {
            $subtitleParts[] = 'From: ' . $validated['from_account_id'];
        }
        if (!empty($validated['to_account_id'])) {
            $subtitleParts[] = 'To: ' . $validated['to_account_id'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Bank transfers'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'bank-transfers-export'
        );
    }
}

