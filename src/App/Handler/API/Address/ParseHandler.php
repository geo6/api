<?php

declare(strict_types=1);

namespace App\Handler\API\Address;

use App\Handler\API\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ParseHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $address = $request->getAttribute('address');

        $expanded = \Postal\Expand::expand_address($address);
        $parsed = \Postal\Parser::parse_address($expanded[0]);

        $json = [
            'query' => [
                'address' => $address,
            ],
            'components' => $parsed,
        ];

        return self::response($request, $json);
    }
}
