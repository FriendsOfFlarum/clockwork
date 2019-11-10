<?php

namespace Reflar\Clockwork\Controllers;

use Flarum\Http\Exception\RouteNotFoundException;
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
        $authenticator = app('clockwork.authenticator');
        $authenticated = $authenticator->check($request);

        if (!$authenticated) {
            return new JsonResponse([
                'message'  => app('translator')->trans('core.lib.error.permission_denied_message'),
                'requires' => $authenticator->requires(),
            ], 403);
        }

        $metadata = app('clockwork')->getMetadata($request->getQueryParams()['request']);

        if ($metadata == null) {
            throw new RouteNotFoundException();
        }

        return new JsonResponse($metadata);
    }
}
