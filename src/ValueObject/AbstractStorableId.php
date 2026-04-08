<?php

namespace Ninja\DeviceTracker\ValueObject;

use Ninja\DeviceTracker\Contracts\StorableId;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract readonly class AbstractStorableId implements StorableId
{
    protected UuidInterface $id;

    final protected function __construct(UuidInterface $id)
    {
        $this->id = $id;
    }

    public static function from(StorableId|string $id): StorableId
    {
        return new static(Uuid::fromString($id));
    }

    public static function build(): self
    {
        return new static(Uuid::uuid7());
    }

    public function toString(): string
    {
        return $this->id->toString();
    }

    public function equals(AbstractStorableId $other): bool
    {
        return $this->id->equals($other->id);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
