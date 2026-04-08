<?php

namespace Ninja\DeviceTracker\Tests\Feature\Cache;

use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\Cache\AbstractCache;
use Ninja\DeviceTracker\Cache\DeviceCache;
use Ninja\DeviceTracker\Tests\FeatureTestCase;

final class DeviceCacheRememberTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        $this->resetAbstractCacheInstances();
        parent::tearDown();
    }

    public function test_remember_invokes_callback_each_time_when_device_cache_disabled(): void
    {
        $this->resetAbstractCacheInstances();
        Config::set('devices.cache_enabled_for', []);
        Config::set('devices.cache_store', 'array');

        $calls = 0;
        $first = DeviceCache::remember('tdd-remember-key', function () use (&$calls) {
            $calls++;

            return 'first';
        });
        $second = DeviceCache::remember('tdd-remember-key', function () use (&$calls) {
            $calls++;

            return 'second';
        });

        $this->assertSame('first', $first);
        $this->assertSame('second', $second);
        $this->assertSame(2, $calls);
    }

    public function test_remember_executes_callback_only_once_when_cache_hit(): void
    {
        $this->resetAbstractCacheInstances();
        Config::set('devices.cache_enabled_for', ['device']);
        Config::set('devices.cache_store', 'array');

        $calls = 0;
        $first = DeviceCache::remember('tdd-remember-hit', function () use (&$calls) {
            $calls++;

            return 'cached-value';
        });
        $second = DeviceCache::remember('tdd-remember-hit', function () use (&$calls) {
            $calls++;

            return 'other';
        });

        $this->assertSame('cached-value', $first);
        $this->assertSame('cached-value', $second);
        $this->assertSame(1, $calls);
    }

    public function test_remember_negative_cache_avoids_repeat_callback_for_null(): void
    {
        $this->resetAbstractCacheInstances();
        Config::set('devices.cache_enabled_for', ['device']);
        Config::set('devices.cache_store', 'array');

        $calls = 0;
        $first = DeviceCache::remember('tdd-remember-null', function () use (&$calls) {
            $calls++;

            return null;
        });
        $second = DeviceCache::remember('tdd-remember-null', function () use (&$calls) {
            $calls++;

            return null;
        });

        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $calls);
    }

    private function resetAbstractCacheInstances(): void
    {
        $ref = new \ReflectionClass(AbstractCache::class);
        $prop = $ref->getProperty('instances');
        $prop->setValue(null, []);
    }
}
