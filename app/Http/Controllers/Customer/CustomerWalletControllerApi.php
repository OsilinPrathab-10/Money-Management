<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\FixedDeposit\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerWalletControllerApi extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    protected function authenticatedClient(): Client
    {
        $user = Auth::user();
        if (! $user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401));
        }

        $client = $user->client
            ?? Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();

        if (! $client) {
            abort(response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404));
        }

        return $client;
    }

    public function balance(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        
        $balance = $this->walletService->balanceForClient($client->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balance,
                'balance_formatted' => '₹' . number_format($balance, 2),
            ]
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        
        $wallet = $this->walletService->getOrCreateWallet($client->id);
        
        $transactions = $wallet->transactions()
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
