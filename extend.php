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
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less')
        ->content(function (Document $document) {
            app('clockwork.flarum')->addDocumentData($document);
        }),
    (new Extend\Frontend('admin'))
        ->content(function (Document $document) {
            app('clockwork.flarum')->addDocumentData($document);
        }),
    (new Extend\Routes('forum'))
        ->post('/__clockwork/auth', 'reflar.clockwork.auth', Controllers\ClockworkAuthController::class)
        ->get('/__clockwork/{request:.+}', 'reflar.clockwork', Controllers\ClockworkController::class),
//    (new Extend\Frontend('admin'))
//        ->js(__DIR__.'/js/dist/admin.js')
//        ->css(__DIR__.'/resources/less/admin.less'),
//    new Extend\Locales(__DIR__ . '/resources/locale'),
    function (Application $app, Dispatcher $events) {
        $app->register(ClockworkServiceProvider::class);

        $events->listen(ConfigureMiddleware::class, function (ConfigureMiddleware $event) {
            $event->pipe(app(Middleware\ClockworkMiddleware::class));
        });
    }
];
