<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class SmsOtpLog extends Model
{
    public const PURPOSE_LOGIN = 'first_login';
    public const PURPOSE_RESET_MPIN = 'forgot_mpin';
    public const PURPOSE_PASSWORD = 'forgot_password';

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_TEST = 'test';

    protected $fillable = [
        'purpose',
        'mobile',
        'user_id',
        'client_id',
        'status',
        'provider',
        'provider_message',
        'ip_address',
    ];

    public static function purposes(): array
    {
        return [
            self::PURPOSE_LOGIN => 'Login OTP',
            self::PURPOSE_RESET_MPIN => 'Reset MPIN OTP',
            self::PURPOSE_PASSWORD => 'Password OTP',
        ];
    }

    public function purposeLabel(): string
    {
        $purpose = strtolower((string) $this->purpose);

        if (in_array($purpose, ['first_login', 'login'], true)) {
            return 'Login OTP';
        }
        if (in_array($purpose, ['forgot_mpin', 'reset_mpin', 'mpin'], true)) {
            return 'Reset MPIN OTP';
        }
        if (in_array($purpose, ['forgot_password', 'reset_password', 'password'], true)) {
            return 'Password OTP';
        }

        return ucfirst(str_replace('_', ' ', (string) $this->purpose));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function record(array $data): ?self
    {
        try {
            $purpose = (string) ($data['purpose'] ?? self::PURPOSE_LOGIN);
            if (! isset(self::purposes()[$purpose])) {
                $purpose = self::PURPOSE_LOGIN;
            }

            $mobile = preg_replace('/\D+/', '', (string) ($data['mobile'] ?? '')) ?? '';
            if (strlen($mobile) > 10) {
                $mobile = substr($mobile, -10);
            }

            return self::create([
                'purpose' => $purpose,
                'mobile' => $mobile,
                'user_id' => $data['user_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'status' => $data['status'] ?? self::STATUS_SENT,
                'provider' => $data['provider'] ?? 'msg91',
                'provider_message' => isset($data['provider_message'])
                    ? substr((string) $data['provider_message'], 0, 500)
                    : null,
                'ip_address' => $data['ip_address'] ?? request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write SMS OTP log', [
                'error' => $e->getMessage(),
                'purpose' => $data['purpose'] ?? null,
                'mobile' => $data['mobile'] ?? null,
            ]);

            return null;
        }
    }
}
