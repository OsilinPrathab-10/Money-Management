<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use App\Services\FixedDeposit\WalletService;
use Illuminate\Http\Request;

class CustomerWalletController extends Controller
{
    public function __construct(protected WalletService $walletService)
    {
    }

    public function index(Request $request)
    {
        $query = CustomerWallet::with('client')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('client', function ($q) use ($s) {
                $q->where('client_name', 'like', "%{$s}%")
                    ->orWhere('client_phone', 'like', "%{$s}%");
            });
        }

        $wallets = $query->paginate(20)->withQueryString();

        return view('admin.fd.wallets.index', compact('wallets'));
    }

    public function show(CustomerWallet $wallet)
    {
        $wallet->load('client');
        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->latest()
            ->paginate(25);
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        return view('admin.fd.wallets.show', compact('wallet', 'transactions', 'bankAccounts'));
    }

    public function showByClient(Client $client)
    {
        $wallet = $this->walletService->getOrCreateWallet($client->id);

        return redirect()->route('fd.wallets.show', $wallet);
    }

    public function balance(Client $client)
    {
        $balance = $this->walletService->balanceForClient($client->id);

        return response()->json([
            'client_id' => $client->id,
            'balance' => $balance,
            'balance_formatted' => '₹' . number_format($balance, 2),
        ]);
    }

    public function withdraw(Request $request, CustomerWallet $wallet)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cash,bank_transfer,upi'],
            'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($wallet, $validated) {
                $this->walletService->debit(
                    $wallet->client_id,
                    (float) $validated['amount'],
                    'Wallet Withdrawal' . (!empty($validated['remarks']) ? ' — ' . $validated['remarks'] : ''),
                    'wallet_withdrawal',
                    $wallet->id,
                    [
                        'payment_mode' => $validated['payment_mode'],
                        'reference' => $validated['reference'] ?? null,
                    ]
                );

                app(\App\Services\Account\FdAccountingService::class)->recordWalletWithdrawal(
                    (int) $wallet->client_id,
                    (float) $validated['amount'],
                    $validated['payment_mode'],
                    isset($validated['internal_bank_account_id']) ? (int) $validated['internal_bank_account_id'] : null,
                    $validated['reference'] ?? null
                );
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal of ₹' . number_format((float) $validated['amount'], 2) . ' processed successfully.');
    }
}
