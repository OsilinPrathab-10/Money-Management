<?php

namespace App\Http\Controllers\CardCash;

use App\Http\Controllers\Controller;
use App\Models\CardCash\CreditCardWallet;
use App\Models\CardCash\CreditCardWalletTransaction;
use App\Services\CardCash\CreditCardWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCardWalletController extends Controller
{
    public function __construct(
        protected CreditCardWalletService $walletService
    ) {}

    public function index(Request $request): View
    {
        $wallets = CreditCardWallet::withCount('transactions')
            ->with('creator')
            ->get();

        // Calculate credits and debits per wallet
        foreach ($wallets as $wallet) {
            $wallet->total_credits = (float) $wallet->transactions()->where('transaction_type', 'credit')->sum('amount');
            $wallet->total_debits = (float) $wallet->transactions()->where('transaction_type', 'debit')->sum('amount');
        }

        $totalBalance = (float) $wallets->sum('current_balance');
        $totalOpening = (float) $wallets->sum('opening_balance');
        $totalCreditsAll = (float) $wallets->sum('total_credits');
        $totalDebitsAll = (float) $wallets->sum('total_debits');

        // Identify selected wallet for "Inside Wallet Account" transactions & ledger display
        $selectedWalletId = $request->input('wallet_id', $request->input('view_wallet'));
        $selectedWallet = null;

        if ($selectedWalletId) {
            $selectedWallet = $wallets->firstWhere('id', (int) $selectedWalletId);
        }
        if (!$selectedWallet && $wallets->isNotEmpty()) {
            $selectedWallet = $wallets->first();
        }

        $transactions = collect();
        $selectedWalletCredits = 0;
        $selectedWalletDebits = 0;

        if ($selectedWallet) {
            $query = $selectedWallet->transactions()->with('creator');

            if ($type = $request->input('tx_type')) {
                $query->where('transaction_type', $type);
            }
            if ($fromDate = $request->input('from_date')) {
                $query->whereDate('transaction_date', '>=', $fromDate);
            }
            if ($toDate = $request->input('to_date')) {
                $query->whereDate('transaction_date', '<=', $toDate);
            }
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('reference_type', 'like', "%{$search}%");
                });
            }

            $transactions = $query->latest('id')->paginate((int) $request->input('per_page', 20))->withQueryString();
            $selectedWalletCredits = (float) $selectedWallet->transactions()->where('transaction_type', 'credit')->sum('amount');
            $selectedWalletDebits = (float) $selectedWallet->transactions()->where('transaction_type', 'debit')->sum('amount');
        }

        return view('admin.card-to-cash.wallets.index', compact(
            'wallets',
            'totalBalance',
            'totalOpening',
            'totalCreditsAll',
            'totalDebitsAll',
            'selectedWallet',
            'transactions',
            'selectedWalletCredits',
            'selectedWalletDebits'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wallet_name' => 'required|string|max:255',
            'wallet_code' => 'required|string|max:50|unique:credit_card_wallets,wallet_code',
            'wallet_type' => 'required|string|max:50',
            'opening_balance' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        $wallet = CreditCardWallet::create([
            'wallet_name' => $validated['wallet_name'],
            'wallet_code' => strtoupper($validated['wallet_code']),
            'wallet_type' => $validated['wallet_type'],
            'opening_balance' => $validated['opening_balance'],
            'current_balance' => $validated['opening_balance'],
            'status' => 'active',
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if ($wallet->opening_balance > 0) {
            CreditCardWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'credit',
                'reference_type' => 'opening_balance',
                'reference_id' => null,
                'amount' => $wallet->opening_balance,
                'balance_before' => 0,
                'balance_after' => $wallet->opening_balance,
                'description' => 'Wallet initial opening balance',
                'transaction_date' => now(),
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('card-cash.wallets.index')
            ->with('success', "Wallet '{$wallet->wallet_name}' created successfully.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $wallet = CreditCardWallet::findOrFail($id);

        $validated = $request->validate([
            'wallet_name' => 'required|string|max:255',
            'wallet_type' => 'required|string|max:50',
            'status' => 'required|in:active,inactive',
            'remarks' => 'nullable|string|max:500',
        ]);

        $wallet->update($validated);

        return back()->with('success', "Wallet '{$wallet->wallet_name}' updated successfully.");
    }

    public function show(int $id, Request $request)
    {
        $wallet = CreditCardWallet::findOrFail($id);

        $query = $wallet->transactions()->with('creator');

        if ($type = $request->input('transaction_type')) {
            $query->where('transaction_type', $type);
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('transaction_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('transaction_date', '<=', $toDate);
        }

        $transactions = $query->latest('id')->paginate((int) $request->input('per_page', 50));

        // Stats for this wallet
        $totalCredits = $wallet->transactions()->where('transaction_type', 'credit')->sum('amount');
        $totalDebits = $wallet->transactions()->where('transaction_type', 'debit')->sum('amount');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => true,
                'wallet' => $wallet,
                'transactions' => $transactions->items(),
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
            ]);
        }

        return redirect()->route('card-cash.wallets.index', ['wallet_id' => $id]);
    }

    public function addBalance(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
        ]);

        $wallet = CreditCardWallet::findOrFail($id);

        try {
            $this->walletService->creditWallet(
                wallet: $wallet,
                amount: (float) $request->input('amount'),
                description: $request->input('description'),
                refType: 'manual_topup',
                refId: null,
                userId: auth()->id()
            );

            return back()->with('success', "₹" . number_format($request->input('amount'), 2) . " credited to '{$wallet->wallet_name}' successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function adjustBalance(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'current_balance' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        $wallet = CreditCardWallet::findOrFail($id);

        try {
            $this->walletService->adjustBalance(
                wallet: $wallet,
                targetBalance: (float) $request->input('current_balance'),
                reason: $request->input('reason'),
                userId: auth()->id()
            );

            return back()->with('success', "Balance for '{$wallet->wallet_name}' adjusted successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
