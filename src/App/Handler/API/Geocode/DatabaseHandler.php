<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

class DatabaseHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $json = [
            'address' => $token['database']['address'],
            'poi'     => $token['database']['poi'],
        ];
        if ($token['debug'] === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
