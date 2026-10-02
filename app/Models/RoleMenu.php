<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RoleMenu extends Model
{
    protected $fillable = [
        'role_id',
        'menu_key',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
