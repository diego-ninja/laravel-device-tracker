<?php

namespace Ninja\DeviceTracker\DTO;

use JsonSerializable;
use Ninja\DeviceTracker\Modules\Detection\DTO\Browser;
use Ninja\DeviceTracker\Modules\Detection\DTO\DeviceType;
use Ninja\DeviceTracker\Modules\Detection\DTO\Platform;
use Stringable;
use Zerotoprod\DataModel\DataModel;

final class Device implements JsonSerializable, Stringable
{
    use DataModel;

    public const UNKNOWN = 'UNK';

    public Browser $browser;

    public Platform $platform;

    public DeviceType $device;

    public ?string $advertisingId = null;

    public ?string $deviceId = null;

    public ?bool $bot = false;

    public ?string $grade = self::UNKNOWN;

    public ?string $source;

    public function unknown(): bool
    {
        return $this->browser->unknown() || $this->platform->unknown();
    }

    public function bot(): bool
    {
        return $this->bot ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function array(): array
    {
        return [
            'browser' => $this->browser->array(),
            'platform' => $this->platform->array(),
            'device' => $this->device->array(),
            'advertising_id' => $this->advertisingId,
            'device_id' => $this->deviceId,
            'grade' => $this->grade,
            'source' => $this->source,
            'label' => (string) $this,
            'bot' => $this->bot(),
        ];
    }

    public function valid(): bool
    {
        $validUnknown = $this->unknown() && config('devices.allow_unknown_devices') === true;
        $validBot = $this->bot() && config('devices.allow_bot_devices') === true;
        $validDevice = ! $this->unknown() && ! $this->bot();

        return $validUnknown || $validBot || $validDevice;

    }

    public function __toString(): string
    {
        return sprintf('%s at %s on %s', $this->browser, $this->device, $this->platform);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->array();
    }

    public function json(): string|false
    {
        return json_encode($this->array());
    }
}
