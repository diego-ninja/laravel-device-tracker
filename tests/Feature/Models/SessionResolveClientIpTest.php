<?php

namespace Ninja\DeviceTracker\Tests\Feature\Models;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Ninja\DeviceTracker\Models\Session;
use Ninja\DeviceTracker\Tests\FeatureTestCase;

class SessionResolveClientIpTest extends FeatureTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['env'] = 'local';
    }

    public function test_resolve_client_ip_is_stable_across_calls_for_same_request(): void
    {
        Config::set('devices.development_ip_pool', ['203.0.113.1', '203.0.113.2', '203.0.113.3']);
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.50']);
        $this->app->instance('request', $request);

        $first = Session::resolveClientIp();
        $second = Session::resolveClientIp();

        $this->assertSame($first, $second);
        $this->assertContains($first, ['203.0.113.1', '203.0.113.2', '203.0.113.3']);
    }

    public function test_resolve_client_ip_falls_back_when_pool_empty(): void
    {
        Config::set('devices.development_ip_pool', []);
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $this->app->instance('request', $request);

        $ip = Session::resolveClientIp();

        $this->assertSame('10.0.0.1', $ip);
    }

    public function test_resolve_client_ip_uses_placeholder_when_request_ip_missing_and_pool_empty(): void
    {
        Config::set('devices.development_ip_pool', []);
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '']);
        $this->app->instance('request', $request);

        $this->assertSame('0.0.0.0', Session::resolveClientIp());
    }

    public function test_non_local_uses_placeholder_when_request_ip_missing(): void
    {
        $previousEnv = $this->app['env'];
        try {
            $this->app['env'] = 'production';
            $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '']);
            $this->app->instance('request', $request);

            $this->assertSame('0.0.0.0', Session::resolveClientIp());
        } finally {
            $this->app['env'] = $previousEnv;
        }
    }
}
