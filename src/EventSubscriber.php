<?php

namespace Ninja\DeviceTracker;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Ninja\DeviceTracker\Events\Google2FASuccess;
use Ninja\DeviceTracker\Exception\DeviceNotFoundException;
use Ninja\DeviceTracker\Facades\DeviceManager;
use Ninja\DeviceTracker\Facades\SessionManager;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Transports\SessionTransport;

final readonly class EventSubscriber
{
    /**
     * @throws DeviceNotFoundException
     */
    public function onLogin(Login $event): void
    {
        try {
            if (! DeviceManager::tracked()) {
                DeviceManager::track();
                DeviceManager::create();
            }

            $session = SessionManager::refresh($event->user);
            SessionTransport::propagate($session->uuid);
        } catch (DeviceNotFoundException $e) {
            Log::error('Login failed due to device error', [
                'error' => $e->getMessage(),
                'user_id' => $event->user->getAuthIdentifier(),
            ]);

            throw $e;
        }
    }

    public function onLogout(Logout $event): void
    {
        Session::current()?->end(
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
        $events->listen('Illuminate\Auth\Events\Login', [self::class, 'onLogin']);
        $events->listen('Illuminate\Auth\Events\Logout', [self::class, 'onLogout']);
        $events->listen('Ninja\DeviceTracker\Events\Google2FASuccess', [self::class, 'onGoogle2FASuccess']);
    }
}
