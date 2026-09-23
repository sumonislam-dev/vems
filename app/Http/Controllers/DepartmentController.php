<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Requests\DepartmentIndexRequest;
use App\Imports\DepartmentsImport;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class DepartmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-departments', only: ['index', 'show', 'export']),
            new Middleware('permission:create-departments', only: ['create', 'store', 'import']),
            new Middleware('permission:edit-departments', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:delete-departments', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of departments.
     */
    public function index(DepartmentIndexRequest $request): Response
    {
        $validated = $request->validated();

        $query = Department::with(['users', 'head']);

        // Apply search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (!empty($filters['status'])) {
                $statuses = $filters['status'];
                $query->where(function ($q) use ($statuses) {
                    foreach ($statuses as $status) {
                        if ($status === 'active') {
                            $q->orWhere('is_active', true);
                        } elseif ($status === 'inactive') {
                            $q->orWhere('is_active', false);
                        }
                    }
                });
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];

        $validSortFields = ['name', 'code', 'location', 'is_active', 'created_at', 'status'];
        if (in_array($sortColumn, $validSortFields)) {
            // Map frontend column names to database column names
            $dbColumn = $sortColumn === 'status' ? 'is_active' : $sortColumn;
            $query->orderBy($dbColumn, $sortDirection);
        }

        // Get pagination data
        $departments = $query->paginate($validated['per_page'])
            ->withQueryString()
            ->through(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'location' => $department->location,
                'phone' => $department->phone,
                'email' => $department->email,
                'is_active' => $department->is_active,
                'status' => $department->is_active ? 'active' : 'inactive',
                'users_count' => $department->users->count(),
                'head' => $department->head ? [
                    'id' => $department->head->id,
                    'name' => $department->head->name,
                    'email' => $department->head->email,
                ] : null,
                'created_at' => $department->created_at,
            ]);

        return Inertia::render('departments/index', [
            'departments' => $departments,
            'filterOptions' => [
                'statuses' => ['active', 'inactive'],
            ],
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new department.
     */
    public function create(): Response
    {
        $users = User::active()->employees()->get(['id', 'name', 'email']);

        return Inertia::render('departments/create', [
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created department in storage.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            Department::create($validated);
            return redirect()->route('departments.index')
                ->with('success', 'Department created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create department.');
        }
    }

    /**
     * Display the specified department.
     */
    public function show(Department $department): Response
    {
        $department->load(['users.roles', 'head']);

        // Get department statistics
        $stats = [
            'total_users' => $department->users->count(),
            'active_users' => $department->users->where('status', 'active')->count(),
            'drivers' => $department->users->filter(function($user) {
                return $user->roles->contains('name', 'Driver') ||
                       in_array($user->user_type, ['driver', 'transport_manager']);
            })->count(),
            'managers' => $department->users->filter(function($user) {
                return $user->roles->contains('name', 'Manager');
            })->count(),
        ];

        return Inertia::render('departments/show', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'location' => $department->location,
                'phone' => $department->phone,
                'email' => $department->email,
                'is_active' => $department->is_active,
                'budget_allocation' => $department->budget_allocation,
                'total_budget' => $department->total_budget,
                'head' => $department->head,
                'users' => $department->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'user_type' => $user->user_type,
                        'status' => $user->status,
                        'roles' => $user->roles->pluck('name'),
                        'created_at' => $user->created_at->format('M d, Y'),
                    ];
                }),
                'created_at' => $department->created_at->format('M d, Y H:i'),
                'updated_at' => $department->updated_at->format('M d, Y H:i'),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for editing the specified department.
     */
    public function edit(Department $department): Response
    {
        $users = User::active()->employees()->get(['id', 'name', 'email']);

        // Keep a non-employee head (e.g. assigned before this restriction
        // existed) selectable/visible on its own department's edit form,
        // even though it won't appear as a candidate on other departments.
        if ($department->head_id && ! $users->contains('id', $department->head_id)) {
            $currentHead = User::find($department->head_id, ['id', 'name', 'email']);
            if ($currentHead) {
                $users->push($currentHead);
            }
        }

        return Inertia::render('departments/edit', [
            'department' => $department,
            'users' => $users,
        ]);
    }

    /**
     * Update the specified department in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        try {
            $department->update($request->validated());
            return redirect()->route('departments.index')
                ->with('success', 'Department updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update department.');
        }
    }

    /**
     * Remove the specified department from storage.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->count() > 0) {
            return back()->with('error', 'Cannot delete department with active users. Please reassign users first.');
        }
        try {
            $department->delete();
            return redirect()->route('departments.index')
                ->with('success', 'Department deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete department.');
        }
    }

    /**
     * Toggle department status.
     */
    public function toggleStatus(Department $department): RedirectResponse
    {
        $department->update([
            'is_active' => !$department->is_active
        ]);

        $status = $department->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
                        ->with('success', "Department {$status} successfully.");
    }

    /**
     * Export the (filtered) department list as CSV.
     */
    public function export(DepartmentIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Department::with(['head']);

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['filters']) && ! empty($validated['filters']['status'])) {
            $statuses = $validated['filters']['status'];
            $query->where(function ($q) use ($statuses) {
                foreach ($statuses as $status) {
                    if ($status === 'active') {
                        $q->orWhere('is_active', true);
                    } elseif ($status === 'inactive') {
                        $q->orWhere('is_active', false);
                    }
                }
            });
        }

        $departments = $query->get();
        $timestamp = now()->format('Y-m-d_H-i-s');

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Name', 'Code', 'Description', 'Location', 'Phone', 'Email', 'Status', 'Head', 'Users', 'Created At']);

        foreach ($departments as $department) {
            fputcsv($output, [
                $department->id,
                $department->name,
                $department->code,
                $department->description,
                $department->location,
                $department->phone,
                $department->email,
                $department->is_active ? 'Active' : 'Inactive',
                $department->head->name ?? '',
                $department->users()->count(),
                $department->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"departments_export_{$timestamp}.csv\"");
    }

    /**
     * Bulk-create departments from an uploaded CSV/Excel file (see
     * DepartmentsImport for the expected columns).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $import = new DepartmentsImport;
        Excel::import($import, $request->file('file'));

        if ($import->failures()->isNotEmpty()) {
            $errors = $import->failures()->take(5)->map(
                fn ($failure) => "row {$failure->row()}: ".implode(', ', $failure->errors())
            )->implode('; ');

            return back()->with(
                'warning',
                "Imported {$import->imported} department(s). {$import->failures()->count()} row(s) skipped ({$errors})."
            );
        }

        return back()->with('success', "Imported {$import->imported} department(s) successfully.");
    }
}
