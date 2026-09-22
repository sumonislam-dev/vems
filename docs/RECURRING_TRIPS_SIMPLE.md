# 📅 SIMPLE RECURRING TRIPS - Date Range Implementation

## How It Works

**User selects:**
- Start Date: `2026-02-10`
- End Date: `2026-02-18`

**System automatically:**
1. Counts days: 9 days (10, 11, 12, 13, 14, 15, 16, 17, 18)
2. Creates 9 trips (one for each day)
3. Groups them together with `recurring_group_id`

---

## Database Structure

```sql
trips table:
├── is_recurring (boolean)
├── recurring_group_id (FK to trip_recurring_groups)
├── recurring_start_date (date) - for reference
└── recurring_end_date (date) - for reference

trip_recurring_groups table:
├── id
├── group_name (optional: "Daily Office Transport Feb 2026")
├── created_by (FK to users)
├── start_date (2026-02-10)
├── end_date (2026-02-18)
└── total_trips (9)
```

---

## Implementation

### Backend (TripService.php)

```php
<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripRecurringGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TripService
{
    public function createRecurringTrips(array $data): TripRecurringGroup
    {
        DB::beginTransaction();
        try {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            
            // Calculate total days
            $totalDays = $startDate->diffInDays($endDate) + 1;
            
            // Create group
            $group = TripRecurringGroup::create([
                'group_name' => $data['group_name'] ?? "Trips from {$startDate->format('M d')} to {$endDate->format('M d, Y')}",
                'created_by' => auth()->id(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_trips' => $totalDays,
                'notes' => $data['notes'] ?? null,
            ]);
            
            // Create trips for each day
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $this->createSingleTrip($group, $currentDate, $data);
                $currentDate->addDay();
            }
            
            DB::commit();
            return $group->fresh();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    private function createSingleTrip(TripRecurringGroup $group, Carbon $date, array $template): Trip
    {
        $trip = Trip::create([
            'trip_number' => $this->generateTripNumber(),
            'is_recurring' => true,
            'recurring_group_id' => $group->id,
            'recurring_start_date' => $group->start_date,
            'recurring_end_date' => $group->end_date,
            
            // Schedule for this specific day
            'scheduled_date' => $date->toDateString(),
            'scheduled_start_time' => $template['scheduled_start_time'],
            'scheduled_end_time' => $template['scheduled_end_time'],
            
            // Copy template data
            'vehicle_id' => $template['vehicle_id'] ?? null,
            'driver_id' => $template['driver_id'] ?? null,
            'department_id' => $template['department_id'] ?? null,
            'requested_by' => auth()->id(),
            'purpose' => $template['purpose'],
            'trip_type' => $template['trip_type'] ?? null,
            'remarks' => $template['remarks'] ?? null,
            'description' => $template['description'] ?? null,
            'schedule_type' => $template['schedule_type'],
            'priority' => $template['priority'],
            'start_location' => $template['start_location'] ?? null,
            'end_location' => $template['end_location'] ?? null,
            'status' => 'approved', // Auto-approve recurring trips
        ]);
        
        // Copy passengers if provided
        if (!empty($template['passengers'])) {
            foreach ($template['passengers'] as $passenger) {
                $trip->passengers()->create($passenger);
            }
        }
        
        // Copy stops if provided
        if (!empty($template['stops'])) {
            foreach ($template['stops'] as $stop) {
                $trip->tripStops()->create($stop);
            }
        }
        
        return $trip;
    }
    
    private function generateTripNumber(): string
    {
        $date = now()->format('Ymd');
        $count = Trip::whereDate('created_at', today())->count() + 1;
        return "TRP-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
```

### Controller (TripController.php)

```php
public function storeRecurring(Request $request)
{
    $validated = $request->validate([
        'group_name' => 'nullable|string|max:255',
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after:start_date',
        'scheduled_start_time' => 'required',
        'scheduled_end_time' => 'required',
        'purpose' => 'required|string',
        'schedule_type' => 'required',
        'priority' => 'required',
        // ... other fields
    ]);
    
    $service = new TripService();
    $group = $service->createRecurringTrips($validated);
    
    return redirect()
        ->route('trips.index')
        ->with('success', "{$group->total_trips} trips created successfully!");
}
```

### Route (web.php)

