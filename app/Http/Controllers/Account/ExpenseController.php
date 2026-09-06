<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\ApproveExpense;
use App\Events\Account\CreateExpense;
use App\Events\Account\DestroyExpense;
use App\Events\Account\PostExpense;
use App\Events\Account\UpdateExpense;
use App\Http\Requests\Account\StoreExpenseRequest;
use App\Http\Requests\Account\UpdateExpenseRequest;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\BankAccount;
use App\Models\Account\ChartOfAccount;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\JournalService;
use App\Services\Account\AccountExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
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
        if(Auth::user()->can('manage-expenses')){
            $query = Expense::with(['category:id,category_name', 'bankAccount:id,account_name', 'chartOfAccount:id,account_code,account_name', 'approvedBy:id,name'])
                ->select('id', 'expense_number', 'expense_date', 'category_id', 'bank_account_id', 'chart_of_account_id', 'amount', 'description', 'reference_number', 'status', 'approved_by', 'created_at')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-expenses')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-expenses')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            if ($request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('expense_number', 'like', '%' . $searchTerm . '%')
                      ->orWhereHas('category', function($subQ) use ($searchTerm) {
                          $subQ->where('category_name', 'like', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('bankAccount', function($subQ) use ($searchTerm) {
                          $subQ->where('account_name', 'like', '%' . $searchTerm . '%');
                      });
                });
            }
            if ($request->category_id) {
                $query->where('category_id', $request->category_id);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->date_from && $request->date_to) {
                $query->whereBetween('expense_date', [$request->date_from, $request->date_to]);
            }
            if ($request->bank_account_id) {
                $query->where('bank_account_id', $request->bank_account_id);
            }

            if ($request->sort) {
                $query->orderBy($request->sort, $request->direction ?? 'asc');
            } else {
                $query->latest();
            }

            $expenses = $query->paginate($request->per_page ?? 10)->withQueryString();

            $categories = ExpenseCategories::where('created_by', creatorId())
                ->where('is_active', true)
                ->select('id', 'category_name')
                ->get();

            $bankAccounts = BankAccount::where('created_by', creatorId())
                ->where('is_active', true)
                ->select('id', 'account_name')
                ->get();

            $chartOfAccounts = ChartOfAccount::where('created_by', creatorId())
                ->where('is_active', true)
                ->whereHas('accountType', function($q) {
                    $q->where(function ($sub) {
                        $sub->where('name', 'like', '%expense%')
                            ->orWhereHas('category', function($q2) {
                                $q2->where('type', 'expenses');
                            });
                    })->where('name', 'not like', '%liability%')
                      ->where('name', 'not like', '%asset%')
                      ->where('name', 'not like', '%income%')
                      ->where('name', 'not like', '%revenue%')
                      ->where('name', 'not like', '%equity%');
                })
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get();

            return view('admin.account.expenses.index', [
                'expenses' => $expenses,
                'categories' => $categories,
                'bankAccounts' => $bankAccounts,
                'chartOfAccounts' => $chartOfAccounts,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-expenses')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,approved,posted',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'bank_account_id' => 'nullable|integer',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = Expense::with(['category:id,category_name', 'bankAccount:id,account_name', 'chartOfAccount:id,account_code,account_name'])
            ->select('id', 'expense_number', 'expense_date', 'category_id', 'bank_account_id', 'chart_of_account_id', 'amount', 'description', 'reference_number', 'status', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-expenses')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-expenses')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if (!empty($validated['search'])) {
            $searchTerm = $validated['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('expense_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('category', function($subQ) use ($searchTerm) {
                      $subQ->where('category_name', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('bankAccount', function($subQ) use ($searchTerm) {
                      $subQ->where('account_name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }
        if (!empty($validated['category_id'])) {
            $query->where('category_id', (int) $validated['category_id']);
        }
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $query->whereBetween('expense_date', [$validated['date_from'], $validated['date_to']]);
        }
        if (!empty($validated['bank_account_id'])) {
            $query->where('bank_account_id', (int) $validated['bank_account_id']);
        }

        $sort = $validated['sort'] ?? null;
        if ($sort) {
            $direction = $validated['direction'] ?? 'asc';
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('id');
        }

        $expenses = $query->get();

        $totalAmount = (float) ($expenses->sum('amount') ?? 0);

        $rows = $expenses->map(function ($e) {
            return [
                'number' => $e->expense_number,
                'date' => $e->expense_date?->format('Y-m-d') ?? '',
                'category' => $e->category?->category_name ?? '—',
                'bank' => $e->bankAccount?->account_name ?? '—',
                'gl' => $e->chartOfAccount?->account_code ? ($e->chartOfAccount->account_code . ' — ' . $e->chartOfAccount->account_name) : '—',
                'amount' => '₹' . number_format((float) ($e->amount ?? 0), 2),
                'status' => ucfirst((string) ($e->status ?? '')),
            ];
        })->values()->all();

        $rows[] = [
            'number' => __('TOTAL'),
            'date' => '',
            'category' => '',
            'bank' => '',
            'gl' => '',
            'amount' => '₹' . number_format($totalAmount, 2),
            'status' => '',
        ];

        $columns = [
            ['key' => 'number', 'label' => __('Number')],
            ['key' => 'date', 'label' => __('Date')],
            ['key' => 'category', 'label' => __('Category')],
            ['key' => 'bank', 'label' => __('Bank')],
            ['key' => 'gl', 'label' => __('GL')],
            ['key' => 'amount', 'label' => __('Amount'), 'class' => 'text-end'],
            ['key' => 'status', 'label' => __('Status')],
        ];

        $subtitleParts = [];
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $subtitleParts[] = $validated['date_from'] . ' → ' . $validated['date_to'];
        }
        if (!empty($validated['status'])) {
            $subtitleParts[] = 'Status: ' . $validated['status'];
        }
        if (!empty($validated['category_id'])) {
            $subtitleParts[] = 'Category: ' . $validated['category_id'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Expenses'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'expenses-export'
        );
    }

    public function show(Expense $expense)
    {
        if (! Auth::user()->can('manage-expenses')) {
            return redirect()->route('account.expenses.index')->with('error', __('Permission denied'));
        }

        if (Auth::user()->can('manage-any-expenses')) {
            if ($expense->created_by !== creatorId()) {
                abort(403);
            }
        } elseif (Auth::user()->can('manage-own-expenses')) {
            if ((int) $expense->creator_id !== (int) Auth::id()) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $expense->load(['category', 'bankAccount', 'chartOfAccount', 'approvedBy']);

        $categories = ExpenseCategories::where('created_by', creatorId())
            ->where('is_active', true)
            ->select('id', 'category_name')
            ->orderBy('category_name')
            ->get();

        $bankAccounts = BankAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->select('id', 'account_name')
            ->orderBy('account_name')
            ->get();

        $chartOfAccounts = ChartOfAccount::where('created_by', creatorId())
            ->where('is_active', true)
            ->whereHas('accountType.category', function($q) {
                $q->where('type', 'expenses');
            })
            ->select('id', 'account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        return view('admin.account.expenses.show', [
            'expense' => $expense,
            'categories' => $categories,
            'bankAccounts' => $bankAccounts,
            'chartOfAccounts' => $chartOfAccounts,
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        if(Auth::user()->can('create-expenses')){
            $validated = $request->validated();

            // Prevent rapid double-click duplicate submissions
            $recentDuplicate = \App\Models\Account\Expense::where('creator_id', Auth::id())
                ->where('amount', $validated['amount'])
                ->where('category_id', $validated['category_id'])
                ->where('bank_account_id', $validated['bank_account_id'])
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();

            if ($recentDuplicate) {
                return redirect()->route('account.expenses.index')->with('error', __('Duplicate submission detected. Please wait a moment.'));
            }

            $expense = new Expense();
            $expense->expense_date = $validated['expense_date'];
            $expense->category_id = $validated['category_id'];
            $expense->bank_account_id = $validated['bank_account_id'];
            $expense->chart_of_account_id = $validated['chart_of_account_id'];
            $expense->amount = $validated['amount'];
            $expense->description = $validated['description'];
            $expense->reference_number = $validated['reference_number'];
            $expense->status = 'draft';
            $expense->creator_id = Auth::id();
            $expense->created_by = creatorId();
            $expense->save();

            CreateExpense::dispatch($request, $expense);

            return redirect()->route('account.expenses.index')->with('success', __('The expense has been created successfully.'));
        }
        else{
            return redirect()->route('account.expenses.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        if(Auth::user()->can('edit-expenses') && $expense->created_by == creatorId()){
            if ($expense->status != 'draft') {
                return redirect()->route('account.expenses.index')->with('error', __('Cannot update posted expense.'));
            }

            $validated = $request->validated();

            $expense->expense_date = $validated['expense_date'];
            $expense->category_id = $validated['category_id'];
            $expense->bank_account_id = $validated['bank_account_id'];
            $expense->chart_of_account_id = $validated['chart_of_account_id'];
            $expense->amount = $validated['amount'];
            $expense->description = $validated['description'];
            $expense->reference_number = $validated['reference_number'];
            $expense->save();

            UpdateExpense::dispatch($request, $expense);

            return back()->with('success', __('The expense details are updated successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy($id)
    {
        $expense = Expense::find($id);
        if(!$expense) {
            return back()->with('success', __('Expense deleted successfully.'));
        }

        if(Auth::user()->can('delete-expenses') && $expense->created_by == creatorId()){
            if ($expense->status != 'draft') {
                return back()->with('error', __('Cannot delete posted expense.'));
            }

            DestroyExpense::dispatch($expense);
            $expense->delete();

            return back()->with('success', __('Expense deleted successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function approve(Expense $expense)
    {
        if(Auth::user()->can('approve-expenses') && $expense->created_by == creatorId()){
            // Atomic update to prevent race conditions from concurrent clicks
            $updated = \App\Models\Account\Expense::where('id', $expense->id)
                ->where('status', 'draft')
                ->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                ]);

            if (!$updated) {
                if ($expense->fresh()->status === 'approved') {
                    return back()->with('success', __('Expense approved successfully.'));
                }
                return back()->with('error', __('Expense is already being processed or is not in draft state.'));
            }

            // Refresh the model since we updated it directly via query builder
            $expense->refresh();

            ApproveExpense::dispatch($expense);

            return back()->with('success', __('Expense approved successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function post(Expense $expense)
    {
        if(Auth::user()->can('post-expenses') && $expense->created_by == creatorId()){
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($expense) {
                    // Atomic update to prevent race conditions and mark as posted
                    $updated = \App\Models\Account\Expense::where('id', $expense->id)
                        ->where('status', 'approved')
                        ->update(['status' => 'posted']);
                        
                    if (!$updated) {
                        throw new \Exception('ALREADY_PROCESSED');
                    }

                    // Refresh model
                    $expense->refresh();

                    $this->journalService->createExpenseEntryJournal($expense);
                    $this->bankTransactionsService->createExpensePayment($expense);

                    PostExpense::dispatch($expense);
                });

                return back()->with('success', __('Expense posted successfully.'));
            } catch (\Exception $e) {
                if ($e->getMessage() === 'ALREADY_PROCESSED') {
                    $currentStatus = $expense->fresh()->status;
                    if ($currentStatus === 'posted') {
                        return back()->with('success', __('Expense posted successfully.'));
                    }
                    return back()->with('error', __('Expense must be approved before posting.'));
                }
                
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}

