<?php

namespace Ninja\DeviceTracker\Modules\Fingerprinting\Injector;

use Illuminate\Http\Response;
use Ninja\DeviceTracker\Facades\DeviceManager;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Modules\Fingerprinting\Injector\Contracts\Injector;
use Ninja\DeviceTracker\Modules\Fingerprinting\Injector\Enums\Library;
use Ninja\DeviceTracker\Transports\FingerprintTransport;

abstract class AbstractInjector implements Injector
{
    public const LIBRARY_NAME = '';

    public const LIBRARY_URL = '';

    protected static function script(Device $device): string
    {
        /** @var view-string $view */
        $view = sprintf('laravel-devices::%s-tracking-script', static::LIBRARY_NAME);

        return view($view, [
            'current' => $device->fingerprint,
            'transport' => [
                'type' => FingerprintTransport::responseTransport()->value,
                'key' => FingerprintTransport::parameter(),
            ],
            'library' => [
                'name' => static::LIBRARY_NAME,
                'url' => static::LIBRARY_URL,
            ],
        ])->render();
    }

    public function inject(Response $response): Response
    {
        $content = $response->getContent();
        if (! $content) {
            return $response;
        }

        $device = DeviceManager::current();
        if ($device) {
            $script = self::script($device);
            $response->setContent(str_replace('</head>', $script.'</head>', $content));
        }

        return $response;
    }

    public function library(): Library
    {
        return Library::from(static::LIBRARY_NAME);
    }
}
