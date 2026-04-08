<?php

namespace Ninja\DeviceTracker\Transports;

use Ninja\DeviceTracker\Enums\Transport;
use Ninja\DeviceTracker\Factories\AbstractStorableIdFactory;
use Ninja\DeviceTracker\Factories\DeviceIdFactory;

final class DeviceTransport extends AbstractTransport
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

    public static function make(Transport $transport): static
    {
        return new self($transport);
    }

    /**
     * @return class-string<AbstractStorableIdFactory>
     */
    protected static function storableIdFactory(): string
    {
        return DeviceIdFactory::class;
    }
}
