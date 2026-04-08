<?php

namespace Ninja\DeviceTracker\Transports;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Ninja\DeviceTracker\Contracts\StorableId;
use Ninja\DeviceTracker\Enums\Transport;
use Stringable;
use Throwable;

abstract class AbstractTransport
{
    protected const CONFIG_PARAMETER = 'transports.device_id.parameter';

    protected const CONFIG_PARAMETER_FALLBACK = 'device_id_parameter';

    protected const CONFIG_ALTERNATIVE_PARAMETER = 'transports.device_id.alternative_parameter';

    protected const CONFIG_ALTERNATIVE_PARAMETER_FALLBACK = 'device_id_alternative_parameter';

    protected const CONFIG_TRANSPORT_HIERARCHY_KEY = 'transports.device_id.transport_hierarchy';

    protected const CONFIG_TRANSPORT_HIERARCHY_KEY_FALLBACK = 'device_id_transport_hierarchy';

    protected const CONFIG_RESPONSE_TRANSPORT_KEY = 'transports.device_id.response_transport';

    protected const CONFIG_RESPONSE_TRANSPORT_KEY_FALLBACK = 'device_id_response_transport';

    protected const DEFAULT_TRANSPORT = Transport::Cookie;

    protected const DEFAULT_RESPONSE_TRANSPORT = Transport::Cookie;

    public function __construct(public Transport $transport) {}

    abstract public static function make(Transport $transport): static;

    /**
     * @return class-string<StorableId>
     */
    abstract protected static function storableIdFactory(): string;

    /**
     * @return array<Transport>
     */
    protected static function transportsHierarchy(): array
    {
        $hierarchy = config(
            sprintf('devices.%s', static::CONFIG_TRANSPORT_HIERARCHY_KEY),
            config(sprintf('devices.%s', static::CONFIG_TRANSPORT_HIERARCHY_KEY_FALLBACK), []),
        );
        if (empty($hierarchy) || ! is_array($hierarchy)) {
            $hierarchy = [static::DEFAULT_TRANSPORT];
        }

        return array_filter(
            array_map(
                fn (mixed $transport) => $transport instanceof Transport
                    ? $transport
                    : (
                        is_string($transport)
                            ? Transport::tryFrom($transport)
                            : null
                    ),
                $hierarchy
            )
        );
    }

    public static function current(): static
    {
        return static::make(
            static::currentFromHierarchy(
                static::transportsHierarchy(),
                static::DEFAULT_TRANSPORT,
            ),
        );
    }

    final public static function currentId(): ?StorableId
    {
        return self::currentIdFromHierarchy(static::transportsHierarchy());
    }

    public static function responseTransport(): Transport
    {
        $responseTransportString = config(
            sprintf('devices.%s', static::CONFIG_RESPONSE_TRANSPORT_KEY),
            config(
                sprintf('devices.%s', static::CONFIG_RESPONSE_TRANSPORT_KEY_FALLBACK),
                static::DEFAULT_RESPONSE_TRANSPORT->value,
            ),
        );

        return Transport::tryFrom($responseTransportString) ?? static::DEFAULT_RESPONSE_TRANSPORT;
    }

    public function get(?string $parameter = null): ?StorableId
    {
        try {
            return match ($this->transport) {
                Transport::Cookie => $this->fromCookie($parameter),
                Transport::Header => $this->fromHeader($parameter),
                Transport::Session => $this->fromSession($parameter),
                Transport::Request => $this->fromRequest($parameter),
            } ?? $this->fromRequest($parameter);
        } catch (Throwable) {
            return null;
        }
    }

    public static function cleanRequest(): void
    {
        static::cleanRequestHierarchy(static::transportsHierarchy());

        // Clean any propagation
        static::make(Transport::Request)->clean();
    }

    protected function clean(?string $parameter = null): void
    {
        try {
            match ($this->transport) {
                Transport::Cookie => $this->cleanFromCookie($parameter),
                Transport::Header => $this->cleanFromHeader($parameter),
                Transport::Session => $this->cleanFromSession($parameter),
                Transport::Request => $this->cleanFromRequest($parameter),
            };
        } catch (Throwable) {
        }
    }

    /**
     * @param  array<Transport>  $hierarchy
     */
    protected static function currentFromHierarchy(array $hierarchy, Transport $default): Transport
    {
        $defaultTransport = null;

        foreach ($hierarchy as $transport) {
            if (is_null($defaultTransport)) {
                $defaultTransport = $transport;
            }
            $id = static::make($transport)->get();
            if (! is_null($id)) {
                return $transport;
            }
        }

        return $defaultTransport ?? $default;
    }

    public static function parameter(): string
    {
        return config(
            sprintf('devices.%s', static::CONFIG_PARAMETER),
            config(sprintf('devices.%s', static::CONFIG_PARAMETER_FALLBACK)),
        );
    }

