<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KycRule implements ValidationRule
{
    public function __construct(string $type)
    {
        $this->type = strtolower($type);
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isValid = match ($this->type) {
            'aadhaar' => preg_match('/^[2-9]{1}[0-9]{11}$/', $value),
            'pan'     => preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($value)),
            'bank'    => preg_match('/^[0-9]{9,18}$/', $value),
            'ifsc'    => preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($value)),
            'pan_name' => preg_match('/^[A-Z\s]{3,75}$/', strtoupper($value)),
            'pan_dob'  => preg_match('/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/(19|20)\d{2}$/', $value),
            default   => false,
        };

        if (! $isValid) {
            $fail($this->errorMessage($attribute));
        }
    }

    /**
     * Custom error messages for each type.
     */
    protected function errorMessage(string $attribute): string
    {
        return match ($this->type) {
            'aadhaar' => "The {$attribute} must be a valid 12-digit Aadhaar number.",
            'pan'     => "The {$attribute} must be a valid PAN number (e.g., ABCDE1234F).",
            'bank'    => "The {$attribute} must be a valid bank account number (9–18 digits).",
            'ifsc'    => "The {$attribute} must be a valid IFSC code (e.g., HDFC0001234).",
            'pan_name' => "The {$attribute} must contain only letters and spaces (as per PAN name).",
            'pan_dob'  => "The {$attribute} must be a valid date in DD/MM/YYYY format (as per PAN DOB).",
            default   => "Invalid value for {$attribute}.",
        };
    }
}
