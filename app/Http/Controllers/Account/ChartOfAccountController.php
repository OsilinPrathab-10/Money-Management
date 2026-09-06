<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\CreateChartOfAccount;
use App\Events\Account\DestroyChartOfAccount;
use App\Events\Account\UpdateChartOfAccount;
use App\Http\Requests\Account\StoreChartOfAccountRequest;
use App\Http\Requests\Account\UpdateChartOfAccountRequest;
use App\Models\Account\AccountType;
use App\Models\Account\ChartOfAccount;
use App\Models\Account\JournalEntryItem;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-chart-of-accounts')){
            $chartofaccounts = ChartOfAccount::query()
                ->with(['account_type', 'parent_account'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-chart-of-accounts')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-chart-of-accounts')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('account_code'), function($q) {
                    $q->where(function($query) {
                    $query->where('account_code', 'like', '%' . request('account_code') . '%');
                    $query->orWhere('account_name', 'like', '%' . request('account_code') . '%');
                    });
                })
                ->when(request('account_type_id') && request('account_type_id') !== 'all', fn($q) => $q->where('account_type_id', request('account_type_id')))
                ->when(request('normal_balance') && request('normal_balance') !== 'all', fn($q) => $q->where('normal_balance', request('normal_balance')))
                ->when(request('is_active') !== null && request('is_active') !== 'all', fn($q) => $q->where('is_active', request('is_active') === '1'))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->oldest())
                ->paginate(request('per_page', 20))
                ->withQueryString();

            $accounttypes = AccountType::where('created_by', creatorId())->select('id', 'name', 'normal_balance')->get();
            $allChartOfAccounts = ChartOfAccount::where('created_by', creatorId())
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get();

            return view('admin.account.chart-of-accounts.index', [
                'chartofaccounts' => $chartofaccounts,
                'accounttypes' => $accounttypes,
                'allAccountTypes' => $accounttypes,
                'allChartOfAccounts' => $allChartOfAccounts,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountExportService $exportService)
    {
        if (! Auth::user()->can('manage-chart-of-accounts')) {
            abort(403);
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv,xlsx',
            'account_code' => 'nullable|string|max:255',
            'account_type_id' => 'nullable|integer',
            'normal_balance' => 'nullable|in:debit,credit',
            'is_active' => 'nullable|in:0,1',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = ChartOfAccount::query()
            ->with(['account_type'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-chart-of-accounts')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-chart-of-accounts')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(!empty($validated['account_code']), function ($q) use ($validated) {
                $term = (string) $validated['account_code'];
                $q->where(function ($query) use ($term) {
                    $query->where('account_code', 'like', '%' . $term . '%')
                        ->orWhere('account_name', 'like', '%' . $term . '%');
                });
            })
            ->when(!empty($validated['account_type_id']), fn($q) => $q->where('account_type_id', (int) $validated['account_type_id']))
            ->when(!empty($validated['normal_balance']), fn($q) => $q->where('normal_balance', $validated['normal_balance']))
            ->when(isset($validated['is_active']) && $validated['is_active'] !== null, fn($q) => $q->where('is_active', (string) $validated['is_active'] === '1'))
            ->when(!empty($validated['sort']), fn($q) => $q->orderBy($validated['sort'], $validated['direction'] ?? 'asc'), fn($q) => $q->latest());

        $chartofaccounts = $query->get();

        $totalOpening = (float) ($chartofaccounts->sum('opening_balance') ?? 0);
        $totalCurrent = (float) ($chartofaccounts->sum('current_balance') ?? 0);

        $rows = $chartofaccounts->map(function ($row) {
            return [
                'code' => $row->account_code,
                'name' => $row->account_name,
                'type' => $row->account_type?->name ?? '—',
                'normal' => $row->normal_balance,
                'opening' => '₹' . number_format((float) ($row->opening_balance ?? 0), 2),
                'current' => '₹' . number_format((float) ($row->current_balance ?? 0), 2),
                'active' => $row->is_active ? __('Yes') : __('No'),
            ];
        })->values()->all();

        $rows[] = [
            'code' => __('TOTAL'),
            'name' => '',
            'type' => '',
            'normal' => '',
            'opening' => '₹' . number_format($totalOpening, 2),
            'current' => '₹' . number_format($totalCurrent, 2),
            'active' => '',
        ];

        $columns = [
            ['key' => 'code', 'label' => __('Code')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'type', 'label' => __('Type')],
            ['key' => 'normal', 'label' => __('Normal')],
            ['key' => 'opening', 'label' => __('Opening'), 'class' => 'text-end'],
            ['key' => 'current', 'label' => __('Current'), 'class' => 'text-end'],
            ['key' => 'active', 'label' => __('Active')],
        ];

        $subtitleParts = [];
        if (!empty($validated['account_code'])) {
            $subtitleParts[] = 'Search: ' . $validated['account_code'];
        }
        if (!empty($validated['account_type_id'])) {
            $subtitleParts[] = 'Type ID: ' . $validated['account_type_id'];
        }
        if (!empty($validated['normal_balance'])) {
            $subtitleParts[] = 'Normal: ' . $validated['normal_balance'];
        }
        if (isset($validated['is_active']) && $validated['is_active'] !== null) {
            $subtitleParts[] = 'Active: ' . $validated['is_active'];
        }

        return $exportService->exportByFormat(
            $validated['format'],
            'admin.account.exports.generic-table',
            [
                'pageTitle' => __('Chart of accounts'),
                'subtitle' => !empty($subtitleParts) ? implode(' | ', $subtitleParts) : null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'chart-of-accounts-export'
        );
    }

    public function store(StoreChartOfAccountRequest $request)
    {
        if(Auth::user()->can('create-chart-of-accounts')){
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            $chartofaccount = new ChartOfAccount();
            $chartofaccount->account_code = $validated['account_code'];
            $chartofaccount->account_name = $validated['account_name'];

            // Set level based on parent account selection
            if ($validated['parent_account_id'] && $validated['parent_account_id'] !== '0') {
                $chartofaccount->level = 2;
                $chartofaccount->parent_account_id = $validated['parent_account_id'];
            } else {
                $chartofaccount->level = 1;
                $chartofaccount->parent_account_id = null;
            }

            $chartofaccount->normal_balance = $validated['normal_balance'];
            $chartofaccount->opening_balance = $validated['opening_balance'];
            $chartofaccount->current_balance = $validated['current_balance'];
            $chartofaccount->is_active = $validated['is_active'];
            $chartofaccount->description = $validated['description'];
            $chartofaccount->account_type_id = $validated['account_type_id'];
            $chartofaccount->creator_id = Auth::id();
            $chartofaccount->created_by = creatorId();
            $chartofaccount->save();

            CreateChartOfAccount::dispatch($request, $chartofaccount);

            return redirect()->route('account.chart-of-accounts.index')->with('success', __('The chart of account has been created successfully.'));
        }
        else{
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartofaccount)
    {
        if(Auth::user()->can('edit-chart-of-accounts')){
            $validated = $request->validated();
            $validated['is_active'] = $request->boolean('is_active', true);

            // Don't update account_code if it's a system account
            if ($chartofaccount->is_system_account != 1) {
                $chartofaccount->account_code = $validated['account_code'];
            }
            // Don't update account_name if it's a system account
            if ($chartofaccount->is_system_account != 1) {
                $chartofaccount->account_name = $validated['account_name'];
            }

            // Set level based on parent account selection
            if ($validated['parent_account_id'] && $validated['parent_account_id'] !== '0') {
                $chartofaccount->level = 2;
                $chartofaccount->parent_account_id = $validated['parent_account_id'];
            } else {
                $chartofaccount->level = 1;
                $chartofaccount->parent_account_id = null;
            }

            $chartofaccount->normal_balance = $validated['normal_balance'];
            $chartofaccount->opening_balance = $validated['opening_balance'];
            $chartofaccount->current_balance = $validated['current_balance'];
            $chartofaccount->is_active = $validated['is_active'];
            $chartofaccount->description = $validated['description'];
            $chartofaccount->account_type_id = $validated['account_type_id'];
            $chartofaccount->save();

            UpdateChartOfAccount::dispatch($request, $chartofaccount);

            return redirect()->back()->with('success', __('The chart of account details are updated successfully.'));
        }
        else{
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function show(ChartOfAccount $chartofaccount)
    {
        if(Auth::user()->can('view-chart-of-accounts')){
            $history = JournalEntryItem::with(['journalEntry'])
                ->where('account_id', $chartofaccount->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            // Calculate actual balance from journal entries
            $totalDebits = JournalEntryItem::where('account_id', $chartofaccount->id)->sum('debit_amount');
            $totalCredits = JournalEntryItem::where('account_id', $chartofaccount->id)->sum('credit_amount');

            $calculatedBalance = $chartofaccount->normal_balance === 'debit'
                ? ($chartofaccount->opening_balance + $totalDebits - $totalCredits)
                : ($chartofaccount->opening_balance + $totalCredits - $totalDebits);

            return view('admin.account.chart-of-accounts.show', [
                'chartofaccount' => $chartofaccount->load(['account_type', 'parent_account']),
                'history' => $history,
                'calculatedBalance' => $calculatedBalance,
                'totalDebits' => $totalDebits,
                'totalCredits' => $totalCredits,
            ]);
        }
        else{
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(ChartOfAccount $chartofaccount)
    {
        if(Auth::user()->can('delete-chart-of-accounts')){

            // Dispatch event for packages to handle their fields
            DestroyChartOfAccount::dispatch($chartofaccount);

            $chartofaccount->delete();

            return redirect()->back()->with('success', __('The chart of account has been deleted.'));
        }
        else{
            return redirect()->route('account.chart-of-accounts.index')->with('error', __('Permission denied'));
        }
    }
}

