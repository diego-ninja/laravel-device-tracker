<?php

namespace Ninja\DeviceTracker\Tests\Feature\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Factories\SessionIdFactory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Ninja\DeviceTracker\Tests\FeatureTestCase;

final class SessionRenewLastActivityTest extends FeatureTestCase
{
    public function test_renew_updates_last_activity_when_elapsed_seconds_exceed_interval(): void
    {
        Config::set('devices.performance.session.always_sync_last_activity', false);
        Config::set('devices.performance.session.last_activity_update_interval', 300);

        $user = new User;
        $user->name = 'Renew stale';
        $user->email = 'renew-stale@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();
        $location = new Location(null, null, null, null, null, null, null, null, null, null);

        $session = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '10.0.0.1',
            'location' => $location,
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now()->subHours(2),
            'last_activity_at' => Carbon::now()->subSeconds(400),
            'finished_at' => null,
        ]);
        $session->save();

        $before = $session->last_activity_at?->copy();
        $this->assertNotNull($before);

        $this->assertTrue($session->renew());
        $session->refresh();

        $this->assertNotNull($session->last_activity_at);
        $this->assertTrue($session->last_activity_at->gt($before));
    }

    public function test_renew_skips_save_when_last_activity_is_recent(): void
    {
        Config::set('devices.performance.session.always_sync_last_activity', false);
        Config::set('devices.performance.session.last_activity_update_interval', 300);

        $user = new User;
        $user->name = 'Renew fresh';
        $user->email = 'renew-fresh@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();
        $location = new Location(null, null, null, null, null, null, null, null, null, null);

        $session = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '10.0.0.1',
            'location' => $location,
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now()->subMinutes(5),
            'last_activity_at' => Carbon::now()->subSeconds(30),
            'finished_at' => null,
        ]);
        $session->save();

        $before = $session->last_activity_at?->copy();
        $this->assertNotNull($before);

        $this->assertTrue($session->renew());
        $session->refresh();

        $this->assertTrue($before->equalTo($session->last_activity_at));
    }
}
