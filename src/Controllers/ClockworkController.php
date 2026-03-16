<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork\Controllers;

use Clockwork\Storage\Search;
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

        if (!$authenticated) {
            return new JsonResponse([
                'message'  => $this->container['translator']->trans('core.lib.error.permission_denied_message'),
                'requires' => $authenticator->requires(),
            ], 403);
        }

        // Flarum merges route params into query params (RouteHandlerFactory).
        // The {request:.+} route param captures e.g. "latest", "{id}", "{id}/previous", "{id}/next/50"
        $path  = $request->getQueryParams()['request'] ?? '';
        $parts = explode('/', $path);

        $id        = $parts[0] ?? null;
        $direction = $parts[1] ?? null;
        $count     = isset($parts[2]) ? (int) $parts[2] : null;

        $storage = $this->container['clockwork']->getClockwork()->storage();
        $search  = Search::fromRequest($request->getQueryParams());

        if ($direction === 'previous') {
            $data = $storage->previous($id, $count, $search);
        } elseif ($direction === 'next') {
            $data = $storage->next($id, $count, $search);
        } elseif ($id === 'latest') {
            $data = $storage->latest($search);
        } else {
            $data = $storage->find($id);
        }

        if ($data === null || $data === false) {
            return new JsonResponse(['message' => 'Not found.'], 404);
        }

        if (is_array($data)) {
            $data = array_map(fn ($r) => $r->toArray(), $data);
        } else {
            $data = $data->toArray();
        }

        return new JsonResponse($data);
    }
}
