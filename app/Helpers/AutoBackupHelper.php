<?php

namespace App\Helpers;

class AutoBackupHelper
{
    /**
     * Save auto backup configuration
     */
    public static function saveConfig($enabled, $frequency)
    {
        $configPath = config_path('auto-backup.php');
        
        $config = [
            'enabled' => (bool) $enabled,
            'frequency' => $frequency,
            'last_backup_at' => config('auto-backup.last_backup_at'),
            'backup_path' => storage_path('app/backups'),
            'keep_backups' => 1,
        ];
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        file_put_contents($configPath, $content);
        
        // Clear config cache
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        
        return true;
    }
    
    /**
     * Update last backup time
     */
    public static function updateLastBackupTime()
    {
        $configPath = config_path('auto-backup.php');
        
        $config = [
            'enabled' => config('auto-backup.enabled'),
            'frequency' => config('auto-backup.frequency'),
            'last_backup_at' => now()->toDateTimeString(),
            'backup_path' => storage_path('app/backups'),
            'keep_backups' => 1,
        ];
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        file_put_contents($configPath, $content);
        
        // Clear config cache
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        
        return true;
    }
}
