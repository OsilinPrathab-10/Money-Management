<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'branch',
        'location',
    ];

    public function staffs()
    {
        return $this->hasMany(Staff::class);
    }
}
