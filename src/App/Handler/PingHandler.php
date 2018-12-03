<?php

declare (strict_types = 1);

namespace App\Handler;

use App\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;
use function time;

class PingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        return new JsonResponse([
            'ack' => time(),
            'token' => $token,
        ]);
    }
}
