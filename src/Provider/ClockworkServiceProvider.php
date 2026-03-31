<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork\Provider;

use Clockwork\DataSource\EloquentDataSource;
use Flarum\Settings\SettingsRepositoryInterface;
use Clockwork\DataSource\LaravelCacheDataSource;
use Clockwork\DataSource\LaravelEventsDataSource;
use Clockwork\DataSource\LaravelQueueDataSource;
use Clockwork\DataSource\LaravelRedisDataSource;
use Clockwork\DataSource\MonologDataSource;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Log;
use Clockwork\Support\Vanilla\Clockwork;
use Flarum\Foundation\Paths;
use Flarum\Group\Group;
use FoF\Clockwork\Clockwork\FlarumAuthenticator;
use FoF\Clockwork\Clockwork\FlarumDataSource;
use FoF\Clockwork\Clockwork\QueueJobTracker;
use FoF\Clockwork\Middleware\BeforeRouteExecutionMiddleware;
use FoF\Clockwork\Middleware\ClockworkMiddleware;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\ServiceProvider;

class ClockworkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['clockwork.queue.tracker']->listenToEvents();

        if ($this->runningInConsole()) {
            $this->app['clockwork.eloquent']->listenToEvents();
            $this->app['clockwork.cache']->listenToEvents();
            if ($this->redisAvailable()) {
                $this->enableRedisEvents();
                $this->app['clockwork.redis']->listenToEvents();
            }

            return;
        }

        $this->app['clockwork.eloquent']->listenToEvents();
        $this->app['clockwork.cache']->listenToEvents();
        $this->app['clockwork.flarum']->listenToEvents();
        $this->app['clockwork.events']->listenToEvents();

        if ($this->redisAvailable()) {
            $this->enableRedisEvents();
            $this->app['clockwork.redis']->listenToEvents();
        }

        $this->app['clockwork.queue']->listenToEvents();
        $this->app['clockwork.queue']->setCurrentRequestId(
            $this->app->make('clockwork')->getClockwork()->request()->id
        );

        // This is done to dispatch an event right before controller execution,
        // and after all middleware, including extension middleware.
        foreach (['admin', 'forum', 'api'] as $frontend) {
            $this->app->extend("flarum.$frontend.middleware", function ($middleware) {
                $middleware[] = BeforeRouteExecutionMiddleware::class;

                return $middleware;
            });
        }

        // Remove the clockwork middleware from API Client handling
        $this->app->extend('flarum.api_client.exclude_middleware', function (array $exclusions) {
            $exclusions[] = ClockworkMiddleware::class;

            return $exclusions;
        });
    }

    public function register()
    {
        $this->app->singleton('clockwork.queue', function ($app) {
            /** @var \Illuminate\Queue\Queue $connection */
            $connection = $app->make('flarum.queue.connection');

            return new LaravelQueueDataSource($connection);
        });

        $this->app->singleton('clockwork.queue.tracker', function ($app) {
            return new QueueJobTracker($app);
        });

        $this->app->singleton('clockwork.redis', function ($app) {
            return new LaravelRedisDataSource($app['events']);
        });

        if ($this->runningInConsole()) {
            $this->app->singleton('clockwork.log', function () {
                return new Log();
            });

            $this->app->singleton('clockwork.eloquent', function ($app) {
                return new EloquentDataSource($app['db'], $app['events']);
            });

            $this->app->singleton('clockwork.cache', function ($app) {
                return new LaravelCacheDataSource($app['events']);
            });

            $this->app->singleton('clockwork', function ($app) {
                $paths = resolve(Paths::class);

                /** @var Clockwork|\Clockwork\Clockwork $clockwork */
                $clockwork = Clockwork::init([
                    'enable'             => true,
                    'storage_files_path' => $paths->storage.'/clockwork',
                ]);

                $clockwork
                    ->addDataSource(new MonologDataSource($app['log']))
                    ->addDataSource($app['clockwork.eloquent'])
                    ->addDataSource($app['clockwork.cache'])
                    ->addDataSource($app['clockwork.queue']);

                if ($this->redisAvailable()) {
                    $clockwork->addDataSource($app['clockwork.redis']);
                }

                return $clockwork;
            });

            return;
        }

        $this->app->singleton('clockwork.authenticator', function ($app) {
            return new FlarumAuthenticator(Group::ADMINISTRATOR_ID, $app->make(SettingsRepositoryInterface::class));
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
                'Flarum\\\\\\\\Event\\\\\\\\.+',
                'Flarum\\\\\\\\Api\\\\\\\\Event\\\\\\\\.+',
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
            // Resolve paths instead of using deprecated `storage_path(...)`
            $paths = resolve(Paths::class);

            /** @var Clockwork|\Clockwork\Clockwork $clockwork */
            $clockwork = Clockwork::init([
                'enable'             => true,
                'storage_files_path' => $paths->storage.'/clockwork',
            ]);

            $clockwork->setAuthenticator($app['clockwork.authenticator']);

            $clockwork
                ->addDataSource(new MonologDataSource($app['log']))
                ->addDataSource($app['clockwork.eloquent'])
                ->addDataSource($app['clockwork.cache'])
                ->addDataSource($app['clockwork.events'])
                ->addDataSource($app['clockwork.flarum']);

            $clockwork->addDataSource($app['clockwork.queue']);

            if ($this->redisAvailable()) {
                $clockwork->addDataSource($app['clockwork.redis']);
            }

            if (in_array('xdebug', get_loaded_extensions())) {
                $clockwork->addDataSource($app['clockwork.xdebug']);
            }

            return $clockwork;
        });
    }

    protected function redisAvailable(): bool
    {
        return $this->app->bound(RedisFactory::class);
    }

    protected function enableRedisEvents(): void
    {
        $manager = $this->app->make(RedisFactory::class);

        if ($manager instanceof RedisManager) {
            $manager->enableEvents();
        }
    }

    protected function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli';
    }
}
