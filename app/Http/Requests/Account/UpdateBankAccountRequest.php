<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bankAccountId = $this->route('bankaccount')->id ?? null;
        return [
            'account_number' => [
                'required',
                'regex:/^(?!0+$)\d{9,18}$/',
                Rule::unique('bank_accounts', 'account_number')
                    ->ignore($bankAccountId)
                    ->where(function ($query) {
                        return $query->where('bank_name', $this->input('bank_name'));
                    }),
            ],
            'account_name' => 'required|string|max:100',
            'bank_name' => 'required|string|max:100',
            'branch_name' => 'nullable|string|max:100',
            'account_type' => 'required',
            'payment_gateway' => 'nullable|string|max:100',
            'upi_id' => 'nullable|string|max:100',
            'qr_code' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'opening_balance' => 'required|numeric|min:0',
            'current_balance' => 'required|numeric|min:0',
            'iban' => 'nullable|string|max:34',
            'swift_code' => 'nullable|string|max:11',
            'routing_number' => 'nullable|string|max:20',
            'ifsc_code' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.regex' => __('Account number must be 9 to 18 digits and cannot be all zeros.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $opening = $this->input('opening_balance');
        $this->merge([
            'account_number' => preg_replace('/\s+/', '', (string) $this->input('account_number')),
            'current_balance' => $opening,
        ]);
    }
}
