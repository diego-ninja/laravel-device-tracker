<?php

namespace Ninja\DeviceTracker\Tests\Feature\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;
use Ninja\DeviceTracker\DTO\Device as DeviceDto;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Factories\FingerprintFactory;
use Ninja\DeviceTracker\Factories\SessionIdFactory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Modules\Detection\DTO\Browser;
use Ninja\DeviceTracker\Modules\Detection\DTO\DeviceType;
use Ninja\DeviceTracker\Modules\Detection\DTO\Platform;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Ninja\DeviceTracker\Tests\FeatureTestCase;
use Ninja\DeviceTracker\ValueObject\DeviceId;
use PHPUnit\Framework\Attributes\DataProvider;

class DeviceTest extends FeatureTestCase
{
    private readonly Device $device;

    private readonly DeviceDto $deviceDto;

    private function setUpDevice(array $deviceDetails = []): void
    {
        $this->device = Device::factory()->create(array_merge(
            [
                'uuid' => DeviceId::from('f765e4d4-a990-4c59-aeed-d16f0aed2665'),
                'fingerprint' => null,
                'browser' => 'Chrome',
                'browser_version' => '130.0.0',
                'browser_family' => 'Chrome',
                'browser_engine' => 'Blink',
                'platform' => 'Mac',
                'platform_version' => '10.15.7',
                'platform_family' => 'Mac',
                'device_type' => 'desktop',
                'device_family' => 'Apple',
                'device_model' => null,
                'grade' => null,
                'metadata' => new Metadata([]),
                'source' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'device_id' => null,
                'advertising_id' => null,
            ],
            $deviceDetails,
        ));
    }

    private function setUpDeviceDto(array $deviceDetails = []): void
    {
        $this->deviceDto = DeviceDto::from(array_merge(
            [
                'browser' => new Browser,
                'platform' => new Platform,
                'device' => new DeviceType,
                'advertisingId' => null,
                'deviceId' => null,
                'bot' => null,
                'grade' => null,
                'source' => null,
            ],
            $deviceDetails,
        ));
    }

    public static function nonMatchingDevices(): array
    {
        return [
            'empty device' => [
                'deviceInfo' => [],
                'dtoInfo' => [],
                'match' => false,
            ],
            'same info different platform' => [
                'deviceInfo' => [],
                'dtoInfo' => [
                    'platform' => Platform::from([
                        'name' => 'Android',
                        'version' => '10.15.7',
                        'family' => 'Mac',
                    ]),
                ],
                'match' => false,
            ],
            'all equals but no unique info on device' => [
                'deviceInfo' => [],
                'dtoInfo' => [
                    'browser' => Browser::from([
                        'name' => 'Chrome',
                        'version' => '130.0.0',
                        'family' => 'Chrome',
                        'engine' => 'Blink',
                    ]),
                    'platform' => Platform::from([
                        'name' => 'Mac',
                        'version' => '10.15.7',
                        'family' => 'Mac',
                    ]),
                    'device' => DeviceType::from([
                        'type' => 'desktop',
                        'family' => 'Apple',
                        'model' => null,
                    ]),
                    'advertisingId' => null,
                    'deviceId' => null,
                    'bot' => null,
                    'grade' => null,
                    'source' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                ],
                'match' => false,
            ],
            'same advertising id but different platform' => [
                'deviceInfo' => [
                    'advertising_id' => 'advertisingId',
                    'platform' => 'Mac',
                ],
                'dtoInfo' => [
                    'platform' => Platform::from([
                        'name' => 'Android',
                        'version' => '10.15.7',
                        'family' => 'Mac',
                    ]),
                    'advertisingId' => 'advertisingId',
                    'deviceId' => null,
                ],
                'match' => false,
            ],
            'same device id but different platform' => [
                'deviceInfo' => [
                    'device_id' => 'deviceId',
                    'platform' => 'Mac',
                ],
                'dtoInfo' => [
                    'platform' => Platform::from([
                        'name' => 'Android',
                        'version' => '10.15.7',
                        'family' => 'Mac',
                    ]),
                    'advertisingId' => null,
                    'deviceId' => 'deviceId',
                ],
                'match' => false,
            ],
        ];
    }

