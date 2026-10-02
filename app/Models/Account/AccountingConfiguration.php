<?php

namespace App\Models\Account;

use Illuminate\Database\Eloquent\Model;

class AccountingConfiguration extends Model
{
    protected $table = 'accounting_configurations';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
