<?php

namespace Ninja\DeviceTracker\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Jenssegers\Agent\Agent;
use Ninja\DeviceTracker\Enums\DeviceStatus;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Events\DeviceCreatedEvent;
use Ninja\DeviceTracker\Events\DeviceHijackedEvent;
use Ninja\DeviceTracker\Events\DeviceVerifiedEvent;
use Ninja\DeviceTracker\Exception\DeviceNotFoundException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Class DeviceManager
 *
 * @package Ninja\DeviceManager\Models
 *
 * @mixin \Illuminate\Database\Query\Builder
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property string                       $id                     unsigned string
 * @property UuidInterface                $uuid                   string
 * @property integer                      $user_id                unsigned string
 * @property DeviceStatus                 $status                 string
 * @property string                       $browser                string
 * @property string                       $browser_version        string
 * @property string                       $platform               string
 * @property string                       $platform_version       string
 * @property boolean                      $mobile                 boolean
 * @property string                       $device                 string
 * @property string                       $device_type            string
 * @property boolean                      $robot                  boolean
 * @property string                       $source                 string
 * @property string                       $ip                     string
 * @property Carbon                       $created_at             datetime
 * @property Carbon                       $updated_at             datetime
 * @property Carbon                       $verified_at            datetime
 * @property Carbon                       $hijacked_at            datetime
 *
 */
class Device extends Model
{
    protected $table = 'devices';

    protected $fillable = [
        'uuid',
        'user_id',
        'browser',
        'browser_version',
        'platform',
        'platform_version',
        'mobile',
        'device',
        'device_type',
        'robot',
        'ip',
        'source',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'device_uuid', 'uuid');
    }

    public function user(): HasOne
    {
        return $this->hasOne(Config::get("devices.authenticatable_class"), 'id', 'user_id');
    }

    public function uuid(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => Uuid::fromString($value),
            set: fn(UuidInterface $value) => $value->toString()
        );
    }

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => DeviceStatus::from($value),
            set: fn(DeviceStatus $value) => $value->value
        );
    }

    public function activeSessions(): Collection
    {
        return $this
            ->sessions()
            ->where('status', SessionStatus::Active)
            ->get();
    }

    public function isCurrent(): bool
    {
        return $this->uuid->toString() === self::getDeviceUuid()?->toString();
    }

    public function verify(): void
    {
        $this->verified_at = now();
        $this->status = DeviceStatus::Verified;

        if ($this->save()) {
            DeviceVerifiedEvent::dispatch($this, $this->user);
        }
    }

    public function verified(): bool
    {
        return $this->status === DeviceStatus::Verified;
    }

    public function hijack(?Authenticatable $user = null): void
    {
        $user = $user ?? Auth::user();

        $this->hijacked_at = now();
        $this->status = DeviceStatus::Hijacked;

        foreach ($this->sessions as $session) {
            $session->block();
        }

        if ($this->save()) {
            DeviceHijackedEvent::dispatch($this, $user);
        }
    }

    public function hijacked(): bool
    {
        return $this->status === DeviceStatus::Hijacked;
    }

    public function forget(): bool
    {
        $this->sessions()->update([
            'finished_at' => now(),
            'status' => SessionStatus::Finished
        ]);

        return $this->delete();
    }

    public static function register(string $source = null, Authenticatable $user = null): ?self
    {
        $agent = new Agent(
            headers: request()->headers->all(),
            userAgent: $source
        );

        $device = self::create([
            'uuid' => Uuid::uuid7(),
            'user_id' => $user->id,
            'browser' => $agent->browser(),
            'browser_version' => $agent->version($agent->browser()),
            'platform' => $agent->platform(),
            'platform_version' => $agent->version($agent->platform()),
            'mobile' => $agent->isMobile(),
            'device' => $agent->device(),
            'device_type' => $agent->deviceType(),
            'robot' => $agent->isRobot(),
            'ip' => request()->ip(),
            'source' => $source
        ]);

        if ($device) {
            DeviceCreatedEvent::dispatch($device, $user);
            return $device;
        }

        return null;
    }

    /**
     * @throws DeviceNotFoundException
     */
    public static function findByUuid(UuidInterface|string $uuid): ?self
    {
        if (is_string($uuid)) {
            $uuid = Uuid::fromString($uuid);
        }

        $session = self::where('uuid', $uuid->toString())->first();
        if (!$session) {
            throw DeviceNotFoundException::withDevice($uuid);
        }

        return $session;
    }

    public static function current(): ?self
    {
        return self::findByUuid(self::getDeviceUuid());
    }

    public static function getDeviceUuid(): ?UuidInterface
    {
        $cookieName = Config::get('devices.device_id_cookie_name');
        return Cookie::has($cookieName) ? Uuid::fromString(Cookie::get($cookieName)) : null;
    }
}
