<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Emi;
use App\Models\LoanProduct;
use App\Models\Bank;
use App\Http\Resources\DashboardResource;
use App\Http\Resources\LoanProductResource;
use App\Http\Resources\SlideResource;
use App\Models\Slide;

class DashboardControllerApi extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $client = Client::where('user_id', $user->id)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client record not found for this user.'
            ], 404);
        }

        $loanProducts = LoanProduct::with('loanType')
            ->where('status', 'active')
            ->get();

        $overdueCount = Emi::whereHas('loanAccount', function ($q) use ($client) {
                $q->where('client_id', $client->id);
            })
            ->where('status', 'overdue')
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data fetched successfully',
            'data' => [
                'client' => new DashboardResource($client, $overdueCount),
                'loan_products' => LoanProductResource::collection($loanProducts)->additional([
                    'view' => 'summary'
                ]),
                'banners' => SlideResource::collection(
                      Slide::where('type', 'banner')->get()
                  )
            ],
        ], 200);
    }

    public function getAllBankDetails(Request $request)
    {
        $searchTerm = $request->query('search');
        $ifscCode = $request->query('ifsc');

        $query = Bank::query();

        if ($searchTerm) {
            $needle = mb_strtolower($searchTerm);
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(bank_name) LIKE ?', ['%' . $needle . '%'])
                  ->orWhereRaw('LOWER(CAST(bank_id AS CHAR)) LIKE ?', ['%' . $needle . '%']);
            });
        }

        if ($ifscCode) {
            $query->whereRaw('LOWER(ifsc_code) LIKE ?', ['%' . mb_strtolower($ifscCode) . '%']);
        }

        $banks = $query->get()->map(function ($bank) {
            return [
                'BankID' => $bank->bank_id,
                'BankName' => $bank->bank_name,
                'IFSCCODE' => $bank->ifsc_code,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Bank details fetched successfully.',
            'total' => count($banks),
            'data' => $banks
        ]);
    }
}
