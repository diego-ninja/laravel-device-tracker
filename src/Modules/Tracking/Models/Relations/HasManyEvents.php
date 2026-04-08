<?php

namespace Ninja\DeviceTracker\Modules\Tracking\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Modules\Tracking\Enums\EventType;
use Ninja\DeviceTracker\Modules\Tracking\Models\Event;

/**
 * @extends HasMany<Event, Device|Session>
 *
 * @phpstan-param Device|Session $parent
 */
class HasManyEvents extends HasMany
{
    /**
     * @return HasMany<Event, Device|Session>|Builder<Event>
     */
    public function type(EventType $type): HasMany|Builder
    {
        return $this->query->where('type', $type);
    }

    /**
     * @return HasMany<Event, Device|Session>|Builder<Event>
     */
    public function login(): HasMany|Builder
    {
        return $this->type(EventType::Login);
    }

    /**
     * @return HasMany<Event, Device|Session>|Builder<Event>
     */
    public function logout(): HasMany|Builder
    {
        return $this->type(EventType::Logout);
    }

    /**
     * @return HasMany<Event, Device|Session>|Builder<Event>
     */
    public function signup(): HasMany|Builder
    {
        return $this->type(EventType::Signup);
    }

    /**
     * @return HasMany<Event, Device|Session>|Builder<Event>
     */
    public function views(): HasMany|Builder
    {
        return $this->type(EventType::PageView);
    }

    /**
     * @return EloquentCollection<int, Event>
     */
    public function last(int $count = 1): EloquentCollection
    {
        return $this->query->orderByDesc('occurred_at')->limit($count)->get();
    }
}
