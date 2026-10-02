<?php

namespace App\Http\Controllers\Account;

use App\Http\Requests\Account\StoreAccountCategoryRequest;
use App\Http\Requests\Account\UpdateAccountCategoryRequest;
use App\Models\Account\AccountCategory;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AccountCategoryController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-account-categories')) {
            $accountcategories = AccountCategory::query()
                ->select('id', 'name', 'code', 'type', 'description', 'is_active', 'created_at')
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-account-types')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-account-types')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->where('creator_id', Auth::id());
                    }
                })
                ->when(request('search'), function ($q) {
                    $term = (string) request('search');
                    $q->where(function ($qq) use ($term) {
                        $qq->where('name', 'like', '%' . $term . '%')
                            ->orWhere('code', 'like', '%' . $term . '%');
                    });
                })
                ->when(request('is_active') !== null && request('is_active') !== '', function ($q) {
                    $isActive = (string) request('is_active') === '1';
                    $q->where('is_active', $isActive);
                })
                ->when(request('type') !== null && request('type') !== '', function ($q) {
                    $q->where('type', request('type'));
                })
                ->latest()
                ->paginate(request('per_page', 20))
                ->withQueryString();

            return view('admin.account.account-categories.index', [
                'accountcategories' => $accountcategories,
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-account-categories')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:0,1',
            'type' => 'nullable|string|in:assets,liabilities,equity,revenue,expenses',
        ]);

        $query = AccountCategory::query()
            ->select('id', 'name', 'code', 'type', 'description', 'is_active', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-account-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-account-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->where('creator_id', Auth::id());
                }
            })
            ->latest();

        if (!empty($validated['search'])) {
            $term = (string) $validated['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                    ->orWhere('code', 'like', '%' . $term . '%');
            });
        }
        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $query->where('is_active', (string) $validated['is_active'] === '1');
        }
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $accountcategories = $query->get();

        $rows = $accountcategories->map(function ($r) {
            return [
                'code' => $r->code,
                'name' => $r->name,
                'type' => ucfirst($r->type),
                'active' => $r->is_active ? __('Yes') : __('No'),
                'description' => (string) ($r->description ?? ''),
            ];
        })->values()->all();

        $columns = [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'type', 'label' => __('Type')],
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
        if (!empty($validated['type'])) {
            $subtitleParts[] = 'Type: ' . ucfirst($validated['type']);
        }
        $subtitle = implode(' | ', $subtitleParts);

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Account categories'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'account-categories-export'
        );
    }

    public function store(StoreAccountCategoryRequest $request)
    {
        if (Auth::user()->can('create-account-categories')) {
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $category = new AccountCategory();
            $category->name = $validated['name'];
            $category->code = $validated['code'];
            $category->type = $validated['type'];
            $category->description = $validated['description'] ?? null;
            $category->is_active = $validated['is_active'];
            $category->creator_id = Auth::id();
            $category->created_by = creatorId();
            $category->save();

            return redirect()->route('account.account-categories.index')->with('success', __('The account category has been created successfully.'));
        } else {
            return redirect()->route('account.account-categories.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateAccountCategoryRequest $request, AccountCategory $accountcategory)
    {
        if (Auth::user()->can('edit-account-categories')) {
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $accountcategory->name = $validated['name'];
            $accountcategory->code = $validated['code'];
            $accountcategory->type = $validated['type'];
            $accountcategory->description = $validated['description'] ?? null;
            $accountcategory->is_active = $validated['is_active'];
            $accountcategory->save();

            return redirect()->route('account.account-categories.index')->with('success', __('The account category details are updated successfully.'));
        } else {
            return redirect()->route('account.account-categories.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy($id)
    {
        if (Auth::user()->can('delete-account-categories')) {
            $category = AccountCategory::find($id);

            if ($category) {
                // Check if there are dependent account types before deletion
                if ($category->accountTypes()->exists()) {
                    return redirect()->route('account.account-categories.index')->with('error', __('Cannot delete category because it has dependent account types associated with it.'));
                }
                $category->delete();
            }

            return redirect()->route('account.account-categories.index')->with('success', __('The account category has been deleted.'));
        } else {
            return redirect()->route('account.account-categories.index')->with('error', __('Permission denied'));
        }
    }
}
