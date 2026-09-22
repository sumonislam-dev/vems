# 🎨 Frontend Updates - Trip Management System

## ✅ Completed Updates

### 1. **Type Definitions** (`resources/js/types/index.d.ts`)

#### Updated Trip Interface
Added recurring trip support:
```typescript
interface Trip {
    // ... existing fields
    is_recurring: boolean;
    recurring_group_id?: number;
    recurring_start_date?: string;
    recurring_end_date?: string;
    original_trip_id?: number;
    recurring_group?: TripRecurringGroup;
    original_trip?: Trip;
}
```

#### New Interfaces Added
```typescript
interface TripRecurringGroup {
    id: number;
    group_name?: string;
    created_by: number;
    start_date: string;
    end_date: string;
    total_trips: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    creator?: User;
    trips?: Trip[];
}

interface Factory {
    id: number;
    name: string;
    code?: string;
    address?: string;
    // ... other fields
}
```

#### Updated TripStop Interface
```typescript
interface TripStop {
    // ... existing fields
    factory_id?: number;
    stop_order: number;
    estimated_arrival?: string;
    actual_arrival?: string;
    departure_time?: string;
    is_destination: boolean;
    visit_purpose?: string;
    factory?: Factory;
}
```

---

### 2. **Trip Create Form** (`resources/js/pages/trips/create.tsx`)

#### New Imports Added
```typescript
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { CalendarDays, Info } from 'lucide-react';
import { useMemo } from 'react';
```

#### New Form State
```typescript
const [isRecurring, setIsRecurring] = useState(false);

const { data, setData, post } = useForm({
    // ... existing fields
    is_recurring: false,
    recurring_start_date: '',
    recurring_end_date: '',
    group_name: '',
});
```

#### New Features

**1. Days Calculator**
```typescript
const daysCount = useMemo(() => {
    if (!isRecurring || !data.recurring_start_date || !data.recurring_end_date) return 0;
    const start = new Date(data.recurring_start_date);
    const end = new Date(data.recurring_end_date);
    const diff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : 0;
}, [isRecurring, data.recurring_start_date, data.recurring_end_date]);
```

**2. Dynamic Route Submission**
```typescript
const submitRoute = isRecurring 
    ? route('trips.store-recurring') 
    : route('trips.store');
```

**3. Recurring Toggle UI**
```tsx
<div className="flex items-center space-x-2 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
    <Checkbox
        id="is_recurring"
        checked={isRecurring}
        onCheckedChange={(checked) => {
            setIsRecurring(!!checked);
            setData('is_recurring', !!checked);
        }}
    />
    <Label htmlFor="is_recurring">
        <CalendarDays className="h-4 w-4" />
        Create Recurring Trips (Multiple Days)
    </Label>
</div>
```

**4. Conditional Schedule Section**

**Single Trip Mode:**
- Single date picker
- Start time
- End time

**Recurring Mode:**
- Start date (date range)
- End date (date range)
- Group name (optional)
- Daily start time (applies to all days)
- Daily end time (applies to all days)
- Days count preview

**5. Days Count Preview Alert**
```tsx
{daysCount > 0 && (
    <Alert>
        <Info className="h-4 w-4" />
        <AlertTitle>
            {daysCount} trip{daysCount > 1 ? 's' : ''} will be created
        </AlertTitle>
        <AlertDescription>
            One trip for each day from {data.recurring_start_date} to {data.recurring_end_date}
        </AlertDescription>
    </Alert>
)}
```

**6. Dynamic Submit Button**
```tsx
<Button type="submit" disabled={processing}>
    {processing 
        ? (isRecurring ? 'Creating Trips...' : 'Creating Trip...') 
        : (isRecurring ? `Create ${daysCount} Trips` : 'Create Trip')
    }
</Button>
```

---

## 🎯 User Experience Flow

### Single Trip Creation
1. User fills in trip details
2. Selects date, start time, end time
3. Clicks "Create Trip"
4. One trip is created

### Recurring Trip Creation
1. User checks "Create Recurring Trips"
2. Schedule section changes to show:
   - Start date (e.g., Feb 10, 2026)
   - End date (e.g., Feb 18, 2026)
   - Optional group name
   - Daily start/end time
3. System automatically calculates: **9 days = 9 trips**
4. Preview alert shows: "9 trips will be created"
5. Button shows: "Create 9 Trips"
6. User clicks submit
7. Backend creates 9 individual trips (one per day)

---

## 📊 Database Alignment

Frontend now fully supports the consolidated migration structure:

✅ **trip_recurring_groups** table
- group_name
- start_date, end_date
- total_trips
- created_by

✅ **trips** table
- is_recurring
- recurring_group_id
- recurring_start_date, recurring_end_date
- original_trip_id (for return trips)

✅ **trip_stops** table
- factory_id support
- visit_purpose
- is_destination flag

---

## 🔄 Backend Routes Required

The frontend now expects these routes:

```php
// Regular trip
POST /trips → TripController@store

// Recurring trips
POST /trips/recurring → TripController@storeRecurring
```

---

## 🎨 UI Components Used

- ✅ `Alert` - For days count preview
- ✅ `Checkbox` - For recurring toggle
- ✅ `Badge` - For status/priority display
- ✅ `Card` - For form sections
- ✅ `Input` - For date/time pickers
- ✅ `Button` - For actions
- ✅ `Label` - For form labels
- ✅ Icons from `lucide-react`

---

## 📝 Validation Expected

Frontend sends these fields for recurring trips:

```typescript
{
    is_recurring: true,
    recurring_start_date: '2026-02-10',
    recurring_end_date: '2026-02-18',
    group_name: 'Optional Group Name',
    scheduled_start_time: '09:00',
    scheduled_end_time: '17:00',
    // ... all other trip fields
}
```

Backend should validate:
- `recurring_start_date` required if `is_recurring`
- `recurring_end_date` > `recurring_start_date`
- Date range not too long (maybe max 365 days?)

---

## ✨ Next Steps

1. **Create TripController** with:
   - `store()` method for single trips
   - `storeRecurring()` method for recurring trips

2. **Create TripService** with:
   - `createRecurringTrips()` logic
   - Date iteration
   - Trip duplication

3. **Add Routes** in `web.php`:
   ```php
   Route::post('trips/recurring', [TripController::class, 'storeRecurring'])
       ->name('trips.store-recurring');
   ```

4. **Test Flow**:
   - Create single trip ✅
   - Create recurring trips (e.g., 7 days) ✅
   - View created trips in listing ✅
   - See recurring group badge ✅

---

## 🚀 Ready for Backend Implementation!

Frontend is fully prepared for:
- ✅ Single trip creation
- ✅ Recurring trip creation (date range)
- ✅ Return trip support (checkbox)
- ✅ Factory integration (via stops)
- ✅ All new migration fields

Just need backend controllers and services! 🎉
