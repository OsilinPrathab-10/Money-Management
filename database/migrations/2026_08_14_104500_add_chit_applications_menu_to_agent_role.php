<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'Agent')->first();
        if (! $role) {
            return;
        }

        $menuKey = 'chit.applications.index.chit-applications';
        $exists = DB::table('role_menus')
            ->where('role_id', $role->id)
            ->where('menu_key', $menuKey)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('role_menus')->insert([
            'role_id' => $role->id,
            'menu_key' => $menuKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $role = Role::where('name', 'Agent')->first();
        if (! $role) {
            return;
        }

        DB::table('role_menus')
            ->where('role_id', $role->id)
            ->where('menu_key', 'chit.applications.index.chit-applications')
            ->delete();
    }
};