    public static function matchingDevices(): array
    {
        return [
            'same advertising id and same platform' => [
                'deviceInfo' => [
                    'advertising_id' => 'advertisingId',
                    'platform' => 'Mac',
                    'browser' => 'Chrome',
                    'browser_engine' => 'Blink',
                    'browser_version' => '1.0.0',
                ],
                'dtoInfo' => [
                    'platform' => Platform::from([
                        'name' => 'Mac',
                        'version' => '10.15.7',
                        'family' => 'Mac',
                    ]),
                    'browser' => Browser::from([
                        'name' => 'Chrome',
                        'version' => '10.15.7',
                        'engine' => 'Blink',
                    ]),
                    'advertisingId' => 'advertisingId',
                    'deviceId' => null,
                ],
                'match' => true,
            ],
            'same device id and same platform' => [
                'deviceInfo' => [
                    'device_id' => 'deviceId',
                    'platform' => 'Mac',
                    'browser' => 'Chrome',
                    'browser_engine' => 'Blink',
                    'browser_version' => '1.0.0',
                ],
                'dtoInfo' => [
                    'platform' => Platform::from([
                        'name' => 'Mac',
                        'version' => '10.15.7',
                        'family' => 'Mac',
                    ]),
                    'browser' => Browser::from([
                        'name' => 'Chrome',
                        'version' => '10.15.7',
                        'engine' => 'Blink',
                    ]),
                    'advertisingId' => null,
                    'deviceId' => 'deviceId',
                ],
                'match' => true,
            ],
        ];
    }

    #[DataProvider('nonMatchingDevices')]
    #[DataProvider('matchingDevices')]
    public function test_by_device_dto_unique_info_with_no_existing_device(array $deviceInfo = [], array $dtoInfo = [], bool $match = false): void
    {
        $this->setUpDevice($deviceInfo);
        $this->setUpDeviceDto($dtoInfo);

        if ($match) {
            $device = Device::byDeviceDtoUniqueInfo($this->deviceDto);
            $this->assertTrue($device instanceof Device);
            $this->assertEquals($this->device->uuid, $device->uuid);
        } else {
            $this->assertNull(Device::byDeviceDtoUniqueInfo($this->deviceDto));
        }
    }

    public function test_verified_returns_false_when_user_has_no_session_for_device(): void
    {
        $user = new User;
        $user->name = 'Verified test';
        $user->email = 'verified-null@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();

        $this->assertFalse($device->verified($user));
    }

    public function test_verified_does_not_throw_when_user_has_device_session(): void
    {
        $user = new User;
        $user->name = 'Verified session';
        $user->email = 'verified-session@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();

        $session = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '192.168.0.1',
            'location' => new Location(null, null, null, null, null, null, null, null, null, null),
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
        ]);
        $session->save();

        $this->assertIsBool($device->verified($user));
    }

    public function test_verified_returns_false_when_passed_null_user_without_auth(): void
    {
        $device = Device::factory()->create();

        $this->assertFalse($device->verified(null));
    }

    public function test_update_info_with_null_data_only_updates_fingerprint(): void
    {
        $device = Device::factory()->create([
            'fingerprint' => null,
            'browser_version' => '1.0.0',
        ]);
        $fingerprint = FingerprintFactory::from('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        $device->updateInfo($fingerprint, null);

        $device->refresh();
        $this->assertSame((string) $fingerprint, (string) $device->fingerprint);
        $this->assertSame('1.0.0', $device->browser_version);
    }

    public function test_update_info_with_all_null_arguments_returns_without_error(): void
    {
        $device = Device::factory()->create();

        $device->updateInfo(null, null);

        $this->assertInstanceOf(Device::class, $device->fresh());
    }

    public function test_update_info_accepts_null_browser_and_platform_versions(): void
    {
        $device = Device::factory()->create([
            'browser' => 'Chrome',
            'browser_version' => '130.0.0',
            'browser_family' => 'Chrome',
            'browser_engine' => 'Blink',
            'platform' => 'Mac',
            'platform_version' => '10.15.7',
            'platform_family' => 'Mac',
            'device_type' => 'desktop',
            'device_family' => 'Apple',
            'device_model' => 'Mac',
            'source' => 'Mozilla/5.0 (compatible)',
        ]);

        $dto = DeviceDto::from([
            'browser' => Browser::from([
                'name' => 'Chrome',
                'version' => null,
                'family' => 'Chrome',
                'engine' => 'Blink',
            ]),
            'platform' => Platform::from([
                'name' => 'Mac',
                'version' => null,
                'family' => 'Mac',
            ]),
            'device' => DeviceType::from([
                'type' => 'desktop',
                'family' => 'Apple',
                'model' => 'Mac',
            ]),
            'advertisingId' => null,
            'deviceId' => null,
            'bot' => null,
            'grade' => null,
            'source' => 'Mozilla/5.0 (compatible)',
        ]);

        $device->updateInfo(null, $dto);
        $device->refresh();

        $this->assertNull($device->browser_version);
        $this->assertNull($device->platform_version);
    }
}
