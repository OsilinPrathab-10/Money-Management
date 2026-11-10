<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CreditScoreHistory;
use Illuminate\View\View;

class ClientViewCibilController extends Controller
{
    public function index(int $clientId): View
    {
        $client = Client::with('kycDetail')->findOrFail($clientId);

        $histories = CreditScoreHistory::query()
            ->where('client_id', $client->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();

        $latestScore = CreditScoreHistory::query()
            ->where('client_id', $client->id)
            ->where('status', 'success')
            ->whereNotNull('score')
            ->orderByDesc('created_at')
            ->value('score');

        return view('admin.clients.client-view-cibil', compact('client', 'histories', 'latestScore'));
    }
}
