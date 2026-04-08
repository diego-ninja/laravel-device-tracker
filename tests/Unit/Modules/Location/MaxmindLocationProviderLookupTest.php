<?php

namespace Ninja\DeviceTracker\Tests\Unit\Modules\Location;

use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use Ninja\DeviceTracker\Modules\Location\MaxmindLocationProvider;
use Ninja\DeviceTracker\Tests\TestCase;

final class MaxmindLocationProviderLookupTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_lookup_casts_accuracy_radius_to_string(): void
    {
        $record = new City([
            'country' => [
                'iso_code' => 'US',
                'names' => ['en' => 'United States'],
            ],
            'city' => [
                'names' => ['en' => 'Example'],
            ],
            'location' => [
                'latitude' => 1.25,
                'longitude' => 2.5,
                'time_zone' => 'UTC',
                'accuracy_radius' => 99,
            ],
            'postal' => [
                'code' => '12345',
            ],
            'subdivisions' => [
                [
                    'names' => ['en' => 'Region'],
                    'iso_code' => 'RG',
                ],
            ],
        ]);

        $reader = \Mockery::mock(Reader::class);
        $reader->shouldReceive('city')
            ->once()
            ->with('203.0.113.10')
            ->andReturn($record);

        $provider = new MaxmindLocationProvider($reader);
        $method = new \ReflectionMethod(MaxmindLocationProvider::class, 'lookup');
        $dto = $method->invoke($provider, '203.0.113.10');

        $this->assertSame('99', $dto->accuracyRadius);
    }
}
