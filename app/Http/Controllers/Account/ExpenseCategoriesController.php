<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateExpenseCategories;
use App\Events\Account\DestroyExpenseCategories;
use App\Events\Account\UpdateExpenseCategories;
use App\Http\Requests\Account\StoreExpenseCategoriesRequest;
use App\Http\Requests\Account\UpdateExpenseCategoriesRequest;
use App\Models\Account\ChartOfAccount;
use App\Models\Account\ExpenseCategories;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ExpenseCategoriesController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-expense-categories')) {
            $expensecategories = ExpenseCategories::with('gl_account:id,account_name')
                ->select('id', 'category_name', 'category_code', 'gl_account_id', 'description', 'is_active', 'created_at')
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-account-types')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-account-types')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        // If the user can manage expense categories but doesn't have
                        // explicit "any/own account types" scoping abilities, still
                        // show their own tenant user records.
                        $q->where('creator_id', Auth::id());
                    }
                })
                ->when(request('search'), function ($q) {
                    $term = (string) request('search');
                    $q->where(function ($qq) use ($term) {
                        $qq->where('category_name', 'like', '%' . $term . '%')
                            ->orWhere('category_code', 'like', '%' . $term . '%');
                    });
                })
                ->when(request('is_active') !== null && request('is_active') !== '', function ($q) {
                    $isActive = (string) request('is_active') === '1';
                    $q->where('is_active', $isActive);
                })
                ->latest()
                ->get();

            return view('admin.account.expense-categories.index', [
                'expensecategories' => $expensecategories,
                'chartofaccounts' => ChartOfAccount::where('created_by', creatorId())
                    ->where('is_active', true)
                    ->whereHas('accountType.category', function($q) {
                        $q->where('type', 'expenses');
                    })
                    ->select('id', 'account_code', 'account_name')
                    ->orderBy('account_code')
                    ->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-expense-categories')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:0,1',
        ]);

        $query = ExpenseCategories::with('gl_account:id,account_name,account_code')
            ->select('id', 'category_name', 'category_code', 'gl_account_id', 'description', 'is_active', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-account-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-account-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    // Same scoping fallback as index().
                    $q->where('creator_id', Auth::id());
                }
            })
            ->latest();

        if (!empty($validated['search'])) {
            $term = (string) $validated['search'];
            $query->where(function ($q) use ($term) {
                $q->where('category_name', 'like', '%' . $term . '%')
                    ->orWhere('category_code', 'like', '%' . $term . '%');
            });
        }
        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (string) $validated['is_active'] === '1');
        }

        $expensecategories = $query->get();

        $rows = $expensecategories->map(function ($r) {
            return [
                'code' => $r->category_code,
                'name' => $r->category_name,
                'gl' => $r->gl_account?->account_code ? ($r->gl_account->account_code . ' — ' . $r->gl_account->account_name) : ($r->gl_account?->account_name ?? '—'),
                'active' => $r->is_active ? __('Yes') : __('No'),
                'description' => (string) ($r->description ?? ''),
            ];
        })->values()->all();

        $columns = [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'gl', 'label' => __('GL')],
            ['key' => 'active', 'label' => __('Active')],
            ['key' => 'description', 'label' => __('Description')],
        ];

        $subtitleParts = [];
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }
        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $subtitleParts[] = 'Active: ' . $validated['is_active'];
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Expense categories'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'expense-categories-export'
        );
    }

    public function store(StoreExpenseCategoriesRequest $request)
    {
        if(Auth::user()->can('create-expense-categories')){
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $expensecategories = new ExpenseCategories();
            $expensecategories->category_name = $validated['category_name'];
            $expensecategories->category_code = $validated['category_code'];
            $expensecategories->description = $validated['description'];
            $expensecategories->gl_account_id = $validated['gl_account_id'];
            $expensecategories->is_active = $validated['is_active'];
            $expensecategories->creator_id = Auth::id();
            $expensecategories->created_by = creatorId();
            $expensecategories->save();

            CreateExpenseCategories::dispatch($request, $expensecategories);

            return redirect()->route('account.expense-categories.index')->with('success', __('The expense categories has been created successfully.'));
        }
        else{
            return redirect()->route('account.expense-categories.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateExpenseCategoriesRequest $request, ExpenseCategories $expensecategories)
    {
        if(Auth::user()->can('edit-expense-categories')){
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $expensecategories->category_name = $validated['category_name'];
            $expensecategories->category_code = $validated['category_code'];
            $expensecategories->description = $validated['description'];
            $expensecategories->is_active = $validated['is_active'];
            $expensecategories->gl_account_id = $validated['gl_account_id'];
            $expensecategories->is_active = $validated['is_active'];
            $expensecategories->save();

            UpdateExpenseCategories::dispatch($request, $expensecategories);

            return redirect()->route('account.expense-categories.index')->with('success', __('The expense categories details are updated successfully.'));
        }
        else{
            return redirect()->route('account.expense-categories.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy($id)
    {
        if(Auth::user()->can('delete-expense-categories')){
            $expensecategories = ExpenseCategories::find($id);

            if ($expensecategories) {
                DestroyExpenseCategories::dispatch($expensecategories);
                $expensecategories->delete();
            }

            return redirect()->route('account.expense-categories.index')->with('success', __('The expense categories has been deleted.'));
        }
        else{
            return redirect()->route('account.expense-categories.index')->with('error', __('Permission denied'));
        }
    }
}

