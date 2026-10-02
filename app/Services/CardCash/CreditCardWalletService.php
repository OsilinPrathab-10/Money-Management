<?php

namespace App\Services\CardCash;

use App\Models\CardCash\CreditCardWallet;
use App\Models\CardCash\CreditCardWalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditCardWalletService
{
    /**
     * Credit a wallet with ledger entry
     */
    public function creditWallet(
        CreditCardWallet $wallet,
        float $amount,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?int $userId = null
    ): CreditCardWalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Credit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $refType, $refId, $userId) {
            // Lock row for update
            $lockedWallet = CreditCardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $lockedWallet->current_balance;
            $balanceAfter = $balanceBefore + $amount;

            $lockedWallet->current_balance = $balanceAfter;
            $lockedWallet->save();

            return CreditCardWalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'transaction_type' => 'credit',
                'reference_type' => $refType,
                'reference_id' => $refId,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'transaction_date' => now(),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Debit a wallet with balance check and ledger entry
     */
    public function debitWallet(
        CreditCardWallet $wallet,
        float $amount,
        string $description,
        ?string $refType = null,
        ?int $refId = null,
        ?int $userId = null
    ): CreditCardWalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Debit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $refType, $refId, $userId) {
            // Lock row for update
            $lockedWallet = CreditCardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $lockedWallet->current_balance;
            if ($balanceBefore < $amount) {
                throw new InvalidArgumentException(sprintf(
                    "Insufficient wallet balance in '%s'. Available: ₹%s, Required: ₹%s",
                    $lockedWallet->wallet_name,
                    number_format($balanceBefore, 2),
                    number_format($amount, 2)
                ));
            }

            $balanceAfter = $balanceBefore - $amount;

            $lockedWallet->current_balance = $balanceAfter;
            $lockedWallet->save();

            return CreditCardWalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'transaction_type' => 'debit',
                'reference_type' => $refType,
                'reference_id' => $refId,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'transaction_date' => now(),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Manual balance adjustment with delta transaction
     */
    public function adjustBalance(
        CreditCardWallet $wallet,
        float $targetBalance,
        string $reason,
        ?int $userId = null
    ): CreditCardWalletTransaction {
        return DB::transaction(function () use ($wallet, $targetBalance, $reason, $userId) {
            $lockedWallet = CreditCardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $lockedWallet->current_balance;
            $delta = $targetBalance - $balanceBefore;

            $lockedWallet->current_balance = $targetBalance;
            $lockedWallet->save();

            return CreditCardWalletTransaction::create([
                'wallet_id' => $lockedWallet->id,
                'transaction_type' => 'adjustment',
                'reference_type' => 'manual_adjustment',
                'reference_id' => null,
                'amount' => abs($delta),
                'balance_before' => $balanceBefore,
                'balance_after' => $targetBalance,
                'description' => sprintf(
                    "Manual adjustment by %s: %s (from ₹%s to ₹%s)",
                    $userId ? "User #{$userId}" : "Admin",
                    $reason,
                    number_format($balanceBefore, 2),
                    number_format($targetBalance, 2)
                ),
                'transaction_date' => now(),
                'created_by' => $userId,
            ]);
        });
    }
}
