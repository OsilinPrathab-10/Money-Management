<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Log;

class ClearLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clear-old';
    protected $description = 'Delete all log files older than 7 days in storage/logs.';

    public function handle()
    {
        $logPath = storage_path('logs');
        Log::info("Starting to clear old log files from {$logPath}");
        $deleted = 0;

        foreach (File::files($logPath) as $file) {
            // Only target files with .log extension
            if ($file->getExtension() !== 'log') {
                continue;
            }

            $fileMTime = Carbon::createFromTimestamp($file->getMTime());
            $this->info("Found log file: {$file->getFilename()} modified at {$fileMTime}");

            // Check if file is older than 7 days
            if ($fileMTime->lt(now()->subDays(7))) {
                File::delete($file);
                $deleted++;
                $msg = "Deleted old log file: {$file->getFilename()}";
                $this->info($msg);
                Log::info($msg);
            }
        }

        $msgSummary = "Deleted {$deleted} old log file(s) successfully.";
        $this->info($msgSummary);
        Log::info($msgSummary);
        return Command::SUCCESS;
    }
}
