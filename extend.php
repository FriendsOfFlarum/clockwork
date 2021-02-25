<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork;

use Flarum\Extend;
use FoF\Clockwork\AddFrontendData;
use FoF\Clockwork\Extend\FileStoragePath;

return [
    (new FileStoragePath()),

    (new Extend\Frontend('forum'))
        ->content(AddFrontendData::class),

    (new Extend\Frontend('admin'))
        ->content(AddFrontendData::class),

    (new Extend\ApiController(\Flarum\Api\Controller\AbstractSerializeController::class))
        ->prepareDataQuery(AddFrontendData::class),

    (new Extend\Routes('forum'))
        ->get('/__clockwork[/]', 'fof.clockwork.app', Controllers\ClockworkRedirectController::class)
        ->get('/__clockwork/app', 'fof.clockwork.app', Controllers\ClockworkWebController::class)
        ->get('/__clockwork/{folder:(?:css|img|js)}/{path:.+}', 'fof.clockwork.asset', Controllers\ClockworkAssetController::class)
        ->post('/__clockwork/auth', 'fof.clockwork.auth', Controllers\ClockworkAuthController::class)
        ->get('/__clockwork/{request:.+}', 'fof.clockwork.request', Controllers\ClockworkController::class),

    (new Extend\ServiceProvider())
        ->register(ClockworkServiceProvider::class),

    (new Extend\Middleware('forum'))
        ->add(Middleware\ClockworkMiddleware::class),

    (new Extend\Middleware('admin'))
        ->add(Middleware\ClockworkMiddleware::class),

    (new Extend\Middleware('api'))
        ->add(Middleware\ClockworkMiddleware::class),
];
