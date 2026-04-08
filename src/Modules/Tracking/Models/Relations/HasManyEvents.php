<?php

namespace Ninja\DeviceTracker\Modules\Tracking\Models\Relations;

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
     * Scope by type on the relation so chains like `events()->views()->last(1)` keep `HasManyEvents`.
     */
    public function type(EventType $type): static
    {
        $this->query->where('type', $type);

        return $this;
    }

    public function login(): static
    {
        return $this->type(EventType::Login);
    }

    public function logout(): static
    {
        return $this->type(EventType::Logout);
    }

    public function signup(): static
    {
        return $this->type(EventType::Signup);
    }

    public function views(): static
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
