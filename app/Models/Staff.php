<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staffs';

    protected $fillable = [
        'user_id',
        'branch_id',
        'name',
        'email',
        'phone',
        'salary_amount',
        'profile_photo',
        'aadhar_photo',
        'bank_account_photo',
        'salary_details',
        'role',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    protected $casts = [
        'salary_details' => 'array',
        'salary_amount' => 'decimal:2',
    ];

    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function expenses()
    {
        return $this->hasMany(StaffExpense::class);
    }

    public function advances()
    {
        return $this->hasMany(StaffAdvance::class);
    }
}
