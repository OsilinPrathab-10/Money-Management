<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\AutoBackupHelper;

class AutoBackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:auto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically backup database based on configured frequency';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if auto backup is enabled
        if (!config('auto-backup.enabled')) {
            $this->info('Auto backup is disabled');
            return 0;
        }

        $frequency = config('auto-backup.frequency');
        $lastBackupAt = config('auto-backup.last_backup_at');

        // Check if backup is needed based on frequency
        if (!$this->shouldBackup($frequency, $lastBackupAt)) {
            $this->info('Backup not needed yet based on frequency: ' . $frequency);
            return 0;
        }

        try {
            // Delete old auto backup
            $this->deleteOldAutoBackup($frequency);

            // Create new backup
            $filename = $this->createBackup($frequency);

            // Update last backup time
            AutoBackupHelper::updateLastBackupTime();

            $this->info('Auto backup created successfully: ' . $filename);
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create auto backup: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check if backup should be created based on frequency
     */
    private function shouldBackup($frequency, $lastBackupAt)
    {
        if (!$lastBackupAt) {
            return true; // First backup
        }

        $lastBackup = Carbon::parse($lastBackupAt);
        $now = Carbon::now();

        switch ($frequency) {
            case 'daily':
                // Backup if last backup was yesterday or earlier
                return !$lastBackup->isToday();

            case 'weekly':
                // Backup if it's Monday and last backup was not this week
                return $now->isMonday() && !$lastBackup->isCurrentWeek();

            case 'monthly':
                // Backup if it's 1st of month and last backup was not this month
                return $now->day === 1 && !$lastBackup->isCurrentMonth();

            default:
                return false;
        }
    }

    /**
     * Delete old auto backup based on frequency
     */
    private function deleteOldAutoBackup($frequency)
    {
        $backupPath = config('auto-backup.backup_path');
        
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
            return;
        }

        // Get all auto backup files for this frequency
        $pattern = "auto_{$frequency}_*.sql";
        $files = File::glob($backupPath . '/' . $pattern);

        // Delete all old auto backups (we keep only the latest)
        foreach ($files as $file) {
            File::delete($file);
            $this->info('Deleted old backup: ' . basename($file));
        }
    }

    /**
     * Create database backup
     */
    private function createBackup($frequency)
    {
        $backupPath = config('auto-backup.backup_path');
        
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        // Generate filename based on frequency
        $filename = $this->generateFilename($frequency);
        $filepath = $backupPath . '/' . $filename;

        // Get database credentials
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        // Create mysqldump command
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'D:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                'mysqldump'
            ];
            $mysqldumpPath = 'mysqldump';
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $mysqldumpPath = $path;
                    break;
                }
            }
            $passwordArg = !empty($password) ? ' --password=' . $this->escapeWindowsArg($password) : '';
            $command = '"' . $mysqldumpPath . '"'
                . ' --host=' . $this->escapeWindowsArg($host)
                . ' --user=' . $this->escapeWindowsArg($username)
                . $passwordArg
                . ' ' . $this->escapeWindowsArg($database)
                . ' --result-file=' . $this->escapeWindowsArg($filepath)
                . ' 2>&1';
        } else {
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s 2>&1',
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($database),
                escapeshellarg($filepath)
            );
        }

        // Execute backup
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('mysqldump command failed');
        }

        return $filename;
    }

    /**
     * Generate backup filename based on frequency
     */
    private function generateFilename($frequency)
    {
        $now = Carbon::now();

        switch ($frequency) {
            case 'daily':
                return 'auto_daily_' . $now->format('Y-m-d') . '.sql';

            case 'weekly':
                return 'auto_weekly_' . $now->format('Y-W') . '.sql';

            case 'monthly':
                return 'auto_monthly_' . $now->format('Y-m') . '.sql';

            default:
                return 'auto_backup_' . $now->format('Y-m-d_His') . '.sql';
        }
    }

    private function escapeWindowsArg(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }
}
