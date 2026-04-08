<?php

namespace Ninja\DeviceTracker\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Ninja\DeviceTracker\DeviceManager<\Illuminate\Contracts\Auth\Authenticatable>
 */
final class DeviceManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'device_manager';
    }
}
