<?php

namespace App\Http\Controllers;

use App\Models\LoanAccount;
use App\Models\Emi;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Crypt;

class PublicLoanController extends Controller
{
    /**
     * View public loan repayment schedule
     */
    public function viewSchedule($token): View
    {
        try {
            // Decrypt the ID or use it as is if it's a slug
            // For now, let's assume it's a base64 encoded application number to keep it simple and readable
            $applicationNumber = base64_decode($token);
            
            $loan = LoanAccount::with(['client', 'emis.collections', 'loanApplication.product'])
                ->where('application_number', $applicationNumber)
                ->firstOrFail();

            return view('public.loan-schedule', compact('loan'));
        } catch (\Exception $e) {
            abort(404, 'Invalid loan schedule link.');
        }
    }
}
