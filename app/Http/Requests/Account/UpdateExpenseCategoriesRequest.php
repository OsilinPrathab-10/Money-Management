<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('expense_categories', 'category_name')
                    ->ignore($this->route('expensecategories'))
                    ->where(function ($query) {
                        return $query->where('created_by', creatorId());
                    })
            ],
            'category_code' => [
                'required',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('expense_categories', 'category_code')
                    ->ignore($this->route('expensecategories'))
                    ->where(function ($query) {
                        return $query->where('created_by', creatorId());
                    })
            ],
            'gl_account_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable',
            'is_active' => 'boolean'
        ];
    }
}