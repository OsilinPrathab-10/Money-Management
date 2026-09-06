<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'amount',
        'date',
        'description',
    ];
    
    protected $casts = [
        'date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
