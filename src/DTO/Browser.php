<?php

namespace Ninja\DeviceTracker\DTO;

use JsonSerializable;
use Stringable;

final readonly class Browser implements JsonSerializable, Stringable
{
    public function __construct(
        public string $name,
        public string $version,
        public string $family,
        public string $engine
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            version: $data['version'],
            family: $data['family'],
            engine: $data['engine']
        );
    }

    public function array(): array
    {
        return [
            "name" => $this->name,
            "version" => $this->version,
            "family" => $this->family,
            "engine" => $this->engine
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }

    public function __toString(): string
    {
        return sprintf("%s %s", $this->name, $this->version);
    }

    public function json(): string
    {
        return json_encode($this->array());
    }
}
