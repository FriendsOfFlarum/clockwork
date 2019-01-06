<?php

namespace Reflar\Clockwork\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class ClockworkController implements RequestHandlerInterface
{

    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authenticator = app('clockwork')->getAuthenticator();
        $authenticated = $authenticator->check($request->getHeaderLine('X-Clockwork-Auth'));

        if ($authenticated !== true) {
            return new JsonResponse([ 'message' => $authenticated, 'requires' => $authenticator->requires() ], 403);
        }

        return new JsonResponse(
            app('clockwork')->getMetadata($request->getQueryParams()['request'])
        );
    }
}