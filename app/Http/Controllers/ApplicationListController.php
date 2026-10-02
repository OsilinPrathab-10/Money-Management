<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ChitGroup;
use App\Models\Client;
use App\Models\FixedDepositScheme;
use App\Models\LoanProduct;
use App\Models\Location;
use App\Services\ApplicationListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApplicationListController extends Controller
{
    public function __construct(protected ApplicationListService $applications)
    {
    }

    public function index(Request $request, string $module = 'all'): View
    {
        $module = $this->guardModule($module);
        if ($module === 'settlement' && ! $this->applications->canListSettlements()) {
            abort(403);
        }

        $settlementTab = 'pending';
        try {
            if ($module === 'settlement') {
                $settlementTab = strtolower((string) $request->input('status', 'pending'));
                if (! in_array($settlementTab, ['pending', 'approved', 'rejected'], true)) {
                    $settlementTab = 'pending';
                }
                $request->merge(['status' => $settlementTab]);
                $listed = $this->applications->listRows($module, $request);
                $stats = $this->applications->settlementTabCounts($request);
                $rows = $this->applications->paginateListed($listed, $request);
            } else {
                $listed = $this->applications->listRows($module, $request);
                $stats = $this->applications->statsFromRows($listed);
                $rows = $this->applications->paginateListed($listed, $request);
            }
        } catch (\Throwable $e) {
            report($e);
            $rows = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25, 1, [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]);
            $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        }

        $user = Auth::user();
        $isAgent = $user && $user->hasRole('Agent') && ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
        $agentId = $isAgent ? ($user->agent_id ?? optional($user->agent)->id) : null;
        $canApply = $user && $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff', 'Agent']);

        $verifiedClientsQuery = Client::whereHas('kycDetail', function ($q) {
            $q->whereIn('status', ['verified', 'approved']);
        });
        if ($isAgent) {
            if (! $agentId) {
                $verifiedClientsQuery->whereRaw('1 = 0');
            } else {
                $verifiedClientsQuery->where(function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                      ->orWhere('assigned_to', $agentId);
                });
            }
        }
        $verifiedClients = $verifiedClientsQuery->orderBy('client_name')->get();

        $loanProducts = LoanProduct::query()
            ->where(function ($q) {
                $q->where('status', 'active')->orWhere('status', 1);
            })
            ->orderBy('loan_name')
            ->get();

        $availableGroups = ChitGroup::with(['scheme'])
            ->whereIn('status', ['forming', 'upcoming', 'active', 'open'])
            ->withCount(['members' => fn ($q) => $q->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])])
            ->orderBy('group_code')
            ->get()
            ->filter(fn ($g) => (int) $g->members_count < (int) ($g->total_members ?? 0))
            ->values();

        $agents = Agent::orderBy('agent_name')->get();
        $fdSchemes = FixedDepositScheme::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $payoutOptions = FixedDepositScheme::payoutOptions();

        return view('admin.applications.index', [
            'module' => $module,
            'stats' => $stats,
            'rows' => $rows,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'productHeading' => $this->applications->productHeading($module),
            'moduleTitle' => $this->applications->moduleTitle($module),
            'verifiedClients' => $verifiedClients,
            'loanProducts' => $loanProducts,
            'availableGroups' => $availableGroups,
            'agents' => $agents,
            'fdSchemes' => $fdSchemes,
            'payoutOptions' => $payoutOptions,
            'canApply' => $canApply,
            'canListSettlements' => $this->applications->canListSettlements(),
            'settlementTab' => $settlementTab,
        ]);
    }

    protected function guardModule(string $module): string
    {
        $module = strtolower(trim($module));
        if (! in_array($module, ApplicationListService::MODULES, true)) {
            abort(404);
        }

        return $module;
    }
}
