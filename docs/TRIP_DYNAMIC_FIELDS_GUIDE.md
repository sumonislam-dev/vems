# Trip Dynamic Fields Implementation Guide

## Overview
All requested dynamic fields for trips are now fully implemented and functional.

## ✅ Implemented Fields

### 1. **Dynamic Start & End Times**
- `start_time` (timestamp) - Automatically set when trip status changes to 'in_progress'
- `end_time` (timestamp) - Automatically set when trip is marked as completed

```php
// Manual usage
$trip->update([
    'start_time' => now(), // Set when driver starts trip
    'end_time' => now(),   // Set when driver completes trip
]);

// Automatic (via Observer)
$trip->update(['status' => 'in_progress']); // Automatically sets start_time
$trip->update(['is_completed' => true]);    // Automatically sets end_time
```

### 2. **Trip Flags**
- `is_return` (boolean) - Indicates if trip is a return journey
- `is_completed` (boolean) - Marks trip as completed (auto-updates end_time)

```php
$trip->update([
    'is_return' => true,      // Mark as return trip
    'is_completed' => true,   // Mark as completed (auto-sets end_time)
]);
```

### 3. **Multiple Departments**
Stored as JSON array in `multiple_departments` field.

```php
// Create trip with multiple departments
Trip::create([
    'trip_number' => 'TRP-001',
    'multiple_departments' => [1, 2, 5], // Array of department IDs
    // ... other fields
]);

// Update multiple departments
$trip->update([
    'multiple_departments' => [3, 4, 6, 7],
]);

// Access departments
$departmentIds = $trip->multiple_departments; // Returns array
```

### 4. **Multiple Logistics**
Implemented via many-to-many relationship through `trip_logistics` pivot table.

```php
// Assign single logistics
$trip->assignLogistics(logisticsId: 1, notes: 'Primary logistics coordinator');

// Assign multiple logistics
$trip->logistics()->attach([
    1 => ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'notes' => 'Main coordinator'],
    2 => ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'notes' => 'Backup coordinator'],
    3 => ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'notes' => 'Route specialist'],
]);

// Get all logistics for a trip
$logistics = $trip->logistics; // Returns collection of Logistics models

// Get logistics with pivot data
$logistics = $trip->logistics()->withPivot(['assigned_by', 'assigned_at', 'notes'])->get();

// Remove logistics
$trip->unassignLogistics(logisticsId: 1, reason: 'No longer needed');

// Sync logistics (replace all with new ones)
$trip->logistics()->sync([2, 3, 5]);
```

### 5. **Comments**
Simple text field for trip comments.

```php
$trip->update([
    'comments' => 'Passenger requested extra stop at factory gate'
]);
```

### 6. **Trip Types**
Enum field with predefined values.

**Available Types:**
- `inspection`
- `pick-up`
- `drop-off`
- `training`
- `complaints`
- `CVV`
- `Incident Inspection`
- `officials`
- `Assigned`

```php
$trip->update([
    'trip_type' => 'inspection' // Must be one of the enum values
]);
```

## 📋 Complete Usage Example

```php
use App\Models\Trip;

// Create a new trip with all dynamic fields
$trip = Trip::create([
    'trip_number' => 'TRP-' . str_pad(Trip::max('id') + 1, 6, '0', STR_PAD_LEFT),
    'purpose' => 'Factory inspection and employee transport',
    'trip_type' => 'inspection',
    'scheduled_date' => '2026-04-25',
    'scheduled_start_time' => '09:00:00',
    'scheduled_end_time' => '17:00:00',
    'department_id' => 1,
    'multiple_departments' => [1, 2, 3], // Multiple departments
    'requested_by' => auth()->id(),
    'is_return' => true,
    'comments' => 'Priority trip - VIP passengers',
    'status' => 'pending',
]);

// Assign multiple logistics coordinators
$trip->logistics()->attach([
    1 => ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'notes' => 'Main coordinator'],
    2 => ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'notes' => 'Route planner'],
]);

// When driver starts the trip
$trip->update(['status' => 'in_progress']); // Automatically sets start_time

// When driver completes the trip
$trip->update(['is_completed' => true]); // Automatically sets end_time and status to 'completed'
```

## 🔄 Auto-Update Behavior (Observer)

The `TripObserver` automatically handles:

1. **Auto-set `start_time`**: When `status` changes to `'in_progress'` and `start_time` is null
2. **Auto-set `end_time`**: When `is_completed` changes to `true` and `end_time` is null
3. **Auto-update `status`**: When `is_completed` is set to true, status changes to `'completed'`

## 📊 Query Examples

```php
// Get trips with multiple departments
$trips = Trip::whereNotNull('multiple_departments')->get();

// Get trips with logistics assigned
$trips = Trip::whereHas('logistics')->with('logistics')->get();

// Get completed trips
$completedTrips = Trip::where('is_completed', true)
    ->whereNotNull('end_time')
    ->get();

// Get return trips
$returnTrips = Trip::where('is_return', true)->get();

// Get trips by type
$inspectionTrips = Trip::where('trip_type', 'inspection')->get();

// Get trips with comments
$tripsWithComments = Trip::whereNotNull('comments')->get();

// Complex query
$trips = Trip::where('trip_type', 'pick-up')
    ->where('is_completed', false)
    ->whereHas('logistics', function($query) {
        $query->where('status', true);
    })
    ->whereJsonContains('multiple_departments', 1)
    ->with(['logistics', 'department'])
    ->get();
```

## 🎯 Controller Example

```php
public function completeTrip(Request $request, Trip $trip)
{
    $validated = $request->validate([
        'odometer_end' => 'required|numeric|gt:' . $trip->odometer_start,
        'fuel_consumed' => 'nullable|numeric',
        'comments' => 'nullable|string',
    ]);

    $trip->update([
        'odometer_end' => $validated['odometer_end'],
        'fuel_consumed' => $validated['fuel_consumed'] ?? null,
        'comments' => $validated['comments'] ?? $trip->comments,
        'is_completed' => true, // This will auto-set end_time
    ]);

    return redirect()->back()->with('success', 'Trip completed successfully!');
}

public function assignLogisticsToTrip(Request $request, Trip $trip)
{
    $validated = $request->validate([
        'logistics_ids' => 'required|array',
        'logistics_ids.*' => 'exists:logistics,id',
    ]);

    foreach ($validated['logistics_ids'] as $logisticsId) {
        $trip->assignLogistics(
            logisticsId: $logisticsId,
            notes: "Assigned via bulk assignment"
        );
    }

    return redirect()->back()->with('success', 'Logistics assigned successfully!');
}
```

## 🗄️ Database Schema

All fields are already in the `trips` table:

```sql
-- Core fields
start_time              TIMESTAMP NULL
end_time                TIMESTAMP NULL
is_return               BOOLEAN DEFAULT FALSE
is_completed            BOOLEAN DEFAULT FALSE
comments                TEXT NULL
trip_type               ENUM(...) NULL
multiple_departments    JSON NULL

-- Related pivot table
trip_logistics (
    trip_id,
    logistics_id,
    assigned_by,
    assigned_at,
    notes
)
```

## ✨ Summary

**Everything you requested is implemented:**
1. ✅ Dynamic start/end times with auto-update
2. ✅ is_return flag
3. ✅ is_completed flag (triggers end_time update)
4. ✅ start_time & end_time fields
5. ✅ Multiple departments (JSON array)
6. ✅ Multiple logistics (many-to-many relationship)
7. ✅ Comments field
8. ✅ Trip types with all requested enum values

**No migration needed** - all database fields already exist. Just use the features as shown in the examples above!
