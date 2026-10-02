<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasObfuscatedRouteKey;

class LoanApplication extends Model
{
    use HasFactory, HasObfuscatedRouteKey, SoftDeletes;

    protected $fillable = [
        'application_number',
        'client_id',
        'loan_code',
        'loan_mode',
        'loan_amount_min',
        'loan_amount_max',
        'loan_amount',
        'credit_limit',
        'tenure_min',
        'tenure_max',
        'tenure',
        'emi_day',
        'emi_start_date',
        'emi_start_month',
        'emi_start_year',
        'emi_start_week',
        'emi_start_day',
        'payment_method',
        'payment_gateway',
        'status',
        'assigned_to',
        'assigned_at',
        'loan_code_video',
        'loan_agreement_pdf',
        'interest_rate',
        'total_payable',
        'term_unit',
        'remarks',
        'applied_at',
        'approved_at',
        'disbursed_at',
        'live_photo',
        'cash_photo',
        'collateral_document',
        'other_document',
    ];

    protected $casts = [
        'loan_amount_min' => 'integer',
        'loan_amount_max' => 'integer',
        'loan_amount' => 'integer',
        'interest_rate' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime'
    ];

    public function getEmiStartDateAttribute(): ?string
    {
        if ($this->emi_start_year && $this->emi_start_month && $this->emi_start_day) {
            return sprintf('%04d-%02d-%02d', (int) $this->emi_start_year, (int) $this->emi_start_month, (int) $this->emi_start_day);
        }
        return null;
    }

    public function setEmiStartDateAttribute($value): void
    {
        if (! empty($value)) {
            $date = \Carbon\Carbon::parse($value);
            $this->attributes['emi_start_year'] = $date->year;
            $this->attributes['emi_start_month'] = $date->month;
            $this->attributes['emi_start_day'] = $date->day;
        }
    }

    /**
     * Max EMI day for a frequency and start month (28/29/30/31), not a static 28.
     */
    public static function maxEmiDayFor(?string $frequency, $startDate = null): int
    {
        $frequency = strtolower(trim((string) $frequency));
        if (in_array($frequency, ['daily', 'day', 'days'], true)) {
            return 1;
        }
        if (in_array($frequency, ['weekly', 'week', 'weeks'], true)) {
            return 7;
        }

        if ($startDate) {
            try {
                $date = $startDate instanceof \Carbon\Carbon
                    ? $startDate
                    : \Carbon\Carbon::parse($startDate);

                return (int) $date->daysInMonth;
            } catch (\Throwable $e) {
                return 31;
            }
        }

        return 31;
    }

    public static function emiDaysFor(?string $frequency, $startDate = null): array
    {
        return range(1, self::maxEmiDayFor($frequency, $startDate));
    }

    public function getAppliedDateAttribute()
    {
        return $this->applied_at ? \Carbon\Carbon::parse($this->applied_at) : $this->created_at;
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_code', 'loan_code');
    }

    public function applicationDetail()
    {
        return $this->hasOne(LoanApplicationDetail::class);
    }

    public function loanAccount()
    {
        return $this->hasOne(LoanAccount::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function disbursementDetail()
    {
        return $this->hasOne(DisbursementDetail::class);
    }

    public function getStatusLabelAttribute(): string
    {
        $status = strtolower((string) ($this->status ?? 'pending'));
        return match ($status) {
            'pending', 'applied' => 'Pending Approval',
            'process', 'in_progress' => 'In Progress',
            'approved' => 'Approved',
            'disbursed' => 'Disbursed',
            'rejected' => 'Rejected',
            'closed', 'completed', 'foreclosed' => $status === 'completed' ? 'Completed' : 'Closed',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        $status = strtolower((string) ($this->status ?? 'pending'));
        return match ($status) {
            'pending', 'applied' => 'warning',
            'approved' => 'info',
            'process', 'in_progress' => 'primary',
            'disbursed' => 'success',
            'rejected' => 'danger',
            'closed', 'completed', 'foreclosed' => $status === 'completed' ? 'success' : 'info',
            default => 'secondary',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->getStatusColorAttribute();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->applied_at)) {
                $model->applied_at = now();
            }

            if (empty($model->application_number)) {
                $datePart = now()->format('Ymd');
                $count = self::withTrashed()->whereDate('created_at', now()->toDateString())->count();
                $nextSeq = $count + 1;

                while (self::withTrashed()->where('application_number', 'APP' . $datePart . str_pad($nextSeq, 4, '0', STR_PAD_LEFT))->exists()) {
                    $nextSeq++;
                }

                $model->application_number = 'APP' . $datePart . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
