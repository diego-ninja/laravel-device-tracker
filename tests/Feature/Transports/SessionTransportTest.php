<?php

namespace Ninja\DeviceTracker\Tests\Feature\Transports;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Ninja\DeviceTracker\Contracts\StorableId;
use Ninja\DeviceTracker\Enums\Transport;
use Ninja\DeviceTracker\Tests\FeatureTestCase;
use Ninja\DeviceTracker\Transports\SessionTransport;
use PHPUnit\Framework\Attributes\DataProvider;

class SessionTransportTest extends FeatureTestCase
{
    public static function hierarchy_provider(): array
    {
        $uuid = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        return [
            'undefined_hierarchy_unset_id' => [
                'hierarchy' => [],
                'expectedTransport' => Transport::Cookie,
                'expectedId' => null,
            ],
            'undefined_hierarchy_set_id_cookie' => [
                'hierarchy' => [],
                'expectedTransport' => Transport::Cookie,
                'expectedId' => $uuid,
                'cookie' => $uuid,
            ],
            'request_cookie_unset_id' => [
                'hierarchy' => [
                    Transport::Request->value,
                    Transport::Cookie->value,
                ],
                'expectedTransport' => Transport::Request,
                'expectedId' => null,
            ],
            'request_cookie_set_id_request' => [
                'hierarchy' => [
                    Transport::Request->value,
                    Transport::Cookie->value,
                ],
                'expectedTransport' => Transport::Request,
                'expectedId' => $uuid,
                'input' => $uuid,
            ],
            'request_cookie_set_id_cookie' => [
                'hierarchy' => [
                    Transport::Request->value,
                    Transport::Cookie->value,
                ],
                'expectedTransport' => Transport::Cookie,
                'expectedId' => $uuid,
                'cookie' => $uuid,
            ],
            'request_cookie_set_id_header' => [
                'hierarchy' => [
                    Transport::Request->value,
                    Transport::Cookie->value,
                ],
                'expectedTransport' => Transport::Request,
                'expectedId' => null,
                'header' => $uuid,
            ],
            'header_set_id_header' => [
                'hierarchy' => [
                    Transport::Header->value,
                ],
                'expectedTransport' => Transport::Header,
                'expectedId' => $uuid,
                'header' => $uuid,
            ],
            'session_set_id_session' => [
                'hierarchy' => [
                    Transport::Session->value,
                ],
                'expectedTransport' => Transport::Session,
                'expectedId' => $uuid,
                'session' => $uuid,
            ],
        ];
    }

    #[DataProvider('hierarchy_provider')]
    public function test_current_with_hierarchy(
        array $hierarchy,
        Transport $expectedTransport,
        ?string $expectedId,
        ?string $cookie = null,
        ?string $header = null,
        ?string $input = null,
        ?string $session = null,
    ): void {
        $parameter = 'session_id';
        $this->setConfig([
            'devices.transports.session_id.transport_hierarchy' => $hierarchy,
            'devices.transports.session_id.parameter' => $parameter,
        ]);

        if (isset($cookie)) {
            request()->cookies->set($parameter, $cookie);
        }
        if (isset($header)) {
            request()->headers->set($parameter, $header);
        }
        if (isset($input)) {
            request()->merge([$parameter => $input]);
        }
        if (isset($session)) {
            session()->put($parameter, $session);
        }

        $transport = SessionTransport::current();

        $this->assertEquals(SessionTransport::make($expectedTransport), $transport);

        $id = SessionTransport::currentId();

        if (is_null($expectedId)) {
            $this->assertNull($id);
        } else {
            $this->assertEquals($expectedId, $id?->toString());
        }
    }

    public function test_current_with_non_encrypted_cookie(): void
    {
        $parameter = 'device_id';
        $this->setConfig([
            'devices.transports.session_id.transport_hierarchy' => [Transport::Cookie->value],
            'devices.transports.session_id.parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        request()->cookies->set($parameter, $id);

        $transport = SessionTransport::current();

        $this->assertEquals(SessionTransport::make(Transport::Cookie), $transport);

        $storableId = SessionTransport::currentId();

        $this->assertTrue($storableId instanceof StorableId);
        $this->assertEquals($id, $storableId);
    }

    public function test_current_with_encrypted_cookie(): void
    {
        $parameter = 'session_id';
        $key = 'base64:Lzrm+AkE+RrRJWDHON58e8unP7LBK6PlyyLo5k4i6Q0=';
        $this->setConfig([
            'devices.transports.session_id.transport_hierarchy' => [Transport::Cookie->value],
            'devices.transports.session_id.parameter' => $parameter,
            'app.key' => $key,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';
        $encrypter = new Encrypter(base64_decode(Str::after($key, 'base64:')), 'AES-256-CBC');
        $encryptedCookie = $encrypter->encrypt(
            value: CookieValuePrefix::create($parameter, $encrypter->getKey()).$id,
            serialize: false,
        );

        request()->cookies->set($parameter, $encryptedCookie);

        $transport = SessionTransport::current();

        $this->assertEquals(SessionTransport::make(Transport::Cookie), $transport);

        $storableId = SessionTransport::currentId();

        $this->assertTrue($storableId instanceof StorableId);
        $this->assertEquals($id, $storableId);
    }

    public function test_current_from_alternative_parameter(): void
    {
        $parameter = 'session_id';
        $this->setConfig([
            'devices.transports.session_id.transport_hierarchy' => [Transport::Cookie->value],
            'devices.transports.session_id.parameter' => 'invalid_parameter',
            'devices.transports.session_id.alternative_parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        request()->cookies->set($parameter, $id);

        $transport = SessionTransport::current();

        $this->assertEquals(SessionTransport::make(Transport::Cookie), $transport);

        $storableId = SessionTransport::currentId();

        $this->assertTrue($storableId instanceof StorableId);
        $this->assertEquals($id, $storableId);
    }

    public function test_forget_session_from_session(): void
    {
        $parameter = 'session_id';
        $this->setConfig([
            'devices.transports.session_id.response_transport' => Transport::Session->value,
            'devices.transports.session_id.parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        Session::start();
        Session::put($parameter, $id);

        SessionTransport::forget();

        $this->assertNull(Session::get($parameter));
    }

    public function test_forget_session_from_cookie(): void
    {
        $parameter = 'session_id';
        $this->setConfig([
            'devices.transports.session_id.response_transport' => Transport::Cookie->value,
            'devices.transports.session_id.parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        Cookie::queue(
            Cookie::forever(
                name: $parameter,
                value: $id,
            ),
        );

        $cookies = Cookie::getQueuedCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $parameter) {
                $this->assertEquals($id, $cookie->getValue());
            }
        }

        SessionTransport::forget();

        $cookies = Cookie::getQueuedCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $parameter) {
                $this->assertNull($cookie->getValue());
            }
        }
    }

    public function test_make_returns_session_transport_instance(): void
    {
        $transport = SessionTransport::make(Transport::Cookie);
        $this->assertInstanceOf(SessionTransport::class, $transport);
    }
}
