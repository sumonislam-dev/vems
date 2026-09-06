<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Request;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip' => Request::ip(),
                'login' => $event->credentials['email'] ?? $event->credentials['username'] ?? null,
                'user_agent' => Request::userAgent(),
            ])
            ->event('login_failed')
            ->log('Failed login attempt');
    }
}
