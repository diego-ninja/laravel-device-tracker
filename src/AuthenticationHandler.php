<?php

namespace Ninja\DeviceTracker;

use Config;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Ninja\DeviceTracker\Events\Google2FASuccess;
use Ninja\DeviceTracker\Facades\DeviceManager;
use Ninja\DeviceTracker\Facades\SessionManager;
use Ninja\DeviceTracker\Models\Session;

final readonly class AuthenticationHandler
{
    public function onLogin(Login $event): void
    {
        DeviceManager::addUserDevice(request());
        SessionManager::refresh();
    }

    public function onLogout(Logout $event): void
    {
        Session::current()?->end(
            forgetSession: true,
            user: $event->user,
        );
    }

    public function onGoogle2FASuccess(Google2FASuccess $event): void
    {
        $user = $event->user;
        $user->session()->device->verify();
        $user->session()->unlock();
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('Illuminate\Auth\Events\Login', 'Ninja\DeviceTracker\AuthenticationHandler@onLogin');
        $events->listen('Illuminate\Auth\Events\Logout', 'Ninja\DeviceTracker\AuthenticationHandler@onLogout');
        $events->listen('Ninja\DeviceTracker\Events\Google2FASuccess', 'Ninja\DeviceTracker\AuthenticationHandler@onGoogle2FASuccess');
    }
}
