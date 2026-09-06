<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateAccountType;
use App\Events\Account\DestroyAccountType;
use App\Events\Account\UpdateAccountType;
use App\Http\Requests\Account\StoreAccountTypeRequest;
use App\Http\Requests\Account\UpdateAccountTypeRequest;
use App\Models\Account\AccountCategory;
use App\Models\Account\AccountType;
use App\Models\Account\ChartOfAccount;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AccountTypeController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-account-types')) {
            $accounttypes = AccountType::with(['category'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-account-types')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-account-types')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
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
                ->latest()
                ->get();

            return view('admin.account.account-types.index', [
                'accounttypes' => $accounttypes,
                'accountcategories' => AccountCategory::where('created_by', creatorId())->orderBy('name')->get(),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-account-types')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:0,1',
        ]);

        $query = AccountType::query()
            ->with(['category'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-account-types')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-account-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($validated['search']), function ($q) use ($validated) {
                $term = (string) $validated['search'];
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', '%' . $term . '%')
                        ->orWhere('code', 'like', '%' . $term . '%');
                });
            })
            ->when(isset($validated['is_active']) && $validated['is_active'] !== '', fn($q) => $q->where('is_active', (string) $validated['is_active'] === '1'));

        $accounttypes = $query->latest()->get();

        $rows = $accounttypes->map(function ($t) {
            return [
                'code' => $t->code,
                'name' => $t->name,
                'category' => $t->category?->name ?? '—',
                'normal' => $t->normal_balance,
                'active' => $t->is_active ? __('Yes') : __('No'),
            ];
        })->values()->all();

        $columns = [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'category', 'label' => __('Category')],
            ['key' => 'normal', 'label' => __('Normal')],
            ['key' => 'active', 'label' => __('Active')],
        ];

        $subtitleParts = [];
        if (!empty($validated['search'])) {
            $subtitleParts[] = 'Search: ' . $validated['search'];
        }
        if (isset($validated['is_active']) && $validated['is_active'] !== '') {
            $subtitleParts[] = 'Active: ' . $validated['is_active'];
        }

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Account types'),
                'subtitle' => !empty($subtitleParts) ? implode(' | ', $subtitleParts) : null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'account-types-export'
        );
    }

    public function store(StoreAccountTypeRequest $request)
    {
        if (Auth::user()->can('create-account-types')) {
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', false);

            $accounttype = new AccountType();
            $accounttype->category_id = $validated['category_id'];
            $accounttype->name = $validated['name'];
            $accounttype->code = $validated['code'];
            $accounttype->normal_balance = $validated['normal_balance'] === '1' ? 'credit' : 'debit';
            $accounttype->description = $validated['description'];
            $accounttype->is_active = $validated['is_active'];
            $accounttype->creator_id = Auth::id();
            $accounttype->created_by = creatorId();
            $accounttype->save();

            // Dispatch event for packages to handle their fields
            CreateAccountType::dispatch($request, $accounttype);

            return redirect()->route('account.account-types.index')->with('success', __('The account type has been created successfully.'));
        } else {
            return redirect()->route('account.account-types.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateAccountTypeRequest $request, AccountType $accounttype)
    {
        if (Auth::user()->can('edit-account-types')) {
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', false);

            $accounttype->category_id = $validated['category_id'];
            $accounttype->name = $validated['name'];
            $accounttype->code = $validated['code'];
            $accounttype->normal_balance = $validated['normal_balance'] === '1' ? 'credit' : 'debit';
            $accounttype->description = $validated['description'];
            $accounttype->is_active = $validated['is_active'];
            $accounttype->save();

            // Dispatch event for packages to handle their fields
            UpdateAccountType::dispatch($request, $accounttype);

            return back()->with('success', __('The account type details are updated successfully.'));
        } else {
            return redirect()->route('account.account-types.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(AccountType $accounttype)
    {
        if (Auth::user()->can('delete-account-types')) {

            // Dispatch event for packages to handle their fields
            DestroyAccountType::dispatch($accounttype);

            $accounttype->delete();

            return redirect()->back()->with('success', __('The accounttype has been deleted.'));
        } else {
            return redirect()->route('account.account-types.index')->with('error', __('Permission denied'));
        }
    }
}

