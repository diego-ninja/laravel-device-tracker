<?php

namespace Ninja\DeviceTracker\Tests\Feature\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Factories\EventIdFactory;
use Ninja\DeviceTracker\Factories\SessionIdFactory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Ninja\DeviceTracker\Modules\Tracking\Enums\EventType;
use Ninja\DeviceTracker\Modules\Tracking\Models\Event;
use Ninja\DeviceTracker\Tests\FeatureTestCase;

final class HasManySessionsAndEventsTest extends FeatureTestCase
{
    public function test_device_sessions_active_matches_finished_at_and_status(): void
    {
        $user = new User;
        $user->name = 'Active sessions';
        $user->email = 'active-sessions@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();
        $location = new Location(null, null, null, null, null, null, null, null, null, null);

        $keep = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '10.0.0.1',
            'location' => $location,
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
            'finished_at' => null,
        ]);
        $keep->save();

        $finished = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '10.0.0.2',
            'location' => $location,
            'status' => SessionStatus::Finished,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now()->subHour(),
            'last_activity_at' => Carbon::now()->subHour(),
            'finished_at' => Carbon::now(),
        ]);
        $finished->save();

        $active = $device->sessions()->active();
        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is($keep));
    }

    public function test_device_events_last_orders_by_occurred_at_via_query(): void
    {
        $device = Device::factory()->create();

        Event::create([
            'uuid' => (string) EventIdFactory::generate(),
            'device_uuid' => $device->uuid,
            'session_uuid' => null,
            'type' => EventType::PageView,
            'metadata' => new Metadata([]),
            'ip_address' => '10.0.0.1',
            'occurred_at' => Carbon::parse('2020-01-01 12:00:00'),
        ]);

        Event::create([
            'uuid' => (string) EventIdFactory::generate(),
            'device_uuid' => $device->uuid,
            'session_uuid' => null,
            'type' => EventType::Login,
            'metadata' => new Metadata([]),
            'ip_address' => '10.0.0.2',
            'occurred_at' => Carbon::parse('2020-06-01 12:00:00'),
        ]);

        $newest = $device->events()->last(1)->first();
        $this->assertNotNull($newest);
        $this->assertSame(EventType::Login, $newest->type);
    }

    public function test_device_events_views_scope_chains_to_last(): void
    {
        $device = Device::factory()->create();

        Event::create([
            'uuid' => (string) EventIdFactory::generate(),
            'device_uuid' => $device->uuid,
            'session_uuid' => null,
            'type' => EventType::PageView,
            'metadata' => new Metadata([]),
            'ip_address' => '10.0.0.1',
            'occurred_at' => Carbon::parse('2021-01-01 12:00:00'),
        ]);

        Event::create([
            'uuid' => (string) EventIdFactory::generate(),
            'device_uuid' => $device->uuid,
            'session_uuid' => null,
            'type' => EventType::Login,
            'metadata' => new Metadata([]),
            'ip_address' => '10.0.0.2',
            'occurred_at' => Carbon::parse('2021-06-01 12:00:00'),
        ]);

        $newestView = $device->events()->views()->last(1)->first();
        $this->assertNotNull($newestView);
        $this->assertSame(EventType::PageView, $newestView->type);
    }

    public function test_sessions_relation_helpers_do_not_leak_query_constraints(): void
    {
        $user = new User;
        $user->name = 'Leak test';
        $user->email = 'sessions-leak@example.test';
        $user->password = 'password';
        $user->save();

        $device = Device::factory()->create();
        $location = new Location(null, null, null, null, null, null, null, null, null, null);

        $older = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '10.0.0.1',
            'location' => $location,
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now()->subDay(),
            'last_activity_at' => Carbon::now()->subDay(),
            'finished_at' => null,
        ]);
        $older->save();

        $newer = new Session([
            'uuid' => SessionIdFactory::generate(),
            'user_id' => $user->id,
            'device_uuid' => $device->uuid,
            'ip' => '10.0.0.2',
            'location' => $location,
            'status' => SessionStatus::Active,
            'metadata' => new Metadata([]),
            'started_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
            'finished_at' => null,
        ]);
        $newer->save();

        $relation = $device->sessions();
        $first = $relation->first();
        $last = $relation->last();

        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertTrue($first->is($older));
        $this->assertTrue($last->is($newer));
    }
}
