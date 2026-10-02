<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    protected $fillable = [
        'request_number',
        'full_name',
        'email',
        'mobile',
        'reason',
        'client_id',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function generateRequestNumber(): string
    {
        $prefix = 'ADR-' . now()->format('Ymd') . '-';
        $count = static::where('request_number', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public static function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    public static function findMatchingClient(string $email, string $mobile): ?Client
    {
        $normalizedMobile = static::normalizeMobile($mobile);

        return Client::query()
            ->where(function ($q) use ($email, $normalizedMobile) {
                $q->where('client_email', $email);

                if ($normalizedMobile !== '') {
                    $q->orWhere('client_phone', $normalizedMobile)
                        ->orWhere('alternate_phone', $normalizedMobile)
                        ->orWhere('client_phone', 'like', '%' . $normalizedMobile)
                        ->orWhere('alternate_phone', 'like', '%' . $normalizedMobile);
                }
            })
            ->first();
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => '<span class="badge bg-label-warning">Pending</span>',
            'under_review' => '<span class="badge bg-label-info">Under Review</span>',
            'approved' => '<span class="badge bg-label-primary">Approved</span>',
            'rejected' => '<span class="badge bg-label-danger">Rejected</span>',
            'completed' => '<span class="badge bg-label-success">Completed</span>',
            default => '<span class="badge bg-label-secondary">' . e($this->status) . '</span>',
        };
    }
}