    protected static function alternativeParameter(): ?string
    {
        return config(
            sprintf('devices.%s', static::CONFIG_ALTERNATIVE_PARAMETER),
            config(sprintf('devices.%s', static::CONFIG_ALTERNATIVE_PARAMETER_FALLBACK)),
        );
    }

    /**
     * @param  array<Transport>  $hierarchy
     */
    protected static function currentIdFromHierarchy(
        array $hierarchy,
    ): ?StorableId {
        $parameter = static::parameter();

        // First of all check propagated value in request
        $id = static::make(Transport::Request)->get($parameter);
        if (! is_null($id)) {
            return $id;
        }

        // Then check all hierarchy
        foreach ($hierarchy as $transport) {
            $id = static::make($transport)->get($parameter);
            if (! is_null($id)) {
                return $id;
            }
        }

        $alternativeParameter = static::alternativeParameter();
        foreach ($hierarchy as $transport) {
            $id = static::make($transport)->get($alternativeParameter);
            if (! is_null($id)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array<Transport>  $hierarchy  Same shape as {@see static::transportsHierarchy()} (resolved enum cases, not raw config strings).
     */
    protected static function cleanRequestHierarchy(array $hierarchy): void
    {
        foreach ($hierarchy as $transport) {
            static::make($transport)->clean();
        }
    }

    public static function set(mixed $response, string $id): mixed
    {
        if (! static::isValidResponse($response)) {
            return $response;
        }

        $transport = static::responseTransport();

        $callable = match ($transport) {
            // Transport::Cookie and Transport::Request
            default => function () use ($response, $transport, $id): mixed {
                $response->withCookie(
                    Cookie::forever(
                        name: static::make($transport)->parameter(),
                        value: (string) $id,
                        secure: Config::get('session.secure', false),
                        httpOnly: Config::get('session.http_only', true)
                    )
                );

                return $response;
            },
            Transport::Header => function () use ($response, $transport, $id): mixed {
                $response->header(static::make($transport)->parameter(), $id);

                return $response;
            },
            Transport::Session => function () use ($response, $transport, $id): mixed {
                Session::put(static::make($transport)->parameter(), $id);

                return $response;
            },
        };

        return $callable();
    }

    public static function propagate(?StorableId $id = null, ?Request $request = null): Request
    {
        $id ??= static::currentId();
        $request ??= request();

        if ($id === null) {
            return $request;
        }

        $parameter = static::parameter();

        return $request->merge([$parameter => $id]);
    }

    protected static function isValidResponse(mixed $response): bool
    {
        $valid = [
            Response::class,
            JsonResponse::class,
        ];

        return in_array(get_class($response), $valid, true);
    }

    private function decryptCookie(string $cookieValue): string
    {
        $decryptedString = Crypt::decrypt($cookieValue, false);

        return CookieValuePrefix::remove($decryptedString);
    }

    private function fromCookie(?string $parameter = null): ?StorableId
    {
        $parameter ??= static::parameter();
        $value = Cookie::get($parameter);

        return $this->fromValue($value);
    }

    private function fromHeader(?string $parameter = null): ?StorableId
    {
        $parameter ??= static::parameter();
        $value = request()->header($parameter);

        return $this->fromValue($value);
    }

    private function fromSession(?string $parameter = null): ?StorableId
    {
        $parameter ??= static::parameter();
        $value = Session::get($parameter);

        return $this->fromValue($value);
    }

    private function fromRequest(?string $parameter = null): ?StorableId
    {
        $parameter ??= static::parameter();
        $value = request()->input($parameter);

        return $this->fromValue($value);
    }

    private function fromValue(mixed $value): ?StorableId
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StorableId) {
            return $value;
        }

        if (! is_string($value) && ! ($value instanceof Stringable)) {
            return null;
        }

        $value = (string) $value;

        $id = null;
        try {
            $id = static::storableIdFactory()::from($value);
        } catch (Throwable) {
        }

        if (! $id instanceof StorableId) {
            $id = static::storableIdFactory()::from($this->decryptCookie($value));
        }

        return $id;
    }

    private function cleanFromCookie(?string $parameter = null): void
    {
        Cookie::forget($parameter ?? static::parameter());
    }

    private function cleanFromHeader(?string $parameter = null): void
    {
        request()->headers->remove($parameter ?? static::parameter());
    }

    private function cleanFromSession(?string $parameter = null): void
    {
        Session::forget($parameter ?? static::parameter());
    }

    private function cleanFromRequest(?string $parameter = null): void
    {
        $parameter ??= static::parameter();
        request()->merge([$parameter => null]);
    }

    public static function forget(): void
    {
        $current = static::responseTransport();

        match ($current) {
            Transport::Cookie => Cookie::queue(Cookie::forget(static::parameter())),
            Transport::Request,
            Transport::Header => null,
            Transport::Session => Session::forget(static::parameter()),
        };
    }
}