```php
Route::post('trips/recurring', [TripController::class, 'storeRecurring'])
    ->name('trips.store-recurring');
```

---

## Frontend Implementation

### Create Recurring Trip Form

```typescript
// pages/trips/create.tsx
const [isRecurring, setIsRecurring] = useState(false);

const { data, setData, post } = useForm({
    // ... other fields
    is_recurring: false,
    start_date: '', // For single trip
    end_date: '', // For recurring trips
    scheduled_start_time: '',
    scheduled_end_time: '',
});

// Calculate days count
const daysCount = useMemo(() => {
    if (!isRecurring || !data.start_date || !data.end_date) return 0;
    const start = new Date(data.start_date);
    const end = new Date(data.end_date);
    const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : 0;
}, [isRecurring, data.start_date, data.end_date]);

return (
    <form onSubmit={handleSubmit}>
        {/* Toggle recurring */}
        <div className="flex items-center space-x-2">
            <Checkbox
                checked={isRecurring}
                onCheckedChange={(checked) => {
                    setIsRecurring(checked);
                    setData('is_recurring', checked);
                }}
            />
            <Label>Create Recurring Trips (Multiple Days)</Label>
        </div>

        {isRecurring ? (
            <>
                {/* Date Range */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label>Start Date *</Label>
                        <Input
                            type="date"
                            value={data.start_date}
                            onChange={(e) => setData('start_date', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <Label>End Date *</Label>
                        <Input
                            type="date"
                            value={data.end_date}
                            onChange={(e) => setData('end_date', e.target.value)}
                            min={data.start_date}
                            required
                        />
                    </div>
                </div>

                {/* Show days count */}
                {daysCount > 0 && (
                    <Alert>
                        <Calendar className="h-4 w-4" />
                        <AlertTitle>
                            {daysCount} trips will be created
                        </AlertTitle>
                        <AlertDescription>
                            One trip for each day from {data.start_date} to {data.end_date}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Time (applies to all days) */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label>Daily Start Time *</Label>
                        <Input
                            type="time"
                            value={data.scheduled_start_time}
                            onChange={(e) => setData('scheduled_start_time', e.target.value)}
                            required
                        />
                    </div>
                    <div>
                        <Label>Daily End Time *</Label>
                        <Input
                            type="time"
                            value={data.scheduled_end_time}
                            onChange={(e) => setData('scheduled_end_time', e.target.value)}
                            required
                        />
                    </div>
                </div>
            </>
        ) : (
            <>
                {/* Single date */}
                <div>
                    <Label>Date *</Label>
                    <Input
                        type="date"
                        value={data.scheduled_date}
                        onChange={(e) => setData('scheduled_date', e.target.value)}
                        required
                    />
                </div>
                {/* Single trip times... */}
            </>
        )}
        
        {/* Rest of form... */}
    </form>
);
```

---

## Query Examples

### Get All Trips in a Group

```php
$group = TripRecurringGroup::with('trips')->find($groupId);

echo "Group: {$group->group_name}";
echo "Total trips: {$group->total_trips}";

foreach ($group->trips as $trip) {
    echo "Trip on {$trip->scheduled_date} - {$trip->status}";
}
```

### Find Recurring Trips for a Date Range

```php
$trips = Trip::where('is_recurring', true)
    ->whereBetween('scheduled_date', [$startDate, $endDate])
    ->get();
```

### Update All Trips in a Group

```php
// Update vehicle for all future trips in group
$group->trips()
    ->where('status', 'pending')
    ->where('scheduled_date', '>=', today())
    ->update(['vehicle_id' => $newVehicleId]);
```

---

## Model Relationships

```php
// Trip.php
public function recurringGroup(): BelongsTo
{
    return $this->belongsTo(TripRecurringGroup::class, 'recurring_group_id');
}

// TripRecurringGroup.php
public function trips(): HasMany
{
    return $this->hasMany(Trip::class, 'recurring_group_id');
}

public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'created_by');
}
```

---

## Benefits

✅ **Simple** - Just select date range
✅ **Automatic** - System counts and creates trips
✅ **Grouped** - Easy to manage all trips together
✅ **Flexible** - Can modify individual trips after creation
✅ **No Complex Patterns** - No weekly/monthly configs needed

This is exactly what you asked for! 🎉
