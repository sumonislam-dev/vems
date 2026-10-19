# Drivers Management Module

This directory contains the drivers-only management system that filters and displays only users with `user_type = 'driver'`.

## Features

### Drivers-Only Listing

- **Filtered Data**: Only shows users where `user_type = 'driver'`
- **Specialized Interface**: Driver-focused UI with relevant information
- **Driver-Specific Stats**: Active, inactive, and suspended driver counts
- **Enhanced Contact Info**: Phone, WhatsApp, and location details
- **Blood Group Tracking**: Medical information for emergency situations

### Driver Management Pages

#### 1. Drivers Index (`/drivers`)

- **Driver Data Table**: Server-side paginated table showing only drivers
- **Enhanced Search**: Search across name, email, phone, and address
- **Driver Filters**: Status and blood group filtering
- **Driver Actions**: View, Edit, and Delete driver-specific actions
- **Driver Stats**: Total, active, inactive, and suspended driver counts
- **Contact Integration**: Quick access to phone and WhatsApp

#### 2. Driver Show Page (`/drivers/{id}`)

- **Driver Profile**: Complete driver information with truck icon
- **Contact Details**: Email, phone, WhatsApp with action buttons
- **Personal Information**: Blood group and address for emergencies
- **System Information**: Driver ID, verification status, join date
- **Quick Actions**: Call, WhatsApp, edit, and delete buttons
- **Driver Badge**: Visual indication of driver role

#### 3. Driver Edit Page (`/drivers/{id}/edit`)

- **Driver-Specific Form**: Tailored for driver information
- **Driver Badge**: Visual indicator showing driver status
- **Contact Fields**: Phone and WhatsApp for communication
- **Emergency Info**: Blood group and address fields
- **Password Update**: Optional password change
- **Status Management**: Active, inactive, suspended options

## Database Integration

### Driver Filtering

- **Automatic Filtering**: All queries filter by `user_type = 'driver'`
- **Route Model Binding**: Ensures only drivers can be accessed
- **Security**: 404 error if non-driver user is accessed via driver routes

### Driver-Specific Stats

```php
$stats = [
    'total' => User::where('user_type', 'driver')->count(),
    'active' => User::where('user_type', 'driver')->where('status', 'active')->count(),
    'inactive' => User::where('user_type', 'driver')->where('status', 'inactive')->count(),
    'suspended' => User::where('user_type', 'driver')->where('status', 'suspended')->count(),
];
```

## Backend Implementation

### DriversController

- **index()**: Paginated listing of drivers only
- **show()**: Display single driver (with driver validation)
- **edit()**: Show edit form for driver (with driver validation)
- **update()**: Update driver (ensures user_type remains 'driver')
- **destroy()**: Delete driver (with driver validation)

### Route Protection

```php
// Ensure the user is actually a driver
if ($driver->user_type !== 'driver') {
    abort(404, 'Driver not found');
}
```

### Routes

Driver-specific routes (no create/store as drivers are created via users):

- `GET /drivers` - Drivers index page
- `GET /drivers/{driver}` - Show driver
- `GET /drivers/{driver}/edit` - Edit driver form
- `PUT/PATCH /drivers/{driver}` - Update driver
- `DELETE /drivers/{driver}` - Delete driver

## UI/UX Features

### Driver-Specific Design

- **Truck Icons**: Driver-themed icons throughout the interface
- **Blue Color Scheme**: Driver avatars use blue background
- **Contact Integration**: Direct call and WhatsApp buttons
- **Emergency Info**: Highlighted blood group display
- **Location Display**: Address with map pin icon

### Enhanced Data Display

- **Driver Avatars**: Blue-themed profile pictures with initials
- **Contact Cards**: Phone and WhatsApp with action buttons
- **Blood Group Badges**: Red-themed medical information display
- **Status Indicators**: Color-coded driver status badges
- **Location Info**: Truncated address with tooltip

### Quick Actions

- **Call Driver**: Direct phone call integration
- **WhatsApp**: Direct WhatsApp messaging
- **Edit Driver**: Quick access to driver editing
- **Delete Driver**: Secure driver deletion with confirmation

## Navigation Integration

### Sidebar Menu

- **Drivers Menu**: Separate menu item with UserCheck icon
- **Position**: Between Users and Products in navigation
- **Icon**: UserCheck icon to distinguish from general Users
- **Access**: Available to all authenticated users

### Breadcrumbs

- **Consistent Navigation**: Dashboard > Drivers > [Action]
- **Clear Context**: Always shows current location in driver management

## Sample Data

### Driver Users in UserSeeder

- **John Driver**: `driver@example.com` / `12345678`
- **Sarah Wilson**: `sarah.driver@example.com` / `12345678`
- **Mike Johnson**: `mike.driver@example.com` / `12345678`

All drivers have:

- Complete contact information (phone, WhatsApp)
- Blood group information
- Full addresses
- Active status
- Email verification

## Key Differences from Users Module

### Filtering

- **Users Module**: Shows all users regardless of type
- **Drivers Module**: Only shows users with `user_type = 'driver'`

### Interface

- **Users Module**: Generic user management interface
- **Drivers Module**: Driver-specific interface with truck icons and contact features

### Stats

- **Users Module**: Total users, active users, drivers, admins
- **Drivers Module**: Total drivers, active drivers, inactive drivers, suspended drivers

### Actions

- **Users Module**: Full CRUD operations including create
- **Drivers Module**: View, edit, delete (create via users module)

## File Structure

```
resources/js/pages/drivers/
├── index.tsx           # Drivers listing page
├── show.tsx           # Driver details page
├── edit.tsx           # Driver edit form
└── README.md          # This documentation

app/Http/Controllers/
└── DriversController.php    # Driver-specific CRUD operations
```

## Usage Examples

### Accessing Drivers

1. Click "Drivers" in the sidebar navigation
2. View filtered list of only driver users
3. Use search and filters specific to drivers
4. Click on any driver to view details

### Managing Drivers

1. **View Driver**: Click on driver row or eye icon
2. **Edit Driver**: Click edit icon or "Edit Driver" button
3. **Contact Driver**: Use "Call Driver" or "WhatsApp" buttons
4. **Delete Driver**: Click delete icon with confirmation

### Creating New Drivers

1. Click "Add Driver" button (redirects to users create)
2. Form pre-fills with `user_type=driver`
3. Complete driver information
4. New driver appears in drivers list

## Integration with Driver Portal

### Conditional Navigation

When a driver logs in, they see:

- **Standard Navigation**: Including the Drivers menu
- **Driver Portal**: Additional driver-specific navigation section
- **Role-based Features**: Access to driver routes, vehicle, tracking, schedule

### Cross-Module Integration

- **Users Module**: Manages all users including drivers
- **Drivers Module**: Specialized view of driver users only
- **Driver Portal**: Driver-specific functionality for logged-in drivers

## Future Enhancements

Potential features to add:

- **Driver Performance**: Delivery metrics and ratings
- **Vehicle Assignment**: Link drivers to specific vehicles
- **Route History**: Track driver route assignments
- **Driver Availability**: Schedule and availability management
- **Emergency Contacts**: Additional emergency contact information
- **Driver Documents**: License, insurance, certification tracking
- **GPS Tracking**: Real-time driver location tracking
