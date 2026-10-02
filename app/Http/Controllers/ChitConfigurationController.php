<?php

namespace App\Http\Controllers;

use App\Models\ChitConfiguration;
use Illuminate\Http\Request;

class ChitConfigurationController extends Controller
{
    public function index()
    {
        $referralPercentage = ChitConfiguration::get('referral_commission_percentage', '10.00');
        $calculationBase = ChitConfiguration::get('referral_calculation_base', 'group_commission');
        $referralEnabled = ChitConfiguration::get('referral_enabled', '1') === '1';
        $penaltyType = ChitConfiguration::get('penalty_type', 'fixed');
        $penaltyValue = ChitConfiguration::get('penalty_value', '0.00');
        $penaltyGraceDays = ChitConfiguration::get('penalty_grace_days', '0');
        $penaltyEnabled = ChitConfiguration::get('penalty_enabled', '0') === '1';
        $settlementProcessingFee = ChitConfiguration::get('settlement_processing_fee', '0');
        $settlementDocumentCharges = ChitConfiguration::get('settlement_document_charges', '0');
        $settlementOtherCharges = ChitConfiguration::get('settlement_other_charges', '0');
        $settlementBankingCharges = ChitConfiguration::get('settlement_banking_charges', '0');

        return view('admin.chit.configuration.index', compact(
            'referralPercentage',
            'calculationBase',
            'referralEnabled',
            'penaltyType',
            'penaltyValue',
            'penaltyGraceDays',
            'penaltyEnabled',
            'settlementProcessingFee',
            'settlementDocumentCharges',
            'settlementOtherCharges',
            'settlementBankingCharges',
        ));
    }

    public function update(Request $request)
    {
        $rules = [];
        if ($request->has('referral_commission_percentage') || $request->has('referral_calculation_base') || $request->has('referral_enabled')) {
            $rules['referral_commission_percentage'] = 'required|numeric|min:0|max:100';
            $rules['referral_calculation_base'] = 'required|in:group_commission,chit_value';
            $rules['referral_enabled'] = 'nullable|in:0,1';
        }
        if ($request->has('penalty_type') || $request->has('penalty_value') || $request->has('penalty_grace_days') || $request->has('penalty_enabled')) {
            $rules['penalty_type'] = 'required|in:fixed,percentage';
            $rules['penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_grace_days'] = 'required|integer|min:0';
            $rules['penalty_enabled'] = 'nullable|in:0,1';
        }
        if ($request->has('settlement_processing_fee') || $request->has('settlement_document_charges') || $request->has('settlement_other_charges') || $request->has('settlement_banking_charges')) {
            $rules['settlement_processing_fee'] = 'required|numeric|min:0';
            $rules['settlement_document_charges'] = 'required|numeric|min:0';
            $rules['settlement_other_charges'] = 'required|numeric|min:0';
            $rules['settlement_banking_charges'] = 'required|numeric|min:0';
        }

        if (empty($rules)) {
            return redirect()->back()->withErrors(['message' => 'No configuration settings submitted.']);
        }

        $request->validate($rules);

        $message = 'Chit configurations updated successfully.';

        if ($request->has('referral_commission_percentage')) {
            ChitConfiguration::set('referral_commission_percentage', number_format($request->referral_commission_percentage, 2, '.', ''));
            ChitConfiguration::set('referral_calculation_base', $request->referral_calculation_base);
            ChitConfiguration::set('referral_enabled', $request->boolean('referral_enabled') ? '1' : '0');
            $message = 'Referral & Commission configuration saved successfully.';
        }

        if ($request->has('penalty_type')) {
            ChitConfiguration::set('penalty_type', $request->penalty_type);
            ChitConfiguration::set('penalty_value', number_format($request->penalty_value, 2, '.', ''));
            ChitConfiguration::set('penalty_grace_days', (string) $request->penalty_grace_days);
            ChitConfiguration::set('penalty_enabled', $request->boolean('penalty_enabled') ? '1' : '0');
            $message = 'Late Penalty configuration saved successfully.';
        }

        if ($request->has('settlement_processing_fee')) {
            ChitConfiguration::set('settlement_processing_fee', number_format($request->settlement_processing_fee, 2, '.', ''));
            ChitConfiguration::set('settlement_document_charges', number_format($request->settlement_document_charges, 2, '.', ''));
            ChitConfiguration::set('settlement_other_charges', number_format($request->settlement_other_charges, 2, '.', ''));
            ChitConfiguration::set('settlement_banking_charges', number_format($request->settlement_banking_charges ?? 0, 2, '.', ''));
            $message = 'Settlement charge defaults saved successfully.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'referral_enabled' => ChitConfiguration::get('referral_enabled', '0') === '1',
            'penalty_enabled' => ChitConfiguration::get('penalty_enabled', '0') === '1',
        ]);
    }
}
