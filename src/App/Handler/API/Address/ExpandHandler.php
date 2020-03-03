<?php

declare(strict_types=1);

namespace App\Handler\API\Address;

use App\Middleware\TokenMiddleware;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExpandHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $address = $request->getAttribute('address');

        $expanded = \Postal\Expand::expand_address($address);

        $json = [
            'query' => [
                'address' => $address,
            ],
            'address' => $expanded,
        ];
        if ($token['debug'] === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
