<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Ensure Agent role_menus includes every menu marked for Agent in verticalMenu.json.
 * Fixes live agents seeing only a partial sidebar when role_menus was incomplete.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'Agent')->first();
        if (! $role) {
            return;
        }

        $menus = app(\App\Services\MenuAccessService::class);
        $jsonKeys = $menus->fallbackKeysFromJsonRoles(['Agent']);
        if ($jsonKeys === []) {
            return;
        }

        $existing = DB::table('role_menus')
            ->where('role_id', $role->id)
            ->pluck('menu_key')
            ->all();

        $missing = array_values(array_diff($jsonKeys, $existing));
        if ($missing === []) {
            return;
        }

        $now = now();
        $rows = array_map(fn ($key) => [
            'role_id' => $role->id,
            'menu_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ], $missing);

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('role_menus')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Keep assigned menus — safe no-op
    }
};
