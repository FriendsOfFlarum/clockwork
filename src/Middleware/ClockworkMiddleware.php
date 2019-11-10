<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork\Middleware;

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
        if (strpos($request->getUri()->getPath(), '/__clockwork') !== false) {
            return $handler->handle($request);
        }

        app('events')->fire('clockwork.running.end');
        app('events')->fire('clockwork.controller.start');

        $response = $handler->handle($request);

        app('events')->fire('clockwork.controller.end');

        $requestHandler = $request->getAttribute('request-handler');
        $uri = $request->getUri();

        if ($requestHandler == 'flarum.api.middleware') {
            $request = $request->withUri($uri->withPath('/api'.$uri->getPath()));
        } else if ($requestHandler == 'flarum.admin.middleware') {
            $request = $request->withUri($uri->withPath('/admin'.$uri->getPath()));
        }

        app('clockwork.flarum')
            ->setRequest($request)
            ->setResponse($response);

        if (!app('clockwork.authenticator')->check($request)) {
            return $response;
        }

        return app('clockwork')
            ->usePsrMessage($request, $response)
            ->requestProcessed();
    }
}
