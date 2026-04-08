<?php

namespace Ninja\DeviceTracker\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\Contracts\Cacheable;
use Ninja\DeviceTracker\Models\Device;
use Psr\SimpleCache\InvalidArgumentException;

abstract class AbstractCache
{
    public const KEY_PREFIX = '';

    /**
     * Serializable marker for negative caching in remember() when the callback returns null.
     * Not a valid {@see Device} or other domain payload; callers always receive null.
     *
     * @internal
     */
    private const REMEMBER_NULL_SENTINEL = '__ninja.device_tracker.abstract_cache.remember_null.v1__';

    /** @var static[] */
    protected static array $instances = [];

    protected ?Repository $cache = null;

    final protected function __construct()
    {
        if (! $this->enabled()) {
            return;
        }

        /** @var string $store */
        $store = config('devices.cache_store');
        $this->cache = Cache::store($store);
    }

    public static function instance(): self
    {
        if (! isset(self::$instances[static::class])) {
            self::$instances[static::class] = new static;
        }

        return self::$instances[static::class];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function get(string $key): ?Device
    {
        return self::instance()->getItem($key);
    }

    public static function put(Cacheable $item): void
    {
        self::instance()->putItem($item);
    }

    public static function remember(string $key, Closure $callback): mixed
    {
        $instance = self::instance();
        if (! $instance->enabled()) {
            return $callback();
        }

        $cache = $instance->cache;
        if ($cache === null) {
            return $callback();
        }

        $ttl = $instance->ttl();

        // Laravel stores often treat get(null) like a miss, so we never put raw null. Non-null
        // values are cached normally. For null callback results we store REMEMBER_NULL_SENTINEL
        // (negative cache) so hot misses (e.g. Device::byUuid) do not repeat DB work until TTL.
        $existing = $cache->get($key);
        if ($existing === self::REMEMBER_NULL_SENTINEL) {
            return null;
        }
        if ($existing !== null) {
            return $existing;
        }

        $value = $callback();
        $cache->put(
            $key,
            $value !== null ? $value : self::REMEMBER_NULL_SENTINEL,
            $ttl
        );

        return $value;
    }

    public static function key(string $key): string
    {
        return sprintf('%s:%s', static::KEY_PREFIX, hash('xxh128', $key));
    }

    public static function forget(Cacheable $item): void
    {
        self::instance()->forgetItem($item);
    }

    public static function flush(): void
    {
        if (! self::instance()->enabled()) {
            return;
        }

        if (! is_null(self::instance()->cache) && method_exists(self::instance()->cache, 'flush')) {
            self::instance()->cache->flush();
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getItem(string $key): ?Device
    {
        if (! $this->enabled()) {
            return null;
        }

        $value = $this->cache?->get($key);
        if ($value === self::REMEMBER_NULL_SENTINEL || $value === null) {
            return null;
        }

        /** @var Device $value */
        return $value;
    }

    protected function putItem(Cacheable $item): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->cache?->put($item->key(), $item, $item->ttl() ?? $this->ttl());
    }

    protected function forgetItem(Cacheable $item): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->cache?->forget($item->key());
    }

    protected function ttl(): int
    {
        return Config::get('devices.cache_ttl')[static::KEY_PREFIX];
    }

    abstract protected function enabled(): bool;
}
