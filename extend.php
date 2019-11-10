<?php

/*
 * This file is part of reflar/clockwork.
 *
 * Copyright (c) 2018 ReFlar.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Reflar\Clockwork;

use Flarum\Event\ConfigureMiddleware;
use Flarum\Extend;
use Flarum\Foundation\Application;
use Flarum\Frontend\Document;
use Illuminate\Events\Dispatcher;

return [
    (new Extend\Frontend('forum'))
        ->content(function (Document $document) {
            app('clockwork.flarum')->addDocumentData($document);
        }),
    (new Extend\Frontend('admin'))
        ->content(function (Document $document) {
            app('clockwork.flarum')->addDocumentData($document);
        }),
    (new Extend\Routes('forum'))
        ->get('/__clockwork[/]', 'reflar.clockwork.app', Controllers\ClockworkRedirectController::class)
        ->get('/__clockwork/app', 'reflar.clockwork.app', Controllers\ClockworkWebController::class)
        ->get('/__clockwork/{folder:(?:css|img|js)}/{path:.+}', 'reflar.clockwork.asset', Controllers\ClockworkAssetController::class)
        ->post('/__clockwork/auth', 'reflar.clockwork.auth', Controllers\ClockworkAuthController::class)
        ->get('/__clockwork/{request:.+}', 'reflar.clockwork.request', Controllers\ClockworkController::class),
    function (Application $app, Dispatcher $events) {
        if ($app->runningInConsole()) {
            return;
        }

        $app->register(ClockworkServiceProvider::class);

        $events->listen(ConfigureMiddleware::class, function (ConfigureMiddleware $event) {
            $event->pipe(app(Middleware\ClockworkMiddleware::class));
        });

        $app['clockwork.flarum']->listenToEarlyEvents();
    },
];
