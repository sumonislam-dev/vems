<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogIndexRequest;
use App\Models\User;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-user-activity'),
        ];
    }

    public function index(ActivityLogIndexRequest $request): Response
    {
        $validated = $request->validated();

        $query = Activity::with('causer');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (! empty($filters['log_name'])) {
                $query->whereIn('log_name', $filters['log_name']);
            }

            if (! empty($filters['event'])) {
                $query->whereIn('event', $filters['event']);
            }

            if (! empty($filters['causer_id'])) {
                $query->whereIn('causer_id', $filters['causer_id']);
            }

            if (! empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (! empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }
        }

        $query->orderBy($validated['sort'], $validated['direction']);

        $activities = $query->paginate($validated['per_page'])
            ->withQueryString()
            ->through(function (Activity $activity) {
                return [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'event' => $activity->event,
                    'description' => $activity->description,
                    'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                    'subject_id' => $activity->subject_id,
                    'causer' => $activity->causer ? [
                        'id' => $activity->causer->id,
                        'name' => $activity->causer->name ?? $activity->causer->getKey(),
                    ] : null,
                    'properties' => [
                        'old' => $activity->properties->get('old') ?? $activity->properties->get('attributes') ?? null,
                        'new' => $activity->properties->get('attributes') ?? null,
                        'other' => $activity->properties->except(['old', 'attributes'])->toArray() ?: null,
                    ],
                    'created_at' => $activity->created_at,
                ];
            });

        return Inertia::render('activity-logs/index', [
            'activities' => $activities,
            'filterOptions' => [
                'log_names' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
                'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
                'causers' => User::whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);
    }
}
