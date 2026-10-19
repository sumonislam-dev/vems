<?php

namespace App\Http\Middleware;

use App\Models\Zone;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user() ? $request->user()->getAllPermissions()->pluck('name')->toArray() : [],
                'roles' => $request->user() ? $request->user()->getRoleNames()->toArray() : [],
                'availableZones' => $request->user() ? Zone::switcherOptions($request->user())->values() : [],
                'activeZoneId' => $request->user() ? $request->session()->get('active_zone_id') : null,
            ],
            'notifications' => $request->user()?->can('view-notifications') ? [
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'items' => $request->user()->notifications()->latest()->take(10)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'message' => $notification->data['message'] ?? '',
                    'url' => $notification->data['url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'time' => $notification->created_at->diffForHumans(),
                ]),
            ] : null,
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
