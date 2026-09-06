<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanAssignmentTracker extends Model
{
    protected $table = 'loan_assignment_trackers';

    protected $fillable = [
        'last_assigned_staff_id',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'last_assigned_staff_id');
    }
}
