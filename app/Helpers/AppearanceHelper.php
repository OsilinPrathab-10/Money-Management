<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AppearanceHelper
{
    protected static $configPath;
    
    public static function init()
    {
        self::$configPath = config_path('appearance.php');
    }
    
    /**
     * Get appearance setting value
     */
    public static function get($key, $default = null)
    {
        self::init();
        
        $current = config('appearance.current', []);
        return $current[$key] ?? config('appearance.default.' . $key, $default);
    }
    
    /**
     * Set appearance setting value
     */
    public static function set($key, $value)
    {
        self::init();
        
        $config = self::loadConfig();
        $config['current'][$key] = $value;
        
        return self::saveConfig($config);
    }
    
    /**
     * Update multiple appearance settings
     */
    public static function update($data)
    {
        self::init();
        
        $config = self::loadConfig();
        
        foreach ($data as $key => $value) {
            $config['current'][$key] = $value;
        }
        
        return self::saveConfig($config);
    }
    
    /**
     * Reset to default settings
     */
    public static function reset()
    {
        self::init();
        
        $config = self::loadConfig();
        $config['current'] = $config['default'];
        
        return self::saveConfig($config);
    }
    
    /**
     * Get all current settings
     */
    public static function all()
    {
        self::init();
        
        return config('appearance.current', config('appearance.default', []));
    }
    
    /**
     * Load config from file
     */
    protected static function loadConfig()
    {
        if (File::exists(self::$configPath)) {
            return include self::$configPath;
        }
        
        return config('appearance', []);
    }
    
    /**
     * Save config to file
     */
    protected static function saveConfig($config)
    {
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        try {
            File::put(self::$configPath, $content);
            
            // Clear config cache to reload
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate(self::$configPath, true);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save appearance config: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create appearance object for compatibility
     */
    public static function getAppearanceObject()
    {
        $data = self::all();
        
        return (object) [
            'primary_color' => $data['primary_color'] ?? '#696cff',
            'secondary_color' => $data['secondary_color'] ?? '#8592a3',
            'theme_mode' => $data['theme_mode'] ?? 'light',
            'title' => $data['title'] ?? 'Loan App',
            'subtitle' => $data['subtitle'] ?? '',
            'logo' => $data['logo'] ?? '',
            'logo_dark' => $data['logo_dark'] ?? '',
            'favicon' => $data['favicon'] ?? '',
            'footer_text' => $data['footer_text'] ?? '',
        ];
    }
}
