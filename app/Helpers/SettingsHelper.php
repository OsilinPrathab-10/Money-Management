<?php

namespace App\Helpers;

use App\Models\Appearance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingsHelper
{
    /**
     * When false, DB is not queried (avoids errors + log spam before migrations).
     */
    private static ?bool $appearanceTableReady = null;

    /**
     * Get appearance setting with fallback to config
     */
    public static function get($key, $default = null)
    {
        if (self::$appearanceTableReady === null) {
            try {
                self::$appearanceTableReady = Schema::hasTable('appearances');
            } catch (\Throwable) {
                self::$appearanceTableReady = false;
            }
        }

        if (! self::$appearanceTableReady) {
            return config("variables.{$key}") ?? $default;
        }

        // Try to get from cache first (cache for 1 hour)
        return Cache::remember("appearance_setting_{$key}", 3600, function () use ($key, $default) {
            $appearance = Appearance::where('type', 'web')->first();
            
            if ($appearance) {
                // Map old admin setting keys to new appearance fields
                $fieldMap = [
                    'admin_title' => 'title',
                    'admin_subtitle' => 'subtitle',
                    'admin_logo' => 'logo',
                    'admin_logo_dark' => 'logo', // Use same logo for both
                    'admin_favicon' => 'favicon',
                    'primary_color' => 'primary_color',
                    'secondary_color' => 'secondary_color',
                    'footer_text' => 'footer_text'
                ];
                
                $field = $fieldMap[$key] ?? $key;
                
                if (isset($appearance->$field) && $appearance->$field !== null && $appearance->$field !== '') {
                    return $appearance->$field;
                }
            }
            
            // Otherwise, try config file
            if (config("variables.{$key}")) {
                return config("variables.{$key}");
            }
            
            // Finally, return default
            return $default;
        });
    }
    
    /**
     * Clear settings cache
     */
    public static function clearCache()
    {
        $keys = [
            'admin_logo',
            'admin_logo_dark',
            'admin_favicon',
            'admin_title',
            'admin_subtitle',
            'primary_color',
            'secondary_color',
            'footer_text',
            'loader_animation',
        ];
        
        foreach ($keys as $key) {
            Cache::forget("appearance_setting_{$key}");
        }
    }
}
