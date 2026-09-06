<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoanDocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',          // e.g. 'noc', 'loan_agreement', 'statement'
        'title',         // document title
        'name',          // backward compatibility alias
        'header',        // HTML for header section
        'footer',        // HTML for footer section
        'logo_path',     // optional logo file stored in storage/app/public
        'body',          // main HTML content with placeholders
        'active',        // template availability flag
    ];

    /**
     * Accessor: get full logo URL.
     */
    public function getLogoUrlAttribute()
    {
        if (!$this->logo_path) {
            return null;
        }

        // If already a valid URL, return as-is
        if (filter_var($this->logo_path, FILTER_VALIDATE_URL)) {
            return $this->logo_path;
        }

        // Otherwise, build full asset path
        return asset('storage/' . $this->logo_path);
    }
}
