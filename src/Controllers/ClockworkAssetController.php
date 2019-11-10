<?php

namespace FoF\Clockwork\Controllers;

use Clockwork\Web\Web;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\User\Exception\PermissionDeniedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class ClockworkAssetController implements RequestHandlerInterface
{
    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!app('clockwork.authenticator')->check($request)) {
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
