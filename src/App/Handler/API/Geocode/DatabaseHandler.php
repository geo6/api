<?php

declare(strict_types=1);

namespace App\Handler\API\Geocode;

use App\Middleware\DbAdapterMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Db\Metadata\Metadata;
use Zend\Diactoros\Response\JsonResponse;

class DatabaseHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $adapter = $request->getAttribute(DbAdapterMiddleware::DBADAPTER_ATTRIBUTE);

        $metadata = new Metadata($adapter);
        $sources_poi = $metadata->getTableNames('poi');

        return new JsonResponse([
            'address' => [
                'crab',
                'urbis',
            ],
            'poi' => $sources_poi,
        ]);
    }
}
