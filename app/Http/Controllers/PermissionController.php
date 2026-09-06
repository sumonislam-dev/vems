<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-permissions', only: ['index', 'show']),
            new Middleware('permission:edit-permissions', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Permission::with(['roles', 'users']);

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
        $permissions = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Get stats with a single aggregate query
        $permissionStats = Permission::selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN EXISTS (SELECT 1 FROM role_has_permissions rhp WHERE rhp.permission_id = permissions.id) THEN 1 ELSE 0 END) as with_roles, '.
            'SUM(CASE WHEN EXISTS (SELECT 1 FROM model_has_permissions mhp WHERE mhp.permission_id = permissions.id AND mhp.model_type = ?) THEN 1 ELSE 0 END) as with_users, '.
            'SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM role_has_permissions rhp WHERE rhp.permission_id = permissions.id) AND NOT EXISTS (SELECT 1 FROM model_has_permissions mhp WHERE mhp.permission_id = permissions.id AND mhp.model_type = ?) THEN 1 ELSE 0 END) as unused',
            [User::class, User::class]
        )->first();

        $stats = [
            'total' => (int) ($permissionStats->total ?? 0),
            'with_roles' => (int) ($permissionStats->with_roles ?? 0),
            'with_users' => (int) ($permissionStats->with_users ?? 0),
            'unused' => (int) ($permissionStats->unused ?? 0),
        ];

        return Inertia::render('permissions/index', [
            'permissions' => $permissions,
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return Inertia::render('permissions/create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'nullable|string|max:255',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $validated['guard_name'] = $validated['guard_name'] ?? 'web';

        $permission = Permission::create($validated);

        if (! empty($validated['roles'])) {
            $permission->roles()->sync($validated['roles']);
        }

        activity('permissions')
            ->causedBy($request->user())
            ->withProperties(['new' => ['name' => $permission->name]])
            ->event('created')
            ->log("Permission \"{$permission->name}\" created");

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permission created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Permission $permission)
    {
        $permission->load(['roles', 'users']);

        return Inertia::render('permissions/show', [
            'permission' => $permission,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Permission $permission)
    {
        $permission->load('roles');
        $roles = Role::orderBy('name')->get();

        return Inertia::render('permissions/edit', [
            'permission' => $permission,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,'.$permission->id,
            'guard_name' => 'nullable|string|max:255',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $validated['guard_name'] = $validated['guard_name'] ?? 'web';

        $oldRoleNames = $permission->roles()->pluck('name')->all();

        $permission->update($validated);

        if (isset($validated['roles'])) {
            $permission->roles()->sync($validated['roles']);
        }

        $newRoleNames = $permission->roles()->pluck('name')->all();
        sort($oldRoleNames);
        sort($newRoleNames);

        if ($oldRoleNames !== $newRoleNames) {
            activity('permissions')
                ->causedBy($request->user())
                ->withProperties(['old' => ['roles' => $oldRoleNames], 'new' => ['roles' => $newRoleNames]])
                ->event('roles_changed')
                ->log("Roles updated for permission \"{$permission->name}\"");
        }

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permission updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        // Check if permission has roles or users
        if ($permission->roles()->count() > 0 || $permission->users()->count() > 0) {
            return redirect()
                ->route('permissions.index')
                ->with('error', 'Cannot delete permission that is assigned to roles or users!');
        }

        $permissionName = $permission->name;
        $permission->delete();

        activity('permissions')
            ->withProperties(['old' => ['name' => $permissionName]])
            ->event('deleted')
            ->log("Permission \"{$permissionName}\" deleted");

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permission deleted successfully!');
    }
}
