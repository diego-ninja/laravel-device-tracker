<?php

namespace Ninja\DeviceTracker\Transports;

use Ninja\DeviceTracker\Enums\Transport;
use Ninja\DeviceTracker\Factories\AbstractStorableIdFactory;
use Ninja\DeviceTracker\Factories\FingerprintFactory;

final class FingerprintTransport extends AbstractTransport
{
    protected const CONFIG_PARAMETER = 'transports.fingerprint.parameter';

    protected const CONFIG_PARAMETER_FALLBACK = 'fingerprint_parameter';

    protected const CONFIG_ALTERNATIVE_PARAMETER = 'transports.fingerprint.alternative_parameter';

    protected const CONFIG_ALTERNATIVE_PARAMETER_FALLBACK = 'fingerprint_alternative_parameter';

    protected const CONFIG_TRANSPORT_HIERARCHY_KEY = 'transports.fingerprint.transport_hierarchy';

    protected const CONFIG_TRANSPORT_HIERARCHY_KEY_FALLBACK = 'fingerprint_transport_hierarchy';

    protected const CONFIG_RESPONSE_TRANSPORT_KEY = 'transports.fingerprint.response_transport';

    protected const CONFIG_RESPONSE_TRANSPORT_KEY_FALLBACK = 'fingerprint_response_transport';

    protected const DEFAULT_TRANSPORT = Transport::Cookie;

    protected const DEFAULT_RESPONSE_TRANSPORT = Transport::Cookie;

    public static function make(Transport $transport): static
    {
        return new self($transport);
    }

    /**
     * @return class-string<AbstractStorableIdFactory>
     */
    protected static function storableIdFactory(): string
    {
        return FingerprintFactory::class;
    }
}
