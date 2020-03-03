<?php

declare(strict_types=1);

namespace App\Handler\API\Address;

use App\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

class ParseHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $address = $request->getAttribute('address');

        $expanded = \Postal\Expand::expand_address($address);
        $parsed = \Postal\Parser::parse_address($expanded[0]);

        $json = [
            'query' => [
                'address' => $address,
            ],
            'components' => $parsed,
        ];
        if ($token['debug'] === true) {
            $json['token'] = $token;
        }

        return new JsonResponse($json);
    }
}
