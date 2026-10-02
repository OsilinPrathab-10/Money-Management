<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileAppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_type',
        'app_logo',
        'splash_image',
        'primary_color',
        'secondary_color',
        'background_color',
        'overview_tab_color',
        'loan_tab_color',
        'chit_tab_color',
        'fixed_deposit_tab_color',
        'banner_images',
        'loan_banner_images',
        'chit_banner_images',
        'fd_banner_images',
        'onboarding_screens',
        'theme_mode',
        'welcome_message',
        'maintenance_mode',
    ];

    protected $casts = [
        'banner_images' => 'array',
        'loan_banner_images' => 'array',
        'chit_banner_images' => 'array',
        'fd_banner_images' => 'array',
        'onboarding_screens' => 'array',
        'maintenance_mode' => 'boolean',
    ];

    /**
     * Retrieve or seed default setting for specified app type ('customer' or 'agent').
     */
    public static function getOrCreate(string $appType): self
    {
        return static::firstOrCreate(
            ['app_type' => $appType],
            [
                'primary_color' => '#696CFF',
                'secondary_color' => '#8592A3',
                'background_color' => '#F5F5F9',
                'overview_tab_color' => '#696CFF',
                'loan_tab_color' => '#00CFDD',
                'chit_tab_color' => '#7367F0',
                'fixed_deposit_tab_color' => '#FF4C51',
                'banner_images' => [],
                'loan_banner_images' => [],
                'chit_banner_images' => [],
                'fd_banner_images' => [],
                'onboarding_screens' => [],
                'theme_mode' => 'light',
                'welcome_message' => 'Welcome to ' . ucfirst($appType) . ' App',
                'maintenance_mode' => false,
            ]
        );
    }
}
