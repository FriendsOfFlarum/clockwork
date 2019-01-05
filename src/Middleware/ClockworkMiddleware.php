<?php

/*
 * This file is part of reflar/clockwork.
 *
 * Copyright (c) 2018 ReFlar.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Reflar\Clockwork\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClockworkMiddleware implements MiddlewareInterface
{

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        app('events')->fire('clockwork.controller.start');

        $response = $handler->handle($request);

        app('events')->fire('clockwork.controller.end');

        app('clockwork.flarum')
            ->setRequest($request)
            ->setResponse($response);

//        return new JsonResponse(app('clockwork.flarum')->count);

        return app('clockwork')
            ->usePsrMessage($request, $response)
            ->requestProcessed();
    }
}