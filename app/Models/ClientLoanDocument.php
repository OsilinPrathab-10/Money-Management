<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ClientLoanDocument extends Model
{
    protected $fillable = [
        'loan_account_id',
        'client_id',
        'document_type',
        'document_title',
        'file_path',
        'file_name',
        'file_size',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return url('storage/' . $this->file_path);
    }

    /**
     * Get the loan account that owns the document
     */
    public function loanAccount()
    {
        return $this->belongsTo(LoanAccount::class);
    }

    /**
     * Get the client that owns the document
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who generated the document
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get full file path
     */
    public function getFullPathAttribute(): string
    {
        return storage_path('app/public/' . $this->file_path);
    }

    /**
     * Check if file exists
     */
    public function fileExists(): bool
    {
        return file_exists($this->full_path);
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSizeAttribute()
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function isVisible(): bool
    {
        // Non-NOC documents are always visible
        if ($this->document_type !== 'noc') {
            return true;
        }

        // NOC must have loan closed
        if (!$this->loanAccount || !$this->loanAccount->closed_at) {
            return false;
        }

        // Show NOC only after 24 hours
        return Carbon::parse($this->loanAccount->closed_at)
            ->addHours(24)
            ->isPast();
    }
}
