<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChitScheme extends Model
{
    use SoftDeletes;

    public const FOREMAN_COMMISSION_LABEL = 'Foreman Commission (பதிவிற்காக)';

    public const REGISTRATION_REGISTERED = 'registered';

    public const REGISTRATION_NON_REGISTERED = 'non_registered';

    protected $fillable = [
        'name', 'scheme_code', 'chit_value', 'total_members', 'duration_months',
        'foreman_commission_month',
        'client_wise_foreman_commission',
        'client_wise_foreman_collection_month',
        'installment_amount', 'commission_pct', 'commission_amount',
        'auction_type', 'scheme_type', 'installment_frequency',
        'fixed_return_amount', 'is_private',
        'description', 'branch_id', 'status', 'created_by', 'payout_schedule',
        'referral_commission_pct', 'post_payout_installment_adjustment',
        'registration_type', 'registration_number', 'registration_date',
        'registering_authority', 'registration_office',
        'registration_certificate_number', 'registration_certificate_path',
        'registration_valid_from', 'registration_valid_until',
    ];

    protected $casts = [
        'chit_value'                        => 'decimal:2',
        'installment_amount'                => 'decimal:2',
        'commission_pct'                    => 'decimal:2',
        'commission_amount'                 => 'decimal:2',
        'fixed_return_amount'               => 'decimal:2',
        'post_payout_installment_adjustment'=> 'decimal:2',
        'is_private'                        => 'boolean',
        'payout_schedule'         => 'array',
        'referral_commission_pct' => 'decimal:2',
        'foreman_commission_month'=> 'integer',
        'client_wise_foreman_commission' => 'decimal:2',
        'client_wise_foreman_collection_month' => 'integer',
        'registration_date'       => 'date',
        'registration_valid_from' => 'date',
        'registration_valid_until'=> 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function chitGroups()
    {
        return $this->hasMany(ChitGroup::class, 'scheme_id');
    }

    public function hasDependentGroups(): bool
    {
        return $this->chitGroups()->withTrashed()->exists();
    }

    public function getSchemeTypeLabelAttribute(): string
    {
        return match ($this->scheme_type) {
            'fixed'        => 'Fixed Chit',
            'auction'      => 'Auction-Based',
            'flexible'     => 'Flexible/Variable',
            'fixed_return' => 'Fixed Return',
            'daily_weekly' => 'Daily/Weekly',
            'group_based'  => 'Group-Based (Private)',
            default        => ucfirst($this->scheme_type ?? 'fixed'),
        };
    }

    public function getSchemeTypeBadgeColorAttribute(): string
    {
        return match ($this->scheme_type) {
            'fixed'        => 'primary',
            'auction'      => 'warning',
            'flexible'     => 'info',
            'fixed_return' => 'success',
            'daily_weekly' => 'danger',
            'group_based'  => 'dark',
            default        => 'secondary',
        };
    }

    public function isRegistered(): bool
    {
        return $this->registration_type === self::REGISTRATION_REGISTERED;
    }

    public function getRegistrationTypeLabelAttribute(): string
    {
        return $this->isRegistered() ? 'Registered' : 'Non-Registered';
    }

    public function foremanCommissionMonth(): int
    {
        if (isset($this->foreman_commission_month) && (int) $this->foreman_commission_month === 0) {
            return 0;
        }

        $month = (int) ($this->foreman_commission_month ?? 1);

        return $month > 0 ? $month : 1;
    }

    public function isForemanCommissionMonth(int $monthNumber): bool
    {
        $month = $this->foremanCommissionMonth();

        return $month > 0 && $monthNumber === $month;
    }

    /**
     * Month 0 means there is no dedicated "all collection is FC" month.
     * Commission is a fixed per-member amount collected once.
     */
    public function usesClientWiseForemanCommission(): bool
    {
        return $this->foremanCommissionMonth() === 0
            && (float) ($this->client_wise_foreman_commission ?? 0) > 0;
    }

    public function clientWiseForemanAmount(): float
    {
        return $this->usesClientWiseForemanCommission()
            ? round((float) $this->client_wise_foreman_commission, 2)
            : 0.0;
    }

    public function clientWiseForemanCollectionMonth(): int
    {
        if (! $this->usesClientWiseForemanCommission()) {
            return 0;
        }

        $month = (int) ($this->client_wise_foreman_collection_month ?? 0);
        $max = max(1, (int) ($this->duration_months ?? 1));

        return ($month >= 1 && $month <= $max) ? $month : 0;
    }

    public function clientWiseForemanExtraForShare(int $monthNumber, float $sharePct = 100.0): float
    {
        if (! $this->usesClientWiseForemanCommission()) {
            return 0.0;
        }

        if ($monthNumber !== $this->clientWiseForemanCollectionMonth()) {
            return 0.0;
        }

        return round($this->clientWiseForemanAmount() * (max(0.0, $sharePct) / 100.0), 2);
    }

    /**
     * First scheduled installment for APIs (not chit_value / duration).
     */
    public function apiInstallmentAmount(): float
    {
        foreach ($this->payout_schedule ?? [] as $entry) {
            if (! is_array($entry) || ! isset($entry['installment_amount'])) {
                continue;
            }
            $amount = (float) $entry['installment_amount'];
            if ($amount > 0) {
                return $amount;
            }
        }

        return (float) $this->installment_amount;
    }

    public function isAuctionBased(): bool
    {
        return in_array($this->scheme_type, ['auction', 'flexible']);
    }

    public function isShortFrequency(): bool
    {
        return in_array($this->installment_frequency, ['daily', 'weekly'], true);
    }

    public function periodUnit(): string
    {
        return match ($this->installment_frequency) {
            'weekly' => 'week',
            'daily' => 'day',
            default => 'month',
        };
    }

    public function periodUnitLabel(): string
    {
        return match ($this->periodUnit()) {
            'week' => 'Week',
            'day' => 'Day',
            default => 'Month',
        };
    }

    public function periodUnitLabelPlural(): string
    {
        return match ($this->periodUnit()) {
            'week' => 'Weeks',
            'day' => 'Days',
            default => 'Months',
        };
    }

    public static function generateCode(): string
    {
        $last = self::withTrashed()->latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'SCH' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    public static function schemeTypes(): array
    {
        return [
            'fixed'        => 'Fixed Chit',
            'auction'      => 'Auction-Based Chit',
            'flexible'     => 'Flexible/Variable Chit',
            'fixed_return' => 'Fixed Return Chit',
            'daily_weekly' => 'Daily/Weekly Chit',
            'group_based'  => 'Group-Based Chit (Private)',
        ];
    }

    public static function registrationTypes(): array
    {
        return [
            self::REGISTRATION_REGISTERED     => 'Registered',
            self::REGISTRATION_NON_REGISTERED => 'Non-Registered',
        ];
    }

    public static function frequencies(): array
    {
        return [
            'monthly' => 'Monthly',
            'weekly'  => 'Weekly',
        ];
    }

    public function flyerUrl(): ?string
    {
        if (! $this->id) {
            return null;
        }

        // Use url() (current request host), not APP_URL. Path must not start with
        // /public — that collides with the Laravel public/ document root and 404s.
        return url('/schemes/' . $this->id . '/flyer');
    }

    public function flyerPdfUrl(): ?string
    {
        if (! $this->id) {
            return null;
        }

        return url('/schemes/' . $this->id . '/flyer.pdf');
    }
}
