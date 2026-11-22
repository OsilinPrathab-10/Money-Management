<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteLeadApplication extends Model
{
    protected $fillable = [
        'application_ref', 'loan_product_id', 'client_id', 'applicant_name', 'email', 'phone',
        'pan_number', 'requested_amount', 'requested_tenure_months', 'employment_type', 'monthly_income',
        'city', 'message', 'status', 'form_data', 'assigned_to', 'loan_application_id', 'admin_notes',
        'called_at', 'ip_address',
    ];

    protected $casts = [
        'form_data' => 'array',
        'called_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
