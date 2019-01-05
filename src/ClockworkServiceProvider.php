<?php

namespace Reflar\Clockwork;


use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\LaravelCacheDataSource;
use Clockwork\DataSource\LaravelEventsDataSource;
use Clockwork\DataSource\MonologDataSource;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Log;
use Clockwork\Support\Laravel\ClockworkSupport;
use Clockwork\Support\Vanilla\Clockwork;
use Illuminate\Support\ServiceProvider;
use Reflar\Clockwork\Clockwork\FlarumDataSource;

class ClockworkServiceProvider extends ServiceProvider
{

    public function boot() {
        $this->app['clockwork.eloquent']->listenToEvents();
        $this->app['clockwork.cache']->listenToEvents();
        $this->app['clockwork.flarum']->listenToEvents();
//        $this->app['clockwork.events']->listenToEvents();
    }

    public function register() {
//        $app->alias('log', Log::class);

        $this->app->singleton('clockwork.support', function ($app) {
            return new ClockworkSupport($app);
        });

        $this->app->singleton('clockwork.log', function () {
            return (new Log)->collectStackTraces(true);
        });

        $this->app->singleton('clockwork.eloquent', function ($app) {
            return (new EloquentDataSource($app['db'], $app['events']))
                ->collectStackTraces(true);
        });

        $this->app->singleton('clockwork.cache', function ($app) {
            return (new LaravelCacheDataSource($app['events']))
                ->collectStackTraces(true);
        });

        $this->app->singleton('clockwork.events', function ($app) {
            return (new LaravelEventsDataSource($app['events'], [
                'Flarum\\\\Foundation\\\\.+',
                'Flarum\\\\Event\\\\.+',
            ]))
                ->collectStackTraces(true);
        });

        $this->app->singleton('clockwork.xdebug', function () {
            return new XdebugDataSource;
        });

        $this->app->singleton('clockwork.flarum', function ($app) {
            return (new FlarumDataSource($app))
                ->collectViews(true)
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

            $clockwork
                ->addDataSource(new MonologDataSource($app['log']))
                ->addDataSource($app['clockwork.flarum'])
                ->addDataSource($app['clockwork.eloquent'])
                ->addDataSource($app['clockwork.cache'])
                ->addDataSource($app['clockwork.events']);

            if (in_array('xdebug', get_loaded_extensions())) {
                $clockwork->addDataSource($app['clockwork.xdebug']);
            }

            return $clockwork;
        });
    }
}