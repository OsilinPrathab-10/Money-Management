<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class RolesPermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0]; // group like: loan.view → "loan"
        });
        return view('admin.roles-permissions.roles', ['permissionGroups' => $permissions]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
        ]);

        // Create role
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        // Assign permissions
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully!',
            'redirect' => route('role-users') . '?success=' . urlencode('Role created successfully!')
        ]);
    }

    public function getUsers()
    {
        $roles = Role::with([
            'users' => function ($query) {
                $query->select('users.id', 'users.name');
            }
        ])
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                $userNames = $role->users->pluck('name')->filter()->values()->all();

                return [
                    'id' => $role->id,
                    'role_name' => $role->name,
                    'assigned_users' => $userNames,
                    'primary_user' => $userNames[0] ?? 'Unassigned',
                    'user_count' => count($userNames),
                    'status' => count($userNames) ? 2 : 3
                ];
            });

        return response()->json(['data' => $roles]);
    }

    public function permission()
    {
        return view('admin.roles-permissions.permission');
    }

    public function storePermission(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        // Flash success message to session for the next request
        session()->flash('success', 'Permission created successfully!');

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully!',
            'redirect' => route('role-permissions')
        ]);
    }

    public function getPermissionsData()
    {
        $permissions = Permission::with('roles')->get()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'assigned_to' => $permission->roles->pluck('name')->values()->all(),
                'created_date' => optional($permission->created_at)->format('d-m-Y'),
            ];
        });

        return response()->json(['data' => $permissions]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'original_name' => 'required|string',
            'name' => 'required|string|max:255',
            'permissions' => 'array',
        ]);

        $role = Role::where('name', $request->original_name)->firstOrFail();

        // validate unique with ignore current role id
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
        ]);

        $role->name = $request->name;
        $role->guard_name = 'web';
        $role->save();

        // Sync permissions if provided, else clear
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully!',
            'redirect' => route('role-users') . '?success=' . urlencode('Role updated successfully!')
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $role = Role::where('name', $request->name)->firstOrFail();

        if (strtolower($role->name) === 'super admin') {
            return response()->json([
                'success' => false,
                'message' => 'The Super Admin role cannot be deleted.'
            ], 403);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully!',
            'redirect' => route('role-users') . '?success=' . urlencode('Role deleted successfully!')
        ]);
    }

    public function getRolePermissions(Request $request)
    {
        $request->validate([
            'role' => 'required|string'
        ]);

        $role = Role::where('name', $request->query('role'))->first();
        if (!$role) {
            return response()->json(['permissions' => []]);
        }

        return response()->json([
            'permissions' => $role->permissions->pluck('name')->values()->all()
        ]);
    }

    public function destroyPermission(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:permissions,id',
        ]);

        $permission = Permission::findOrFail($request->id);
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully!'
        ]);
    }
}
