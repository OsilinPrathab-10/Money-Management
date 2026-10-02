<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Ensure core roles (1: Admin, 2: Staff, 3: Agent, 4: Customer) exist in database
     * and sync recommended menu permissions for all active roles.
     */
    public function up(): void
    {
        $coreRoles = ['Admin', 'Staff', 'Agent', 'Customer', 'Client'];
        foreach ($coreRoles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $service = app(\App\Services\MenuAccessService::class);
        foreach (['Admin', 'Staff', 'Agent'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $recommended = $service->recommendedKeysForRole($roleName);
                if (! empty($recommended)) {
                    $service->syncRoleMenus($role, $recommended);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};
