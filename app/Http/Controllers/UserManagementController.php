<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display user management page
     */
    public function index(): View
    {
        // Get user statistics
        $totalUsers = User::role(['Admin', 'Agent', 'Staff'])->count();
        $activeUsers = User::role(['Admin', 'Agent', 'Staff'])->where('status', 'active')->count();
        $inactiveUsers = User::role(['Admin', 'Agent', 'Staff'])->where('status', 'inactive')->count();
        $roles = Role::whereIn('name', ['Admin', 'Agent', 'Staff'])->get();
        $branches = Branch::all();
        $locations = Location::orderBy('name')->get();

        return view('admin.user-management.index', [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'roles' => $roles,
            'branches' => $branches,
            'locations' => $locations,
        ]);
    }

    /**
     * Get users data for DataTable
     */
    public function getData(Request $request): JsonResponse
    {
        $columns = [
            1 => 'id',
            2 => 'name',
            3 => 'email',
            4 => 'status',
        ];

        $totalData = User::role(['Admin', 'Agent', 'Staff'])->count();
        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'id';
        $dir = $request->input('order.0.dir') ?? 'desc';

        $query = User::role(['Admin', 'Agent', 'Staff']);

        // Search handling
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');

            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $totalFiltered = $query->count();

        $users = $query->with('roles')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        if (!empty($users)) {
            $ids = $start;

            foreach ($users as $user) {
                $nestedData['id'] = $user->id;
                $nestedData['fake_id'] = ++$ids;
                $nestedData['name'] = $user->name;
                $nestedData['email'] = $user->email;
                $nestedData['phone'] = $user->phone;
                
                // Get role from Spatie Permission
                $roleName = $user->roles->first() ? $user->roles->first()->name : 'User';
                $nestedData['role'] = ucfirst($roleName);
                
                $nestedData['status'] = $user->status ?? 'active';

                $data[] = $nestedData;
            }
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'code' => 200,
            'data' => $data,
        ]);
    }

    /**
     * Toggle user status
     */
    public function toggleStatus($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Protect Super Admin from being moderated by others
            if ($user->email === 'admin@example.com' && (!auth()->check() || auth()->user()->email !== 'admin@example.com')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin status cannot be changed.'
                ], 403);
            }

            $user->status = $user->status === 'active' ? 'inactive' : 'active';
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'status' => $user->status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z][A-Za-z .]{1,254}$/'],
                'email' => 'required|email|unique:users,email',
                'phone' => ['required', 'regex:/^[1-9][0-9]{9}$/', 'unique:users,phone'],
                'password' => 'required|string|min:8|confirmed',
            ], [
                'name.required' => 'Name is required',
                'name.regex' => 'Name must contain only letters and spaces',
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
                'email.unique' => 'This email is already registered',
                'phone.required' => 'Phone is required',
                'phone.regex' => 'Phone number must be 10 digits (numbers only)',
                'phone.unique' => 'This phone number is already registered',
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Passwords do not match',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => bcrypt($validated['password']),
                'plain_password' => $validated['password'],
                'status' => 'active',
            ]);

            // Assign role if provided
            if ($request->has('role')) {
                $user->assignRole($request->role);
            }

            // Fire notification event
            event(new \App\Events\NewUserRegistrationEvent($user));

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            // Protect Super Admin from being modified by others
            if ($user->email === 'admin@example.com' && auth()->user()->email !== 'admin@example.com') {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to modify the Super Admin account.'
                ], 403);
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s.]+$/'],
                'email' => 'required|email|unique:users,email,' . $id,
                'phone' => ['required', 'regex:/^[1-9][0-9]{9}$/', 'unique:users,phone,' . $id],
                'role' => 'nullable|string|exists:roles,name',
                'password' => 'nullable|string|min:8|confirmed',
            ], [
                'name.required' => 'Name is required',
                'name.regex' => 'Name must contain only letters, dots and spaces',
                'email.required' => 'Email is required',
                'email.email' => 'Please enter a valid email address',
                'email.unique' => 'This email is already registered',
                'phone.required' => 'Phone is required',
                'phone.regex' => 'Phone number must be 10 digits (numbers only)',
                'phone.unique' => 'This phone number is already registered',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Passwords do not match',
            ]);

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->phone = $validated['phone'];
            
            if (!empty($validated['password'])) {
                $user->password = bcrypt($validated['password']);
                $user->plain_password = $validated['password'];
            }
            
            $user->save();

            if (!empty($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Protect Super Admin from deletion
            if ($user->email === 'admin@example.com') {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin account cannot be deleted.'
                ], 403);
            }

            // Prevent deleting current logged-in user
            if (auth()->check() && $user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            // Protect Super Admin from role changes
            if ($user->email === 'admin@example.com' && (!auth()->check() || auth()->user()->email !== 'admin@example.com')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin role cannot be changed.'
                ], 403);
            }

            $roleName = $request->input('role');

            if (!$roleName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role is required'
                ], 422);
            }

            // Sync user roles (replaces existing roles)
            $user->syncRoles([$roleName]);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
                'role' => ucfirst($roleName)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role: ' . $e->getMessage()
            ], 500);
        }
    }
}
