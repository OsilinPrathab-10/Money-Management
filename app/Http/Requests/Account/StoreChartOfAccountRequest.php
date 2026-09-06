<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_code' => 'required|string|min:1|max:20|unique:chart_of_accounts,account_code,NULL,id,created_by,' . creatorId(),
            'account_name' => ['required', 'string', 'max:255', 'regex:/^(?=.*[A-Za-z])[A-Za-z0-9&().,\-\s]+$/'],
            'level' => 'nullable|integer|min:1',
            'normal_balance' => 'required|string',
            'opening_balance' => 'required|numeric|min:0',
            'current_balance' => 'required|numeric|min:0|same:opening_balance',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'account_type_id' => 'nullable|exists:account_types,id',
            'parent_account_id' => 'nullable'
        ];
    }

    public function messages(): array
    {
        return [
            'account_name.regex' => 'Account name must contain letters and only valid symbols.',
            'current_balance.same' => 'Current balance must match opening balance during account creation.',
            'opening_balance.required' => 'Opening Balance is required.',
            'current_balance.required' => 'Current Balance is required.',
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