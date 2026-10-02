<?php

namespace App\Http\Controllers;

use App\Services\MenuAccessService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleMenuController extends Controller
{
    /**
     * Roles excluded from sidebar menu customization.
     */
    protected array $excludedRoles = [
        'Client',
        'CreditVerifier',
        'creditverifier',
        'creditverfier',
        'Customer',
        'customer',
        'mahesh',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request, MenuAccessService $menus)
    {
        $roles = Role::where('guard_name', 'web')
            ->whereNotIn('name', $this->excludedRoles)
            ->orderBy('name')
            ->get();

        $selectedName = $request->query('role', 'Staff');
        $role = $roles->firstWhere('name', $selectedName) ?? $roles->first();

        $catalog = $menus->catalog();
        $assigned = $role ? $menus->keysForRole($role) : [];
        $recommendedKeys = $role ? $menus->recommendedKeysForRole($role->name) : [];

        return view('admin.roles-permissions.menus', [
            'roles' => $roles,
            'role' => $role,
            'catalog' => $catalog,
            'assignedKeys' => $assigned,
            'recommendedKeys' => $recommendedKeys,
        ]);
    }

    public function update(Request $request, MenuAccessService $menus)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
            'menus' => 'nullable|array',
            'menus.*' => 'string',
            'apply_recommended' => 'nullable|boolean',
        ]);

        $role = Role::where('name', $request->role)->where('guard_name', 'web')->firstOrFail();

        $excludedLower = array_map('strtolower', $this->excludedRoles);
        if (in_array(strtolower($role->name), $excludedLower, true)) {
            return back()->with('error', "Menus for role \"{$role->name}\" are managed separately.");
        }

        $menuKeys = $request->boolean('apply_recommended')
            ? $menus->recommendedKeysForRole($role->name)
            : $request->input('menus', []);

        $menus->syncRoleMenus($role, $menuKeys);

        $msg = $request->boolean('apply_recommended')
            ? "Recommended menus applied for role \"{$role->name}\"."
            : "Menus updated for role \"{$role->name}\".";

        return redirect()
            ->route('roles.menus', ['role' => $role->name])
            ->with('success', $msg);
    }
}
