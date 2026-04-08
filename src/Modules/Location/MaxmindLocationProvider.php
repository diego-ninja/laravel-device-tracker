<?php

namespace Ninja\DeviceTracker\Modules\Location;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Ninja\DeviceTracker\Cache\LocationCache;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Ninja\DeviceTracker\Modules\Location\Exception\LocationLookupFailedException;

final class MaxmindLocationProvider extends AbstractLocationProvider
{
    public function __construct(private readonly Reader $reader) {}

    public function locate(string $ip): Location
    {
        $key = sprintf('%s:%s', LocationCache::KEY_PREFIX, $ip);
        $this->location = LocationCache::remember($key, function () use ($ip) {
            try {
                return $this->lookup($ip);
            } catch (AddressNotFoundException|InvalidDatabaseException $e) {
                throw LocationLookupFailedException::forIp($ip, $e);
            }
        });

        return $this->location;
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidDatabaseException
     */
    private function lookup(string $ip): Location
    {
        $record = $this->reader->city($ip);

        $accuracyRadius = $record->location->accuracyRadius;

        return Location::fromArray([
            'ip' => $ip,
            'country' => $record->country->isoCode,
            'region' => $record->mostSpecificSubdivision->name,
            'city' => $record->city->name,
            'postal' => $record->postal->code,
            'latitude' => (string) $record->location->latitude,
            'longitude' => (string) $record->location->longitude,
            'timezone' => $record->location->timeZone,
            'accuracyRadius' => $accuracyRadius !== null ? (string) $accuracyRadius : null,
        ]);
    }
}
