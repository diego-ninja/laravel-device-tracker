<?php

namespace Ninja\DeviceTracker\Models;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\Cache\SessionCache;
use Ninja\DeviceTracker\Contracts\Cacheable;
use Ninja\DeviceTracker\Contracts\StorableId;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Events\SessionBlockedEvent;
use Ninja\DeviceTracker\Events\SessionFinishedEvent;
use Ninja\DeviceTracker\Events\SessionStartedEvent;
use Ninja\DeviceTracker\Events\SessionUnblockedEvent;
use Ninja\DeviceTracker\Events\SessionUnlockedEvent;
use Ninja\DeviceTracker\Exception\SessionNotFoundException;
use Ninja\DeviceTracker\Facades\SessionManager;
use Ninja\DeviceTracker\Factories\SessionIdFactory;
use Ninja\DeviceTracker\Modules\Location\Contracts\LocationProvider;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Ninja\DeviceTracker\Modules\Tracking\Enums\EventType;
use Ninja\DeviceTracker\Modules\Tracking\Models\Event;
use Ninja\DeviceTracker\Modules\Tracking\Models\Relations\HasManyEvents;
use Ninja\DeviceTracker\Traits\PropertyProxy;
use Ninja\DeviceTracker\Transports\SessionTransport;
use RuntimeException;

/**
 * Class Session
 *
 * @mixin \Illuminate\Database\Query\Builder
 * @mixin Builder<Session>
 *
 * @property int $id unsigned int
 * @property StorableId $uuid uuid
 * @property int $user_id unsigned int
 * @property StorableId $device_uuid string
 * @property string $ip string
 * @property Location $location json
 * @property SessionStatus $status string
 * @property ?int $blocked_by unsigned int
 * @property Metadata $metadata json
 * @property Carbon $started_at datetime
 * @property ?Carbon $finished_at datetime
 * @property ?Carbon $blocked_at datetime
 * @property ?Carbon $unlocked_at datetime
 * @property ?Carbon $last_activity_at datetime
 * @property-read User|null $user
 * @property-read Device $device
 * @property-read Collection<int, Event> $events
 *
 * @method static Builder<Session> query()
 */
class Session extends Model implements Cacheable
{
    use PropertyProxy;

    protected $table = 'device_sessions';

