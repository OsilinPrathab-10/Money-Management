<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $chartOfAccountId = $this->route('chartofaccount') ? $this->route('chartofaccount')->id : null;
        
        return [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code,' . $chartOfAccountId . ',id,created_by,' . creatorId(),
            'account_name' => 'required|string|max:255',
            'level' => 'nullable|integer|min:1',
            'normal_balance' => 'required|string',
            'opening_balance' => 'nullable|numeric|min:0',
            'current_balance' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'account_type_id' => 'nullable|exists:account_types,id',
            'parent_account_id' => 'nullable'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->account_type_id && $this->normal_balance) {
                $accountType = \App\Models\Account\AccountType::with('category')->find($this->account_type_id);
                if ($accountType && $accountType->category) {
                    $categoryType = strtolower($accountType->category->type);
                    $normalBalance = strtolower($this->normal_balance);
                    
                    // Enforce Accounting Rules: Assets/Expenses -> Debit, Liabilities/Equity/Income -> Credit
                    if (in_array($categoryType, ['assets', 'asset', 'expenses', 'expense'])) {
                        if ($normalBalance !== 'debit') {
                            $validator->errors()->add('normal_balance', "Normal Balance must be Debit for {$accountType->category->name} type accounts.");
                        }
                    } elseif (in_array($categoryType, ['liabilities', 'liability', 'equity', 'income', 'revenue', 'revenues'])) {
                        if ($normalBalance !== 'credit') {
                            $validator->errors()->add('normal_balance', "Normal Balance must be Credit for {$accountType->category->name} type accounts.");
                        }
                    }
                }
            }
        });
    }
}