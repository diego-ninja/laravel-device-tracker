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
use Ninja\DeviceTracker\Transports\FingerprintTransport;
use PHPUnit\Framework\Attributes\DataProvider;

class FingerprintTransportTest extends FeatureTestCase
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
        $parameter = 'fingerprint';
        $this->setConfig([
            'devices.transports.fingerprint.transport_hierarchy' => $hierarchy,
            'devices.transports.fingerprint.parameter' => $parameter,
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

        $transport = FingerprintTransport::current();

        $this->assertEquals(FingerprintTransport::make($expectedTransport), $transport);

        $id = FingerprintTransport::currentId();

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
            'devices.transports.fingerprint.transport_hierarchy' => [Transport::Cookie->value],
            'devices.transports.fingerprint.parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        request()->cookies->set($parameter, $id);

        $transport = FingerprintTransport::current();

        $this->assertEquals(FingerprintTransport::make(Transport::Cookie), $transport);

        $storableId = FingerprintTransport::currentId();

        $this->assertTrue($storableId instanceof StorableId);
        $this->assertEquals($id, $storableId);
    }

    public function test_current_with_encrypted_cookie(): void
    {
        $parameter = 'fingerprint';
        $key = 'base64:Lzrm+AkE+RrRJWDHON58e8unP7LBK6PlyyLo5k4i6Q0=';
        $this->setConfig([
            'devices.transports.fingerprint.transport_hierarchy' => [Transport::Cookie->value],
            'devices.transports.fingerprint.parameter' => $parameter,
            'app.key' => $key,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';
        $encrypter = new Encrypter(base64_decode(Str::after($key, 'base64:')), 'AES-256-CBC');
        $encryptedCookie = $encrypter->encrypt(
            value: CookieValuePrefix::create($parameter, $encrypter->getKey()).$id,
            serialize: false,
        );

        request()->cookies->set($parameter, $encryptedCookie);

        $transport = FingerprintTransport::current();

        $this->assertEquals(FingerprintTransport::make(Transport::Cookie), $transport);

        $storableId = FingerprintTransport::currentId();

        $this->assertTrue($storableId instanceof StorableId);
        $this->assertEquals($id, $storableId);
    }

    public function test_current_from_alternative_parameter(): void
    {
        $parameter = 'fingerprint';
        $this->setConfig([
            'devices.transports.fingerprint.transport_hierarchy' => [Transport::Cookie->value],
            'devices.transports.fingerprint.parameter' => 'invalid_parameter',
            'devices.transports.fingerprint.alternative_parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        request()->cookies->set($parameter, $id);

        $transport = FingerprintTransport::current();

        $this->assertEquals(FingerprintTransport::make(Transport::Cookie), $transport);

        $storableId = FingerprintTransport::currentId();

        $this->assertTrue($storableId instanceof StorableId);
        $this->assertEquals($id, $storableId);
    }

    public function test_forget_session_from_session(): void
    {
        $parameter = 'fingerprint';
        $this->setConfig([
            'devices.transports.fingerprint.response_transport' => Transport::Session->value,
            'devices.transports.fingerprint.parameter' => $parameter,
        ]);
        $id = 'f765e4d4-a990-4c59-aeed-d16f0aed2665';

        Session::start();
        Session::put($parameter, $id);

        FingerprintTransport::forget();

        $this->assertNull(Session::get($parameter));
    }

    public function test_forget_session_from_cookie(): void
    {
        $parameter = 'fingerprint';
        $this->setConfig([
            'devices.transports.fingerprint.response_transport' => Transport::Cookie->value,
            'devices.transports.fingerprint.parameter' => $parameter,
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

        FingerprintTransport::forget();

        $cookies = Cookie::getQueuedCookies();
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $parameter) {
                $this->assertNull($cookie->getValue());
            }
        }
    }

    public function test_make_returns_fingerprint_transport_instance(): void
    {
        $transport = FingerprintTransport::make(Transport::Cookie);
        $this->assertInstanceOf(FingerprintTransport::class, $transport);
    }
}
