<?php

namespace Ninja\DeviceTracker\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Enums\DeviceStatus;
use Ninja\DeviceTracker\Factories\DeviceIdFactory;
use Ninja\DeviceTracker\Factories\FingerprintFactory;
use Ninja\DeviceTracker\Models\Device;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'uuid' => DeviceIdFactory::from($this->faker->uuid),
            'status' => DeviceStatus::Verified,
            'fingerprint' => $this->faker->boolean ? FingerprintFactory::from($this->faker->uuid) : null,
            'browser' => $this->faker->boolean ? $this->faker->word : null,
            'browser_version' => $this->faker->boolean ? $this->faker->semver() : null,
            'browser_family' => $this->faker->boolean ? $this->faker->word : null,
            'browser_engine' => $this->faker->boolean ? $this->faker->word : null,
            'platform' => $this->faker->boolean ? $this->faker->word : null,
            'platform_version' => $this->faker->boolean ? $this->faker->semver() : null,
            'platform_family' => $this->faker->boolean ? $this->faker->word : null,
            'device_type' => $this->faker->boolean ? $this->faker->word : null,
            'device_family' => $this->faker->boolean ? $this->faker->word : null,
            'device_model' => $this->faker->boolean ? $this->faker->word : null,
            'grade' => $this->faker->boolean ? $this->faker->word : null,
            'metadata' => new Metadata([]),
            'source' => $this->faker->userAgent,
            'device_id' => $this->faker->boolean ? $this->faker->uuid : null,
            'advertising_id' => $this->faker->boolean ? $this->faker->uuid : null,
        ];
    }
}
