<?php
/**
 * Created by PhpStorm.
 * User: david
 * Date: 1/3/19
 * Time: 7:49 PM
 */

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
        return new JsonResponse(
            app('clockwork')->getMetadata($request->getQueryParams()['request'])
        );
    }
}