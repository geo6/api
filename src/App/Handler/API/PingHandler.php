<?php

declare(strict_types=1);

namespace App\Handler\API;

use App\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function time;
use Zend\Diactoros\Response\JsonResponse;

class PingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);
        $server = $request->getServerParams();

        return new JsonResponse([
            'now'        => time(),
            'token'      => $token,
            'ip'         => $server['REMOTE_ADDR'] ?? null,
            'referer'    => $server['HTTP_REFERER'] ?? null,
            'user-agent' => $server['HTTP_USER_AGENT'] ?? null,
        ]);
    }
}
