# Laravel Spatie Role Management System

This directory contains the complete role and permission management system using Laravel Spatie Permission package.

## Overview

The role management system provides comprehensive access control with:
- **Roles**: Groups of permissions that can be assigned to users
- **Permissions**: Individual access rights for specific actions
- **User Assignment**: Users can have multiple roles and direct permissions
- **Guard Support**: Separate permission sets for web and API guards

## Features

### Role Management (`/roles`)
- **Role Listing**: View all roles with permission and user counts
- **Role Creation**: Create new roles with permission assignment
- **Role Editing**: Modify existing roles and their permissions
- **Role Deletion**: Remove roles (with safety checks for assigned users)
- **Permission Grouping**: Permissions organized by category for easy management

### Permission Management (`/permissions`)
- **Permission Listing**: View all permissions with role and user counts
- **Permission Creation**: Create new permissions with role assignment
- **Permission Editing**: Modify existing permissions and their roles
- **Permission Deletion**: Remove permissions (with safety checks)
- **Usage Tracking**: See which roles and users have each permission

## Database Structure

### Tables Created by Spatie
- `roles` - Role definitions
- `permissions` - Permission definitions
- `model_has_permissions` - Direct user permissions
- `model_has_roles` - User role assignments
- `role_has_permissions` - Role permission assignments

### Key Fields
- **Roles**: `id`, `name`, `guard_name`, `created_at`, `updated_at`
- **Permissions**: `id`, `name`, `guard_name`, `created_at`, `updated_at`

## Default Roles & Permissions

### Roles Created by Seeder
1. **super-admin** - Full system access (all permissions)
2. **admin** - Administrative access (most permissions)
3. **manager** - Management level access (limited permissions)
4. **driver** - Driver-specific access (driver portal only)
5. **employee** - Basic user access (view only)

### Permission Categories
1. **User Management**: view, create, edit, delete users
2. **Driver Management**: view, edit, delete drivers
3. **Product Management**: view, create, edit, delete, export products
4. **Vehicle Management**: view, create, edit, delete vehicles
5. **Role Management**: view, create, edit, delete roles
6. **Permission Management**: view, create, edit, delete permissions
7. **Dashboard Access**: view dashboard
8. **Driver Portal**: access driver-specific features

## Backend Implementation

### Models
- **Role**: Extends Spatie Role with custom methods
- **Permission**: Extends Spatie Permission with custom methods
- **User**: Uses HasRoles trait for role/permission functionality

### Controllers
- **RoleController**: Complete CRUD for role management
- **PermissionController**: Complete CRUD for permission management

### Key Methods
```php
// User role/permission methods (via HasRoles trait)
$user->assignRole('admin');
$user->givePermissionTo('edit users');
$user->hasRole('admin');
$user->can('edit users');

// Role permission methods
$role->givePermissionTo('view users');
$role->hasPermissionTo('view users');
```

## Frontend Implementation

### Role Pages
- **roles/index.tsx** - Role listing with search and stats
- **roles/create.tsx** - Role creation with permission assignment
- **roles/edit.tsx** - Role editing with permission management
- **roles/show.tsx** - Role details with users and permissions

### Permission Pages
- **permissions/index.tsx** - Permission listing with usage stats
- **permissions/create.tsx** - Permission creation with role assignment
- **permissions/edit.tsx** - Permission editing
- **permissions/show.tsx** - Permission details with roles and users

### UI Features
- **Permission Grouping**: Permissions organized by category
- **Bulk Selection**: Select all/deselect all permissions
- **Usage Statistics**: See role/permission usage counts
- **Safety Checks**: Prevent deletion of assigned roles/permissions

## Usage Examples

### Checking Permissions in Controllers
```php
// Check if user has permission
if (auth()->user()->can('edit users')) {
    // Allow action
}

// Check if user has role
if (auth()->user()->hasRole('admin')) {
    // Allow action
}

// Middleware protection
Route::middleware(['permission:edit users'])->group(function () {
    // Protected routes
});
```

### Checking Permissions in Frontend
```tsx
// In Inertia pages, permissions are available via auth prop
const { auth } = usePage<SharedData>().props;

// Check permission
if (auth.user.permissions?.includes('edit users')) {
    // Show edit button
}

// Check role
if (auth.user.roles?.includes('admin')) {
    // Show admin features
}
```

### Middleware Usage
```php
// In routes/web.php
Route::middleware(['role:admin'])->group(function () {
    Route::resource('users', UserController::class);
});

Route::middleware(['permission:view dashboard'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

## User Assignment

### Automatic Role Assignment
The seeder automatically assigns roles based on `user_type`:
- `user_type = 'admin'` → `admin` role
- `user_type = 'driver'` → `driver` role
- `user_type = 'manager'` → `manager` role
- `user_type = 'employee'` → `employee` role
- Email `admin@example.com` → `super-admin` role

### Manual Assignment
```php
// Assign role to user
$user = User::find(1);
$user->assignRole('admin');

// Give direct permission
$user->givePermissionTo('edit users');

// Remove role
$user->removeRole('admin');

// Sync roles (replace all roles)
$user->syncRoles(['admin', 'manager']);
```

## Security Features

### Role Protection
- Cannot delete roles that have users assigned
- Super admin role has all permissions automatically
- Role names are unique per guard

### Permission Protection
- Cannot delete permissions assigned to roles or users
- Permission names are unique per guard
- Cached for performance

### Guard Separation
- Web guard for web interface
- API guard for API access
- Separate permission sets per guard

## File Structure

```
resources/js/pages/
├── roles/
│   ├── index.tsx          # Role listing
│   ├── create.tsx         # Role creation
│   ├── edit.tsx          # Role editing
│   ├── show.tsx          # Role details
│   └── README.md         # This documentation
└── permissions/
    ├── index.tsx          # Permission listing
    ├── create.tsx         # Permission creation
    ├── edit.tsx          # Permission editing
    └── show.tsx          # Permission details

app/
├── Models/
│   ├── Role.php          # Custom role model
│   ├── Permission.php    # Custom permission model
│   └── User.php          # User with HasRoles trait
└── Http/Controllers/
    ├── RoleController.php      # Role management
    └── PermissionController.php # Permission management

database/seeders/
└── RolePermissionSeeder.php    # Default roles and permissions
```

## Testing

### Sample Accounts
- **Super Admin**: `admin@example.com` / `12345678`
- **Driver**: `driver@example.com` / `12345678`
- **Manager**: `alice.manager@example.com` / `12345678`
- **Employee**: `emma.employee@example.com` / `12345678`

### Testing Scenarios
1. **Role Management**: Create, edit, delete roles
2. **Permission Assignment**: Assign/remove permissions from roles
3. **User Access**: Test different user types and their access levels
4. **Safety Checks**: Try to delete assigned roles/permissions
5. **Guard Separation**: Test web vs API permissions

## Best Practices

### Role Design
- Keep roles simple and job-function based
- Use descriptive role names (admin, manager, driver)
- Avoid too many granular roles

### Permission Design
- Use action-resource naming (view users, edit products)
- Group related permissions logically
- Keep permissions atomic (one action per permission)

### Assignment Strategy
- Assign roles to users, not individual permissions
- Use direct permissions only for exceptions
- Regularly audit role assignments

## Future Enhancements

Potential features to add:
- **Role Hierarchy**: Parent-child role relationships
- **Time-based Permissions**: Temporary access grants
- **IP Restrictions**: Location-based access control
- **Audit Logging**: Track permission changes
- **Bulk User Assignment**: Assign roles to multiple users
- **Permission Templates**: Pre-defined permission sets
- **API Permissions**: Separate API access control
