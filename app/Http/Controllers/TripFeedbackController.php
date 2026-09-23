<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TripFeedbackController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-complaints|view-own-complaints', only: ['index', 'show']),
            new Middleware('permission:create-complaints', only: ['create', 'store']),
            new Middleware('permission:delete-complaints', only: ['destroy']),
        ];
    }

    public const CATEGORIES = [
        'driver_behavior' => 'Driver Behavior',
        'vehicle_condition' => 'Vehicle Condition',
        'punctuality' => 'Punctuality',
        'safety' => 'Safety',
        'route' => 'Route',
        'other' => 'Other',
    ];

    /**
     * Display a listing of feedback/complaints, scoped to what the user is allowed to see.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'sort' => 'nullable|in:created_at,status,priority,type',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $query = TripFeedback::with(['trip:id,trip_number,scheduled_date', 'submitter:id,name', 'assignee:id,name'])
            ->visibleTo($user);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('trip', fn ($tq) => $tq->where('trip_number', 'like', "%{$search}%"));
            });
        }

        $sortColumn = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumn, $sortDirection);

        $items = $query->paginate($request->get('per_page', 15))->withQueryString();

        $scopedStats = TripFeedback::visibleTo($user)->selectRaw(
            'COUNT(*) as total, ' .
            "SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open, " .
            "SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review, " .
            "SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved, " .
            "SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed"
        )->first();

        return Inertia::render('complaints/index', [
            'items' => $items,
            'stats' => [
                'total' => (int) ($scopedStats->total ?? 0),
                'open' => (int) ($scopedStats->open ?? 0),
                'in_review' => (int) ($scopedStats->in_review ?? 0),
                'resolved' => (int) ($scopedStats->resolved ?? 0),
                'closed' => (int) ($scopedStats->closed ?? 0),
            ],
            'categories' => self::CATEGORIES,
            'canViewAll' => $user->can('view-complaints'),
            'queryParams' => $request->only(['search', 'type', 'category', 'status', 'priority', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for submitting feedback/a complaint about a trip.
     */
    public function create(Request $request)
    {
        $user = $request->user();

        $tripsQuery = Trip::query()->select(['id', 'trip_number', 'scheduled_date', 'description']);

        if (!$user->can('view-complaints')) {
            $this->scopeTripsToUser($tripsQuery, $user);
        }

        $trips = $tripsQuery->orderByDesc('scheduled_date')->limit(100)->get();

        return Inertia::render('complaints/create', [
            'trips' => $trips,
            'defaultTripId' => $request->integer('trip_id') ?: null,
            'categories' => self::CATEGORIES,
        ]);
    }

    /**
     * Store a newly submitted feedback/complaint.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'type' => 'required|in:feedback,complaint',
            'category' => 'required|in:' . implode(',', array_keys(self::CATEGORIES)),
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'vehicle_rating' => 'nullable|integer|min:1|max:5',
            'priority' => 'nullable|in:low,medium,high,critical',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // `exists:trips,id` above only confirms the trip is real, not that
        // this user has anything to do with it — without this, anyone with
        // create-complaints could rate a driver/vehicle on a trip they never
        // requested, rode, or drove (ratings here feed User::average_rating).
        if (!$user->can('view-complaints')
            && !$this->scopeTripsToUser(Trip::where('id', $validated['trip_id']), $user)->exists()) {
            throw ValidationException::withMessages([
                'trip_id' => 'You can only submit feedback for a trip you requested, rode on, or drove.',
            ]);
        }

        $feedback = TripFeedback::create([
            ...$validated,
            'submitted_by' => $user->id,
            'priority' => $validated['priority'] ?? 'low',
            'status' => 'open',
        ]);

        return redirect()->route('complaints.show', $feedback)
            ->with('success', $validated['type'] === 'complaint' ? 'Complaint submitted successfully.' : 'Feedback submitted successfully.');
    }

    /**
     * Display the specified feedback/complaint.
     */
    public function show(Request $request, TripFeedback $complaint)
    {
        $this->authorizeView($request, $complaint);

        $complaint->load([
            'trip:id,trip_number,scheduled_date,description',
            'submitter:id,name',
            'assignee:id,name',
            'resolver:id,name',
        ]);

        return Inertia::render('complaints/show', [
            'item' => $complaint,
            'categories' => self::CATEGORIES,
        ]);
    }

    /**
     * Remove the specified feedback/complaint (spam/duplicate cleanup).
     */
    public function destroy(TripFeedback $complaint)
    {
        $complaint->delete();

        return redirect()->route('complaints.index')->with('success', 'Entry deleted.');
    }

    private function authorizeView(Request $request, TripFeedback $complaint): void
    {
        $user = $request->user();

        abort_unless($user->can('view-complaints') || $complaint->submitted_by === $user->id, 403);
    }

    /**
     * Restrict a trips query to ones this user requested, rode as a
     * passenger on, or drove (mirrors Trip::scopeVisibleTo()'s non-privileged
     * branch — kept separate here since that scope is gated on view-trips,
     * a different permission than the view-complaints gate used in this
     * controller).
     */
    private function scopeTripsToUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('requested_by', $user->id)
                ->orWhereHas('passengers', fn ($pq) => $pq->where('user_id', $user->id))
                ->orWhereHas('vehicle', fn ($vq) => $vq->where('driver_id', $user->id));
        });
    }
}
