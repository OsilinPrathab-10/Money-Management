<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations to sync complete Agent role menus.
     */
    public function up(): void
    {
        $role = Role::where('name', 'Agent')->first();
        if (! $role) {
            return;
        }

        $service = app(\App\Services\MenuAccessService::class);
        $recommendedKeys = $service->recommendedKeysForRole('Agent');
        if ($recommendedKeys === []) {
            return;
        }

        $service->syncRoleMenus($role, $recommendedKeys);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};
