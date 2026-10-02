<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('accountcategory')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('account_categories', 'name')
                    ->ignore($categoryId)
                    ->where(function ($query) {
                        return $query->where('created_by', creatorId());
                    })
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('account_categories', 'code')
                    ->ignore($categoryId)
                    ->where(function ($query) {
                        return $query->where('created_by', creatorId());
                    })
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['assets', 'liabilities', 'equity', 'revenue', 'expenses']),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ];
    }
}
