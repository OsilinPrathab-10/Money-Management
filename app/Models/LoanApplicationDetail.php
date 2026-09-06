<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApplicationDetail extends Model
{
    protected $fillable = [
        'loan_application_id',
        'details',
        'vehicle_image',
        'business_proofs',
    ];

    protected $casts = [
        'details' => 'array',
        'business_proofs' => 'array',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function getVehicleImageUrlAttribute()
    {
        return $this->vehicle_image
            ? asset('storage/' . $this->vehicle_image)
            : null;
    }
}
