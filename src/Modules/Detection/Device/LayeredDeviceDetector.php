<?php

namespace Ninja\DeviceTracker\Modules\Detection\Device;

use Illuminate\Http\Request;
use Ninja\DeviceTracker\DTO\Device;
use Ninja\DeviceTracker\Modules\Detection\Contracts\DeviceDetectorInterface;

final class LayeredDeviceDetector implements DeviceDetectorInterface
{
    /**
     * @param  array<int, DeviceDetectorInterface>  $detectors
     */
    public function __construct(private readonly array $detectors) {}

    public function detect(Request|string $request, ?Device $base = null): ?Device
    {
        return collect($this->detectors)
            ->reduce(function (?Device $carry, DeviceDetectorInterface $detector) use ($request) {
                return $detector->detect($request, $carry);
            }, $base);
    }
}
