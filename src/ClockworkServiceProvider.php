<?php

namespace FoF\Clockwork;

use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\LaravelCacheDataSource;
use Clockwork\DataSource\LaravelEventsDataSource;
use Clockwork\DataSource\MonologDataSource;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Log;
use Clockwork\Support\Laravel\ClockworkSupport;
use Clockwork\Support\Vanilla\Clockwork;
use Flarum\Group\Group;
use FoF\Clockwork\Clockwork\FlarumAuthenticator;
use FoF\Clockwork\Clockwork\FlarumDataSource;
use Illuminate\Support\ServiceProvider;

class ClockworkServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app['clockwork.eloquent']->listenToEvents();
        $this->app['clockwork.cache']->listenToEvents();
        $this->app['clockwork.flarum']->listenToEvents();
        $this->app['clockwork.events']->listenToEvents();
    }

    public function register()
    {
        $this->app->singleton('clockwork.support', function ($app) {
            return new ClockworkSupport($app);
        });

        $this->app->singleton('clockwork.authenticator', function () {
            return new FlarumAuthenticator(Group::ADMINISTRATOR_ID);
        });

        $this->app->singleton('clockwork.log', function () {
            return new Log();
        });

        $this->app->singleton('clockwork.eloquent', function ($app) {
            return new EloquentDataSource($app['db'], $app['events']);
        });

        $this->app->singleton('clockwork.cache', function ($app) {
            return new LaravelCacheDataSource($app['events']);
        });

        $this->app->singleton('clockwork.events', function ($app) {
            return new LaravelEventsDataSource($app['events'], [
                'Flarum\\\\Event\\\\.+',
                'Flarum\\\\Api\\\\Event\\\\.+',
            ]);
        });

        $this->app->singleton('clockwork.xdebug', function () {
            return new XdebugDataSource();
        });

        $this->app->singleton('clockwork.flarum', function ($app) {
            return (new FlarumDataSource($app))
                ->setLog($app['clockwork.log']);
        });

        $this->app['clockwork.flarum']->listenToEarlyEvents();

        $this->app->singleton('clockwork', function ($app) {
            /** @var Clockwork|\Clockwork\Clockwork $clockwork */
            $clockwork = Clockwork::init([
                'enable' => true,
            ]);

            $clockwork->setAuthenticator($app['clockwork.authenticator']);

            $clockwork
                ->addDataSource(new MonologDataSource($app['log']))
                ->addDataSource($app['clockwork.eloquent'])
                ->addDataSource($app['clockwork.cache'])
                ->addDataSource($app['clockwork.events'])
                ->addDataSource($app['clockwork.flarum']);

            if (in_array('xdebug', get_loaded_extensions())) {
                $clockwork->addDataSource($app['clockwork.xdebug']);
            }

            return $clockwork;
        });
    }
}