    public $timestamps = false;

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'blocked_at' => 'datetime',
        'unlocked_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'device_uuid',
        'ip',
        'location',
        'status',
        'metadata',
        'started_at',
        'last_activity_at',
    ];

    /**
     * @return HasOne<Device, $this>
     */
    public function device(): HasOne
    {
        return $this->hasOne(Device::class, 'uuid', 'device_uuid');
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        /** @var class-string<User> $authenticatable */
        $authenticatable = Config::get('devices.authenticatable_class', User::class);

        return $this->hasOne($authenticatable, 'id', 'user_id');
    }

    public function events(): HasManyEvents
    {
        $instance = $this->newRelatedInstance(Event::class);

        return new HasManyEvents(
            query: $instance->newQuery(),
            parent: $this,
            foreignKey: 'session_uuid',
            localKey: 'uuid'
        );
    }

    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'model');
    }

    /**
     * @return Attribute<Closure, Closure>
     */
    public function uuid(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => SessionIdFactory::from($value),
            set: fn (StorableId $value) => (string) $value,
        );
    }

    /**
     * @return Attribute<Closure, Closure>
     */
    public function status(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => SessionStatus::from($value),
            set: fn (SessionStatus $value) => $value->value,
        );
    }

    /**
     * @return Attribute<Closure, Closure>
     */
    public function location(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Location::fromArray(json_decode($value, true)),
            set: fn (Location $value) => $value->json(),
        );
    }

    /**
     * @return Attribute<Closure, Closure>
     */
    public function metadata(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Metadata::from(json_decode($value, true)) : new Metadata([]),
            set: fn (Metadata $value) => $value->json()
        );
    }

    public static function start(Device $device, ?Authenticatable $user = null): Session
    {
        $now = Carbon::now();
        $user = $user ?? user();

        if ($user === null) {
            throw new RuntimeException('No user provided');
        }

        $ip = self::getIp();

        $location = app(LocationProvider::class)->locate($ip);

        if (config('devices.allow_device_multi_session') === false) {
            self::endPreviousSessions($device, $user);
        }

        /** @var Session $session */
        $session = self::create([
            'user_id' => $user->getAuthIdentifier(),
            'uuid' => SessionIdFactory::generate(),
            'device_uuid' => $device->uuid,
            'ip' => $ip,
            'location' => $location,
            'status' => self::initialStatus($device),
            'started_at' => $now,
            'last_activity_at' => $now,
        ]);

        event(new SessionStartedEvent($session, $user));

        return $session;
    }

    public function relocate(): Session
    {
        $ip = self::getIp();

        $this->ip = $ip;
        $this->location = app(LocationProvider::class)->locate($ip);

        return $this;
    }

    private static function initialStatus(Device $device): SessionStatus
    {
        $user = user();
        if (! $user?->google2faEnabled()) {
            return SessionStatus::Active;
        } else {
            return $device->verified() ? SessionStatus::Active : SessionStatus::Locked;
        }
    }

    private static function endPreviousSessions(Device $device, Authenticatable $user): void
    {
        self::where('device_uuid', $device->uuid)
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('finished_at')
            ->each(fn (Session $session) => $session->end());

    }

    public function event(EventType $type, Metadata $metadata): Event
    {
        /** @var Event $event */
        $event = Event::create([
            'device_uuid' => $this->device_uuid,
            'session_uuid' => $this->uuid,
            'type' => $type,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);

        return $event;
    }

    public function end(?Authenticatable $user = null): bool
    {
        if ($this->status === SessionStatus::Finished) {
            SessionTransport::forget();

            return true;
        }

        $this->status = SessionStatus::Finished;
        $this->finished_at = Carbon::now();

        if ($this->save()) {
            SessionTransport::forget();

            event(new SessionFinishedEvent($this, $user ?? user()));

            return true;
        }

        return false;
    }

    public function renew(): bool
    {
        if (
            $this->status !== SessionStatus::Active
            || SessionManager::alwaysSyncLastActivity()
            || (
                $this->last_activity_at === null
                || Carbon::now()->timestamp - $this->last_activity_at->timestamp > SessionManager::lastActivityUpdateInterval()
            )
        ) {
            $this->last_activity_at = Carbon::now();
            $this->status = SessionStatus::Active;
            $this->finished_at = null;

            return $this->save();
        }

        return true;
    }

    public function restart(Request $request): bool
    {
        if ($this->blocked() || $this->finished()) {
            return false;
        }

        $device = $this->device;
        if ($device === null || $device->hijacked()) {
            return false;
        }

        foreach (Config::get('devices.ignore_restart', []) as $ignore) {
            if ($this->shouldIgnoreRestart($request, $ignore)) {
                return false;
            }
        }

        $user = $request->user(Config::get('devices.auth_guard'));
        if ($user === null) {
            return false;
        }

        if (! $user->hasDevice($device)) {
            return false;
        }

        return $this->renew();
    }

    /**
     * @param  array<string, mixed>  $ignore
     */
    private function shouldIgnoreRestart(Request $request, array $ignore): bool
    {
        /** @var Route $route */
        $route = $request->route();

        return ($route->getName() === $ignore['route'] || $route->getAction() === $ignore['route'])
            && $route->methods()[0] === $ignore['method'];
    }

    public function block(?Authenticatable $user = null): bool
    {
        $user = $user ?? user();

        $this->status = SessionStatus::Blocked;
        $this->blocked_by = $user?->getAuthIdentifier();

        if ($this->save()) {
            event(new SessionBlockedEvent($this, $user));

            return true;
        }

        return false;
    }

    public function unblock(?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($this->status !== SessionStatus::Blocked) {
            return false;
        }

        $this->status = SessionStatus::Active;
        $this->blocked_by = null;

        if ($this->save()) {
            event(new SessionUnblockedEvent($this, $user));

            return true;
        }

        return false;
    }

    public function inactive(): bool
    {
        $seconds = Config::get('devices.inactivity_seconds', 1200);
        if ($seconds === 0) {
            return false;
        }

        return abs((int) $this->last_activity_at?->timestamp - (int) now()->timestamp) > $seconds;
    }

    public function isCurrent(): bool
    {
        return session_uuid() === $this->uuid;
    }

    public function blocked(): bool
    {
        return $this->status === SessionStatus::Blocked;
    }

    public function locked(): bool
    {
        return $this->status === SessionStatus::Locked;
    }

    public function finished(): bool
    {
        return $this->status === SessionStatus::Finished;
    }

    public function unlock(): void
    {
        if ($this->status === SessionStatus::Locked) {
            $this->status = SessionStatus::Active;
            $this->unlocked_at = Carbon::now();

            if ($this->save()) {
                event(new SessionUnlockedEvent($this, user()));
            }
        }
    }

    public function key(): string
    {
        return SessionCache::key($this->uuid);
    }

    public function ttl(): ?int
    {
        return null;
    }

    public static function byUuid(StorableId|string $uuid, bool $cached = true): ?self
    {
        if (is_string($uuid)) {
            $uuid = SessionIdFactory::from($uuid);
        }

        if (! $cached) {
            /** @var Session|null $session */
            $session = self::where('uuid', (string) $uuid)->first();

            return $session;
        }

        return SessionCache::remember(
            key: SessionCache::key($uuid),
            callback: function () use ($uuid) {
                return self::where('uuid', (string) $uuid)->first();
            }
        );
    }

    /**
     * @throws SessionNotFoundException
     */
    public static function byUuidOrFail(StorableId|string $uuid): self
    {
        return self::byUuid($uuid) ?? throw SessionNotFoundException::withSession($uuid);
    }

    public static function current(): ?Session
    {
        if (session_uuid() === null) {
            return null;
        }

        return self::byUuid(session_uuid());
    }

    public static function boot(): void
    {
        parent::boot();

        static::created(function (Session $session) {
            SessionCache::forget($session);
            SessionCache::put($session);
        });

        static::updated(function (Session $session) {
            SessionCache::forget($session);
            SessionCache::put($session);
        });
    }

    public static function resolveClientIp(): string
    {
        if (! App::environment('local')) {
            return (string) (request()->ip() ?? '');
        }

        $pool = config('devices.development_ip_pool', []);
        if (! is_array($pool) || $pool === []) {
            return (string) (request()->ip() ?? '127.0.0.1');
        }

        $requestIp = (string) (request()->ip() ?? '127.0.0.1');
        $index = abs(crc32($requestIp)) % count($pool);

        return (string) $pool[$index];
    }

    private static function getIp(): string
    {
        return self::resolveClientIp();
    }
}
