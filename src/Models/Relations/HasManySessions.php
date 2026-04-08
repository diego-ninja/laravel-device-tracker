<?php

namespace Ninja\DeviceTracker\Models\Relations;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Ninja\DeviceTracker\Enums\SessionStatus;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Models\Session;

/**
 * @extends HasMany<Session, Device|User>
 *
 * @phpstan-param Device|User $parent
 */
final class HasManySessions extends HasMany
{
    public function first(): ?Session
    {
        /** @var Session|null $session */
        $session = $this->query
            ->with('device')
            ->orderBy('started_at')
            ->first();

        return $session;
    }

    public function last(): ?Session
    {
        /** @var Session|null $session */
        $session = $this->query
            ->with('device')
            ->orderByDesc('started_at')
            ->first();

        return $session;
    }

    public function current(): ?Session
    {
        /** @var Session|null $session */
        $session = $this->query
            ->with('device')
            ->where('uuid', session_uuid())
            ->first();

        return $session;
    }

    public function recent(): ?Session
    {
        /** @var Session|null $session */
        $session = $this->query
            ->with('device')
            ->where('status', SessionStatus::Active->value)
            ->orderByDesc('last_activity_at')
            ->first();

        return $session;
    }

    /**
     * @return Collection<int, Session>
     */
    public function active(bool $exceptCurrent = false): Collection
    {
        $query = $this->query
            ->with('device')
            ->where('finished_at', null)
            ->where('status', SessionStatus::Active);

        if ($exceptCurrent) {
            if (session_uuid() !== null) {
                $query->where('id', '!=', session_uuid());
            }
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Session>
     */
    public function finished(): Collection
    {
        return $this->query
            ->with('device')
            ->whereNotNull('finished_at')
            ->where('status', SessionStatus::Finished)
            ->orderBy('finished_at', 'desc')
            ->get();
    }

    public function signout(bool $logoutCurrentSession = false): void
    {
        if ($logoutCurrentSession) {
            $this->current()?->end();
        }

        $this->get()->each(function (Session $session) {
            $session->end();
        });
    }
}
