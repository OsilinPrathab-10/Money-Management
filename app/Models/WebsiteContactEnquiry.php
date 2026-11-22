<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteContactEnquiry extends Model
{
    protected $fillable = [
        'reference', 'name', 'email', 'phone', 'subject', 'message', 'status',
        'client_id', 'handled_by', 'called_at', 'admin_notes', 'ip_address',
    ];

    protected $casts = ['called_at' => 'datetime'];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
