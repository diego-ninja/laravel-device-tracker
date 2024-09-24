<?php

namespace Ninja\DeviceTracker;

use Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ninja\DeviceTracker\Contracts\DeviceDetector;
use Ninja\DeviceTracker\Contracts\LocationProvider;
use Ninja\DeviceTracker\Middleware\SessionTracker;

class DeviceTrackerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMiddlewares();

        if (Config::get('devices.load_routes')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/devices.php');
        }
    }

    public function register(): void
    {
        $config = __DIR__ . '/../config/devices.php';
        $this->mergeConfigFrom(
            path: $config,
            key: 'devices'
        );

        $this->app->singleton(LocationProvider::class, function () {
            return new IpinfoLocationProvider();
        });

        $this->app->singleton(DeviceDetector::class, function () {
            return new UserAgentDeviceDetector();
        });

        $this->registerFacades();
        $this->registerAuthenticationEventHandler();
    }

    private function registerMiddlewares(): void
    {
        $router = $this->app['router'];
        $router->middleware('session', SessionTracker::class);
    }

    private function registerFacades(): void
    {
        $this->app->bind('device_manager', function ($app) {
            return new DeviceManager($app);
        });

        $this->app->bind('session_manager', function ($app) {
            return new SessionManager($app);
        });
    }

    private function registerAuthenticationEventHandler(): void
    {
        Event::subscribe(AuthenticationHandler::class);
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/devices.php' => config_path('devices.php')], 'config');

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations')
            ], 'device-tracker-migrations');
        }
    }
}
