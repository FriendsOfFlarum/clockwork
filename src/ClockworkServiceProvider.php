<?php

namespace Reflar\Clockwork;


use Clockwork\Authentication\SimpleAuthenticator;
use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\LaravelCacheDataSource;
use Clockwork\DataSource\LaravelEventsDataSource;
use Clockwork\DataSource\MonologDataSource;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Log;
use Clockwork\Support\Laravel\ClockworkSupport;
use Clockwork\Support\Vanilla\Clockwork;
use Flarum\Group\Group;
use Illuminate\Support\ServiceProvider;
use Reflar\Clockwork\Clockwork\FlarumAuthenticator;
use Reflar\Clockwork\Clockwork\FlarumDataSource;

class ClockworkServiceProvider extends ServiceProvider
{

    public function boot() {
        $this->app['clockwork.eloquent']->listenToEvents();
        $this->app['clockwork.cache']->listenToEvents();
        $this->app['clockwork.flarum']->listenToEvents();
        $this->app['clockwork.events']->listenToEvents();
    }

    public function register() {

        $this->app->singleton('clockwork.support', function ($app) {
            return new ClockworkSupport($app);
        });

        $this->app->singleton('clockwork.authenticator', function () {
            return new FlarumAuthenticator(Group::ADMINISTRATOR_ID);
//            return new SimpleAuthenticator('hello');
        });

        $this->app->singleton('clockwork.log', function () {
            return (new Log)->collectStackTraces();
        });

        $this->app->singleton('clockwork.eloquent', function ($app) {
            return (new EloquentDataSource($app['db'], $app['events']))
                ->collectStackTraces();
        });

        $this->app->singleton('clockwork.cache', function ($app) {
            return (new LaravelCacheDataSource($app['events']))
                ->collectStackTraces();
        });

        $this->app->singleton('clockwork.events', function ($app) {
            return (new LaravelEventsDataSource($app['events'], [
                'Flarum\\\\Event\\\\.+',
                'Flarum\\\\Api\\\\Event\\\\.+',
            ]))
                ->collectStackTraces(false);
        });

        $this->app->singleton('clockwork.xdebug', function () {
            return new XdebugDataSource;
        });

        $this->app->singleton('clockwork.flarum', function ($app) {
            return (new FlarumDataSource($app))
                ->collectViews()
                ->setLog($app['clockwork.log']);
        });

        $this->app['clockwork.flarum']->listenToEarlyEvents();

        $this->app->singleton('clockwork', function ($app) {
            /**
             * @var $clockwork Clockwork
             */
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