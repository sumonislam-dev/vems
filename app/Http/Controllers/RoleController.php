<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-roles', only: ['index', 'show']),
            new Middleware('permission:create-roles', only: ['create', 'store']),
            new Middleware('permission:edit-roles', only: ['edit', 'update']),
            new Middleware('permission:delete-roles', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Role::with(['permissions', 'users']);

        // Apply search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('guard_name', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortColumn = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortColumn, $sortDirection);

        // Get pagination data
        $roles = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Get stats with a single aggregate query
        $roleStats = Role::selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN EXISTS (SELECT 1 FROM model_has_roles mhr WHERE mhr.role_id = roles.id AND mhr.model_type = ?) THEN 1 ELSE 0 END) as with_users, '.
            'SUM(CASE WHEN EXISTS (SELECT 1 FROM role_has_permissions rhp WHERE rhp.role_id = roles.id) THEN 1 ELSE 0 END) as with_permissions, '.
            'SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM model_has_roles mhr WHERE mhr.role_id = roles.id AND mhr.model_type = ?) AND NOT EXISTS (SELECT 1 FROM role_has_permissions rhp WHERE rhp.role_id = roles.id) THEN 1 ELSE 0 END) as empty_roles',
            [User::class, User::class]
        )->first();

        $stats = [
            'total' => (int) ($roleStats->total ?? 0),
            'with_users' => (int) ($roleStats->with_users ?? 0),
            'with_permissions' => (int) ($roleStats->with_permissions ?? 0),
            'empty' => (int) ($roleStats->empty_roles ?? 0),
        ];

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/create', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $validated['guard_name'] = $validated['guard_name'] ?? 'web';

        $role = Role::create($validated);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        activity('roles')
            ->causedBy($request->user())
            ->withProperties(['new' => ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()]])
            ->event('created')
            ->log("Role \"{$role->name}\" created");

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return Inertia::render('roles/show', [
            'role' => $role,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/edit', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'guard_name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $validated['guard_name'] = $validated['guard_name'] ?? 'web';

        $oldPermissionNames = $role->permissions()->pluck('name')->all();

        $role->update($validated);

        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        $newPermissionNames = $role->permissions()->pluck('name')->all();
        sort($oldPermissionNames);
        sort($newPermissionNames);

        if ($oldPermissionNames !== $newPermissionNames) {
            activity('roles')
                ->causedBy($request->user())
                ->withProperties(['old' => ['permissions' => $oldPermissionNames], 'new' => ['permissions' => $newPermissionNames]])
                ->event('permissions_changed')
                ->log("Permissions updated for role \"{$role->name}\"");
        }

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        // Check if role has users
        if ($role->users()->count() > 0) {
            return redirect()
                ->route('roles.index')
                ->with('error', 'Cannot delete role that has users assigned to it!');
        }

        $roleName = $role->name;
        $role->delete();

        activity('roles')
            ->withProperties(['old' => ['name' => $roleName]])
            ->event('deleted')
            ->log("Role \"{$roleName}\" deleted");

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully!');
    }
}
