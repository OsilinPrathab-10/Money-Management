<?php

namespace App\Services\FixedDeposit;

use App\Models\Client;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function getOrCreateWallet(int $clientId): CustomerWallet
    {
        return CustomerWallet::firstOrCreate(
            ['client_id' => $clientId],
            [
                'balance' => 0,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]
        );
    }

    public function credit(
        int $clientId,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($clientId, $amount, $description, $referenceType, $referenceId, $meta) {
            $wallet = $this->getOrCreateWallet($clientId);

            if ($wallet->status !== 'active') {
                throw new RuntimeException('Customer wallet is not active.');
            }

            $wallet = CustomerWallet::where('id', $wallet->id)->lockForUpdate()->first();
            $wallet->balance = round(((float) $wallet->balance) + $amount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'client_id' => $clientId,
                'type' => 'credit',
                'amount' => round($amount, 2),
                'balance_after' => $wallet->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_by' => Auth::id(),
            ]);
        });
    }

    public function debit(
        int $clientId,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($clientId, $amount, $description, $referenceType, $referenceId, $meta) {
            $wallet = $this->getOrCreateWallet($clientId);
            $wallet = CustomerWallet::where('id', $wallet->id)->lockForUpdate()->first();

            if (((float) $wallet->balance) < $amount) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $wallet->balance = round(((float) $wallet->balance) - $amount, 2);
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'client_id' => $clientId,
                'type' => 'debit',
                'amount' => round($amount, 2),
                'balance_after' => $wallet->balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta' => $meta ?: null,
                'created_by' => Auth::id(),
            ]);
        });
    }

    public function balanceForClient(int $clientId): float
    {
        return (float) ($this->getOrCreateWallet($clientId)->balance ?? 0);
    }
}
