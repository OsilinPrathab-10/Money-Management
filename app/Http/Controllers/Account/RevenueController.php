<?php

namespace App\Http\Controllers\Account;

use App\Events\Account\ApproveRevenue;
use App\Events\Account\CreateRevenue;
use App\Events\Account\DestroyRevenue;
use App\Events\Account\PostRevenue;
use App\Events\Account\UpdateRevenue;
use App\Http\Requests\Account\StoreRevenueRequest;
use App\Http\Requests\Account\UpdateRevenueRequest;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use App\Models\Account\BankAccount;
use App\Models\Account\ChartOfAccount;
use App\Services\Account\BankTransactionsService;
use App\Services\Account\JournalService;
use App\Services\Account\AccountExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class RevenueController extends Controller
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
        if(Auth::user()->can('manage-revenues')){
            $query = Revenue::with(['category:id,category_name', 'bankAccount:id,account_name', 'chartOfAccount:id,account_code,account_name', 'approvedBy:id,name'])
                ->select('id', 'revenue_number', 'revenue_date', 'category_id', 'bank_account_id', 'chart_of_account_id', 'amount', 'description', 'reference_number', 'status', 'approved_by', 'created_at')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-revenues')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-revenues')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            // Apply filters
            if ($request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('revenue_number', 'like', '%' . $searchTerm . '%')
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
                $query->whereBetween('revenue_date', [$request->date_from, $request->date_to]);
            }
            if ($request->bank_account_id) {
                $query->where('bank_account_id', $request->bank_account_id);
            }

            if ($request->sort) {
                $query->orderBy($request->sort, $request->direction ?? 'asc');
            } else {
                $query->latest();
            }

            $revenues = $query->paginate($request->per_page ?? 20)->withQueryString();

            $categories = RevenueCategories::where('created_by', creatorId())
                ->where('is_active', true)
                ->select('id', 'category_name')
                ->get();

            $bankAccounts = BankAccount::where('created_by', creatorId())
                ->where('is_active', true)
                ->select('id', 'account_name')
                ->get();

            $chartOfAccounts = ChartOfAccount::where('created_by', creatorId())
                ->where('is_active', true)
                ->whereHas('accountType.category', function($q) {
                    $q->where('type', 'revenue');
                })
                ->select('id', 'account_code', 'account_name')
                ->orderBy('account_code')
                ->get();

            return view('admin.account.revenues.index', [
                'revenues' => $revenues,
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
        if (! Auth::user()->can('manage-revenues')) {
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

        $query = Revenue::with(['category:id,category_name', 'bankAccount:id,account_name', 'chartOfAccount:id,account_code,account_name'])
            ->select('id', 'revenue_number', 'revenue_date', 'category_id', 'bank_account_id', 'chart_of_account_id', 'amount', 'description', 'reference_number', 'status', 'created_at')
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-revenues')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-revenues')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if (!empty($validated['search'])) {
            $searchTerm = $validated['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('revenue_number', 'like', '%' . $searchTerm . '%')
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
            $query->whereBetween('revenue_date', [$validated['date_from'], $validated['date_to']]);
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

        $revenues = $query->get();

        $totalAmount = (float) ($revenues->sum('amount') ?? 0);

        $rows = $revenues->map(function ($r) {
            return [
                'number' => $r->revenue_number,
                'date' => $r->revenue_date?->format('Y-m-d') ?? '',
                'category' => $r->category?->category_name ?? '—',
                'bank' => $r->bankAccount?->account_name ?? '—',
                'gl' => $r->chartOfAccount?->account_code ? ($r->chartOfAccount->account_code . ' — ' . $r->chartOfAccount->account_name) : '—',
                'amount' => '₹' . number_format((float) ($r->amount ?? 0), 2),
                'status' => ucfirst((string) ($r->status ?? '')),
            ];
        })->values()->all();

        // Totals row (keeps generic table aligned)
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
                'pageTitle' => __('Revenues'),
                'subtitle' => $subtitle ?: null,
                'columns' => $columns,
                'rows' => $rows,
            ],
            'revenues-export'
        );
    }

    public function show(Revenue $revenue)
    {
        if (! Auth::user()->can('manage-revenues')) {
            return redirect()->route('account.revenues.index')->with('error', __('Permission denied'));
        }

        if (Auth::user()->can('manage-any-revenues')) {
            if ($revenue->created_by !== creatorId()) {
                abort(403);
            }
        } elseif (Auth::user()->can('manage-own-revenues')) {
            if ((int) $revenue->creator_id !== (int) Auth::id()) {
                abort(403);
            }
        } else {
            abort(403);
        }

        $revenue->load(['category', 'bankAccount', 'chartOfAccount', 'approvedBy']);

        $categories = RevenueCategories::where('created_by', creatorId())
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
                $q->where('type', 'revenue');
            })
            ->select('id', 'account_code', 'account_name')
            ->orderBy('account_code')
            ->get();

        return view('admin.account.revenues.show', [
            'revenue' => $revenue,
            'categories' => $categories,
            'bankAccounts' => $bankAccounts,
            'chartOfAccounts' => $chartOfAccounts,
        ]);
    }

    public function store(StoreRevenueRequest $request)
    {
        if(Auth::user()->can('create-revenues')){
            $validated = $request->validated();

            // Prevent rapid double-click duplicate submissions
            $recentDuplicate = \App\Models\Account\Revenue::where('creator_id', Auth::id())
                ->where('amount', $validated['amount'])
                ->where('category_id', $validated['category_id'])
                ->where('bank_account_id', $validated['bank_account_id'])
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();

            if ($recentDuplicate) {
                return redirect()->route('account.revenues.index')->with('error', __('Duplicate submission detected. Please wait a moment.'));
            }

            $revenue = new Revenue();
            $revenue->revenue_date = $validated['revenue_date'];
            $revenue->category_id = $validated['category_id'];
            $revenue->bank_account_id = $validated['bank_account_id'];
            $revenue->chart_of_account_id = $validated['chart_of_account_id'] ?? null;
            $revenue->amount = $validated['amount'];
            $revenue->description = $validated['description'];
            $revenue->reference_number = $validated['reference_number'];
            $revenue->status = 'draft';
            $revenue->creator_id = Auth::id();
            $revenue->created_by = creatorId();
            $revenue->save();

            CreateRevenue::dispatch($request, $revenue);

            return redirect()->route('account.revenues.index')->with('success', __('The revenue has been created successfully.'));
        }
        else{
            return redirect()->route('account.revenues.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateRevenueRequest $request, Revenue $revenue)
    {
        if(Auth::user()->can('edit-revenues') && $revenue->created_by == creatorId()){
            if ($revenue->status != 'draft') {
                return redirect()->route('account.revenues.index')->with('error', __('Cannot update posted revenue.'));
            }

            $validated = $request->validated();

            $revenue->revenue_date = $validated['revenue_date'];
            $revenue->category_id = $validated['category_id'];
            $revenue->bank_account_id = $validated['bank_account_id'];
            $revenue->chart_of_account_id = $validated['chart_of_account_id'] ?? null;
            $revenue->amount = $validated['amount'];
            $revenue->description = $validated['description'];
            $revenue->reference_number = $validated['reference_number'];
            $revenue->save();

            UpdateRevenue::dispatch($request, $revenue);

            return back()->with('success', __('The revenue details are updated successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy($id)
    {
        $revenue = Revenue::find($id);
        if(!$revenue) {
            return back()->with('success', __('The revenue has been deleted.'));
        }

        if(Auth::user()->can('delete-revenues') && $revenue->created_by == creatorId()){
            if ($revenue->status != 'draft') {
                return back()->with('error', __('Cannot delete posted revenue.'));
            }

            DestroyRevenue::dispatch($revenue);
            $revenue->delete();

            return back()->with('success', __('The revenue has been deleted.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function approve(Revenue $revenue)
    {
        if(Auth::user()->can('approve-revenues') && $revenue->created_by == creatorId()){
            // Atomic update to prevent race conditions from concurrent clicks
            $updated = \App\Models\Account\Revenue::where('id', $revenue->id)
                ->where('status', 'draft')
                ->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                ]);

            if (!$updated) {
                if ($revenue->fresh()->status === 'approved') {
                    return back()->with('success', __('Revenue approved successfully.'));
                }
                return back()->with('error', __('Revenue is already being processed or is not in draft state.'));
            }

            // Refresh the model since we updated it directly via query builder
            $revenue->refresh();

            ApproveRevenue::dispatch($revenue);

            return back()->with('success', __('Revenue approved successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function post(Revenue $revenue)
    {
        if(Auth::user()->can('post-revenues') && $revenue->created_by == creatorId()){
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($revenue) {
                    // Atomic update to prevent race conditions and mark as posted
                    $updated = \App\Models\Account\Revenue::where('id', $revenue->id)
                        ->where('status', 'approved')
                        ->update(['status' => 'posted']);
                        
                    if (!$updated) {
                        throw new \Exception('ALREADY_PROCESSED');
                    }

                    // Refresh model to get updated state for listeners
                    $revenue->refresh();

                    $this->journalService->createRevenueEntryJournal($revenue);
                    $this->bankTransactionsService->createRevenuePayment($revenue);

                    PostRevenue::dispatch($revenue);
                });

                return back()->with('success', __('Revenue posted successfully.'));
            } catch (\Exception $e) {
                if ($e->getMessage() === 'ALREADY_PROCESSED') {
                    $currentStatus = $revenue->fresh()->status;
                    if ($currentStatus === 'posted') {
                        return back()->with('success', __('Revenue posted successfully.'));
                    }
                    return back()->with('error', __('Revenue must be approved before posting.'));
                }
                
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}

