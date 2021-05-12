<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Handler\API\AbstractHandler;
use App\Middleware\TokenMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DatabaseHandler extends AbstractHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute(TokenMiddleware::TOKEN_ATTRIBUTE);

        $json = [
            'address' => $token['database']['address'],
            'poi'     => $token['database']['poi'],
        ];

        return self::response($request, $json);
    }
}
