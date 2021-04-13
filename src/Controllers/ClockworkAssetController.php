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

use Clockwork\Web\Web;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ClockworkAssetController implements RequestHandlerInterface
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
        if (!$this->container['clockwork.authenticator']->check($request)) {
            throw new PermissionDeniedException();
        }

        $asset = (new Web())->asset($request->getQueryParams()['folder'].'/'.$request->getQueryParams()['path']);

        if ($asset == null) {
            throw new RouteNotFoundException();
        }

        $path = $asset['path'];
        $mime = strpos($path, 'img'.DIRECTORY_SEPARATOR) !== false ? 'image' : $asset['mime'];

        return new Response(
            new Stream($asset['path']),
            200,
            ['Content-Type' => $mime]
        );
    }
}
