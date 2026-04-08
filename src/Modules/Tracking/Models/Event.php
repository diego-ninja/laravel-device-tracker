<?php

namespace Ninja\DeviceTracker\Modules\Tracking\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ninja\DeviceTracker\Contracts\StorableId;
use Ninja\DeviceTracker\DTO\Metadata;
use Ninja\DeviceTracker\Factories\EventIdFactory;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Modules\Tracking\Enums\EventType;
use Ninja\DeviceTracker\Traits\PropertyProxy;

/**
 * Class Event
 *
 * @mixin \Illuminate\Database\Query\Builder
 * @mixin \Illuminate\Database\Eloquent\Builder<Event>
 *
 * @property int $id unsigned int
 * @property StorableId $uuid string
 * @property StorableId $device_uuid string
 * @property StorableId $session_uuid string
 * @property EventType $type string
 * @property string $ip_address string
 * @property Metadata $metadata json
 * @property Carbon $occurred_at datetime
 * @property-read Device|null $device
 * @property-read Session|null $session
 */
class Event extends Model
{
    use PropertyProxy;

    protected $table = 'device_events';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'device_uuid',
        'session_uuid',
        'type',
        'metadata',
        'ip_address',
        'occurred_at',
    ];

    /**
     * @return Attribute<Metadata, Metadata>
     */
    public function metadata(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Metadata::from(json_decode($value, true)) : new Metadata([]),
            set: fn (Metadata $value) => $value->json()
        );
    }

    /**
     * @return Attribute<EventType|null, EventType>
     */
    public function type(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? EventType::tryFrom($value) : null,
            set: fn (EventType $value) => $value->value
        );
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_uuid', 'uuid');
    }

    public static function log(EventType $type, ?Session $session, ?Metadata $metadata): Event
    {
        /** @var Event $event */
        $event = static::create([
            'uuid' => EventIdFactory::generate(),
            'device_uuid' => $session->device_uuid ?? device_uuid(),
            'session_uuid' => $session?->uuid,
            'type' => $type,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);

        return $event;
    }
}
