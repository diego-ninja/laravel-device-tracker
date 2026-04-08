<?php

namespace Ninja\DeviceTracker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\Exception\DeviceNotFoundException;

final readonly class DeviceChecker
{
    /**
     * @throws DeviceNotFoundException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (device() === null) {
            if (! $this->shouldThrow()) {
                $errorCode = config('devices.middlewares.device-checker.http_error_code', 403);
                if (! array_key_exists($errorCode, Response::$statusTexts)) {
                    $errorCode = 403;
                }
                abort((int) $errorCode, 'Device not found.');
            } else {
                throw new DeviceNotFoundException;
            }
        }

        return $next($request);
    }

    private function shouldThrow(): bool
    {
        return Config::get('devices.middlewares.device-checker.exception_on_unavailable_devices', false);
    }
}
