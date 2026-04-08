<?php

namespace Ninja\DeviceTracker\Tests\Feature\Console;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Artisan;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Factories\SessionIdFactory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Ninja\DeviceTracker\Tests\FeatureTestCase;

final class DeviceInspectCommandTest extends FeatureTestCase
{
    public function test_outputs_session_counts_consistent_with_relation_queries(): void
    {
        $user = new User;
        $user->name = 'Inspect';
        $user->email = 'inspect@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();

        $location = new Location(null, null, null, null, null, null, null, null, null, null);

        $active = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '192.168.0.1',
            'location' => $location,
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
            'finished_at' => null,
        ]);
        $active->save();

        $finished = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '192.168.0.2',
            'location' => $location,
            'status' => SessionStatus::Finished,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now()->subHour(),
            'last_activity_at' => Carbon::now()->subHour(),
            'finished_at' => Carbon::now(),
        ]);
        $finished->save();

        $exit = Artisan::call('devices:inspect', ['uuid' => (string) $device->uuid]);
        $this->assertSame(0, $exit);

        $output = Artisan::output();
        $this->assertStringContainsString('Active Sessions', $output);
        $this->assertStringContainsString('Total Sessions', $output);
        $this->assertMatchesRegularExpression('/Active Sessions\s*\|\s*1/', $output);
        $this->assertMatchesRegularExpression('/Total Sessions\s*\|\s*2/', $output);
        $this->assertMatchesRegularExpression('/Associated Users\s*\|\s*1/', $output);
    }
}
