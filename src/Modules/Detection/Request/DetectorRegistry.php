<?php

namespace Ninja\DeviceTracker\Modules\Detection\Request;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Ninja\DeviceTracker\Modules\Detection\Contracts\RequestTypeDetector;
use Ninja\DeviceTracker\Modules\Tracking\Cache\EventTypeCache;
use Ninja\DeviceTracker\Modules\Tracking\Enums\EventType;

final class DetectorRegistry implements RequestTypeDetector
{
    /**
     * @var Collection<int, RequestTypeDetector>
     */
    private Collection $detectors;

    public function __construct()
    {
        /** @var list<RequestTypeDetector> $stack */
        $stack = [
            new AuthenticationRequestDetector,
            new LivewireRequestDetector,
            new ApiRequestDetector,
            new AjaxRequestDetector,
            new RedirectResponseDetector,
            new PageViewDetector,
        ];

        $this->detectors = collect($stack)
            ->sortByDesc(fn (RequestTypeDetector $detector) => $detector->priority())
            ->values();
    }

    public function priority(): int
    {
        return 0;
    }

    public function detect(Request $request, mixed $response): ?EventType
    {
        $cache = EventTypeCache::withRequest($request);

        return $cache::remember($cache::key(''), function () use ($request, $response) {
            return $this->detectors->first(
                function (RequestTypeDetector $detector) use ($request, $response): bool {
                    return $detector->supports($request, $response);
                }
            )?->detect($request, $response);
        });
    }

    public function supports(Request $request, mixed $response): bool
    {
        return $this->detectors->contains(fn (RequestTypeDetector $detector) => $detector->supports($request, $response));
    }
}
