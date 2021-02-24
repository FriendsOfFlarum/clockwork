<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork\Controllers;

use Flarum\Http\Exception\RouteNotFoundException;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClockworkController implements RequestHandlerInterface
{
    /**
     * @var Container
     */
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authenticator = $this->container['clockwork.authenticator'];
        $authenticated = $authenticator->check($request);

        if (! $authenticated) {
            return new JsonResponse([
                'message'  => $this->container['translator']->trans('core.lib.error.permission_denied_message'),
                'requires' => $authenticator->requires(),
            ], 403);
        }

        $req = $request->getQueryParams()['request'];
        $metadata = $this->container['clockwork']->getMetadata($req);

        if ($metadata == null) {
            throw new RouteNotFoundException();
        }

        return new JsonResponse($metadata);
    }
}
