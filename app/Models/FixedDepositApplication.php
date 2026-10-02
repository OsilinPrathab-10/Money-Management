<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedDepositApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'application_number',
        'client_id',
        'scheme_id',
        'deposit_amount',
        'tenure',
        'tenure_type',
        'interest_rate',
        'interest_amount',
        'maturity_amount',
        'deposit_date',
        'start_date',
        'maturity_date',
        'nominee_name',
        'nominee_relation',
        'payout_option',
        'status',
        'remarks',
        'applied_at',
        'approved_at',
        'booked_at',
        'approved_by',
        'rejected_by',
        'booked_by',
        'created_by',
        'fixed_deposit_id',
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'interest_amount' => 'decimal:2',
        'maturity_amount' => 'decimal:2',
        'deposit_date' => 'date',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'booked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $application) {
            if (empty($application->application_number)) {
                $application->application_number = self::generateApplicationNumber();
            }
            if (empty($application->applied_at)) {
                $application->applied_at = now();
            }
        });
    }

    public static function generateApplicationNumber(): string
    {
        $prefix = 'FDA' . now()->format('Ymd');
        $last = self::withTrashed()
            ->where('application_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('application_number');

        $seq = 1;
        if ($last && preg_match('/(\d{4})$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(FixedDepositScheme::class, 'scheme_id');
    }

    public function fixedDeposit(): BelongsTo
    {
        return $this->belongsTo(FixedDeposit::class, 'fixed_deposit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'booked' => 'Booked',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'rejected' => 'danger',
            'booked' => 'success',
            default => 'secondary',
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
