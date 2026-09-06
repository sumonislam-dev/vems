<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => Request::ip(),
            'last_login_device' => Request::userAgent(),
        ])->saveQuietly();

        activity('auth')
            ->causedBy($user)
            ->withProperties([
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ])
            ->event('login')
            ->log('User logged in');
    }
}
