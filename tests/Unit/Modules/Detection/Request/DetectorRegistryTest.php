<?php

namespace Ninja\DeviceTracker\Tests\Unit\Modules\Detection\Request;

use Ninja\DeviceTracker\Modules\Detection\Request\AuthenticationRequestDetector;
use Ninja\DeviceTracker\Modules\Detection\Request\DetectorRegistry;
use Ninja\DeviceTracker\Tests\TestCase;

final class DetectorRegistryTest extends TestCase
{
    public function test_detectors_are_ordered_by_descending_priority(): void
    {
        $registry = new DetectorRegistry;

        $ref = new \ReflectionClass(DetectorRegistry::class);
        $prop = $ref->getProperty('detectors');
        $prop->setAccessible(true);
        /** @var \Illuminate\Support\Collection<int, object> $detectors */
        $detectors = $prop->getValue($registry);

        $priorities = $detectors->map(fn (object $d) => $d->priority())->all();
        $sorted = $priorities;
        rsort($sorted, SORT_NUMERIC);

        $this->assertSame($sorted, $priorities);
        $this->assertInstanceOf(AuthenticationRequestDetector::class, $detectors->first());
    }
}
