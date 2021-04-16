<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork\Middleware;

use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClockworkMiddleware implements MiddlewareInterface
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strpos($request->getUri()->getPath(), '/__clockwork') !== false || !$this->container->bound('clockwork.flarum')) {
            return $handler->handle($request);
        }

        $this->container['events']->dispatch('clockwork.running.end');
        $this->container['events']->dispatch('clockwork.middleware.start');

        $response = $handler->handle($request);

        $this->container['events']->dispatch('clockwork.middleware.end');

        $requestHandler = $request->getAttribute('request-handler');
        $uri = $request->getUri();

        if ($requestHandler == 'flarum.api.middleware') {
            $request = $request->withUri($uri->withPath('/api'.$uri->getPath()));
        } elseif ($requestHandler == 'flarum.admin.middleware') {
            $request = $request->withUri($uri->withPath('/admin'.$uri->getPath()));
        }

        $this->container['clockwork.flarum']
            ->setRequest($request)
            ->setResponse($response);

        if (!$this->container['clockwork.authenticator']->check($request)) {
            return $response;
        }

        return $this->container['clockwork']
            ->usePsrMessage($request, $response)
            ->requestProcessed();
    }
}
