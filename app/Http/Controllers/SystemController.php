<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Models\LoginLog;
use App\Models\CollectionLog;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SystemController extends Controller
{
    /**
     * Display server status page
     */
    public function serverStatus()
    {
        // Get server information
        $serverInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];

        // Database connection status
        try {
            DB::connection()->getPdo();
            $databaseStatus = 'Connected';
            $databaseVersion = DB::select('SELECT VERSION() as version')[0]->version ?? 'Unknown';
        } catch (\Exception $e) {
            $databaseStatus = 'Disconnected';
            $databaseVersion = 'N/A';
        }

        // Storage information
        $storagePath = storage_path();
        $totalSpace = disk_total_space($storagePath);
        $freeSpace = disk_free_space($storagePath);
        $usedSpace = $totalSpace - $freeSpace;
        $usedPercentage = ($usedSpace / $totalSpace) * 100;

        $storageInfo = [
            'total' => $this->formatBytes($totalSpace),
            'used' => $this->formatBytes($usedSpace),
            'free' => $this->formatBytes($freeSpace),
            'used_percentage' => round($usedPercentage, 2),
        ];

        // PHP Extensions
        $requiredExtensions = [
            'openssl' => extension_loaded('openssl'),
            'pdo' => extension_loaded('pdo'),
            'mbstring' => extension_loaded('mbstring'),
            'tokenizer' => extension_loaded('tokenizer'),
            'xml' => extension_loaded('xml'),
            'ctype' => extension_loaded('ctype'),
            'json' => extension_loaded('json'),
            'bcmath' => extension_loaded('bcmath'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
            'zip' => extension_loaded('zip'),
        ];

        return view('admin.system.server-status', compact(
            'serverInfo',
            'databaseStatus',
            'databaseVersion',
            'storageInfo',
            'requiredExtensions'
        ));
    }

    /**
     * Display database backup page
     */
    public function databaseBackup()
    {
        // Get list of existing backups
        $backupPath = storage_path('app/backups');
        
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $backups = [];
        $files = File::files($backupPath);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $backups[] = [
                    'name' => basename($file),
                    'size' => $this->formatBytes(filesize($file)),
                    'date' => Carbon::createFromTimestamp(filemtime($file))->format('d-m-Y h:i A'),
                    'timestamp' => filemtime($file),
                ];
            }
        }

        // Sort by timestamp descending
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return view('admin.system.database-backup', compact('backups'));
    }

    /**
     * Create database backup
     */
    public function createBackup(Request $request)
    {
        try {
            $backupPath = storage_path('app/backups');
            
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            $filename = 'backup_' . date('Y-m-d_His') . '.sql';
            $filepath = $backupPath . '/' . $filename;

            $database = (string) config('database.connections.mysql.database');
            $username = (string) config('database.connections.mysql.username');
            $password = (string) config('database.connections.mysql.password');
            $host = (string) config('database.connections.mysql.host');

            // Create backup using mysqldump
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Try to find mysqldump in common XAMPP and MySQL paths
                $possiblePaths = [
                    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                    'D:\\xampp\\mysql\\bin\\mysqldump.exe',
                    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                    'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
                    'mysqldump' // Fallback to PATH
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
                // Linux/Unix command
                $passwordArg = !empty($password) ? ' --password=' . escapeshellarg($password) : '';
                $command = sprintf(
                    'mysqldump --host=%s --user=%s%s %s > %s 2>&1',
                    escapeshellarg($host),
                    escapeshellarg($username),
                    $passwordArg,
                    escapeshellarg($database),
                    escapeshellarg($filepath)
                );
            }

            $hasExec = is_callable('exec') && false === stripos((string)ini_get('disable_functions'), 'exec');
            $backupSuccess = false;

            if ($hasExec) {
                $output = [];
                $returnVar = null;
                \exec($command, $output, $returnVar);

                if ($returnVar === 0) {
                    $backupSuccess = true;
                } else {
                    Log::warning('mysqldump failed, falling back to PHP backup', ['output' => implode("\n", $output)]);
                }
            }

            if (!$backupSuccess) {
                // Exec disabled or mysqldump failed, use pure PHP backup
                $this->backupDatabasePhp($filepath);
            }

            // Check if file was created and has content
            if (File::exists($filepath) && filesize($filepath) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Database backup created successfully',
                    'filename' => $filename
                ]);
            } else {
                // Delete empty file if created
                if (File::exists($filepath)) {
                    File::delete($filepath);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create database backup. The backup file is empty.'
                ], 500);
            }
        } catch (\Throwable $e) {
            Log::error('Backup exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download database backup
     */
    public function downloadBackup($filename)
    {
        try {
            $filepath = storage_path('app/backups/' . $filename);

            if (!File::exists($filepath)) {
                return redirect()->back()->with('error', 'Backup file not found');
            }

            return response()->download($filepath);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to download backup: ' . $e->getMessage());
        }
    }

    /**
     * Delete database backup
     */
    public function deleteBackup($filename)
    {
        try {
            $filepath = storage_path('app/backups/' . $filename);

            if (!File::exists($filepath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            File::delete($filepath);

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function escapeWindowsArg(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    /**
     * Create database backup using pure PHP (Fallback)
     */
    private function backupDatabasePhp(string $filepath)
    {
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $handle = fopen($filepath, 'w');
        if (!$handle) {
            throw new \Exception('Cannot open file for writing: ' . $filepath);
        }

        fwrite($handle, "-- Shanmuga Finance Database Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $tableName) {
            $stmt = $pdo->query('SHOW CREATE TABLE `' . $tableName . '`');
            $createTable = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($createTable) {
                fwrite($handle, "DROP TABLE IF EXISTS `" . $tableName . "`;\n");
                $createSql = $createTable['Create Table'] ?? $createTable['Create View'] ?? '';
                fwrite($handle, $createSql . ";\n\n");
            }

            if (isset($createTable['Create View'])) {
                continue;
            }

            $stmt = $pdo->query('SELECT * FROM `' . $tableName . '`');
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $keys = array_keys($row);
                $values = array_values($row);
                
                $escapedValues = array_map(function($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $pdo->quote($value);
                }, $values);

                $sql = "INSERT INTO `" . $tableName . "` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
                fwrite($handle, $sql);
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    /**
     * Display login/logout logs for the last 30 days
     */
    public function loginLog(Request $request)
    {
        $search = trim((string) $request->input('search'));
        
        $logs = LoginLog::where('created_at', '>=', now()->subDays(30))
            ->when($search !== '', function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('user_name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.system.login-log', compact('logs', 'search'));
    }

    /**
     * Clear login logs from the last 30 days
     */
    public function clearLoginLog(Request $request)
    {
        try {
            LoginLog::where('created_at', '>=', now()->subDays(30))->delete();
            return redirect()->back()->with('success', 'Login logs for the last 30 days cleared successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to clear login logs: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear login logs.');
        }
    }

    /**
     * Display collection logs for the last 30 days
     */
    public function collectionLog(Request $request)
    {
        $search = trim((string) $request->input('search'));

        $logs = CollectionLog::where('created_at', '>=', now()->subDays(30))
            ->when($search !== '', function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('client_name', 'like', "%{$search}%")
                      ->orWhere('loan_number', 'like', "%{$search}%")
                      ->orWhere('emi_number', 'like', "%{$search}%")
                      ->orWhere('collected_by_name', 'like', "%{$search}%")
                      ->orWhere('payment_mode', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.system.collection-log', compact('logs', 'search'));
    }

    /**
     * Clear collection logs from the last 30 days
     */
    public function clearCollectionLog(Request $request)
    {
        try {
            CollectionLog::where('created_at', '>=', now()->subDays(30))->delete();
            return redirect()->back()->with('success', 'Collection logs for the last 30 days cleared successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to clear collection logs: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear collection logs.');
        }
    }

    /**
     * Export collection logs for the last 30 days as CSV, respecting the search filter
     */
    public function exportCollectionLog(Request $request)
    {
        $search = trim((string) $request->input('search'));

        $logs = CollectionLog::where('created_at', '>=', now()->subDays(30))
            ->when($search !== '', function($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('client_name', 'like', "%{$search}%")
                      ->orWhere('loan_number', 'like', "%{$search}%")
                      ->orWhere('emi_number', 'like', "%{$search}%")
                      ->orWhere('collected_by_name', 'like', "%{$search}%")
                      ->orWhere('payment_mode', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        $fileName = "collection_logs_" . now()->format('Y-m-d_His') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'Client Name',
            'Loan Number',
            'EMI Number',
            'Collected Amount (Rs)',
            'Payment Mode',
            'Collected By Name',
            'Collected At',
            'IP Address'
        ];

        $callback = function() use ($logs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->client_name,
                    $log->loan_number,
                    $log->emi_number,
                    $log->collected_amount,
                    ucfirst($log->payment_mode),
                    $log->collected_by_name,
                    $log->collected_at ? $log->collected_at->format('d-m-Y h:i A') : 'N/A',
                    $log->ip_address
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
