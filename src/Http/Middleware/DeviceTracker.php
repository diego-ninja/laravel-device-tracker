<?php

namespace Ninja\DeviceTracker\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Ninja\DeviceTracker\Contracts\LoggedUserGuesser;
use Ninja\DeviceTracker\Contracts\StorableId;
use Ninja\DeviceTracker\DTO\Device as DeviceDto;
use Ninja\DeviceTracker\Enums\Transport;
use Ninja\DeviceTracker\Exception\InvalidDeviceDetectedException;
use Ninja\DeviceTracker\Exception\UnknownDeviceDetectedException;
use Ninja\DeviceTracker\Facades\DeviceManager;
use Ninja\DeviceTracker\Factories\DeviceIdFactory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Transports\DeviceTransport;
use Throwable;

final readonly class DeviceTracker
{
    /**
     * @throws UnknownDeviceDetectedException
     * @throws InvalidDeviceDetectedException
     */
    public function handle(
        Request $request,
        Closure $next,
        ?string $hierarchyParameterString = null,
        ?string $responseTransport = null,
        bool $skipDeviceMatchCheck = false,
    ): mixed {
        $this->checkCustomDeviceTransportHierarchy($hierarchyParameterString);
        $this->checkCustomDeviceResponseTransport($responseTransport);

        // detect all device details
        $detectedDevice = DeviceManager::detect($request);

        // Check whitelist and allowance of detected device
        if (! $detectedDevice || ! DeviceManager::isWhitelisted($detectedDevice->source)) {
            if (! $detectedDevice || $detectedDevice->unknown()) {
                $this->isDeviceAllowed(
                    userAgent: $detectedDevice->source ?? null,
                );
            } elseif ($detectedDevice->bot()) {
                $this->isDeviceAllowed(
                    unknown: false,
                    userAgent: $detectedDevice->source,
                );
            }
        }

        $fingerprint = fingerprint();
        $deviceUuid = device_uuid();
        $ip = $request->getClientIp();
        $user = user() ?? $this->guessLoggingUser();
        $checkIp = DeviceManager::isLoginRoute() || $user !== null;

        $device = $this->getMatchingDevice(
            deviceUuid: $deviceUuid,
            fingerprint: $fingerprint,
            deviceDto: $detectedDevice,
            checkIpAndUser: $checkIp,
            ip: $ip,
            user: $user,
            skipDeviceMatchCheck: $skipDeviceMatchCheck,
        );

        if ($device !== null) {
            return DeviceTransport::set($next(DeviceTransport::propagate($device->uuid)), $device->uuid);
        } elseif ($deviceUuid !== null && Device::byUuid($deviceUuid) !== null) {
            // Request has a device uuid that belongs to an existing device that do not match info. Reset device uuid
            $deviceUuid = DeviceIdFactory::generate();
            DeviceTransport::propagate($deviceUuid);
        }

        if (DeviceManager::shouldTrack()) {
            /** @var Device|null $device */
            $device = null;
            try {
                $device = DeviceManager::create(
                    deviceUuid: $deviceUuid,
                    fingerprint: $fingerprint,
                    deviceDto: $detectedDevice,
                );
            } catch (UniqueConstraintViolationException $e) { // @phpstan-ignore catch.neverThrown (unique violations from the DB layer)
                // Race conditions probably means that a device has been created between the matching device check
                // and the previous DeviceManager::create. Try to find again the matching device that should now exist
                $device = $this->getMatchingDevice(
                    deviceUuid: $deviceUuid,
                    fingerprint: $fingerprint,
                    deviceDto: $detectedDevice,
                    checkIpAndUser: $checkIp,
                    ip: $ip,
                    user: $user,
                    skipDeviceMatchCheck: $skipDeviceMatchCheck,
                );

                if ($device !== null) {
                    return DeviceTransport::set($next(DeviceTransport::propagate($device->uuid)), $device->uuid);
                }

                $this->abort($detectedDevice === null, $this->deviceDtoSource($detectedDevice), $e);
            } catch (UnknownDeviceDetectedException $e) {
                $this->abort($detectedDevice === null, $this->deviceDtoSource($detectedDevice), $e);
            }

            if ($device === null) {
                throw new \RuntimeException('Device tracking expected a device after create or recovery.');
            }

            DeviceManager::track($device->uuid);

            return DeviceTransport::set($next(DeviceTransport::propagate($device->uuid)), $device->uuid);
        }

        // Device does not exist and it should not be tracked. Keep in the request the uuid provided or set a new uuid
        $deviceUuid ??= DeviceIdFactory::generate();

        return DeviceTransport::set($next(DeviceTransport::propagate($deviceUuid)), $deviceUuid);
    }

    private function deviceDtoSource(?DeviceDto $deviceDto): string
    {
        if ($deviceDto === null) {
            return 'unknown';
        }

        return $deviceDto->source ?? 'unknown';
    }

    private function checkCustomDeviceTransportHierarchy(?string $hierarchyParameterString = null): void
    {
        if (! empty($hierarchyParameterString)) {
            $hierarchy = array_filter(
                explode('|', $hierarchyParameterString),
                fn (string $value) => Transport::tryFrom($value) !== null,
            );
            if (! empty($hierarchy)) {
                Config::set('devices.transports.device_id.transport_hierarchy', $hierarchy);
            }
        }
    }

    private function checkCustomDeviceResponseTransport(?string $parameterString = null): void
    {
        if (
            ! empty($parameterString)
            && Transport::tryFrom($parameterString) !== null
            && $parameterString !== Transport::Request->value
        ) {
            Config::set('devices.transports.device_id.response_transport', $parameterString);
        }
    }

    private function getMatchingDevice(
        ?StorableId $deviceUuid,
        ?StorableId $fingerprint,
        ?DeviceDto $deviceDto,
        bool $checkIpAndUser = false,
        ?string $ip = null,
        ?Authenticatable $user = null,
        bool $skipDeviceMatchCheck = false,
    ): ?Device {
        $device = DeviceManager::matchingDevice(
            deviceUuid: $deviceUuid,
            fingerprint: $fingerprint,
            deviceDto: $deviceDto,
            ip: $checkIpAndUser ? $ip : null,
            user: $checkIpAndUser ? $user : null,
            skipMatchCheck: $skipDeviceMatchCheck,
        );

        if ($device !== null) {
            if (! $skipDeviceMatchCheck) {
                $device = $device->updateInfo(
                    fingerprint: $fingerprint,
                    data: $deviceDto,
                );
            }

            return $device;
        }

        return null;
    }

    /**
     * @throws UnknownDeviceDetectedException
     * @throws InvalidDeviceDetectedException
     */
    private function isDeviceAllowed(bool $unknown = true, ?string $userAgent = null): void
    {
        if (isset($userAgent) && DeviceManager::isWhitelisted($userAgent)) {
            return;
        }
        if (Config::get('devices.allow_'.($unknown ? 'unknown' : 'bot').'_devices', false) === false) {
            $this->abort($unknown, $userAgent);
        }
    }

    /**
     * @throws InvalidDeviceDetectedException
     * @throws UnknownDeviceDetectedException
     */
    private function abort(bool $unknown = false, ?string $userAgent = null, ?Throwable $e = null): void
    {
        if ($e !== null) {
            Log::error(sprintf('Device exception caught (%s): %s', get_class($e), $e->getMessage()));
        }

        if (! $this->shouldThrow()) {
            $errorCode = config('devices.middlewares.device-tracker.http_error_code', 403);
            if (! array_key_exists($errorCode, Response::$statusTexts)) {
                $errorCode = 403;
            }
            abort((int) $errorCode, sprintf(
                '%s device detected: user-agent %s',
                $unknown ? 'Unknown' : 'Bot',
                $userAgent
            ));
        } else {
            if ($unknown === true) {
                throw UnknownDeviceDetectedException::withUA($userAgent);
            }
            throw InvalidDeviceDetectedException::withUA($userAgent);
        }
    }

    private function shouldThrow(): bool
    {
        return Config::get('devices.middlewares.device-tracker.exception_on_invalid_devices', false);
    }

    private function guessLoggingUser(): ?Authenticatable
    {
        $guesserClass = config('devices.logged_user_guesser');
        if ($guesserClass === null) {
            return null;
        }

        $instance = null;
        try {
            $instance = app($guesserClass);
        } catch (Throwable $e) {
            Log::info($e->getMessage());
        }

        if (! ($instance instanceof LoggedUserGuesser)) {
            try {
                $instance = new $guesserClass;
            } catch (Throwable $e) {
                Log::info($e->getMessage());
            }
        }

        return $instance instanceof LoggedUserGuesser
            ? $instance->guess()
            : null;
    }
}
