<?php

namespace Ninja\DeviceTracker\Tests\Feature\Http\Middleware;

use Faker\Provider\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ninja\DeviceTracker\Enums\Transport;
use Ninja\DeviceTracker\Exception\UnknownDeviceDetectedException;
use Ninja\DeviceTracker\Http\Middleware\DeviceTracker;
use Ninja\DeviceTracker\Models\Device;
use Ninja\DeviceTracker\Tests\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeviceTrackerTest extends FeatureTestCase
{
    public const DEVICE_ID_PARAMETER = 'laravel_device_id';

    public static function nullUserAgentDataProvider(): array
    {
        $prefix = 'null_ua_';

        return [
            // Unknown Devices not allowed
            $prefix.'unknown devices not allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => false,
                ],
                'userAgent' => null,
                'exceptionClass' => HttpException::class,
            ],
            $prefix.'unknown devices not allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => false,
                ],
                'userAgent' => null,
                'exceptionClass' => UnknownDeviceDetectedException::class,
            ],

            // Unknown Devices allowed
            $prefix.'unknown devices allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => true,
                ],
                'userAgent' => null,
                'exceptionClass' => null,
            ],
            $prefix.'unknown devices allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => true,
                ],
                'userAgent' => null,
                'exceptionClass' => null,
            ],
        ];
    }

    public static function invalidUserAgentDataProvider(): array
    {
        $invalidUserAgent = 'absolutely_invalid_user_agent';
        $prefix = 'invalid_ua_';

        return [
            // Unknown Devices not allowed
            $prefix.'unknown devices not allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => false,
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => HttpException::class,
            ],
            $prefix.'unknown devices not allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => false,
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => UnknownDeviceDetectedException::class,
            ],

            // Unknown Devices allowed
            $prefix.'unknown devices allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => true,
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => null,
            ],
            $prefix.'unknown devices allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => true,
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => null,
            ],
        ];
    }

    public static function validUserAgentDataProvider(): array
    {
        $userAgent = UserAgent::userAgent();
        $prefix = 'valid ua, ';

        return [
            // Unknown Devices not allowed
            $prefix.'unknown devices not allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => false,
                ],
                'userAgent' => $userAgent,
                'exceptionClass' => null,
            ],
            $prefix.'unknown devices not allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => false,
                ],
                'userAgent' => $userAgent,
                'exceptionClass' => null,
            ],

            // Unknown Devices allowed
            $prefix.'unknown devices allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => true,
                ],
                'userAgent' => $userAgent,
                'exceptionClass' => null,
            ],
            $prefix.'unknown devices allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => true,
                ],
                'userAgent' => $userAgent,
                'exceptionClass' => null,
            ],
        ];
    }

    public static function invalidWhitelistedUserAgentDataProvider(): array
    {
        $invalidUserAgent = 'absolutely_invalid_user_agent';
        $prefix = 'invalid_whitelisted_ua_';

        return [
            // Unknown Devices not allowed
            $prefix.'unknown devices not allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => false,
                    'devices.user_agent_whitelist' => [$invalidUserAgent],
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => null,
            ],
            $prefix.'unknown devices not allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => false,
                    'devices.user_agent_whitelist' => [$invalidUserAgent],
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => null,
            ],

            // Unknown Devices allowed
            $prefix.'unknown devices allowed, 403' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
                    'devices.allow_unknown_devices' => true,
                    'devices.user_agent_whitelist' => [$invalidUserAgent],
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => null,
            ],
            $prefix.'unknown devices allowed, exception' => [
                'config' => [
                    'devices.middlewares.device-tracker.exception_on_invalid_devices' => true,
                    'devices.allow_unknown_devices' => true,
                    'devices.user_agent_whitelist' => [$invalidUserAgent],
                ],
                'userAgent' => $invalidUserAgent,
                'exceptionClass' => null,
            ],
        ];
    }

    #[DataProvider('nullUserAgentDataProvider')]
    #[DataProvider('invalidUserAgentDataProvider')]
    #[DataProvider('validUserAgentDataProvider')]
    #[DataProvider('invalidWhitelistedUserAgentDataProvider')]
    public function test_with_user_agent(
        array $config,
        ?string $userAgent = null,
        ?string $exceptionClass = null
    ): void {
        $config = array_merge($config, [
            'devices.transports.device_id.transport_hierarchy' => ['cookie'],
            'devices.transports.device_id.parameter' => self::DEVICE_ID_PARAMETER,
        ]);
        $this->setConfig($config);

        $request = request();
        $request->headers->set('User-Agent', $userAgent);

        $next = function (Request $nextRequest) use ($exceptionClass) {
            if (! empty($exceptionClass)) {
                $this->fail('It should not enter in the next middleware');
            }

            $this->assertNull($nextRequest->headers->get(self::DEVICE_ID_PARAMETER));

            return new Response(null, 200);
        };
        if (! empty($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        // Nothing happened yet
        $this->assertNull(device_uuid());
        $middleware = new DeviceTracker;
        /** @var Response $response */
        $response = $middleware->handle($request, $next);

        $this->assertNotNull(device_uuid());
        $this->assertTrue($response->headers->has('set-cookie'));
        $cookies = $response->headers->getCookies();
        $this->assertStringStartsWith(self::DEVICE_ID_PARAMETER, implode(',', $cookies));
        $this->assertEquals(0, Device::query()->count());
    }

    public function test_custom_http_error_code(): void
    {
        $code = 412;
        $config = [
            'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
            'devices.middlewares.device-tracker.http_error_code' => $code,
            'devices.allow_unknown_devices' => false,
            'devices.user_agent_whitelist' => [],
        ];
        $this->setConfig($config);

        $request = request();
        $request->headers->set('User-Agent', null);

        $next = function (Request $nextRequest) {
            $this->fail('It should not enter in the next middleware');
        };

        // Nothing happened yet
        $this->assertNull(device_uuid());
        $middleware = new DeviceTracker;
        try {
            $middleware->handle($request, $next);
            $this->fail('It should generate an exception');
        } catch (HttpException $e) {
            $this->assertEquals($code, $e->getStatusCode());
        }
    }

    public function test_custom_http_error_code_with_invalid_code(): void
    {
        $code = 10000;
        $config = [
            'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
            'devices.middlewares.device-tracker.http_error_code' => $code,
            'devices.allow_unknown_devices' => false,
            'devices.user_agent_whitelist' => [],
        ];
        $this->setConfig($config);

        $request = request();
        $request->headers->set('User-Agent', null);

        $next = function (Request $nextRequest) {
            $this->fail('It should not enter in the next middleware');
        };

        // Nothing happened yet
        $this->assertNull(device_uuid());
        $middleware = new DeviceTracker;
        try {
            $middleware->handle($request, $next);
            $this->fail('It should generate an exception');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_default_device_response_transport(): void
    {
        $config = [
            'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
            'devices.allow_unknown_devices' => true,
            'devices.transports.device_id.transport_hierarchy' => ['cookie'],
            'devices.transports.device_id.response_transport' => 'cookie',
            'devices.transports.device_id.parameter' => self::DEVICE_ID_PARAMETER,
            'devices.user_agent_whitelist' => [],
        ];
        $this->setConfig($config);

        $request = request();
        $request->headers->set('User-Agent', null);

        $next = function () {
            return new Response('', 200);
        };

        $middleware = new DeviceTracker;
        $response = $middleware->handle($request, $next);

        $this->assertTrue($response instanceof Response);
        $this->assertNotEmpty($response->headers->getCookies());

        $cookieNames = array_map(fn (Cookie $cookie) => $cookie->getName(), $response->headers->getCookies());
        $this->assertContains(self::DEVICE_ID_PARAMETER, $cookieNames);

        $this->assertFalse($response->headers->has(self::DEVICE_ID_PARAMETER));
    }

    public function test_custom_device_response_transport(): void
    {
        $config = [
            'devices.middlewares.device-tracker.exception_on_invalid_devices' => false,
            'devices.allow_unknown_devices' => true,
            'devices.transports.device_id.transport_hierarchy' => ['cookie'],
            'devices.transports.device_id.response_transport' => 'cookie',
            'devices.transports.device_id.parameter' => self::DEVICE_ID_PARAMETER,
            'devices.user_agent_whitelist' => [],
        ];
        $this->setConfig($config);

        $request = request();
        $request->headers->set('User-Agent', null);

        $next = function () {
            return new Response('', 200);
        };

        $middleware = new DeviceTracker;
        $response = $middleware->handle($request, $next, null, Transport::Header->value);

        $this->assertTrue($response instanceof Response);
        $this->assertEmpty($response->headers->getCookies());

        $this->assertTrue($response->headers->has(self::DEVICE_ID_PARAMETER));
    }
}
