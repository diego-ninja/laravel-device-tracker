<?php

namespace Ninja\DeviceTracker\Tests\Feature\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Ninja\DeviceTracker\Models\ChangeHistory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Observers\ChangeHistoryObserver;
use Ninja\DeviceTracker\Tests\FeatureTestCase;

final class ChangeHistoryObserverTest extends FeatureTestCase
{
    public function test_get_model_key_rejects_unknown_model(): void
    {
        $foreign = new class extends Model
        {
            protected $table = 'unknown_models';
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported model:');

        ChangeHistoryObserver::getModelKey($foreign);
    }

    public function test_deleting_device_removes_related_history_when_history_enabled(): void
    {
        $this->setConfig([
            'devices.history.enabled' => true,
        ]);

        // Config defaults keep history off at application boot, so the service provider does not
        // register ChangeHistoryObserver; we enable the feature and attach the observer only here.
        Device::observe(ChangeHistoryObserver::class);
        Session::observe(ChangeHistoryObserver::class);
        Relation::morphMap([
            'device' => Device::class,
            'session' => Session::class,
        ]);

        $device = Device::factory()->create();

        $row = new ChangeHistory([
            'column' => 'browser',
            'old_value' => 'Old',
            'new_value' => 'New',
        ]);
        $row->model()->associate($device);
        $row->save();

        $table = config('devices.history.table', 'laravel_devices_history');
        $this->assertDatabaseCount($table, 1);

        $device->delete();

        $this->assertDatabaseCount($table, 0);
    }
}
