<?php

namespace Ninja\DeviceTracker\Modules\Fingerprinting\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\Exception\FingerprintDuplicatedException;
use Ninja\DeviceTracker\Facades\DeviceManager;
use Ninja\DeviceTracker\Modules\Fingerprinting\Injector\Enums\Library;
use Ninja\DeviceTracker\Modules\Fingerprinting\Injector\Factories\InjectorFactory;
use Ninja\DeviceTracker\Transports\FingerprintTransport;

final class FingerprintTracker
{
    /**
     * @throws FingerprintDuplicatedException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! DeviceManager::fingerprinted()) {
            if ($this->redirect($response)) {
                return $response;
            }

            if (! $this->html($response)) {
                return $response;
            }

            return $this->addFingerprint($response);
        }

        return $response;
    }

    private function addFingerprint(Response $response): Response
    {
        $fingerprint = FingerprintTransport::currentId();
        $library = Config::get('devices.fingerprint_client_library', Library::FingerprintJS);

        if ($fingerprint === null) {
            return InjectorFactory::make($library)->inject($response);
        }

        return $response;
    }

    private function html(mixed $response): bool
    {
        if (! $response instanceof Response) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'text/html')) {
            return false;
        }

        return true;
    }

    private function redirect(mixed $response): bool
    {
        return $response instanceof RedirectResponse;
    }
}
